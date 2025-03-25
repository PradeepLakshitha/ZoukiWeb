<?php
require_once 'session_check.php';
check_session(['Admin', 'Manager']);
include 'db_connection.php';
include 'includes/functions.php';

// Page-specific variables
$page_title = 'Product Management';
$active_page = 'products';

// Get the logged-in user's information
$userName = $_SESSION['username'];
$userType = $_SESSION['uType'];
$userId = $_SESSION['userID'] ?? 0;

// Get user profile photo
$userPhotoQuery = $conn->prepare("SELECT profile_photo FROM z_user WHERE username = ?");
if (!$userPhotoQuery) {
    error_log("User photo query prepare failed: " . $conn->error);
} else {
    $userPhotoQuery->bind_param("s", $userName);
    $userPhotoQuery->execute();
    $userPhotoResult = $userPhotoQuery->get_result();
    $userPhoto = $userPhotoResult ? $userPhotoResult->fetch_assoc()['profile_photo'] ?? null : null;
}

// Get user details
$userDetails = getUserDetails($conn, $userName);

// Get unread notification count
$unreadCount = getUnreadNotificationCount($conn, $userId);

// Get recent notifications
$notificationsResult = getRecentNotifications($conn, $userId);

// Check for authentication first
if (!isset($_SESSION['username']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Not logged in at all
    $_SESSION['error'] = "Please log in to continue.";
    header("Location: index.php");
    exit();
}

// Then check for authorization (admin/manager roles)
if (!isset($_SESSION['uType']) || ($_SESSION['uType'] !== 'Admin' && $_SESSION['uType'] !== 'Manager')) {
    // Logged in but not authorized
    $_SESSION['error'] = "You don't have permission to access this page.";
    header("Location: dashboard.php");
    exit();
}

// Optional: Verify session integrity (e.g., IP address hasn't changed)
if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
    // Suspicious activity - session might be hijacked
    session_unset();
    session_destroy();
    $_SESSION['error'] = "Your session has expired. Please log in again.";
    header("Location: index.php");
    exit();
}

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 5; // Items per page
$offset = ($page - 1) * $limit;

// Search functionality
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$search_condition = '';
if (!empty($search)) {
    $search_condition = "WHERE p.product_name LIKE '%$search%' OR p.allergens LIKE '%$search%'";
}

// Filter by category or group
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$group_filter = isset($_GET['group']) ? (int)$_GET['group'] : 0;

if ($category_filter > 0) {
    $search_condition = $search_condition ? $search_condition . " AND p.product_id IN (SELECT product_id FROM product_categories WHERE category_id = $category_filter)" :
        "WHERE p.product_id IN (SELECT product_id FROM product_categories WHERE category_id = $category_filter)";
}

if ($group_filter > 0) {
    $search_condition = $search_condition ? $search_condition . " AND p.product_id IN (SELECT product_id FROM product_groups WHERE group_id = $group_filter)" :
        "WHERE p.product_id IN (SELECT product_id FROM product_groups WHERE group_id = $group_filter)";
}

// Count total products for pagination
$count_query = "SELECT COUNT(*) as total FROM products p $search_condition";
$count_result = $conn->query($count_query);
$total_products = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_products / $limit);

// Fetch products with pagination
$products_query = "
    SELECT p.*, 
           GROUP_CONCAT(DISTINCT c.category_name ORDER BY c.category_name SEPARATOR ', ') as categories,
           GROUP_CONCAT(DISTINCT g.group_name ORDER BY g.group_name SEPARATOR ', ') as `groups`
    FROM products p
    LEFT JOIN product_categories pc ON p.product_id = pc.product_id
    LEFT JOIN categories c ON pc.category_id = c.category_id
    LEFT JOIN product_groups pg ON p.product_id = pg.product_id
    LEFT JOIN `groups` g ON pg.group_id = g.group_id
    $search_condition
    GROUP BY p.product_id
    ORDER BY p.product_name ASC
    LIMIT $offset, $limit
