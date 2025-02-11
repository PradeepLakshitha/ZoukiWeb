<?php
session_start();
include 'db_connection.php';

// Auto-login using Remember Me cookie
if (isset($_COOKIE['username']) && !isset($_SESSION['username'])) {
    $_SESSION['username'] = $_COOKIE['username'];
    $_SESSION['uType'] = $_COOKIE['uType']; // Restore user type
    header("Location: home.php");
    exit();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZOUKI User Login</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="d-flex flex-column min-vh-100 position-relative">

<div class="container-custom flex-grow-1 d-flex align-items-center justify-content-center">
    <div class="row w-100 justify-content-center">
        <div class="col-md-6 d-flex align-items-center justify-content-center">
            <div class="login-container">
                <div class="logo-container">
                    <img src="img/ZoukiLogo.svg" alt="ZOUKI Logo" style="width: 120px; height: auto;">
                </div>

                <div id="message"></div>

                <form id="loginForm">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" id="username" name="username" class="form-control form-control-lg" required />
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" id="password" name="password" class="form-control form-control-lg" required />
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="form-check mb-0">
                            <input class="form-check-input me-2" type="checkbox" name="remember" id="rememberMe" />
                            <label class="form-check-label" for="rememberMe"> Remember me </label>
                        </div>
                        <a href="forgot_password.php" class="text-body">Forgot password?</a>
                    </div>

                    <div class="text-center text-lg-start mt-4 pt-2">
                        <button type="submit" id="loginBtn" class="btn btn-success btn-lg w-100">Login</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<footer class="mt-auto text-center py-3 w-100 position-relative footer">
    <div class="container">
        <p class="mb-0">Copyright © 2025. All rights reserved.</p>
    </div>
</footer>

<script>
    document.getElementById("loginForm").addEventListener("submit", function(event) {
        event.preventDefault();

        let formData = new FormData(this);

        fetch("login.php", {
            method: "POST",
            body: formData,
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    Swal.fire({
                        icon: 'success',
                        title: 'Login Successful',
                        text: 'Redirecting...',
                        showConfirmButton: false,
                        timer: 2000
                    }).then(() => {
                        window.location.href = 'home.php';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Login Failed',
                        text: data.message
                    });
                }
            })
            .catch(error => console.error("Error:", error));
    });
</script>
</body>
</html>
