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
$product_query = "SELECT p.*, 
                  GROUP_CONCAT(DISTINCT c.category_name ORDER BY c.category_name SEPARATOR ', ') as categories,
                  GROUP_CONCAT(DISTINCT g.group_name ORDER BY g.group_name SEPARATOR ', ') as groups
                  FROM products p
                  LEFT JOIN product_categories pc ON p.product_id = pc.product_id
                  LEFT JOIN categories c ON pc.category_id = c.category_id
                  LEFT JOIN product_groups pg ON p.product_id = pg.product_id
                  LEFT JOIN groups g ON pg.group_id = g.group_id
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
            padding: 0 30px;
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

        .footer {
            background: #78b85c;
            color: white;
            text-align: center;
            padding: 10px;
            width: 100%;
            margin-top: auto;
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
            <button class="btn btn-outline-primary" onclick="printRecipe()">
                <i class="fas fa-print"></i> Print Recipe
            </button>
            <button class="btn btn-success" onclick="downloadPDF()">
                <i class="fas fa-file-pdf"></i> Save as PDF
            </button>
        </div>
    </div>

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
                <div class="recipe-section">
                    <h3 class="recipe-section-title">
                        <i class="fas fa-carrot"></i> Ingredients
                    </h3>
                    <ul class="ingredients-list">
                        <?php
                        $ingredients = explode(',', $product['ingredients']);
                        foreach ($ingredients as $ingredient) {
                            $ingredient = trim($ingredient);
                            if (!empty($ingredient)) {
                                echo '<li><i class="fas fa-circle"></i> ' . htmlspecialchars($ingredient) . '</li>';
                            }
                        }
                        ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($product['recipe'])): ?>
                <div class="recipe-section">
                    <h3 class="recipe-section-title">
                        <i class="fas fa-utensils"></i> Recipe Instructions
                    </h3>
                    <div class="instructions">
                        <?php echo nl2br(htmlspecialchars($product['recipe'])); ?>
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
</script>
</body>
</html>