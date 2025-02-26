<?php
// Session configuration for better persistence
//session_cache_limiter('private');
//session_cache_expire(120); // minutes
//ini_set('session.gc_maxlifetime', 7200); // 2 hours

// Start the session
require_once 'session_check.php';
check_session(['Admin', 'Manager']);
include 'db_connection.php';



// Initialize variables
$successMessage = '';
$errorMessage = '';

// Debug information
$debug = false; // Set to true to enable debugging
if ($debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Log session data
    $log_data = date('Y-m-d H:i:s') . " - Accessing products_management.php\n";
    $log_data .= "Session ID: " . session_id() . "\n";
    $log_data .= "Session Variables: " . print_r($_SESSION, true) . "\n";
    $log_data .= "-------------------\n";
    file_put_contents('access_debug.log', $log_data, FILE_APPEND);
}

// Check for authentication first
if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Not logged in at all
    $_SESSION['error'] = "Please log in to continue.";
    header("Location: index.php");
    exit();
}

// Then check for authorization (admin/manager roles)
if (!isset($_SESSION['uType']) || ($_SESSION['uType'] !== 'Admin' && $_SESSION['uType'] !== 'Manager')) {
    // Logged in but not authorized
    $_SESSION['error'] = "You don't have permission to access this page.";
    header("Location: dashboard.php");
    exit();
}

// Optional: Verify session integrity (e.g., IP address hasn't changed)
if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
    // Suspicious activity - session might be hijacked
    session_unset();
    session_destroy();
    $_SESSION['error'] = "Your session has expired. Please log in again.";
    header("Location: index.php");
    exit();
}

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 5; // Items per page
$offset = ($page - 1) * $limit;

// Search functionality
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$search_condition = '';
if (!empty($search)) {
    $search_condition = "WHERE p.product_name LIKE '%$search%' OR p.allergens LIKE '%$search%'";
}

// Filter by category or group
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$group_filter = isset($_GET['group']) ? (int)$_GET['group'] : 0;

if ($category_filter > 0) {
    $search_condition = $search_condition ? $search_condition . " AND p.product_id IN (SELECT product_id FROM product_categories WHERE category_id = $category_filter)" :
        "WHERE p.product_id IN (SELECT product_id FROM product_categories WHERE category_id = $category_filter)";
}

if ($group_filter > 0) {
    $search_condition = $search_condition ? $search_condition . " AND p.product_id IN (SELECT product_id FROM product_groups WHERE group_id = $group_filter)" :
        "WHERE p.product_id IN (SELECT product_id FROM product_groups WHERE group_id = $group_filter)";
}

// Count total products for pagination
$count_query = "SELECT COUNT(*) as total FROM products p $search_condition";
$count_result = $conn->query($count_query);
$total_products = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_products / $limit);

// Fetch products with pagination
$products_query = "
    SELECT p.*, 
           GROUP_CONCAT(DISTINCT c.category_name ORDER BY c.category_name SEPARATOR ', ') as categories,
           GROUP_CONCAT(DISTINCT g.group_name ORDER BY g.group_name SEPARATOR ', ') as groups
    FROM products p
    LEFT JOIN product_categories pc ON p.product_id = pc.product_id
    LEFT JOIN categories c ON pc.category_id = c.category_id
    LEFT JOIN product_groups pg ON p.product_id = pg.product_id
    LEFT JOIN groups g ON pg.group_id = g.group_id
    $search_condition
    GROUP BY p.product_id
    ORDER BY p.product_name ASC
    LIMIT $offset, $limit
";
$products_result = $conn->query($products_query);

