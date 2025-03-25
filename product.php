<?php
require_once 'session_check.php';
check_session(['Admin', 'Manager']);
include 'db_connection.php';
include 'includes/functions.php';

// Ensure only Admin & Manager can access
if (!isset($_SESSION['username']) || ($_SESSION['uType'] !== 'Admin' && $_SESSION['uType'] !== 'Manager')) {
    $_SESSION['error'] = "Access denied!";
    header("Location: dashboard.php");
    exit();
}

// Page-specific variables
$page_title = 'Add New Product';
$active_page = 'products';

// Get the logged-in user's information
$userName = $_SESSION['username'];
$userType = $_SESSION['uType'];
$userId = $_SESSION['userID'] ?? 0;

// Get user profile photo
$userPhotoQuery = $conn->prepare("SELECT profile_photo FROM z_user WHERE username = ?");
if (!$userPhotoQuery) {
    error_log("User photo query prepare failed: " . $conn->error);
} else {
    $userPhotoQuery->bind_param("s", $userName);
    $userPhotoQuery->execute();
    $userPhotoResult = $userPhotoQuery->get_result();
    $userPhoto = $userPhotoResult ? $userPhotoResult->fetch_assoc()['profile_photo'] ?? null : null;
}

// Get user details
$userDetails = getUserDetails($conn, $userName);

// Get unread notification count
$unreadCount = getUnreadNotificationCount($conn, $userId);

// Get recent notifications
$notificationsResult = getRecentNotifications($conn, $userId);

// Initialize variables
$successMessage = '';
$errorMessage = '';

// Create upload directory if not exists
$upload_dir = "uploads/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle Product Actions (Add)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
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

        // Get original filename and extension
        $original_filename = pathinfo($_FILES['image']['name'], PATHINFO_FILENAME);
        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif');

        if (in_array($file_extension, $allowed_extensions)) {
            // Format the date as DD-MM-YYYY
            $date_formatted = date('d-m-Y');

            // Create new filename with the required format
            $new_filename = $original_filename . '_' . $date_formatted . '.' . $file_extension;
            $image = $upload_dir . $new_filename;

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

                // Add notification for all admin and manager users
                $adminQuery = "SELECT userID FROM z_user WHERE uType IN ('Admin', 'Manager')";
                $adminResult = $conn->query($adminQuery);
                
                if ($adminResult) {
                    while ($admin = $adminResult->fetch_assoc()) {
                        addNotification(
                            $conn, 
                            $admin['userID'], 
                            'product', 
                            'New Product Added', 
                            "New product '{$product_name}' with {$healthy_option} health rating has been added."
                        );
                    }
                }

                $successMessage = "Product successfully added!";
                $_SESSION['success'] = $successMessage;

                header("Location: products_management.php");
                exit();
            }
        }
    } catch (Exception $e) {
        $errorMessage = "Error: " . $e->getMessage();
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

// Add page-specific CSS
$additional_css = '
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
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    position: relative;
    overflow: hidden;
}

