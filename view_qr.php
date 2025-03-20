<?php
// This is a simple page to display the product information from a QR code
// It accepts a product ID and fetches the data from the database

// Include database connection
include 'db_connection.php';

$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
// Get product ID from query parameter
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    // If no valid ID provided, show error
    $error = "No valid product ID provided";
    $product = null;
} else {
    // Fetch the product data
    $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $error = "Product not found";
        $product = null;
    } else {
        $product = $result->fetch_assoc();

        // Get categories and groups
        $categories_query = "SELECT c.category_name 
                           FROM categories c
                           JOIN product_categories pc ON c.category_id = pc.category_id
                           WHERE pc.product_id = ?";
        $stmt = $conn->prepare($categories_query);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $categories_result = $stmt->get_result();

        $categories = [];
        while ($row = $categories_result->fetch_assoc()) {
            $categories[] = $row['category_name'];
        }

        $groups_query = "SELECT g.group_name 
                       FROM `groups` g
                       JOIN product_groups pg ON g.group_id = pg.group_id
                       WHERE pg.product_id = ?";
        $stmt = $conn->prepare($groups_query);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $groups_result = $stmt->get_result();

        $groups = [];
        while ($row = $groups_result->fetch_assoc()) {
            $groups[] = $row['group_name'];
        }

        $product['categories'] = implode(', ', $categories);
        $product['groups'] = implode(', ', $groups);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($product) ? htmlspecialchars($product['product_name']) : 'Product Info'; ?> - ZOUKI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script>
        // Tab switching function
        function showTab(tabId) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.style.display = 'none';
            });

            // Deactivate all tab buttons
            document.querySelectorAll('.info-tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Show the selected tab content
            document.getElementById(tabId).style.display = 'block';

            // Activate the clicked tab button
            event.currentTarget.classList.add('active');
        }
    </script>
    <style>
        :root {
            --primary-color: #4CAF50;
            --primary-light: #E8F5E9;
            --secondary-color: #2196F3;
            --warning-color: #FFC107;
            --danger-color: #F44336;
            --text-dark: #263238;
            --text-light: #546E7A;
            --border-radius: 12px;
            --box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        body {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Roboto, -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            padding: 0;
            color: var(--text-dark);
            line-height: 1.5;
        }

        .container {
            padding: 15px;
            max-width: 768px;
            margin: 0 auto;
        }

        .product-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            margin: 10px auto 20px;
        }

        .card-header {
            background: var(--primary-color);
            color: white;
            padding: 15px 20px;
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .zouki-logo {
            height: 30px;
            width: auto;
            filter: brightness(0) invert(1); /* Makes the logo white */
        }

        .logo-text {
            font-size: 1.4rem;
            font-weight: 700;
            letter-spacing: 1px;
            color: white;
        }

        .card-body {
            padding: 0;
        }

        .product-info-header {
            display: flex;
            align-items: flex-start;
            padding: 20px;
            background: linear-gradient(to bottom, rgba(232, 245, 233, 0.5), white);
        }

        .product-img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-right: 15px;
        }

        .product-img-placeholder {
            width: 100px;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e9ecef;
            border-radius: 8px;
            margin-right: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .product-basic-info {
            flex: 1;
        }

        .product-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0 0 8px 0;
            color: var(--text-dark);
        }

        .health-badge {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 10px;
            gap: 5px;
        }

        .health-green {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .health-amber {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .health-red {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .info-tabs {
            display: flex;
            border-bottom: 1px solid #eee;
        }

        .info-tab {
            flex: 1;
            text-align: center;
            padding: 12px 5px;
            cursor: pointer;
            font-weight: 500;
            color: var(--text-light);
            background: white;
            transition: all 0.2s ease;
            border-bottom: 3px solid transparent;
        }

        .info-tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .info-content {
            padding: 20px;
        }

        .allergens-section {
            background-color: rgba(255, 193, 7, 0.05);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid var(--warning-color);
        }

        .allergens-section .title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .allergens-section .content {
            color: var(--text-light);
            font-size: 0.95rem;
        }

        .ingredients-section .title {
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-dark);
        }

        .ingredients-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            grid-gap: 10px;
            font-size: 0.9rem;
        }

        .ingredient-item {
            padding: 8px 10px;
            background-color: #f8f9fa;
            border-radius: 6px;
            display: flex;
            align-items: center;
        }

        .ingredient-item::before {
            content: "•";
            color: var(--primary-color);
            font-weight: bold;
            margin-right: 8px;
        }

        .ingredients-section-title {
            grid-column: 1 / -1;
            margin-top: 10px;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--primary-color);
            font-size: 0.95rem;
        }

        .error-container {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        @media (max-width: 576px) {
            .ingredients-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <?php if (isset($error)): ?>
        <div class="error-container">
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            </div>
            <a href="home.php" class="btn btn-primary mt-3">
                <i class="fas fa-home me-2"></i> Go to Home
            </a>
        </div>
    <?php else: ?>
        <div class="product-card">
            <div class="card-header">
                <span><i class="fas fa-info-circle me-2"></i>Product Information</span>

                <img src="/img/ZoukiLogo.svg" alt="ZOUKI" class="zouki-logo">
            </div>
            <div class="card-body">
                <div class="product-info-header">
                    <?php if (!empty($product['image']) && file_exists($product['image'])): ?>
                        <img src="<?php echo $base_url .'/'. htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="product-img">
                    <?php else: ?>
                        <div class="product-img-placeholder">
                            <i class="fas fa-utensils fa-2x text-muted"></i>
                        </div>
                    <?php endif; ?>

                    <div class="product-basic-info">
                        <h1 class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></h1>

                        <?php
                        $badge_class = '';
                        $badge_text = '';
                        switch ($product['healthy_option']) {
                            case 'Green':
                                $badge_class = 'health-green';
                                $badge_text = '<i class="fas fa-check-circle"></i> Healthy Choice';
                                break;
                            case 'Amber':
                                $badge_class = 'health-amber';
                                $badge_text = '<i class="fas fa-exclamation-circle"></i> AMBER';
                                break;
                            case 'Red':
                                $badge_class = 'health-red';
                                $badge_text = '<i class="fas fa-times-circle"></i> RED';
                                break;
                        }
                        ?>

                        <div class="health-badge <?php echo $badge_class; ?>">
                            <?php echo $badge_text; ?>
                        </div>
                    </div>
                </div>

                <!-- Tabbed interface for product info -->
                <div class="info-tabs">
                    <div class="info-tab active" onclick="showTab('allergens-tab')">
                        <i class="fas fa-exclamation-triangle"></i> Allergens
                    </div>
                    <div class="info-tab" onclick="showTab('ingredients-tab')">
                        <i class="fas fa-carrot"></i> Ingredients
                    </div>
                </div>

                <div class="info-content">
                    <!-- Allergens Tab -->
                    <div id="allergens-tab" class="tab-content">
                        <?php if (!empty($product['allergens'])): ?>
                            <div class="allergens-section">
                                <div class="title">
                                    <i class="fas fa-exclamation-triangle text-warning"></i> Allergen Information
                                </div>
                                <div class="content">
                                    <?php echo htmlspecialchars($product['allergens']); ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-muted">No allergens information available.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Ingredients Tab (hidden initially) -->
                    <div id="ingredients-tab" class="tab-content" style="display: none;">
                        <?php if (!empty($product['ingredients'])): ?>
                            <div class="ingredients-section">
                                <div class="title">
                                    <i class="fas fa-carrot text-success"></i> Ingredients List
                                </div>
                                <div class="ingredients-grid">
                                    <?php
                                    // Parse ingredients into a more structured format
                                    $ingredients_text = $product['ingredients'];

                                    // Determine the delimiter - line breaks, commas, bullets, etc.
                                    if (strpos($ingredients_text, "\n") !== false) {
                                        $ingredients = array_filter(explode("\n", $ingredients_text));
                                    } elseif (strpos($ingredients_text, "•") !== false) {
                                        $ingredients = array_filter(explode("•", $ingredients_text));
                                    } elseif (strpos($ingredients_text, "-") !== false && substr_count($ingredients_text, "-") > 1) {
                                        $ingredients = array_filter(explode("-", $ingredients_text));
                                    } else {
                                        $ingredients = array_filter(explode(',', $ingredients_text));
                                    }

                                    // List of possible section headers
                                    $section_headers = ["Garnish:", "Vegetables:", "For the sauce:", "For the marinade:",
                                        "For the filling:", "For the topping:", "For the dressing:",
                                        "Main ingredients:", "Spices:", "Herbs:", "Seasonings:"];

                                    // Process and display ingredients in a grid
                                    foreach ($ingredients as $ingredient) {
                                        $ingredient = trim($ingredient);
                                        if (empty($ingredient)) continue;

                                        // Check if this is a section header
                                        $is_section_header = false;
                                        foreach ($section_headers as $header) {
                                            if (strcasecmp(trim($ingredient), trim($header)) === 0 ||
                                                stripos($ingredient, $header) === 0) {
                                                $is_section_header = true;
                                                echo '<div class="ingredients-section-title">' . htmlspecialchars($ingredient) . '</div>';
                                                break;
                                            }
                                        }

                                        if (!$is_section_header) {
                                            echo '<div class="ingredient-item">' . htmlspecialchars($ingredient) . '</div>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-muted">No ingredients information available.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>