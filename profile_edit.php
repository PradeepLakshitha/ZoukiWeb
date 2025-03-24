<?php
require_once 'session_check.php';
check_session(); // Allow all authenticated users
include 'db_connection.php';

// Get logged-in user's information
$userId = $_SESSION['userID'] ?? 0;
$userName = $_SESSION['username'];
$userType = $_SESSION['uType'];

// Fetch user details
$userQuery = $conn->prepare("
    SELECT 
        first_name, 
        last_name, 
        email, 
        contact_number,
        DATE_FORMAT(created_at, '%d %b %Y') as join_date
    FROM z_user 
    WHERE userID = ?
");
$userQuery->bind_param("i", $userId);
$userQuery->execute();
$userResult = $userQuery->get_result();
$userDetails = $userResult->fetch_assoc();

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $contactNumber = trim($_POST['contact_number']);
    $currentPassword = trim($_POST['current_password']);
    $newPassword = trim($_POST['new_password']);
    $confirmPassword = trim($_POST['confirm_password']);

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Invalid email format";
    }
    // Verify current password
    else {
        $verifyQuery = $conn->prepare("SELECT password FROM z_user WHERE userID = ?");
        $verifyQuery->bind_param("i", $userId);
        $verifyQuery->execute();
        $verifyResult = $verifyQuery->get_result();
        $userData = $verifyResult->fetch_assoc();

        if (!password_verify($currentPassword, $userData['password'])) {
            $errorMessage = "Current password is incorrect";
        }
        // Check if passwords match for change
        elseif (!empty($newPassword) && $newPassword !== $confirmPassword) {
            $errorMessage = "New passwords do not match";
        }
        // All validation passed, update profile
        else {
            try {
                $conn->begin_transaction();

                // Check if email is already taken by another user
                $emailCheckQuery = $conn->prepare("SELECT userID FROM z_user WHERE email = ? AND userID != ?");
                $emailCheckQuery->bind_param("si", $email, $userId);
                $emailCheckQuery->execute();
                $emailCheck = $emailCheckQuery->get_result();

                if ($emailCheck->num_rows > 0) {
                    throw new Exception("Email is already in use by another account");
                }

                // Update user details
                if (!empty($newPassword)) {
                    // With password change
                    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                    $updateQuery = $conn->prepare("
                        UPDATE z_user 
                        SET first_name = ?, last_name = ?, email = ?, contact_number = ?, password = ? 
                        WHERE userID = ?
                    ");
                    $updateQuery->bind_param("sssssi", $firstName, $lastName, $email, $contactNumber, $hashedPassword, $userId);
                } else {
                    // Without password change
                    $updateQuery = $conn->prepare("
                        UPDATE z_user 
                        SET first_name = ?, last_name = ?, email = ?, contact_number = ? 
                        WHERE userID = ?
                    ");
                    $updateQuery->bind_param("ssssi", $firstName, $lastName, $email, $contactNumber, $userId);
                }

                if ($updateQuery->execute()) {
                    $conn->commit();

                    // Update session with new details
                    $_SESSION['first_name'] = $firstName;
                    $_SESSION['last_name'] = $lastName;

                    // Update user details variable to show on the form
                    $userDetails['first_name'] = $firstName;
                    $userDetails['last_name'] = $lastName;
                    $userDetails['email'] = $email;
                    $userDetails['contact_number'] = $contactNumber;

                    $successMessage = "Profile updated successfully";
                } else {
                    throw new Exception("Failed to update profile");
                }
            } catch (Exception $e) {
                $conn->rollback();
                $errorMessage = "Error: " . $e->getMessage();
            }
        }
    }
}

// Handle profile picture upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'upload_photo') {
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        $file = $_FILES['profile_photo'];

        // Check file type
        if (!in_array($file['type'], $allowedTypes)) {
            $errorMessage = "Invalid file type. Only JPG, PNG and GIF are allowed.";
        }
        // Check file size
        elseif ($file['size'] > $maxSize) {
            $errorMessage = "File is too large. Maximum size is 5MB.";
        }
        else {
            // Create upload directory if it doesn't exist
            $uploadDir = "uploads/profile_photos/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Generate a unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = "user_{$userId}_" . time() . "." . $extension;
            $targetPath = $uploadDir . $filename;

            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                // Update database with new profile photo path
                $updateQuery = $conn->prepare("UPDATE z_user SET profile_photo = ? WHERE userID = ?");
                $updateQuery->bind_param("si", $targetPath, $userId);

                if ($updateQuery->execute()) {
                    $successMessage = "Profile photo updated successfully";

                    // Update the user details
                    $userDetails['profile_photo'] = $targetPath;
                } else {
                    $errorMessage = "Failed to update profile photo in database";
                }
            } else {
                $errorMessage = "Failed to upload profile photo";
            }
        }
    } else {
        $errorMessage = "No file uploaded or an error occurred";
    }
}

