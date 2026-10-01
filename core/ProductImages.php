<?php
require_once __DIR__ . '/Uploader.php';

class ProductImages
{
    const MIN_IMAGES = 1;
    const MAX_IMAGES = 5;

    public static function paths($product)
    {
        $paths = [];
        $decoded = $product['images'] ?? null;
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        if (is_array($decoded)) {
            foreach ($decoded as $path) {
                if (is_string($path) && self::isSafePath($path) && !in_array($path, $paths, true)) {
                    $paths[] = $path;
                }
            }
        }

        if (!$paths && isset($product['image']) && is_string($product['image']) && self::isSafePath($product['image'])) {
            $paths[] = $product['image'];
        }

        return $paths;
    }

    public static function primaryPath($product)
    {
        $paths = self::paths($product);
        return $paths[0] ?? ($product['image'] ?? '');
    }

    public static function finalCountError($remainingExisting, $newUploads, $isEdit = true)
    {
        $total = (int) $remainingExisting + (int) $newUploads;
        if ($total < self::MIN_IMAGES) {
            return $isEdit
                ? 'A product must have at least 1 image.'
                : 'At least 1 product image is required.';
        }
        if ($total > self::MAX_IMAGES) {
            return 'You can have a maximum of 5 images.';
        }
        return null;
    }

