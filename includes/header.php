<?php
// Common header file that should be included in all dashboard pages
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Zouki Food Insights'; ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <!-- Additional page-specific CSS -->
    <?php if (isset($page_specific_css)): ?>
    <?php echo $page_specific_css; ?>
    <?php endif; ?>

    <!-- Additional scripts based on page needs -->
    <?php if (isset($use_chart_js) && $use_chart_js): ?>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>

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

            .app-card-body {
                padding: 16px;
            }
        }
        
        /* Add any additional custom styles here */
        <?php if (isset($additional_css)): ?>
        <?php echo $additional_css; ?>
        <?php endif; ?>
    </style>
<link rel="stylesheet" href="css/dashboard-enhanced.css">
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
                    <a class="nav-link <?php echo $active_page == 'dashboard' ? 'active' : ''; ?>" href="dashboard.php">
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
                    <a class="nav-link <?php echo $active_page == 'products' ? 'active' : ''; ?>" href="products_management.php">
                        <i class="bi bi-box"></i>
                        <span>Products</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $active_page == 'categories' ? 'active' : ''; ?>" href="categories_groups.php?tab=categories">
                        <i class="bi bi-tags"></i>
                        <span>Categories</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $active_page == 'groups' ? 'active' : ''; ?>" href="categories_groups.php?tab=groups">
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
                    <a class="nav-link <?php echo $active_page == 'users' ? 'active' : ''; ?>" href="users.php">
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
                    <a class="nav-link <?php echo $active_page == 'notifications' ? 'active' : ''; ?>" href="notifications.php">
                        <i class="bi bi-bell"></i>
                        <span>Notifications</span>
                        <?php if (isset($unreadCount) && $unreadCount > 0): ?>
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
                    <a class="nav-link <?php echo $active_page == 'settings' ? 'active' : ''; ?>" href="settings.php">
                        <i class="bi bi-gear"></i>
                        <span>Settings</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $active_page == 'profile' ? 'active' : ''; ?>" href="profile_edit.php">
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
    <?php if (isset($errorMessage) && $errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($errorMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($successMessage) && $successMessage): ?>
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
            <h1 class="page-title"><?php echo $page_title ?? 'Dashboard'; ?></h1>
        </div>
        <div class="navbar-tools">
            <button class="navbar-tool-button" id="darkModeToggleTop">
                <i class="bi bi-moon-fill"></i>
            </button>
            <button class="navbar-tool-button" id="notificationsToggle">
                <i class="bi bi-bell"></i>
                <?php if (isset($unreadCount) && $unreadCount > 0): ?>
                    <span class="notification-badge"><?php echo $unreadCount; ?></span>
                <?php endif; ?>
            </button>
            <div class="user-profile" id="userProfileToggle">
                <?php 
                // User avatar logic
                $userPhoto = $userPhoto ?? null;
                $userName = $_SESSION['username'] ?? 'User';
                $userType = $_SESSION['uType'] ?? 'Guest';
                
                $initial = isset($userDetails) && isset($userDetails['first_name']) ? 
                           strtoupper(substr($userDetails['first_name'], 0, 1)) : 
                           strtoupper(substr($userName, 0, 1));
                ?>
                
                <?php if (!empty($userPhoto) && file_exists($userPhoto)): ?>
                    <img src="<?php echo htmlspecialchars($userPhoto); ?>" alt="Profile" class="user-avatar" style="object-fit: cover;">
                <?php else: ?>
                    <div class="user-avatar">
                        <?php echo $initial; ?>
                    </div>
                <?php endif; ?>
                <div class="user-info d-none d-md-block">
                    <div class="user-name">
                        <?php
                        // Use full name if available, otherwise username
                        if (isset($userDetails) && isset($userDetails['first_name']) && isset($userDetails['last_name'])) {
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
                    $iconClass = getNotificationIcon($notification['type'] ?? 'default');
                    $colorClass = getNotificationColor($notification['type'] ?? 'default', $notification['message'] ?? '');
                    $timeAgo = isset($notification['created_at']) ? getTimeAgo($notification['created_at']) : '';
                    ?>
                    <div class="recent-item <?php echo (isset($notification['is_read']) && $notification['is_read']) ? 'read' : ''; ?>">
                        <div class="recent-item-icon <?php echo $colorClass; ?>">
                            <i class="bi <?php echo $iconClass; ?>"></i>
                        </div>
                        <div class="recent-item-content">
                            <span class="recent-item-title"><?php echo htmlspecialchars($notification['title'] ?? 'Notification'); ?></span>
                            <span class="recent-item-info"><?php echo htmlspecialchars($notification['message'] ?? ''); ?></span>
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
                            <?php echo $initial; ?>
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
    
    <!-- Page content starts here -->