<?php
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Uploader.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Validator.php';
Auth::requireAdmin('../login.php');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new Database();
$id = (int) ($_GET['id'] ?? 0);
$category = $db->selectOne("SELECT * FROM categories WHERE id = ?", [$id]);

if (!$category) {
    header('Location: index.php');
    exit;
}

$errors = [];
$formError = '';
$name = $category['name'];
$statusChecked = (int) $category['status'] === 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = Validator::stringValue($_POST['name'] ?? null);
    $statusChecked = isset($_POST['status']);

    try {
        $errors = Validator::validateCategory($db, $name, $id);
        $upload = Uploader::validate($_FILES['image'] ?? []);
        if (!$upload['success'] && $upload['message']) {
            $errors['image'] = $upload['message'];
        }

        if (empty($errors)) {
            $upload = $upload['success'] ? Uploader::save($_FILES['image'], 'categories') : $upload;
            if (!$upload['success'] && $upload['message']) {
                $errors['image'] = $upload['message'];
            } else {
                $imagePath = $upload['success'] ? $upload['path'] : $category['image'];
                try {
                    $db->run(
                        'UPDATE categories SET name = ?, image = ?, status = ? WHERE id = ?',
                        [$name, $imagePath, $statusChecked ? 1 : 0, $id]
                    );
                } catch (Throwable $e) {
                    if ($upload['success']) {
                        Uploader::delete($upload['path']);
                    }
                    if (Validator::isDuplicateConstraint($e)) {
                        $errors['name'] = 'A category with this name already exists.';
                    } else {
                        $formError = 'Could not update the category. Please check the entered information and try again.';
                        error_log('Category update failed: ' . $e->getMessage());
                    }
                }

                if (empty($errors) && $formError === '') {
                    if ($upload['success']) {
                        Uploader::delete($category['image']);
                    }
                    Session::flash('success', 'Category updated.');
                    header('Location: index.php');
                    exit;
                }
            }
        }
    } catch (Throwable $e) {
        $formError = 'Could not validate the category. Please try again.';
        error_log('Category validation failed: ' . $e->getMessage());
    }
}

$pageTitle = 'Edit Category';
$activeNav = 'categories';
$base = '../';
$adminToastMessages = $formError !== '' ? [['type' => 'error', 'message' => $formError]] : [];
require_once __DIR__ . '/../../includes/admin-header.php';
?>
<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card">
            <div class="card-header pb-0">
                <h6>Edit Category</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <img src="<?= htmlspecialchars('/' . shop_image($category['image'])) ?>" width="80" height="80" style="object-fit: cover; border-radius: 8px;" alt="">
                </div>
                <form method="post" enctype="multipart/form-data" data-admin-form="category" novalidate>
                    <div class="input-group input-group-outline mb-3 <?= isset($errors['name']) ? 'is-invalid' : '' ?>">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($name) ?>" maxlength="100" data-admin-validation="category-name" aria-invalid="<?= isset($errors['name']) ? 'true' : 'false' ?>">
                    </div>
                    <p class="text-danger text-xs field-error" data-error-for="name" <?= isset($errors['name']) ? '' : 'hidden' ?>><?= htmlspecialchars($errors['name'] ?? '') ?></p>

                    <label class="form-label mt-2">Replace Image</label>
                    <input type="file" name="image" class="form-control mb-1 <?= isset($errors['image']) ? 'is-invalid' : '' ?>" accept="image/jpeg,image/png,image/webp,image/gif" data-admin-validation="image" aria-invalid="<?= isset($errors['image']) ? 'true' : 'false' ?>">
                    <p class="text-xs text-secondary">Leave empty to keep the current image.</p>
                    <p class="text-danger text-xs field-error" data-error-for="image" <?= isset($errors['image']) ? '' : 'hidden' ?>><?= htmlspecialchars($errors['image'] ?? '') ?></p>

                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="status" id="status" <?= $statusChecked ? 'checked' : '' ?>>
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
