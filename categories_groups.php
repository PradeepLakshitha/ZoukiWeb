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

// Get active tab from URL parameter or default to categories
$tab = isset($_GET['tab']) && $_GET['tab'] === 'groups' ? 'groups' : 'categories';

// Set page-specific variables
$page_title = $tab === 'categories' ? 'Categories Management' : 'Groups Management';
$active_page = $tab; // This will highlight the correct nav item

// Get the logged-in user's information
$userName = $_SESSION['username'];
$userType = $_SESSION['uType'];
$userId = $_SESSION['userID'] ?? 0;

// Get user details
$userDetails = getUserDetails($conn, $userName);

// Get unread notification count
$unreadCount = getUnreadNotificationCount($conn, $userId);

// Get recent notifications
$notificationsResult = getRecentNotifications($conn, $userId);

// Initialize variables
$successMessage = '';
$errorMessage = '';

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

// Handle Category Actions (Add, Update, Delete)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Categories actions
    if (strpos($action, 'category_') === 0) {
        $category_id = $_POST['category_id'] ?? null;
        $category_name = trim($_POST['category_name'] ?? '');

        try {
            if ($action === "category_add" && !empty($category_name)) {
                $stmt = $conn->prepare("INSERT INTO categories (category_name) VALUES (?)");
                $stmt->bind_param("s", $category_name);

                if ($stmt->execute()) {
                    $_SESSION['success'] = "Category added successfully!";
                } else {
                    throw new Exception("Failed to add category.");
                }
            } elseif ($action === "category_update" && !empty($category_id) && !empty($category_name)) {
                $stmt = $conn->prepare("UPDATE categories SET category_name = ? WHERE category_id = ?");
                $stmt->bind_param("si", $category_name, $category_id);

                if ($stmt->execute()) {
                    $_SESSION['success'] = "Category updated successfully!";
                } else {
                    throw new Exception("Failed to update category.");
                }
            } elseif ($action === "category_delete" && !empty($category_id)) {
                // Check if category is associated with any products
                $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM product_categories WHERE category_id = ?");
                $check_stmt->bind_param("i", $category_id);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                $count = $result->fetch_assoc()['count'];

                if ($count > 0) {
                    throw new Exception("Cannot delete category: it is associated with " . $count . " product(s).");
                }

                $stmt = $conn->prepare("DELETE FROM categories WHERE category_id = ?");
                $stmt->bind_param("i", $category_id);

                if ($stmt->execute()) {
                    $_SESSION['success'] = "Category deleted successfully!";
                } else {
                    throw new Exception("Failed to delete category.");
                }
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }

        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=categories");
        exit();
    }

    // Groups actions
    if (strpos($action, 'group_') === 0) {
        $group_id = $_POST['group_id'] ?? null;
        $group_name = trim($_POST['group_name'] ?? '');

        try {
            if ($action === "group_add" && !empty($group_name)) {
                $stmt = $conn->prepare("INSERT INTO `groups` (group_name) VALUES (?)");
                $stmt->bind_param("s", $group_name);

                if ($stmt->execute()) {
                    $_SESSION['success'] = "Group added successfully!";
                } else {
                    throw new Exception("Failed to add group.");
                }
            } elseif ($action === "group_update" && !empty($group_id) && !empty($group_name)) {
                $stmt = $conn->prepare("UPDATE `groups` SET group_name = ? WHERE group_id = ?");
                $stmt->bind_param("si", $group_name, $group_id);

                if ($stmt->execute()) {
                    $_SESSION['success'] = "Group updated successfully!";
                } else {
                    throw new Exception("Failed to update group.");
                }
            } elseif ($action === "group_delete" && !empty($group_id)) {
                // Check if group is associated with any products
                $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM product_groups WHERE group_id = ?");
                $check_stmt->bind_param("i", $group_id);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                $count = $result->fetch_assoc()['count'];

                if ($count > 0) {
                    throw new Exception("Cannot delete group: it is associated with " . $count . " product(s).");
                }

                $stmt = $conn->prepare("DELETE FROM `groups` WHERE group_id = ?");
                $stmt->bind_param("i", $group_id);

                if ($stmt->execute()) {
                    $_SESSION['success'] = "Group deleted successfully!";
                } else {
                    throw new Exception("Failed to delete group.");
                }
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }

        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=groups");
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

// Fetch all categories
$categories_query = "SELECT c.category_id, c.category_name, COUNT(pc.product_id) as product_count 
                     FROM categories c 
                     LEFT JOIN product_categories pc ON c.category_id = pc.category_id 
                     GROUP BY c.category_id 
                     ORDER BY c.category_name ASC";
$categories_result = $conn->query($categories_query);
$categories_count = $categories_result->num_rows;

// Fetch all groups
$groups_query = "SELECT g.group_id, g.group_name, COUNT(pg.product_id) as product_count 
                 FROM `groups` g 
                 LEFT JOIN product_groups pg ON g.group_id = pg.group_id 
                 GROUP BY g.group_id 
                 ORDER BY g.group_name ASC";
$groups_result = $conn->query($groups_query);
$groups_count = $groups_result->num_rows;

// Add page-specific CSS
$additional_css = '
.count-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 12px;
    background-color: rgba(76, 175, 80, 0.1);
    color: var(--primary-color);
    font-size: 0.75rem;
    font-weight: 600;
    margin-left: 8px;
}

.badge-count {
    background-color: #e9ecef;
    color: #495057;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
}

.action-buttons .btn {
    padding: 5px 10px;
    font-size: 0.875rem;
}

.action-buttons .btn i {
    font-size: 1rem;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
}

.empty-state i {
    font-size: 3.5rem;
    color: #e0e0e0;
    margin-bottom: 15px;
}

.empty-state h5 {
    color: #6c757d;
    margin-bottom: 10px;
}

.empty-state p {
    color: #adb5bd;
    max-width: 300px;
    margin: 0 auto 20px;
}

.nav-tabs {
    border-bottom: 1px solid #dee2e6;
    margin-bottom: 20px;
}

.nav-tabs .nav-item .nav-link {
    border: none;
    color: #6c757d;
    padding: 12px 20px;
    font-weight: 500;
    border-radius: 0;
    margin-right: 4px;
    transition: all 0.3s ease;
}

.nav-tabs .nav-item .nav-link:hover {
    border-color: transparent;
    color: var(--primary-color);
}

.nav-tabs .nav-item .nav-link.active {
    color: var(--primary-color);
    background-color: transparent;
    border-bottom: 3px solid var(--primary-color);
}
';

// Include header
include 'includes/header.php';
?>

<!-- Main Content -->
<div class="container-fluid">
    <!-- Tab Navigation -->
    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link <?php echo $tab === 'categories' ? 'active' : ''; ?>"
               href="?tab=categories" role="tab">
                Categories <span class="count-badge"><?php echo $categories_count; ?></span>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?php echo $tab === 'groups' ? 'active' : ''; ?>"
               href="?tab=groups" role="tab">
                Groups <span class="count-badge"><?php echo $groups_count; ?></span>
            </a>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content">
        <!-- Categories Tab -->
        <div class="tab-pane fade <?php echo $tab === 'categories' ? 'show active' : ''; ?>">
            <div class="app-card">
                <div class="app-card-header">
                    <h5 class="app-card-title">All Categories</h5>
                    <div class="app-card-toolbar">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                            <i class="bi bi-plus-lg"></i> Add Category
                        </button>
                    </div>
                </div>
                <div class="app-card-body p-0">
                    <?php if ($categories_count > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                <tr>
                                    <th width="5%">#</th>
                                    <th width="65%">Category Name</th>
                                    <th width="15%">Products</th>
                                    <th width="15%" class="text-center">Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                $counter = 1;
                                while ($category = $categories_result->fetch_assoc()):
                                    ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><?php echo htmlspecialchars($category['category_name']); ?></td>
                                        <td>
                                                    <span class="badge-count">
                                                        <?php echo $category['product_count']; ?> product<?php echo $category['product_count'] != 1 ? 's' : ''; ?>
                                                    </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="action-buttons">
                                                <button type="button" class="btn btn-sm btn-outline-primary me-1"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#editCategoryModal"
                                                        data-id="<?php echo $category['category_id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($category['category_name']); ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#deleteCategoryModal"
                                                        data-id="<?php echo $category['category_id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($category['category_name']); ?>"
                                                        data-count="<?php echo $category['product_count']; ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <!-- Empty State for Categories -->
                        <div class="empty-state">
                            <i class="bi bi-tags"></i>
                            <h5>No Categories Yet</h5>
                            <p>You haven't added any categories yet. Add your first category to get started.</p>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                <i class="bi bi-plus-lg"></i> Add First Category
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Groups Tab -->
        <div class="tab-pane fade <?php echo $tab === 'groups' ? 'show active' : ''; ?>">
            <div class="app-card">
                <div class="app-card-header">
                    <h5 class="app-card-title">All Groups</h5>
                    <div class="app-card-toolbar">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGroupModal">
                            <i class="bi bi-plus-lg"></i> Add Group
                        </button>
                    </div>
                </div>
                <div class="app-card-body p-0">
                    <?php if ($groups_count > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                <tr>
                                    <th width="5%">#</th>
                                    <th width="65%">Group Name</th>
                                    <th width="15%">Products</th>
                                    <th width="15%" class="text-center">Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                $counter = 1;
                                while ($group = $groups_result->fetch_assoc()):
                                    ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td><?php echo htmlspecialchars($group['group_name']); ?></td>
                                        <td>
                                                    <span class="badge-count">
                                                        <?php echo $group['product_count']; ?> product<?php echo $group['product_count'] != 1 ? 's' : ''; ?>
                                                    </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="action-buttons">
                                                <button type="button" class="btn btn-sm btn-outline-primary me-1"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#editGroupModal"
                                                        data-id="<?php echo $group['group_id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($group['group_name']); ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#deleteGroupModal"
                                                        data-id="<?php echo $group['group_id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($group['group_name']); ?>"
                                                        data-count="<?php echo $group['product_count']; ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <!-- Empty State for Groups -->
                        <div class="empty-state">
                            <i class="bi bi-collection"></i>
                            <h5>No Groups Yet</h5>
                            <p>You haven't added any groups yet. Add your first group to get started.</p>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGroupModal">
                                <i class="bi bi-plus-lg"></i> Add First Group
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="categories_groups.php?tab=categories" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="category_add">
                    <div class="mb-3">
                        <label for="category_name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="category_name" name="category_name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="categories_groups.php?tab=categories" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="category_update">
                    <input type="hidden" name="category_id" id="edit_category_id">
                    <div class="mb-3">
                        <label for="edit_category_name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="edit_category_name" name="category_name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Category Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="categories_groups.php?tab=categories" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="category_delete">
                    <input type="hidden" name="category_id" id="delete_category_id">
                    <p id="delete_category_message">Are you sure you want to delete this category?</p>
                    <div id="delete_category_warning" class="alert alert-warning d-none">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <span id="delete_category_warning_text"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="confirm_delete_category">Delete Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Group Modal -->
<div class="modal fade" id="addGroupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Group</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="categories_groups.php?tab=groups" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="group_add">
                    <div class="mb-3">
                        <label for="group_name" class="form-label">Group Name</label>
                        <input type="text" class="form-control" id="group_name" name="group_name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Group</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Group Modal -->
<div class="modal fade" id="editGroupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Group</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="categories_groups.php?tab=groups" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="group_update">
                    <input type="hidden" name="group_id" id="edit_group_id">
                    <div class="mb-3">
                        <label for="edit_group_name" class="form-label">Group Name</label>
                        <input type="text" class="form-control" id="edit_group_name" name="group_name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Group</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Group Modal -->
<div class="modal fade" id="deleteGroupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Group</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="categories_groups.php?tab=groups" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="group_delete">
                    <input type="hidden" name="group_id" id="delete_group_id">
                    <p id="delete_group_message">Are you sure you want to delete this group?</p>
                    <div id="delete_group_warning" class="alert alert-warning d-none">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <span id="delete_group_warning_text"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="confirm_delete_group">Delete Group</button>
                </div>
            </form>
        </div>
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

<?php 
// Define page-specific scripts
$page_scripts = '
// Edit Category Modal
const editCategoryModal = document.getElementById("editCategoryModal");
if (editCategoryModal) {
    editCategoryModal.addEventListener("show.bs.modal", function (event) {
        // Button that triggered the modal
        const button = event.relatedTarget;

        // Extract info from data-* attributes
        const categoryId = button.getAttribute("data-id");
        const categoryName = button.getAttribute("data-name");

        // Update the modal\'s content
        const modalCategoryId = this.querySelector("#edit_category_id");
        const modalCategoryName = this.querySelector("#edit_category_name");

        modalCategoryId.value = categoryId;
        modalCategoryName.value = categoryName;
    });
}

// Delete Category Modal
const deleteCategoryModal = document.getElementById("deleteCategoryModal");
if (deleteCategoryModal) {
    deleteCategoryModal.addEventListener("show.bs.modal", function (event) {
        // Button that triggered the modal
        const button = event.relatedTarget;

        // Extract info from data-* attributes
        const categoryId = button.getAttribute("data-id");
        const categoryName = button.getAttribute("data-name");
        const productCount = parseInt(button.getAttribute("data-count") || "0");

        // Update the modal\'s content
        const modalCategoryId = this.querySelector("#delete_category_id");
        const deleteMessage = this.querySelector("#delete_category_message");
        const warningDiv = this.querySelector("#delete_category_warning");
        const warningText = this.querySelector("#delete_category_warning_text");
        const deleteButton = this.querySelector("#confirm_delete_category");

        modalCategoryId.value = categoryId;
        deleteMessage.textContent = `Are you sure you want to delete the category "${categoryName}"?`;

        // Show warning if products are associated
        if (productCount > 0) {
            warningDiv.classList.remove("d-none");
            warningText.textContent = `This category is associated with ${productCount} product${productCount !== 1 ? "s" : ""}. You cannot delete it until you remove these associations.`;
            deleteButton.disabled = true;
        } else {
            warningDiv.classList.add("d-none");
            deleteButton.disabled = false;
        }
    });
}

// Edit Group Modal
const editGroupModal = document.getElementById("editGroupModal");
if (editGroupModal) {
    editGroupModal.addEventListener("show.bs.modal", function (event) {
        // Button that triggered the modal
        const button = event.relatedTarget;

        // Extract info from data-* attributes
        const groupId = button.getAttribute("data-id");
        const groupName = button.getAttribute("data-name");

        // Update the modal\'s content
        const modalGroupId = this.querySelector("#edit_group_id");
        const modalGroupName = this.querySelector("#edit_group_name");

        modalGroupId.value = groupId;
        modalGroupName.value = groupName;
    });
}

// Delete Group Modal
const deleteGroupModal = document.getElementById("deleteGroupModal");
if (deleteGroupModal) {
    deleteGroupModal.addEventListener("show.bs.modal", function (event) {
        // Button that triggered the modal
        const button = event.relatedTarget;

        // Extract info from data-* attributes
        const groupId = button.getAttribute("data-id");
        const groupName = button.getAttribute("data-name");
        const productCount = parseInt(button.getAttribute("data-count") || "0");

        // Update the modal\'s content
        const modalGroupId = this.querySelector("#delete_group_id");
        const deleteMessage = this.querySelector("#delete_group_message");
        const warningDiv = this.querySelector("#delete_group_warning");
        const warningText = this.querySelector("#delete_group_warning_text");
        const deleteButton = this.querySelector("#confirm_delete_group");

        modalGroupId.value = groupId;
        deleteMessage.textContent = `Are you sure you want to delete the group "${groupName}"?`;

        // Show warning if products are associated
        if (productCount > 0) {
            warningDiv.classList.remove("d-none");
            warningText.textContent = `This group is associated with ${productCount} product${productCount !== 1 ? "s" : ""}. You cannot delete it until you remove these associations.`;
            deleteButton.disabled = true;
        } else {
            warningDiv.classList.add("d-none");
            deleteButton.disabled = false;
        }
    });
}

// Display success message if exists
' . (isset($successMessage) && !empty($successMessage) ? '
window.addEventListener("DOMContentLoaded", (event) => {
    const successModal = new bootstrap.Modal(document.getElementById("successModal"));
    document.getElementById("successModalMessage").textContent = ' . json_encode($successMessage) . ';
    successModal.show();
    setTimeout(() => successModal.hide(), 2000);
});
' : '') . '
';

// Include footer (this loads all JavaScript)
include 'includes/footer.php';
?>