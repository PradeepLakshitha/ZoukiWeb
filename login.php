<?php
session_start();
include 'db_connection.php';

header('Content-Type: application/json'); // Ensure JSON response

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']); // Get raw password from form
    $remember = isset($_POST['remember']);

    $sql = "SELECT userID, username, password, uType FROM z_user WHERE username = ? AND status = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        // Compare the entered password with the stored hash
        if (password_verify($password, $row['password'])) {
            // Regenerate session ID for security
            session_regenerate_id(true);

            // Set all required session variables
            $_SESSION['userID'] = $row['userID'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['uType'] = $row['uType']; // Store user type in session
            $_SESSION['logged_in'] = true; // This is required by products_management.php
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR']; // For session security check

            // Securely Unset password variables
            unset($_POST['password']);
            unset($password);

            // Set a Remember Me cookie **ONLY IF LOGIN IS SUCCESSFUL**
            // Set a Remember Me cookie **ONLY IF LOGIN IS SUCCESSFUL**
            if ($remember) {
                // Set httponly cookies for security
                $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'; // Only use secure in HTTPS
                setcookie("username", $username, [
                    'expires' => time() + (86400 * 30),
                    'path' => '/',
                    'secure' => $secure,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
                setcookie("uType", $row['uType'], [
                    'expires' => time() + (86400 * 30),
                    'path' => '/',
                    'secure' => $secure,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
            }

            // Optional: Write to log for debugging
            file_put_contents('login_debug.log',
                date('Y-m-d H:i:s') . " - Login successful: $username (ID: {$row['userID']}, Type: {$row['uType']})\n",
                FILE_APPEND);

            echo json_encode(["status" => "success", "message" => "Login successful!"]);
            exit();
        } else {
            echo json_encode(["status" => "error", "message" => "Invalid password!"]);
            exit();
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid username or account inactive!"]);
        exit();
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit();
}
?>