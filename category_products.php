<?php
require_once 'session_check.php';
check_session(); // All authenticated users can access
include 'db_connection.php';

// Ensure the category ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: home.php");
    exit();
}

$category_id = (int)$_GET['id'];

// Get the category information
$category_query = "SELECT category_name FROM categories WHERE category_id = ?";
$stmt = $conn->prepare($category_query);
$stmt->bind_param("i", $category_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: home.php");
    exit();
}

$category = $result->fetch_assoc();
$category_name = $category['category_name'];

// Search functionality
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$search_condition = '';
if (!empty($search)) {
    $search_condition = "AND (p.product_name LIKE '%$search%' OR p.allergens LIKE '%$search%' OR p.ingredients LIKE '%$search%')";
}

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12; // Items per page
$offset = ($page - 1) * $limit;

// Count total products for pagination
$count_query = "SELECT COUNT(*) as total FROM products p 
                JOIN product_categories pc ON p.product_id = pc.product_id 
                WHERE pc.category_id = ? $search_condition";
$stmt = $conn->prepare($count_query);
$stmt->bind_param("i", $category_id);
$stmt->execute();
$total_products = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_products / $limit);

// Fetch products with pagination
$products_query = "SELECT p.* FROM products p 
                   JOIN product_categories pc ON p.product_id = pc.product_id 
                   WHERE pc.category_id = ? $search_condition
                   ORDER BY p.product_name ASC
                   LIMIT ? OFFSET ?";
$stmt = $conn->prepare($products_query);
$stmt->bind_param("iii", $category_id, $limit, $offset);
$stmt->execute();
$products_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($category_name); ?> Products - ZOUKI</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

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

        .category-header {
            background: white;
            border-radius: 10px;
            padding: 20px;
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

        .search-bar {
            max-width: 500px;
            margin: 0 auto 30px;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .product-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0px 10px 20px rgba(0, 0, 0, 0.1);
        }

        .product-image {
            height: 180px;
            background-color: #f1f1f1;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        .product-image.no-image {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-info {
            padding: 15px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .product-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 10px;
        }

        .product-allergens {
            margin-top: auto;
            color: #6c757d;
            font-size: 0.9rem;
        }

        .product-footer {
            padding: 10px 15px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .healthy-badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
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

        .view-details {
            color: #4CAF50;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
        }

        .view-details:hover {
            text-decoration: underline;
        }

        .pagination-container {
            margin-top: 30px;
            display: flex;
            justify-content: center;
        }

        .pagination {
            background: white;
            padding: 10px;
            border-radius: 30px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.05);
        }

        .pagination .page-item .page-link {
            border: none;
            color: #6c757d;
            padding: 8px 16px;
            font-size: 0.9rem;
            background: transparent;
        }

        .pagination .page-item.active .page-link {
            background-color: #4CAF50;
            color: white;
            border-radius: 50%;
        }

        .pagination .page-item .page-link:hover {
            background-color: rgba(0,0,0,0.03);
            color: #4CAF50;
            border-radius: 50%;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0px 4px 15px rgba(0, 0, 0, 0.05);
        }

        .empty-state i {
            font-size: 4rem;
            color: #adb5bd;
            margin-bottom: 20px;
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
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }

            .category-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
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
    <div class="category-header">
        <div>
            <a href="home.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Back to Categories
            </a>
            <h2 class="mt-2"><?php echo htmlspecialchars($category_name); ?></h2>
            <p class="text-muted"><?php echo $total_products; ?> products found</p>
        </div>

        <form action="" method="GET" class="search-bar">
            <input type="hidden" name="id" value="<?php echo $category_id; ?>">
            <div class="input-group">
                <input type="text" class="form-control" placeholder="Search products..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-success" type="submit">
                    <i class="fas fa-search"></i>
                </button>
                <?php if (!empty($search)): ?>
                    <a href="category_products.php?id=<?php echo $category_id; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if ($products_result->num_rows > 0): ?>
        <div class="product-grid">
            <?php while ($product = $products_result->fetch_assoc()): ?>
                <div class="product-card" onclick="window.location.href='product_detail.php?id=<?php echo $product['product_id']; ?>'">
                    <?php if (!empty($product['image']) && file_exists($product['image'])): ?>
                        <div class="product-image" style="background-image: url('<?php echo htmlspecialchars($product['image']); ?>')"></div>
                    <?php else: ?>
                        <div class="product-image no-image">
                            <i class="fas fa-utensils fa-2x text-muted"></i>
                        </div>
                    <?php endif; ?>

                    <div class="product-info">
                        <div class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></div>
                        <div class="product-allergens">
                            <strong>Allergens:</strong> <?php echo htmlspecialchars($product['allergens']); ?>
                        </div>
                    </div>

                    <div class="product-footer">
                        <?php
                        switch ($product['healthy_option']) {
                            case 'Green':
                                echo '<span class="healthy-badge green">Healthy</span>';
                                break;
                            case 'Amber':
                                echo '<span class="healthy-badge amber">AMBER</span>';
                                break;
                            case 'Red':
                                echo '<span class="healthy-badge red">RED</span>';
                                break;
                            default:
                                echo '<span class="text-muted">Not rated</span>';
                        }
                        ?>
                        <span class="view-details">View Recipe <i class="fas fa-chevron-right"></i></span>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <nav aria-label="Page navigation">
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?id=<?php echo $category_id; ?>&page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>" aria-label="Previous">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?id=<?php echo $category_id; ?>&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?id=<?php echo $category_id; ?>&page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>" aria-label="Next">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-search"></i>
            <h3>No Products Found</h3>
            <?php if (!empty($search)): ?>
                <p>No products match your search criteria. Try different keywords or browse all products in this category.</p>
                <a href="category_products.php?id=<?php echo $category_id; ?>" class="btn btn-success mt-3">
                    <i class="fas fa-sync-alt"></i> View All Products
                </a>
            <?php else: ?>
                <p>There are no products in this category yet.</p>
                <a href="home.php" class="btn btn-success mt-3">
                    <i class="fas fa-home"></i> Return to Home
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Footer -->
<footer class="footer">
    Copyright © <?php echo date("Y"); ?>. All rights reserved.
</footer>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>