<?php
include 'db_connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $contact_number = trim($_POST['contact_number']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $status = trim($_POST['status']);
    $uType = trim($_POST['uType']); // Capture user type

    // Validate Email Format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>
                Swal.fire({ icon: 'error', title: 'Invalid Email!', text: 'Please enter a valid email address.' });
              </script>";
        exit();
    }

    // Check if Username or Email Already Exists
    $check_sql = "SELECT * FROM z_user WHERE email = ? OR username = ?";
    $stmt_check = $conn->prepare($check_sql);
    $stmt_check->bind_param("ss", $email, $username);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        echo "<script>
                Swal.fire({ icon: 'error', title: 'User Exists!', text: 'Email or Username already taken.' });
              </script>";
        exit();
    }

    // Hash Password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // Insert Data Using Prepared Statement
    $sql = "INSERT INTO z_user (first_name, last_name, contact_number, email, username, password, status, uType)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssss", $first_name, $last_name, $contact_number, $email, $username, $hashed_password, $status, $uType);

    if ($stmt->execute()) {
        echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'User Added!',
                    text: 'User has been added successfully.',
                    showConfirmButton: false,
                    timer: 2000
                }).then(() => { window.location='add_user.php'; });
              </script>";
    } else {
        echo "<script>
                Swal.fire({ icon: 'error', title: 'Error!', text: 'Something went wrong: {$conn->error}' });
              </script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - ZOUKI</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- SweetAlert2 for Beautiful Popups -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container mt-5">
    <h2 class="text-center">Add New User</h2>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">First Name</label>
            <input type="text" name="first_name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Last Name</label>
            <input type="text" name="last_name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Contact Number</label>
            <input type="text" name="contact_number" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-control" required>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">User Type</label>
            <select name="uType" class="form-control" required>
                <option value="Admin">Admin</option>
                <option value="Manager">Manager</option>
                <option value="User">User</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary w-100">Add User</button>
    </form>
</div>

<!-- Footer -->
<footer class="mt-auto text-center py-3 w-100 position-relative footer">
    <div class="container">
        <p class="mb-0">&copy; 2025 ZOUKI. All rights reserved.</p>
    </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