.healthy-option::before {
    content: "";
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

.dragover {
    border-color: var(--primary-color);
    background: #e8f5e9;
}

.preview-image {
    max-width: 100%;
    max-height: 200px;
    object-fit: contain;
}

.selected-file {
    margin-top: 8px;
    font-size: 0.875rem;
}

/* Action Button */
.action-button {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 1000;
}

.action-button .btn {
    padding: 12px 24px;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.action-button .btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 15px rgba(0,0,0,0.15);
}
';

// Include header
include 'includes/header.php';
?>

<!-- Action Button -->
<div class="action-button">
    <button type="submit" form="productForm" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i>
        <span>Add Product</span>
    </button>
</div>

<!-- Main Content -->
<div class="container-fluid">
    <form id="productForm" action="product.php" method="POST" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="product_id" id="product_id">

        <div class="row">
            <!-- Left Column -->
            <div class="col-md-7">
                <div class="app-card">
                    <div class="app-card-header">
                        <h5 class="app-card-title">Basic Information</h5>
                    </div>
                    <div class="app-card-body">
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
                <div class="app-card mb-3">
                    <div class="app-card-header">
                        <h5 class="app-card-title">Categories & Groups</h5>
                    </div>
                    <div class="app-card-body">
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

                <!-- Product Image -->
                <div class="app-card">
                    <div class="app-card-header">
                        <h5 class="app-card-title">Product Image</h5>
                    </div>
                    <div class="app-card-body">
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

<?php
// Page-specific scripts
$page_scripts = '
// Toggle Selection for Categories and Groups
function toggleSelection(element, type) {
    const checkbox = element.querySelector(`input[type="checkbox"]`);
    checkbox.checked = !checkbox.checked;
    element.classList.toggle("selected", checkbox.checked);
}

// Image Upload and Preview Functions
function triggerFileInput() {
    document.getElementById("imageInput").click();
}

function handleImageUpload(event) {
    const file = event.target.files[0];
    if (file) {
        if (file.size > 2 * 1024 * 1024) { // 2MB limit
            alert("File size exceeds 2MB limit");
            event.target.value = "";
            return;
        }

        if (!["image/jpeg", "image/png"].includes(file.type)) {
            alert("Only JPG and PNG files are allowed");
            event.target.value = "";
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById("imagePreview");
            const placeholder = document.getElementById("uploadPlaceholder");
            const fileName = document.getElementById("fileName");

            preview.src = e.target.result;
            preview.classList.remove("d-none");
            placeholder.classList.add("d-none");
            fileName.textContent = file.name;
        };
        reader.readAsDataURL(file);
    }
}

// Drag and Drop Functionality
function setupDragAndDrop() {
    const dropZone = document.getElementById("imageUploadContainer");
    const fileInput = document.getElementById("imageInput");

    ["dragenter", "dragover", "dragleave", "drop"].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ["dragenter", "dragover"].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.querySelector(".image-preview-area").classList.add("dragover");
        });
    });

    ["dragleave", "drop"].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.querySelector(".image-preview-area").classList.remove("dragover");
        });
    });

    dropZone.addEventListener("drop", (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        fileInput.files = files;
        handleImageUpload({ target: fileInput });
    });
}

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

// Initialize
document.addEventListener("DOMContentLoaded", function() {
    // Set up image upload event listener
    document.getElementById("imageInput").addEventListener("change", handleImageUpload);

    // Initialize drag and drop
    setupDragAndDrop();

    // Initialize Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll("[data-bs-toggle=\'tooltip\']"));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Form validation
    document.getElementById("productForm").addEventListener("submit", function(e) {
        e.preventDefault();

        // Clear all previous validation states and messages
        this.querySelectorAll(".is-invalid").forEach(el => {
            el.classList.remove("is-invalid");
        });

        this.querySelectorAll(".invalid-feedback").forEach(el => {
            el.remove();
        });

        let isValid = true;
        const invalidFields = [];

        // Validate required fields
        this.querySelectorAll("[required]").forEach(field => {
            const fieldLabel = field.getAttribute("data-label") || field.name;

            if (!field.value) {
                isValid = false;
                field.classList.add("is-invalid");
                invalidFields.push(fieldLabel);

                // Only add feedback if it doesn\'t exist
                const existingFeedback = field.parentNode.querySelector(".invalid-feedback");
                if (!existingFeedback) {
                    const feedback = document.createElement("div");
                    feedback.className = "invalid-feedback";
                    feedback.textContent = `${fieldLabel} is required`;
                    field.parentNode.appendChild(feedback);
                }
            }
        });

        // Validate Categories
const categoriesSelected = document.querySelectorAll("input[name=\"categories[]\"]:checked").length > 0;
if (!categoriesSelected) {
    isValid = false;
    invalidFields.push("Categories");
    document.querySelector(".categories-grid").classList.add("is-invalid");
}

// Validate Groups
const groupsSelected = document.querySelectorAll("input[name=\"groups[]\"]:checked").length > 0;
if (!groupsSelected) {
    isValid = false;
    invalidFields.push("Groups");
    document.querySelector(".groups-grid").classList.add("is-invalid");
}

        // Validate Health Rating
        if (!document.getElementById("healthy_option").value) {
            isValid = false;
            invalidFields.push("Health Rating");
            document.querySelector(".healthy-options").classList.add("is-invalid");
        }

        if (!isValid) {
            const validationModal = new bootstrap.Modal(document.getElementById("validationModal"));
            const missingFieldsList = document.getElementById("missingFieldsList");

            // Clear previous list
            missingFieldsList.innerHTML = "";

            // Add each missing field to the list
            invalidFields.forEach(field => {
                const li = document.createElement("li");
                li.textContent = field;
                missingFieldsList.appendChild(li);
            });

            validationModal.show();
            return;
        }

        // Show loading state
        const submitBtn = document.querySelector("button[type=\"submit\"]");
        submitBtn.disabled = true;
        submitBtn.innerHTML = "<span class=\"spinner-border spinner-border-sm me-2\"></span>Adding...";
        
        // Submit the form
        this.submit();
    });
});
';

// Include footer
include 'includes/footer.php';
?>