<?php
session_start();
include 'db_connection.php';

header('Content-Type: application/json'); // Ensure JSON response

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']); // Get raw password from form
    $remember = isset($_POST['remember']);

    $sql = "SELECT * FROM z_user WHERE username = ? AND status = 'active'";
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

            $_SESSION['userID'] = $row['userID'];
            $_SESSION['username'] = $row['username']; // Ensure consistency in session variable

            // Securely Unset password variables
            unset($_POST['password']);
            unset($password);

            // Set a Remember Me cookie **ONLY IF LOGIN IS SUCCESSFUL**
            if ($remember) {
                setcookie("username", $username, time() + (86400 * 30), "/", "", true, true); // HttpOnly and Secure
            }

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
