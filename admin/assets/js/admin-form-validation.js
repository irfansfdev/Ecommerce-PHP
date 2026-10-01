(function () {
    'use strict';

    var allowedImageTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    var allowedImageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    var maxImageBytes = 2 * 1024 * 1024;
    var maxDescriptionBytes = 65535;

    function characterLength(value) {
        return Array.from(value).length;
    }

    function byteLength(value) {
        return new Blob([value]).size;
    }

    function validationMessage(field) {
        var value = field.value.trim();
        var rule = field.dataset.adminValidation;

        if (rule === 'category-name' || rule === 'product-name') {
            var label = rule === 'category-name' ? 'Category name' : 'Product name';
            var maxLength = rule === 'category-name' ? 100 : 200;
            if (value === '') {
                return label + ' is required.';
            }
            if (characterLength(value) < 2) {
                return label + ' must be at least 2 characters.';
            }
            if (characterLength(value) > maxLength) {
                return label + ' must not exceed ' + maxLength + ' characters.';
            }
        }

        if (rule === 'category') {
            return value === '' ? 'Please select a category.' : '';
        }

        if (rule === 'price') {
            if (value === '') {
                return 'Product price is required.';
            }
            if (!/^-?\d+(?:\.\d+)?$/.test(value)) {
                return 'Please enter a valid price.';
            }
            if (Number(value) <= 0) {
                return 'Price must be greater than 0.';
            }
            if (!/^\d+(?:\.\d{1,2})?$/.test(value) || Number(value) > 99999999.99) {
                return 'Please enter a valid price with up to 2 decimal places.';
            }
        }

        if (rule === 'stock') {
            if (value === '') {
                return 'Stock quantity is required.';
            }
            if (/^-/.test(value) && Number.isFinite(Number(value))) {
                return 'Stock quantity cannot be negative.';
            }
            if (!/^\d+$/.test(value)) {
                return Number.isFinite(Number(value))
                    ? 'Quantity must be a whole number.'
                    : 'Please enter a valid quantity.';
            }
            if (Number(value) > 4294967295) {
                return 'Stock quantity is too large.';
            }
        }

        if (rule === 'description' && byteLength(field.value) > maxDescriptionBytes) {
            return 'Description must fit within the 65,535-byte database limit.';
        }

        if (rule === 'image') {
            var file = field.files && field.files[0];
            if (!file) {
                return field.dataset.requiredImage === 'true' ? 'Product image is required.' : '';
            }
            if (file.size > maxImageBytes) {
                return 'Image size must not exceed 2MB.';
            }
            var extension = file.name.split('.').pop().toLowerCase();
            if ((file.type && allowedImageTypes.indexOf(file.type) === -1)
                || (!file.type && allowedImageExtensions.indexOf(extension) === -1)) {
                return 'Please upload a valid image.';
            }
        }

        if (rule === 'product-images') {
            var form = field.closest('form');
            var existingCount = form.querySelectorAll('[data-existing-product-image]').length;
            var removedCount = form.querySelectorAll('[data-remove-product-image]:checked').length;
            var selectedFiles = field.files || [];
            var remainingCount = existingCount - removedCount;
            var totalCount = remainingCount + selectedFiles.length;

            if (selectedFiles.length > 5) {
                return 'You can upload a maximum of 5 images.';
            }
            for (var fileIndex = 0; fileIndex < selectedFiles.length; fileIndex++) {
                var selectedFile = selectedFiles[fileIndex];
                if (selectedFile.size > maxImageBytes) {
                    return 'Image size must not exceed 2MB.';
                }
                var selectedExtension = selectedFile.name.split('.').pop().toLowerCase();
                var extensionType = {
                    jpg: 'image/jpeg',
                    jpeg: 'image/jpeg',
                    png: 'image/png',
                    webp: 'image/webp',
                    gif: 'image/gif'
                }[selectedExtension];
                if (!extensionType || (selectedFile.type && selectedFile.type !== extensionType)
                    || (!selectedFile.type && allowedImageExtensions.indexOf(selectedExtension) === -1)) {
                    return 'Please upload a valid image file.';
                }
            }
            if (totalCount < 1) {
                return field.dataset.imageMode === 'edit'
                    ? 'A product must have at least 1 image.'
                    : 'At least 1 product image is required.';
            }
            if (totalCount > 5) {
                return 'You can have a maximum of 5 images.';
            }
        }

        return '';
    }

    function refreshProductImagePreview(form, field) {
        var existingCount = form.querySelectorAll('[data-existing-product-image]').length;
        var removedCount = form.querySelectorAll('[data-remove-product-image]:checked').length;
        var files = field.files || [];
        var count = existingCount - removedCount + files.length;
        var countLabel = form.querySelector('[data-image-count]');
        if (countLabel) {
            countLabel.textContent = count + ' / 5 images';
        }

        var preview = form.querySelector('[data-selected-image-previews]');
        if (!preview) {
            return;
        }
        preview.querySelectorAll('img').forEach(function (image) {
            if (image.src.indexOf('blob:') === 0) {
                URL.revokeObjectURL(image.src);
            }
        });
        preview.textContent = '';
        Array.prototype.forEach.call(files, function (file) {
            if (!file.type || allowedImageTypes.indexOf(file.type) === -1) {
                return;
            }
            var wrapper = document.createElement('span');
            wrapper.className = 'product-image-preview';
            var image = document.createElement('img');
            image.src = URL.createObjectURL(file);
            image.alt = file.name;
            wrapper.appendChild(image);

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'product-image-preview-remove';
            remove.setAttribute('data-remove-selected-image', '');
            remove.setAttribute('data-selected-image-index', String(preview.children.length));
            remove.setAttribute('aria-label', 'Remove ' + file.name);
            remove.textContent = '\u00D7';
            wrapper.appendChild(remove);
            preview.appendChild(wrapper);
        });
    }

    function fileKey(file) {
        return [file.name, file.size, file.lastModified, file.type].join('|');
    }

    function assignSelectedFiles(field, files) {
        var transfer = new DataTransfer();
        files.forEach(function (file) {
            transfer.items.add(file);
        });
        field.files = transfer.files;
        field._selectedProductFiles = files.slice();
    }

    function appendSelectedFiles(field) {
        var selected = field._selectedProductFiles || [];
        var knownFiles = new Set(selected.map(fileKey));
        Array.prototype.forEach.call(field.files || [], function (file) {
            var key = fileKey(file);
            if (!knownFiles.has(key)) {
                knownFiles.add(key);
                selected.push(file);
            }
        });
        assignSelectedFiles(field, selected);
    }

    function setFieldError(form, field, message) {
        field.classList.toggle('is-invalid', message !== '');
        field.setAttribute('aria-invalid', message !== '' ? 'true' : 'false');

        var group = field.closest('.input-group');
        if (group) {
            group.classList.toggle('is-invalid', message !== '');
        }

        var errorName = field.name.replace(/\[\]$/, '');
        var error = form.querySelector('[data-error-for="' + errorName + '"]');
        if (error) {
            error.textContent = message;
            error.hidden = message === '';
        }

        return message === '';
    }

    document.querySelectorAll('form[data-admin-form]').forEach(function (form) {
        var fields = form.querySelectorAll('[data-admin-validation]');

        fields.forEach(function (field) {
            var validate = function () {
                if (field.dataset.adminValidation === 'product-images') {
                    appendSelectedFiles(field);
                    refreshProductImagePreview(form, field);
                }
                setFieldError(form, field, validationMessage(field));
            };
            field.addEventListener('input', validate);
            field.addEventListener('change', validate);
        });

        form.querySelectorAll('[data-remove-product-image]').forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                var imageField = form.querySelector('[data-admin-validation="product-images"]');
                if (imageField) {
                    refreshProductImagePreview(form, imageField);
                    setFieldError(form, imageField, validationMessage(imageField));
                }
            });
        });

        form.addEventListener('click', function (event) {
            var removeButton = event.target.closest('[data-remove-selected-image]');
            if (!removeButton || !form.contains(removeButton)) {
                return;
            }
            var imageField = form.querySelector('[data-admin-validation="product-images"]');
            if (!imageField) {
                return;
            }
            var selected = (imageField._selectedProductFiles || Array.from(imageField.files || [])).slice();
            selected.splice(parseInt(removeButton.dataset.selectedImageIndex, 10), 1);
            assignSelectedFiles(imageField, selected);
            refreshProductImagePreview(form, imageField);
            setFieldError(form, imageField, validationMessage(imageField));
        });

        form.addEventListener('submit', function (event) {
            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }

            var firstInvalid = null;
            fields.forEach(function (field) {
                if (!setFieldError(form, field, validationMessage(field)) && !firstInvalid) {
                    firstInvalid = field;
                }
            });

            if (firstInvalid) {
                event.preventDefault();
                firstInvalid.focus();
                return;
            }

            form.dataset.submitting = 'true';
            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (button) {
                button.disabled = true;
            });
        });
    });
})();