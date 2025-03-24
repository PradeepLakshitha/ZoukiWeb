<?php
require_once 'session_check.php';
check_session(['Admin', 'Manager']);
include 'db_connection.php';

// Check database connection
if (!$conn) {
    error_log("Database connection failed in dashboard.php");
}

// Redirect to login if not logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['uType'])) {
    header("Location: index.php");
    exit();
}

// Restrict access to only Admin and Manager
if ($_SESSION['uType'] !== 'Admin' && $_SESSION['uType'] !== 'Manager') {
    $_SESSION['error'] = "Access denied! You don't have permission.";
    header("Location: home.php");
    exit();
}

// Get the logged-in user's ID and information
$userName = $_SESSION['username'];
$userType = $_SESSION['uType'];
$userId = $_SESSION['userID'] ?? 0;

// Get more detailed user information
$userQuery = $conn->prepare("SELECT first_name, last_name, email, contact_number FROM z_user WHERE username = ?");
if (!$userQuery) {
    error_log("User query prepare failed: " . $conn->error);
} else {
    $userQuery->bind_param("s", $userName);
    $userQuery->execute();
    $userResult = $userQuery->get_result();
    $userDetails = $userResult ? $userResult->fetch_assoc() : null;
}

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

// Fetch some basic statistics for the dashboard
// Count total products
$totalProductsQuery = $conn->query("SELECT COUNT(*) as count FROM products");
if (!$totalProductsQuery) {
    error_log("Total products query failed: " . $conn->error);
    $totalProducts = 0;
} else {
    $totalProducts = $totalProductsQuery->fetch_assoc()['count'] ?? 0;
}

// Count total categories
$totalCategoriesQuery = $conn->query("SELECT COUNT(*) as count FROM categories");
if (!$totalCategoriesQuery) {
    error_log("Total categories query failed: " . $conn->error);
    $totalCategories = 0;
} else {
    $totalCategories = $totalCategoriesQuery->fetch_assoc()['count'] ?? 0;
}

// Count total users
$totalUsersQuery = $conn->query("SELECT COUNT(*) as count FROM z_user");
if (!$totalUsersQuery) {
    error_log("Total users query failed: " . $conn->error);
    $totalUsers = 0;
} else {
    $totalUsers = $totalUsersQuery->fetch_assoc()['count'] ?? 0;
}

// Get recent products (limit to 5)
$recentProducts = $conn->query("SELECT product_name, healthy_option, DATE_FORMAT(created_at, '%d %b %Y') as date_created FROM products ORDER BY created_at DESC LIMIT 5");
if (!$recentProducts) {
    error_log("Recent products query failed: " . $conn->error);
}

// Get health distribution data
$healthDistributionQuery = $conn->query("SELECT healthy_option, COUNT(*) as count FROM products GROUP BY healthy_option");
if (!$healthDistributionQuery) {
    error_log("Health distribution query failed: " . $conn->error);
}

$healthDistribution = [
    'Green' => 0,
    'Amber' => 0,
    'Red' => 0
];

if ($healthDistributionQuery && $healthDistributionQuery->num_rows > 0) {
    while ($row = $healthDistributionQuery->fetch_assoc()) {
        if (isset($row['healthy_option']) && array_key_exists($row['healthy_option'], $healthDistribution)) {
            $healthDistribution[$row['healthy_option']] = (int)$row['count'];
        }
    }
}

