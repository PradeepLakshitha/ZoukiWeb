<?php
session_start();
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
$oldImagePath = '';
$oldImageName = '';

// Set upload directory
$upload_dir = "uploads/";
$thumb_dir = $upload_dir . "thumbnails/";

// Create directories if they don't exist
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if (!is_dir($thumb_dir)) {
    mkdir($thumb_dir, 0777, true);
}

// Process image replacement
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'replace') {
    // Get the old image path from form
    $oldImagePath = $_POST['old_image'];
    $oldImageName = basename($oldImagePath);

    // Validate old image path for security
    if (strpos($oldImagePath, $upload_dir) !== 0 || !file_exists($oldImagePath)) {
        $errorMessage = "Invalid image path or file not found.";
    } else {
        // Check if a file was uploaded
        if (!isset($_FILES['new_image']) || $_FILES['new_image']['error'] !== 0) {
            $errorMessage = "No file uploaded or upload error occurred.";
        } else {
            $file = $_FILES['new_image'];

            // Check file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($file['type'], $allowedTypes)) {
                $errorMessage = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
            }
            // Check file size (5MB limit)
            else if ($file['size'] > 5 * 1024 * 1024) {
                $errorMessage = "File is too large. Maximum size is 5MB.";
            } else {
                // Get file extension of the new image
                $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                // Create temporary file names
                $tempFile = $upload_dir . 'temp_' . uniqid() . '.' . $fileExt;
                $thumbPath = $thumb_dir . $oldImageName;

                // First move the uploaded file to a temporary location
                if (move_uploaded_file($file['tmp_name'], $tempFile)) {
                    try {
                        // Simple replacement approach
                        if (replaceImage($tempFile, $oldImagePath, $thumbPath, $fileExt)) {
                            $successMessage = "Image replaced successfully!";
                        } else {
                            $errorMessage = "Failed to process the image.";
                        }

                        // Clean up temp file
                        if (file_exists($tempFile)) {
                            unlink($tempFile);
                        }
                    } catch (Exception $e) {
                        $errorMessage = "Error: " . $e->getMessage();
                        // Clean up temp file
                        if (file_exists($tempFile)) {
                            unlink($tempFile);
                        }
                    }
                } else {
                    $errorMessage = "Failed to upload file. Check directory permissions.";
                }
            }
        }
    }
}

