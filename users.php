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

// Set active tab for navigation
$activeTab = 'users';

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Items per page
$offset = ($page - 1) * $limit;

// Search and filter functionality
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? $conn->real_escape_string($_GET['type']) : '';
$status_filter = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';

// Build search conditions
$search_condition = '';
$conditions = [];

if (!empty($search)) {
    $conditions[] = "(username LIKE '%$search%' OR first_name LIKE '%$search%' OR last_name LIKE '%$search%' OR email LIKE '%$search%')";
}

if (!empty($type_filter)) {
    $conditions[] = "uType = '$type_filter'";
}

if (!empty($status_filter)) {
    $conditions[] = "status = '$status_filter'";
}

if (!empty($conditions)) {
    $search_condition = "WHERE " . implode(' AND ', $conditions);
}

// Handle User Status Change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'change_status') {
    $user_id = (int) $_POST['user_id'];
    $new_status = $_POST['new_status'];

    // Check if trying to deactivate own account
    $user_check_query = "SELECT username FROM z_user WHERE userID = ?";
    $stmt = $conn->prepare($user_check_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user['username'] === $_SESSION['username']) {
        $_SESSION['error'] = "You cannot change the status of your own account!";
    } else {
        $stmt = $conn->prepare("UPDATE z_user SET status = ? WHERE userID = ?");
        $stmt->bind_param("si", $new_status, $user_id);

        if ($stmt->execute()) {
            $_SESSION['success'] = "User status updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update user status: " . $conn->error;
        }
    }

    header("Location: users.php");
    exit();
}

// Handle User Delete
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $user_id = (int) $_POST['user_id'];

    // Check if trying to delete own account
    $user_check_query = "SELECT username FROM z_user WHERE userID = ?";
    $stmt = $conn->prepare($user_check_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user['username'] === $_SESSION['username']) {
        $_SESSION['error'] = "You cannot delete your own account!";
    } else {
        $stmt = $conn->prepare("DELETE FROM z_user WHERE userID = ?");
        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {
            $_SESSION['success'] = "User deleted successfully!";
        } else {
            $_SESSION['error'] = "Failed to delete user: " . $conn->error;
        }
    }

    header("Location: users.php");
    exit();
}

// Count total users for pagination
$count_query = "SELECT COUNT(*) as total FROM z_user $search_condition";
$count_result = $conn->query($count_query);
$total_users = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_users / $limit);

// Fetch users with pagination
$users_query = "SELECT userID, username, first_name, last_name, email, contact_number, uType, status 
                FROM z_user 
                $search_condition 
                ORDER BY username ASC 
                LIMIT $offset, $limit";
