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
$page_title = 'Add New User';
$active_page = 'users';

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

// Initialize variables
$successMessage = '';
$errorMessage = '';

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $contact_number = trim($_POST['contact_number']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $status = trim($_POST['status']);
    $uType = trim($_POST['uType']);
    $profile_photo = NULL; // Default to NULL for profile_photo

    // Handle profile photo upload if provided
    if(isset($_FILES['profile_photo']) && $_FILES['profile_photo']['name'] != "") {
        $upload_dir = "uploads/profiles/";
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif');
        
        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = $username . '_' . time() . '.' . $file_extension;
            $target_file = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
                $profile_photo = $target_file; // Save the path to use in DB
            } else {
                $errorMessage = "Failed to upload profile photo.";
            }
        } else {
            $errorMessage = "Invalid file type for profile photo. Only JPG, JPEG, PNG & GIF files are allowed.";
        }
    }

    // Validate Email Format
    if (!isset($errorMessage) || empty($errorMessage)) { // Only proceed if no error occurred
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = "Invalid email format! Please enter a valid email address.";
        } else {
            // Check if Username or Email Already Exists
            $check_sql = "SELECT * FROM z_user WHERE email = ? OR username = ?";
            $stmt_check = $conn->prepare($check_sql);
            
            if ($stmt_check === false) {
                $errorMessage = "Prepare statement failed: " . $conn->error;
            } else {
                $stmt_check->bind_param("ss", $email, $username);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();

                if ($result_check->num_rows > 0) {
                    $errorMessage = "Email or Username already taken.";
                } else {
                    try {
                        // Hash Password
                        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                        // Insert Data Using Prepared Statement
                        $sql = "INSERT INTO z_user (first_name, last_name, contact_number, email, username, password, status, uType, profile_photo) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        
                        $stmt = $conn->prepare($sql);
                        
                        if ($stmt === false) {
                            throw new Exception("Prepare failed: " . $conn->error);
                        }
                        
                        // Explicitly bind each parameter 
                        $bind_result = $stmt->bind_param("sssssssss",
                            $first_name,
                            $last_name,
                            $contact_number,
                            $email,
                            $username,
                            $hashed_password,
                            $status,
                            $uType,
                            $profile_photo
                        );
                        
                        if ($bind_result === false) {
                            throw new Exception("Bind_param failed: " . $stmt->error);
                        }
                        
                        $execute_result = $stmt->execute();
                        
                        if ($execute_result) {
                            // Add notification for all admin users
                            $adminQuery = "SELECT userID FROM z_user WHERE uType = 'Admin'";
                            $adminResult = $conn->query($adminQuery);
                            
                            if ($adminResult) {
                                while ($admin = $adminResult->fetch_assoc()) {
                                    addNotification(
                                        $conn, 
                                        $admin['userID'], 
                                        'user', 
                                        'New User Added', 
                                        "New user '{$username}' with role '{$uType}' has been added."
                                    );
                                }
                            }

                            $successMessage = "User added successfully!";
                            $_SESSION['success'] = $successMessage;
                            header("Location: users.php");
                            exit();
                        } else {
                            throw new Exception("Execute failed: " . $stmt->error);
                        }
                    } catch (Exception $e) {
                        $errorMessage = "Database error: " . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Fetch total users count for display
$totalUsersQuery = $conn->query("SELECT COUNT(*) as total FROM z_user");
$totalUsers = $totalUsersQuery ? $totalUsersQuery->fetch_assoc()['total'] : 0;

// Check for session messages
if (isset($_SESSION['success'])) {
    $successMessage = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $errorMessage = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Include header
include 'includes/header.php';
?>

<!-- Main Content -->
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="app-card">
                <div class="app-card-header">
                    <h5 class="app-card-title">Add New User</h5>
                    <div class="app-card-toolbar">
                        <a href="users.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> Back to Users
                        </a>
                    </div>
                </div>
                <div class="app-card-body">
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="addUserForm" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-control" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" name="contact_number" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="username" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">User Type</label>
                                    <select name="uType" class="form-select" required>
                                        <option value="Admin">Admin</option>
                                        <option value="Manager">Manager</option>
                                        <option value="User">User</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Profile Photo</label>
                            <input type="file" name="profile_photo" class="form-control" accept="image/jpeg,image/png,image/gif">
                            <small class="text-muted">Upload a profile picture (optional)</small>
                        </div>
                        <div class="d-flex justify-content-between mt-4">
                            <a href="users.php" class="btn btn-light">
                                <i class="bi bi-arrow-left"></i> Back to Users
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-person-plus"></i> Add User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Page-specific scripts
$page_scripts = '
// Form validation with detailed error messages
document.getElementById("addUserForm").addEventListener("submit", function(e) {
    let isValid = true;
    let errorMessages = [];
    
    this.querySelectorAll("[required]").forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.classList.add("is-invalid");
            
            // Get field label
            const label = field.previousElementSibling ? field.previousElementSibling.textContent : field.name;
            errorMessages.push(label + " is required");
            
            // Add error message below the field
            const existingFeedback = field.nextElementSibling && field.nextElementSibling.classList.contains("invalid-feedback");
            if (!existingFeedback) {
                const feedback = document.createElement("div");
                feedback.className = "invalid-feedback";
                feedback.textContent = label + " is required";
                field.parentNode.appendChild(feedback);
            }
        } else {
            field.classList.remove("is-invalid");
        }
    });

    // Validate email format
    const emailField = this.querySelector("[type=\'email\']");
    if (emailField.value && !isValidEmail(emailField.value)) {
        isValid = false;
        emailField.classList.add("is-invalid");
        errorMessages.push("Invalid email format");
        
        // Add error message for email
        const existingFeedback = emailField.nextElementSibling && emailField.nextElementSibling.classList.contains("invalid-feedback");
        if (!existingFeedback) {
            const feedback = document.createElement("div");
            feedback.className = "invalid-feedback";
            feedback.textContent = "Please enter a valid email address";
            emailField.parentNode.appendChild(feedback);
        }
    }

    if (!isValid) {
        e.preventDefault();
        
        // Show error summary at the top
        const errorSummary = document.createElement("div");
        errorSummary.className = "alert alert-danger mb-3";
        errorSummary.innerHTML = "<strong>Please correct the following errors:</strong><ul>" + 
            errorMessages.map(msg => `<li>${msg}</li>`).join("") + "</ul>";
        
        const form = document.getElementById("addUserForm");
        form.insertBefore(errorSummary, form.firstChild);
        
        // Remove the error summary after 5 seconds
        setTimeout(() => {
            if (errorSummary.parentNode) {
                errorSummary.parentNode.removeChild(errorSummary);
            }
        }, 5000);
        
        // Scroll to the top of the form
        window.scrollTo(0, form.offsetTop - 100);
    }
});

function isValidEmail(email) {
    const regex = /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/;
    return regex.test(email);
}
';

// Include footer
include 'includes/footer.php';
?>