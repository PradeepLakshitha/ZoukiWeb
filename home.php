<?php
require_once 'session_check.php';
check_session(); // All authenticated users can access home
include 'db_connection.php';

// Get logged-in username
$user_name = $_SESSION['username'];

// Fetch all categories from the database
$categories_query = "SELECT c.category_id, c.category_name, COUNT(pc.product_id) as product_count 
                    FROM categories c 
                    LEFT JOIN product_categories pc ON c.category_id = pc.category_id 
                    GROUP BY c.category_id 
                    HAVING COUNT(pc.product_id) > 0
                    ORDER BY c.category_name ASC";
$categories_result = $conn->query($categories_query);
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

    <!-- SweetAlert2 for Notifications -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
            padding: 20px;
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
            width: 100%;
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
            cursor: pointer;
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

        .product-count {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(76, 175, 80, 0.8);
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        /* Footer */
        .footer {
            background: #78b85c;
            color: white;
            text-align: center;
            padding: 10px;
            width: 100%;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0px 4px 15px rgba(0, 0, 0, 0.05);
            margin-top: 40px;
        }

        .empty-state i {
            font-size: 4rem;
            color: #adb5bd;
            margin-bottom: 20px;
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
        <form method="post" action="logout.php">
            <button type="submit" class="btn btn-logout">Sign Out</button>
        </form>
        <?php if (isset($_SESSION['uType']) && ($_SESSION['uType'] === 'Admin' || $_SESSION['uType'] === 'Manager')): ?>
            <a href="dashboard.php" style="margin-left: 20px;">
                <img src="img/settings.svg" alt="Settings">
            </a>
        <?php else: ?>
            <a href="#" onclick="showNoAccessPopup();" style="margin-left: 20px;">
                <img src="img/settings.svg" alt="Settings">
            </a>
        <?php endif; ?>
    </div>
</header>

<!-- Main Content -->
<div class="container-custom">
    <h2>Welcome to Zouki Food Insights</h2>
    <p>Explore categories to find food products, ingredients, and recipes</p>

    <!-- Card Section -->
    <div class="card-container">
        <?php
        if ($categories_result->num_rows > 0) {
            // Images to cycle through for categories
            $backgrounds = ["img/2.png", "img/2.png", "img/3.png", "img/4.png"];
            $bg_index = 0;

            while ($category = $categories_result->fetch_assoc()) {
                // Cycle through background images
                $bg_image = $backgrounds[$bg_index % count($backgrounds)];
                $bg_index++;

                echo '<div class="custom-card" style="background-image: url(\'' . $bg_image . '\');" 
                        onclick="window.location.href=\'category_products.php?id=' . $category['category_id'] . '\'">
                        <h5>' . htmlspecialchars($category['category_name']) . '</h5>
                        <span class="product-count">' . $category['product_count'] . ' Products</span>
                      </div>';
            }
        } else {
            echo '<div class="empty-state">
                    <i class="fas fa-utensils"></i>
                    <h3>No Categories Found</h3>
                    <p>There are no product categories available yet.</p>
                  </div>';
        }
        ?>
    </div>
</div>

<!-- Footer -->
<footer class="footer">
    Copyright © <?php echo date("Y"); ?>. All rights reserved.
</footer>

<script>
    function showNoAccessPopup() {
        Swal.fire({
            icon: 'error',
            title: 'Access Denied',
            text: "You don't have permission to access the dashboard.",
        });
    }
</script>

</body>
</html>