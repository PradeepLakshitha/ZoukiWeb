<?php
require_once 'session_check.php';
check_session(['Admin', 'Manager']);
include 'db_connection.php';

// Ensure only Admin & Manager can access
if (!isset($_SESSION['username']) || ($_SESSION['uType'] !== 'Admin' && $_SESSION['uType'] !== 'Manager')) {
    $_SESSION['error'] = "Access denied!";
    header("Location: dashboard.php");
    exit();
}

// Initialize variables
$successMessage = '';
$errorMessage = '';
$uploadedFiles = [];
$errorFiles = [];

// Set active tab for navigation
$activeTab = 'settings';

// Create uploads directory if it doesn't exist
$upload_dir = "uploads/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle Image Upload or Delete
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload') {
        // Check if files were uploaded
        if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
            $errorMessage = "Please select at least one image to upload.";
        } else {
            // Count total files
            $totalFiles = count($_FILES['images']['name']);

            // Create thumbnail directory if it doesn't exist
            $thumb_dir = $upload_dir . "thumbnails/";
            if (!is_dir($thumb_dir)) {
                mkdir($thumb_dir, 0777, true);
            }

            // Loop through each file
            for ($i = 0; $i < $totalFiles; $i++) {
                // Get file info
                $fileName = $_FILES['images']['name'][$i];
                $fileTmpName = $_FILES['images']['tmp_name'][$i];
                $fileSize = $_FILES['images']['size'][$i];
                $fileError = $_FILES['images']['error'][$i];
                $fileType = $_FILES['images']['type'][$i];

                // Get file extension
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                // Define allowed file types
                $allowed = array('jpg', 'jpeg', 'png', 'gif');

                // Validate file type
                if (in_array($fileExt, $allowed)) {
                    // Check for upload errors
                    if ($fileError === 0) {
                        // Check file size (5MB limit)
                        if ($fileSize < 5000000) {
                            // Get original file name without extension
                            $originalName = pathinfo($fileName, PATHINFO_FILENAME);

                            // Format current date for filename (DD-MM-YYYY)
                            $currentDate = date('d-m-Y');

                            // Create standardized file name for products
                            $newFileName = $originalName . '_' . $currentDate . '.' . $fileExt;
                            $destination = $upload_dir . $newFileName;
                            $thumbDestination = $thumb_dir . $newFileName;

                            // Process the image to ensure it meets product image standards
                            if (processProductImage($fileTmpName, $destination, $thumbDestination, $fileExt)) {
                                $uploadedFiles[] = [
                                    'original_name' => $fileName,
                                    'new_name' => $newFileName,
                                    'path' => $destination,
                                    'thumb_path' => $thumbDestination,
                                    'size' => round($fileSize / 1024, 2) . ' KB', // Convert to KB
                                    'type' => $fileType
                                ];
                            } else {
                                $errorFiles[] = [
                                    'name' => $fileName,
                                    'error' => 'Failed to process image'
                                ];
                            }
                        } else {
                            $errorFiles[] = [
                                'name' => $fileName,
                                'error' => 'File size exceeds limit (5MB)'
                            ];
                        }
                    } else {
                        $errorFiles[] = [
                            'name' => $fileName,
                            'error' => 'Upload error code: ' . $fileError
                        ];
                    }
                } else {
                    $errorFiles[] = [
                        'name' => $fileName,
                        'error' => 'File type not allowed. Only JPG, JPEG, PNG & GIF files are accepted.'
                    ];
                }
            }

            // Set success or error message
            if (count($uploadedFiles) > 0) {
                $successMessage = count($uploadedFiles) . " image(s) uploaded successfully.";
                if (count($errorFiles) > 0) {
                    $errorMessage = count($errorFiles) . " file(s) couldn't be uploaded.";
                }
            } else {
                $errorMessage = "No files were uploaded. Please check the error messages.";
            }
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['image'])) {
        $imageToDelete = trim($_POST['image']);

        // Debug the image path
        error_log("Attempting to delete image: " . $imageToDelete);

        // Skip empty paths
        if (empty($imageToDelete)) {
            $errorMessage = "No image path provided for deletion.";
            error_log("Delete failed: Empty image path");
        }
        else {
            $thumbToDelete = $upload_dir . "thumbnails/" . basename($imageToDelete);

            // Security check - make sure we're only deleting from uploads directory
            if (strpos($imageToDelete, $upload_dir) === 0 && file_exists($imageToDelete)) {
                error_log("Image exists, attempting to delete: " . $imageToDelete);

                // Delete the main image
                if (unlink($imageToDelete)) {
                    // Try to delete the thumbnail too if it exists
                    if (file_exists($thumbToDelete)) {
                        unlink($thumbToDelete);
                    }
                    $successMessage = "Image deleted successfully.";
                    error_log("Image deleted successfully");
                } else {
                    $errorMessage = "Failed to delete image.";
                    error_log("Delete failed: unlink() returned false");
                }
            } else {
                $errorMessage = "Invalid image path or image not found: " . $imageToDelete;
                error_log("Delete failed: Image not found or invalid path");
            }
        }

        // Redirect to prevent form resubmission
        header("Location: bulk_image_upload.php?success=" . urlencode($successMessage));
        exit();
    }
}

