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
$errors = [];
$formError = '';
$name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = Validator::stringValue($_POST['name'] ?? null);

    try {
        $errors = Validator::validateCategory($db, $name);
        $upload = Uploader::validate($_FILES['image'] ?? []);
        if (!$upload['success'] && $upload['message']) {
            $errors['image'] = $upload['message'];
        }

        if (empty($errors)) {
            $slug = make_slug($name);
            $existing = $db->selectOne('SELECT id FROM categories WHERE slug = ?', [$slug]);
            if ($existing) {
                $slug .= '-' . time();
            }

            $upload = $upload['success'] ? Uploader::save($_FILES['image'], 'categories') : $upload;
            if (!$upload['success'] && $upload['message']) {
                $errors['image'] = $upload['message'];
            } else {
                $imagePath = $upload['success'] ? $upload['path'] : null;
                try {
                    $db->insert(
                        'INSERT INTO categories (name, slug, image, status) VALUES (?, ?, ?, 1)',
                        [$name, $slug, $imagePath]
                    );
                } catch (Throwable $e) {
                    if ($upload['success']) {
                        Uploader::delete($upload['path']);
                    }
                    if (Validator::isDuplicateConstraint($e)) {
                        $errors['name'] = 'A category with this name already exists.';
                    } else {
                        $formError = 'Could not save the category. Please check the entered information and try again.';
                        error_log('Category create failed: ' . $e->getMessage());
                    }
                }

                if (empty($errors) && $formError === '') {
                    Session::flash('success', 'Category added.');
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

$pageTitle = 'Add Category';
$activeNav = 'categories';
$base = '../';
$adminToastMessages = $formError !== '' ? [['type' => 'error', 'message' => $formError]] : [];
require_once __DIR__ . '/../../includes/admin-header.php';
?>
<div class="row">
    <div class="col-lg-6 mx-auto">
        <div class="card">
            <div class="card-header pb-0">
                <h6>Add Category</h6>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data" data-admin-form="category" novalidate>
                    <div class="input-group input-group-outline mb-3 <?= isset($errors['name']) ? 'is-invalid' : '' ?>">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="name" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($name) ?>" maxlength="100" data-admin-validation="category-name" aria-invalid="<?= isset($errors['name']) ? 'true' : 'false' ?>">
                    </div>
                    <p class="text-danger text-xs field-error" data-error-for="name" <?= isset($errors['name']) ? '' : 'hidden' ?>><?= htmlspecialchars($errors['name'] ?? '') ?></p>

                    <label class="form-label mt-2">Category Image</label>
                    <input type="file" name="image" class="form-control mb-1 <?= isset($errors['image']) ? 'is-invalid' : '' ?>" accept="image/jpeg,image/png,image/webp,image/gif" data-admin-validation="image" aria-invalid="<?= isset($errors['image']) ? 'true' : 'false' ?>">
                    <p class="text-xs text-secondary">JPG, PNG, WEBP or GIF. Max 2MB. Optional.</p>
                    <p class="text-danger text-xs field-error" data-error-for="image" <?= isset($errors['image']) ? '' : 'hidden' ?>><?= htmlspecialchars($errors['image'] ?? '') ?></p>

                    <div class="mt-4">
                        <button type="submit" class="btn bg-gradient-dark">Save Category</button>
                        <a href="index.php" class="btn btn-outline-dark">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/admin-footer.php'; ?>
