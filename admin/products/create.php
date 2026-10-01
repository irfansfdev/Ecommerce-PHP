<?php
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Uploader.php';
require_once __DIR__ . '/../../core/ProductImages.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Validator.php';
Auth::requireAdmin('../login.php');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new Database();
$categories = $db->select("SELECT id, name FROM categories WHERE status = 1 ORDER BY name ASC");

$errors = [];
$formError = '';
$form = ['name' => '', 'category_id' => '', 'price' => '', 'stock' => '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['name'] = Validator::stringValue($_POST['name'] ?? null);
    $form['category_id'] = Validator::stringValue($_POST['category_id'] ?? null);
    $form['price'] = Validator::stringValue($_POST['price'] ?? null);
    $form['stock'] = Validator::stringValue($_POST['stock'] ?? null);
    $form['description'] = Validator::stringValue($_POST['description'] ?? null);

    try {
        $errors = Validator::validateProduct($db, $form);
        $imageUploads = ProductImages::validateUploads($_FILES['images'] ?? []);
        if (!$imageUploads['success']) {
            $errors['images'] = $imageUploads['message'];
        } elseif (!$imageUploads['files']) {
            $errors['images'] = 'At least 1 product image is required.';
        }

        if (empty($errors)) {
            $productId = 0;
            $storedPaths = [];
            $connection = $db->getConnection();
            $connection->begin_transaction();
            try {
                $slug = make_slug($form['name']);
                $existing = $db->selectOne('SELECT id FROM products WHERE slug = ?', [$slug]);
                if ($existing) {
                    $slug .= '-' . time();
                }

                $productId = $db->insert(
                    'INSERT INTO products (category_id, name, slug, description, price, stock, image, images, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NULL, 1)',
                    [
                        (int) $form['category_id'],
                        $form['name'],
                        $slug,
                        $form['description'],
                        (float) $form['price'],
                        (int) $form['stock'],
                        'products/product-1.jpg',
                    ]
                );

                $upload = ProductImages::storeUploads($imageUploads['files'], $productId);
                if (!$upload['success']) {
                    throw new RuntimeException($upload['message']);
                }
                $storedPaths = $upload['paths'];
                $imagesJson = ProductImages::encode($storedPaths);
                $db->run(
                    'UPDATE products SET image = ?, images = ? WHERE id = ?',
                    [$storedPaths[0], $imagesJson, $productId]
                );
                $connection->commit();

                Session::flash('success', 'Product created successfully.');
                header('Location: index.php');
                exit;
            } catch (Throwable $e) {
                $connection->rollback();
                if ($storedPaths) {
                    ProductImages::deleteImagePaths($productId, $storedPaths);
                }
                if ($productId > 0) {
                    ProductImages::removeEmptyProductDirectory($productId);
                }
                if (Validator::isDuplicateConstraint($e)) {
                    $errors['name'] = 'A product with this information already exists.';
                } else {
                    $errors['images'] = $e instanceof RuntimeException
                        ? $e->getMessage()
                        : 'Could not save the product and its images. Please try again.';
                    error_log('Product create failed: ' . $e->getMessage());
                }
            }
        }
    } catch (Throwable $e) {
        $formError = 'Could not validate the product. Please try again.';
        error_log('Product validation failed: ' . $e->getMessage());
    }
}

$pageTitle = 'Add Product';
$activeNav = 'products';
$base = '../';
$adminToastMessages = $formError !== '' ? [['type' => 'error', 'message' => $formError]] : [];
require_once __DIR__ . '/../../includes/admin-header.php';
?>
<div class="row">
    <div class="col-lg-7 mx-auto">
        <div class="card">
            <div class="card-header pb-0">
                <h6>Add Product</h6>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data" data-admin-form="product-create" novalidate>
                    <div class="input-group input-group-outline mb-3 <?= isset($errors['name']) ? 'is-invalid' : '' ?>">
                        <label class="form-label">Product Name</label>
                        <input type="text" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($form['name']) ?>" maxlength="200" data-admin-validation="product-name" aria-invalid="<?= isset($errors['name']) ? 'true' : 'false' ?>">
                    </div>
                    <p class="text-danger text-xs field-error" data-error-for="name" <?= isset($errors['name']) ? '' : 'hidden' ?>><?= htmlspecialchars($errors['name'] ?? '') ?></p>

                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-control mb-3 <?= isset($errors['category_id']) ? 'is-invalid' : '' ?>" data-admin-validation="category" aria-invalid="<?= isset($errors['category_id']) ? 'true' : 'false' ?>">
                        <option value="">Select a category</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= $form['category_id'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-danger text-xs field-error" data-error-for="category_id" <?= isset($errors['category_id']) ? '' : 'hidden' ?>><?= htmlspecialchars($errors['category_id'] ?? '') ?></p>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="input-group input-group-outline mb-3 <?= isset($errors['price']) ? 'is-invalid' : '' ?>">
                                <label class="form-label">Price ($)</label>
                                <input type="text" name="price" class="form-control <?= isset($errors['price']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($form['price']) ?>" inputmode="decimal" maxlength="12" data-admin-validation="price" aria-invalid="<?= isset($errors['price']) ? 'true' : 'false' ?>">
                            </div>
                            <p class="text-danger text-xs field-error" data-error-for="price" <?= isset($errors['price']) ? '' : 'hidden' ?>><?= htmlspecialchars($errors['price'] ?? '') ?></p>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group input-group-outline mb-3 <?= isset($errors['stock']) ? 'is-invalid' : '' ?>">
                                <label class="form-label">Stock Quantity</label>
                                <input type="text" name="stock" class="form-control <?= isset($errors['stock']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($form['stock']) ?>" inputmode="numeric" maxlength="10" data-admin-validation="stock" aria-invalid="<?= isset($errors['stock']) ? 'true' : 'false' ?>">
                            </div>
                            <p class="text-danger text-xs field-error" data-error-for="stock" <?= isset($errors['stock']) ? '' : 'hidden' ?>><?= htmlspecialchars($errors['stock'] ?? '') ?></p>
                        </div>
                    </div>

                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control mb-3 <?= isset($errors['description']) ? 'is-invalid' : '' ?>" rows="4" data-admin-validation="description" aria-invalid="<?= isset($errors['description']) ? 'true' : 'false' ?>"><?= htmlspecialchars($form['description']) ?></textarea>
                    <p class="text-danger text-xs field-error" data-error-for="description" <?= isset($errors['description']) ? '' : 'hidden' ?>><?= htmlspecialchars($errors['description'] ?? '') ?></p>

                    <label class="form-label">Product Images</label>
                    <input type="file" name="images[]" multiple class="form-control mb-1 <?= isset($errors['images']) ? 'is-invalid' : '' ?>" accept="image/jpeg,image/png,image/webp,image/gif" data-admin-validation="product-images" data-image-mode="create" aria-invalid="<?= isset($errors['images']) ? 'true' : 'false' ?>">
                    <p class="text-xs text-secondary">JPG, PNG, WEBP or GIF. Max 2MB each. Select 1–5 images.</p>
                    <p class="text-xs text-secondary" data-image-count>0 / 5 images</p>
                    <div class="product-image-previews" data-selected-image-previews></div>
                    <p class="text-danger text-xs field-error" data-error-for="images" <?= isset($errors['images']) ? '' : 'hidden' ?>><?= htmlspecialchars($errors['images'] ?? '') ?></p>

                    <div class="mt-4">
                        <button type="submit" class="btn bg-gradient-dark">Save Product</button>
                        <a href="index.php" class="btn btn-outline-dark">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
