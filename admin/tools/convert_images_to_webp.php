<?php
// admin/tools/convert_images_to_webp.php
// CLI script to batch-convert product images to WebP and generate resized variants.
// Usage: php convert_images_to_webp.php [--dir=PATH] [--widths=400,800,1200] [--quality=80] [--dry-run]

set_time_limit(0);

$options = getopt('', ['dir::', 'widths::', 'quality::', 'dry-run']);
$dir = $options['dir'] ?? __DIR__ . '/../../admin/storage/uploads/products';
$widths = isset($options['widths']) ? array_map('intval', explode(',', $options['widths'])) : [400,800,1200];
$quality = isset($options['quality']) ? (int)$options['quality'] : 80;
$dryRun = isset($options['dry-run']);

$allowed_exts = ['jpg','jpeg','png','webp'];

if (!is_dir($dir)) {
    fwrite(STDERR, "Directory not found: $dir\n");
    exit(1);
}

$files = scandir($dir);
$processed = 0;
foreach ($files as $file) {
    if (in_array($file, ['.','..'])) continue;
    $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
    if (!is_file($path)) continue;

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_exts)) continue;

    // Skip tiny files
    if (filesize($path) < 1024) continue;

    $basename = preg_replace('/\.[^.]+$/', '', $file);

    foreach ($widths as $w) {
        $webp_target = $dir . DIRECTORY_SEPARATOR . $basename . '-' . $w . '.webp';
        $fallback_jpg = $dir . DIRECTORY_SEPARATOR . $basename . '-' . $w . '.jpg';

        if (file_exists($webp_target)) {
            fwrite(STDOUT, "Skipping existing: $webp_target\n");
            continue;
        }

        if ($dryRun) {
            fwrite(STDOUT, "Would create: $webp_target\n");
            continue;
        }

        // Load source image using GD
        $src = null;
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $src = @imagecreatefromjpeg($path);
                break;
            case 'png':
                $src = @imagecreatefrompng($path);
                break;
            case 'webp':
                if (function_exists('imagecreatefromwebp')) {
                    $src = @imagecreatefromwebp($path);
                } else {
                    // Can't read webp on this platform
                    fwrite(STDERR, "WebP support not available in GD for: $path\n");
                    continue 2;
                }
                break;
            default:
                continue 2;
        }

        if (!$src) {
            fwrite(STDERR, "Failed to load image: $path\n");
            continue;
        }

        $orig_w = imagesx($src);
        $orig_h = imagesy($src);
        if ($orig_w <= $w) {
            // If source is smaller than target width, just convert without resize
            $new_w = $orig_w;
            $new_h = $orig_h;
        } else {
            $new_w = $w;
            $new_h = (int)round(($orig_h / $orig_w) * $new_w);
        }

        $dst = imagecreatetruecolor($new_w, $new_h);
        // Preserve transparency for PNGs
        if ($ext === 'png') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);

        // Create JPEG fallback
        if (!imagejpeg($dst, $fallback_jpg, $quality)) {
            fwrite(STDERR, "Failed to write JPEG: $fallback_jpg\n");
        }

        // Create WebP (if supported)
        if (function_exists('imagewebp')) {
            if (!imagewebp($dst, $webp_target, $quality)) {
                fwrite(STDERR, "Failed to write WebP: $webp_target\n");
            }
        } else {
            fwrite(STDERR, "imagewebp() not available; cannot generate WebP for $path\n");
        }

        imagedestroy($dst);
        imagedestroy($src);

        fwrite(STDOUT, "Generated: $webp_target and $fallback_jpg\n");
        $processed++;
    }
}

fwrite(STDOUT, "Done. Processed variants: $processed\n");

?>
