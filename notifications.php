<?php
require_once 'session_check.php';
check_session(); // Allow all authenticated users
include 'db_connection.php';

// Get logged-in user's ID and information
$userName = $_SESSION['username'];
$userType = $_SESSION['uType'];
$userId = $_SESSION['userID'] ?? 0;

// Get more detailed user information
$userQuery = $conn->prepare("SELECT first_name, last_name FROM z_user WHERE username = ?");
$userQuery->bind_param("s", $userName);
$userQuery->execute();
$userResult = $userQuery->get_result();
$userDetails = $userResult->fetch_assoc();

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Items per page
$offset = ($page - 1) * $limit;

// Filter setup
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$filterCondition = '';
switch ($filter) {
    case 'unread':
        $filterCondition = " AND n.is_read = 0";
        break;
    case 'read':
        $filterCondition = " AND n.is_read = 1";
        break;
    default:
        $filterCondition = "";
}

// Notification type filter
$typeFilter = isset($_GET['type']) ? $_GET['type'] : 'all';
$typeCondition = '';
if ($typeFilter !== 'all') {
    $typeCondition = " AND n.type = '" . $conn->real_escape_string($typeFilter) . "'";
}

// Get notifications
$notificationsQuery = $conn->prepare("
    SELECT 
        n.notification_id,
        n.type,
        n.title,
        n.message,
        n.is_read,
        n.created_at
    FROM notifications n
    WHERE n.user_id = ? $filterCondition $typeCondition
    ORDER BY n.created_at DESC
    LIMIT ?, ?
");
$notificationsQuery->bind_param("iii", $userId, $offset, $limit);
$notificationsQuery->execute();
$notificationsResult = $notificationsQuery->get_result();

// Count total for pagination
$totalQuery = $conn->prepare("
    SELECT COUNT(*) as total
    FROM notifications n
    WHERE n.user_id = ? $filterCondition $typeCondition
");
$totalQuery->bind_param("i", $userId);
$totalQuery->execute();
$totalResult = $totalQuery->get_result();
$totalRow = $totalResult->fetch_assoc();
$totalNotifications = $totalRow['total'];
$totalPages = ceil($totalNotifications / $limit);

// Count unread notifications
$unreadQuery = $conn->prepare("
    SELECT COUNT(*) as unread_count
    FROM notifications n
    WHERE n.user_id = ? AND n.is_read = 0
");
$unreadQuery->bind_param("i", $userId);
$unreadQuery->execute();
$unreadResult = $unreadQuery->get_result();
$unreadRow = $unreadResult->fetch_assoc();
$unreadCount = $unreadRow['unread_count'];

// Handle individual notification actions via AJAX
if (isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];

    if ($_POST['action'] === 'mark_read') {
        $notificationId = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;

        if ($notificationId > 0) {
            $markQuery = $conn->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE notification_id = ? AND user_id = ?
            ");
            $markQuery->bind_param("ii", $notificationId, $userId);

            if ($markQuery->execute() && $markQuery->affected_rows > 0) {
                $response['success'] = true;
                $response['message'] = 'Notification marked as read';
            } else {
                $response['message'] = 'Failed to mark notification as read';
            }
        }
    }
    elseif ($_POST['action'] === 'delete') {
        $notificationId = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;

        if ($notificationId > 0) {
            $deleteQuery = $conn->prepare("
                DELETE FROM notifications 
                WHERE notification_id = ? AND user_id = ?
            ");
            $deleteQuery->bind_param("ii", $notificationId, $userId);

            if ($deleteQuery->execute() && $deleteQuery->affected_rows > 0) {
                $response['success'] = true;
                $response['message'] = 'Notification deleted';
            } else {
                $response['message'] = 'Failed to delete notification';
            }
        }
    }
    elseif ($_POST['action'] === 'mark_all_read') {
        $markAllQuery = $conn->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE user_id = ? AND is_read = 0
        ");
        $markAllQuery->bind_param("i", $userId);

        if ($markAllQuery->execute()) {
            $response['success'] = true;
            $response['count'] = $markAllQuery->affected_rows;
            $response['message'] = $markAllQuery->affected_rows . ' notification(s) marked as read';
        } else {
            $response['message'] = 'Failed to mark notifications as read';
        }
    }
    elseif ($_POST['action'] === 'delete_selected') {
        $selectedIds = isset($_POST['ids']) ? $_POST['ids'] : [];

        if (!empty($selectedIds)) {
            // Convert to integers for security
            $selectedIds = array_map('intval', $selectedIds);

            // Create placeholders for prepared statement
            $placeholders = str_repeat('?,', count($selectedIds) - 1) . '?';

            // Build the query with dynamic placeholders
            $query = "DELETE FROM notifications WHERE notification_id IN ($placeholders) AND user_id = ?";

            // Prepare and bind parameters
            $stmt = $conn->prepare($query);
            $types = str_repeat('i', count($selectedIds)) . 'i';
            $params = $selectedIds;
            $params[] = $userId;

            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $response['success'] = true;
                $response['count'] = $stmt->affected_rows;
                $response['message'] = $stmt->affected_rows . ' notification(s) deleted';
            } else {
                $response['message'] = 'Failed to delete notifications';
            }
        }
    }
    elseif ($_POST['action'] === 'delete_all') {
        // Apply the same filters as the current view
        $deleteQuery = $conn->prepare("
            DELETE FROM notifications 
            WHERE user_id = ? $filterCondition $typeCondition
        ");
        $deleteQuery->bind_param("i", $userId);

        if ($deleteQuery->execute()) {
            $response['success'] = true;
            $response['count'] = $deleteQuery->affected_rows;
            $response['message'] = $deleteQuery->affected_rows . ' notification(s) deleted';
        } else {
            $response['message'] = 'Failed to delete notifications';
        }
    }

    // Return JSON response for AJAX requests
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Check for non-AJAX form submissions
if (isset($_POST['form_action'])) {
    $formAction = $_POST['form_action'];

    if ($formAction === 'mark_selected_read') {
        $notificationIds = isset($_POST['notification_ids']) ? $_POST['notification_ids'] : [];

        if (!empty($notificationIds)) {
            // Convert to integers for security
            $notificationIds = array_map('intval', $notificationIds);

            // Create placeholders for prepared statement
            $placeholders = str_repeat('?,', count($notificationIds) - 1) . '?';

            // Build the query with dynamic placeholders
            $query = "UPDATE notifications SET is_read = 1 WHERE notification_id IN ($placeholders) AND user_id = ?";

            // Prepare and bind parameters
            $stmt = $conn->prepare($query);
            $types = str_repeat('i', count($notificationIds)) . 'i';
            $params = $notificationIds;
            $params[] = $userId;

            $stmt->bind_param($types, ...$params);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $successMessage = $stmt->affected_rows . " notification(s) marked as read";
            } else {
                $errorMessage = "No notifications were updated";
            }
        } else {
            $errorMessage = "No notifications selected";
        }
    }
    elseif ($formAction === 'delete_selected') {
        $notificationIds = isset($_POST['notification_ids']) ? $_POST['notification_ids'] : [];

        if (!empty($notificationIds)) {
            // Convert to integers for security
            $notificationIds = array_map('intval', $notificationIds);

            // Create placeholders for prepared statement
            $placeholders = str_repeat('?,', count($notificationIds) - 1) . '?';

            // Build the query with dynamic placeholders
            $query = "DELETE FROM notifications WHERE notification_id IN ($placeholders) AND user_id = ?";

            // Prepare and bind parameters
            $stmt = $conn->prepare($query);
            $types = str_repeat('i', count($notificationIds)) . 'i';
            $params = $notificationIds;
            $params[] = $userId;

            $stmt->bind_param($types, ...$params);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $successMessage = $stmt->affected_rows . " notification(s) deleted";
            } else {
                $errorMessage = "No notifications were deleted";
            }
        } else {
            $errorMessage = "No notifications selected";
        }
    }

    // Redirect to refresh the page after action
    header("Location: notifications.php?filter=$filter&type=$typeFilter&page=$page");
    exit;
}

// Check for session messages
$successMessage = $successMessage ?? ($_SESSION['success'] ?? '');
$errorMessage = $_SESSION['error'] ?? '';

// Clear session messages
if (isset($_SESSION['success'])) unset($_SESSION['success']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);

// Helper function to get time ago format
function getTimeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ($mins == 1 ? ' minute ago' : ' minutes ago');
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ($hours == 1 ? ' hour ago' : ' hours ago');
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ($days == 1 ? ' day ago' : ' days ago');
    } else {
        return date('M j, Y', $time);
    }
}

