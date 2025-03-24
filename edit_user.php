<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'session_check.php';
check_session(['Admin', 'Manager']);
include 'db_connection.php';

// Check database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ensure only Admin & Manager can access
if (!isset($_SESSION['username']) || ($_SESSION['uType'] !== 'Admin' && $_SESSION['uType'] !== 'Manager')) {
    $_SESSION['error'] = "Access denied!";
    header("Location: dashboard.php");
    exit();
}

// Initialize variables
$successMessage = '';
$errorMessage = '';
$user = [];

// Set active tab for navigation
$activeTab = 'users';

// Check if user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "No user specified for editing.";
    header("Location: users.php");
    exit();
}

$user_id = (int) $_GET['id'];

// Fetch user data
$user_query = "SELECT * FROM z_user WHERE userID = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "User not found.";
    header("Location: users.php");
    exit();
}

$user = $result->fetch_assoc();

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $contact_number = trim($_POST['contact_number']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $status = trim($_POST['status']);
    $uType = trim($_POST['uType']);
    $profile_photo = $user['profile_photo']; // Keep existing profile photo by default
    
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
                // Delete old profile photo if it exists
                if (!empty($user['profile_photo']) && file_exists($user['profile_photo']) && $user['profile_photo'] != $target_file) {
                    unlink($user['profile_photo']);
                }
                $profile_photo = $target_file;
            } else {
                $errorMessage = "Failed to upload profile photo.";
            }
        } else {
            $errorMessage = "Invalid file type for profile photo. Only JPG, JPEG, PNG & GIF files are allowed.";
        }
    }

    // Validate email format
    if (!isset($errorMessage) || empty($errorMessage)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = "Invalid email format! Please enter a valid email address.";
        } else {
            // Check if Username or Email Already Exists (excluding current user)
            $check_sql = "SELECT * FROM z_user WHERE (email = ? OR username = ?) AND userID != ?";
            $stmt_check = $conn->prepare($check_sql);
            $stmt_check->bind_param("ssi", $email, $username, $user_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows > 0) {
                $errorMessage = "Email or Username already taken by another user.";
            } else {
                try {
                    // Update user information
                    $update_query = "UPDATE z_user SET 
                                    first_name = ?, 
                                    last_name = ?, 
                                    contact_number = ?, 
                                    email = ?, 
                                    username = ?,
                                    status = ?, 
                                    uType = ?,
                                    profile_photo = ?
                                    WHERE userID = ?";
                                    
                    $stmt = $conn->prepare($update_query);
                    
                    if ($stmt === false) {
                        throw new Exception("Prepare failed: " . $conn->error);
                    }
                    
                    $stmt->bind_param("ssssssssi", 
                        $first_name, 
                        $last_name, 
                        $contact_number, 
                        $email, 
                        $username, 
                        $status, 
                        $uType, 
                        $profile_photo,
                        $user_id
                    );
                    
                    // Handle password update if provided
                    if (!empty($_POST['password'])) {
                        $password = trim($_POST['password']);
                        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                        
                        $password_query = "UPDATE z_user SET password = ? WHERE userID = ?";
                        $password_stmt = $conn->prepare($password_query);
                        $password_stmt->bind_param("si", $hashed_password, $user_id);
                        $password_stmt->execute();
                    }
                    
                    if ($stmt->execute()) {
                        $successMessage = "User updated successfully!";
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
    <title>Edit User - ZOUKI</title>
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

        /* Form Styles */
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            padding: 10px 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
        }

        .form-label {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        /* Action Button Styles */
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
        
        /* Profile preview */
        .profile-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e0e0e0;
            background-color: #f8f9fa;
            margin: 0 auto 20px;
            display: block;
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
        <h4 class="mb-0">Edit User</h4>
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

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Edit User: <?php echo htmlspecialchars($user['username']); ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?id=' . $user_id; ?>" id="editUserForm" enctype="multipart/form-data">
                            
                            <?php if (!empty($user['profile_photo']) && file_exists($user['profile_photo'])): ?>
                                <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="Profile Photo" class="profile-preview mb-4">
                            <?php else: ?>
                                <div class="profile-preview d-flex align-items-center justify-content-center mb-4">
                                    <i class="bi bi-person" style="font-size: 3rem; color: #adb5bd;"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">First Name</label>
                                        <input type="text" name="first_name" class="form-control" required value="<?php echo htmlspecialchars($user['first_name']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" name="last_name" class="form-control" required value="<?php echo htmlspecialchars($user['last_name']); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Contact Number</label>
                                        <input type="text" name="contact_number" class="form-control" required value="<?php echo htmlspecialchars($user['contact_number']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($user['email']); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Username</label>
                                        <input type="text" name="username" class="form-control" required value="<?php echo htmlspecialchars($user['username']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Password <small class="text-muted">(Leave empty to keep current)</small></label>
                                        <input type="password" name="password" class="form-control">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-select" required>
                                            <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">User Type</label>
                                        <select name="uType" class="form-select" required>
                                            <option value="Admin" <?php echo $user['uType'] === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                                            <option value="Manager" <?php echo $user['uType'] === 'Manager' ? 'selected' : ''; ?>>Manager</option>
                                            <option value="User" <?php echo $user['uType'] === 'User' ? 'selected' : ''; ?>>User</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Profile Photo</label>
                                <input type="file" name="profile_photo" class="form-control" accept="image/jpeg,image/png,image/gif">
                                <small class="text-muted">Upload a new profile picture (optional)</small>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <a href="users.php" class="btn btn-light">
                                    <i class="bi bi-arrow-left"></i> Back to Users
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
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
    // Display success message if exists
    <?php if ($successMessage): ?>
    window.addEventListener('DOMContentLoaded', (event) => {
        const successModal = new bootstrap.Modal(document.getElementById('successModal'));
        document.getElementById('successModalMessage').textContent = <?php echo json_encode($successMessage); ?>;
        successModal.show();
        setTimeout(() => {
            window.location.href = 'users.php';
        }, 2000);
    });
    <?php endif; ?>

    // Form validation with detailed error messages
    document.getElementById('editUserForm').addEventListener('submit', function(e) {
        let isValid = true;
        let errorMessages = [];
        
        this.querySelectorAll('[required]').forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('is-invalid');
                
                // Get field label
                const label = field.previousElementSibling ? field.previousElementSibling.textContent : field.name;
                errorMessages.push(label + ' is required');
                
                // Add error message below the field
                const existingFeedback = field.nextElementSibling && field.nextElementSibling.classList.contains('invalid-feedback');
                if (!existingFeedback) {
                    const feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback';
                    feedback.textContent = label + ' is required';
                    field.parentNode.appendChild(feedback);
                }
            } else {
                field.classList.remove('is-invalid');
            }
        });

        // Validate email format
        const emailField = this.querySelector('[type="email"]');
        if (emailField.value && !isValidEmail(emailField.value)) {
            isValid = false;
            emailField.classList.add('is-invalid');
            errorMessages.push('Invalid email format');
            
            // Add error message for email
            const existingFeedback = emailField.nextElementSibling && emailField.nextElementSibling.classList.contains('invalid-feedback');
            if (!existingFeedback) {
                const feedback = document.createElement('div');
                feedback.className = 'invalid-feedback';
                feedback.textContent = 'Please enter a valid email address';
                emailField.parentNode.appendChild(feedback);
            }
        }

        if (!isValid) {
            e.preventDefault();
            
            // Show error summary at the top
            const errorSummary = document.createElement('div');
            errorSummary.className = 'alert alert-danger mb-3';
            errorSummary.innerHTML = '<strong>Please correct the following errors:</strong><ul>' + 
                errorMessages.map(msg => `<li>${msg}</li>`).join('') + '</ul>';
            
            const form = document.getElementById('editUserForm');
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
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }

    // Preview profile photo
    document.querySelector('input[name="profile_photo"]').addEventListener('change', function(event) {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const previewImg = document.querySelector('.profile-preview');
                if (previewImg.tagName === 'IMG') {
                    previewImg.src = e.target.result;
                } else {
                    // If it's the placeholder div, replace it with an image
                    const newImg = document.createElement('img');
                    newImg.src = e.target.result;
                    newImg.alt = "Profile Photo";
                    newImg.className = "profile-preview mb-4";
                    previewImg.parentNode.replaceChild(newImg, previewImg);
                }
            };
            
            reader.readAsDataURL(this.files[0]);
        }
    });

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