<?php
require_once 'session_check.php';
check_session(); // All authenticated users can access
include 'db_connection.php';

// Ensure the product ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: home.php");
    exit();
}

$product_id = (int)$_GET['id'];

// Get the product information with its categories and groups
/*$product_query = "SELECT p.*,
                  GROUP_CONCAT(DISTINCT c.category_name ORDER BY c.category_name SEPARATOR ', ') as categories,
                  GROUP_CONCAT(DISTINCT g.group_name ORDER BY g.group_name SEPARATOR ', ') as groups
                  FROM products p
                  LEFT JOIN product_categories pc ON p.product_id = pc.product_id
                  LEFT JOIN categories c ON pc.category_id = c.category_id
                  LEFT JOIN product_groups pg ON p.product_id = pg.product_id
                  LEFT JOIN groups g ON pg.group_id = g.group_id
                  WHERE p.product_id = ?
                  GROUP BY p.product_id";*/

$product_query = "SELECT p.*, 
                  GROUP_CONCAT(DISTINCT c.category_name ORDER BY c.category_name SEPARATOR ', ') as categories,
                  GROUP_CONCAT(DISTINCT g.group_name ORDER BY g.group_name SEPARATOR ', ') as `groups`
                  FROM products p
                  LEFT JOIN product_categories pc ON p.product_id = pc.product_id
                  LEFT JOIN categories c ON pc.category_id = c.category_id
                  LEFT JOIN product_groups pg ON p.product_id = pg.product_id
                  LEFT JOIN `groups` g ON pg.group_id = g.group_id
                  WHERE p.product_id = ?
                  GROUP BY p.product_id";

$stmt = $conn->prepare($product_query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: home.php");
    exit();
}

$product = $result->fetch_assoc();

// Get categories to determine back link
$category_query = "SELECT c.category_id, c.category_name 
                   FROM categories c
                   JOIN product_categories pc ON c.category_id = pc.category_id
                   WHERE pc.product_id = ?
                   LIMIT 1";