/**
 * Process an uploaded image for product use
 *
 * @param string $sourcePath Source file path
 * @param string $destPath Destination file path
 * @param string $thumbPath Thumbnail file path
 * @param string $fileExt File extension
 * @return bool Success or failure
 */
function processProductImage($sourcePath, $destPath, $thumbPath, $fileExt) {
    // Load the source image based on file type
    $sourceImage = null;
    switch ($fileExt) {
        case 'jpg':
        case 'jpeg':
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case 'png':
            $sourceImage = imagecreatefrompng($sourcePath);
            break;
        case 'gif':
            $sourceImage = imagecreatefromgif($sourcePath);
            break;
        default:
            return false;
    }

    if (!$sourceImage) {
        return false;
    }

    // Get original dimensions
    $origWidth = imagesx($sourceImage);
    $origHeight = imagesy($sourceImage);

    // Set standard product image dimensions
    $maxWidth = 800;
    $maxHeight = 800;

    // Set thumbnail dimensions
    $thumbWidth = 150;
    $thumbHeight = 150;

    // Calculate new dimensions while maintaining aspect ratio
    if ($origWidth > $maxWidth || $origHeight > $maxHeight) {
        $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
        $newWidth = round($origWidth * $ratio);
        $newHeight = round($origHeight * $ratio);
    } else {
        $newWidth = $origWidth;
        $newHeight = $origHeight;
    }

    // Create main image
    $newImage = imagecreatetruecolor($newWidth, $newHeight);

    // Handle transparency for PNG and GIF
    if ($fileExt == 'png' || $fileExt == 'gif') {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
    }

    // Resize the image
    imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

    // Create thumbnail image (square crop from center)
    $thumbImage = imagecreatetruecolor($thumbWidth, $thumbHeight);

    // Handle transparency for thumbnail
    if ($fileExt == 'png' || $fileExt == 'gif') {
        imagealphablending($thumbImage, false);
        imagesavealpha($thumbImage, true);
        $transparent = imagecolorallocatealpha($thumbImage, 255, 255, 255, 127);
        imagefilledrectangle($thumbImage, 0, 0, $thumbWidth, $thumbHeight, $transparent);
    }

    // Calculate crop dimensions
    $sourceDim = min($newWidth, $newHeight);
    $sourceX = ($newWidth - $sourceDim) / 2;
    $sourceY = ($newHeight - $sourceDim) / 2;

    // Create the thumbnail with square dimensions
    imagecopyresampled($thumbImage, $newImage, 0, 0, $sourceX, $sourceY, $thumbWidth, $thumbHeight, $sourceDim, $sourceDim);

    // Save the images based on file type
    $success = false;
    switch ($fileExt) {
        case 'jpg':
        case 'jpeg':
            $success = imagejpeg($newImage, $destPath, 90) && imagejpeg($thumbImage, $thumbPath, 85);
            break;
        case 'png':
            $success = imagepng($newImage, $destPath, 8) && imagepng($thumbImage, $thumbPath, 9);
            break;
        case 'gif':
            $success = imagegif($newImage, $destPath) && imagegif($thumbImage, $thumbPath);
            break;
    }

    // Free up memory
    imagedestroy($sourceImage);
    imagedestroy($newImage);
    imagedestroy($thumbImage);

    return $success;
}

