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
$id = (int) ($_GET['id'] ?? 0);
$product = $db->selectOne("SELECT * FROM products WHERE id = ?", [$id]);

if (!$product) {
    header('Location: index.php');
    exit;
}

$categories = $db->select("SELECT id, name FROM categories ORDER BY name ASC");

$errors = [];
$formError = '';
$existingImages = ProductImages::paths($product);
$removeIndexes = [];
$form = [
    'name'        => $product['name'],
    'category_id' => $product['category_id'],
    'price'       => $product['price'],
    'stock'       => $product['stock'],
    'description' => $product['description'],
    'status'      => (int) $product['status'] === 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['name'] = Validator::stringValue($_POST['name'] ?? null);
    $form['category_id'] = Validator::stringValue($_POST['category_id'] ?? null);
    $form['price'] = Validator::stringValue($_POST['price'] ?? null);
    $form['stock'] = Validator::stringValue($_POST['stock'] ?? null);
    $form['description'] = Validator::stringValue($_POST['description'] ?? null);
    $form['status'] = isset($_POST['status']);

    try {
        $errors = Validator::validateProduct($db, $form, true);
        $imageUploads = ProductImages::validateUploads($_FILES['images'] ?? []);
        if (!$imageUploads['success']) {
            $errors['images'] = $imageUploads['message'];
        }

        $submittedRemovals = $_POST['remove_images'] ?? [];
        if (!is_array($submittedRemovals)) {
            $errors['images'] = 'Selected product images are invalid. Please refresh and try again.';
            $submittedRemovals = [];
        }
        foreach ($submittedRemovals as $submittedIndex) {
            if (!is_scalar($submittedIndex)) {
                $errors['images'] = 'Selected product images are invalid. Please refresh and try again.';
                continue;
            }
            $removeIndexes[] = (string) $submittedIndex;
        }
        $removeIndexes = array_values(array_unique($removeIndexes));
        foreach ($removeIndexes as $index) {
            if (!ctype_digit($index) || !array_key_exists((int) $index, $existingImages)) {
                $errors['images'] = 'Selected product images are invalid. Please refresh and try again.';
                break;
            }
        }

        $removedImages = [];
        $remainingImages = [];
        foreach ($existingImages as $index => $path) {
            if (in_array((string) $index, $removeIndexes, true)) {
                $removedImages[] = $path;
            } else {
                $remainingImages[] = $path;
            }
        }
        if ($imageUploads['success']) {
            $countError = ProductImages::finalCountError(count($remainingImages), count($imageUploads['files']));
            if ($countError !== null) {
                $errors['images'] = $countError;
            }
        }

        if (empty($errors)) {
            $upload = ProductImages::storeUploads($imageUploads['files'], $id);
            if (!$upload['success']) {
                $errors['images'] = $upload['message'];
            } else {
                $newPaths = $upload['paths'];
                $finalImages = array_values(array_unique(array_merge($remainingImages, $newPaths)));
                $connection = $db->getConnection();
                $connection->begin_transaction();
                try {
                    $db->run(
                        'UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, stock = ?, image = ?, images = ?, status = ? WHERE id = ?',
                        [
                            (int) $form['category_id'],
                            $form['name'],
                            $form['description'],
                            (float) $form['price'],
                            (int) $form['stock'],
                            $finalImages[0],
                            ProductImages::encode($finalImages),
                            $form['status'] ? 1 : 0,
                            $id,
                        ]
                    );
                    $connection->commit();
                } catch (Throwable $e) {
                    $connection->rollback();
                    ProductImages::deleteImagePaths($id, $newPaths);
                    if (Validator::isDuplicateConstraint($e)) {
                        $errors['name'] = 'A product with this information already exists.';
                    } else {
                        $formError = 'Could not update the product. Please check the entered information and try again.';
                        error_log('Product update failed: ' . $e->getMessage());
                    }
                }

                if (empty($errors) && $formError === '') {
                    ProductImages::deleteImagePaths($id, $removedImages);
                    Session::flash('success', 'Product updated successfully.');
                    header('Location: index.php');
                    exit;
                }
            }
        }
    } catch (Throwable $e) {
        $formError = 'Could not validate the product. Please try again.';
        error_log('Product validation failed: ' . $e->getMessage());
    }
}

$pageTitle = 'Edit Product';
$activeNav = 'products';
$base = '../';
$adminToastMessages = $formError !== '' ? [['type' => 'error', 'message' => $formError]] : [];
require_once __DIR__ . '/../../includes/admin-header.php';
?>
<div class="row">
    <div class="col-lg-7 mx-auto">
        <div class="card">
            <div class="card-header pb-0">
                <h6>Edit Product</h6>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data" data-admin-form="product-edit" novalidate>
                    <div class="input-group input-group-outline mb-3 <?= isset($errors['name']) ? 'is-invalid' : '' ?>">
                        <label class="form-label">Product Name</label>
                        <input type="text" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($form['name']) ?>" maxlength="200" data-admin-validation="product-name" aria-invalid="<?= isset($errors['name']) ? 'true' : 'false' ?>">
                    </div>
                    <p class="text-danger text-xs field-error" data-error-for="name" <?= isset($errors['name']) ? '' : 'hidden' ?>><?= htmlspecialchars($errors['name'] ?? '') ?></p>

                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-control mb-3 <?= isset($errors['category_id']) ? 'is-invalid' : '' ?>" data-admin-validation="category" aria-invalid="<?= isset($errors['category_id']) ? 'true' : 'false' ?>">
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
                    <div class="product-image-existing-list" data-existing-images>
                        <?php foreach ($existingImages as $imageIndex => $imagePath): ?>
                            <label class="product-image-existing" data-existing-product-image>
                                <img src="<?= htmlspecialchars($base . '../public/' . shop_image($imagePath)) ?>" alt="Product image <?= $imageIndex + 1 ?>">
                                <span><input type="checkbox" name="remove_images[]" value="<?= (int) $imageIndex ?>" data-remove-product-image <?= in_array((string) $imageIndex, $removeIndexes, true) ? 'checked' : '' ?>> Remove</span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <input type="file" name="images[]" multiple class="form-control mb-1 <?= isset($errors['images']) ? 'is-invalid' : '' ?>" accept="image/jpeg,image/png,image/webp,image/gif" data-admin-validation="product-images" data-image-mode="edit" aria-invalid="<?= isset($errors['images']) ? 'true' : 'false' ?>">
                    <p class="text-xs text-secondary">Select up to 5 images total. JPG, PNG, WEBP or GIF. Max 2MB each.</p>
                    <p class="text-xs text-secondary" data-image-count><?= count($existingImages) ?> / 5 images</p>
                    <div class="product-image-previews" data-selected-image-previews></div>
                    <p class="text-danger text-xs field-error" data-error-for="images" <?= isset($errors['images']) ? '' : 'hidden' ?>><?= htmlspecialchars($errors['images'] ?? '') ?></p>

                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="status" id="status" <?= $form['status'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="status">Active (visible in the storefront)</label>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn bg-gradient-dark">Save Changes</button>
                        <a href="index.php" class="btn btn-outline-dark">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
