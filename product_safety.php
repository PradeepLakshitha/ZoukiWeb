<?php
include 'db_connection.php';

// Security function to verify token
function verifyProductToken($id, $token) {
    // Simple token verification - in production you'd want stronger encryption
    $expected = hash('sha256', $id . 'ZoukiSafety2025');
    return hash_equals($expected, $token);
}

// Initialize variables
$error = false;
$product = null;

// Check if the required parameters are present
if (isset($_GET['id']) && isset($_GET['token'])) {
    $product_id = (int)$_GET['id'];
    $token = $_GET['token'];

    // Verify the token
    if (verifyProductToken($product_id, $token)) {
        // Token is valid, fetch product data
        $query = "SELECT product_name, image, allergens, ingredients, healthy_option 
                  FROM products WHERE product_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $product = $result->fetch_assoc();
        } else {
            $error = "Product not found";
        }
    } else {
        $error = "Invalid security token";
    }
} else {
    $error = "Missing required parameters";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; script-src 'self' 'unsafe-inline'; img-src 'self' data:;">
    <title>Product Safety Information</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Inter', 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .safety-container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .safety-header {
            background: #4CAF50;
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .safety-header h1 {
            font-size: 1.5rem;
            margin: 0;
            font-weight: 600;
        }

        .safety-header .logo {
            height: 35px;
        }

        .product-image-container {
            width: 100%;
            height: 200px;
            overflow: hidden;
            background: #f1f1f1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-image {
            max-width: 100%;
            max-height: 200px;
            object-fit: contain;
        }

        .product-details {
            padding: 20px;
        }

        .product-name {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: #333;
        }

        .health-indicator {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .health-indicator.green {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .health-indicator.amber {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .health-indicator.red {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .health-indicator i {
            margin-right: 8px;
        }

        .safety-section {
            margin-bottom: 20px;
        }

        .safety-section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: #4CAF50;
            display: flex;
            align-items: center;
        }

        .safety-section-title i {
            margin-right: 8px;
        }

        .allergens-box {
            background-color: #fff8e1;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .allergens-title {
            font-weight: 600;
            color: #b7791f;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ingredients-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
            columns: 2;
            column-gap: 20px;
        }

        .ingredients-list li {
            break-inside: avoid;
            padding: 6px 0;
            border-bottom: 1px dotted #eee;
            font-size: 0.9rem;
        }

        .zouki-footer {
            text-align: center;
            margin-top: 20px;
            padding-bottom: 15px;
            color: #6c757d;
            font-size: 0.8rem;
        }

        .error-container {
            text-align: center;
            padding: 40px 20px;
        }

        .error-container i {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 20px;
        }

        @media (max-width: 576px) {
            .safety-container {
                margin: 0;
                border-radius: 0;
                min-height: 100vh;
            }

            .ingredients-list {
                columns: 1;
            }
        }
    </style>
</head>
<body>
<div class="safety-container">
    <?php if ($error): ?>
        <div class="error-container">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <h2>Error</h2>
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    <?php else: ?>
        <div class="safety-header">
            <h1>Product Safety Information</h1>
            <img src="img/ZoukiLogo.svg" alt="ZOUKI Logo" class="logo">
        </div>

        <div class="product-image-container">
            <?php if (!empty($product['image']) && file_exists($product['image'])): ?>
                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="product-image">
            <?php else: ?>
                <div class="no-image">
                    <i class="bi bi-image" style="font-size: 3rem; color: #adb5bd;"></i>
                </div>
            <?php endif; ?>
        </div>

        <div class="product-details">
            <h2 class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></h2>

            <?php
            switch ($product['healthy_option']) {
                case 'Green':
                    echo '<div class="health-indicator green"><i class="bi bi-check-circle-fill"></i> Healthy Choice</div>';
                    break;
                case 'Amber':
                    echo '<div class="health-indicator amber"><i class="bi bi-exclamation-circle-fill"></i> AMBER - Moderate</div>';
                    break;
                case 'Red':
                    echo '<div class="health-indicator red"><i class="bi bi-x-circle-fill"></i> RED - Occasional</div>';
                    break;
            }
            ?>

            <div class="allergens-box">
                <div class="allergens-title">
                    <i class="bi bi-exclamation-triangle-fill"></i> Allergen Information
                </div>
                <div><?php echo htmlspecialchars($product['allergens']); ?></div>
            </div>

            <div class="safety-section">
                <div class="safety-section-title">
                    <i class="bi bi-list-check"></i> Ingredients
                </div>
                <ul class="ingredients-list">
                    <?php
                    $ingredients_text = $product['ingredients'];

                    // Try different delimiters for ingredients
                    if (strpos($ingredients_text, "\n") !== false) {
                        $ingredients = array_filter(explode("\n", $ingredients_text));
                    } elseif (strpos($ingredients_text, "•") !== false) {
                        $ingredients = array_filter(explode("•", $ingredients_text));
                    } elseif (strpos($ingredients_text, "-") !== false && substr_count($ingredients_text, "-") > 1) {
                        $ingredients = array_filter(explode("-", $ingredients_text));
                    } else {
                        $ingredients = array_filter(explode(',', $ingredients_text));
                    }

                    foreach ($ingredients as $ingredient) {
                        $ingredient = trim($ingredient);
                        if (!empty($ingredient)) {
                            echo '<li>' . htmlspecialchars($ingredient) . '</li>';
                        }
                    }
                    ?>
                </ul>
            </div>
        </div>

        <div class="zouki-footer">
            &copy; <?php echo date('Y'); ?> ZOUKI Food. Scan date: <?php echo date('d/m/Y'); ?>
        </div>
    <?php endif; ?>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</body>
</html>