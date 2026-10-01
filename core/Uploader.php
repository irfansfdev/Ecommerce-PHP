<?php

// Handles a single uploaded image: checks it's really an image, checks the
// size, and saves it under public/uploads/{folder}/ with a random name so
// two people uploading "photo.jpg" never overwrite each other.
class Uploader
{
    const MAX_BYTES = 2 * 1024 * 1024; // 2MB

    const ALLOWED_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    // $file is one entry from $_FILES, $folder is 'products' or 'categories'.
    // Returns ['success' => true, 'path' => 'uploads/products/xxxx.jpg']
    // or ['success' => false, 'message' => '...'].
    public static function validate($file)
    {
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'message' => null]; // nothing uploaded, not necessarily an error
        }

        if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return ['success' => false, 'message' => 'Image size must not exceed 2MB.'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Image upload failed. Please try again.'];
        }

        $fileSize = isset($file['tmp_name']) && is_file($file['tmp_name']) ? filesize($file['tmp_name']) : false;
        if ($fileSize === false) {
            return ['success' => false, 'message' => 'Please upload a valid image.'];
        }

        if ($fileSize > self::MAX_BYTES) {
            return ['success' => false, 'message' => 'Image size must not exceed 2MB.'];
        }

        $mime = @mime_content_type($file['tmp_name']);

        if (!isset(self::ALLOWED_TYPES[$mime])) {
            return ['success' => false, 'message' => 'Please upload a valid image.'];
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $extensionTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
        ];
        if (!isset($extensionTypes[$extension]) || $extensionTypes[$extension] !== $mime) {
            return ['success' => false, 'message' => 'Please upload a valid image.'];
        }

        return ['success' => true, 'extension' => self::ALLOWED_TYPES[$mime]];
    }

    public static function save($file, $folder)
    {
        $validation = self::validate($file);
        if (!$validation['success']) {
            return $validation;
        }

        $extension = $validation['extension'];
        $filename = bin2hex(random_bytes(10)) . '.' . $extension;

        $targetDir = __DIR__ . '/../public/uploads/' . $folder . '/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $targetDir . $filename)) {
            return ['success' => false, 'message' => 'Could not save the uploaded image.'];
        }

        return ['success' => true, 'path' => 'uploads/' . $folder . '/' . $filename];
    }

    // Deletes a previously uploaded image, but only ever touches files inside
    // public/uploads/ - never the seed images that ship with the template.
    public static function delete($path)
    {
        $path = str_replace('\\', '/', (string) $path);
        if (!preg_match('~^uploads/[A-Za-z0-9_-]+(?:/[A-Za-z0-9._-]+)*$~D', $path)) {
            return;
        }

        $uploadsRoot = realpath(__DIR__ . '/../public/uploads');
        $fullPath = realpath(__DIR__ . '/../public/' . $path);
        if ($uploadsRoot && $fullPath && strpos($fullPath, $uploadsRoot . DIRECTORY_SEPARATOR) === 0 && is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}
