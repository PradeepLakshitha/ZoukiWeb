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

// Page-specific variables
$page_title = 'System Settings';
$active_page = 'settings';

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

// Check for messages
$successMessage = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';

// Clear session messages
if (isset($_SESSION['success'])) unset($_SESSION['success']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);

// Include header
include 'includes/header.php';
?>

<!-- Page-specific content starts here -->
<div class="container-fluid">
    <!-- Settings Categories -->
    <div class="row">
        <!-- System Settings -->
        <div class="col-md-4 mb-4">
            <div class="app-card">
                <div class="app-card-body">
                    <div class="text-center mb-3">
                        <i class="bi bi-gear-fill" style="font-size: 2.5rem; color: var(--primary-color);"></i>
                    </div>
                    <h5 class="mb-2 text-center">General Settings</h5>
                    <p class="text-muted text-center">Configure system-wide settings and preferences</p>
                    <div class="text-center mt-3">
                        <a href="#" class="btn btn-primary">Manage</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Permissions -->
        <div class="col-md-4 mb-4">
            <div class="app-card">
                <div class="app-card-body">
                    <div class="text-center mb-3">
                        <i class="bi bi-shield-lock" style="font-size: 2.5rem; color: var(--primary-color);"></i>
                    </div>
                    <h5 class="mb-2 text-center">User Permissions</h5>
                    <p class="text-muted text-center">Manage user roles and access permissions</p>
                    <div class="text-center mt-3">
                        <a href="#" class="btn btn-primary">Manage</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Backup & Restore -->
        <div class="col-md-4 mb-4">
            <div class="app-card">
                <div class="app-card-body">
                    <div class="text-center mb-3">
                        <i class="bi bi-archive" style="font-size: 2.5rem; color: var(--primary-color);"></i>
                    </div>
                    <h5 class="mb-2 text-center">Backup & Restore</h5>
                    <p class="text-muted text-center">Backup data and restore from previous backups</p>
                    <div class="text-center mt-3">
                        <a href="#" class="btn btn-primary">Manage</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Import/Export Products -->
        <div class="col-md-4 mb-4">
            <div class="app-card">
                <div class="app-card-body">
                    <div class="text-center mb-3">
                        <i class="bi bi-file-earmark-arrow-up-down" style="font-size: 2.5rem; color: var(--primary-color);"></i>
                    </div>
                    <h5 class="mb-2 text-center">Import/Export Products</h5>
                    <p class="text-muted text-center">Bulk import or export products using CSV files</p>
                    <div class="text-center mt-3">
                        <a href="import_export_setup.php" class="btn btn-primary">Manage</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Image Upload -->
        <div class="col-md-4 mb-4">
            <div class="app-card">
                <div class="app-card-body">
                    <div class="text-center mb-3">
                        <i class="bi bi-images" style="font-size: 2.5rem; color: var(--primary-color);"></i>
                    </div>
                    <h5 class="mb-2 text-center">Bulk Image Upload</h5>
                    <p class="text-muted text-center">Upload multiple product images at once</p>
                    <div class="text-center mt-3">
                        <a href="bulk_image_upload.php" class="btn btn-primary">Manage</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Email Configuration -->
        <div class="col-md-4 mb-4">
            <div class="app-card">
                <div class="app-card-body">
                    <div class="text-center mb-3">
                        <i class="bi bi-envelope" style="font-size: 2.5rem; color: var(--primary-color);"></i>
                    </div>
                    <h5 class="mb-2 text-center">Email Settings</h5>
                    <p class="text-muted text-center">Configure email notifications and templates</p>
                    <div class="text-center mt-3">
                        <a href="#" class="btn btn-primary">Manage</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recipe Format Settings -->
    <div class="app-card mb-4">
        <div class="app-card-header">
            <h5 class="app-card-title">Recipe Format Settings</h5>
        </div>
        <div class="app-card-body">
            <div class="row">
                <div class="col-md-8">
                    <h6>Format Filters Management</h6>
                    <p>Manage formatting filters for ingredients and recipe instructions. These filters determine how ingredients are organized and how recipe instructions are highlighted.</p>

                    <ul class="list-group list-unstyled ms-3 mt-3">
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Ingredient section headers (e.g., "Garnish:", "For the sauce:")</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Ingredient measurement patterns for parsing quantities</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Recipe section headers (e.g., "Preparation:", "Cooking:")</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Recipe highlight patterns (e.g., cooking times, temperatures)</li>
                    </ul>
                </div>
                <div class="col-md-4 text-end align-self-center">
                    <a href="format_filters.php" class="btn btn-primary">
                        <i class="bi bi-sliders"></i> Manage Format Filters
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- System Information -->
    <div class="app-card mt-4">
        <div class="app-card-header">
            <h5 class="app-card-title">System Information</h5>
        </div>
        <div class="app-card-body">
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

<?php
// Include footer
include 'includes/footer.php';
?>