<?php
require_once 'session_check.php';
check_session(['Admin']);
include 'db_connection.php';

// Ensure only Admin can access
if (!isset($_SESSION['username']) || $_SESSION['uType'] !== 'Admin') {
    $_SESSION['error'] = "Access denied! Only Administrators can access settings.";
    header("Location: dashboard.php");
    exit();
}

// Initialize variables
$successMessage = '';
$errorMessage = '';

// Set active tab for navigation
$activeTab = 'settings';

// Setting categories
$activeSection = isset($_GET['section']) ? $_GET['section'] : 'general';

// Handle settings update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action']) && $_POST['action'] === 'update_settings') {
        $section = $_POST['section'];

        // Create a transaction to ensure all updates succeed or fail together
        try {
            $conn->begin_transaction();

            // Process general settings
            if ($section === 'general') {
                $site_name = trim($_POST['site_name']);
                $site_description = trim($_POST['site_description']);
                $admin_email = trim($_POST['admin_email']);
                $timezone = trim($_POST['timezone']);

                // Validate inputs
                if (empty($site_name)) {
                    throw new Exception("Site name cannot be empty");
                }

                if (!empty($admin_email) && !filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Invalid admin email format");
                }

                // Update settings in database
                $stmt = $conn->prepare("UPDATE z_settings SET value = ? WHERE setting_key = 'site_name'");
                $stmt->bind_param("s", $site_name);
                $stmt->execute();

                $stmt = $conn->prepare("UPDATE z_settings SET value = ? WHERE setting_key = 'site_description'");
                $stmt->bind_param("s", $site_description);
                $stmt->execute();

                $stmt = $conn->prepare("UPDATE z_settings SET value = ? WHERE setting_key = 'admin_email'");
                $stmt->bind_param("s", $admin_email);
                $stmt->execute();

                $stmt = $conn->prepare("UPDATE z_settings SET value = ? WHERE setting_key = 'timezone'");
                $stmt->bind_param("s", $timezone);
                $stmt->execute();

                $successMessage = "General settings updated successfully!";
            }

            // Process email settings
            else if ($section === 'email') {
                $smtp_host = trim($_POST['smtp_host']);
                $smtp_port = (int)trim($_POST['smtp_port']);
                $smtp_username = trim($_POST['smtp_username']);
                $smtp_password = trim($_POST['smtp_password']);
                $smtp_encryption = trim($_POST['smtp_encryption']);
                $from_email = trim($_POST['from_email']);
                $from_name = trim($_POST['from_name']);

                // Validate inputs
                if (!empty($from_email) && !filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Invalid from email format");
                }

                if (!empty($smtp_port) && ($smtp_port < 1 || $smtp_port > 65535)) {
                    throw new Exception("SMTP port must be between 1 and 65535");
                }

                // Update settings in database
                $stmt = $conn->prepare("UPDATE z_settings SET value = ? WHERE setting_key = 'smtp_host'");
                $stmt->bind_param("s", $smtp_host);
                $stmt->execute();

                $stmt = $conn->prepare("UPDATE z_settings SET value = ? WHERE setting_key = 'smtp_port'");
                $stmt->bind_param("s", $smtp_port);
                $stmt->execute();

                $stmt = $conn->prepare("UPDATE z_settings SET value = ? WHERE setting_key = 'smtp_username'");
                $stmt->bind_param("s", $smtp_username);
                $stmt->execute();

                // Only update password if provided
                if (!empty($smtp_password)) {
                    $stmt = $conn->prepare("UPDATE z_settings SET value = ? WHERE setting_key = 'smtp_password'");
                    $stmt->bind_param("s", $smtp_password);
                    $stmt->execute();
                }

                $stmt = $conn->prepare("UPDATE z_settings SET value = ? WHERE setting_key = 'smtp_encryption'");
                $stmt->bind_param("s", $smtp_encryption);
                $stmt->execute();

                $stmt = $conn->prepare("UPDATE z_settings SET value = ? WHERE setting_key = 'from_email'");
                $stmt->bind_param("s", $from_email);
                $stmt->execute();

                $stmt = $conn->prepare("UPDATE z_settings SET value = ? WHERE setting_key = 'from_name'");
                $stmt->bind_param("s", $from_name);
                $stmt->execute();

                $successMessage = "Email settings updated successfully!";
            }

            // Process security settings
            else if ($section === 'security') {
                $min_password_length = (int)trim($_POST['min_password_length']);
                $password_expiry_days = (int)trim($_POST['password_expiry_days']);
                $session_timeout_minutes = (int)trim($_POST['session_timeout_minutes']);
                $failed_login_attempts = (int)trim($_POST['failed_login_attempts']);
                $lockout_time_minutes = (int)trim($_POST['lockout_time_minutes']);
                $require_password_reset = isset($_POST['require_password_reset']) ? 1 : 0;

                // Validate inputs
                if ($min_password_length < 6) {
                    throw new Exception("Minimum password length must be at least 6 characters");
                }

                if ($session_timeout_minutes < 1) {
                    throw new Exception("Session timeout must be at least 1 minute");
                }

                // Update settings in database
                $stmt = $conn->prepare("UPDATE z_settings SET value = ? WHERE setting_key = 'min_password_length'");
                $stmt->bind_param("s", $min_password_length);
                $stmt->execute();

                $stmt = $conn->prepare("UPDATE z_settings SET value = ? WHERE setting_key = 'password_expiry_days'");
                $stmt->bind_param("s", $password_expiry_days);
                $stmt->execute();

                $stmt = $conn->prepare("UPDATE z_settings SET value = ? WHERE setting_key = 'session_timeout_minutes'");
                $stmt->bind_param("s", $session_timeout_minutes);
                $stmt->execute();

                $stmt = $conn->prepare("UPDATE z_settings SET value = ? WHERE setting_key = 'failed_login_attempts'");
                $stmt->bind_param("s", $failed_login_attempts);
                $stmt->execute();

                $stmt = $conn->prepare("UPDATE z_settings SET value = ? WHERE setting_key = 'lockout_time_minutes'");
                $stmt->bind_param("s", $lockout_time_minutes);
                $stmt->execute();

                $stmt = $conn->prepare("UPDATE z_settings SET value = ? WHERE setting_key = 'require_password_reset'");
                $stmt->bind_param("s", $require_password_reset);
                $stmt->execute();

                $successMessage = "Security settings updated successfully!";
            }

            // Process backup settings
            else if ($section === 'backup') {
                $backup_enabled = isset($_POST['backup_enabled']) ? 1 : 0;
                $backup_frequency = trim($_POST['backup_frequency']);
                $backup_retention_days = (int)trim($_POST['backup_retention_days']);
                $backup_path = trim($_POST['backup_path']);

                // Validate inputs
                if (!empty($backup_path) && !is_dir($backup_path) && !is_writable($backup_path)) {
                    throw new Exception("Backup path must be a valid, writable directory");
                }

                // Update settings in database
                $stmt = $conn->prepare("UPDATE z_settings SET value = ? WHERE setting_key = 'backup_enabled'");
                $stmt->bind_param("s", $backup_enabled);
                $stmt->execute();

                $stmt = $conn->prepare("UPDATE z_settings SET value = ? WHERE setting_key = 'backup_frequency'");
                $stmt->bind_param("s", $backup_frequency);
                $stmt->execute();

                $stmt = $conn->prepare("UPDATE z_settings SET value = ? WHERE setting_key = 'backup_retention_days'");
                $stmt->bind_param("s", $backup_retention_days);
                $stmt->execute();

                $stmt = $conn->prepare("UPDATE z_settings SET value = ? WHERE setting_key = 'backup_path'");
                $stmt->bind_param("s", $backup_path);
                $stmt->execute();

                $successMessage = "Backup settings updated successfully!";
            }

            // If we get here, all updates were successful
            $conn->commit();
        }
        catch (Exception $e) {
            // Something went wrong, rollback changes
            $conn->rollback();
            $errorMessage = "Error updating settings: " . $e->getMessage();
        }
    }
}

