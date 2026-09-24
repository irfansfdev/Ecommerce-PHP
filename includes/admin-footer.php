        </div>
    </main>

    <script src="<?= $base ?>assets/js/core/bootstrap.bundle.min.js"></script>
    <script src="<?= $base ?>assets/js/material-dashboard.min.js"></script>
    <script>
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
