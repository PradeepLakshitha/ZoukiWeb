<!-- Header -->
    <header class="header" id="header">
        <div class="logo">
            <img src="img/ZoukiLogo.svg" alt="ZOUKI Logo">
        </div>
        <div class="user-options">
            <span class="welcome-text">Welcome, <?php echo htmlspecialchars($user_name); ?></span>
            <form method="post" action="logout.php">
                <button type="submit" class="btn-logout">
                    <i class="fas fa-sign-out-alt me-1"></i> Sign Out
                </button>
            </form>
            <?php if (isset($_SESSION['uType']) && ($_SESSION['uType'] === 'Admin' || $_SESSION['uType'] === 'Manager')): ?>
                <a href="dashboard.php" class="settings-link">
                    <img src="img/settings.svg" alt="Settings">
                </a>
            <?php else: ?>
                <a href="#" onclick="showNoAccessPopup();" class="settings-link">
                    <img src="img/settings.svg" alt="Settings">
                </a>
            <?php endif; ?>
        </div>
    </header><?php
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
    <title>ZOUKI Food Insights</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <!-- Google Fonts - Montserrat & Roboto -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <!-- SweetAlert2 for Notifications -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- AOS - Animate On Scroll Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <!-- Three.js for background effects -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>

    <style>
        :root {
            --primary-color: #4CAF50;
            --primary-light: #7AE582;
            --primary-dark: #388E3C;
            --secondary-color: #00BCD4;
            --accent-color: #7C4DFF;
            --dark-color: #212121;
            --light-color: #F5F5F5;
            --success-color: #4CAF50;
            --warning-color: #FFC107;
            --danger-color: #F44336;
            
            --header-height: 70px;
            --transition-speed: 0.3s;
            --card-border-radius: 16px;
            --shadow-sm: 0 4px 6px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 8px 15px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 15px 30px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 20px 40px rgba(0, 0, 0, 0.15);
            
            --gradient-primary: linear-gradient(135deg, #4CAF50, #8BC34A);
            --gradient-secondary: linear-gradient(135deg, #00BCD4, #03A9F4);
            --gradient-accent: linear-gradient(135deg, #7C4DFF, #B388FF);
        }

        /* Page preloader */
        .preloader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }
        
        .preloader.fade-out {
            opacity: 0;
            visibility: hidden;
        }
        
        .preloader-spinner {
            width: 60px;
            height: 60px;
            border: 5px solid rgba(76, 175, 80, 0.2);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            font-family: 'Roboto', sans-serif;
            background-color: #f8f9fa;
            color: var(--dark-color);
            overflow-x: hidden;
            scroll-behavior: smooth;
            position: relative;
        }

        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            width: 100%;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
        }

        /* Background Canvas and Effects */
        #bg-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
            opacity: 0.3; /* Reduced opacity to make bubbles more visible */
        }

        /* Background Bubble Container */
        .background-bubbles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            overflow: hidden;
            z-index: 0;
            pointer-events: none;
        }
        
        /* Background Bubbles */
        .bg-bubble {
            position: absolute;
            border-radius: 50%;
            background: rgba(76, 175, 80, 0.3); /* Increased opacity */
            animation: float-bg linear infinite;
            animation-play-state: running;
            pointer-events: none;
            box-shadow: 0 0 20px rgba(76, 175, 80, 0.5); /* Increased glow */
            transition: background-color 0.5s ease;
            border: 1px solid rgba(255, 255, 255, 0.2); /* Added subtle border */
            z-index: 1; /* Ensure bubbles are visible */
            opacity: 0; /* Start invisible */
            will-change: transform, opacity; /* Performance optimization */
        }
        
        @keyframes float-bg {
            0% {
                transform: translateY(100vh) translateX(0) rotate(0deg);
                opacity: 0;
                filter: hue-rotate(0deg);
            }
            5% { /* Quicker fade in */
                opacity: 0.8; /* Increased visibility */
            }
            25% {
                filter: hue-rotate(90deg);
            }
            50% {
                filter: hue-rotate(180deg);
            }
            75% {
                filter: hue-rotate(270deg);
            }
            95% { /* Longer visibility */
                opacity: 0.8; /* Increased visibility */
            }
            100% {
                transform: translateY(-20vh) translateX(20px) rotate(360deg);
                opacity: 0;
                filter: hue-rotate(360deg);
            }
        }
        
        /* Particle Effects */
        .particle {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
            opacity: 0.5;
            z-index: -1;
        }

        /* Header Styles */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: var(--header-height);
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: var(--shadow-sm);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
            z-index: 1000;
            transition: all var(--transition-speed) ease;
        }

        .header.scrolled {
            height: 60px;
            box-shadow: var(--shadow-md);
        }

        .logo {
            display: flex;
            align-items: center;
        }

        .logo img {
            height: 40px;
            transition: all var(--transition-speed) ease;
        }

        .header.scrolled .logo img {
            height: 35px;
        }

        .user-options {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .welcome-text {
            font-weight: 500;
            transition: all var(--transition-speed) ease;
        }

        .header.scrolled .welcome-text {
            font-size: 0.9rem;
        }

        .btn-logout {
            background: var(--danger-color);
            color: white;
            padding: 8px 16px;
            border-radius: 30px;
            border: none;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(244, 67, 54, 0.3);
        }

        .btn-logout:hover {
            background: #E53935;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(244, 67, 54, 0.4);
        }

        .settings-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.8);
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }

        .settings-link:hover {
            transform: rotate(180deg);
            background: var(--light-color);
            box-shadow: var(--shadow-md);
        }

        .settings-link img {
            width: 22px;
            height: 22px;
        }

        /* Main Content */
        .container-custom {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: calc(var(--header-height) + 2rem) 2rem 2rem;
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            position: relative;
            z-index: 2; /* Ensure content is above bubbles */
        }

        .hero-section {
            text-align: center;
            margin-bottom: 3rem;
            width: 100%;
        }

        .hero-title {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: inline-block;
            font-weight: 700;
        }

        .hero-subtitle {
            font-size: 1.2rem;
            color: #6c757d;
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* Card Container */
        .card-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
            width: 100%;
            margin-top: 1rem;
            padding-bottom: 1rem; /* Add padding to ensure content isn't cut off */
        }

        /* Custom Card */
        .custom-card {
            position: relative;
            width: 100%;
            height: 300px;
            border-radius: var(--card-border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: all 0.5s cubic-bezier(0.23, 1, 0.32, 1);
            cursor: pointer;
            perspective: 1000px;
        }

        .custom-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-hover);
        }

        .custom-card:hover .card-background {
            transform: scale(1.1);
        }

        .card-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            transition: transform 0.5s cubic-bezier(0.23, 1, 0.32, 1);
            z-index: 1;
        }

        .card-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                to bottom,
                rgba(0, 0, 0, 0.4) 0%,
                rgba(0, 0, 0, 0.2) 40%,
                rgba(0, 0, 0, 0.2) 60%,
                rgba(0, 0, 0, 0.6) 100%
            );
            z-index: 2;
        }

        .card-content {
            position: absolute;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            padding: 1.5rem;
            z-index: 3;
            color: white;
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .card-bottom {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .explore-link {
            color: white;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
            opacity: 0.8;
        }

        .explore-link:hover {
            opacity: 1;
            transform: translateX(5px);
        }

        .product-count {
            background: var(--primary-color);
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        /* Empty State */
        .empty-state {
            width: 100%;
            max-width: 600px;
            margin: 3rem auto;
            background: white;
            border-radius: var(--card-border-radius);
            padding: 3rem;
            text-align: center;
            box-shadow: var(--shadow-md);
        }

        .empty-state i {
            font-size: 4rem;
            color: #adb5bd;
            margin-bottom: 1.5rem;
        }

        .empty-state h3 {
            margin-bottom: 1rem;
            color: #343a40;
        }

        .empty-state p {
            color: #6c757d;
            margin-bottom: 0;
        }

        /* Footer */
        .footer {
            background: var(--gradient-primary);
            color: white;
            text-align: center;
            padding: 1.5rem;
            margin-top: 3rem;
            position: relative;
            overflow: hidden;
        }

        .footer-content {
            position: relative;
            z-index: 2;
        }

        .footer-wave {
            position: absolute;
            top: -100px;
            left: 0;
            width: 100%;
            height: 100px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1440 320'%3E%3Cpath fill='%23ffffff' fill-opacity='1' d='M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,128C672,128,768,160,864,176C960,192,1056,192,1152,181.3C1248,171,1344,149,1392,138.7L1440,128L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z'%3E%3C/path%3E%3C/svg%3E");
            background-size: cover;
            z-index: 1;
        }

        /* Floating Bubbles Animation */
        .bubble {
            position: absolute;
            bottom: -20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            pointer-events: none;
            animation: float linear infinite;
        }

        @keyframes float {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(-1000px) rotate(720deg);
                opacity: 0;
            }
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .header {
                padding: 0 1.5rem;
            }

            .hero-title {
                font-size: 2.2rem;
            }

            .card-container {
                gap: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .header {
                padding: 0 1rem;
            }

            .container-custom {
                padding: calc(var(--header-height) + 1.5rem) 1rem 1.5rem;
                width: 100%;
                max-width: 100%;
            }

            .welcome-text {
                display: none;
            }

            .hero-title {
                font-size: 2rem;
            }

            .hero-subtitle {
                font-size: 1.1rem;
            }

            .card-container {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 1.2rem;
                width: 100%;
            }

            .custom-card {
                height: 280px;
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            :root {
                --header-height: 60px;
            }
            
            .header {
                padding: 0 0.8rem;
            }

            .container-custom {
                padding: calc(var(--header-height) + 1.5rem) 0.8rem 2rem;
                overflow-x: hidden;
                width: 100%;
            }
            
            .hero-title {
                font-size: 1.8rem;
            }

            .hero-subtitle {
                font-size: 1rem;
                padding: 0 0.5rem;
            }

            .card-container {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                padding: 0 0.5rem;
                width: 100%;
            }

            .custom-card {
                height: 240px;
                width: 100%;
                max-width: 100%;
                margin: 0 auto;
            }

            .card-title {
                font-size: 1.3rem;
            }

            .empty-state {
                padding: 2rem 1rem;
                margin: 2rem auto;
            }
            
            .footer {
                margin-top: 2rem;
            }
        }
        
        /* Fix for very small screens */
        @media (max-width: 360px) {
            .container-custom {
                padding-top: calc(var(--header-height) + 1rem);
            }
            
            .hero-title {
                font-size: 1.6rem;
            }
            
            .custom-card {
                height: 220px;
            }
            
            .btn-logout {
                font-size: 0.85rem;
                padding: 6px 12px;
            }
            
            .settings-link {
                width: 36px;
                height: 36px;
            }
        }
    </style>
</head>
<body>
    <!-- Preloader -->
    <div class="preloader" id="preloader">
        <div class="preloader-spinner"></div>
    </div>
    
    <!-- Background Canvas -->
    <canvas id="bg-canvas"></canvas>
    
    <!-- Background Bubbles Container -->
    <div class="background-bubbles" id="background-bubbles"></div>
    <header class="header" id="header">
        <div class="logo">
            <img src="img/ZoukiLogo.svg" alt="ZOUKI Logo">
        </div>
        <div class="user-options">
            <span class="welcome-text">Welcome, <?php echo htmlspecialchars($user_name); ?></span>
            <form method="post" action="logout.php">
                <button type="submit" class="btn-logout">
                    <i class="fas fa-sign-out-alt me-1"></i> Sign Out
                </button>
            </form>
            <?php if (isset($_SESSION['uType']) && ($_SESSION['uType'] === 'Admin' || $_SESSION['uType'] === 'Manager')): ?>
                <a href="dashboard.php" class="settings-link">
                    <img src="img/settings.svg" alt="Settings">
                </a>
            <?php else: ?>
                <a href="#" onclick="showNoAccessPopup();" class="settings-link">
                    <img src="img/settings.svg" alt="Settings">
                </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container-custom">
        <div class="hero-section" data-aos="fade-up" data-aos-duration="800">
            <h1 class="hero-title">Welcome to Zouki Food Insights</h1>
            <p class="hero-subtitle">Discover our food categories to explore nutritional information, ingredients, and recipes that promote healthier eating choices.</p>
        </div>

        <!-- Card Section -->
        <div class="card-container">
            <?php
            if ($categories_result->num_rows > 0) {
                // Images to cycle through for categories
                $backgrounds = ["img/2.png", "img/2.png", "img/3.png", "img/4.png"];
                $bg_index = 0;
                $delay = 0;

                while ($category = $categories_result->fetch_assoc()) {
                    // Cycle through background images
                    $bg_image = $backgrounds[$bg_index % count($backgrounds)];
                    $bg_index++;
                    $delay += 100;

                    echo '<div class="custom-card" data-aos="fade-up" data-aos-duration="800" data-aos-delay="' . $delay . '" 
                            onclick="window.location.href=\'category_products.php?id=' . $category['category_id'] . '\'">
                            <div class="card-background" style="background-image: url(\'' . $bg_image . '\');"></div>
                            <div class="card-overlay"></div>
                            <div class="card-content">
                                <h3 class="card-title">' . htmlspecialchars($category['category_name']) . '</h3>
                                <div class="card-bottom">
                                    <a href="category_products.php?id=' . $category['category_id'] . '" class="explore-link">
                                        Explore <i class="fas fa-arrow-right"></i>
                                    </a>
                                    <span class="product-count">
                                        <i class="fas fa-box me-1"></i> ' . $category['product_count'] . ' Products
                                    </span>
                                </div>
                            </div>
                          </div>';
                }
            } else {
                echo '<div class="empty-state" data-aos="fade-up" data-aos-duration="800">
                        <i class="fas fa-utensils"></i>
                        <h3>No Categories Found</h3>
                        <p>There are no product categories available yet. Check back soon for updates!</p>
                      </div>';
            }
            ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-wave"></div>
        <div class="footer-content">
            <p>Copyright © <?php echo date("Y"); ?>. ~Zouki Group of Companies~ All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Initialize AOS animation library
        document.addEventListener('DOMContentLoaded', function() {
            // Show preloader while page elements load
            const preloader = document.getElementById('preloader');
            
            // Initialize background bubbles behind the scenes while preloader is showing
            createBackgroundBubbles();
            
            // Initialize AOS animation library
            AOS.init({
                once: true,
                disable: 'mobile' // Disable AOS on mobile for better performance
            });

            // Create floating bubbles in footer
            createBubbles();
            
            // Initialize background effect
            initBackgroundEffect();
            
            // Initialize header scroll effect
            initScrollEffect();
            
            // Hide preloader after everything is initialized
            window.addEventListener('load', function() {
                setTimeout(function() {
                    preloader.classList.add('fade-out');
                    setTimeout(function() {
                        preloader.style.display = 'none';
                    }, 500);
                }, 500);
            });
            
            // Force redraw for mobile devices
            setTimeout(function() {
                window.dispatchEvent(new Event('resize'));
            }, 500);
        });

        // Show no access popup
        function showNoAccessPopup() {
            Swal.fire({
                icon: 'error',
                title: 'Access Denied',
                text: "You don't have permission to access the dashboard.",
                backdrop: `rgba(0,0,0,0.4)`,
                showConfirmButton: true,
                confirmButtonColor: '#4CAF50',
                timer: 3000
            });
        }

        // Create floating bubbles in footer
        function createBubbles() {
            const footer = document.querySelector('.footer');
            const bubbleCount = 20;
            
            for (let i = 0; i < bubbleCount; i++) {
                const bubble = document.createElement('div');
                bubble.classList.add('bubble');
                
                // Random size between 10px and 50px
                const size = Math.random() * 40 + 10;
                bubble.style.width = `${size}px`;
                bubble.style.height = `${size}px`;
                
                // Random position along width of footer
                const left = Math.random() * 100;
                bubble.style.left = `${left}%`;
                
                // Random animation duration between 10-25s
                const duration = Math.random() * 15 + 10;
                bubble.style.animationDuration = `${duration}s`;
                
                // Random animation delay
                const delay = Math.random() * 5;
                bubble.style.animationDelay = `${delay}s`;
                
                footer.appendChild(bubble);
            }
        }
        
        // Create background bubbles
        function createBackgroundBubbles() {
            const bubbleContainer = document.getElementById('background-bubbles');
            
            // Clear any existing bubbles first
            bubbleContainer.innerHTML = '';
            
            const bubbleCount = 60; // Increased number of bubbles
            
            // Define more vibrant base colors for bubbles with higher opacity
            const colors = [
                'rgba(76, 175, 80, 0.5)',   // Green (primary) - more opaque
                'rgba(33, 150, 243, 0.5)',  // Blue - more opaque
                'rgba(156, 39, 176, 0.5)',  // Purple - more opaque
                'rgba(255, 193, 7, 0.5)',   // Amber - more opaque
                'rgba(0, 188, 212, 0.5)',   // Cyan - more opaque
                'rgba(233, 30, 99, 0.5)',   // Pink - more opaque
                'rgba(255, 87, 34, 0.5)'    // Deep Orange - more opaque
            ];
            
            // Create bubbles in smaller batches with timeouts to avoid layout thrashing
            const batchSize = 10;
            const batchCount = Math.ceil(bubbleCount / batchSize);
            
            function createBubbleBatch(batchIndex) {
                if (batchIndex >= batchCount) return;
                
                const startIndex = batchIndex * batchSize;
                const endIndex = Math.min(startIndex + batchSize, bubbleCount);
                
                for (let i = startIndex; i < endIndex; i++) {
                    const bubble = document.createElement('div');
                    bubble.classList.add('bg-bubble');
                    
                    // Random size between 30px and 120px (increased size)
                    const size = Math.random() * 90 + 30;
                    bubble.style.width = `${size}px`;
                    bubble.style.height = `${size}px`;
                    
                    // Random position along width
                    const left = Math.random() * 100;
                    bubble.style.left = `${left}%`;
                    
                    // Random position along height (distribution across entire screen)
                    const bottom = Math.random() * 200 - 50; // From -50% to 150% for full coverage
                    bubble.style.bottom = `${bottom}%`;
                    
                    // Random animation duration between 15-35s
                    const duration = Math.random() * 20 + 15;
                    bubble.style.animationDuration = `${duration}s`;
                    
                    // Minimal animation delay for initial batch, increased delays for later batches
                    // This creates a more natural staggered effect without initial pause
                    const delay = Math.random() * 5 + (batchIndex * 0.5);
                    bubble.style.animationDelay = `${delay}s`;
                    
                    // Random color from our color array
                    const baseColor = colors[Math.floor(Math.random() * colors.length)];
                    bubble.style.backgroundColor = baseColor;
                    
                    // Add a pronounced glow effect matching the bubble color
                    const glowColor = baseColor.replace(/[\d.]+\)$/, '0.6)');
                    bubble.style.boxShadow = `0 0 ${Math.floor(size/4)}px ${glowColor}`;
                    
                    // Add initial transform to avoid static appearance
                    const initialProgress = Math.random() * 100;
                    bubble.style.transform = `translateY(${100 - initialProgress}vh) translateX(${initialProgress * 0.2}px) rotate(${initialProgress * 3.6}deg)`;
                    
                    bubbleContainer.appendChild(bubble);
                }
                
                // Schedule next batch
                setTimeout(() => createBubbleBatch(batchIndex + 1), 100);
            }
            
            // Start creating bubbles in batches
            createBubbleBatch(0);
        }

        // Initialize Three.js background effect
        function initBackgroundEffect() {
            const canvas = document.getElementById('bg-canvas');
            const renderer = new THREE.WebGLRenderer({
                canvas: canvas,
                antialias: true,
                alpha: true
            });
            
            renderer.setSize(window.innerWidth, window.innerHeight);
            renderer.setPixelRatio(window.devicePixelRatio > 1 ? 2 : 1);
            
            const scene = new THREE.Scene();
            const camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 0.1, 1000);
            camera.position.set(0, 0, 5);
            
            // Create particles
            const particlesGeometry = new THREE.BufferGeometry();
            const particlesCount = 1000;
            
            const posArray = new Float32Array(particlesCount * 3);
            const scales = new Float32Array(particlesCount);
            
            for (let i = 0; i < particlesCount * 3; i++) {
                posArray[i] = (Math.random() - 0.5) * 10;
            }
            
            for (let i = 0; i < particlesCount; i++) {
                scales[i] = Math.random();
            }
            
            particlesGeometry.setAttribute('position', new THREE.BufferAttribute(posArray, 3));
            particlesGeometry.setAttribute('scale', new THREE.BufferAttribute(scales, 1));
            
            // Material
            const particlesMaterial = new THREE.PointsMaterial({
                size: 0.015,
                color: 0x4CAF50,
                transparent: true,
                opacity: 0.8,
                sizeAttenuation: true
            });
            
            // Points
            const particlesMesh = new THREE.Points(particlesGeometry, particlesMaterial);
            scene.add(particlesMesh);
            
            // Animation
            const clock = new THREE.Clock();
            
            const animate = () => {
                const elapsedTime = clock.getElapsedTime();
                
                particlesMesh.rotation.y = elapsedTime * 0.05;
                particlesMesh.rotation.x = elapsedTime * 0.02;
                
                // Render
                renderer.render(scene, camera);
                
                // Call animate again on the next frame
                window.requestAnimationFrame(animate);
            };
            
            animate();
            
            // Handle window resize
            window.addEventListener('resize', () => {
                // Update sizes
                camera.aspect = window.innerWidth / window.innerHeight;
                camera.updateProjectionMatrix();
                
                // Update renderer
                renderer.setSize(window.innerWidth, window.innerHeight);
            });
        }

        // Initialize header scroll effect
        function initScrollEffect() {
            const header = document.getElementById('header');
            
            window.addEventListener('scroll', () => {
                if (window.scrollY > 50) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            });
        }
    </script>
</body>
</html>