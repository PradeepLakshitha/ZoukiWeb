<?php
session_start();

// Redirect to login page if not logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

// Get logged-in username
$user_name = $_SESSION['username'];

// Handle logout
if (isset($_POST['logout'])) {
    session_destroy();
    setcookie("username", "", time() - 3600, "/"); // Clear remember me cookie
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZOUKI Home</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <!-- MDBootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.3.0/mdb.min.css" rel="stylesheet">

    <style>
        /* Page Structure */
        html, body {
            height: 100%;
            margin: 0;
            display: flex;
            flex-direction: column;
            background: #f8f9fa; /* Light background */
        }

        .container-custom {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        /* Header */
        .header {
            background: white;
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 30px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
        }

        .logo img {
            height: 50px;
        }

        .user-options {
            display: flex;
            align-items: center;
        }

        .user-options span {
            margin-right: 15px;
            font-weight: bold;
        }

        .btn-logout {
            background: #dc3545;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            border: none;
            transition: background 0.3s;
        }

        .btn-logout:hover {
            background: #c82333;
        }

        /* Card Section */
        .card-container {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 30px;
        }

        .custom-card {
            width: 260px;
            height: 320px;
            background-size: cover;
            background-position: center;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0px 10px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
            display: flex;
            align-items: flex-end;
        }

        .custom-card:hover {
            transform: translateY(-8px);
            box-shadow: 0px 15px 30px rgba(0, 0, 0, 0.2);
        }

        .custom-card h5 {
            background: rgba(255, 255, 255, 0.8); /* Semi-transparent white */
            color: #333; /* Suitable text color */
            padding: 10px;
            margin: 0;
            width: 100%;
            text-align: center;
            font-weight: bold;
            position: absolute;
            top: 0;
        }

        /* Footer */
        .footer {
            background: #78b85c;
            color: white;
            text-align: center;
            padding: 10px;
            width: 100%;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .card-container {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>

<!-- Header -->
<header class="header">
    <div class="logo">
        <img src="img/ZoukiLogo.svg" alt="ZOUKI Logo">
    </div>
    <div class="user-options">
        <span>Welcome, <?php echo htmlspecialchars($user_name); ?></span>
        <form method="post">
            <button type="submit" name="logout" class="btn btn-logout">Sign Out</button>
        </form>
    </div>
</header>

<!-- Main Content -->
<div class="container-custom">
    <h2>Welcome to Zouki Food Insights</h2>
    <p>Explore the food images, ingredients, and allergens</p>

    <!-- Card Section -->
    <div class="card-container">
        <?php
        $cards = [
            ["title" => "Hot Food Rotation", "image" => "img/2.png"],
            ["title" => "Premade Rotation", "image" => "img/3.png"],
            ["title" => "Theme Lunch", "image" => "img/4.png"],
            ["title" => "Zouki Catering", "image" => "img/3.png"]
        ];

        foreach ($cards as $card) {
            echo '<div class="custom-card" style="background-image: url(\'' . $card["image"] . '\');">
                    <h5>' . htmlspecialchars($card["title"]) . '</h5>
                  </div>';
        }
        ?>
    </div>
</div>

<!-- Footer -->
<footer class="footer">
    Copyright © <?php echo date("Y"); ?>. All rights reserved.
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- MDBootstrap JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.3.0/mdb.min.js"></script>

</body>
</html>
