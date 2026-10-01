        <?php $base = $base ?? ''; ?>
        </div>
    </main>

    <script src="<?= $base ?>assets/js/core/bootstrap.bundle.min.js"></script>
    <script src="<?= $base ?>assets/js/material-dashboard.min.js"></script>
    <script src="<?= $base ?>assets/js/admin-form-validation.js?v=<?= filemtime(__DIR__ . '/../admin/assets/js/admin-form-validation.js') ?>"></script>
    <script src="<?= $base ?>assets/js/admin-toast.js?v=<?= filemtime(__DIR__ . '/../admin/assets/js/admin-toast.js') ?>"></script>
    <script>
        var statusBadgeClasses = {
            order: {
                processing: 'bg-gradient-warning',
                shipped: 'bg-gradient-info',
                delivered: 'bg-gradient-success',
                cancelled: 'bg-gradient-secondary'
            },
            payment: {
                pending: 'bg-gradient-warning',
                completed: 'bg-gradient-success',
                failed: 'bg-gradient-danger'
            }
        };

        document.querySelectorAll('[data-status-preview]').forEach(function (field) {
            var type = field.dataset.statusPreview;
            var badge = document.querySelector('[data-status-badge="' + type + '"]');
            var syncBadge = function () {
                var value = field.value;
                badge.textContent = value.charAt(0).toUpperCase() + value.slice(1);
                badge.className = 'badge ' + (statusBadgeClasses[type][value] || 'bg-gradient-secondary');
            };

            field.addEventListener('change', syncBadge);
        });

        document.querySelectorAll('.input-group-outline input, .input-group-outline textarea').forEach(function (field) {
            var group = field.parentElement;
            var syncLabel = function () {
                group.classList.toggle('is-filled', field.value.trim() !== '');
            };

            syncLabel();
            field.addEventListener('input', syncLabel);
            field.addEventListener('change', syncLabel);
        });
    </script>
    <!-- Note: chartjs.min.js is loaded in admin-header.php (when $pageScript === 'chart') so it
         is available before the pages' inline new Chart(...) scripts further down the body. -->
</body>
</html>
