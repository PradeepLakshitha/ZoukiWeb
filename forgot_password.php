<?php
session_start();
include 'db_connection.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer
require 'vendor/autoload.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format!";
        header("Location: forgot_password.php");
        exit();
    }

    $sql = "SELECT * FROM z_user WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $token = bin2hex(random_bytes(50));
        $stmt = $conn->prepare("UPDATE z_user SET reset_token = ? WHERE email = ?");
        $stmt->bind_param("ss", $token, $email);
        $stmt->execute();

        // Local Reset Link (For Testing)
        $reset_link = "http://localhost/zouki_web/reset_password.php?token=$token";

        // Send Email via PHPMailer
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';  // Change this to your mail server
            $mail->SMTPAuth = true;
            $mail->Username = 'zoukioperations@gmail.com';  // Change this to your email
            $mail->Password = 'lxgr pwar rilz pmhx';  // Change this to your email password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('zoukioperations@gmail.com', 'ZOUKI Support');
            $mail->addAddress($email);
            $mail->Subject = 'ZOUKI Password Reset Request';
            $mail->Body = "Click the link below to reset your password:\n\n$reset_link";

            $mail->send();
            $_SESSION['success'] = "A reset link has been sent to your email!";

        } catch (Exception $e) {
            $_SESSION['error'] = "Email could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    } else {
        $_SESSION['error'] = "Email not found!";
    }

    header("Location: forgot_password.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - ZOUKI</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- SweetAlert2 for Notifications -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="container mt-5">
    <h2 class="text-center">Forgot Password</h2>

    <?php if (isset($_SESSION['success'])): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '<?php echo $_SESSION['success']; ?>',
                showConfirmButton: false,
                timer: 3000
            }).then(() => {
                window.location.href = "index.php"; // Redirect after the popup closes
            });
        </script>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>


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

    <form method="POST" action="forgot_password.php">
        <div class="mb-3">
            <label class="form-label">Enter your registered email</label>
            <input type="email" name="email" class="form-control" placeholder="Email address" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
    </form>
</div>
</body>
</html>