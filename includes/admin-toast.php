<?php
require_once __DIR__ . '/../core/Session.php';

$adminToastMessages = $adminToastMessages ?? [];
$adminSuccessMessage = Session::flash('success');
$adminErrorMessage = Session::flash('error');

if ($adminSuccessMessage) {
    $adminToastMessages[] = ['type' => 'success', 'message' => $adminSuccessMessage];
}
if ($adminErrorMessage) {
    $adminToastMessages[] = ['type' => 'error', 'message' => $adminErrorMessage];
}
?>
<div class="admin-toast-container" data-admin-toast-container aria-live="polite" aria-atomic="false">
    <?php foreach ($adminToastMessages as $toast): ?>
        <?php $isError = ($toast['type'] ?? '') === 'error'; ?>
        <div class="admin-toast <?= $isError ? 'admin-toast-error' : 'admin-toast-success' ?>" role="<?= $isError ? 'alert' : 'status' ?>" data-admin-toast data-duration="<?= $isError ? '4500' : '3000' ?>">
            <span class="admin-toast-icon" aria-hidden="true"><?= $isError ? '&#10005;' : '&#10003;' ?></span>
            <span class="admin-toast-message"><?= htmlspecialchars((string) ($toast['message'] ?? '')) ?></span>
            <button class="admin-toast-close" type="button" aria-label="Dismiss notification">&times;</button>
        </div>
    <?php endforeach; ?>
</div>