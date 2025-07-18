<?php
// === CONFIGURATION ===
$sizeXS = 200;
$sizeS  = 400;
$sizeM  = 600;
$sizeL  = 900;
$sizeXL = 1600;
$sizeG  = 800;
$jpeg_quality = 90;

// Batch range (0 = no limit)
$from = 1;
$to   = 0; // Set to 0 to process all

define('_JEXEC', 1);

// Force use of modern Upload class (avoids broken legacy version)
$uploadclassfile = __DIR__ . '/media/k2/assets/vendors/verot/class.upload.php/src/class.upload.php';

if (!file_exists($uploadclassfile)) {
    exit("❌ Cannot find modern class.upload.php at $uploadclassfile\n");
}

require_once($uploadclassfile);
use \Verot\Upload\Upload;



require_once __DIR__ . '/configuration.php';

$sourcedir = __DIR__ . '/media/k2/items/src';
$targetdir = __DIR__ . '/media/k2/items/cache';

$sizes = [
    'XS'      => $sizeXS,
    'S'       => $sizeS,
    'M'       => $sizeM,
    'L'       => $sizeL,
    'XL'      => $sizeXL,
    'Generic' => $sizeG
];

// === IMAGE RESIZE FUNCTION ===
function buildImage($sourcefile, $targetfile, $size, $jpeg_quality = 80)
{
    if (file_exists($targetfile)) {
        echo "    ⏭️  Skipped (already exists): " . basename($targetfile) . "\n";
        return true;
    }

    $handle = new Upload($sourcefile, 'en');
    $handle->uploaded = true;
    $handle->image_resize = true;
    $handle->image_ratio_y = true;
    $handle->image_convert = 'jpg';
    $handle->jpeg_quality = $jpeg_quality;
    $handle->file_auto_rename = false;
    $handle->file_overwrite = true;
    $handle->file_new_name_body = basename($targetfile, '.jpg');
    $handle->image_x = (int)$size;

    echo "    🔧 Starting resize to {$size}px → " . basename($targetfile) . "\n";

    if (!$handle->Process(dirname($targetfile))) {
        echo "    ❌ Failed: " . $handle->error . "\n";
        file_put_contents('failed.log', basename($sourcefile) . " failed for size {$size} → " . basename($targetfile) . "\n", FILE_APPEND);
        return false;
    }

    return true;
}

// === MAIN MULTI-SIZE WRAPPER ===
function buildImages($sourcefile, $targetdir, $sizes, $jpeg_quality = 80)
{
    $success = true;
    foreach ($sizes as $key => $value) {
        if ($value != 0) {
            $filename = basename($sourcefile, '.jpg');
            $targetfile = $targetdir . '/' . $filename . '_' . $key . '.jpg';
            if (!buildImage($sourcefile, $targetfile, $value, $jpeg_quality)) {
                $success = false;
            }
        }
    }
    return $success;
}

// === GATHER FILES AND SORT ===
$files = glob($sourcedir . '/*.jpg');
sort($files, SORT_STRING | SORT_FLAG_CASE); // Alphabetical, case-insensitive

$totalFiles = count($files);
if ($totalFiles === 0) {
    echo "❌ No images found in {$sourcedir}\n";
    exit;
}

echo "📷 Total source images found: {$totalFiles}\n\n";

$index = 0;
$processed = 0;

foreach ($files as $file) {
    $index++;

    if ($from > 0 && $index < $from) continue;
    if ($to > 0 && $index > $to) break;

    $filename = basename($file);
    echo "▶️  Source file {$index}/{$totalFiles}: {$filename}\n";

    $success = buildImages($file, $targetdir, $sizes, $jpeg_quality);
    if ($success) {
        echo "✅ Completed: {$filename}\n\n";
    } else {
        echo "⚠️  Some sizes failed for: {$filename}\n\n";
    }

    $processed++;
}

echo "\n✅ Done. Processed {$processed} images.\n";
if (file_exists('failed.log')) {
    echo "📄 Failures logged to failed.log\n";
}