// Get list of all existing images
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

        $existingImages[] = [
            'name' => $file,
            'path' => $fullPath,
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

/**
 * Simple image replacement function
 *
 * @param string $sourcePath Source file path
 * @param string $destPath Destination file path
 * @param string $thumbPath Thumbnail file path
 * @param string $fileExt File extension
 * @return bool Success or failure
 */
function replaceImage($sourcePath, $destPath, $thumbPath, $fileExt) {
    // Process main image
    $sourceImage = null;

    // Load the source image based on file type
    switch ($fileExt) {
        case 'jpg':
        case 'jpeg':
            $sourceImage = @imagecreatefromjpeg($sourcePath);
            break;
        case 'png':
            $sourceImage = @imagecreatefrompng($sourcePath);
            break;
        case 'gif':
            $sourceImage = @imagecreatefromgif($sourcePath);
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

    // Save to temporary files first
    $tempMainFile = sys_get_temp_dir() . '/' . uniqid('main_') . '.' . $fileExt;
    $tempThumbFile = sys_get_temp_dir() . '/' . uniqid('thumb_') . '.' . $fileExt;

    // Save the images based on file type
    $success = false;
    switch ($fileExt) {
        case 'jpg':
        case 'jpeg':
            $success = imagejpeg($newImage, $tempMainFile, 90) && imagejpeg($thumbImage, $tempThumbFile, 85);
            break;
        case 'png':
            $success = imagepng($newImage, $tempMainFile, 8) && imagepng($thumbImage, $tempThumbFile, 9);
            break;
        case 'gif':
            $success = imagegif($newImage, $tempMainFile) && imagegif($thumbImage, $tempThumbFile);
            break;
    }

    // Free up memory
    imagedestroy($sourceImage);
    imagedestroy($newImage);
    imagedestroy($thumbImage);

    if (!$success) {
        // Clean up temp files
        @unlink($tempMainFile);
        @unlink($tempThumbFile);
        return false;
    }

    // Now move the temp files to their final locations
    // First, delete the existing files
    if (file_exists($destPath)) {
        @unlink($destPath);
    }
    if (file_exists($thumbPath)) {
        @unlink($thumbPath);
    }

    // Then copy the temp files to their destinations
    $mainCopy = copy($tempMainFile, $destPath);
    $thumbCopy = copy($tempThumbFile, $thumbPath);

    // Clean up temp files
    @unlink($tempMainFile);
    @unlink($tempThumbFile);

    return $mainCopy && $thumbCopy;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Image Replacement - ZOUKI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
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
            cursor: pointer;
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

        .image-item.selected {
            border: 3px solid var(--primary-color);
        }

        .image-preview {
            width: 100%;
            height: 200px;
            object-fit: contain;
            border-radius: 8px;
            background-color: #f1f1f1;
            margin-bottom: 20px;
        }

        /* Form Styles */
        .form-control {
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            padding: 10px 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
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
        <h4 class="mb-0">Simple Image Replacement</h4>
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

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Select Image to Replace</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Click on an image to select it for replacement. The new image will keep the same filename.</p>

                        <?php if (count($existingImages) > 0): ?>
                            <div class="image-gallery">
                                <?php foreach ($existingImages as $image): ?>
                                    <div class="image-item" onclick="selectImage('<?php echo htmlspecialchars($image['path']); ?>', '<?php echo htmlspecialchars($image['name']); ?>')">
                                        <img src="<?php echo htmlspecialchars($image['path']); ?>" alt="<?php echo htmlspecialchars($image['name']); ?>">
                                        <div class="image-info">
                                            <div><?php echo htmlspecialchars($image['name']); ?></div>
                                            <div><?php echo htmlspecialchars($image['size']); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-images" style="font-size: 3rem; color: #adb5bd;"></i>
                                <h5 class="mt-3">No Images Found</h5>
                                <p class="text-muted">Upload some images first using the Bulk Image Upload tool.</p>
                                <a href="bulk_image_upload.php" class="btn btn-primary">Go to Bulk Upload</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Replace Selected Image</h5>
                    </div>
                    <div class="card-body">
                        <div id="noImageSelected" class="text-center py-4">
                            <i class="bi bi-arrow-left-circle" style="font-size: 2rem; color: #adb5bd;"></i>
                            <h5 class="mt-3">No Image Selected</h5>
                            <p class="text-muted">Click on an image from the gallery to select it for replacement.</p>
                        </div>

                        <div id="imageSelected" class="d-none">
                            <h6 class="mb-3">Selected Image:</h6>
                            <img id="previewImage" src="" alt="Selected Image" class="image-preview">
                            <p id="imageName" class="text-muted mb-3"></p>

                            <form method="POST" enctype="multipart/form-data" id="replaceForm">
                                <input type="hidden" name="action" value="replace">
                                <input type="hidden" name="old_image" id="oldImageInput">

                                <div class="mb-3">
                                    <label for="newImageInput" class="form-label">Select New Image</label>
                                    <input type="file" class="form-control" id="newImageInput" name="new_image" accept="image/*" required>
                                    <div class="form-text">Upload a new image to replace the selected one. Accepted formats: JPG, PNG, GIF. Max size: 5MB.</div>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-arrow-repeat"></i> Replace Image
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0">Instructions</h5>
                    </div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <li class="mb-2">Click on an image in the gallery to select it</li>
                            <li class="mb-2">Upload a new image using the form</li>
                            <li class="mb-2">Click "Replace Image" to update it</li>
                            <li>The new image will maintain the same filename to preserve links from products</li>
                        </ol>

                        <div class="alert alert-info mt-3 mb-0">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            Need to upload new images instead? Visit the <a href="bulk_image_upload.php" class="alert-link">Bulk Image Upload</a> page.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function selectImage(path, name) {
        // Show the image selected section
        document.getElementById('noImageSelected').classList.add('d-none');
        document.getElementById('imageSelected').classList.remove('d-none');

        // Update the form with the selected image info
        document.getElementById('previewImage').src = path;
        document.getElementById('imageName').textContent = 'File: ' + name;
        document.getElementById('oldImageInput').value = path;

        // Highlight the selected image
        document.querySelectorAll('.image-item').forEach(item => {
            item.classList.remove('selected');
        });

        // Find the clicked item and add selected class
        document.querySelectorAll('.image-item').forEach(item => {
            const itemImg = item.querySelector('img');
            if (itemImg.src.endsWith(encodeURIComponent(name)) || itemImg.src.endsWith(name)) {
                item.classList.add('selected');
            }
        });

        // Scroll to the form
        document.getElementById('imageSelected').scrollIntoView({ behavior: 'smooth' });
    }

    // File input validation
    document.getElementById('newImageInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // Check file type
            const fileType = file.type;
            const validTypes = ['image/jpeg', 'image/png', 'image/gif'];

            if (!validTypes.includes(fileType)) {
                alert('Invalid file type. Only JPG, PNG, and GIF are allowed.');
                this.value = '';
                return;
            }

            // Check file size (5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('File is too large. Maximum size is 5MB.');
                this.value = '';
                return;
            }
        }
    });

    // Form submission
    document.getElementById('replaceForm').addEventListener('submit', function() {
        const submitButton = this.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Replacing...';
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

        // If there was an old image selected, reselect it after page reload
        <?php if ($oldImagePath && $oldImageName): ?>
        selectImage('<?php echo htmlspecialchars($oldImagePath); ?>', '<?php echo htmlspecialchars($oldImageName); ?>');
        <?php endif; ?>
    });
</script>
</body>
</html>