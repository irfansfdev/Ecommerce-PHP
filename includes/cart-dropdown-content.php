<?php if (empty($cartItems)): ?>
    <div class="dropdown-cart-products">
        <p class="text-center mb-0 py-2">Your cart is empty.</p>
    </div>
<?php else: ?>
    <div class="dropdown-cart-products">
        <?php foreach ($cartItems as $item): ?>
            <div class="product">
                <div class="product-cart-details">
                    <h4 class="product-title">
                        <a href="product.php?slug=<?= urlencode($item['slug']) ?>"><?= htmlspecialchars($item['name']) ?></a>
                    </h4>
                    <span class="cart-product-info">
                        <span class="cart-product-qty"><?= (int) $item['qty'] ?></span>
                        x $<?= number_format($item['price'], 2) ?>
                    </span>
                </div><!-- End .product-cart-details -->
                <figure class="product-image-container">
                    <a href="product.php?slug=<?= urlencode($item['slug']) ?>" class="product-image">
                        <img src="<?= htmlspecialchars(shop_image($item['image'])) ?>" alt="product">
                    </a>
                </figure>
                <a href="cart.php?remove=<?= (int) $item['cart_key'] ?>" class="btn-remove" title="Remove Product"><i class="icon-close"></i></a>
            </div><!-- End .product -->
        <?php endforeach; ?>
    </div><!-- End .cart-product -->
    <div class="dropdown-cart-total">
        <span>Total</span>
        <span class="cart-total-price">$<?= number_format($cartTotal, 2) ?></span>
    </div><!-- End .dropdown-cart-total -->
    <div class="dropdown-cart-action">
        <a href="cart.php" class="btn btn-primary">View Cart</a>
        <a href="checkout.php" class="btn btn-outline-primary-2"><span>Checkout</span><i class="icon-long-arrow-right"></i></a>
    </div><!-- End .dropdown-cart-total -->
<?php endif; ?>