$users_result = $conn->query($users_query);

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
    <title>User Management - ZOUKI</title>
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

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .status-badge.active {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }

        .status-badge.inactive {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }

        /* Type Badge */
        .type-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .type-badge.admin {
            background-color: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }

        .type-badge.manager {
            background-color: rgba(0, 123, 255, 0.1);
            color: #007bff;
        }

        .type-badge.user {
            background-color: rgba(23, 162, 184, 0.1);
            color: #17a2b8;
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

        .user-details dt {
            font-weight: 600;
            color: #495057;
        }

        .user-details dd {
            margin-bottom: 15px;
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
            <a class="nav-link active" href="users.php">
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
        <h4 class="mb-0">User Management</h4>
        <span class="ms-3 text-muted">Total Users: <?php echo $total_users; ?></span>
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
    <a href="add_user.php" class="btn btn-add">
        <i class="bi bi-person-plus"></i>
        <span>Add User</span>
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
                        <input type="text" class="form-control" placeholder="Search users..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-primary" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>

                    <select class="form-select" name="type" onchange="this.form.submit()">
                        <option value="">All Types</option>
                        <option value="Admin" <?php echo $type_filter === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="Manager" <?php echo $type_filter === 'Manager' ? 'selected' : ''; ?>>Manager</option>
                        <option value="User" <?php echo $type_filter === 'User' ? 'selected' : ''; ?>>User</option>
                    </select>

                    <select class="form-select" name="status" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>

                    <?php if ($search || $type_filter || $status_filter): ?>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div class="card-body p-0">
                <?php if ($users_result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                            <tr>
                                <th width="5%">ID</th>
                                <th width="15%">Username</th>
                                <th width="20%">Name</th>
                                <th width="20%">Email</th>
                                <th width="10%">Type</th>
                                <th width="10%">Status</th>
                                <th width="20%" class="text-center">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php while ($user = $users_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $user['userID']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <?php
                                        switch ($user['uType']) {
                                            case 'Admin':
                                                echo '<span class="type-badge admin">Admin</span>';
                                                break;
                                            case 'Manager':
                                                echo '<span class="type-badge manager">Manager</span>';
                                                break;
                                            case 'User':
                                                echo '<span class="type-badge user">User</span>';
                                                break;
                                            default:
                                                echo '<span class="type-badge">Unknown</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo strtolower($user['status']); ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="action-buttons">
                                            <button type="button" class="btn btn-sm btn-outline-primary me-1"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#viewUserModal"
                                                    data-id="<?php echo $user['userID']; ?>"
                                                    data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                    data-firstname="<?php echo htmlspecialchars($user['first_name']); ?>"
                                                    data-lastname="<?php echo htmlspecialchars($user['last_name']); ?>"
                                                    data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                    data-phone="<?php echo htmlspecialchars($user['contact_number']); ?>"
                                                    data-type="<?php echo htmlspecialchars($user['uType']); ?>"
                                                    data-status="<?php echo htmlspecialchars($user['status']); ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <a href="edit_user.php?id=<?php echo $user['userID']; ?>" class="btn btn-sm btn-outline-secondary me-1">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php if ($user['username'] !== $_SESSION['username']): ?>
                                                <button type="button" class="btn btn-sm <?php echo $user['status'] === 'active' ? 'btn-outline-warning' : 'btn-outline-success'; ?> me-1"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#statusModal"
                                                        data-id="<?php echo $user['userID']; ?>"
                                                        data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                        data-current-status="<?php echo $user['status']; ?>"
                                                        data-new-status="<?php echo $user['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                                    <i class="bi <?php echo $user['status'] === 'active' ? 'bi-slash-circle' : 'bi-check-circle'; ?>"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#deleteUserModal"
                                                        data-id="<?php echo $user['userID']; ?>"
                                                        data-username="<?php echo htmlspecialchars($user['username']); ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary me-1" disabled>
                                                    <i class="bi <?php echo $user['status'] === 'active' ? 'bi-slash-circle' : 'bi-check-circle'; ?>"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
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
                                            <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo $type_filter; ?>&status=<?php echo $status_filter; ?>" aria-label="Previous">
                                                <i class="bi bi-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo $type_filter; ?>&status=<?php echo $status_filter; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo $type_filter; ?>&status=<?php echo $status_filter; ?>" aria-label="Next">
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
                        <i class="bi bi-people"></i>
                        <h5>No Users Found</h5>
                        <?php if ($search || $type_filter || $status_filter): ?>
                            <p>No users match your search or filter criteria. Try changing your filters or adding new users.</p>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline-secondary me-2">
                                <i class="bi bi-x-lg"></i> Clear Filters
                            </a>
                        <?php else: ?>
                            <p>You haven't added any users yet. Start by adding your first user.</p>
                        <?php endif; ?>
                        <a href="add_user.php" class="btn btn-primary">
                            <i class="bi bi-person-plus"></i> Add User
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- View User Modal -->
<div class="modal fade" id="viewUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="row user-details">
                    <dt class="col-sm-4">Username</dt>
                    <dd class="col-sm-8" id="view-username"></dd>

                    <dt class="col-sm-4">Name</dt>
                    <dd class="col-sm-8" id="view-name"></dd>

                    <dt class="col-sm-4">Email</dt>
                    <dd class="col-sm-8" id="view-email"></dd>

                    <dt class="col-sm-4">Contact Number</dt>
                    <dd class="col-sm-8" id="view-phone"></dd>

                    <dt class="col-sm-4">User Type</dt>
                    <dd class="col-sm-8" id="view-type"></dd>

                    <dt class="col-sm-4">Status</dt>
                    <dd class="col-sm-8" id="view-status"></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <a href="#" id="edit-user-link" class="btn btn-primary">
                    <i class="bi bi-pencil"></i> Edit User
                </a>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Change Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change User Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="users.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="change_status">
                    <input type="hidden" name="user_id" id="status-user-id">
                    <input type="hidden" name="new_status" id="status-new-status">
                    <p>Are you sure you want to <span id="status-action-text"></span> the user <strong id="status-username"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <span id="status-warning-text"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" id="status-submit-btn">
                        <i class="bi" id="status-icon"></i> <span id="status-btn-text"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="users.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" id="delete-user-id">
                    <p>Are you sure you want to delete the user <strong id="delete-username"></strong>?</p>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        This action cannot be undone. The user will be permanently deleted from the system.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Delete User
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
    // View User Modal
    const viewUserModal = document.getElementById('viewUserModal');
    if (viewUserModal) {
        viewUserModal.addEventListener('show.bs.modal', function (event) {
            // Button that triggered the modal
            const button = event.relatedTarget;

            // Extract info from data-* attributes
            const userId = button.getAttribute('data-id');
            const username = button.getAttribute('data-username');
            const firstName = button.getAttribute('data-firstname');
            const lastName = button.getAttribute('data-lastname');
            const email = button.getAttribute('data-email');
            const phone = button.getAttribute('data-phone');
            const userType = button.getAttribute('data-type');
            const status = button.getAttribute('data-status');

            // Update the modal's content
            document.getElementById('view-username').textContent = username;
            document.getElementById('view-name').textContent = firstName + ' ' + lastName;
            document.getElementById('view-email').textContent = email;
            document.getElementById('view-phone').textContent = phone;
            document.getElementById('view-type').textContent = userType;

            // Set status with appropriate styling
            const statusEl = document.getElementById('view-status');
            statusEl.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            statusEl.className = status === 'active' ? 'text-success' : 'text-danger';

            // Set the edit link
            document.getElementById('edit-user-link').href = 'edit_user.php?id=' + userId;
        });
    }

    // Status Change Modal
    const statusModal = document.getElementById('statusModal');
    if (statusModal) {
        statusModal.addEventListener('show.bs.modal', function (event) {
            // Button that triggered the modal
            const button = event.relatedTarget;

            // Extract info from data-* attributes
            const userId = button.getAttribute('data-id');
            const username = button.getAttribute('data-username');
            const currentStatus = button.getAttribute('data-current-status');
            const newStatus = button.getAttribute('data-new-status');

            // Update the modal's content
            document.getElementById('status-user-id').value = userId;
            document.getElementById('status-username').textContent = username;
            document.getElementById('status-new-status').value = newStatus;

            // Set appropriate text based on the action
            if (newStatus === 'active') {
                document.getElementById('status-action-text').textContent = 'activate';
                document.getElementById('status-warning-text').textContent = 'This will allow the user to log in to the system.';
                document.getElementById('status-submit-btn').className = 'btn btn-success';
                document.getElementById('status-icon').className = 'bi bi-check-circle me-1';
                document.getElementById('status-btn-text').textContent = 'Activate User';
            } else {
                document.getElementById('status-action-text').textContent = 'deactivate';
                document.getElementById('status-warning-text').textContent = 'This will prevent the user from logging in to the system.';
                document.getElementById('status-submit-btn').className = 'btn btn-warning';
                document.getElementById('status-icon').className = 'bi bi-slash-circle me-1';
                document.getElementById('status-btn-text').textContent = 'Deactivate User';
            }
        });
    }

    // Delete User Modal
    const deleteUserModal = document.getElementById('deleteUserModal');
    if (deleteUserModal) {
        deleteUserModal.addEventListener('show.bs.modal', function (event) {
            // Button that triggered the modal
            const button = event.relatedTarget;

            // Extract info from data-* attributes
            const userId = button.getAttribute('data-id');
            const username = button.getAttribute('data-username');

            // Update the modal's content
            document.getElementById('delete-user-id').value = userId;
            document.getElementById('delete-username').textContent = username;
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