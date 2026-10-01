<?php
require_once __DIR__ . '/Session.php';

class Cart
{
    private $db;
    private $userId;

    public function __construct($db, $userId = null)
    {
        $this->db = $db;
        $this->userId = $userId ? (int) $userId : null;
    }

    public function getItems($lock = false)
    {
        if ($this->userId !== null) {
            $sql = "SELECT ci.id AS cart_item_id, ci.product_id AS id, ci.quantity AS qty,
                           p.name, p.slug, p.price, p.image, p.stock, p.status
                    FROM cart_items ci
                    LEFT JOIN products p ON p.id = ci.product_id
                    WHERE ci.user_id = ?
                    ORDER BY ci.created_at ASC";
            if ($lock) {
                $sql .= ' FOR UPDATE';
            }
            $items = $this->db->select($sql, [$this->userId]);
        } else {
            $guestCart = Session::get('cart', []);
            if (empty($guestCart) || !is_array($guestCart)) {
                return [];
            }

            $productIds = array_filter(array_map('intval', array_keys($guestCart)), function ($id) {
                return $id > 0;
            });
            if (!$productIds) {
                return [];
            }

            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $products = $this->db->select(
                "SELECT id, name, slug, price, image, stock, status FROM products WHERE id IN ($placeholders)",
                array_values($productIds)
            );
            $items = [];
            foreach ($products as $product) {
                $productId = (int) $product['id'];
                $product['cart_item_id'] = $productId;
                $product['qty'] = (int) ($guestCart[$productId] ?? 0);
                $items[] = $product;
            }
        }

        foreach ($items as &$item) {
            $item['id'] = (int) $item['id'];
            $item['qty'] = (int) $item['qty'];
            $item['stock'] = (int) ($item['stock'] ?? 0);
            $item['cart_item_id'] = (int) $item['cart_item_id'];
            $item['cart_key'] = $item['cart_item_id'];
            $item['available'] = isset($item['status']) && (int) $item['status'] === 1 && $item['stock'] > 0;
            $item['name'] = $item['name'] ?? 'Unavailable product';
            $item['slug'] = $item['slug'] ?? '';
            $item['image'] = $item['image'] ?? '';
            $item['price'] = (float) ($item['price'] ?? 0);
            $item['line_total'] = $item['available'] ? $item['price'] * $item['qty'] : 0.0;
        }
        unset($item);

        return $items;
    }

    public function getCount()
    {
        if ($this->userId !== null) {
            $row = $this->db->selectOne(
                'SELECT COALESCE(SUM(quantity), 0) AS quantity FROM cart_items WHERE user_id = ?',
                [$this->userId]
            );
            return (int) ($row['quantity'] ?? 0);
        }

        return array_sum(array_map('intval', Session::get('cart', [])));
    }

    public function add($productId, $quantity)
    {
        $productId = (int) $productId;
        $quantity = (int) $quantity;
        if ($productId <= 0 || $quantity <= 0) {
            return false;
        }

        $product = $this->db->selectOne(
            'SELECT id, stock FROM products WHERE id = ? AND status = 1',
            [$productId]
        );
        if (!$product || (int) $product['stock'] <= 0) {
            return false;
        }

        $stock = (int) $product['stock'];
        if ($this->userId !== null) {
            $this->db->run(
                'INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + VALUES(quantity), ?)',
                [$this->userId, $productId, min($quantity, $stock), $stock]
            );
            return true;
        }

        $guestCart = Session::get('cart', []);
        $guestCart[$productId] = min((int) ($guestCart[$productId] ?? 0) + $quantity, $stock);
        Session::set('cart', $guestCart);
        return true;
    }

    public function update($cartItemId, $quantity)
    {
        $cartItemId = (int) $cartItemId;
        $quantity = (int) $quantity;
        if ($cartItemId <= 0 || $quantity <= 0) {
            return false;
        }

        if ($this->userId !== null) {
            $item = $this->db->selectOne(
                'SELECT ci.product_id, p.stock, p.status FROM cart_items ci
                 JOIN products p ON p.id = ci.product_id
                 WHERE ci.id = ? AND ci.user_id = ?',
                [$cartItemId, $this->userId]
            );
            if (!$item || (int) $item['status'] !== 1 || (int) $item['stock'] <= 0) {
                return false;
            }
            return $this->db->run(
                'UPDATE cart_items SET quantity = ? WHERE id = ? AND user_id = ?',
                [min($quantity, (int) $item['stock']), $cartItemId, $this->userId]
            ) > 0;
        }

        $guestCart = Session::get('cart', []);
        if (!isset($guestCart[$cartItemId])) {
            return false;
        }
        $product = $this->db->selectOne(
            'SELECT stock FROM products WHERE id = ? AND status = 1',
            [$cartItemId]
        );
        if (!$product || (int) $product['stock'] <= 0) {
            return false;
        }
        $guestCart[$cartItemId] = min($quantity, (int) $product['stock']);
        Session::set('cart', $guestCart);
        return true;
    }

    public function remove($cartItemId)
    {
        $cartItemId = (int) $cartItemId;
        if ($cartItemId <= 0) {
            return false;
        }
        if ($this->userId !== null) {
            return $this->db->run(
                'DELETE FROM cart_items WHERE id = ? AND user_id = ?',
                [$cartItemId, $this->userId]
            ) > 0;
        }

        $guestCart = Session::get('cart', []);
        unset($guestCart[$cartItemId]);
        Session::set('cart', $guestCart);
        return true;
    }

    public function clear()
    {
        if ($this->userId !== null) {
            return $this->db->run('DELETE FROM cart_items WHERE user_id = ?', [$this->userId]);
        }
        Session::set('cart', []);
        return true;
    }

    public function mergeGuestCart()
    {
        if ($this->userId === null) {
            return;
        }

        $guestCart = Session::get('cart', []);
        if (empty($guestCart) || !is_array($guestCart)) {
            return;
        }

        $connection = $this->db->getConnection();
        $connection->begin_transaction();
        try {
            foreach ($guestCart as $productId => $quantity) {
                $productId = (int) $productId;
                $quantity = (int) $quantity;
                if ($productId <= 0 || $quantity <= 0) {
                    continue;
                }
                $product = $this->db->selectOne(
                    'SELECT stock FROM products WHERE id = ? AND status = 1',
                    [$productId]
                );
                if (!$product || (int) $product['stock'] <= 0) {
                    continue;
                }
                $stock = (int) $product['stock'];
                $this->db->run(
                    'INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + VALUES(quantity), ?)',
                    [$this->userId, $productId, min($quantity, $stock), $stock]
                );
            }
            $connection->commit();
            Session::set('cart', []);
        } catch (Throwable $e) {
            $connection->rollback();
            throw $e;
        }
    }
}