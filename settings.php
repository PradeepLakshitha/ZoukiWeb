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
$activeTab = 'settings';

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
    <title>Settings - ZOUKI</title>
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

        /* Settings Card */
        .settings-card {
            transition: all 0.3s ease;
            cursor: pointer;
            height: 100%;
        }

        .settings-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .settings-card .card-body {
            text-align: center;
            padding: 30px 20px;
        }

        .settings-card i {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .settings-card h5 {
            margin-bottom: 10px;
        }

        .settings-card p {
            color: #6c757d;
            margin-bottom: 0;
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
        <h4 class="mb-0">System Settings</h4>
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

        <!-- Settings Categories -->
        <div class="row">
            <!-- System Settings -->
            <div class="col-md-4 mb-4">
                <div class="card settings-card" onclick="location.href='#';">
                    <div class="card-body">
                        <i class="bi bi-gear-fill"></i>
                        <h5>General Settings</h5>
                        <p>Configure system-wide settings and preferences</p>
                    </div>
                </div>
            </div>

            <!-- User Permissions -->
            <div class="col-md-4 mb-4">
                <div class="card settings-card" onclick="location.href='#';">
                    <div class="card-body">
                        <i class="bi bi-shield-lock"></i>
                        <h5>User Permissions</h5>
                        <p>Manage user roles and access permissions</p>
                    </div>
                </div>
            </div>

            <!-- Backup & Restore -->
            <div class="col-md-4 mb-4">
                <div class="card settings-card" onclick="location.href='#';">
                    <div class="card-body">
                        <i class="bi bi-archive"></i>
                        <h5>Backup & Restore</h5>
                        <p>Backup data and restore from previous backups</p>
                    </div>
                </div>
            </div>

            <!-- Import/Export Products -->
            <div class="col-md-4 mb-4">
                <div class="card settings-card" onclick="location.href='import_export_setup.php';">
                    <div class="card-body">
                        <i class="bi bi-file-earmark-arrow-up-down"></i>
                        <h5>Import/Export Products</h5>
                        <p>Bulk import or export products using CSV files</p>
                    </div>
                </div>
            </div>

            <!-- Bulk Image Upload -->
            <div class="col-md-4 mb-4">
                <div class="card settings-card" onclick="location.href='bulk_image_upload.php';">
                    <div class="card-body">
                        <i class="bi bi-images"></i>
                        <h5>Bulk Image Upload</h5>
                        <p>Upload multiple product images at once</p>
                    </div>
                </div>
            </div>

            <!-- Email Configuration -->
            <div class="col-md-4 mb-4">
                <div class="card settings-card" onclick="location.href='#';">
                    <div class="card-body">
                        <i class="bi bi-envelope"></i>
                        <h5>Email Settings</h5>
                        <p>Configure email notifications and templates</p>
                    </div>
                </div>
            </div>

            <!-- System Logs -->
            <div class="col-md-4 mb-4">
                <div class="card settings-card" onclick="location.href='#';">
                    <div class="card-body">
                        <i class="bi bi-journal-text"></i>
                        <h5>System Logs</h5>
                        <p>View system logs and activity history</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Information -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">System Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="fw-bold">PHP Version:</label>
                            <span class="ms-2"><?php echo PHP_VERSION; ?></span>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold">Server Software:</label>
                            <span class="ms-2"><?php echo $_SERVER['SERVER_SOFTWARE']; ?></span>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold">Database:</label>
                            <span class="ms-2">MySQL</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="fw-bold">Last Login:</label>
                            <span class="ms-2"><?php echo date('Y-m-d H:i:s'); ?></span>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold">Current User:</label>
                            <span class="ms-2"><?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo htmlspecialchars($_SESSION['uType']); ?>)</span>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold">System Time:</label>
                            <span class="ms-2"><?php echo date('Y-m-d H:i:s'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
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