// Check for session messages
$successMessage = $successMessage ?? ($_SESSION['success'] ?? '');
$errorMessage = $errorMessage ?? ($_SESSION['error'] ?? '');

// Clear session messages
if (isset($_SESSION['success'])) unset($_SESSION['success']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);

// Fetch profile photo if it exists in the database (might need to add this column)
$photoQuery = $conn->prepare("SELECT profile_photo FROM z_user WHERE userID = ?");
$photoQuery->bind_param("i", $userId);
$photoQuery->execute();
$photoResult = $photoQuery->get_result();
$photoData = $photoResult->fetch_assoc();

$profilePhoto = $photoData['profile_photo'] ?? '';
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Zouki Food Insights</title>

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

        /* Profile Styles */
        .profile-sidebar {
            text-align: center;
            padding: 20px;
        }

        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 20px;
            border: 4px solid var(--card-bg);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .profile-photo-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            border: 4px solid var(--card-bg);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            font-size: 3rem;
            color: #adb5bd;
        }

        .profile-photo-upload {
            margin-top: 10px;
        }

        .profile-info {
            margin-top: 20px;
        }

        .profile-info-item {
            margin-bottom: 10px;
            text-align: left;
        }

        .profile-info-label {
            font-weight: 600;
            color: var(--text-muted);
            display: block;
            font-size: 0.8rem;
            margin-bottom: 5px;
        }

        .profile-info-value {
            font-size: 0.9rem;
        }

        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 10px;
            top: 10px;
            z-index: 10;
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
                    <a class="nav-link" href="notifications.php">
                        <i class="bi bi-bell"></i>
                        <span>Notifications</span>
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
                    <a class="nav-link active" href="profile_edit.php">
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
            <button class="btn btn-link d-lg-none me-2" id="mobileMenuToggle">
                <i class="bi bi-list fs-4"></i>
            </button>
            <h1 class="page-title">My Profile</h1>
        </div>
    </div>

    <div class="row">
        <!-- Profile Sidebar -->
        <div class="col-md-4">
            <div class="app-card">
                <div class="profile-sidebar">
                    <?php if (!empty($profilePhoto) && file_exists($profilePhoto)): ?>
                        <img src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Profile Photo" class="profile-photo">
                    <?php else: ?>
                        <div class="profile-photo-placeholder">
                            <?php echo strtoupper(substr($userDetails['first_name'] ?? $userName, 0, 1)); ?>
                        </div>
                    <?php endif; ?>

                    <h5 class="mt-3"><?php echo htmlspecialchars(($userDetails['first_name'] ?? '') . ' ' . ($userDetails['last_name'] ?? '')); ?></h5>
                    <p class="text-muted mb-4"><?php echo htmlspecialchars($userType); ?></p>

                    <form action="profile_edit.php" method="POST" enctype="multipart/form-data" class="profile-photo-upload">
                        <input type="hidden" name="action" value="upload_photo">
                        <div class="mb-3">
                            <input type="file" class="form-control form-control-sm" id="profile_photo" name="profile_photo" accept="image/*">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Update Photo</button>
                    </form>

                    <div class="profile-info mt-4">
                        <div class="profile-info-item">
                            <span class="profile-info-label">Username</span>
                            <span class="profile-info-value"><?php echo htmlspecialchars($userName); ?></span>
                        </div>
                        <div class="profile-info-item">
                            <span class="profile-info-label">Email</span>
                            <span class="profile-info-value"><?php echo htmlspecialchars($userDetails['email'] ?? ''); ?></span>
                        </div>
                        <div class="profile-info-item">
                            <span class="profile-info-label">Contact</span>
                            <span class="profile-info-value"><?php echo htmlspecialchars($userDetails['contact_number'] ?? ''); ?></span>
                        </div>
                        <div class="profile-info-item">
                            <span class="profile-info-label">Joined</span>
                            <span class="profile-info-value"><?php echo htmlspecialchars($userDetails['join_date'] ?? ''); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Profile Form -->
        <div class="col-md-8">
            <div class="app-card">
                <div class="app-card-header">
                    <h5 class="app-card-title">Edit Profile</h5>
                </div>
                <div class="app-card-body">
                    <form action="profile_edit.php" method="POST" id="profileForm">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($userDetails['first_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($userDetails['last_name'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($userDetails['email'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="contact_number" class="form-label">Contact Number</label>
                                <input type="text" class="form-control" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($userDetails['contact_number'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password (required to save changes)</label>
                            <div class="position-relative">
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                <span class="password-toggle" onclick="togglePasswordVisibility('current_password')">
                                        <i class="bi bi-eye"></i>
                                    </span>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="new_password" class="form-label">New Password (leave blank to keep current)</label>
                                <div class="position-relative">
                                    <input type="password" class="form-control" id="new_password" name="new_password">
                                    <span class="password-toggle" onclick="togglePasswordVisibility('new_password')">
                                            <i class="bi bi-eye"></i>
                                        </span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <div class="position-relative">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                    <span class="password-toggle" onclick="togglePasswordVisibility('confirm_password')">
                                            <i class="bi bi-eye"></i>
                                        </span>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                <i class="bi bi-arrow-counterclockwise"></i> Reset
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Account Security -->
            <div class="app-card">
                <div class="app-card-header">
                    <h5 class="app-card-title">Account Security</h5>
                </div>
                <div class="app-card-body">
                    <div class="mb-4">
                        <h6 class="mb-3">Two-Factor Authentication</h6>
                        <p class="text-muted">Add an extra layer of security to your account by enabling two-factor authentication.</p>
                        <button class="btn btn-outline-primary" id="enable2fa">
                            <i class="bi bi-shield-lock"></i> Enable 2FA
                        </button>
                    </div>

                    <div class="mb-4">
                        <h6 class="mb-3">Login Activity</h6>
                        <p class="text-muted">Review your recent login activity to ensure it was you.</p>
                        <button class="btn btn-outline-primary" id="viewLoginActivity">
                            <i class="bi bi-clock-history"></i> View Activity
                        </button>
                    </div>

                    <div>
                        <h6 class="mb-3">Delete Account</h6>
                        <p class="text-muted">Permanently delete your account and all associated data.</p>
                        <button class="btn btn-danger" id="deleteAccount">
                            <i class="bi bi-trash"></i> Delete Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
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
    const profileForm = document.getElementById('profileForm');
    const enable2faBtn = document.getElementById('enable2fa');
    const viewLoginActivityBtn = document.getElementById('viewLoginActivity');
    const deleteAccountBtn = document.getElementById('deleteAccount');
    const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
    const confirmModalBtn = document.getElementById('confirmModalBtn');

    // Initialize actions
    let currentAction = '';

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

    // Toggle Password Visibility
    function togglePasswordVisibility(inputId) {
        const input = document.getElementById(inputId);
        const icon = input.nextElementSibling.querySelector('i');

        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    }

    // Reset Form
    function resetForm() {
        profileForm.reset();
        showToast('Form has been reset', 'info');
    }

    // Validate Profile Form
    profileForm.addEventListener('submit', function(e) {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;

        if (newPassword !== confirmPassword) {
            e.preventDefault();
            showToast('New passwords do not match', 'danger');
            return false;
        }

        return true;
    });

    // Enable 2FA Button
    enable2faBtn.addEventListener('click', function() {
        showToast('Two-factor authentication feature coming soon', 'info');
    });

    // View Login Activity Button
    viewLoginActivityBtn.addEventListener('click', function() {
        showToast('Login activity tracking coming soon', 'info');
    });

    // Delete Account Button
    deleteAccountBtn.addEventListener('click', function() {
        // Show confirmation
        document.getElementById('confirmationModalTitle').textContent = 'Delete Account';
        document.getElementById('confirmationModalBody').innerHTML = `
                <p>Are you sure you want to delete your account? This action cannot be undone and will permanently remove all your data.</p>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    All your data, settings, and history will be permanently deleted.
                </div>
                <div class="mb-3">
                    <label for="confirmDeletePassword" class="form-label">Enter your password to confirm</label>
                    <input type="password" class="form-control" id="confirmDeletePassword" required>
                </div>
            `;
        document.getElementById('confirmModalBtn').className = 'btn btn-danger';
        document.getElementById('confirmModalBtn').textContent = 'Delete Account';

        currentAction = 'delete_account';
        confirmationModal.show();
    });

    // Confirmation Modal Button
    confirmModalBtn.addEventListener('click', function() {
        if (currentAction === 'delete_account') {
            const password = document.getElementById('confirmDeletePassword').value;

            if (!password) {
                showToast('Please enter your password to confirm', 'warning');
                return;
            }

            // Here you would normally send an AJAX request to delete the account
            // For demo, we'll just show a toast
            showToast('Account deletion feature coming soon', 'info');
            confirmationModal.hide();
        }
    });

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
</script>
</body>
</html>