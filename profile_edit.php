<?php
require_once 'session_check.php';
check_session(); // Allow all authenticated users
include 'db_connection.php';
include 'includes/functions.php';  // Include common functions

// Page-specific variables
$page_title = 'Edit Profile';
$active_page = 'profile';
$use_chart_js = false; // Disable Chart.js for this page

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

// Fetch user profile details
$userQuery = $conn->prepare("
    SELECT 
        first_name, 
        last_name, 
        email, 
        contact_number,
        profile_photo,
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
if (isset($successMessage) == false) {
    $successMessage = $_SESSION['success'] ?? '';
}
if (isset($errorMessage) == false) {
    $errorMessage = $_SESSION['error'] ?? '';
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

// Clear session messages
if (isset($_SESSION['success'])) unset($_SESSION['success']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);

// Add CSS for profile page
$additional_css = '
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
';

// JavaScript for profile page
$page_scripts = '
// Toggle Password Visibility
function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.nextElementSibling.querySelector("i");

    if (input.type === "password") {
        input.type = "text";
        icon.classList.replace("bi-eye", "bi-eye-slash");
    } else {
        input.type = "password";
        icon.classList.replace("bi-eye-slash", "bi-eye");
    }
}

// Reset Form
function resetForm() {
    document.getElementById("profileForm").reset();
    showToast("Form has been reset", "info");
}

// Validate Profile Form
document.getElementById("profileForm").addEventListener("submit", function(e) {
    const newPassword = document.getElementById("new_password").value;
    const confirmPassword = document.getElementById("confirm_password").value;

    if (newPassword !== confirmPassword && newPassword !== "") {
        e.preventDefault();
        showToast("New passwords do not match", "danger");
        return false;
    }

    return true;
});

// Security Features (placeholders)
document.getElementById("enable2fa").addEventListener("click", function() {
    showToast("Two-factor authentication feature coming soon", "info");
});

document.getElementById("viewLoginActivity").addEventListener("click", function() {
    showToast("Login activity tracking coming soon", "info");
});

document.getElementById("deleteAccount").addEventListener("click", function() {
    // Show confirmation modal
    const modalTitle = document.querySelector("#confirmationModal .modal-title");
    const modalBody = document.querySelector("#confirmationModal .modal-body");
    const confirmBtn = document.querySelector("#confirmationModal .btn-primary");
    
    modalTitle.textContent = "Delete Account";
    modalBody.innerHTML = `
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
    
    confirmBtn.className = "btn btn-danger";
    confirmBtn.textContent = "Delete Account";
    confirmBtn.dataset.action = "delete_account";
    
    const modal = new bootstrap.Modal(document.getElementById("confirmationModal"));
    modal.show();
});

// Handle confirmation modal actions
document.querySelector("#confirmationModal .btn-primary").addEventListener("click", function() {
    const action = this.dataset.action;
    const modal = bootstrap.Modal.getInstance(document.getElementById("confirmationModal"));
    
    if (action === "delete_account") {
        const password = document.getElementById("confirmDeletePassword").value;
        
        if (!password) {
            showToast("Please enter your password to confirm", "warning");
            return;
        }
        
        // Here you would normally send an AJAX request to delete the account
        showToast("Account deletion feature coming soon", "info");
        modal.hide();
    }
});

// Helper function to show toast notifications
function showToast(message, type = "success") {
    const toastContainer = document.querySelector(".toast-container");
    if (!toastContainer) return;
    
    const toastId = "toast-" + Date.now();
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
    
    toastContainer.insertAdjacentHTML("beforeend", toastHTML);
    
    const toast = new bootstrap.Toast(document.getElementById(toastId), {
        autohide: true,
        delay: 3000
    });
    
    toast.show();
    
    // Remove the toast from DOM after it\'s hidden
    document.getElementById(toastId).addEventListener("hidden.bs.toast", function() {
        this.remove();
    });
}
';

// Include header
include 'includes/header.php';
?>

<!-- Main Content -->
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

    <div class="row">
        <!-- Profile Sidebar -->
        <div class="col-md-4">
            <div class="app-card">
                <div class="profile-sidebar">
                    <?php if (!empty($userDetails['profile_photo']) && file_exists($userDetails['profile_photo'])): ?>
                        <img src="<?php echo htmlspecialchars($userDetails['profile_photo']); ?>" alt="Profile Photo" class="profile-photo">
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
                        <p class="text-muted mb-3">Add an extra layer of security to your account by enabling two-factor authentication.</p>
                        <button class="btn btn-outline-primary" id="enable2fa">
                            <i class="bi bi-shield-lock"></i> Enable 2FA
                        </button>
                    </div>

                    <div class="mb-4">
                        <h6 class="mb-3">Login Activity</h6>
                        <p class="text-muted mb-3">Review your recent login activity to ensure it was you.</p>
                        <button class="btn btn-outline-primary" id="viewLoginActivity">
                            <i class="bi bi-clock-history"></i> View Activity
                        </button>
                    </div>

                    <div>
                        <h6 class="mb-3">Delete Account</h6>
                        <p class="text-muted mb-3">Permanently delete your account and all associated data.</p>
                        <button class="btn btn-danger" id="deleteAccount">
                            <i class="bi bi-trash"></i> Delete Account
                        </button>
                    </div>
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
                <h5 class="modal-title">Confirmation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to proceed with this action?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary">Confirm</button>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>