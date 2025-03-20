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

// Get active tab from URL parameter or default to categories
$activeTab = isset($_GET['tab']) && $_GET['tab'] === 'groups' ? 'groups' : 'categories';

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories & Groups Management</title>
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
            overflow: hidden;
        }

        .card-header {
            background: white;
            border-bottom: 1px solid rgba(0,0,0,0.08);
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-body {
            padding: 20px;
        }

        /* Table Styles */
        .table {
            margin-bottom: 0;
        }

        .table th {
            font-weight: 600;
            color: #2c3e50;
            border-top: none;
            background-color: rgba(0,0,0,0.02);
        }

        .table td {
            vertical-align: middle;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0,0,0,0.02);
        }

        /* Button Styles */
        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background: #43a047;
            border-color: #43a047;
        }

        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
        }

        /* Form Styles */
        .form-control {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
        }

        /* Tab Navigation */
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

        /* Count Badge */
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

        /* Action Buttons */
        .action-buttons .btn {
            padding: 5px 10px;
            font-size: 0.875rem;
        }

        .action-buttons .btn i {
            font-size: 1rem;
        }

        /* Empty State */
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

        /* Badge Styles */
        .badge-count {
            background-color: #e9ecef;
            color: #495057;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Modal Styles */
        .modal-content {
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .modal-header {
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 20px 24px 15px;
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            border-top: 1px solid rgba(0,0,0,0.05);
            padding: 15px 24px 20px;
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
        <h4 class="mb-0">Categories & Groups Management</h4>
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

        <!-- Tab Navigation -->
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $activeTab === 'categories' ? 'active' : ''; ?>"
                   href="?tab=categories" role="tab">
                    Categories <span class="count-badge"><?php echo $categories_count; ?></span>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $activeTab === 'groups' ? 'active' : ''; ?>"
                   href="?tab=groups" role="tab">
                    Groups <span class="count-badge"><?php echo $groups_count; ?></span>
                </a>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Categories Tab -->
            <div class="tab-pane fade <?php echo $activeTab === 'categories' ? 'show active' : ''; ?>">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">All Categories</h5>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                            <i class="bi bi-plus-lg"></i> Add Category
                        </button>
                    </div>
                    <div class="card-body p-0">
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
                                                        <span class="badge badge-count">
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
            <div class="tab-pane fade <?php echo $activeTab === 'groups' ? 'show active' : ''; ?>">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">All Groups</h5>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGroupModal">
                            <i class="bi bi-plus-lg"></i> Add Group
                        </button>
                    </div>
                    <div class="card-body p-0">
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
                                                        <span class="badge badge-count">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Edit Category Modal
    const editCategoryModal = document.getElementById('editCategoryModal');
    if (editCategoryModal) {
        editCategoryModal.addEventListener('show.bs.modal', function (event) {
            // Button that triggered the modal
            const button = event.relatedTarget;

            // Extract info from data-* attributes
            const categoryId = button.getAttribute('data-id');
            const categoryName = button.getAttribute('data-name');

            // Update the modal's content
            const modalCategoryId = this.querySelector('#edit_category_id');
            const modalCategoryName = this.querySelector('#edit_category_name');

            modalCategoryId.value = categoryId;
            modalCategoryName.value = categoryName;
        });
    }

    // Delete Category Modal
    const deleteCategoryModal = document.getElementById('deleteCategoryModal');
    if (deleteCategoryModal) {
        deleteCategoryModal.addEventListener('show.bs.modal', function (event) {
            // Button that triggered the modal
            const button = event.relatedTarget;

            // Extract info from data-* attributes
            const categoryId = button.getAttribute('data-id');
            const categoryName = button.getAttribute('data-name');
            const productCount = parseInt(button.getAttribute('data-count') || '0');

            // Update the modal's content
            const modalCategoryId = this.querySelector('#delete_category_id');
            const deleteMessage = this.querySelector('#delete_category_message');
            const warningDiv = this.querySelector('#delete_category_warning');
            const warningText = this.querySelector('#delete_category_warning_text');
            const deleteButton = this.querySelector('#confirm_delete_category');

            modalCategoryId.value = categoryId;
            deleteMessage.textContent = `Are you sure you want to delete the category "${categoryName}"?`;

            // Show warning if products are associated
            if (productCount > 0) {
                warningDiv.classList.remove('d-none');
                warningText.textContent = `This category is associated with ${productCount} product${productCount !== 1 ? 's' : ''}. You cannot delete it until you remove these associations.`;
                deleteButton.disabled = true;
            } else {
                warningDiv.classList.add('d-none');
                deleteButton.disabled = false;
            }
        });
    }

    // Edit Group Modal
    const editGroupModal = document.getElementById('editGroupModal');
    if (editGroupModal) {
        editGroupModal.addEventListener('show.bs.modal', function (event) {
            // Button that triggered the modal
            const button = event.relatedTarget;

            // Extract info from data-* attributes
            const groupId = button.getAttribute('data-id');
            const groupName = button.getAttribute('data-name');

            // Update the modal's content
            const modalGroupId = this.querySelector('#edit_group_id');
            const modalGroupName = this.querySelector('#edit_group_name');

            modalGroupId.value = groupId;
            modalGroupName.value = groupName;
        });
    }

    // Delete Group Modal
    const deleteGroupModal = document.getElementById('deleteGroupModal');
    if (deleteGroupModal) {
        deleteGroupModal.addEventListener('show.bs.modal', function (event) {
            // Button that triggered the modal
            const button = event.relatedTarget;

            // Extract info from data-* attributes
            const groupId = button.getAttribute('data-id');
            const groupName = button.getAttribute('data-name');
            const productCount = parseInt(button.getAttribute('data-count') || '0');

            // Update the modal's content
            const modalGroupId = this.querySelector('#delete_group_id');
            const deleteMessage = this.querySelector('#delete_group_message');
            const warningDiv = this.querySelector('#delete_group_warning');
            const warningText = this.querySelector('#delete_group_warning_text');
            const deleteButton = this.querySelector('#confirm_delete_group');

            modalGroupId.value = groupId;
            deleteMessage.textContent = `Are you sure you want to delete the group "${groupName}"?`;

            // Show warning if products are associated
            if (productCount > 0) {
                warningDiv.classList.remove('d-none');
                warningText.textContent = `This group is associated with ${productCount} product${productCount !== 1 ? 's' : ''}. You cannot delete it until you remove these associations.`;
                deleteButton.disabled = true;
            } else {
                warningDiv.classList.add('d-none');
                deleteButton.disabled = false;
            }
        });
    }

    // Display success message if exists
    <?php if ($successMessage): ?>
    window.addEventListener('DOMContentLoaded', (event) => {
        const successModal = new bootstrap.Modal(document.getElementById('successModal'));
        document.getElementById('successModalMessage').textContent = <?php echo json_encode($successMessage); ?>;
        successModal.show();
        setTimeout(() => successModal.hide(), 2000);
    });
    <?php endif; ?>

    // Form validation
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
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