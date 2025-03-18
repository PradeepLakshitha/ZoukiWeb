<?php
require_once 'session_check.php';
check_session(['Admin', 'Manager']);

// Set headers for JSON response
header('Content-Type: application/json');

// Directory containing uploaded images
$uploads_dir = 'uploads/';

// Check if directory exists
if (!is_dir($uploads_dir)) {
    echo json_encode([
        'success' => false,
        'message' => 'Upload directory does not exist',
        'images' => []
    ]);
    exit;
}

// Get all image files from directory
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
$images = [];

$files = scandir($uploads_dir);
foreach ($files as $file) {
    // Skip directory entries and hidden files
    if ($file === '.' || $file === '..' || substr($file, 0, 1) === '.') {
        continue;
    }

    // Check if it's an image file
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (in_array($extension, $allowed_extensions)) {
        $filepath = $uploads_dir . $file;

        // Get file info
        $filesize = filesize($filepath);
        $filetime = filemtime($filepath);

        $images[] = [
            'name' => $file,
            'path' => $filepath,
            'size' => $filesize,
            'time' => $filetime,
            'time_formatted' => date('Y-m-d H:i:s', $filetime)
        ];
    }
}

// Sort images by upload time (newest first)
usort($images, function($a, $b) {
    return $b['time'] - $a['time'];
});

// Limit to most recent 30 images
$images = array_slice($images, 0, 30);

// Return the data
echo json_encode([
    'success' => true,
    'message' => 'Images retrieved successfully',
    'images' => $images
]);