// Get existing images in uploads directory
$existingImages = [];
if (is_dir($upload_dir)) {
    $files = scandir($upload_dir);
    foreach ($files as $file) {
        $fileExt = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $fullPath = $upload_dir . $file;

        // Skip directories, temporary files, and non-image files
        if (is_dir($fullPath) ||
            $file === '.' ||
            $file === '..' ||
            strpos($file, 'temp_') === 0 ||
            strpos($file, 'backup_') === 0 ||
            !in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif'])) {
            continue;
        }

        $imagePath = $fullPath;
        $displayPath = $imagePath;

        $existingImages[] = [
            'name' => $file,
            'path' => $displayPath,
            'real_path' => $fullPath, // Store the real path without cache busting
            'size' => round(filesize($fullPath) / 1024, 2) . ' KB',
            'type' => mime_content_type($fullPath),
            'date' => date('Y-m-d H:i:s', filemtime($fullPath))
        ];
    }

    // Sort by most recent first
    usort($existingImages, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}

// Check for URL parameters (for redirects after form submission)
if (isset($_GET['success'])) {
    $successMessage = $_GET['success'];
}
if (isset($_GET['error'])) {
    $errorMessage = $_GET['error'];
}

// Check for session messages
if (isset($_SESSION['success'])) {
    $successMessage = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $errorMessage = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Image Upload - ZOUKI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --primary-color: #4CAF50;
            --secondary-color: #2196F3;
            --background-color: #f8f9fa;
            --sidebar-width: 250px;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --success-color: #28a745;
            --card-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Base Styles */
        body {
            background-color: var(--background-color);
            font-family: 'Inter', 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            background: #2c3e50;
            padding-top: 60px;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            margin: 4px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }

        .sidebar .nav-link i {
            font-size: 1.1rem;
        }

        /* Top Navbar Styles */
        .top-navbar {
            position: fixed;
            top: 0;
            right: 0;
            left: var(--sidebar-width);
            height: 60px;
            background: white;
            box-shadow: var(--card-shadow);
            z-index: 999;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s ease;
        }

        .top-navbar .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .top-navbar .user-info .user-name {
            font-weight: 500;
            color: #2c3e50;
        }

        /* Main Content Area */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 80px 20px 20px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        /* Card Styles */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 20px;
            background: white;
        }

        .card-header {
            background: white;
            border-bottom: 1px solid rgba(0,0,0,0.08);
            padding: 15px 20px;
            border-radius: 12px 12px 0 0;
        }

        .card-body {
            padding: 20px;
        }

        /* File Upload Styles */
        .file-upload {
            border: 2px dashed #e0e0e0;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            position: relative;
            transition: all 0.3s ease;
            background: #f8f9fa;
            margin-bottom: 20px;
        }

        .file-upload.dragover {
            border-color: var(--primary-color);
            background: #f0f0f0;
        }

        .file-upload i {
            font-size: 3rem;
            color: #adb5bd;
            margin-bottom: 15px;
        }

        .file-upload-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .selected-files {
            margin-top: 10px;
            font-size: 0.875rem;
            color: #495057;
        }

        /* Button Styles */
        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
            padding: 10px 16px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: #43a047;
            border-color: #43a047;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        /* Image Gallery */
        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .image-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            aspect-ratio: 1/1;
        }

        .image-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .image-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .image-info {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            font-size: 0.75rem;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .image-item:hover .image-info {
            opacity: 1;
        }

        .copy-path {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(255,255,255,0.8);
            color: #333;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .image-item:hover .copy-path {
            opacity: 1;
        }

        .image-actions {
            position: absolute;
            bottom: 5px;
            right: 5px;
            display: flex;
            gap: 5px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .image-item:hover .image-actions {
            opacity: 1;
        }

        .btn-delete {
            background: rgba(255,255,255,0.8);
            color: #333;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-delete:hover {
            background: rgba(220, 53, 69, 0.8);
            color: white;
        }

        /* Progress Bar */
        .progress {
            height: 10px;
            border-radius: 5px;
            margin-top: 20px;
        }

        /* Uploaded Files Table */
        .uploaded-files-table {
            margin-top: 20px;
        }

        /* Error Files List */
        .error-files-list {
            margin-top: 20px;
            max-height: 200px;
            overflow-y: auto;
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
                padding-top: 50px;
            }

            .sidebar .nav-link span {
                display: none;
            }

            .sidebar .nav-link {
                padding: 12px;
                margin: 4px 8px;
                justify-content: center;
            }

            .top-navbar {
                left: 60px;
            }

            .main-content {
                margin-left: 60px;
            }

            .image-gallery {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            }
        }
    </style>
</head>
<body>
<!-- Sidebar -->
<nav class="sidebar">
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link" href="dashboard.php">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="products_management.php">
                <i class="bi bi-box"></i>
                <span>Products</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="categories_groups.php?tab=categories">
                <i class="bi bi-tags"></i>
                <span>Categories</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="categories_groups.php?tab=groups">
                <i class="bi bi-collection"></i>
                <span>Groups</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="users.php">
                <i class="bi bi-people"></i>
                <span>Users</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="reports.php">
                <i class="bi bi-graph-up"></i>
                <span>Reports</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="settings.php">
                <i class="bi bi-gear"></i>
                <span>Settings</span>
            </a>
        </li>
    </ul>
</nav>

<!-- Top Navbar -->
<nav class="top-navbar">
    <div class="d-flex align-items-center">
        <h4 class="mb-0">Bulk Image Upload</h4>
    </div>
    <div class="user-info">
        <span class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
        <a href="logout.php" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</nav>

<!-- Main Content -->
<div class="main-content">
    <div class="container-fluid">
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($errorMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($successMessage): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($successMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="bi bi-info-circle me-2"></i> <strong>Tip:</strong> You can upload multiple images at once, and delete images by hovering over them and clicking the trash icon.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

        <div class="row">
            <!-- Upload Section -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Upload Images</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-4">
                            Upload multiple product images at once. These images can then be used when adding or updating products.
                        </p>

                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="upload">

                            <div class="file-upload" id="fileUploadArea">
                                <i class="bi bi-cloud-arrow-up"></i>
                                <h5>Drag & Drop Images</h5>
                                <p class="text-muted">or click to browse files</p>
                                <input type="file" name="images[]" id="imageInput" class="file-upload-input" accept="image/*" multiple>
                            </div>

                            <div id="selectedFiles" class="selected-files d-none">
                                <strong>Selected files:</strong>
                                <ul id="fileList" class="list-unstyled"></ul>
                            </div>

                            <div class="progress d-none" id="uploadProgress">
                                <div class="progress-bar bg-success" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>

                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary w-100" id="uploadButton" disabled>
                                    <i class="bi bi-upload"></i> Upload Images
                                </button>
                            </div>
                        </form>

                        <?php if (count($uploadedFiles) > 0): ?>
                            <div class="uploaded-files-table mt-4">
                                <h6>Successfully Uploaded Files</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                        <tr>
                                            <th>Original Name</th>
                                            <th>New Name</th>
                                            <th>Size</th>
                                            <th>Path</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($uploadedFiles as $file): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($file['original_name']); ?></td>
                                                <td><?php echo htmlspecialchars($file['new_name']); ?></td>
                                                <td><?php echo htmlspecialchars($file['size']); ?></td>
                                                <td>
                                                    <code class="small"><?php echo htmlspecialchars($file['path']); ?></code>
                                                    <button class="btn btn-sm btn-outline-secondary ms-2 copy-btn" data-path="<?php echo htmlspecialchars($file['path']); ?>">
                                                        <i class="bi bi-clipboard"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (count($errorFiles) > 0): ?>
                            <div class="error-files-list mt-4">
                                <h6>Files with Errors</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                        <tr>
                                            <th>File Name</th>
                                            <th>Error</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($errorFiles as $file): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($file['name']); ?></td>
                                                <td class="text-danger"><?php echo htmlspecialchars($file['error']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Gallery Section -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Image Gallery</h5>
                        <span class="badge bg-primary"><?php echo count($existingImages); ?> Images</span>
                    </div>
                    <div class="card-body">
                        <?php if (count($existingImages) > 0): ?>
                            <div class="image-gallery">
                                <?php foreach ($existingImages as $image): ?>
                                    <div class="image-item">
                                        <img src="<?php echo htmlspecialchars($image['path']); ?>" alt="<?php echo htmlspecialchars($image['name']); ?>">
                                        <div class="image-info">
                                            <div><?php echo htmlspecialchars($image['name']); ?></div>
                                            <div><?php echo htmlspecialchars($image['size']); ?></div>
                                        </div>
                                        <button class="copy-path" data-path="<?php echo htmlspecialchars($image['real_path']); ?>" title="Copy path">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                        <div class="image-actions">
                                            <form method="POST" action="bulk_image_upload.php" style="display:inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="image" value="<?php echo htmlspecialchars($image['real_path']); ?>">
                                                <button type="submit" class="btn-delete" onclick="return confirm('Are you sure you want to delete this image?');" title="Delete image">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-images" style="font-size: 3rem; color: #adb5bd;"></i>
                                <h5 class="mt-3">No Images Found</h5>
                                <p class="text-muted">Upload some images to see them here.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upload Instructions -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Upload Instructions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Supported File Types</h6>
                        <p>The following image formats are supported:</p>
                        <ul>
                            <li>JPEG/JPG</li>
                            <li>PNG</li>
                            <li>GIF</li>
                        </ul>

                        <h6>File Size Limits</h6>
                        <p>Maximum file size: <strong>5MB</strong> per image</p>

                        <h6>Image Processing</h6>
                        <p>All uploaded images are automatically:</p>
                        <ul>
                            <li>Resized to standard product dimensions (max 800×800px)</li>
                            <li>Optimized for web display</li>
                            <li>Given product-friendly filenames</li>
                            <li>Processed with thumbnails for gallery view</li>
                        </ul>
                    </div>

                    <div class="col-md-6">
                        <h6>Using Images in Products</h6>
                        <p>After uploading, you can use these images when creating or editing products:</p>
                        <ol>
                            <li>Copy the image path by hovering over an image and clicking the clipboard icon</li>
                            <li>When adding/editing a product, paste this path in the image field</li>
                            <li>Alternatively, use the image filename in your CSV imports in the image_path column</li>
                        </ol>

                        <h6>Image Organization</h6>
                        <p>Images are organized as follows:</p>
                        <ul>
                            <li>Main product images: <code>uploads/ActualImageName_DD-MM-YYYY.extension</code></li>
                            <li>Thumbnails: <code>uploads/thumbnails/ActualImageName_DD-MM-YYYY.extension</code></li>
                        </ul>
                        <p>This format matches exactly what the product management system expects.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Copy Path Success Toast -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
    <div id="copyToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi bi-check-circle me-2"></i> Image path copied to clipboard!
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // File Upload Handling
    const fileInput = document.getElementById('imageInput');
    const fileUploadArea = document.getElementById('fileUploadArea');
    const selectedFiles = document.getElementById('selectedFiles');
    const fileList = document.getElementById('fileList');
    const uploadButton = document.getElementById('uploadButton');
    const uploadProgress = document.getElementById('uploadProgress');
    const progressBar = uploadProgress.querySelector('.progress-bar');

    // Handle file selection
    fileInput.addEventListener('change', function(e) {
        const files = e.target.files;
        if (files.length > 0) {
            selectedFiles.classList.remove('d-none');
            uploadButton.disabled = false;

            // Clear previous list
            fileList.innerHTML = '';

            // Check each file
            let validFiles = true;

            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const fileSize = (file.size / 1024).toFixed(2); // Size in KB

                // Validate file type
                if (!file.type.match('image.*')) {
                    const li = document.createElement('li');
                    li.innerHTML = `<strong class="text-danger">${file.name}</strong> (${fileSize} KB) - Invalid file type`;
                    fileList.appendChild(li);
                    validFiles = false;
                } else if (file.size > 5 * 1024 * 1024) { // 5MB limit
                    const li = document.createElement('li');
                    li.innerHTML = `<strong class="text-danger">${file.name}</strong> (${fileSize} KB) - File too large (max 5MB)`;
                    fileList.appendChild(li);
                    validFiles = false;
                } else {
                    const li = document.createElement('li');
                    li.innerHTML = `<strong>${file.name}</strong> (${fileSize} KB)`;
                    fileList.appendChild(li);
                }
            }

            uploadButton.disabled = !validFiles;
        } else {
            selectedFiles.classList.add('d-none');
            uploadButton.disabled = true;
        }
    });

    // Drag and drop functionality
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        fileUploadArea.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        fileUploadArea.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        fileUploadArea.addEventListener(eventName, unhighlight, false);
    });

    function highlight() {
        fileUploadArea.classList.add('dragover');
    }

    function unhighlight() {
        fileUploadArea.classList.remove('dragover');
    }

    fileUploadArea.addEventListener('drop', handleDrop, false);

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        fileInput.files = files;

        // Trigger change event
        const event = new Event('change');
        fileInput.dispatchEvent(event);
    }

    // Form submission and progress simulation (for upload only)
    document.querySelector('form[action=""]').addEventListener('submit', function() {
        if (fileInput.files.length > 0) {
            uploadProgress.classList.remove('d-none');
            uploadButton.disabled = true;

            // Simulate progress (in a real implementation, you'd use XHR to track actual upload progress)
            let progress = 0;
            const interval = setInterval(function() {
                progress += 5;
                progressBar.style.width = progress + '%';
                progressBar.setAttribute('aria-valuenow', progress);

                if (progress >= 100) {
                    clearInterval(interval);
                }
            }, 100);
        }
    });

    // Copy path functionality
    document.querySelectorAll('.copy-btn, .copy-path').forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent event from bubbling up
            const path = this.getAttribute('data-path');
            navigator.clipboard.writeText(path).then(() => {
                const toast = new bootstrap.Toast(document.getElementById('copyToast'));
                toast.show();
            });
        });
    });

    // Responsive sidebar toggle
    document.addEventListener('DOMContentLoaded', function() {
        const mediaQuery = window.matchMedia('(max-width: 768px)');
        function handleScreenChange(e) {
            if (e.matches) {
                document.querySelector('.sidebar').classList.add('collapsed');
                document.querySelector('.main-content').classList.add('expanded');
            } else {
                document.querySelector('.sidebar').classList.remove('collapsed');
                document.querySelector('.main-content').classList.remove('expanded');
            }
        }
        mediaQuery.addListener(handleScreenChange);
        handleScreenChange(mediaQuery);
    });
</script>
</body>
</html>