// Create z_settings table if it doesn't exist
$tableExists = $conn->query("SHOW TABLES LIKE 'z_settings'")->num_rows > 0;
if (!$tableExists) {
    $createTable = "CREATE TABLE z_settings (
        id INT(11) NOT NULL AUTO_INCREMENT,
        setting_key VARCHAR(50) NOT NULL,
        value TEXT NULL,
        section VARCHAR(50) NOT NULL,
        display_name VARCHAR(100) NOT NULL,
        description TEXT NULL,
        input_type VARCHAR(20) NOT NULL DEFAULT 'text',
        options TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY (setting_key)
    )";
    $conn->query($createTable);

    // Insert default settings
    $defaultSettings = [
        // General settings
        ['site_name', 'ZOUKI Web', 'general', 'Site Name', 'The name of your website', 'text', NULL],
        ['site_description', 'Food Insights Management System', 'general', 'Site Description', 'A brief description of your website', 'textarea', NULL],
        ['admin_email', 'admin@example.com', 'general', 'Admin Email', 'The primary administrator email address', 'email', NULL],
        ['timezone', 'UTC', 'general', 'Timezone', 'Default timezone for the application', 'select', '{"UTC":"UTC","America/New_York":"Eastern Time","America/Chicago":"Central Time","America/Denver":"Mountain Time","America/Los_Angeles":"Pacific Time","Asia/Tokyo":"Japan Time","Europe/London":"London Time","Australia/Sydney":"Sydney Time"}'],

        // Email settings
        ['smtp_host', 'smtp.gmail.com', 'email', 'SMTP Host', 'Your email server hostname', 'text', NULL],
        ['smtp_port', '587', 'email', 'SMTP Port', 'The port used by your email server', 'number', NULL],
        ['smtp_username', 'your_email@gmail.com', 'email', 'SMTP Username', 'Username for SMTP authentication', 'text', NULL],
        ['smtp_password', '', 'email', 'SMTP Password', 'Password for SMTP authentication', 'password', NULL],
        ['smtp_encryption', 'tls', 'email', 'SMTP Encryption', 'Encryption method for SMTP', 'select', '{"":"None","ssl":"SSL","tls":"TLS"}'],
        ['from_email', 'noreply@example.com', 'email', 'From Email', 'Default sender email address', 'email', NULL],
        ['from_name', 'ZOUKI System', 'email', 'From Name', 'Default sender name', 'text', NULL],

        // Security settings
        ['min_password_length', '8', 'security', 'Minimum Password Length', 'Minimum number of characters required for passwords', 'number', NULL],
        ['password_expiry_days', '90', 'security', 'Password Expiry (Days)', 'Number of days before passwords expire (0 for never)', 'number', NULL],
        ['session_timeout_minutes', '30', 'security', 'Session Timeout (Minutes)', 'Minutes of inactivity before session expires', 'number', NULL],
        ['failed_login_attempts', '5', 'security', 'Failed Login Attempts', 'Number of failed attempts before account lockout', 'number', NULL],
        ['lockout_time_minutes', '30', 'security', 'Lockout Time (Minutes)', 'Minutes an account remains locked after failed attempts', 'number', NULL],
        ['require_password_reset', '1', 'security', 'Require Password Reset', 'Require password reset on first login or after admin reset', 'checkbox', NULL],

        // Backup settings
        ['backup_enabled', '0', 'backup', 'Enable Automatic Backups', 'Enable or disable automatic database backups', 'checkbox', NULL],
        ['backup_frequency', 'daily', 'backup', 'Backup Frequency', 'How often to perform automatic backups', 'select', '{"hourly":"Hourly","daily":"Daily","weekly":"Weekly","monthly":"Monthly"}'],
        ['backup_retention_days', '30', 'backup', 'Backup Retention (Days)', 'Number of days to keep backups (0 for indefinite)', 'number', NULL],
        ['backup_path', '/backups', 'backup', 'Backup Path', 'Directory path where backups will be stored', 'text', NULL]
    ];

    $stmt = $conn->prepare("INSERT INTO z_settings (setting_key, value, section, display_name, description, input_type, options) VALUES (?, ?, ?, ?, ?, ?, ?)");

    foreach ($defaultSettings as $setting) {
        $stmt->bind_param("sssssss", $setting[0], $setting[1], $setting[2], $setting[3], $setting[4], $setting[5], $setting[6]);
        $stmt->execute();
    }
}