";
$products_result = $conn->query($products_query);

// Fetch all categories and groups for filters
$categories_result = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name");
$groups_result = $conn->query("SELECT group_id, group_name FROM `groups` ORDER BY group_name");

// Check for session messages
$successMessage = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';

// Clear session messages
if (isset($_SESSION['success'])) unset($_SESSION['success']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);

// Handle Product Delete Action
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $product_id = (int) $_POST['product_id'];

    try {
        // Start a transaction to ensure all operations complete successfully
        $conn->begin_transaction();

        // Get the product details for image reference
        $product_query = "SELECT image FROM products WHERE product_id = ?";
        $stmt = $conn->prepare($product_query);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();

        // Delete associated categories
        $stmt = $conn->prepare("DELETE FROM product_categories WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete product categories: " . $conn->error);
        }

        // Delete associated groups
        $stmt = $conn->prepare("DELETE FROM product_groups WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete product groups: " . $conn->error);
        }

        // Delete the product
        $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete product: " . $conn->error);
        }

        // Delete the product image if it exists
        if (!empty($product['image']) && file_exists($product['image'])) {
            unlink($product['image']);
        }

        // Commit the transaction
        $conn->commit();

        $_SESSION['success'] = "Product successfully deleted!";
    } catch (Exception $e) {
        // Rollback the transaction on error
        $conn->rollback();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }

    // Redirect back to the products page
    header("Location: products_management.php");
    exit();
}

// Add page-specific CSS if needed
$additional_css = '
.product-img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 8px;
    background-color: #f1f1f1;
}