$stmt = $conn->prepare($category_query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$category_result = $stmt->get_result();
$back_link = "home.php";
$category_name = "";

if ($category_result->num_rows > 0) {
    $category = $category_result->fetch_assoc();
    $back_link = "category_products.php?id=" . $category['category_id'];
    $category_name = $category['category_name'];
}

// Create the link for the QR code - this will point to view_qr.php
//$qr_url = "view_qr.php?id=" . $product_id;
//$qr_url = "http://54.206.221.88/view_qr.php?id=" . $product_id;
$qr_url = "http://54.206.221.88/qr/" . $product_id;

// Store the raw data for the test modal
$qr_data = array(
    'id' => $product_id,
    'name' => $product['product_name'],
    'allergens' => $product['allergens'],
    'ingredients' => $product['ingredients'],
    'healthy_option' => $product['healthy_option']
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['product_name']); ?> - Recipe - ZOUKI</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <!-- jsPDF for PDF Generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <!-- QR Code Library -->
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>

    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .header {
            background: white;
            padding: 15px 30px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo img {
            height: 50px;
        }

        .user-options {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .container-custom {
            flex: 1;
            padding: 30px 15px;
        }

        .recipe-navigation {
            background: white;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .back-button {
            color: #6c757d;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: color 0.3s;
        }

        .back-button:hover {
            color: #212529;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .recipe-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0px 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .healthy-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .healthy-badge.green {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .healthy-badge.amber {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .healthy-badge.red {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .recipe-header {
            position: relative;
            width: 100%;
            background: rgba(0,0,0,0.02);
            display: flex;
            align-items: center;
            overflow: hidden;
            padding: 20px;
        }

        .recipe-image {
            width: 200px;
            height: 200px;
            border-radius: 10px;
            object-fit: cover;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .recipe-image-placeholder {
            width: 200px;
            height: 200px;
            border-radius: 10px;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .recipe-title-section {
            flex: 1;
            padding: 0 20px;
        }

        .recipe-qr-section {
            width: 150px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-left: 10px;
        }

        .recipe-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: #333;
        }

        .recipe-meta {
            display: flex;
            flex-wrap: wrap;
            margin-top: 15px;
            gap: 15px;
        }

        .recipe-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .recipe-content {
            padding: 30px;
        }

        .recipe-section {
            margin-bottom: 30px;
        }

        .recipe-section-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #4CAF50;
        }

        .recipe-section-title i {
            color: #4CAF50;
        }

        .ingredients-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .ingredients-list li {
            padding: 10px 15px;
            background: rgba(0,0,0,0.02);
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ingredients-list li i {
            color: #4CAF50;
            font-size: 0.8rem;
        }

        .instructions {
            white-space: pre-line;
            line-height: 1.6;
        }

        /* Completely redesigned ingredients section */
        .ingredients-container {
            background: #fcfcfc;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 3px 10px rgba(0,0,0,0.02);
        }

        .ingredients-title {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .ingredients-title i {
            background: #4CAF50;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-size: 0.9rem;
        }

        .ingredients-title h3 {
            margin: 0;
            font-size: 1.3rem;
            color: #333;
        }

        .ingredients-columns {
            column-count: 3;
            column-gap: 20px;
            margin: 0 0 10px 0;
        }

        /* Ingredient section title */
        .ingredient-section-title {
            background: #f1f8e9;
            color: #33691e;
            padding: 6px 10px;
            margin: 10px 0 8px 0;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.9rem;
            border-left: 3px solid #4CAF50;
            break-inside: avoid;
        }

        @media (max-width: 992px) {
            .ingredients-columns {
                column-count: 2;
            }
        }

        @media (max-width: 576px) {
            .ingredients-columns {
                column-count: 1;
            }
        }

        .ingredient-item {
            break-inside: avoid;
            padding: 5px 0;
            border-bottom: 1px dotted #f0f0f0;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            font-size: 0.85rem;
        }

        .ingredient-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .ingredient-amount {
            min-width: 50px;
            font-weight: 600;
            color: #4CAF50;
            font-size: 0.85rem;
        }

        .ingredient-name {
            flex: 1;
        }

        /* Recipe instructions section with topic highlighting */
        .recipe-instructions {
            background: #fcfcfc;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 3px 10px rgba(0,0,0,0.02);
        }

        .recipe-heading {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f0f0;
        }

        .recipe-heading i {
            background: #4CAF50;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }

        .recipe-heading h3 {
            margin: 0;
            font-size: 1.4rem;
            color: #333;
        }

        .instructions {
            line-height: 1.5;
        }

        .instructions p {
            margin-bottom: 0.75rem;
        }

        /* Recipe topic highlighting */
        .recipe-topic {
            background: #f1f8e9;
            padding: 10px 15px;
            margin: 15px 0 10px 0;
            border-radius: 6px;
            font-size: 1.1rem;
            font-weight: 600;
            color: #33691e;
            border-left: 4px solid #4CAF50;
        }

        /* Enhanced recipe steps styling */
        .recipe-steps {
            counter-reset: step-counter;
            list-style-type: none;
            padding: 0;
            margin: 20px 0;
        }

        .recipe-steps li {
            position: relative;
            padding: 15px 20px 15px 60px;
            margin-bottom: 15px;
            background: rgba(76, 175, 80, 0.05);
            border-radius: 8px;
            counter-increment: step-counter;
        }

        .recipe-steps li::before {
            content: counter(step-counter);
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: #4CAF50;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .footer {
            background: #78b85c;
            color: white;
            text-align: center;
            padding: 10px;
            width: 100%;
            margin-top: auto;
        }

        /* QR Code Container Styles */
        .qr-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0px 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .qr-section h4 {
            margin-bottom: 15px;
            color: #4CAF50;
        }

        .qr-code-container {
            display: inline-block;
            padding: 15px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            margin-bottom: 15px;
        }

        .qr-code-container img {
            max-width: 100%;
        }

        .qr-description {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .recipe-header {
                flex-direction: column;
                text-align: center;
            }

            .recipe-image, .recipe-image-placeholder {
                margin-bottom: 20px;
            }

            .recipe-title-section {
                padding: 0;
                margin-bottom: 20px;
            }

            .recipe-qr-section {
                width: 100%;
                margin-left: 0;
            }

            .recipe-meta {
                justify-content: center;
            }

            .recipe-navigation {
                flex-direction: column;
                gap: 15px;
            }

            .action-buttons {
                width: 100%;
                justify-content: center;
            }
        }

        @media print {
            .header, .recipe-navigation, .action-buttons, .footer {
                display: none !important;
            }

            .container-custom {
                padding: 0;
            }

            .recipe-card {
                box-shadow: none;
                margin: 0;
            }

            body {
                background-color: white;
            }
        }
    </style>
</head>
<body>
<!-- Header -->
<header class="header">
    <a href="home.php" class="logo">
        <img src="img/ZoukiLogo.svg" alt="ZOUKI Logo">
    </a>
    <div class="user-options">
        <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
        <a href="home.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-th"></i> Categories
        </a>
        <form method="post" action="logout.php" class="d-inline">
            <button type="submit" class="btn btn-danger btn-sm">
                <i class="fas fa-sign-out-alt"></i> Sign Out
            </button>
        </form>
    </div>
</header>

<div class="container-custom">
    <div class="recipe-navigation">
        <div>
            <a href="<?php echo $back_link; ?>" class="back-button">
                <i class="fas fa-arrow-left"></i>
                <?php echo !empty($category_name) ? 'Back to ' . htmlspecialchars($category_name) : 'Back to Categories'; ?>
            </a>
        </div>
        <div class="action-buttons">
            <button class="btn btn-outline-success" onclick="printQRCode()">
                <i class="fas fa-qrcode"></i> Print QR Code
            </button>
            <button class="btn btn-outline-primary" onclick="printRecipe()">
                <i class="fas fa-print"></i> Print Recipe
            </button>
            <button class="btn btn-success" onclick="downloadPDF()">
                <i class="fas fa-file-pdf"></i> Save as PDF
            </button>
        </div>
    </div>

    <input type="hidden" id="qr-raw-data" value='<?php echo htmlspecialchars(json_encode($qr_data), ENT_QUOTES); ?>'>

    <div class="recipe-card" id="recipe-card">
        <div class="recipe-header">
            <?php if (!empty($product['image']) && file_exists($product['image'])): ?>
                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="recipe-image">
            <?php else: ?>
                <div class="recipe-image-placeholder">
                    <i class="fas fa-utensils fa-3x text-muted"></i>
                </div>
            <?php endif; ?>

            <div class="recipe-title-section">
                <h1 class="recipe-title"><?php echo htmlspecialchars($product['product_name']); ?></h1>

                <?php
                switch ($product['healthy_option']) {
                    case 'Green':
                        echo '<span class="healthy-badge green"><i class="fas fa-check-circle"></i> Healthy Choice</span>';
                        break;
                    case 'Amber':
                        echo '<span class="healthy-badge amber"><i class="fas fa-exclamation-circle"></i> AMBER - Moderate</span>';
                        break;
                    case 'Red':
                        echo '<span class="healthy-badge red"><i class="fas fa-times-circle"></i> RED - Occasional</span>';
                        break;
                }
                ?>

                <div class="recipe-meta">
                    <?php if (!empty($product['categories'])): ?>
                        <div class="recipe-meta-item">
                            <i class="fas fa-tags"></i>
                            <span><?php echo htmlspecialchars($product['categories']); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($product['groups'])): ?>
                        <div class="recipe-meta-item">
                            <i class="fas fa-layer-group"></i>
                            <span><?php echo htmlspecialchars($product['groups']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- QR Code on right side of header -->
            <div class="recipe-qr-section">
                <div class="qr-code-container" id="qrcode"></div>
                <div class="qr-label">Scan for info</div>
                <a href="<?php echo $qr_url; ?>" target="_blank" class="btn btn-sm btn-outline-secondary qr-test-btn">
                    <i class="fas fa-external-link-alt"></i> View QR Link
                </a>
            </div>
        </div>

        <div class="recipe-content">
            <?php if (!empty($product['allergens'])): ?>
                <div class="recipe-section">
                    <h3 class="recipe-section-title">
                        <i class="fas fa-exclamation-triangle"></i> Allergens
                    </h3>
                    <div class="alert alert-warning">
                        <?php echo htmlspecialchars($product['allergens']); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($product['ingredients'])): ?>
                <div class="ingredients-container">
                    <div class="ingredients-title">
                        <i class="fas fa-carrot"></i>
                        <h3>Ingredients</h3>
                    </div>

                    <div class="ingredients-content">
                        <?php
                        // More intelligent ingredient parsing
                        // Try different delimiters - commas, new lines, or bullets
                        $ingredients_text = $product['ingredients'];

                        // First check if ingredients are already in a structured format
                        if (strpos($ingredients_text, "\n") !== false) {
                            // Split by new lines if present
                            $ingredients = array_filter(explode("\n", $ingredients_text));
                        } elseif (strpos($ingredients_text, "•") !== false) {
                            // Split by bullet points if present
                            $ingredients = array_filter(explode("•", $ingredients_text));
                        } elseif (strpos($ingredients_text, "-") !== false && substr_count($ingredients_text, "-") > 1) {
                            // Split by dashes if multiple are present
                            $ingredients = array_filter(explode("-", $ingredients_text));
                        } else {
                            // Default to comma separation
                            $ingredients = array_filter(explode(',', $ingredients_text));
                        }

                        $current_section = null;

                        // List of items that should be treated as section headers
                        $section_headers = ["Garnish:", "Vegetables:", "For the sauce:", "For the marinade:", "For the filling:", "For the topping:", "For the dressing:", "Main ingredients:", "Spices:", "Herbs:", "Seasonings:"];

                        // Process each ingredient
                        foreach ($ingredients as $ingredient) {
                            $ingredient = trim($ingredient);
                            if (empty($ingredient)) continue;

                            // Check if this might be a section header
                            $is_known_header = false;
                            foreach ($section_headers as $header) {
                                if (strcasecmp(trim($ingredient), trim($header)) === 0 ||
                                    stripos($ingredient, $header) === 0) {
                                    $is_known_header = true;
                                    break;
                                }
                            }

                            // Section headers are known headers or items without units/measurements
                            if ($is_known_header ||
                                (!preg_match('/\d+|cup|cups|oz|ounce|ounces|g|gram|grams|kg|ml|l|pound|pounds|lb|lbs|tbsp|tsp|tablespoon|teaspoon|pinch|dash/i', $ingredient)
                                    && strlen($ingredient) < 40
                                    && !preg_match('/[,]/', $ingredient))) {

                                // This looks like a section header
                                if ($current_section !== null) {
                                    // Close previous section if any
                                    echo '</div>';
                                }

                                echo '<div class="ingredient-section-title">' . htmlspecialchars($ingredient) . '</div>';
                                echo '<div class="ingredients-columns">';
                                $current_section = $ingredient;
                                continue;
                            }

                            // Start a section if we haven't already
                            if ($current_section === null) {
                                echo '<div class="ingredients-columns">';
                                $current_section = '';
                            }

                            echo '<div class="ingredient-item">';

                            // Try to identify quantity and unit from ingredient
                            if (preg_match('/^([\d\/.]+\s*(?:tbsp|tsp|cup|cups|oz|ounce|ounces|g|gram|grams|kg|ml|l|pound|pounds|lb|lbs|pinch|dash|tablespoon|teaspoon)?)\s+(.+)$/i', $ingredient, $matches)) {
                                // We have a structured ingredient with quantity/unit
                                $amount = trim($matches[1]);
                                $name = trim($matches[2]);

                                echo '<div class="ingredient-amount">' . htmlspecialchars($amount) . '</div>';
                                echo '<div class="ingredient-name">' . htmlspecialchars($name) . '</div>';
                            } else {
                                // Just a regular ingredient
                                echo '<div class="ingredient-amount"></div>';
                                echo '<div class="ingredient-name">' . htmlspecialchars($ingredient) . '</div>';
                            }

                            echo '</div>';
                        }

                        // Close the last section
                        if ($current_section !== null) {
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($product['recipe'])): ?>
                <div class="recipe-instructions">
                    <div class="recipe-heading">
                        <i class="fas fa-utensils"></i>
                        <h3>Recipe Instructions</h3>
                    </div>

                    <div class="instructions">
                        <?php
                        $recipe_text = $product['recipe'];

                        // Define specific recipe sections to highlight
                        $specific_sections = ['Marination:', 'Cooking Rice:', 'Garnish:', 'Preparation:', 'Cooking:', 'Assembly:', 'Serving:'];

                        // Check if recipe already contains numbered steps
                        if (preg_match('/^\s*\d+[\)\.]\s+/m', $recipe_text)) {
                            // Recipe has numbered steps, format accordingly
                            echo '<ol class="recipe-steps">';

                            // Split by line breaks first
                            $lines = explode("\n", $recipe_text);
                            $current_step = '';
                            $in_step = false;

                            foreach ($lines as $line) {
                                $line = trim($line);
                                if (empty($line)) continue;

                                // Check if line is one of our specific section headers
                                $is_section_header = false;
                                foreach ($specific_sections as $section) {
                                    if (stripos($line, $section) !== false || strtolower(trim($line)) === strtolower(trim($section))) {
                                        if ($in_step) {
                                            echo '<li>' . htmlspecialchars($current_step) . '</li>';
                                            $in_step = false;
                                        }
                                        echo '</ol>'; // Close current steps list
                                        echo '<div class="recipe-topic">' . htmlspecialchars($line) . '</div>';
                                        echo '<ol class="recipe-steps">'; // Start new steps list
                                        $is_section_header = true;
                                        break;
                                    }
                                }
                                if ($is_section_header) continue;

                                // Check if this line starts a new step
                                if (preg_match('/^\s*(\d+)[\)\.]\s+(.+)$/i', $line, $matches)) {
                                    // If we were in a previous step, close it
                                    if ($in_step) {
                                        echo '<li>' . htmlspecialchars($current_step) . '</li>';
                                    }

                                    // Start a new step
                                    $current_step = $matches[2];
                                    $in_step = true;
                                } elseif ($in_step) {
                                    // Continue current step
                                    $current_step .= ' ' . $line;
                                } else {
                                    // Not in a step and not starting a new one
                                    echo '<p>' . htmlspecialchars($line) . '</p>';
                                }
                            }

                            // Close last step if any
                            if ($in_step) {
                                echo '<li>' . htmlspecialchars($current_step) . '</li>';
                            }

                            echo '</ol>';
                        } else {
                            // No structured steps detected, try to identify sections
                            $lines = explode("\n", $recipe_text);
                            $current_section = '';
                            $section_content = '';

                            foreach ($lines as $line) {
                                $line = trim($line);
                                if (empty($line)) {
                                    if (!empty($section_content)) {
                                        echo '<p>' . htmlspecialchars($section_content) . '</p>';
                                        $section_content = '';
                                    }
                                    continue;
                                }

                                // Check if line is one of our specific section headers
                                $is_section_header = false;
                                foreach ($specific_sections as $section) {
                                    if (stripos($line, $section) !== false || strtolower(trim($line)) === strtolower(trim($section))) {
                                        // End current section if any
                                        if (!empty($section_content)) {
                                            echo '<p>' . htmlspecialchars($section_content) . '</p>';
                                            $section_content = '';
                                        }
                                        echo '<div class="recipe-topic">' . htmlspecialchars($line) . '</div>';
                                        $is_section_header = true;
                                        break;
                                    }
                                }

                                if (!$is_section_header) {
                                    if (!empty($section_content)) {
                                        $section_content .= ' ' . $line;
                                    } else {
                                        $section_content = $line;
                                    }
                                }
                            }

                            // Output any remaining content
                            if (!empty($section_content)) {
                                echo '<p>' . htmlspecialchars($section_content) . '</p>';
                            }

                            // Highlight cooking details like temperatures and times
                            echo '<script>
                                    document.addEventListener("DOMContentLoaded", function() {
                                        const instructionsDiv = document.querySelector(".instructions");
                                        const text = instructionsDiv.innerHTML;
                                        
                                        // Highlight temperatures (e.g., 350°F, 180°C)
                                        const tempHighlighted = text.replace(
                                            /(\d+)\s*(°[CF]|degrees [CF])/gi,
                                            "<span style=\"color: #d9534f; font-weight: bold;\">$1$2</span>"
                                        );
                                        
                                        // Highlight cooking times (e.g., 10 minutes, 2-3 hours)
                                        const timeHighlighted = tempHighlighted.replace(
                                            /(\d+[-\d]*)\s*(minute|minutes|hour|hours|min|mins|hr|hrs)/gi,
                                            "<span style=\"color: #5bc0de; font-weight: bold;\">$1 $2</span>"
                                        );
                                        
                                        instructionsDiv.innerHTML = timeHighlighted;
                                    });
                                </script>';
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="footer">
    Copyright © <?php echo date("Y"); ?>. All rights reserved.
</footer>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Generate QR Code
    document.addEventListener('DOMContentLoaded', function() {
        // Create QR Code
        generateQRCode();
    });

    function generateQRCode() {
        var qrUrl = "<?php echo $qr_url; ?>";
        var typeNumber = 0; // Auto-detect
        var errorCorrectionLevel = 'L'; // Low
        var qr = qrcode(typeNumber, errorCorrectionLevel);
        qr.addData(qrUrl);
        qr.make();

        // Create image tag
        document.getElementById('qrcode').innerHTML = qr.createImgTag(4); // Size multiplier
    }

    // Print functionality
    function printRecipe() {
        window.print();
    }

    // PDF Download functionality
    function downloadPDF() {
        const { jsPDF } = window.jspdf;

        // Create a new PDF
        const doc = new jsPDF({
            orientation: 'portrait',
            unit: 'mm',
            format: 'a4'
        });

        // Get recipe card element
        const recipeCard = document.getElementById('recipe-card');

        // Use html2canvas to take a screenshot of the recipe card
        html2canvas(recipeCard, {
            scale: 2, // Higher scale for better quality
            useCORS: true,
            logging: false,
            letterRendering: true
        }).then(canvas => {
            // Add image to PDF
            const imgData = canvas.toDataURL('image/jpeg', 1.0);
            const imgProps = doc.getImageProperties(imgData);
            const pdfWidth = doc.internal.pageSize.getWidth() - 20;
            const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;

            // Add title
            const productName = '<?php echo addslashes($product['product_name']); ?>';
            doc.setFontSize(16);
            doc.text(productName + ' - ZOUKI Recipe', 10, 10);

            // Add image
            doc.addImage(imgData, 'JPEG', 10, 20, pdfWidth, pdfHeight);

            // Save the PDF
            doc.save(productName.replace(/[^a-z0-9]/gi, '_').toLowerCase() + '_recipe.pdf');
        });
    }

    // Print QR Code functionality
    function printQRCode() {
        const qrCodeContainer = document.querySelector('.qr-code-container').innerHTML;
        const productName = '<?php echo addslashes($product['product_name']); ?>';

        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
                <html>
                <head>
                    <title>Safety QR Code - ${productName}</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            padding: 20px;
                            text-align: center;
                        }
                        .qr-title {
                            font-size: 18px;
                            margin-bottom: 20px;
                        }
                        .qr-container {
                            margin: 20px auto;
                        }
                        .qr-footer {
                            margin-top: 20px;
                            font-size: 12px;
                            color: #666;
                        }
                    </style>
                </head>
                <body>
                    <div class="qr-title">
                        Safety Information QR Code for ${productName}
                    </div>
                    <div class="qr-container">
                        ${qrCodeContainer}
                    </div>
                    <div class="qr-footer">
                        Scan this QR code for allergen and ingredient information
                    </div>
                </body>
                </html>
            `);

        printWindow.document.close();
        printWindow.focus();

        // Print after a short delay to ensure content is loaded
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 500);
    }

    // Copy to clipboard functionality
    function copyToClipboard(elementId) {
        const copyText = document.getElementById(elementId);
        copyText.select();
        copyText.setSelectionRange(0, 99999); // For mobile devices

        navigator.clipboard.writeText(copyText.value)
            .then(() => {
                // Show a success tooltip or message
                const button = copyText.nextElementSibling;
                const originalHTML = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check"></i>';

                setTimeout(() => {
                    button.innerHTML = originalHTML;
                }, 1500);
            })
            .catch(err => {
                console.error('Failed to copy: ', err);
            });
    }
</script>
</body>
</html>