// Fetch all categories and groups for filters
$categories_result = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name");
$groups_result = $conn->query("SELECT group_id, group_name FROM groups ORDER BY group_name");

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

        /* Filter Bar */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
            align-items: center;
        }

        .filter-bar .form-control,
        .filter-bar .form-select {
            max-width: 200px;
            border-radius: 8px;
        }

        .filter-bar .btn {
            border-radius: 8px;
            padding: 8px 16px;
            display: flex;
            align-items: center;
            gap: 6px;
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

        .product-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            background-color: #f1f1f1;
        }

        .healthy-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .healthy-badge.green {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }

        .healthy-badge.amber {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning-color);
        }

        .healthy-badge.red {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }

        /* Action Buttons */
        .action-buttons .btn {
            padding: 6px 12px;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        /* Pagination Styles */
        .pagination {
            margin-bottom: 0;
        }

        .pagination .page-item .page-link {
            border: none;
            color: #6c757d;
            padding: 8px 16px;
            font-size: 0.9rem;
            background: transparent;
        }

        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
        }

        .pagination .page-item .page-link:hover {
            background-color: rgba(0,0,0,0.03);
            color: var(--primary-color);
            border-radius: 50%;
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
            max-width: 500px;
            margin: 0 auto 20px;
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

        .product-details dt {
            font-weight: 600;
            color: #495057;
        }

        .product-details dd {
            margin-bottom: 15px;
        }

        /* Badges */
        .badge {
            font-weight: 500;
            padding: 5px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
        }

        /* Action Button */
        .action-button {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }

        .btn-add {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            color: white;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .btn-add:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.15);
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

            .filter-bar .form-control,
            .filter-bar .form-select {
                max-width: 100%;
            }

            .product-img {
                width: 40px;
                height: 40px;
            }

            .action-button {
                bottom: 20px;
                right: 20px;
            }
        }

        /* Health Rating Colors */
        .health-green {
            color: var(--success-color);
        }
        .health-amber {
            color: var(--warning-color);
        }
        .health-red {
            color: var(--danger-color);
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
    <a href="product.php" class="btn btn-add">
        <i class="bi bi-plus-lg"></i>
        <span>Add Product</span>
    </a>
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

        <?php if ($successMessage): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($successMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Filter & Search Bar -->
        <div class="card mb-4">
            <div class="card-body">
                <form action="" method="GET" class="filter-bar">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Search products..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-primary" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>

                    <select class="form-select" name="category" onchange="this.form.submit()">
                        <option value="0">All Categories</option>
                        <?php while ($category = $categories_result->fetch_assoc()): ?>
                            <option value="<?php echo $category['category_id']; ?>" <?php echo $category_filter == $category['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>

                    <select class="form-select" name="group" onchange="this.form.submit()">
                        <option value="0">All Groups</option>
                        <?php while ($group = $groups_result->fetch_assoc()): ?>
                            <option value="<?php echo $group['group_id']; ?>" <?php echo $group_filter == $group['group_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($group['group_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>

                    <?php if ($search || $category_filter > 0 || $group_filter > 0): ?>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Products Table -->
        <div class="card">
            <div class="card-body p-0">
                <?php if ($products_result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                            <tr>
                                <th width="5%"></th>
                                <th width="25%">Product Name</th>
                                <th width="15%">Category</th>
                                <th width="15%">Group</th>
                                <th width="10%">Health</th>
                                <th width="15%">Allergens</th>
                                <th width="15%" class="text-center">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php while ($product = $products_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($product['image']) && file_exists($product['image'])): ?>
                                            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="product-img">
                                        <?php else: ?>
                                            <div class="product-img d-flex align-items-center justify-content-center bg-light">
                                                <i class="bi bi-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['categories'] ?? 'None'); ?></td>
                                    <td><?php echo htmlspecialchars($product['groups'] ?? 'None'); ?></td>
                                    <td>
                                        <?php
                                        switch ($product['healthy_option']) {
                                            case 'Green':
                                                echo '<span class="healthy-badge green">Healthy</span>';
                                                break;
                                            case 'Amber':
                                                echo '<span class="healthy-badge amber">AMBER</span>';
                                                break;
                                            case 'Red':
                                                echo '<span class="healthy-badge red">RED</span>';
                                                break;
                                            default:
                                                echo '<span class="text-muted">Not set</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['allergens']); ?></td>
                                    <td class="text-center">
                                        <div class="action-buttons">
                                            <button type="button" class="btn btn-sm btn-outline-primary me-1"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#viewProductModal"
                                                    data-id="<?php echo $product['product_id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                                    data-allergens="<?php echo htmlspecialchars($product['allergens']); ?>"
                                                    data-ingredients="<?php echo htmlspecialchars($product['ingredients']); ?>"
                                                    data-healthy="<?php echo htmlspecialchars($product['healthy_option']); ?>"
                                                    data-recipe="<?php echo htmlspecialchars($product['recipe']); ?>"
                                                    data-image="<?php echo htmlspecialchars($product['image']); ?>"
                                                    data-categories="<?php echo htmlspecialchars($product['categories'] ?? 'None'); ?>"
                                                    data-groups="<?php echo htmlspecialchars($product['groups'] ?? 'None'); ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <a href="product_edit.php?id=<?php echo $product['product_id']; ?>" class="btn btn-sm btn-outline-secondary me-1">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteProductModal"
                                                    data-id="<?php echo $product['product_id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($product['product_name']); ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="d-flex justify-content-center p-3">
                            <nav aria-label="Page navigation">
                                <ul class="pagination">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&group=<?php echo $group_filter; ?>" aria-label="Previous">
                                                <i class="bi bi-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&group=<?php echo $group_filter; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&group=<?php echo $group_filter; ?>" aria-label="Next">
                                                <i class="bi bi-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Empty State -->
                    <div class="empty-state">
                        <i class="bi bi-box"></i>
                        <h5>No Products Found</h5>
                        <?php if ($search || $category_filter > 0 || $group_filter > 0): ?>
                            <p>No products match your search or filter criteria. Try changing your filters or adding new products.</p>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline-secondary me-2">
                                <i class="bi bi-x-lg"></i> Clear Filters
                            </a>
                        <?php else: ?>
                            <p>You haven't added any products yet. Start by adding your first product.</p>
                        <?php endif; ?>
                        <a href="product.php" class="btn btn-primary">
                            <i class="bi bi-plus-lg"></i> Add Product
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- View Product Modal -->
<div class="modal fade" id="viewProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Product Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4 text-center mb-4 mb-md-0">
                        <div id="product-image-container" class="mb-3">
                            <!-- Product image will be inserted here via JavaScript -->
                        </div>
                        <span class="healthy-badge" id="product-health-badge">Healthy</span>
                    </div>
                    <div class="col-md-8">
                        <dl class="row product-details">
                            <dt class="col-sm-3">Product Name</dt>
                            <dd class="col-sm-9" id="product-name">Product Name</dd>

                            <dt class="col-sm-3">Categories</dt>
                            <dd class="col-sm-9" id="product-categories">Category 1, Category 2</dd>

                            <dt class="col-sm-3">Groups</dt>
                            <dd class="col-sm-9" id="product-groups">Group 1, Group 2</dd>

                            <dt class="col-sm-3">Allergens</dt>
                            <dd class="col-sm-9" id="product-allergens">Allergens information</dd>

                            <dt class="col-sm-3">Ingredients</dt>
                            <dd class="col-sm-9" id="product-ingredients">Ingredients list</dd>

                            <dt class="col-sm-3">Recipe</dt>
                            <dd class="col-sm-9" id="product-recipe">Recipe instructions</dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" id="edit-product-link" class="btn btn-primary">
                    <i class="bi bi-pencil"></i> Edit Product
                </a>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Product Modal -->
<div class="modal fade" id="deleteProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="products_management.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="product_id" id="delete_product_id">
                    <p>Are you sure you want to delete <strong id="delete_product_name"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        This action cannot be undone. All product data, including images and associated records, will be permanently deleted.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Delete Product
                    </button>
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
    // View Product Modal
    const viewProductModal = document.getElementById('viewProductModal');
    if (viewProductModal) {
        viewProductModal.addEventListener('show.bs.modal', function (event) {
            // Button that triggered the modal
            const button = event.relatedTarget;

            // Extract info from data-* attributes
            const productId = button.getAttribute('data-id');
            const productName = button.getAttribute('data-name');
            const productAllergens = button.getAttribute('data-allergens');
            const productIngredients = button.getAttribute('data-ingredients');
            const productHealthy = button.getAttribute('data-healthy');
            const productRecipe = button.getAttribute('data-recipe');
            const productImage = button.getAttribute('data-image');
            const productCategories = button.getAttribute('data-categories');
            const productGroups = button.getAttribute('data-groups');

            // Update the modal's content
            document.getElementById('product-name').textContent = productName;
            document.getElementById('product-allergens').textContent = productAllergens;
            document.getElementById('product-ingredients').textContent = productIngredients;
            document.getElementById('product-recipe').textContent = productRecipe;
            document.getElementById('product-categories').textContent = productCategories;
            document.getElementById('product-groups').textContent = productGroups;

            // Set the edit link
            document.getElementById('edit-product-link').href = 'product_edit.php?id=' + productId;

            // Handle product image
            const imageContainer = document.getElementById('product-image-container');
            if (productImage && productImage !== '') {
                imageContainer.innerHTML = `<img src="${productImage}" alt="${productName}" class="img-fluid rounded" style="max-height: 200px;">`;
            } else {
                imageContainer.innerHTML = `
                        <div class="d-flex align-items-center justify-content-center bg-light rounded" style="height: 200px; width: 100%;">
                            <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                        </div>
                    `;
            }

            // Set health badge
            const healthBadge = document.getElementById('product-health-badge');
            if (productHealthy) {
                switch (productHealthy) {
                    case 'Green':
                        healthBadge.className = 'healthy-badge green';
                        healthBadge.textContent = 'Healthy';
                        break;
                    case 'Amber':
                        healthBadge.className = 'healthy-badge amber';
                        healthBadge.textContent = 'AMBER';
                        break;
                    case 'Red':
                        healthBadge.className = 'healthy-badge red';
                        healthBadge.textContent = 'RED';
                        break;
                    default:
                        healthBadge.className = 'badge bg-secondary';
                        healthBadge.textContent = 'Not Set';
                }
            } else {
                healthBadge.className = 'badge bg-secondary';
                healthBadge.textContent = 'Not Set';
            }
        });
    }

    // Delete Product Modal
    const deleteProductModal = document.getElementById('deleteProductModal');
    if (deleteProductModal) {
        deleteProductModal.addEventListener('show.bs.modal', function (event) {
            // Button that triggered the modal
            const button = event.relatedTarget;

            // Extract info from data-* attributes
            const productId = button.getAttribute('data-id');
            const productName = button.getAttribute('data-name');

            // Update the modal's content
            document.getElementById('delete_product_id').value = productId;
            document.getElementById('delete_product_name').textContent = productName;
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