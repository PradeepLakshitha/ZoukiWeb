<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
file_put_contents('debug.log', "Form submitted: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

require_once 'session_check.php';
check_session(['Admin', 'Manager']);
include 'db_connection.php';

// Ensure only Admin & Manager can access
if (!isset($_SESSION['username']) || ($_SESSION['uType'] !== 'Admin' && $_SESSION['uType'] !== 'Manager')) {
    $_SESSION['error'] = "Access denied!";
    header("Location: dashboard.php");
    exit();
}
$upload_dir = "uploads/";
// Set active tab for navigation
$activeTab = 'products';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}
// Initialize variables
$successMessage = '';
$errorMessage = '';

// Handle Product Actions (Add, Update, Delete)
if ($_SERVER["REQUEST_METHOD"] == "POST") {

// Debug POST data
    error_log("POST data received: " . print_r($_POST, true));
    error_log("FILES data received: " . print_r($_FILES, true));

    $action = $_POST['action'];
    $product_id = $_POST['product_id'] ?? null;
    $product_name = trim($_POST['product_name']);
    $allergens = trim($_POST['allergens']);
    $ingredients = trim($_POST['ingredients']);
    $healthy_option = trim($_POST['healthy_option']);
    $recipe = trim($_POST['recipe']);
    $categories = $_POST['categories'] ?? [];
    $groups = $_POST['groups'] ?? [];

    // Image Upload Handling
    $image = "";
    if (!empty($_FILES['image']['name'])) {
        $upload_dir = "uploads/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif');

        if (in_array($file_extension, $allowed_extensions)) {
            $unique_filename = uniqid() . '.' . $file_extension;
            $image = $upload_dir . $unique_filename;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $image)) {
                // File uploaded successfully
            } else {
                $errorMessage = "Failed to upload image.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
        } else {
            $errorMessage = "Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    try {
        if ($action === "add") {
            $stmt = $conn->prepare("INSERT INTO products (product_name, allergens, ingredients, image, healthy_option, recipe) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $product_name, $allergens, $ingredients, $image, $healthy_option, $recipe);

            if ($stmt->execute()) {
                $product_id = $conn->insert_id;

                // Insert categories
                foreach ($categories as $category_id) {
                    $stmt = $conn->prepare("INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)");
                    $stmt->bind_param("ii", $product_id, $category_id);
                    $stmt->execute();
                }

                // Insert groups
                foreach ($groups as $group_id) {
                    $stmt = $conn->prepare("INSERT INTO product_groups (product_id, group_id) VALUES (?, ?)");
                    $stmt->bind_param("ii", $product_id, $group_id);
                    $stmt->execute();
                }

                $_SESSION['success'] = "Product successfully added!";

                header("Location: products_management.php");
                exit();
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
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

// Fetch Categories & Groups
$categories_result = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name");
$groups_result = $conn->query("SELECT group_id, group_name FROM `groups` ORDER BY group_name");

// Count total products
$total_products = $conn->query("SELECT COUNT(*) as total FROM products")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management</title>
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
    </style>
</head>
<style>
    /* Form and Card Styles */
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

    .form-control, .form-select {
        border-radius: 8px;
        border: 1px solid #e0e0e0;
        padding: 8px 12px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.15);
    }

    .form-label {
        font-weight: 500;
        color: #2c3e50;
        margin-bottom: 8px;
        font-size: 0.95rem;
    }

    /* Health Rating Styles */
    .healthy-options {
        display: flex;
        gap: 12px;
        margin: 10px 0;
    }

    .healthy-option {
        width: 45px;
        height: 45px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-weight: 600;
        color: white;
        transition: all 0.2s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        position: relative;
        overflow: hidden;
    }

    .healthy-option::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255,255,255,0.1);
        opacity: 0;
        transition: opacity 0.2s ease;
    }

    .healthy-option:hover::before {
        opacity: 1;
    }

    .healthy-option.green { background: linear-gradient(135deg, var(--success-color), #34c759); }
    .healthy-option.amber { background: linear-gradient(135deg, var(--warning-color), #ffac33); }
    .healthy-option.red { background: linear-gradient(135deg, var(--danger-color), #ff4444); }

    .healthy-option.selected {
        transform: scale(1.1);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }

    /* Grid Styles for Categories and Groups */
    .categories-grid,
    .groups-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 10px;
        margin-top: 10px;
    }
    .selection-item {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 10px 15px;
        cursor: pointer;
        transition: all 0.2s ease;
        text-align: center;
        user-select: none;
    }

    .selection-item:hover {
        background: #e9ecef;
        transform: translateY(-1px);
    }

    .selection-item.selected {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
        box-shadow: 0 2px 4px rgba(76, 175, 80, 0.2);
    }

    /* Add this to your existing CSS */
    #missingFieldsList {
        list-style: none;
        padding: 0;
        margin: 0;
        text-align: left;
    }

    #missingFieldsList li {
        color: #dc3545;
        padding: 4px 0;
    }

    #missingFieldsList li::before {
        content: "•";
        color: #dc3545;
        display: inline-block;
        width: 1em;
        margin-left: -1em;
    }

    .grid-item {
        background: #f8f9fa;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 10px;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.9rem;
        text-align: center;
        position: relative;
        overflow: hidden;
        user-select: none;
    }

    .grid-item:hover {
        background: #f0f0f0;
        transform: translateY(-1px);
    }

    .grid-item.selected {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
        box-shadow: 0 2px 4px rgba(76, 175, 80, 0.2);
    }

    .grid-item input[type="checkbox"] {
        position: absolute;
        opacity: 0;
    }
    .image-upload-container {
        width: 100%;
    }

    .image-preview-area {
        border: 2px dashed #dee2e6;
        border-radius: 8px;
        min-height: 200px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        position: relative;
        overflow: hidden;
        background: #f8f9fa;
        transition: all 0.3s ease;
    }

    .image-preview-area:hover {
        border-color: var(--primary-color);
        background: #f0f0f0;
    }

    .preview-image {
        max-width: 100%;
        max-height: 200px;
        object-fit: contain;
    }

    /* Image Upload Styles */
    .image-upload {
        border: 2px dashed #e0e0e0;
        border-radius: 12px;
        padding: 15px;
        text-align: center;
        position: relative;
        height: 180px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        transition: all 0.3s ease;
        background: #f8f9fa;
    }

    .image-upload.dragover {
        border-color: var(--primary-color);
        background: #f0f0f0;
    }

    .image-upload img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    .upload-placeholder {
        text-align: center;
        color: #6c757d;
    }

    .upload-placeholder i {
        font-size: 2.5rem;
        margin-bottom: 10px;
        color: #adb5bd;
    }
    .dragover {
        border-color: var(--primary-color);
        background: #e8f5e9;
    }

    .selected-file {
        margin-top: 8px;
        font-size: 0.875rem;
    }

    /* Action Button Styles */
    .action-button {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 1000;
    }

    .btn-primary {
        background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
        border: none;
        padding: 10px 24px;
        border-radius: 10px;
        font-weight: 500;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    /* Responsive Styles */
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

        .category-grid, .group-grid {
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        }

        .action-button {
            bottom: 20px;
            right: 20px;
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
            <a class="nav-link active" href="products_management.php">
                <i class="bi bi-box"></i>
                <span>Products</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $activeTab === 'categories' ? 'active' : ''; ?>" href="categories_groups.php?tab=categories">
                <i class="bi bi-tags"></i>
                <span>Categories</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $activeTab === 'groups' ? 'active' : ''; ?>" href="categories_groups.php?tab=groups">
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
            <a class="nav-link" href="settings.php">
                <i class="bi bi-gear"></i>
                <span>Settings</span>
            </a>
        </li>
    </ul>
</nav>

<!-- Top Navbar -->
<nav class="top-navbar">
    <div class="d-flex align-items-center">
        <h4 class="mb-0">Product Management</h4>
        <span class="ms-3 text-muted">Total Products: <?php echo $total_products; ?></span>
    </div>
    <div class="user-info">
        <span class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
        <a href="logout.php" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</nav>

<!-- Action Button -->
<div class="action-button">
    <button type="submit" form="productForm" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i>
        <span>Add Product</span>
    </button>
</div>
<!-- Main Content -->
<div class="main-content">
    <div class="container-fluid">
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($errorMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form id="productForm" action="product.php" method="POST" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="product_id" id="product_id">

            <div class="row">
                <!-- Left Column -->
                <div class="col-md-7">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Basic Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Product Name</label>
                                    <input type="text" name="product_name" class="form-control" required
                                           data-label="Product name">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Allergens</label>
                                    <input type="text" name="allergens" class="form-control" required
                                           data-label="Allergens">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Ingredients</label>
                                <textarea name="ingredients" class="form-control" rows="3" required
                                          data-label="Ingredients"></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Recipe</label>
                                <textarea name="recipe" class="form-control" rows="3" required
                                          data-label="Recipe"></textarea>
                            </div>

                            <!-- Health Rating -->
                            <div class="mb-3">
                                <label class="form-label d-block">Health Rating</label>
                                <div class="healthy-options">
                                    <div class="healthy-option green" onclick="selectHealthy(this, 'Green')"
                                         data-bs-toggle="tooltip" title="Healthy Choice">G</div>
                                    <div class="healthy-option amber" onclick="selectHealthy(this, 'Amber')"
                                         data-bs-toggle="tooltip" title="AMBER">A</div>
                                    <div class="healthy-option red" onclick="selectHealthy(this, 'Red')"
                                         data-bs-toggle="tooltip" title="RED">R</div>
                                </div>
                                <input type="hidden" name="healthy_option" id="healthy_option" required
                                       data-label="Health rating">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col-md-5">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="mb-0">Categories & Groups</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Categories</label>
                                <div class="categories-grid">
                                    <?php while ($category = $categories_result->fetch_assoc()): ?>
                                        <div class="selection-item" onclick="toggleSelection(this, 'categories')">
                                            <input type="checkbox" name="categories[]"
                                                   value="<?= $category['category_id'] ?>"
                                                   class="d-none category-checkbox">
                                            <span><?= htmlspecialchars($category['category_name']) ?></span>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Groups</label>
                                <div class="groups-grid">
                                    <?php while ($group = $groups_result->fetch_assoc()): ?>
                                        <div class="selection-item" onclick="toggleSelection(this, 'groups')">
                                            <input type="checkbox" name="groups[]"
                                                   value="<?= $group['group_id'] ?>"
                                                   class="d-none group-checkbox">
                                            <span><?= htmlspecialchars($group['group_name']) ?></span>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Update the Image Upload section -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Product Image</h5>
                        </div>
                        <div class="card-body">
                            <div class="image-upload-container" id="imageUploadContainer">
                                <input type="file" name="image" id="imageInput" class="d-none" accept="image/jpeg,image/png" required>
                                <div class="image-preview-area" onclick="triggerFileInput()">
                                    <img id="imagePreview" src="" alt="" class="preview-image d-none">
                                    <div id="uploadPlaceholder" class="upload-placeholder">
                                        <i class="bi bi-cloud-arrow-up"></i>
                                        <p class="mb-1">Drop image here or click to upload</p>
                                        <small class="text-muted">Supports: JPG, PNG (Max 2MB)</small>
                                    </div>
                                </div>
                            </div>
                            <div id="fileName" class="selected-file mt-2 text-muted small"></div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="mb-3">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                </div>
                <h5 class="modal-title mb-3">Success!</h5>
                <p class="text-muted mb-0" id="successModalMessage"></p>
            </div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">Continue</button>
            </div>
        </div>
    </div>
</div>

<!-- Validation Modal -->
<div class="modal fade" id="validationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="mb-3">
                    <i class="bi bi-exclamation-circle-fill text-warning" style="font-size: 3rem;"></i>
                </div>
                <h5 class="modal-title mb-3">Required Fields</h5>
                <p class="text-muted mb-0">Please fill in all required fields before submitting.</p>
                <!-- Add this new element -->
                <ul id="missingFieldsList" class="text-start mt-3"></ul>
            </div>
            <div class="modal-footer border-0 justify-content-center">
                <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>

    // Toggle Selection for Categories and Groups
    function toggleSelection(element, type) {
        const checkbox = element.querySelector(`input[type="checkbox"]`);
        checkbox.checked = !checkbox.checked;
        element.classList.toggle('selected', checkbox.checked);
    }

    // Image Upload and Preview Functions
    function triggerFileInput() {
        document.getElementById('imageInput').click();
    }

    function handleImageUpload(event) {
        const file = event.target.files[0];
        if (file) {
            if (file.size > 2 * 1024 * 1024) { // 2MB limit
                alert('File size exceeds 2MB limit');
                event.target.value = '';
                return;
            }

            if (!['image/jpeg', 'image/png'].includes(file.type)) {
                alert('Only JPG and PNG files are allowed');
                event.target.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('imagePreview');
                const placeholder = document.getElementById('uploadPlaceholder');
                const fileName = document.getElementById('fileName');

                preview.src = e.target.result;
                preview.classList.remove('d-none');
                placeholder.classList.add('d-none');
                fileName.textContent = file.name;
            };
            reader.readAsDataURL(file);
        }
    }

    // Drag and Drop Functionality
    function setupDragAndDrop() {
        const dropZone = document.getElementById('imageUploadContainer');
        const fileInput = document.getElementById('imageInput');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => {
                dropZone.querySelector('.image-preview-area').classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => {
                dropZone.querySelector('.image-preview-area').classList.remove('dragover');
            });
        });

        dropZone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files;
            handleImageUpload({ target: fileInput });
        });
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        // Set up image upload event listener
        document.getElementById('imageInput').addEventListener('change', handleImageUpload);

        // Initialize drag and drop
        setupDragAndDrop();
    });

    // Initialize tooltips and popovers
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Bootstrap tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Show success message if exists
        <?php if ($successMessage): ?>
        const successModal = new bootstrap.Modal(document.getElementById('successModal'));
        document.getElementById('successModalMessage').textContent = <?php echo json_encode($successMessage); ?>;
        successModal.show();
        setTimeout(() => successModal.hide(), 2000);
        <?php endif; ?>
    });

    // Health Rating Selection
    function selectHealthy(element, option) {
        document.getElementById("healthy_option").value = option;
        document.querySelectorAll(".healthy-option").forEach(el => {
            el.classList.remove("selected");
            el.classList.remove("pulse");
        });
        element.classList.add("selected");
        element.classList.add("pulse");
    }

    // Image Preview and Upload
    function previewImage(event) {
        const reader = new FileReader();
        const imagePreview = document.getElementById('imagePreview');
        const placeholder = document.getElementById('uploadPlaceholder');

        reader.onload = function() {
            imagePreview.src = reader.result;
            imagePreview.style.display = 'block';
            placeholder.style.display = 'none';
        };

        if (event.target.files[0]) {
            reader.readAsDataURL(event.target.files[0]);
        }
    }

    // Drag and Drop Functionality
    const dropZone = document.getElementById('imageUploadContainer');
    const fileInput = document.getElementById('image');

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, unhighlight, false);
    });

    function highlight(e) {
        dropZone.classList.add('dragover');
    }

    function unhighlight(e) {
        dropZone.classList.remove('dragover');
    }

    dropZone.addEventListener('drop', handleDrop, false);

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        fileInput.files = files;
        previewImage({ target: fileInput });
    }

    // Grid Item Selection
    document.querySelectorAll('.grid-item').forEach(item => {
        item.addEventListener('click', function(e) {
            if (e.target.tagName !== 'INPUT') {
                const checkbox = this.querySelector('input[type="checkbox"]');
                checkbox.checked = !checkbox.checked;
                this.classList.toggle('selected', checkbox.checked);
            } else {
                this.classList.toggle('selected', e.target.checked);
            }
        });
    });

    document.getElementById('productForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        // Clear all previous validation states and messages
        this.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });

        this.querySelectorAll('.invalid-feedback').forEach(el => {
            el.remove();
        });

        let isValid = true;
        const invalidFields = [];

        // Validate required fields
        this.querySelectorAll('[required]').forEach(field => {
            const fieldLabel = field.getAttribute('data-label') || field.name;

            if (!field.value) {
                isValid = false;
                field.classList.add('is-invalid');
                invalidFields.push(fieldLabel);

                // Only add feedback if it doesn't exist
                const existingFeedback = field.parentNode.querySelector('.invalid-feedback');
                if (!existingFeedback) {
                    const feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback';
                    feedback.textContent = `${fieldLabel} is required`;
                    field.parentNode.appendChild(feedback);
                }
            }
        });

        // Validate Categories
        const categoriesSelected = document.querySelectorAll('input[name="categories[]"]:checked').length > 0;
        if (!categoriesSelected) {
            isValid = false;
            invalidFields.push('Categories');
            document.querySelector('.categories-grid').classList.add('is-invalid');
        }

        // Validate Groups
        const groupsSelected = document.querySelectorAll('input[name="groups[]"]:checked').length > 0;
        if (!groupsSelected) {
            isValid = false;
            invalidFields.push('Groups');
            document.querySelector('.groups-grid').classList.add('is-invalid');
        }

        // Validate Health Rating
        if (!document.getElementById('healthy_option').value) {
            isValid = false;
            invalidFields.push('Health Rating');
            document.querySelector('.healthy-options').classList.add('is-invalid');
        }

        if (!isValid) {
            const validationModal = new bootstrap.Modal(document.getElementById('validationModal'));
            const missingFieldsList = document.getElementById('missingFieldsList');

            // Clear previous list
            missingFieldsList.innerHTML = '';

            // Add each missing field to the list
            invalidFields.forEach(field => {
                const li = document.createElement('li');
                li.textContent = field;
                missingFieldsList.appendChild(li);
            });

            validationModal.show();
            return;
        }

        // Show loading state
        const submitBtn = document.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Adding...';

        try {
            const formData = new FormData(this);
            const response = await fetch('product.php', {
                method: 'POST',
                body: formData
            });

            if (response.ok) {
                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                successModal.show();
                setTimeout(() => {
                    //window.location.reload();
                    window.location.href = 'products_management.php';
                }, 2000);
            } else {
                throw new Error('Submission failed');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while submitting the form. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-plus-circle me-2"></i>Add Product';
        }
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