<?php
session_start();
include 'db_connection.php';

if (!isset($_GET['token'])) {
    $_SESSION['error'] = "Invalid or expired reset token!";
    header("Location: forgot_password.php");
    exit();
}

$token = $_GET['token'];

// Verify the token in the database
$stmt = $conn->prepare("SELECT email FROM z_user WHERE reset_token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Invalid or expired reset token!";
    header("Location: forgot_password.php");
    exit();
}

// If form is submitted, reset the password
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE z_user SET password = ?, reset_token = NULL WHERE reset_token = ?");
    $stmt->bind_param("ss", $new_password, $token);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Password reset successfully! You can now log in.";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = "Something went wrong. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- SweetAlert2 for Notifications -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container mt-5">
    <h2 class="text-center">Reset Password</h2>

    <?php if (isset($_SESSION['error'])): ?>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: '<?php echo $_SESSION['error']; ?>',
                showConfirmButton: false,
                timer: 3000
            });
        </script>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" placeholder="Enter new password" required>
        </div>
        <button type="submit" class="btn btn-success w-100">Reset Password</button>
    </form>
</div>
</body>
</html>
