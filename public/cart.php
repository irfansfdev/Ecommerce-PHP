<?php
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Cart.php';
require_once __DIR__ . '/../core/Helpers.php';
Session::startCustomer();

$db = new Database();
$cartService = new Cart($db, Auth::isLoggedIn() ? Session::get('user_id') : null);

// --- Add a product (from a product card or the product detail page) ---
if (isset($_GET['add'])) {
    $id = filter_var($_GET['add'], FILTER_VALIDATE_INT);
    $qty = isset($_GET['qty']) ? filter_var($_GET['qty'], FILTER_VALIDATE_INT) : 1;
    $ajaxRequest = ($_GET['ajax'] ?? '') === '1';

    $added = $id !== false && $qty !== false && $qty > 0 && $cartService->add($id, $qty);
    $message = $added ? 'Added to cart.' : 'Sorry, that item is unavailable or out of stock.';

    if ($ajaxRequest) {
        $cartItems = $cartService->getItems();
        $cartCount = 0;
        $cartTotal = 0.0;
        foreach ($cartItems as $item) {
            $cartCount += $item['qty'];
            $cartTotal += $item['line_total'];
        }

        ob_start();
        require __DIR__ . '/../includes/cart-dropdown-content.php';
        $cartDropdownHtml = ob_get_clean();

        if (!$added) {
            http_response_code(422);
        }
        header('Cache-Control: no-store, private');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $added,
            'message' => $message,
            'cartCount' => $cartCount,
            'cartDropdownHtml' => $cartDropdownHtml,
        ]);
        exit;
    }

    Session::flash($added ? 'success' : 'error', $added ? 'Product added to your cart.' : $message);
    header('Location: cart.php');
    exit;
}

// --- Remove a single product ---
if (isset($_GET['remove'])) {
    $cartService->remove((int) $_GET['remove']);
    header('Location: cart.php');
    exit;
}

// --- Empty the whole cart ---
if (isset($_GET['clear'])) {
    $cartService->clear();
    header('Location: cart.php');
    exit;
}

// --- Update quantities from the cart table form ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    $qtys = $_POST['qty'] ?? [];

    foreach ($qtys as $id => $qty) {
        if (filter_var($id, FILTER_VALIDATE_INT) === false || filter_var($qty, FILTER_VALIDATE_INT) === false) {
            continue;
        }
        $cartService->update((int) $id, (int) $qty);
    }

    Session::flash('success', 'Cart updated.');
    header('Location: cart.php');
    exit;
}

$pageTitle = 'Shopping Cart';
require_once __DIR__ . '/../includes/header.php';
// $cartItems / $cartTotal / $cartCount are built by header.php from the active cart source.
?>
<nav aria-label="breadcrumb" class="breadcrumb-nav border-0 mb-0">
    <div class="container">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Shopping Cart</li>
        </ol>
    </div>
</nav>

<div class="page-content">
    <div class="cart">
        <div class="container">
            <?php if (empty($cartItems)): ?>
                <div class="row justify-content-center">
                    <div class="col-md-6 text-center py-5">
                        <i class="icon-shopping-cart" style="font-size: 3rem; color: #ccc;"></i>
                        <h3 class="mt-3">Your cart is empty</h3>
                        <p>Looks like you haven't added anything yet.</p>
                        <a href="category.php" class="btn btn-primary btn-round"><span>Start Shopping</span><i class="icon-long-arrow-right"></i></a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <div class="col-lg-8">
                        <form action="cart.php" method="post">
                            <table class="table table-cart table-mobile">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Total</th>
                                        <th></th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($cartItems as $item): ?>
                                        <tr>
                                            <td class="product-col">
                                                <div class="product">
                                                    <figure class="product-media">
                                                        <img src="<?= htmlspecialchars(shop_image($item['image'])) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                                                    </figure>
                                                    <h3 class="product-title">
                                                        <a href="product.php?slug=<?= urlencode($item['slug']) ?>"><?= htmlspecialchars($item['name']) ?></a>
                                                        <?php if (!$item['available']): ?><small class="text-danger">Unavailable</small><?php endif; ?>
                                                    </h3>
                                                </div>
                                            </td>

                                            <td class="price-col">$<?= number_format($item['price'], 2) ?></td>

                                            <td class="quantity-col">
                                                <?php if ($item['available']): ?>
                                                    <input type="number" name="qty[<?= (int) $item['cart_key'] ?>]" class="form-control" value="<?= (int) $item['qty'] ?>" min="1" max="<?= (int) $item['stock'] ?>" step="1">
                                                <?php else: ?>
                                                    <span><?= (int) $item['qty'] ?></span>
                                                <?php endif; ?>
                                            </td>

                                            <td class="total-col">$<?= number_format($item['line_total'], 2) ?></td>

                                            <td class="remove-col">
                                                <a href="cart.php?remove=<?= (int) $item['cart_key'] ?>" class="btn-remove" title="Remove Product"><i class="icon-close"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <div class="cart-bottom">
                                <a href="category.php" class="btn btn-outline-dark-2 btn-round"><span>Continue Shopping</span><i class="icon-long-arrow-right"></i></a>
                                <button type="submit" name="update_cart" value="1" class="btn btn-outline-dark-2 btn-round"><span>Update Cart</span><i class="icon-refresh"></i></button>
                            </div>
                        </form>
                    </div><!-- End .col-lg-8 -->

                    <div class="col-lg-4">
                        <div class="summary summary-cart">
                            <h3 class="summary-title">Cart Total</h3>

                            <table class="table table-summary">
                                <tbody>
                                    <tr class="summary-subtotal">
                                        <td>Subtotal:</td>
                                        <td>$<?= number_format($cartTotal, 2) ?></td>
                                    </tr>
                                    <tr class="summary-shipping">
                                        <td>Shipping:</td>
                                        <td>Calculated at checkout</td>
                                    </tr>
                                    <tr class="summary-total">
                                        <td>Total:</td>
                                        <td>$<?= number_format($cartTotal, 2) ?></td>
                                    </tr>
                                </tbody>
                            </table>

                            <a href="checkout.php" class="btn btn-primary btn-order btn-round">Proceed to Checkout</a>
                        </div><!-- End .summary -->
                    </div><!-- End .col-lg-4 -->
                </div><!-- End .row -->
            <?php endif; ?>
        </div><!-- End .container -->
    </div><!-- End .cart -->
</div><!-- End .page-content -->
</main><!-- End .main -->
<?php require_once __DIR__ . '/../includes/footer.php'; ?>