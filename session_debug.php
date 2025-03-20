<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

echo "<h2>Session Debug Info</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>Session Settings</h2>";
echo "Session save path: " . session_save_path() . "<br>";
echo "Session name: " . session_name() . "<br>";
echo "Session ID: " . session_id() . "<br>";

echo "<h2>Current User</h2>";
echo "Username: " . ($_SESSION['username'] ?? 'Not set') . "<br>";
echo "User Type: " . ($_SESSION['uType'] ?? 'Not set') . "<br>";
echo "Logged in: " . (isset($_SESSION['logged_in']) ? 'Yes' : 'No') . "<br>";
?>