// Fetch all settings
$settings = [];
$result = $conn->query("SELECT * FROM z_settings ORDER BY section, id");

while ($row = $result->fetch_assoc()) {
    $settings[$row['section']][] = $row;
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - ZOUKI</title>
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

        /* Nav Tabs */
        .settings-tabs {
            margin-bottom: 20px;
        }

        .settings-tabs .nav-link {
            color: #6c757d;
            font-weight: 500;
            padding: 10px 20px;
            border: 1px solid transparent;
            border-radius: 8px;
            margin-right: 8px;
            transition: all 0.3s ease;
        }

        .settings-tabs .nav-link:hover {
            color: var(--primary-color);
            background-color: rgba(76, 175, 80, 0.05);
        }

        .settings-tabs .nav-link.active {
            color: var(--primary-color);
            border-color: var(--primary-color);
            background-color: rgba(76, 175, 80, 0.1);
        }

        .settings-tabs .nav-link i {
            margin-right: 8px;
        }

        /* Form Styles */
        .form-label {
            font-weight: 500;
            color: #343a40;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #ced4da;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
        }

        .form-text {
            color: #6c757d;
            font-size: 0.875rem;
            margin-top: -15px;
            margin-bottom: 15px;
        }

        .form-check {
            margin-bottom: 20px;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #43a047;
            border-color: #43a047;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        /* Section heading */
        .section-heading {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
            color: #2c3e50;
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

            .settings-tabs .nav-link {
                padding: 8px 12px;
                font-size: 0.875rem;
            }

            .settings-tabs .nav-link i {
                margin-right: 0;
            }

            .settings-tabs .nav-link span {
                display: none;
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

        <!-- Settings Tabs -->
        <ul class="nav nav-pills settings-tabs">
            <li class="nav-item">
                <a class="nav-link <?php echo $activeSection === 'general' ? 'active' : ''; ?>" href="?section=general">
                    <i class="bi bi-sliders"></i>
                    <span>General</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activeSection === 'email' ? 'active' : ''; ?>" href="?section=email">
                    <i class="bi bi-envelope"></i>
                    <span>Email</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activeSection === 'security' ? 'active' : ''; ?>" href="?section=security">
                    <i class="bi bi-shield-lock"></i>
                    <span>Security</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activeSection === 'backup' ? 'active' : ''; ?>" href="?section=backup">
                    <i class="bi bi-cloud-arrow-up"></i>
                    <span>Backup</span>
                </a>
            </li>
        </ul>

        <!-- Settings Content -->
        <div class="card">
            <div class="card-body">
                <?php if ($activeSection === 'general' && isset($settings['general'])): ?>
                    <form method="POST" action="settings.php?section=general">
                        <input type="hidden" name="action" value="update_settings">
                        <input type="hidden" name="section" value="general">

                        <h5 class="section-heading">General Settings</h5>
                        <p class="text-muted mb-4">Configure basic information about your website</p>

                        <?php foreach ($settings['general'] as $setting): ?>
                            <div class="mb-3">
                                <label for="<?php echo $setting['setting_key']; ?>" class="form-label">
                                    <?php echo htmlspecialchars($setting['display_name']); ?>
                                </label>

                                <?php if ($setting['input_type'] === 'text' || $setting['input_type'] === 'email' || $setting['input_type'] === 'number'): ?>
                                    <input
                                        type="<?php echo $setting['input_type']; ?>"
                                        class="form-control"
                                        id="<?php echo $setting['setting_key']; ?>"
                                        name="<?php echo $setting['setting_key']; ?>"
                                        value="<?php echo htmlspecialchars($setting['value']); ?>"
                                        <?php echo $setting['input_type'] === 'number' ? 'min="0"' : ''; ?>
                                    >
                                <?php elseif ($setting['input_type'] === 'textarea'): ?>
                                    <textarea
                                        class="form-control"
                                        id="<?php echo $setting['setting_key']; ?>"
                                        name="<?php echo $setting['setting_key']; ?>"
                                        rows="3"
                                    ><?php echo htmlspecialchars($setting['value']); ?></textarea>
                                <?php elseif ($setting['input_type'] === 'select' && $setting['options']): ?>
                                    <select
                                        class="form-select"
                                        id="<?php echo $setting['setting_key']; ?>"
                                        name="<?php echo $setting['setting_key']; ?>"
                                    >
                                        <?php
                                        $options = json_decode($setting['options'], true);
                                        foreach ($options as $key => $value):
                                            ?>
                                            <option value="<?php echo $key; ?>" <?php echo $setting['value'] === $key ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($value); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($setting['input_type'] === 'checkbox'): ?>
                                    <div class="form-check">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="<?php echo $setting['setting_key']; ?>"
                                            name="<?php echo $setting['setting_key']; ?>"
                                            <?php echo $setting['value'] == 1 ? 'checked' : ''; ?>
                                        >
                                        <label class="form-check-label" for="<?php echo $setting['setting_key']; ?>">
                                            Enable
                                        </label>
                                    </div>
                                <?php endif; ?>

                                <?php if ($setting['description']): ?>
                                    <div class="form-text"><?php echo htmlspecialchars($setting['description']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i> Save Changes
                            </button>
                        </div>
                    </form>

                <?php elseif ($activeSection === 'email' && isset($settings['email'])): ?>
                    <form method="POST" action="settings.php?section=email">
                        <input type="hidden" name="action" value="update_settings">
                        <input type="hidden" name="section" value="email">

                        <h5 class="section-heading">Email Settings</h5>
                        <p class="text-muted mb-4">Configure email server settings for system notifications</p>

                        <?php foreach ($settings['email'] as $setting): ?>
                            <div class="mb-3">
                                <label for="<?php echo $setting['setting_key']; ?>" class="form-label">
                                    <?php echo htmlspecialchars($setting['display_name']); ?>
                                </label>

                                <?php if ($setting['input_type'] === 'text' || $setting['input_type'] === 'email' || $setting['input_type'] === 'number'): ?>
                                    <input
                                        type="<?php echo $setting['input_type']; ?>"
                                        class="form-control"
                                        id="<?php echo $setting['setting_key']; ?>"
                                        name="<?php echo $setting['setting_key']; ?>"
                                        value="<?php echo htmlspecialchars($setting['value']); ?>"
                                        <?php echo $setting['input_type'] === 'number' ? 'min="0"' : ''; ?>
                                    >
                                <?php elseif ($setting['input_type'] === 'password'): ?>
                                    <input
                                        type="password"
                                        class="form-control"
                                        id="<?php echo $setting['setting_key']; ?>"
                                        name="<?php echo $setting['setting_key']; ?>"
                                        placeholder="Leave empty to keep current password"
                                    >
                                <?php elseif ($setting['input_type'] === 'select' && $setting['options']): ?>
                                    <select
                                        class="form-select"
                                        id="<?php echo $setting['setting_key']; ?>"
                                        name="<?php echo $setting['setting_key']; ?>"
                                    >
                                        <?php
                                        $options = json_decode($setting['options'], true);
                                        foreach ($options as $key => $value):
                                            ?>
                                            <option value="<?php echo $key; ?>" <?php echo $setting['value'] === $key ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($value); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>

                                <?php if ($setting['description']): ?>
                                    <div class="form-text"><?php echo htmlspecialchars($setting['description']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <button type="button" class="btn btn-secondary me-2" id="testEmailBtn">
                                <i class="bi bi-envelope-check me-2"></i> Test Connection
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i> Save Changes
                            </button>
                        </div>
                    </form>

                <?php elseif ($activeSection === 'security' && isset($settings['security'])): ?>
                    <form method="POST" action="settings.php?section=security">
                        <input type="hidden" name="action" value="update_settings">
                        <input type="hidden" name="section" value="security">

                        <h5 class="section-heading">Security Settings</h5>
                        <p class="text-muted mb-4">Configure security and authentication settings</p>

                        <?php foreach ($settings['security'] as $setting): ?>
                            <div class="mb-3">
                                <label for="<?php echo $setting['setting_key']; ?>" class="form-label">
                                    <?php echo htmlspecialchars($setting['display_name']); ?>
                                </label>

                                <?php if ($setting['input_type'] === 'text' || $setting['input_type'] === 'email' || $setting['input_type'] === 'number'): ?>
                                    <input
                                        type="<?php echo $setting['input_type']; ?>"
                                        class="form-control"
                                        id="<?php echo $setting['setting_key']; ?>"
                                        name="<?php echo $setting['setting_key']; ?>"
                                        value="<?php echo htmlspecialchars($setting['value']); ?>"
                                        <?php echo $setting['input_type'] === 'number' ? 'min="0"' : ''; ?>
                                    >
                                <?php elseif ($setting['input_type'] === 'checkbox'): ?>
                                    <div class="form-check">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="<?php echo $setting['setting_key']; ?>"
                                            name="<?php echo $setting['setting_key']; ?>"
                                            <?php echo $setting['value'] == 1 ? 'checked' : ''; ?>
                                        >
                                        <label class="form-check-label" for="<?php echo $setting['setting_key']; ?>">
                                            Enable
                                        </label>
                                    </div>
                                <?php endif; ?>

                                <?php if ($setting['description']): ?>
                                    <div class="form-text"><?php echo htmlspecialchars($setting['description']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i> Save Changes
                            </button>
                        </div>
                    </form>

                <?php elseif ($activeSection === 'backup' && isset($settings['backup'])): ?>
                    <form method="POST" action="settings.php?section=backup">
                        <input type="hidden" name="action" value="update_settings">
                        <input type="hidden" name="section" value="backup">

                        <h5 class="section-heading">Backup & Maintenance</h5>
                        <p class="text-muted mb-4">Configure database backup settings</p>

                        <?php foreach ($settings['backup'] as $setting): ?>
                            <div class="mb-3">
                                <label for="<?php echo $setting['setting_key']; ?>" class="form-label">
                                    <?php echo htmlspecialchars($setting['display_name']); ?>
                                </label>

                                <?php if ($setting['input_type'] === 'text' || $setting['input_type'] === 'email' || $setting['input_type'] === 'number'): ?>
                                    <input
                                        type="<?php echo $setting['input_type']; ?>"
                                        class="form-control"
                                        id="<?php echo $setting['setting_key']; ?>"
                                        name="<?php echo $setting['setting_key']; ?>"
                                        value="<?php echo htmlspecialchars($setting['value']); ?>"
                                        <?php echo $setting['input_type'] === 'number' ? 'min="0"' : ''; ?>
                                    >
                                <?php elseif ($setting['input_type'] === 'select' && $setting['options']): ?>
                                    <select
                                        class="form-select"
                                        id="<?php echo $setting['setting_key']; ?>"
                                        name="<?php echo $setting['setting_key']; ?>"
                                    >
                                        <?php
                                        $options = json_decode($setting['options'], true);
                                        foreach ($options as $key => $value):
                                            ?>
                                            <option value="<?php echo $key; ?>" <?php echo $setting['value'] === $key ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($value); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php elseif ($setting['input_type'] === 'checkbox'): ?>
                                    <div class="form-check">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="<?php echo $setting['setting_key']; ?>"
                                            name="<?php echo $setting['setting_key']; ?>"
                                            <?php echo $setting['value'] == 1 ? 'checked' : ''; ?>
                                        >
                                        <label class="form-check-label" for="<?php echo $setting['setting_key']; ?>">
                                            Enable
                                        </label>
                                    </div>
                                <?php endif; ?>

                                <?php if ($setting['description']): ?>
                                    <div class="form-text"><?php echo htmlspecialchars($setting['description']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <button type="button" class="btn btn-secondary me-2" id="manualBackupBtn">
                                <i class="bi bi-download me-2"></i> Backup Now
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i> Save Changes
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Test Email Modal -->
<div class="modal fade" id="testEmailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Test Email Connection</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="test_email" class="form-label">Send Test Email To</label>
                    <input type="email" class="form-control" id="test_email" placeholder="Enter email address">
                </div>
                <div id="testEmailStatus"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="sendTestEmailBtn">
                    <i class="bi bi-envelope-paper me-1"></i> Send Test Email
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Manual Backup Modal -->
<div class="modal fade" id="manualBackupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Manual Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>This will create a backup of your database. The process may take a few moments depending on the size of your database.</p>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i> The backup will be stored in the configured backup path.
                </div>
                <div id="backupStatus"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmBackupBtn">
                    <i class="bi bi-download me-1"></i> Create Backup
                </button>
            </div>
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
    // Test Email Button Handler
    document.getElementById('testEmailBtn')?.addEventListener('click', function() {
        const testEmailModal = new bootstrap.Modal(document.getElementById('testEmailModal'));
        testEmailModal.show();
    });

    // Send Test Email Button Handler
    document.getElementById('sendTestEmailBtn')?.addEventListener('click', function() {
        const testEmail = document.getElementById('test_email').value;
        const statusDiv = document.getElementById('testEmailStatus');

        if (!testEmail || !testEmail.includes('@')) {
            statusDiv.innerHTML = '<div class="alert alert-danger mt-3">Please enter a valid email address</div>';
            return;
        }

        // Show loading state
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Sending...';
        statusDiv.innerHTML = '<div class="alert alert-info mt-3">Attempting to send test email...</div>';

        // Simulate AJAX request for testing email configuration
        setTimeout(() => {
            // This is just a placeholder - in a real app, you would call an actual AJAX endpoint
            statusDiv.innerHTML = '<div class="alert alert-success mt-3">Test email sent successfully!</div>';
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-envelope-paper me-1"></i> Send Test Email';
        }, 2000);
    });

    // Manual Backup Button Handler
    document.getElementById('manualBackupBtn')?.addEventListener('click', function() {
        const manualBackupModal = new bootstrap.Modal(document.getElementById('manualBackupModal'));
        manualBackupModal.show();
    });

    // Confirm Backup Button Handler
    document.getElementById('confirmBackupBtn')?.addEventListener('click', function() {
        const statusDiv = document.getElementById('backupStatus');

        // Show loading state
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Backing up...';
        statusDiv.innerHTML = '<div class="alert alert-info mt-3">Creating database backup...</div>';

        // Simulate AJAX request for creating backup
        setTimeout(() => {
            // This is just a placeholder - in a real app, you would call an actual AJAX endpoint
            statusDiv.innerHTML = '<div class="alert alert-success mt-3">Backup created successfully!</div>';
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-download me-1"></i> Create Backup';
        }, 3000);
    });

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