// Helper function to get notification icon based on type
function getNotificationIcon($type) {
    switch ($type) {
        case 'product':
            return 'bi-box';
        case 'system':
            return 'bi-gear';
        case 'user':
            return 'bi-person';
        default:
            return 'bi-bell';
    }
}

// Helper function to get notification color based on type
function getNotificationColor($type, $message = '') {
    switch ($type) {
        case 'product':
            if (strpos($message, 'Amber') !== false) {
                return 'amber';
            } elseif (strpos($message, 'Red') !== false) {
                return 'red';
            }
            return 'green';
        case 'system':
            return 'blue';
        case 'user':
            return 'purple';
        default:
            return 'primary';
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Zouki Food Insights</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        :root {
            --primary-color: #4CAF50;
            --primary-color-dark: #3e8e41;
            --secondary-color: #2196F3;
            --background-color: #f5f7fa;
            --card-bg: #ffffff;
            --text-color: #333333;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 70px;
            --topbar-height: 70px;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --transition-speed: 0.3s;
        }

        [data-bs-theme="dark"] {
            --primary-color: #5cb85c;
            --primary-color-dark: #4cae4c;
            --secondary-color: #5bc0de;
            --background-color: #1e1e2f;
            --card-bg: #27293d;
            --text-color: #ffffff;
            --text-muted: #adb5bd;
            --border-color: #444;
        }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            transition: background-color var(--transition-speed);
            overflow-x: hidden;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: var(--card-bg);
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
            z-index: 999;
            transition: all var(--transition-speed);
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar-header {
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-color);
        }

        .sidebar-logo {
            height: 40px;
            transition: all var(--transition-speed);
        }

        .sidebar.collapsed .sidebar-logo {
            transform: scale(0.8);
        }

        .sidebar-toggle {
            background: transparent;
            border: none;
            color: var(--text-color);
            font-size: 1.25rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px;
            border-radius: 50%;
            transition: all var(--transition-speed);
        }

        .sidebar-toggle:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }

        .sidebar.collapsed .sidebar-toggle i::before {
            content: "\F138";
        }

        .sidebar-menu {
            padding: 20px 0;
            flex-grow: 1;
        }

        .sidebar-menu-section {
            margin-bottom: 10px;
            padding: 0 20px;
        }

        .sidebar-menu-section-title {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 10px;
            transition: all var(--transition-speed);
            white-space: nowrap;
        }

        .sidebar.collapsed .sidebar-menu-section-title {
            opacity: 0;
        }

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--text-color);
            border-radius: 8px;
            margin: 0 8px;
            transition: all var(--transition-speed);
            position: relative;
            overflow: hidden;
        }

        .nav-link i {
            font-size: 1.2rem;
            margin-right: 12px;
            transition: all var(--transition-speed);
        }

        .nav-link span {
            transition: all var(--transition-speed);
            white-space: nowrap;
        }

        .sidebar.collapsed .nav-link span {
            opacity: 0;
            width: 0;
        }

        .sidebar.collapsed .nav-link i {
            margin-right: 0;
        }

        .nav-link:hover {
            background-color: rgba(76, 175, 80, 0.1);
            color: var(--primary-color);
        }

        .nav-link.active {
            background-color: var(--primary-color);
            color: white;
        }

        .nav-link.active i {
            color: white;
        }

        .sidebar-footer {
            padding: 15px 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all var(--transition-speed);
        }

        .sidebar.collapsed .sidebar-footer {
            justify-content: center;
        }

        .footer-button {
            background: transparent;
            border: none;
            color: var(--text-color);
            font-size: 1.25rem;
            cursor: pointer;
            transition: all var(--transition-speed);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .footer-button .footer-text {
            font-size: 0.8rem;
        }

        .footer-button:hover {
            color: var(--primary-color);
        }

        .sidebar.collapsed .footer-button .footer-text {
            display: none;
        }

        /* Main Content Area */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            transition: all var(--transition-speed);
            min-height: 100vh;
        }

        .main-content.expanded {
            margin-left: var(--sidebar-collapsed-width);
        }

        /* Top Navbar */
        .top-navbar {
            background: var(--card-bg);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 0;
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }

        /* Card styles */
        .app-card {
            background: var(--card-bg);
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 24px;
            transition: all var(--transition-speed);
        }

        .app-card-body {
            padding: 24px;
        }

        .app-card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .app-card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
        }

        /* Notification Item */
        .notification-item {
            position: relative;
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            transition: all var(--transition-speed);
            display: flex;
            align-items: flex-start;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }

        .notification-item.read {
            opacity: 0.7;
        }

        .notification-checkbox {
            margin-right: 15px;
            margin-top: 4px;
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-right: 16px;
            color: white;
            flex-shrink: 0;
        }

        .notification-icon.green {
            background-color: var(--success-color);
        }

        .notification-icon.amber {
            background-color: var(--warning-color);
        }

        .notification-icon.red {
            background-color: var(--danger-color);
        }

        .notification-icon.blue {
            background-color: var(--info-color);
        }

        .notification-icon.purple {
            background-color: #6f42c1;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            margin-bottom: 3px;
            display: block;
        }

        .notification-message {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-bottom: 5px;
        }

        .notification-time {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .notification-actions {
            margin-left: 15px;
            display: flex;
            gap: 8px;
        }

        .btn-action {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            background-color: transparent;
            border: none;
            cursor: pointer;
            transition: all var(--transition-speed);
        }

        .btn-action:hover {
            background-color: rgba(0, 0, 0, 0.05);
            color: var(--primary-color);
        }

        .btn-action.delete:hover {
            color: var(--danger-color);
        }

        /* Filter Toolbar */
        .filter-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Pagination */
        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .pagination-info {
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        /* Toast notification */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: var(--sidebar-collapsed-width);
                transform: translateX(-100%);
            }

            .sidebar.mobile-show {
                transform: translateX(0);
                width: var(--sidebar-width);
            }

            .sidebar.mobile-show .sidebar-menu-section-title,
            .sidebar.mobile-show .nav-link span,
            .sidebar.mobile-show .footer-button .footer-text {
                opacity: 1;
                width: auto;
            }

            .sidebar.mobile-show .nav-link i {
                margin-right: 12px;
            }

            .main-content {
                margin-left: 0;
            }

            .top-navbar {
                padding: 15px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }

            .filter-toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>

<body>
<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="img/ZoukiLogo.svg" alt="ZOUKI Logo" class="sidebar-logo">
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="bi bi-chevron-left"></i>
        </button>
    </div>

    <div class="sidebar-menu">
        <div class="sidebar-menu-section">
            <div class="sidebar-menu-section-title">Main</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="home.php">
                        <i class="bi bi-house-door"></i>
                        <span>Home</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="sidebar-menu-section">
            <div class="sidebar-menu-section-title">Product Management</div>
            <ul class="nav flex-column">
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
            </ul>
        </div>

        <div class="sidebar-menu-section">
            <div class="sidebar-menu-section-title">User Management</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="users.php">
                        <i class="bi bi-people"></i>
                        <span>Users</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="roles.php">
                        <i class="bi bi-person-badge"></i>
                        <span>Roles & Permissions</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="sidebar-menu-section">
            <div class="sidebar-menu-section-title">Notifications</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="notifications.php">
                        <i class="bi bi-bell"></i>
                        <span>Notifications</span>
                        <?php if ($unreadCount > 0): ?>
                            <span class="badge rounded-pill bg-danger ms-2"><?php echo $unreadCount; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
        </div>

        <div class="sidebar-menu-section">
            <div class="sidebar-menu-section-title">Configuration</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
                        <i class="bi bi-gear"></i>
                        <span>Settings</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile_edit.php">
                        <i class="bi bi-person-circle"></i>
                        <span>My Profile</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="sidebar-footer">
        <button class="footer-button" id="darkModeToggle">
            <i class="bi bi-moon"></i>
            <span class="footer-text">Dark Mode</span>
        </button>
        <button class="footer-button" id="logoutButton" onclick="window.location.href='logout.php'">
            <i class="bi bi-box-arrow-right"></i>
            <span class="footer-text">Logout</span>
        </button>
    </div>
</aside>

<!-- Main Content -->
<main class="main-content" id="mainContent">
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

    <!-- Top Navbar -->
    <div class="top-navbar">
        <div class="d-flex align-items-center">
            <button class="btn btn-link d-lg-none me-2" id="mobileMenuToggle">
                <i class="bi bi-list fs-4"></i>
            </button>
            <h1 class="page-title">Notifications</h1>
        </div>
    </div>

    <!-- Notifications -->
    <div class="app-card">
        <div class="app-card-header">
            <h5 class="app-card-title">All Notifications</h5>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm" id="markAllReadBtn">
                    <i class="bi bi-envelope-open"></i> Mark All as Read
                </button>
                <button class="btn btn-outline-danger btn-sm" id="deleteAllBtn">
                    <i class="bi bi-trash"></i> Delete All
                </button>
            </div>
        </div>
        <div class="app-card-body">
            <form action="notifications.php" method="POST" id="notificationsForm">
                <!-- Filter Toolbar -->
                <div class="filter-toolbar">
                    <div class="filter-group">
                        <select class="form-select" name="filter" id="filterSelect">
                            <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Notifications</option>
                            <option value="unread" <?php echo $filter === 'unread' ? 'selected' : ''; ?>>Unread Only</option>
                            <option value="read" <?php echo $filter === 'read' ? 'selected' : ''; ?>>Read Only</option>
                        </select>
                        <select class="form-select" name="type" id="typeSelect">
                            <option value="all" <?php echo $typeFilter === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <option value="product" <?php echo $typeFilter === 'product' ? 'selected' : ''; ?>>Product</option>
                            <option value="system" <?php echo $typeFilter === 'system' ? 'selected' : ''; ?>>System</option>
                            <option value="user" <?php echo $typeFilter === 'user' ? 'selected' : ''; ?>>User</option>
                        </select>
                        <button type="button" class="btn btn-primary" id="applyFiltersBtn">
                            Apply Filters
                        </button>
                    </div>
                    <div class="filter-group">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btnSelectAll">
                            <i class="bi bi-check-all"></i> Select All
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btnMarkSelected">
                            <i class="bi bi-envelope-open"></i> Mark Selected
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm" id="btnDeleteSelected">
                            <i class="bi bi-trash"></i> Delete Selected
                        </button>
                    </div>
                </div>

                <!-- Notifications List -->
                <div class="notifications-list">
                    <?php if ($notificationsResult && $notificationsResult->num_rows > 0): ?>
                        <?php while ($notification = $notificationsResult->fetch_assoc()): ?>
                            <?php
                            $iconClass = getNotificationIcon($notification['type']);
                            $colorClass = getNotificationColor($notification['type'], $notification['message']);
                            $timeAgo = getTimeAgo($notification['created_at']);
                            ?>
                            <div class="notification-item <?php echo $notification['is_read'] ? 'read' : ''; ?>" data-id="<?php echo $notification['notification_id']; ?>">
                                <div class="notification-checkbox">
                                    <input type="checkbox" class="form-check-input notification-check"
                                           name="notification_ids[]" value="<?php echo $notification['notification_id']; ?>">
                                </div>
                                <div class="notification-icon <?php echo $colorClass; ?>">
                                    <i class="bi <?php echo $iconClass; ?>"></i>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                    <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                                    <div class="notification-time"><?php echo $timeAgo; ?></div>
                                </div>
                                <div class="notification-actions">
                                    <?php if (!$notification['is_read']): ?>
                                        <button type="button" class="btn-action mark-read" data-id="<?php echo $notification['notification_id']; ?>" title="Mark as read">
                                            <i class="bi bi-envelope-open"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn-action delete" data-id="<?php echo $notification['notification_id']; ?>" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center p-5">
                            <div class="display-1 text-muted mb-3">
                                <i class="bi bi-bell-slash"></i>
                            </div>
                            <h3 class="text-muted mb-3">No notifications found</h3>
                            <p class="text-muted mb-4">You don't have any notifications at the moment.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-wrapper">
                        <div class="pagination-info">
                            Showing <?php echo min($totalNotifications, $offset + 1); ?> to <?php echo min($offset + $limit, $totalNotifications); ?> of <?php echo $totalNotifications; ?> notifications
                        </div>
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?>&type=<?php echo $typeFilter; ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>

                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>&type=<?php echo $typeFilter; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>

                                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?>&type=<?php echo $typeFilter; ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>

                <!-- Hidden inputs for action -->
                <input type="hidden" name="form_action" id="formAction" value="">
            </form>
        </div>
    </div>

    <!-- Toast Container for Notifications -->
    <div class="toast-container position-fixed top-0 end-0 p-3"></div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmationModalTitle">Confirmation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="confirmationModalBody">
                    Are you sure you want to proceed with this action?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmModalBtn">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="mt-4 mb-3">
        <div class="text-center text-muted">
            Copyright © <?php echo date("Y"); ?> Zouki Group of Companies. All rights reserved.
        </div>
    </footer>
</main>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // DOM Elements
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const darkModeToggle = document.getElementById('darkModeToggle');
    const notificationsForm = document.getElementById('notificationsForm');
    const btnSelectAll = document.getElementById('btnSelectAll');
    const btnMarkSelected = document.getElementById('btnMarkSelected');
    const btnDeleteSelected = document.getElementById('btnDeleteSelected');
    const markAllReadBtn = document.getElementById('markAllReadBtn');
    const deleteAllBtn = document.getElementById('deleteAllBtn');
    const filterSelect = document.getElementById('filterSelect');
    const typeSelect = document.getElementById('typeSelect');
    const applyFiltersBtn = document.getElementById('applyFiltersBtn');
    const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
    const confirmModalBtn = document.getElementById('confirmModalBtn');
    const checkboxes = document.querySelectorAll('.notification-check');

    // Initialize actions
    let currentAction = '';
    let selectedId = null;

    // Sidebar Toggle
    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
    });

    // Mobile Menu Toggle
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('mobile-show');
        });
    }

    // Dark Mode Toggle
    function toggleDarkMode() {
        const html = document.documentElement;
        const currentTheme = html.getAttribute('data-bs-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

        html.setAttribute('data-bs-theme', newTheme);

        // Update icon
        const darkModeIcon = darkModeToggle.querySelector('i');

        if (newTheme === 'dark') {
            darkModeIcon.classList.replace('bi-moon', 'bi-sun');
            darkModeToggle.querySelector('.footer-text').textContent = 'Light Mode';
        } else {
            darkModeIcon.classList.replace('bi-sun', 'bi-moon');
            darkModeToggle.querySelector('.footer-text').textContent = 'Dark Mode';
        }

        // Save preference to localStorage
        localStorage.setItem('theme', newTheme);
    }

    darkModeToggle.addEventListener('click', toggleDarkMode);

    // Check for saved theme preference
    document.addEventListener('DOMContentLoaded', () => {
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            document.documentElement.setAttribute('data-bs-theme', savedTheme);

            if (savedTheme === 'dark') {
                darkModeToggle.querySelector('i').classList.replace('bi-moon', 'bi-sun');
                darkModeToggle.querySelector('.footer-text').textContent = 'Light Mode';
            }
        }

        // Initialize for small screens
        const isSmallScreen = window.innerWidth < 992;
        if (isSmallScreen) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        }
    });

    // Apply filters
    applyFiltersBtn.addEventListener('click', () => {
        const filter = filterSelect.value;
        const type = typeSelect.value;
        window.location.href = `notifications.php?filter=${filter}&type=${type}`;
    });

    // Select All Checkboxes
    btnSelectAll.addEventListener('click', () => {
        const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);

        checkboxes.forEach(checkbox => {
            checkbox.checked = !allChecked;
        });

        btnSelectAll.innerHTML = allChecked ?
            '<i class="bi bi-check-all"></i> Select All' :
            '<i class="bi bi-check-all"></i> Unselect All';
    });

    // Mark Selected as Read button
    btnMarkSelected.addEventListener('click', () => {
        const selectedIds = getSelectedIds();

        if (selectedIds.length === 0) {
            showToast('Please select at least one notification', 'warning');
            return;
        }

        // Set form action
        document.getElementById('formAction').value = 'mark_selected_read';

        // Show confirmation
        document.getElementById('confirmationModalTitle').textContent = 'Mark as Read';
        document.getElementById('confirmationModalBody').textContent =
            `Are you sure you want to mark ${selectedIds.length} notification(s) as read?`;
        document.getElementById('confirmModalBtn').className = 'btn btn-primary';
        document.getElementById('confirmModalBtn').textContent = 'Mark as Read';

        currentAction = 'mark_selected_read';
        confirmationModal.show();
    });

    // Delete Selected button
    btnDeleteSelected.addEventListener('click', () => {
        const selectedIds = getSelectedIds();

        if (selectedIds.length === 0) {
            showToast('Please select at least one notification', 'warning');
            return;
        }

        // Set form action
        document.getElementById('formAction').value = 'delete_selected';

        // Show confirmation
        document.getElementById('confirmationModalTitle').textContent = 'Delete Notifications';
        document.getElementById('confirmationModalBody').textContent =
            `Are you sure you want to delete ${selectedIds.length} notification(s)?`;
        document.getElementById('confirmModalBtn').className = 'btn btn-danger';
        document.getElementById('confirmModalBtn').textContent = 'Delete';

        currentAction = 'delete_selected';
        confirmationModal.show();
    });

    // Mark All as Read button
    markAllReadBtn.addEventListener('click', () => {
        // Show confirmation
        document.getElementById('confirmationModalTitle').textContent = 'Mark All as Read';
        document.getElementById('confirmationModalBody').textContent =
            'Are you sure you want to mark all notifications as read?';
        document.getElementById('confirmModalBtn').className = 'btn btn-primary';
        document.getElementById('confirmModalBtn').textContent = 'Mark All as Read';

        currentAction = 'mark_all_read';
        confirmationModal.show();
    });

    // Delete All button
    deleteAllBtn.addEventListener('click', () => {
        // Show confirmation
        document.getElementById('confirmationModalTitle').textContent = 'Delete All Notifications';
        document.getElementById('confirmationModalBody').textContent =
            'Are you sure you want to delete all notifications? This action cannot be undone.';
        document.getElementById('confirmModalBtn').className = 'btn btn-danger';
        document.getElementById('confirmModalBtn').textContent = 'Delete All';

        currentAction = 'delete_all';
        confirmationModal.show();
    });

    // Individual Mark as Read buttons
    document.querySelectorAll('.btn-action.mark-read').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const id = btn.getAttribute('data-id');
            const item = btn.closest('.notification-item');

            // Send AJAX request
            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=mark_read&notification_id=${id}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Mark as read visually
                        item.classList.add('read');

                        // Remove the mark as read button
                        btn.remove();

                        // Show toast message
                        showToast(data.message, 'success');

                        // Update unread count in sidebar
                        updateUnreadCount(-1);
                    } else {
                        showToast(data.message, 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('An error occurred', 'danger');
                });
        });
    });

    // Individual Delete buttons
    document.querySelectorAll('.btn-action.delete').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const id = btn.getAttribute('data-id');
            selectedId = id;

            // Show confirmation
            document.getElementById('confirmationModalTitle').textContent = 'Delete Notification';
            document.getElementById('confirmationModalBody').textContent =
                'Are you sure you want to delete this notification?';
            document.getElementById('confirmModalBtn').className = 'btn btn-danger';
            document.getElementById('confirmModalBtn').textContent = 'Delete';

            currentAction = 'delete_single';
            confirmationModal.show();
        });
    });

    // Confirmation Modal Button
    confirmModalBtn.addEventListener('click', () => {
        switch (currentAction) {
            case 'mark_selected_read':
            case 'delete_selected':
                notificationsForm.submit();
                break;

            case 'mark_all_read':
                handleMarkAllRead();
                break;

            case 'delete_all':
                handleDeleteAll();
                break;

            case 'delete_single':
                handleDeleteSingle(selectedId);
                break;
        }

        confirmationModal.hide();
    });

    // Helper function to get selected notification IDs
    function getSelectedIds() {
        return Array.from(checkboxes)
            .filter(checkbox => checkbox.checked)
            .map(checkbox => checkbox.value);
    }

    // Handle Mark All as Read
    function handleMarkAllRead() {
        fetch('notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=mark_all_read'
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mark all items as read visually
                    document.querySelectorAll('.notification-item:not(.read)').forEach(item => {
                        item.classList.add('read');

                        // Remove mark as read buttons
                        const markReadBtn = item.querySelector('.btn-action.mark-read');
                        if (markReadBtn) {
                            markReadBtn.remove();
                        }
                    });

                    // Show toast message
                    showToast(data.message, 'success');

                    // Set unread count to 0
                    const unreadBadge = document.querySelector('.sidebar .badge');
                    if (unreadBadge) {
                        unreadBadge.style.display = 'none';
                    }
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred', 'danger');
            });
    }

    // Handle Delete All
    function handleDeleteAll() {
        fetch('notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete_all&filter=<?php echo $filter; ?>&type=<?php echo $typeFilter; ?>`
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show toast message
                    showToast(data.message, 'success');

                    // Reload the page to reflect changes
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred', 'danger');
            });
    }

    // Handle Delete Single
    function handleDeleteSingle(id) {
        if (!id) return;

        fetch('notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete&notification_id=${id}`
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the notification item from the DOM
                    const item = document.querySelector(`.notification-item[data-id="${id}"]`);
                    if (item) {
                        item.remove();

                        // If no notifications left, show empty state
                        if (document.querySelectorAll('.notification-item').length === 0) {
                            const emptyState = `
                                <div class="text-center p-5">
                                    <div class="display-1 text-muted mb-3">
                                        <i class="bi bi-bell-slash"></i>
                                    </div>
                                    <h3 class="text-muted mb-3">No notifications found</h3>
                                    <p class="text-muted mb-4">You don't have any notifications at the moment.</p>
                                </div>
                            `;
                            document.querySelector('.notifications-list').innerHTML = emptyState;
                        }
                    }

                    // Show toast message
                    showToast(data.message, 'success');

                    // If it was unread, update the count
                    if (!item.classList.contains('read')) {
                        updateUnreadCount(-1);
                    }
                } else {
                    showToast(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred', 'danger');
            });
    }

    // Helper function to show toast notifications
    function showToast(message, type = 'success') {
        const toastId = 'toast-' + Date.now();
        const toastHTML = `
                <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;

        const toastContainer = document.querySelector('.toast-container');
        toastContainer.insertAdjacentHTML('beforeend', toastHTML);

        const toast = new bootstrap.Toast(document.getElementById(toastId), {
            autohide: true,
            delay: 3000
        });

        toast.show();

        // Remove the toast from DOM after it's hidden
        document.getElementById(toastId).addEventListener('hidden.bs.toast', function () {
            this.remove();
        });
    }

    // Update unread count in sidebar
    function updateUnreadCount(change) {
        const badge = document.querySelector('.sidebar .badge');
        if (badge) {
            const currentCount = parseInt(badge.textContent);
            const newCount = currentCount + change;

            if (newCount <= 0) {
                badge.style.display = 'none';
            } else {
                badge.textContent = newCount;
                badge.style.display = 'inline-block';
            }
        }
    }
</script>
</body>
</html>