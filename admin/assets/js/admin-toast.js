(function () {
    'use strict';

    var container = document.querySelector('[data-admin-toast-container]');

    function getContainer() {
        if (!container) {
            container = document.createElement('div');
            container.className = 'admin-toast-container';
            container.setAttribute('data-admin-toast-container', '');
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('aria-atomic', 'false');
            document.body.appendChild(container);
        }
        return container;
    }

    function dismiss(toast) {
        if (!toast || toast.dataset.leaving === 'true') {
            return;
        }
        toast.dataset.leaving = 'true';
        toast.classList.remove('admin-toast-visible');
        toast.classList.add('admin-toast-leaving');
        window.setTimeout(function () {
            toast.remove();
        }, 220);
    }

    function activate(toast) {
        window.requestAnimationFrame(function () {
            toast.classList.add('admin-toast-visible');
        });
        var duration = parseInt(toast.getAttribute('data-duration'), 10) || 3000;
        toast._dismissTimer = window.setTimeout(function () {
            dismiss(toast);
        }, duration);
        var close = toast.querySelector('.admin-toast-close');
        if (close) {
            close.addEventListener('click', function () {
                window.clearTimeout(toast._dismissTimer);
                dismiss(toast);
            });
        }
    }

    document.querySelectorAll('[data-admin-toast]').forEach(activate);

    window.AdminToast = {
        show: function (type, message, duration) {
            var isError = type === 'error';
            var toast = document.createElement('div');
            toast.className = 'admin-toast ' + (isError ? 'admin-toast-error' : 'admin-toast-success');
            toast.setAttribute('role', isError ? 'alert' : 'status');
            toast.setAttribute('data-admin-toast', '');
            toast.setAttribute('data-duration', String(duration || (isError ? 4500 : 3000)));

            var icon = document.createElement('span');
            icon.className = 'admin-toast-icon';
            icon.setAttribute('aria-hidden', 'true');
            icon.textContent = isError ? '\u2715' : '\u2713';

            var text = document.createElement('span');
            text.className = 'admin-toast-message';
            text.textContent = String(message);

            var close = document.createElement('button');
            close.className = 'admin-toast-close';
            close.type = 'button';
            close.setAttribute('aria-label', 'Dismiss notification');
            close.textContent = '\u00D7';

            toast.appendChild(icon);
            toast.appendChild(text);
            toast.appendChild(close);
            getContainer().appendChild(toast);
            activate(toast);
        }
    };
})();