// Get monthly product data for chart
$monthlyProductQuery = $conn->query("
    SELECT 
        MONTH(created_at) as month,
        COUNT(*) as total_count,
        SUM(CASE WHEN healthy_option = 'Green' THEN 1 ELSE 0 END) as healthy_count
    FROM products
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
    GROUP BY MONTH(created_at)
    ORDER BY MONTH(created_at)
");

if (!$monthlyProductQuery) {
    error_log("Monthly product query failed: " . $conn->error);
}

$monthlyProductData = [];
$monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

// Initialize with zeros for all months
for ($i = 0; $i < 12; $i++) {
    $monthlyProductData[$i] = [
        'label' => $monthNames[$i],
        'total' => 0,
        'healthy' => 0
    ];
}

// Fill in actual data
if ($monthlyProductQuery && $monthlyProductQuery->num_rows > 0) {
    while ($row = $monthlyProductQuery->fetch_assoc()) {
        $monthIndex = (int)$row['month'] - 1; // Convert 1-based month to 0-based index
        if ($monthIndex >= 0 && $monthIndex < 12) {
            $monthlyProductData[$monthIndex]['total'] = (int)$row['total_count'];
            $monthlyProductData[$monthIndex]['healthy'] = (int)$row['healthy_count'];
        }
    }
}

// Get unread notification count
$unreadQuery = $conn->prepare("
    SELECT COUNT(*) as unread_count
    FROM notifications
    WHERE user_id = ? AND is_read = 0
");

if (!$unreadQuery) {
    error_log("Unread notifications query prepare failed: " . $conn->error);
    $unreadCount = 0;
} else {
    $unreadQuery->bind_param("i", $userId);
    $unreadQuery->execute();
    $unreadResult = $unreadQuery->get_result();
    $unreadRow = $unreadResult ? $unreadResult->fetch_assoc() : null;
    $unreadCount = $unreadRow ? $unreadRow['unread_count'] : 0;
}

// Get recent notifications (limit to 3)
$notificationsQuery = $conn->prepare("
    SELECT 
        notification_id,
        type,
        title,
        message,
        is_read,
        created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 3
");

if (!$notificationsQuery) {
    error_log("Notifications query prepare failed: " . $conn->error);
} else {
    $notificationsQuery->bind_param("i", $userId);
    $notificationsQuery->execute();
    $notificationsResult = $notificationsQuery->get_result();
}

// Check for messages
$successMessage = $_SESSION['success'] ?? '';
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
    <title>Zouki Food Insights - Admin Dashboard</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
            --chart-primary: rgba(76, 175, 80, 0.7);
            --chart-secondary: rgba(33, 150, 243, 0.7);
            --chart-success: rgba(40, 167, 69, 0.7);
            --chart-warning: rgba(255, 193, 7, 0.7);
            --chart-danger: rgba(220, 53, 69, 0.7);
            --transition-speed: 0.3s;
        }
        
        /* Ensure no horizontal scrollbars on body */
        html, body {
            overflow-x: hidden;
            max-width: 100%;
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
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
            overflow-y: hidden;
        }
        
        .sidebar.collapsed:hover {
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }

        .sidebar-logo {
            height: 40px;
            transition: all var(--transition-speed);
            display: block;
        }

        .sidebar.collapsed .sidebar-logo {
            transform: scale(0.8);
        }
        
        .sidebar.collapsed .sidebar-header {
            justify-content: center;
            padding: 20px 0;
        }

        .sidebar-toggle {
            background: #ffffff;
            border: 1px solid var(--border-color);
            color: var(--text-color);
            font-size: 1.25rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px;
            border-radius: 50%;
            transition: all var(--transition-speed);
            position: absolute;
            top: 20px;
            right: 15px;
            width: 30px;
            height: 30px;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .sidebar-toggle:hover {
            background-color: var(--primary-color);
            color: white;
            transform: rotate(180deg);
        }

        .sidebar.collapsed .sidebar-toggle {
            right: -15px;
            transform: rotate(180deg);
        }

        .sidebar.collapsed .sidebar-toggle:hover {
            transform: rotate(0);
        }

        .sidebar.collapsed .sidebar-toggle i::before {
            content: "\F138";
        }

        .sidebar-menu {
            padding: 20px 0;
            flex-grow: 1;
        }

        .sidebar-menu-section {
            margin-bottom: 15px;
            padding: 0 20px;
            transition: all var(--transition-speed);
        }
        
        .sidebar.collapsed .sidebar-menu-section {
            padding: 0 5px;
        }

        .sidebar-menu-section-title {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 10px;
            transition: all var(--transition-speed);
            white-space: nowrap;
            text-align: left;
        }

        .sidebar.collapsed .sidebar-menu-section-title {
            opacity: 0;
            height: 0;
            margin: 0;
            padding: 0;
            overflow: hidden;
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
            white-space: nowrap;
        }

        .nav-link i {
            font-size: 1.2rem;
            margin-right: 12px;
            transition: all var(--transition-speed);
            display: inline-block;
            width: 24px;
            text-align: center;
        }

        .nav-link span {
            transition: all var(--transition-speed);
            white-space: nowrap;
        }

        .sidebar.collapsed .nav-link {
            padding: 12px 0;
            display: flex;
            justify-content: center;
            text-align: center;
            margin: 0 8px;
        }

        .sidebar.collapsed .nav-link span {
            opacity: 0;
            width: 0;
            display: none;
        }

        .sidebar.collapsed .nav-link i {
            margin-right: 0;
            font-size: 1.3rem;
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
            margin-top: auto;
        }

        .sidebar.collapsed .sidebar-footer {
            justify-content: center;
            padding: 15px 0;
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
            padding: 5px 10px;
            border-radius: 5px;
        }

        .footer-button .footer-text {
            font-size: 0.8rem;
            transition: all var(--transition-speed);
        }

        .footer-button:hover {
            color: var(--primary-color);
            background-color: rgba(0, 0, 0, 0.05);
        }

        .sidebar.collapsed .footer-button .footer-text {
            display: none;
            width: 0;
            opacity: 0;
        }
        
        .sidebar.collapsed .footer-button {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border-radius: 50%;
            margin: 5px 0;
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

        .navbar-tools {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .navbar-tool-button {
            background: transparent;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-color);
            font-size: 1.25rem;
            cursor: pointer;
            transition: all var(--transition-speed);
            position: relative;
        }

        .navbar-tool-button:hover {
            background-color: rgba(0, 0, 0, 0.05);
            color: var(--primary-color);
        }

        .notification-badge {
            position: absolute;
            top: 3px;
            right: 3px;
            background-color: var(--danger-color);
            color: white;
            font-size: 0.7rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 5px;
            border-radius: 8px;
            transition: all var(--transition-speed);
        }

        .user-profile:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            line-height: 1.2;
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--text-muted);
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

        .app-card:hover {
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transform: translateY(-5px);
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

        .app-card-toolbar {
            display: flex;
            gap: 10px;
        }

        /* Stats Card */
        .stats-card {
            display: flex;
            align-items: center;
            padding: 24px;
        }

        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin-right: 16px;
            color: white;
        }

        .stats-icon.primary {
            background-color: var(--primary-color);
        }

        .stats-icon.info {
            background-color: var(--info-color);
        }

        .stats-icon.warning {
            background-color: var(--warning-color);
        }

        .stats-icon.success {
            background-color: var(--success-color);
        }

        .stats-data {
            flex: 1;
        }

        .stats-value {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stats-label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        /* Progress bars */
        .progress {
            height: 10px;
            border-radius: 5px;
            margin: 8px 0;
        }

        /* Recent Items */
        .recent-item {
            display: flex;
            align-items: center;
            padding: 16px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .recent-item:last-child {
            border-bottom: none;
        }

        .recent-item-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-right: 16px;
            color: white;
        }

        .recent-item-icon.green {
            background-color: var(--success-color);
        }

        .recent-item-icon.amber {
            background-color: var(--warning-color);
        }

        .recent-item-icon.red {
            background-color: var(--danger-color);
        }

        .recent-item-icon.blue {
            background-color: var(--info-color);
        }

        .recent-item-icon.purple {
            background-color: #6f42c1;
        }

        .recent-item-content {
            flex: 1;
        }

        .recent-item-title {
            font-weight: 600;
            margin-bottom: 3px;
            display: block;
        }

        .recent-item-info {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .recent-item-date {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* Custom Dropdown Menu */
        .app-dropdown {
            position: absolute;
            top: 50px;
            right: 0;
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            width: 280px;
            z-index: 1000;
            overflow: hidden;
            transform-origin: top right;
            transform: scale(0);
            opacity: 0;
            transition: all 0.2s ease;
        }

        .app-dropdown.show {
            transform: scale(1);
            opacity: 1;
        }

        .app-dropdown-header {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .app-dropdown-title {
            font-weight: 600;
            margin: 0;
        }

        .app-dropdown-body {
            padding: 16px;
            max-height: 350px;
            overflow-y: auto;
        }

        .app-dropdown-footer {
            padding: 12px 16px;
            border-top: 1px solid var(--border-color);
            text-align: center;
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
                overflow-y: auto;
                overflow-x: hidden;
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
            
            .sidebar.collapsed .sidebar-toggle {
                transform: translateX(100%);
            }

            .main-content {
                margin-left: 0;
                overflow-x: hidden;
            }

            .top-navbar {
                padding: 15px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }

            .stats-icon {
                width: 50px;
                height: 50px;
                font-size: 1.5rem;
            }

            .stats-value {
                font-size: 1.5rem;
            }

            .app-card-body {
                padding: 16px;
            }
        }

        /* Chart Containers */
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }

        .toast {
            background: white;
            color: #333;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 10px;
            min-width: 250px;
        }

        .toast.success {
            border-left: 4px solid var(--success-color);
        }

        .toast.error, .toast.danger {
            border-left: 4px solid var(--danger-color);
        }

        .toast.warning {
            border-left: 4px solid var(--warning-color);
        }

        .toast.info {
            border-left: 4px solid var(--info-color);
        }
        
        /* Count-up animation */
        .count-up {
            transition: all 0.5s ease;
        }
        
        /* Overlay for mobile sidebar */
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 998;
        }
        
        .overlay.show {
            display: block;
        }
    </style>
</head>

<body>
<!-- Overlay for mobile sidebar -->
<div class="overlay" id="overlay"></div>

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
                    <a class="nav-link active" href="dashboard.php">
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
                
            </ul>
        </div>

        <div class="sidebar-menu-section">
            <div class="sidebar-menu-section-title">Notifications</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="notifications.php">
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
            <button class="navbar-tool-button d-lg-none me-2" id="mobileMenuToggle">
                <i class="bi bi-list"></i>
            </button>
            <h1 class="page-title">Dashboard</h1>
        </div>
        <div class="navbar-tools">
            <button class="navbar-tool-button" id="darkModeToggleTop">
                <i class="bi bi-moon-fill"></i>
            </button>
            <button class="navbar-tool-button" id="notificationsToggle">
                <i class="bi bi-bell"></i>
                <?php if ($unreadCount > 0): ?>
                    <span class="notification-badge"><?php echo $unreadCount; ?></span>
                <?php endif; ?>
            </button>
            <div class="user-profile" id="userProfileToggle">
                <?php if (!empty($userPhoto) && file_exists($userPhoto)): ?>
                    <img src="<?php echo htmlspecialchars($userPhoto); ?>" alt="Profile" class="user-avatar" style="object-fit: cover;">
                <?php else: ?>
                    <div class="user-avatar">
                        <?php
                        $initial = isset($userDetails['first_name']) ? strtoupper(substr($userDetails['first_name'], 0, 1)) : strtoupper(substr($userName, 0, 1));
                        echo $initial;
                        ?>
                    </div>
                <?php endif; ?>
                <div class="user-info d-none d-md-block">
                    <div class="user-name">
                        <?php
                        // Use full name if available, otherwise username
                        if (isset($userDetails['first_name']) && isset($userDetails['last_name'])) {
                            echo htmlspecialchars($userDetails['first_name'] . ' ' . $userDetails['last_name']);
                        } else {
                            echo htmlspecialchars($userName);
                        }
                        ?>
                    </div>
                    <div class="user-role"><?php echo htmlspecialchars($userType); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications Dropdown -->
    <div class="app-dropdown" id="notificationsDropdown">
        <div class="app-dropdown-header">
            <h6 class="app-dropdown-title">Notifications</h6>
            <button class="btn btn-sm btn-link p-0" id="markAllReadBtn">Mark all as read</button>
        </div>
        <div class="app-dropdown-body">
            <?php if (isset($notificationsResult) && $notificationsResult && $notificationsResult->num_rows > 0): ?>
                <?php while ($notification = $notificationsResult->fetch_assoc()): ?>
                    <?php
                    $iconClass = getNotificationIcon($notification['type']);
                    $colorClass = getNotificationColor($notification['type'], $notification['message'] ?? '');
                    $timeAgo = getTimeAgo($notification['created_at']);
                    ?>
                    <div class="recent-item <?php echo $notification['is_read'] ? 'read' : ''; ?>">
                        <div class="recent-item-icon <?php echo $colorClass; ?>">
                            <i class="bi <?php echo $iconClass; ?>"></i>
                        </div>
                        <div class="recent-item-content">
                            <span class="recent-item-title"><?php echo htmlspecialchars($notification['title']); ?></span>
                            <span class="recent-item-info"><?php echo htmlspecialchars($notification['message']); ?></span>
                        </div>
                        <div class="recent-item-date">
                            <?php echo $timeAgo; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="p-3 text-center">
                    <div class="text-muted">No notifications</div>
                </div>
            <?php endif; ?>
        </div>
        <div class="app-dropdown-footer">
            <a href="notifications.php" class="btn btn-outline-primary btn-sm" id="viewAllNotificationsBtn">View All Notifications</a>
        </div>
    </div>

    <!-- User Profile Dropdown -->
    <div class="app-dropdown" id="userDropdown">
        <div class="app-dropdown-header">
            <h6 class="app-dropdown-title">My Profile</h6>
        </div>
        <div class="app-dropdown-body">
            <?php if (isset($userDetails)): ?>
                <div class="d-flex align-items-center mb-3">
                    <?php if (!empty($userPhoto) && file_exists($userPhoto)): ?>
                        <img src="<?php echo htmlspecialchars($userPhoto); ?>" alt="Profile" class="me-3" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover;">
                    <?php else: ?>
                        <div class="user-avatar me-3" style="width: 60px; height: 60px; font-size: 1.5rem;">
                            <?php echo strtoupper(substr($userDetails['first_name'] ?? $userName, 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h6 class="mb-1"><?php echo htmlspecialchars(($userDetails['first_name'] ?? '') . ' ' . ($userDetails['last_name'] ?? '')); ?></h6>
                        <div class="text-muted small"><?php echo htmlspecialchars($userDetails['email'] ?? ''); ?></div>
                        <div class="text-muted small"><?php echo htmlspecialchars($userDetails['contact_number'] ?? ''); ?></div>
                    </div>
                </div>
            <?php endif; ?>
            <ul class="list-group list-group-flush">
                <li class="list-group-item bg-transparent">
                    <a href="profile_edit.php" class="text-decoration-none text-inherit" id="editProfileBtn">
                        <i class="bi bi-person-circle me-2"></i> Edit Profile
                    </a>
                </li>
                <li class="list-group-item bg-transparent">
                    <a href="settings.php" class="text-decoration-none text-inherit">
                        <i class="bi bi-gear me-2"></i> Settings
                    </a>
                </li>
                <li class="list-group-item bg-transparent">
                    <a href="#" class="text-decoration-none text-inherit">
                        <i class="bi bi-question-circle me-2"></i> Help Center
                    </a>
                </li>
            </ul>
        </div>
        <div class="app-dropdown-footer">
            <a href="logout.php" class="btn btn-danger btn-sm w-100">
                <i class="bi bi-box-arrow-right me-2"></i> Logout
            </a>
        </div>
    </div>

    <!-- Main Dashboard Content -->
    <div class="row">
        <!-- Stats Cards -->
        <div class="col-md-6 col-lg-3">
            <div class="app-card">
                <div class="stats-card">
                    <div class="stats-icon primary">
                        <i class="bi bi-box"></i>
                    </div>
                    <div class="stats-data">
                        <div class="stats-value count-up" data-count="<?php echo $totalProducts; ?>">0</div>
                        <div class="stats-label">Total Products</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="app-card">
                <div class="stats-card">
                    <div class="stats-icon info">
                        <i class="bi bi-tags"></i>
                    </div>
                    <div class="stats-data">
                        <div class="stats-value count-up" data-count="<?php echo $totalCategories; ?>">0</div>
                        <div class="stats-label">Total Categories</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="app-card">
                <div class="stats-card">
                    <div class="stats-icon success">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stats-data">
                        <div class="stats-value count-up" data-count="<?php echo $totalUsers; ?>">0</div>
                        <div class="stats-label">Total Users</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="app-card">
                <div class="stats-card">
                    <div class="stats-icon warning">
                        <i class="bi bi-heart"></i>
                    </div>
                    <div class="stats-data">
                        <div class="stats-value count-up" data-count="<?php
                            $totalHealthProducts = $healthDistribution['Green'] + $healthDistribution['Amber'] + $healthDistribution['Red'];
                            $healthPercentage = $totalHealthProducts > 0 ?
                                round(($healthDistribution['Green'] / $totalHealthProducts) * 100) : 0;
                            echo $healthPercentage;
                            ?>">0</div>
                        <div class="stats-label">Health Rating %</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Charts -->
        <div class="col-lg-8">
            <div class="app-card">
                <div class="app-card-header">
                    <h5 class="app-card-title">Products Overview</h5>
                    <div class="app-card-toolbar">
                        <select class="form-select form-select-sm" id="chartRangeSelector">
                            <option value="weekly">Weekly</option>
                            <option value="monthly" selected>Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                </div>
                <div class="app-card-body">
                    <div class="chart-container">
                        <canvas id="productOverviewChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="app-card">
                <div class="app-card-header">
                    <h5 class="app-card-title">Health Distribution</h5>
                </div>
                <div class="app-card-body">
                    <div class="chart-container">
                        <canvas id="healthDistributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Products -->
        <div class="col-lg-6">
            <div class="app-card">
                <div class="app-card-header">
                    <h5 class="app-card-title">Recent Products</h5>
                    <div class="app-card-toolbar">
                        <a href="products_management.php" class="btn btn-outline-primary btn-sm">View All</a>
                    </div>
                </div>
                <div class="app-card-body">
                    <?php if ($recentProducts && $recentProducts->num_rows > 0): ?>
                        <?php while ($product = $recentProducts->fetch_assoc()): ?>
                            <div class="recent-item">
                                <div class="recent-item-icon <?php echo strtolower($product['healthy_option']); ?>">
                                    <i class="bi bi-box"></i>
                                </div>
                                <div class="recent-item-content">
                                    <span class="recent-item-title"><?php echo htmlspecialchars($product['product_name']); ?></span>
                                    <span class="recent-item-info">Health Rating: <?php echo htmlspecialchars($product['healthy_option']); ?></span>
                                </div>
                                <div class="recent-item-date">
                                    <?php echo $product['date_created']; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-3 text-center">
                            <div class="text-muted">No recent products found</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Project Progress -->
        <div class="col-lg-6">
            <div class="app-card">
                <div class="app-card-header">
                    <h5 class="app-card-title">Project Progress</h5>
                </div>
                <div class="app-card-body">
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Product Catalog Update</span>
                            <span>75%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 75%" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Nutritional Analysis</span>
                            <span>45%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-info" role="progressbar" style="width: 45%" aria-valuenow="45" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <span>User Database Migration</span>
                            <span>90%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: 90%" aria-valuenow="90" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Recipe Documentation</span>
                            <span>30%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-danger" role="progressbar" style="width: 30%" aria-valuenow="30" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Mobile App Development</span>
                            <span>60%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: 60%" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Quick Actions -->
        <div class="col-12">
            <div class="app-card">
                <div class="app-card-header">
                    <h5 class="app-card-title">Quick Actions</h5>
                </div>
                <div class="app-card-body">
                    <div class="row justify-content-center">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="product.php" class="btn btn-outline-primary btn-lg w-100 h-100 d-flex flex-column align-items-center justify-content-center p-4">
                                <i class="bi bi-plus-circle mb-2" style="font-size: 2rem;"></i>
                                <span>Add Product</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="categories_groups.php?tab=categories" class="btn btn-outline-info btn-lg w-100 h-100 d-flex flex-column align-items-center justify-content-center p-4">
                                <i class="bi bi-tags mb-2" style="font-size: 2rem;"></i>
                                <span>Manage Categories</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="add_user.php" class="btn btn-outline-success btn-lg w-100 h-100 d-flex flex-column align-items-center justify-content-center p-4">
                                <i class="bi bi-person-plus mb-2" style="font-size: 2rem;"></i>
                                <span>Add User</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="reports.php" class="btn btn-outline-warning btn-lg w-100 h-100 d-flex flex-column align-items-center justify-content-center p-4">
                                <i class="bi bi-file-earmark-text mb-2" style="font-size: 2rem;"></i>
                                <span>Generate Report</span>
                            </a>
                        </div>
                    </div>
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

<!-- Toast Container for notifications -->
<div class="toast-container"></div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // DOM Elements
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const overlay = document.getElementById('overlay');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const darkModeToggle = document.getElementById('darkModeToggle');
    const darkModeToggleTop = document.getElementById('darkModeToggleTop');
    const notificationsToggle = document.getElementById('notificationsToggle');
    const userProfileToggle = document.getElementById('userProfileToggle');
    const notificationsDropdown = document.getElementById('notificationsDropdown');
    const userDropdown = document.getElementById('userDropdown');
    const chartRangeSelector = document.getElementById('chartRangeSelector');
    const markAllReadBtn = document.getElementById('markAllReadBtn');

    // Sidebar Toggle for mobile - Fix for collapse/expand functionality
    document.addEventListener('DOMContentLoaded', () => {
        const isSmallScreen = window.innerWidth < 992;

        if (isSmallScreen) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        }
        
        // Add horizontal overflow prevention
        document.body.style.overflowX = 'hidden';
        
        // Fix icon visibility
        if (sidebar.classList.contains('collapsed')) {
            document.querySelectorAll('.nav-link i').forEach(icon => {
                icon.style.display = 'inline-block';
            });
        }
        
        // Initialize count-up animation
        animateCountUp();
    });

    // Sidebar Toggle with improved handling
    sidebarToggle.addEventListener('click', (e) => {
        e.preventDefault();
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
        
        // Fix icon visibility and scrolling issues
        if (sidebar.classList.contains('collapsed')) {
            setTimeout(() => {
                document.querySelectorAll('.nav-link i').forEach(icon => {
                    icon.style.display = 'inline-block';
                });
            }, 300);
        }
    });

    // Mobile Menu Toggle
    mobileMenuToggle.addEventListener('click', () => {
        sidebar.classList.toggle('mobile-show');
        overlay.classList.toggle('show');
    });
    
    // Close mobile menu when clicking on overlay
    overlay.addEventListener('click', () => {
        sidebar.classList.remove('mobile-show');
        overlay.classList.remove('show');
    });

    // Dark Mode Toggle
    function toggleDarkMode() {
        const html = document.documentElement;
        const currentTheme = html.getAttribute('data-bs-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

        html.setAttribute('data-bs-theme', newTheme);

        // Update icons
        const darkModeIcon = darkModeToggle.querySelector('i');
        const darkModeIconTop = darkModeToggleTop.querySelector('i');

        if (newTheme === 'dark') {
            darkModeIcon.classList.replace('bi-moon', 'bi-sun');
            darkModeIconTop.classList.replace('bi-moon-fill', 'bi-sun-fill');
            darkModeToggle.querySelector('.footer-text').textContent = 'Light Mode';
        } else {
            darkModeIcon.classList.replace('bi-sun', 'bi-moon');
            darkModeIconTop.classList.replace('bi-sun-fill', 'bi-moon-fill');
            darkModeToggle.querySelector('.footer-text').textContent = 'Dark Mode';
        }

        // Save preference to localStorage
        localStorage.setItem('theme', newTheme);
    }

    darkModeToggle.addEventListener('click', toggleDarkMode);
    darkModeToggleTop.addEventListener('click', toggleDarkMode);

    // Check for saved theme preference
    document.addEventListener('DOMContentLoaded', () => {
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            document.documentElement.setAttribute('data-bs-theme', savedTheme);

            if (savedTheme === 'dark') {
                darkModeToggle.querySelector('i').classList.replace('bi-moon', 'bi-sun');
                darkModeToggleTop.querySelector('i').classList.replace('bi-moon-fill', 'bi-sun-fill');
                darkModeToggle.querySelector('.footer-text').textContent = 'Light Mode';
            }
        }
    });

    // Notifications dropdown
    notificationsToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        notificationsDropdown.classList.toggle('show');
        userDropdown.classList.remove('show');
    });

    // User profile dropdown
    userProfileToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        userDropdown.classList.toggle('show');
        notificationsDropdown.classList.remove('show');
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', (e) => {
        if (!notificationsDropdown.contains(e.target) && !notificationsToggle.contains(e.target)) {
            notificationsDropdown.classList.remove('show');
        }

        if (!userDropdown.contains(e.target) && !userProfileToggle.contains(e.target)) {
            userDropdown.classList.remove('show');
        }
    });

    // Mark all notifications as read
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.preventDefault();

            // Send AJAX request
            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=mark_all_read'
            })
                .then(response => {
                    // First check if the response is OK
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.success) {
                        // Mark all as read visually
                        document.querySelectorAll('.recent-item:not(.read)').forEach(item => {
                            item.classList.add('read');
                        });

                        // Hide notification badge
                        const badge = document.querySelector('.notification-badge');
                        if (badge) {
                            badge.style.display = 'none';
                        }

                        // Hide sidebar badge
                        const sidebarBadge = document.querySelector('.sidebar .badge');
                        if (sidebarBadge) {
                            sidebarBadge.style.display = 'none';
                        }

                        // Show a toast notification
                        showToast('All notifications marked as read', 'success');
                    } else {
                        showToast(data && data.message ? data.message : 'Failed to mark notifications as read', 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Still update UI for better UX
                    document.querySelectorAll('.recent-item:not(.read)').forEach(item => {
                        item.classList.add('read');
                    });
                    
                    // Hide notification badges anyway
                    document.querySelectorAll('.notification-badge, .sidebar .badge').forEach(badge => {
                        badge.style.display = 'none';
                    });
                    
                    showToast('Notifications marked as read', 'success');
                });
        });
    }

    // Count-up animation for stats
    function animateCountUp() {
        const countElements = document.querySelectorAll('.count-up');
        
        countElements.forEach(el => {
            const target = parseInt(el.getAttribute('data-count')) || 0;
            const duration = 1500; // animation duration in ms
            const frameDuration = 1000/60; // 60fps
            const totalFrames = Math.round(duration / frameDuration);
            const easeOutQuad = t => t * (2 - t);
            
            let frame = 0;
            let currentValue = 0;
            
            const counter = setInterval(() => {
                frame++;
                const progress = easeOutQuad(frame / totalFrames);
                currentValue = Math.round(target * progress);
                
                if (frame === totalFrames) {
                    clearInterval(counter);
                    el.textContent = target;
                } else {
                    el.textContent = currentValue;
                }
            }, frameDuration);
        });
    }

    // Define fallback data for charts
    const defaultMonthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const defaultTotalProductsData = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
    const defaultHealthyProductsData = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];

    // Initialize chart data with fallbacks
    let monthLabels, totalProductsData, healthyProductsData;

    try {
        <?php if (isset($monthlyProductData) && is_array($monthlyProductData)): ?>
        monthLabels = <?php echo json_encode(array_column($monthlyProductData, 'label')); ?>;
        totalProductsData = <?php echo json_encode(array_column($monthlyProductData, 'total')); ?>;
        healthyProductsData = <?php echo json_encode(array_column($monthlyProductData, 'healthy')); ?>;
        <?php else: ?>
        monthLabels = defaultMonthLabels;
        totalProductsData = defaultTotalProductsData;
        healthyProductsData = defaultHealthyProductsData;
        <?php endif; ?>
    } catch (e) {
        console.warn("Error loading chart data, using defaults", e);
        monthLabels = defaultMonthLabels;
        totalProductsData = defaultTotalProductsData;
        healthyProductsData = defaultHealthyProductsData;
    }

    // Products Overview Chart - Using actual data with fallbacks
    const productOverviewChart = new Chart(
        document.getElementById('productOverviewChart'),
        {
            type: 'line',
            data: {
                labels: monthLabels,
                datasets: [
                    {
                        label: 'Total Products',
                        data: totalProductsData,
                        borderColor: '#4CAF50',
                        backgroundColor: 'rgba(76, 175, 80, 0.1)',
                        tension: 0.4,
                        borderWidth: 2,
                        fill: true
                    },
                    {
                        label: 'Healthy Products',
                        data: healthyProductsData,
                        borderColor: '#2196F3',
                        backgroundColor: 'rgba(33, 150, 243, 0.1)',
                        tension: 0.4,
                        borderWidth: 2,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        }
    );

    // Health Distribution Chart - Using actual data with fallbacks
    const healthDistributionChart = new Chart(
        document.getElementById('healthDistributionChart'),
        {
            type: 'doughnut',
            data: {
                labels: ['Green (Healthy)', 'Amber (Moderate)', 'Red (Less Healthy)'],
                datasets: [
                    {
                        data: [
                            <?php echo isset($healthDistribution) && isset($healthDistribution['Green']) ? $healthDistribution['Green'] : 0; ?>,
                            <?php echo isset($healthDistribution) && isset($healthDistribution['Amber']) ? $healthDistribution['Amber'] : 0; ?>,
                            <?php echo isset($healthDistribution) && isset($healthDistribution['Red']) ? $healthDistribution['Red'] : 0; ?>
                        ],
                        backgroundColor: ['#28a745', '#ffc107', '#dc3545'],
                        borderColor: ['#28a745', '#ffc107', '#dc3545'],
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                cutout: '70%'
            }
        }
    );

    // Chart Range Selector with animated transition
    chartRangeSelector.addEventListener('change', (e) => {
        const range = e.target.value;

        // For now, reset to the default data we already have (monthly)
        if (range === 'monthly') {
            productOverviewChart.data.labels = monthLabels;
            productOverviewChart.data.datasets[0].data = totalProductsData;
            productOverviewChart.data.datasets[1].data = healthyProductsData;
            productOverviewChart.update();
        } else {
            // In a real implementation, we would fetch data via AJAX here
            // For now, let's simulate weekly and yearly data based on monthly
            if (range === 'weekly') {
                // Simulate weekly data (last 7 days)
                const weeklyLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                const weeklyTotal = [0, 0, 0, 0, 0, 0, 0];
                const weeklyHealthy = [0, 0, 0, 0, 0, 0, 0];

                // Generate some sample data based on the most recent month
                const latestMonthTotal = totalProductsData[totalProductsData.length - 1] || 0;
                const latestMonthHealthy = healthyProductsData[healthyProductsData.length - 1] || 0;

                for (let i = 0; i < 7; i++) {
                    // Create some random variation around the monthly average
                    const factor = 0.85 + (Math.random() * 0.3); // Between 0.85 and 1.15
                    weeklyTotal[i] = Math.round((latestMonthTotal / 30) * 7 * factor);
                    weeklyHealthy[i] = Math.round((latestMonthHealthy / 30) * 7 * factor);
                }

                productOverviewChart.data.labels = weeklyLabels;
                productOverviewChart.data.datasets[0].data = weeklyTotal;
                productOverviewChart.data.datasets[1].data = weeklyHealthy;

            } else if (range === 'yearly') {
                // Simulate yearly data (last 4 years)
                const yearlyLabels = ['2022', '2023', '2024', '2025'];
                const yearlyTotal = [0, 0, 0, 0];
                const yearlyHealthy = [0, 0, 0, 0];

                // Aggregate monthly data into yearly data
                const monthsPerYear = Math.floor(totalProductsData.length / 4);
                for (let i = 0; i < totalProductsData.length && i < 12; i++) {
                    const yearIndex = Math.min(3, Math.floor(i / monthsPerYear));
                    yearlyTotal[yearIndex] += totalProductsData[i] || 0;
                    yearlyHealthy[yearIndex] += healthyProductsData[i] || 0;
                }

                productOverviewChart.data.labels = yearlyLabels;
                productOverviewChart.data.datasets[0].data = yearlyTotal;
                productOverviewChart.data.datasets[1].data = yearlyHealthy;
            }

            productOverviewChart.update();
        }
    });

    // Helper function to show toast notifications
    function showToast(message, type = 'success') {
        // Create the toast container if it doesn't exist
        let toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            document.body.appendChild(toastContainer);
        }

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
</script>
</body>
</html>