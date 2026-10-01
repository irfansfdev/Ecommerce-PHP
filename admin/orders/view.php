<?php
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/EmailService.php';
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/OrderStatus.php';
require_once __DIR__ . '/../../core/Session.php';
Auth::requireAdmin('../login.php');

$db = new Database();
$id = (int) ($_GET['id'] ?? 0);

$order = $db->selectOne(
    "SELECT o.*, u.name AS customer_name, u.email AS customer_email
     FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?",
    [$id]
);

if (!$order) {
    header('Location: index.php');
    exit;
}

// Update order/payment status.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = $_POST['order_status'] ?? $order['order_status'];
    $newPaymentStatus = $_POST['payment_status'] ?? '';

    $validPayment = ['pending', 'completed', 'failed'];
    $previousStatus = $order['order_status'];

    if (is_string($newStatus)
        && in_array($newPaymentStatus, $validPayment, true)
        && OrderStatus::isAllowed($previousStatus, $newStatus)
    ) {
        $newPaymentStatus = OrderStatus::paymentStatusForUpdate(
            $order['payment_method'],
            $newStatus,
            $newPaymentStatus
        );
        $conn = $db->getConnection();
        $conn->begin_transaction();
        $updated = 0;
        $transactionFailed = false;
        try {
            $updated = $db->run(
                "UPDATE orders SET order_status = ?, payment_status = ? WHERE id = ? AND order_status = ?",
                [$newStatus, $newPaymentStatus, $id, $previousStatus]
            );

            if ($updated > 0 && $newStatus === 'cancelled' && $previousStatus !== 'cancelled') {
                foreach ($db->select(
                    'SELECT product_id, quantity FROM order_items WHERE order_id = ?',
                    [$id]
                ) as $item) {
                    $db->run(
                        'UPDATE products SET stock = stock + ? WHERE id = ?',
                        [(int) $item['quantity'], (int) $item['product_id']]
                    );
                }
            }

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('Order status update failed: ' . $e->getMessage());
            $transactionFailed = true;
        }

        if (!$transactionFailed && ($updated > 0 || $newStatus === $previousStatus)) {
            if ($updated > 0 && $newStatus !== $previousStatus) {
                EmailService::sendOrderStatusUpdate($db, $id, $previousStatus);
            }
            Session::flash('success', 'Order updated.');
        } elseif ($transactionFailed) {
            Session::flash('error', 'The order could not be updated. Please try again.');
        } else {
            Session::flash('error', 'Order status changed before this update. Please review the current order status and try again.');
        }
    } else {
        Session::flash('error', 'That order status transition or payment status is not allowed.');
    }

    header('Location: view.php?id=' . $id);
    exit;
}

$items = $db->select(
    "SELECT oi.*, p.name, p.slug, p.image FROM order_items oi
     JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?",
    [$id]
);

$pageTitle = 'Order ' . $order['order_number'];
$activeNav = 'orders';
$base = '../';
$nextOrderStatuses = OrderStatus::nextStatuses($order['order_status']);
$statusBadge = [
    'processing' => 'bg-gradient-warning',
    'shipped' => 'bg-gradient-info',
    'delivered' => 'bg-gradient-success',
    'cancelled' => 'bg-gradient-secondary',
];
require_once __DIR__ . '/../../includes/admin-header.php';
?>
<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>Order Items - <?= htmlspecialchars($order['order_number']) ?></h6>
            </div>
            <div class="card-body px-0 pb-2">
                <div class="table-responsive">
                    <table class="table align-items-center mb-0">
                        <thead>
                            <tr>
                                <th class="text-uppercase text-xxs font-weight-bolder opacity-7">Product</th>
                                <th class="text-uppercase text-xxs font-weight-bolder opacity-7 text-center">Qty</th>
                                <th class="text-uppercase text-xxs font-weight-bolder opacity-7 text-center">Unit Price</th>
                                <th class="text-uppercase text-xxs font-weight-bolder opacity-7 text-center">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex px-2 py-1 align-items-center">
                                            <img src="<?= htmlspecialchars($base . '../public/' . shop_image($item['image'])) ?>" width="40" height="40" style="object-fit: cover; border-radius: 6px;" class="me-2" alt="">
                                            <span class="text-sm font-weight-bold"><?= htmlspecialchars($item['name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="text-sm text-center"><?= (int) $item['quantity'] ?></td>
                                    <td class="text-sm text-center">$<?= number_format($item['unit_price'], 2) ?></td>
                                    <td class="text-sm text-center">$<?= number_format($item['subtotal'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end fw-bold">Order Total</td>
                                <td class="text-center fw-bold">$<?= number_format($order['total_amount'], 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header pb-0"><h6>Shipping Address</h6></div>
            <div class="card-body">
                <p class="text-sm mb-0"><?= nl2br(htmlspecialchars($order['shipping_address'])) ?></p>
            </div>
        </div>
    </div>

    <div class="col-lg-4 mb-4">
        <div class="card mb-4">
            <div class="card-header pb-0"><h6>Customer</h6></div>
            <div class="card-body">
                <p class="text-sm mb-1"><strong><?= htmlspecialchars($order['customer_name']) ?></strong></p>
                <p class="text-sm text-secondary mb-0"><?= htmlspecialchars($order['customer_email']) ?></p>
            </div>
        </div>

        <div class="card">
            <div class="card-header pb-0"><h6>Payment & Fulfillment</h6></div>
            <div class="card-body">
                <p class="text-sm">Method: <span class="text-uppercase font-weight-bold"><?= htmlspecialchars($order['payment_method']) ?></span></p>
                <?php if ($order['transaction_id']): ?>
                    <p class="text-sm">Transaction ID: <span class="font-weight-bold"><?= htmlspecialchars($order['transaction_id']) ?></span></p>
                <?php endif; ?>
                <p class="text-sm">Placed: <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></p>

                <form method="post" class="mt-3">
                    <label for="order-status" class="form-label text-sm d-flex justify-content-between align-items-center">
                        Order Status
                        <span class="badge <?= $statusBadge[$order['order_status']] ?? 'bg-gradient-secondary' ?>" data-status-badge="order"><?= ucfirst($order['order_status']) ?></span>
                    </label>
                    <select id="order-status" name="order_status" class="form-control admin-status-control mb-3" data-status-preview="order" <?= empty($nextOrderStatuses) ? 'disabled' : '' ?>>
                        <option value="<?= htmlspecialchars($order['order_status']) ?>" selected><?= ucfirst($order['order_status']) ?></option>
                        <?php foreach ($nextOrderStatuses as $nextStatus): ?>
                            <option value="<?= $nextStatus ?>"><?= ucfirst($nextStatus) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="payment-status" class="form-label text-sm d-flex justify-content-between align-items-center">
                        Payment Status
                        <span class="badge <?= $order['payment_status'] === 'completed' ? 'bg-gradient-success' : ($order['payment_status'] === 'failed' ? 'bg-gradient-danger' : 'bg-gradient-warning') ?>" data-status-badge="payment"><?= ucfirst($order['payment_status']) ?></span>
                    </label>
                    <select id="payment-status" name="payment_status" class="form-control admin-status-control mb-3" data-status-preview="payment">
                        <?php foreach (['pending', 'completed', 'failed'] as $s): ?>
                            <option value="<?= $s ?>" <?= $order['payment_status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn bg-gradient-dark w-100">Update Order</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