.healthy-badge {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.healthy-badge.green {
    background-color: rgba(40, 167, 69, 0.1);
    color: var(--success-color);
}

.healthy-badge.amber {
    background-color: rgba(255, 193, 7, 0.1);
    color: var(--warning-color);
}

.healthy-badge.red {
    background-color: rgba(220, 53, 69, 0.1);
    color: var(--danger-color);
}

/* Action Buttons */
.action-buttons .btn {
    padding: 6px 12px;
    font-size: 0.875rem;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

/* Filter Bar */
.filter-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 20px;
    align-items: center;
}

.filter-bar .form-control,
.filter-bar .form-select {
    max-width: 200px;
    border-radius: 8px;
}

.filter-bar .btn {
    border-radius: 8px;
    padding: 8px 16px;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Action Button */
.action-button {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 1000;
}

.btn-add {
    background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
    border: none;
    padding: 12px 24px;
    border-radius: 10px;
    color: white;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.btn-add:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 15px rgba(0,0,0,0.15);
}
';

// Include header
include 'includes/header.php';
?>

<!-- Action Button -->
<div class="action-button">
    <a href="product.php" class="btn btn-add">
        <i class="bi bi-plus-lg"></i>
        <span>Add Product</span>
    </a>
</div>

<!-- Main Content -->
<div class="container-fluid">
    <!-- Filter & Search Bar -->
    <div class="app-card mb-4">
        <div class="app-card-body">
            <form action="" method="GET" class="filter-bar">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Search products..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-outline-primary" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </div>

                <select class="form-select" name="category" onchange="this.form.submit()">
                    <option value="0">All Categories</option>
                    <?php while ($category = $categories_result->fetch_assoc()): ?>
                        <option value="<?php echo $category['category_id']; ?>" <?php echo $category_filter == $category['category_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['category_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <select class="form-select" name="group" onchange="this.form.submit()">
                    <option value="0">All Groups</option>
                    <?php 
                    $groups_result->data_seek(0); // Reset the pointer
                    while ($group = $groups_result->fetch_assoc()): 
                    ?>
                        <option value="<?php echo $group['group_id']; ?>" <?php echo $group_filter == $group['group_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($group['group_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>

                <?php if ($search || $category_filter > 0 || $group_filter > 0): ?>
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg"></i> Clear Filters
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Products Table -->
    <div class="app-card">
        <div class="app-card-header">
            <h5 class="app-card-title">Products</h5>
            <div class="app-card-toolbar">
                <span class="text-muted">Total: <?php echo $total_products; ?> products</span>
            </div>
        </div>
        <div class="app-card-body p-0">
            <?php if ($products_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th width="5%"></th>
                            <th width="25%">Product Name</th>
                            <th width="15%">Category</th>
                            <th width="15%">Group</th>
                            <th width="10%">Health</th>
                            <th width="15%">Allergens</th>
                            <th width="15%" class="text-center">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php while ($product = $products_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($product['image']) && file_exists($product['image'])): ?>
                                        <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="product-img">
                                    <?php else: ?>
                                        <div class="product-img d-flex align-items-center justify-content-center bg-light">
                                            <i class="bi bi-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($product['categories'] ?? 'None'); ?></td>
                                <td><?php echo htmlspecialchars($product['groups'] ?? 'None'); ?></td>
                                <td>
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
                                            echo '<span class="text-muted">Not set</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($product['allergens']); ?></td>
                                <td class="text-center">
                                    <div class="action-buttons">
                                        <button type="button" class="btn btn-sm btn-outline-primary me-1"
                                                data-bs-toggle="modal"
                                                data-bs-target="#viewProductModal"
                                                data-id="<?php echo $product['product_id']; ?>"
                                                data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                                data-allergens="<?php echo htmlspecialchars($product['allergens']); ?>"
                                                data-ingredients="<?php echo htmlspecialchars($product['ingredients']); ?>"
                                                data-healthy="<?php echo htmlspecialchars($product['healthy_option']); ?>"
                                                data-recipe="<?php echo htmlspecialchars($product['recipe']); ?>"
                                                data-image="<?php echo htmlspecialchars($product['image']); ?>"
                                                data-categories="<?php echo htmlspecialchars($product['categories'] ?? 'None'); ?>"
                                                data-groups="<?php echo htmlspecialchars($product['groups'] ?? 'None'); ?>">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <a href="product_edit.php?id=<?php echo $product['product_id']; ?>" class="btn btn-sm btn-outline-secondary me-1">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteProductModal"
                                                data-id="<?php echo $product['product_id']; ?>"
                                                data-name="<?php echo htmlspecialchars($product['product_name']); ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-center p-3">
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&group=<?php echo $group_filter; ?>" aria-label="Previous">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&group=<?php echo $group_filter; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&group=<?php echo $group_filter; ?>" aria-label="Next">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Empty State -->
                <div class="text-center p-5">
                    <div class="mb-4">
                        <i class="bi bi-box text-muted" style="font-size: 3rem;"></i>
                    </div>
                    <h5>No Products Found</h5>
                    <?php if ($search || $category_filter > 0 || $group_filter > 0): ?>
                        <p class="text-muted mb-4">No products match your search or filter criteria. Try changing your filters or adding new products.</p>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline-secondary me-2">
                            <i class="bi bi-x-lg"></i> Clear Filters
                        </a>
                    <?php else: ?>
                        <p class="text-muted mb-4">You haven't added any products yet. Start by adding your first product.</p>
                    <?php endif; ?>
                    <a href="product.php" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Add Product
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- View Product Modal -->
<div class="modal fade" id="viewProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Product Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-4 text-center mb-4 mb-md-0">
                        <div id="product-image-container" class="mb-3">
                            <!-- Product image will be inserted here via JavaScript -->
                        </div>
                        <span class="healthy-badge" id="product-health-badge">Healthy</span>
                    </div>
                    <div class="col-md-8">
                        <dl class="row">
                            <dt class="col-sm-3">Product Name</dt>
                            <dd class="col-sm-9" id="product-name">Product Name</dd>

                            <dt class="col-sm-3">Categories</dt>
                            <dd class="col-sm-9" id="product-categories">Category 1, Category 2</dd>

                            <dt class="col-sm-3">Groups</dt>
                            <dd class="col-sm-9" id="product-groups">Group 1, Group 2</dd>

                            <dt class="col-sm-3">Allergens</dt>
                            <dd class="col-sm-9" id="product-allergens">Allergens information</dd>

                            <dt class="col-sm-3">Ingredients</dt>
                            <dd class="col-sm-9" id="product-ingredients">Ingredients list</dd>

                            <dt class="col-sm-3">Recipe</dt>
                            <dd class="col-sm-9" id="product-recipe">Recipe instructions</dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" id="edit-product-link" class="btn btn-primary">
                    <i class="bi bi-pencil"></i> Edit Product
                </a>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Product Modal -->
<div class="modal fade" id="deleteProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="products_management.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="product_id" id="delete_product_id">
                    <p>Are you sure you want to delete <strong id="delete_product_name"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        This action cannot be undone. All product data, including images and associated records, will be permanently deleted.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Delete Product
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Define page-specific scripts
$page_scripts = '
// View Product Modal
const viewProductModal = document.getElementById("viewProductModal");
if (viewProductModal) {
    viewProductModal.addEventListener("show.bs.modal", function (event) {
        // Button that triggered the modal
        const button = event.relatedTarget;

        // Extract info from data-* attributes
        const productId = button.getAttribute("data-id");
        const productName = button.getAttribute("data-name");
        const productAllergens = button.getAttribute("data-allergens");
        const productIngredients = button.getAttribute("data-ingredients");
        const productHealthy = button.getAttribute("data-healthy");
        const productRecipe = button.getAttribute("data-recipe");
        const productImage = button.getAttribute("data-image");
        const productCategories = button.getAttribute("data-categories");
        const productGroups = button.getAttribute("data-groups");

        // Update the modal\'s content
        document.getElementById("product-name").textContent = productName;
        document.getElementById("product-allergens").textContent = productAllergens;
        document.getElementById("product-ingredients").textContent = productIngredients;
        document.getElementById("product-recipe").textContent = productRecipe;
        document.getElementById("product-categories").textContent = productCategories;
        document.getElementById("product-groups").textContent = productGroups;

        // Set the edit link
        document.getElementById("edit-product-link").href = "product_edit.php?id=" + productId;

        // Handle product image
        const imageContainer = document.getElementById("product-image-container");
        if (productImage && productImage !== "") {
            imageContainer.innerHTML = `<img src="${productImage}" alt="${productName}" class="img-fluid rounded" style="max-height: 200px;">`;
        } else {
            imageContainer.innerHTML = `
                    <div class="d-flex align-items-center justify-content-center bg-light rounded" style="height: 200px; width: 100%;">
                        <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                    </div>
                `;
        }

        // Set health badge
        const healthBadge = document.getElementById("product-health-badge");
        if (productHealthy) {
            switch (productHealthy) {
                case "Green":
                    healthBadge.className = "healthy-badge green";
                    healthBadge.textContent = "Healthy";
                    break;
                case "Amber":
                    healthBadge.className = "healthy-badge amber";
                    healthBadge.textContent = "AMBER";
                    break;
                case "Red":
                    healthBadge.className = "healthy-badge red";
                    healthBadge.textContent = "RED";
                    break;
                default:
                    healthBadge.className = "badge bg-secondary";
                    healthBadge.textContent = "Not Set";
            }
        } else {
            healthBadge.className = "badge bg-secondary";
            healthBadge.textContent = "Not Set";
        }
    });
}

// Delete Product Modal
const deleteProductModal = document.getElementById("deleteProductModal");
if (deleteProductModal) {
    deleteProductModal.addEventListener("show.bs.modal", function (event) {
        // Button that triggered the modal
        const button = event.relatedTarget;

        // Extract info from data-* attributes
        const productId = button.getAttribute("data-id");
        const productName = button.getAttribute("data-name");

        // Update the modal\'s content
        document.getElementById("delete_product_id").value = productId;
        document.getElementById("delete_product_name").textContent = productName;
    });
}
';

// Include footer (this loads all JavaScript)
include 'includes/footer.php';
?>