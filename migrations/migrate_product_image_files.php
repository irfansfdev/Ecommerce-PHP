<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ProductImages.php';

$db = new Database();
$products = $db->select('SELECT id, image, images FROM products ORDER BY id');
$publicRoot = realpath(__DIR__ . '/../public');
$uploadsRoot = realpath(__DIR__ . '/../public/uploads/products');
$allowedExtensions = Uploader::ALLOWED_TYPES;
$migratedCount = 0;

foreach ($products as $product) {
    $productId = (int) $product['id'];
    $paths = ProductImages::paths($product);
    if (!$paths) {
        echo "Skipping product {$productId}: no usable image path.\n";
        continue;
    }

    $updatedPaths = [];
    foreach ($paths as $path) {
        if (!$publicRoot || !$uploadsRoot || !preg_match('~^uploads/products/([^/]+)$~', $path, $matches)) {
            $updatedPaths[] = $path;
            continue;
        }

        $source = realpath($publicRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        if (!$source || strpos($source, $uploadsRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($source)) {
            echo "Keeping product {$productId} legacy path (file not found): {$path}\n";
            $updatedPaths[] = $path;
            continue;
        }

        $mime = @mime_content_type($source);
        if (!isset($allowedExtensions[$mime])) {
            echo "Keeping product {$productId} legacy path (unsupported existing MIME): {$path}\n";
            $updatedPaths[] = $path;
            continue;
        }

        $productDirectory = $uploadsRoot . DIRECTORY_SEPARATOR . $productId;
        if (!is_dir($productDirectory) && !mkdir($productDirectory, 0755, true) && !is_dir($productDirectory)) {
            throw new RuntimeException("Could not create image directory for product {$productId}.");
        }
        if (is_link($productDirectory)) {
            throw new RuntimeException("Product image directory for {$productId} is a symlink.");
        }

        $hash = substr(hash_file('sha256', $source), 0, 16);
        $filename = 'image-legacy-' . $hash . '.' . $allowedExtensions[$mime];
        $destination = $productDirectory . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($destination) && !copy($source, $destination)) {
            throw new RuntimeException("Could not copy legacy image for product {$productId}.");
        }

        $updatedPaths[] = 'uploads/products/' . $productId . '/' . $filename;
        echo "Copied product {$productId} image: {$matches[1]}\n";
    }

    $updatedPaths = array_values(array_unique($updatedPaths));
    $imagesJson = ProductImages::encode($updatedPaths);
    $primaryImage = $updatedPaths[0];
    if ($imagesJson !== $product['images'] || $primaryImage !== $product['image']) {
        $db->run('UPDATE products SET images = ?, image = ? WHERE id = ?', [$imagesJson, $primaryImage, $productId]);
        $migratedCount++;
    }
}

echo "Updated {$migratedCount} product image record(s). Legacy source files were retained.\n";