    public static function encode($paths)
    {
        $paths = array_values(array_unique(array_filter($paths, function ($path) {
            return is_string($path) && self::isSafePath($path);
        })));

        if (count($paths) < self::MIN_IMAGES || count($paths) > self::MAX_IMAGES) {
            throw new InvalidArgumentException('A product must have between 1 and 5 images.');
        }

        return json_encode($paths, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function validateUploads($uploadedFiles)
    {
        $files = self::normaliseUploads($uploadedFiles);
        if ($files === false) {
            return ['success' => false, 'files' => [], 'message' => 'Image upload data is invalid.'];
        }
        if (count($files) > self::MAX_IMAGES) {
            return ['success' => false, 'files' => [], 'message' => 'You can upload a maximum of 5 images.'];
        }

        foreach ($files as $file) {
            $validation = Uploader::validate($file);
            if (!$validation['success']) {
                $message = $validation['message'] ?? 'Please upload a valid image file.';
                if (strpos($message, 'valid image') !== false) {
                    $message = 'Please upload a valid image file.';
                }
                return ['success' => false, 'files' => [], 'message' => $message];
            }
        }

        return ['success' => true, 'files' => $files, 'message' => null];
    }

    public static function storeUploads($files, $productId)
    {
        $productId = (int) $productId;
        if ($productId <= 0) {
            return ['success' => false, 'paths' => [], 'message' => 'Could not determine the product image folder.'];
        }

        $validation = self::validateUploads($files);
        if (!$validation['success']) {
            return ['success' => false, 'paths' => [], 'message' => $validation['message']];
        }
        if (!$validation['files']) {
            return ['success' => true, 'paths' => [], 'message' => null];
        }

        $productsRoot = __DIR__ . '/../public/uploads/products';
        if (!is_dir($productsRoot) && !mkdir($productsRoot, 0755, true) && !is_dir($productsRoot)) {
            return ['success' => false, 'paths' => [], 'message' => 'Could not create the product image folder.'];
        }
        if (is_link($productsRoot)) {
            return ['success' => false, 'paths' => [], 'message' => 'The product image folder is not available.'];
        }

        $productDirectory = $productsRoot . DIRECTORY_SEPARATOR . $productId;
        if (is_link($productDirectory)) {
            return ['success' => false, 'paths' => [], 'message' => 'The product image folder is not available.'];
        }
        if (!is_dir($productDirectory) && !mkdir($productDirectory, 0755, true) && !is_dir($productDirectory)) {
            return ['success' => false, 'paths' => [], 'message' => 'Could not create the product image folder.'];
        }

        $rootReal = realpath($productsRoot);
        $directoryReal = realpath($productDirectory);
        if (!$rootReal || !$directoryReal || strpos($directoryReal, $rootReal . DIRECTORY_SEPARATOR) !== 0) {
            return ['success' => false, 'paths' => [], 'message' => 'The product image folder is not available.'];
        }

        $savedPaths = [];
        foreach ($validation['files'] as $file) {
            $fileValidation = Uploader::validate($file);
            if (!$fileValidation['success']) {
                self::deleteImagePaths($productId, $savedPaths);
                return ['success' => false, 'paths' => [], 'message' => $fileValidation['message']];
            }

            $filename = 'image-' . bin2hex(random_bytes(8)) . '.' . $fileValidation['extension'];
            $destination = $directoryReal . DIRECTORY_SEPARATOR . $filename;
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                self::deleteImagePaths($productId, $savedPaths);
                return ['success' => false, 'paths' => [], 'message' => 'Could not save the uploaded images. Please try again.'];
            }
            $savedPaths[] = 'uploads/products/' . $productId . '/' . $filename;
        }

        return ['success' => true, 'paths' => $savedPaths, 'message' => null];
    }

    public static function deleteImagePaths($productId, $paths)
    {
        $productId = (int) $productId;
        foreach ($paths as $path) {
            if (!is_string($path)) {
                continue;
            }
            if ($productId > 0 && strpos($path, 'uploads/products/' . $productId . '/') === 0) {
                $remainder = substr($path, strlen('uploads/products/' . $productId . '/'));
                if ($remainder !== '' && basename($remainder) === $remainder && preg_match('/^[A-Za-z0-9._-]+$/', $remainder)) {
                    Uploader::delete($path);
                }
            } elseif (strpos($path, 'uploads/products/') === 0) {
                Uploader::delete($path);
            }
        }
    }

    public static function deleteProductDirectory($productId)
    {
        $productId = (int) $productId;
        if ($productId <= 0) {
            return;
        }

        $productsRoot = __DIR__ . '/../public/uploads/products';
        if (is_link($productsRoot)) {
            return;
        }
        $root = realpath($productsRoot);
        $directory = __DIR__ . '/../public/uploads/products/' . $productId;
        if (!$root || is_link($directory) || !is_dir($directory)) {
            return;
        }
        $realDirectory = realpath($directory);
        if (!$realDirectory || strpos($realDirectory, $root . DIRECTORY_SEPARATOR) !== 0) {
            return;
        }

        self::removeDirectoryContents($realDirectory);
        @rmdir($realDirectory);
    }

    public static function removeEmptyProductDirectory($productId)
    {
        $productId = (int) $productId;
        if ($productId <= 0) {
            return;
        }
        $root = realpath(__DIR__ . '/../public/uploads/products');
        $directory = __DIR__ . '/../public/uploads/products/' . $productId;
        if (!$root || is_link($directory) || !is_dir($directory)) {
            return;
        }
        $realDirectory = realpath($directory);
        if ($realDirectory && strpos($realDirectory, $root . DIRECTORY_SEPARATOR) === 0) {
            $entries = scandir($realDirectory);
            if ($entries === ['.', '..']) {
                @rmdir($realDirectory);
            }
        }
    }

    private static function removeDirectoryContents($directory)
    {
        $entries = scandir($directory);
        if ($entries === false) {
            return;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            if (is_link($path) || is_file($path)) {
                @unlink($path);
            } elseif (is_dir($path)) {
                self::removeDirectoryContents($path);
                @rmdir($path);
            }
        }
    }

    private static function normaliseUploads($files)
    {
        if (!is_array($files)) {
            return [];
        }
        if (!isset($files['error'])) {
            if (!$files) {
                return [];
            }
            $normalised = [];
            foreach ($files as $file) {
                if (!is_array($file) || !isset($file['error'])) {
                    return false;
                }
                if ((int) $file['error'] !== UPLOAD_ERR_NO_FILE) {
                    $normalised[] = $file;
                }
            }
            return $normalised;
        }
        if (!is_array($files['error'])) {
            return $files['error'] === UPLOAD_ERR_NO_FILE ? [] : [$files];
        }

        $normalised = [];
        foreach ($files['error'] as $index => $error) {
            if ((int) $error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if (!isset($files['name'][$index], $files['type'][$index], $files['tmp_name'][$index], $files['size'][$index])) {
                return false;
            }
            $normalised[] = [
                'name' => $files['name'][$index],
                'type' => $files['type'][$index],
                'tmp_name' => $files['tmp_name'][$index],
                'error' => (int) $error,
                'size' => (int) $files['size'][$index],
            ];
        }
        return $normalised;
    }

    private static function isSafePath($path)
    {
        return preg_match('~^products/[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp|gif)$~i', $path) === 1
            || preg_match('~^uploads/products/[0-9]+/[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp|gif)$~i', $path) === 1
            || preg_match('~^uploads/products/[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp|gif)$~i', $path) === 1;
    }
}