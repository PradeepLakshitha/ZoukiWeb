<?php
require_once 'session_check.php';
check_session(['Admin']); // Only Admin can access this page
include 'db_connection.php';

// Initialize variables
$successMessage = '';
$errorMessage = '';
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'ingredient_sections';

// Define valid tabs
$validTabs = ['ingredient_sections', 'ingredient_patterns', 'recipe_sections', 'recipe_patterns'];
if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'ingredient_sections';
}

// Process form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    // INGREDIENT SECTION HEADERS
    if ($action == 'add_ingredient_section') {
        $header_text = trim($_POST['header_text']);
        $display_order = (int)$_POST['display_order'];

        if (!empty($header_text)) {
            try {
                $stmt = $conn->prepare("INSERT INTO ingredient_section_headers (header_text, display_order) VALUES (?, ?)");
                $stmt->bind_param("si", $header_text, $display_order);

                if ($stmt->execute()) {
                    $successMessage = "Ingredient section header added successfully!";
                } else {
                    $errorMessage = "Failed to add ingredient section header.";
                }
            } catch (Exception $e) {
                $errorMessage = "Error: " . $e->getMessage();
            }
        } else {
            $errorMessage = "Header text cannot be empty.";
        }

        $activeTab = 'ingredient_sections';
    }
    elseif ($action == 'update_ingredient_section') {
        $id = (int)$_POST['id'];
        $header_text = trim($_POST['header_text']);
        $display_order = (int)$_POST['display_order'];

        if (!empty($header_text)) {
            try {
                $stmt = $conn->prepare("UPDATE ingredient_section_headers SET header_text = ?, display_order = ? WHERE id = ?");
                $stmt->bind_param("sii", $header_text, $display_order, $id);

                if ($stmt->execute()) {
                    $successMessage = "Ingredient section header updated successfully!";
                } else {
                    $errorMessage = "Failed to update ingredient section header.";
                }
            } catch (Exception $e) {
                $errorMessage = "Error: " . $e->getMessage();
            }
        } else {
            $errorMessage = "Header text cannot be empty.";
        }

        $activeTab = 'ingredient_sections';
    }
    elseif ($action == 'delete_ingredient_section') {
        $id = (int)$_POST['id'];

        try {
            $stmt = $conn->prepare("DELETE FROM ingredient_section_headers WHERE id = ?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                $successMessage = "Ingredient section header deleted successfully!";
            } else {
                $errorMessage = "Failed to delete ingredient section header.";
            }
        } catch (Exception $e) {
            $errorMessage = "Error: " . $e->getMessage();
        }

        $activeTab = 'ingredient_sections';
    }

    // INGREDIENT MEASUREMENT PATTERNS
    elseif ($action == 'add_ingredient_pattern') {
        $pattern_name = trim($_POST['pattern_name']);
        $regex_pattern = trim($_POST['regex_pattern']);
        $description = trim($_POST['description']);

        if (!empty($pattern_name) && !empty($regex_pattern)) {
            try {
                $stmt = $conn->prepare("INSERT INTO ingredient_measurement_patterns (pattern_name, regex_pattern, description) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $pattern_name, $regex_pattern, $description);

                if ($stmt->execute()) {
                    $successMessage = "Ingredient measurement pattern added successfully!";
                } else {
                    $errorMessage = "Failed to add ingredient measurement pattern.";
                }
            } catch (Exception $e) {
                $errorMessage = "Error: " . $e->getMessage();
            }
        } else {
            $errorMessage = "Pattern name and regex pattern cannot be empty.";
        }

        $activeTab = 'ingredient_patterns';
    }
    elseif ($action == 'update_ingredient_pattern') {
        $id = (int)$_POST['id'];
        $pattern_name = trim($_POST['pattern_name']);
        $regex_pattern = trim($_POST['regex_pattern']);
        $description = trim($_POST['description']);

        if (!empty($pattern_name) && !empty($regex_pattern)) {
            try {
                $stmt = $conn->prepare("UPDATE ingredient_measurement_patterns SET pattern_name = ?, regex_pattern = ?, description = ? WHERE id = ?");
                $stmt->bind_param("sssi", $pattern_name, $regex_pattern, $description, $id);

                if ($stmt->execute()) {
                    $successMessage = "Ingredient measurement pattern updated successfully!";
                } else {
                    $errorMessage = "Failed to update ingredient measurement pattern.";
                }
            } catch (Exception $e) {
                $errorMessage = "Error: " . $e->getMessage();
            }
        } else {
            $errorMessage = "Pattern name and regex pattern cannot be empty.";
        }

        $activeTab = 'ingredient_patterns';
    }
    elseif ($action == 'delete_ingredient_pattern') {
        $id = (int)$_POST['id'];

        try {
            $stmt = $conn->prepare("DELETE FROM ingredient_measurement_patterns WHERE id = ?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                $successMessage = "Ingredient measurement pattern deleted successfully!";
            } else {
                $errorMessage = "Failed to delete ingredient measurement pattern.";
            }
        } catch (Exception $e) {
            $errorMessage = "Error: " . $e->getMessage();
        }

        $activeTab = 'ingredient_patterns';
    }

    // RECIPE SECTION HEADERS
    elseif ($action == 'add_recipe_section') {
        $header_text = trim($_POST['header_text']);
        $display_order = (int)$_POST['display_order'];

        if (!empty($header_text)) {
            try {
                $stmt = $conn->prepare("INSERT INTO recipe_section_headers (header_text, display_order) VALUES (?, ?)");
                $stmt->bind_param("si", $header_text, $display_order);

                if ($stmt->execute()) {
                    $successMessage = "Recipe section header added successfully!";
                } else {
                    $errorMessage = "Failed to add recipe section header.";
                }
            } catch (Exception $e) {
                $errorMessage = "Error: " . $e->getMessage();
            }
        } else {
            $errorMessage = "Header text cannot be empty.";
        }

        $activeTab = 'recipe_sections';
    }
    elseif ($action == 'update_recipe_section') {
        $id = (int)$_POST['id'];
        $header_text = trim($_POST['header_text']);
        $display_order = (int)$_POST['display_order'];

        if (!empty($header_text)) {
            try {
                $stmt = $conn->prepare("UPDATE recipe_section_headers SET header_text = ?, display_order = ? WHERE id = ?");
                $stmt->bind_param("sii", $header_text, $display_order, $id);

                if ($stmt->execute()) {
                    $successMessage = "Recipe section header updated successfully!";
                } else {
                    $errorMessage = "Failed to update recipe section header.";
                }
            } catch (Exception $e) {
                $errorMessage = "Error: " . $e->getMessage();
            }
        } else {
            $errorMessage = "Header text cannot be empty.";
        }

        $activeTab = 'recipe_sections';
    }
    elseif ($action == 'delete_recipe_section') {
        $id = (int)$_POST['id'];

        try {
            $stmt = $conn->prepare("DELETE FROM recipe_section_headers WHERE id = ?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                $successMessage = "Recipe section header deleted successfully!";
            } else {
                $errorMessage = "Failed to delete recipe section header.";
            }
        } catch (Exception $e) {
            $errorMessage = "Error: " . $e->getMessage();
        }

        $activeTab = 'recipe_sections';
    }

    // RECIPE HIGHLIGHT PATTERNS
    elseif ($action == 'add_recipe_pattern') {
        $pattern_name = trim($_POST['pattern_name']);
        $regex_pattern = trim($_POST['regex_pattern']);
        $highlight_color = trim($_POST['highlight_color']);
        $bold = isset($_POST['bold']) ? 1 : 0;
        $description = trim($_POST['description']);

        if (!empty($pattern_name) && !empty($regex_pattern)) {
            try {
                $stmt = $conn->prepare("INSERT INTO recipe_highlight_patterns (pattern_name, regex_pattern, highlight_color, bold, description) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssis", $pattern_name, $regex_pattern, $highlight_color, $bold, $description);

                if ($stmt->execute()) {
                    $successMessage = "Recipe highlight pattern added successfully!";
                } else {
                    $errorMessage = "Failed to add recipe highlight pattern.";
                }
            } catch (Exception $e) {
                $errorMessage = "Error: " . $e->getMessage();
            }
        } else {
            $errorMessage = "Pattern name and regex pattern cannot be empty.";
        }

        $activeTab = 'recipe_patterns';
    }
    elseif ($action == 'update_recipe_pattern') {
        $id = (int)$_POST['id'];
        $pattern_name = trim($_POST['pattern_name']);
        $regex_pattern = trim($_POST['regex_pattern']);
        $highlight_color = trim($_POST['highlight_color']);
        $bold = isset($_POST['bold']) ? 1 : 0;
        $description = trim($_POST['description']);

        if (!empty($pattern_name) && !empty($regex_pattern)) {
            try {
                $stmt = $conn->prepare("UPDATE recipe_highlight_patterns SET pattern_name = ?, regex_pattern = ?, highlight_color = ?, bold = ?, description = ? WHERE id = ?");
                $stmt->bind_param("sssisi", $pattern_name, $regex_pattern, $highlight_color, $bold, $description, $id);

                if ($stmt->execute()) {
                    $successMessage = "Recipe highlight pattern updated successfully!";
                } else {
                    $errorMessage = "Failed to update recipe highlight pattern.";
                }
            } catch (Exception $e) {
                $errorMessage = "Error: " . $e->getMessage();
            }
        } else {
            $errorMessage = "Pattern name and regex pattern cannot be empty.";
        }

        $activeTab = 'recipe_patterns';
    }
    elseif ($action == 'delete_recipe_pattern') {
        $id = (int)$_POST['id'];

        try {
            $stmt = $conn->prepare("DELETE FROM recipe_highlight_patterns WHERE id = ?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                $successMessage = "Recipe highlight pattern deleted successfully!";
            } else {
                $errorMessage = "Failed to delete recipe highlight pattern.";
            }
        } catch (Exception $e) {
            $errorMessage = "Error: " . $e->getMessage();
        }

        $activeTab = 'recipe_patterns';
    }
}

// Fetch data for each tab
// Ingredient Section Headers
$ingredientSections = [];
$ingredientSectionsQuery = "SELECT * FROM ingredient_section_headers ORDER BY display_order, header_text";
$ingredientSectionsResult = $conn->query($ingredientSectionsQuery);
if ($ingredientSectionsResult && $ingredientSectionsResult->num_rows > 0) {
    while ($row = $ingredientSectionsResult->fetch_assoc()) {
        $ingredientSections[] = $row;
    }
}

// Ingredient Measurement Patterns
$ingredientPatterns = [];
$ingredientPatternsQuery = "SELECT * FROM ingredient_measurement_patterns ORDER BY pattern_name";
$ingredientPatternsResult = $conn->query($ingredientPatternsQuery);
if ($ingredientPatternsResult && $ingredientPatternsResult->num_rows > 0) {
    while ($row = $ingredientPatternsResult->fetch_assoc()) {
        $ingredientPatterns[] = $row;
    }
}

// Recipe Section Headers
$recipeSections = [];
$recipeSectionsQuery = "SELECT * FROM recipe_section_headers ORDER BY display_order, header_text";
$recipeSectionsResult = $conn->query($recipeSectionsQuery);
if ($recipeSectionsResult && $recipeSectionsResult->num_rows > 0) {
    while ($row = $recipeSectionsResult->fetch_assoc()) {
        $recipeSections[] = $row;
    }
}

// Recipe Highlight Patterns
$recipePatterns = [];
$recipePatternsQuery = "SELECT * FROM recipe_highlight_patterns ORDER BY pattern_name";
$recipePatternsResult = $conn->query($recipePatternsQuery);
if ($recipePatternsResult && $recipePatternsResult->num_rows > 0) {
    while ($row = $recipePatternsResult->fetch_assoc()) {
        $recipePatterns[] = $row;
    }
}

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Format Filters Management - ZOUKI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --primary-color: #4CAF50;
            --secondary-color: #2196F3;
            --background-color: #f8f9fa;
            --sidebar-width: 250px;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --success-color: #28a745;
            --card-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Base Styles */
        body {
            background-color: var(--background-color);
            font-family: 'Inter', 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            background: #2c3e50;
            padding-top: 60px;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            margin: 4px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }

        .sidebar .nav-link i {
            font-size: 1.1rem;
        }

        /* Top Navbar Styles */
        .top-navbar {
            position: fixed;
            top: 0;
            right: 0;
            left: var(--sidebar-width);
            height: 60px;
            background: white;
            box-shadow: var(--card-shadow);
            z-index: 999;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s ease;
        }

        .top-navbar .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .top-navbar .user-info .user-name {
            font-weight: 500;
            color: #2c3e50;
        }

        /* Main Content Area */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 80px 20px 20px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        .page-title {
            margin-bottom: 25px;
            color: #333;
        }

        /* Card Styles */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            margin-bottom: 20px;
            background: white;
        }

        .card-header {
            background: white;
            border-bottom: 1px solid rgba(0,0,0,0.08);
            padding: 15px 20px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h5 {
            margin: 0;
            font-weight: 600;
        }

        .card-body {
            padding: 20px;
        }

        /* Tab Styles */
        .nav-tabs {
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 20px;
        }

        .nav-tabs .nav-item .nav-link {
            border: none;
            color: #6c757d;
            padding: 12px 20px;
            font-weight: 500;
            border-radius: 0;
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-item .nav-link:hover {
            background-color: rgba(0,0,0,0.02);
            color: var(--primary-color);
        }

        .nav-tabs .nav-item .nav-link.active {
            color: var(--primary-color);
            border-bottom: 3px solid var(--primary-color);
            background-color: transparent;
        }

        /* Table Styles */
        .table {
            margin-bottom: 0;
        }

        .table th {
            font-weight: 600;
            color: #2c3e50;
        }

        .table td {
            vertical-align: middle;
        }

        /* Form Styles */
        .form-label {
            font-weight: 500;
            color: #333;
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            padding: 10px 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
        }

        .color-preview {
            width: 25px;
            height: 25px;
            border-radius: 5px;
            display: inline-block;
            margin-right: 10px;
            border: 1px solid #ddd;
        }

        /* Action Buttons */
        .action-btn {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.875rem;
            margin-right: 5px;
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
                padding-top: 50px;
            }

            .sidebar .nav-link span {
                display: none;
            }

            .sidebar .nav-link {
                padding: 12px;
                margin: 4px 8px;
                justify-content: center;
            }

            .top-navbar {
                left: 60px;
            }

            .main-content {
                margin-left: 60px;
            }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 30px;
        }

        .empty-state i {
            font-size: 3rem;
            color: #d1d1d1;
            margin-bottom: 15px;
        }

        .empty-state h5 {
            color: #6c757d;
        }
    </style>
</head>
<body>
<!-- Sidebar -->
<nav class="sidebar">
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link" href="dashboard.php">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="products_management.php">
                <i class="bi bi-box"></i>
                <span>Products</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="categories_groups.php?tab=categories">
                <i class="bi bi-tags"></i>
                <span>Categories</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="categories_groups.php?tab=groups">
                <i class="bi bi-collection"></i>
                <span>Groups</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="users.php">
                <i class="bi bi-people"></i>
                <span>Users</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="reports.php">
                <i class="bi bi-graph-up"></i>
                <span>Reports</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="settings.php">
                <i class="bi bi-gear"></i>
                <span>Settings</span>
            </a>
        </li>
    </ul>
</nav>

<!-- Top Navbar -->
<nav class="top-navbar">
    <div class="d-flex align-items-center">
        <h4 class="mb-0">Format Filters Management</h4>
    </div>
    <div class="user-info">
        <span class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
        <a href="logout.php" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</nav>

<!-- Main Content -->
<div class="main-content">
    <div class="container-fluid">
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($errorMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($successMessage): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($successMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <ul class="nav nav-tabs" id="formatTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $activeTab === 'ingredient_sections' ? 'active' : ''; ?>" href="?tab=ingredient_sections">
                    Ingredient Section Headers
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $activeTab === 'ingredient_patterns' ? 'active' : ''; ?>" href="?tab=ingredient_patterns">
                    Ingredient Measurement Patterns
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $activeTab === 'recipe_sections' ? 'active' : ''; ?>" href="?tab=recipe_sections">
                    Recipe Section Headers
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $activeTab === 'recipe_patterns' ? 'active' : ''; ?>" href="?tab=recipe_patterns">
                    Recipe Highlight Patterns
                </a>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- INGREDIENT SECTION HEADERS TAB -->
            <div class="tab-pane fade <?php echo $activeTab === 'ingredient_sections' ? 'show active' : ''; ?>" id="ingredient-sections-tab">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Ingredient Section Headers</h5>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addIngredientSectionModal">
                            <i class="bi bi-plus-lg"></i> Add New Header
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (count($ingredientSections) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Header Text</th>
                                        <th>Display Order</th>
                                        <th>Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($ingredientSections as $section): ?>
                                        <tr>
                                            <td><?php echo $section['id']; ?></td>
                                            <td><?php echo htmlspecialchars($section['header_text']); ?></td>
                                            <td><?php echo $section['display_order']; ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary action-btn"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#editIngredientSectionModal"
                                                        data-id="<?php echo $section['id']; ?>"
                                                        data-header="<?php echo htmlspecialchars($section['header_text']); ?>"
                                                        data-order="<?php echo $section['display_order']; ?>">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger action-btn"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#deleteIngredientSectionModal"
                                                        data-id="<?php echo $section['id']; ?>"
                                                        data-header="<?php echo htmlspecialchars($section['header_text']); ?>">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-tags"></i>
                                <h5>No ingredient section headers found</h5>
                                <p>Add section headers to organize ingredients in recipes.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- INGREDIENT MEASUREMENT PATTERNS TAB -->
            <div class="tab-pane fade <?php echo $activeTab === 'ingredient_patterns' ? 'show active' : ''; ?>" id="ingredient-patterns-tab">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Ingredient Measurement Patterns</h5>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addIngredientPatternModal">
                            <i class="bi bi-plus-lg"></i> Add New Pattern
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (count($ingredientPatterns) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Pattern Name</th>
                                        <th>Regex Pattern</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($ingredientPatterns as $pattern): ?>
                                        <tr>
                                            <td><?php echo $pattern['id']; ?></td>
                                            <td><?php echo htmlspecialchars($pattern['pattern_name']); ?></td>
                                            <td><code><?php echo htmlspecialchars($pattern['regex_pattern']); ?></code></td>
                                            <td><?php echo htmlspecialchars($pattern['description']); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary action-btn"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#editIngredientPatternModal"
                                                        data-id="<?php echo $pattern['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($pattern['pattern_name']); ?>"
                                                        data-pattern="<?php echo htmlspecialchars($pattern['regex_pattern']); ?>"
                                                        data-description="<?php echo htmlspecialchars($pattern['description']); ?>">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger action-btn"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#deleteIngredientPatternModal"
                                                        data-id="<?php echo $pattern['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($pattern['pattern_name']); ?>">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-braces"></i>
                                <h5>No ingredient measurement patterns found</h5>
                                <p>Add regex patterns to identify measurements in ingredients.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- RECIPE SECTION HEADERS TAB -->
            <div class="tab-pane fade <?php echo $activeTab === 'recipe_sections' ? 'show active' : ''; ?>" id="recipe-sections-tab">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Recipe Section Headers</h5>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRecipeSectionModal">
                            <i class="bi bi-plus-lg"></i> Add New Header
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (count($recipeSections) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Header Text</th>
                                        <th>Display Order</th>
                                        <th>Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($recipeSections as $section): ?>
                                        <tr>
                                            <td><?php echo $section['id']; ?></td>
                                            <td><?php echo htmlspecialchars($section['header_text']); ?></td>
                                            <td><?php echo $section['display_order']; ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary action-btn"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#editRecipeSectionModal"
                                                        data-id="<?php echo $section['id']; ?>"
                                                        data-header="<?php echo htmlspecialchars($section['header_text']); ?>"
                                                        data-order="<?php echo $section['display_order']; ?>">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger action-btn"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#deleteRecipeSectionModal"
                                                        data-id="<?php echo $section['id']; ?>"
                                                        data-header="<?php echo htmlspecialchars($section['header_text']); ?>">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-list-ul"></i>
                                <h5>No recipe section headers found</h5>
                                <p>Add section headers to organize recipe instructions.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- RECIPE HIGHLIGHT PATTERNS TAB -->
            <div class="tab-pane fade <?php echo $activeTab === 'recipe_patterns' ? 'show active' : ''; ?>" id="recipe-patterns-tab">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Recipe Highlight Patterns</h5>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRecipePatternModal">
                            <i class="bi bi-plus-lg"></i> Add New Pattern
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (count($recipePatterns) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Pattern Name</th>
                                        <th>Regex Pattern</th>
                                        <th>Color</th>
                                        <th>Bold</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($recipePatterns as $pattern): ?>
                                        <tr>
                                            <td><?php echo $pattern['id']; ?></td>
                                            <td><?php echo htmlspecialchars($pattern['pattern_name']); ?></td>
                                            <td><code><?php echo htmlspecialchars($pattern['regex_pattern']); ?></code></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="color-preview" style="background-color: <?php echo htmlspecialchars($pattern['highlight_color']); ?>"></div>
                                                    <?php echo htmlspecialchars($pattern['highlight_color']); ?>
                                                </div>
                                            </td>
                                            <td><?php echo $pattern['bold'] ? 'Yes' : 'No'; ?></td>
                                            <td><?php echo htmlspecialchars($pattern['description']); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary action-btn"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#editRecipePatternModal"
                                                        data-id="<?php echo $pattern['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($pattern['pattern_name']); ?>"
                                                        data-pattern="<?php echo htmlspecialchars($pattern['regex_pattern']); ?>"
                                                        data-color="<?php echo htmlspecialchars($pattern['highlight_color']); ?>"
                                                        data-bold="<?php echo $pattern['bold']; ?>"
                                                        data-description="<?php echo htmlspecialchars($pattern['description']); ?>">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger action-btn"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#deleteRecipePatternModal"
                                                        data-id="<?php echo $pattern['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($pattern['pattern_name']); ?>">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-highlighter"></i>
                                <h5>No recipe highlight patterns found</h5>
                                <p>Add patterns to highlight important information in recipes.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODALS -->
<!-- Add Ingredient Section Modal -->
<div class="modal fade" id="addIngredientSectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Ingredient Section Header</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="add_ingredient_section">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="header_text" class="form-label">Header Text</label>
                        <input type="text" class="form-control" id="header_text" name="header_text" required>
                    </div>
                    <div class="mb-3">
                        <label for="display_order" class="form-label">Display Order</label>
                        <input type="number" class="form-control" id="display_order" name="display_order" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Header</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Ingredient Section Modal -->
<div class="modal fade" id="editIngredientSectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Ingredient Section Header</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="update_ingredient_section">
                <input type="hidden" name="id" id="edit_ingredient_section_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_header_text" class="form-label">Header Text</label>
                        <input type="text" class="form-control" id="edit_header_text" name="header_text" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_display_order" class="form-label">Display Order</label>
                        <input type="number" class="form-control" id="edit_display_order" name="display_order">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Ingredient Section Modal -->
<div class="modal fade" id="deleteIngredientSectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Ingredient Section Header</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="delete_ingredient_section">
                <input type="hidden" name="id" id="delete_ingredient_section_id">
                <div class="modal-body">
                    <p>Are you sure you want to delete the ingredient section header "<span id="delete_ingredient_section_name"></span>"?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Ingredient Pattern Modal -->
<div class="modal fade" id="addIngredientPatternModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Ingredient Measurement Pattern</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="add_ingredient_pattern">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="pattern_name" class="form-label">Pattern Name</label>
                        <input type="text" class="form-control" id="pattern_name" name="pattern_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="regex_pattern" class="form-label">Regex Pattern</label>
                        <input type="text" class="form-control" id="regex_pattern" name="regex_pattern" required>
                        <div class="form-text">Enter a valid regex pattern to identify ingredient measurements.</div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Pattern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Ingredient Pattern Modal -->
<div class="modal fade" id="editIngredientPatternModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Ingredient Measurement Pattern</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="update_ingredient_pattern">
                <input type="hidden" name="id" id="edit_ingredient_pattern_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_pattern_name" class="form-label">Pattern Name</label>
                        <input type="text" class="form-control" id="edit_pattern_name" name="pattern_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_regex_pattern" class="form-label">Regex Pattern</label>
                        <input type="text" class="form-control" id="edit_regex_pattern" name="regex_pattern" required>
                        <div class="form-text">Enter a valid regex pattern to identify ingredient measurements.</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Ingredient Pattern Modal -->
<div class="modal fade" id="deleteIngredientPatternModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Ingredient Measurement Pattern</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="delete_ingredient_pattern">
                <input type="hidden" name="id" id="delete_ingredient_pattern_id">
                <div class="modal-body">
                    <p>Are you sure you want to delete the pattern "<span id="delete_ingredient_pattern_name"></span>"?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Recipe Section Modal -->
<div class="modal fade" id="addRecipeSectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Recipe Section Header</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="add_recipe_section">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="recipe_header_text" class="form-label">Header Text</label>
                        <input type="text" class="form-control" id="recipe_header_text" name="header_text" required>
                    </div>
                    <div class="mb-3">
                        <label for="recipe_display_order" class="form-label">Display Order</label>
                        <input type="number" class="form-control" id="recipe_display_order" name="display_order" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Header</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Recipe Section Modal -->
<div class="modal fade" id="editRecipeSectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Recipe Section Header</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="update_recipe_section">
                <input type="hidden" name="id" id="edit_recipe_section_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_recipe_header_text" class="form-label">Header Text</label>
                        <input type="text" class="form-control" id="edit_recipe_header_text" name="header_text" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_recipe_display_order" class="form-label">Display Order</label>
                        <input type="number" class="form-control" id="edit_recipe_display_order" name="display_order">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Recipe Section Modal -->
<div class="modal fade" id="deleteRecipeSectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Recipe Section Header</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="delete_recipe_section">
                <input type="hidden" name="id" id="delete_recipe_section_id">
                <div class="modal-body">
                    <p>Are you sure you want to delete the recipe section header "<span id="delete_recipe_section_name"></span>"?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Recipe Pattern Modal -->
<div class="modal fade" id="addRecipePatternModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Recipe Highlight Pattern</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="add_recipe_pattern">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="recipe_pattern_name" class="form-label">Pattern Name</label>
                        <input type="text" class="form-control" id="recipe_pattern_name" name="pattern_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="recipe_regex_pattern" class="form-label">Regex Pattern</label>
                        <input type="text" class="form-control" id="recipe_regex_pattern" name="regex_pattern" required>
                        <div class="form-text">Enter a valid regex pattern to identify text to highlight.</div>
                    </div>
                    <div class="mb-3">
                        <label for="highlight_color" class="form-label">Highlight Color</label>
                        <input type="color" class="form-control form-control-color" id="highlight_color" name="highlight_color" value="#d9534f">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="bold" name="bold" checked>
                        <label class="form-check-label" for="bold">Bold Text</label>
                    </div>
                    <div class="mb-3">
                        <label for="recipe_description" class="form-label">Description</label>
                        <textarea class="form-control" id="recipe_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Pattern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Recipe Pattern Modal -->
<div class="modal fade" id="editRecipePatternModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Recipe Highlight Pattern</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="update_recipe_pattern">
                <input type="hidden" name="id" id="edit_recipe_pattern_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_recipe_pattern_name" class="form-label">Pattern Name</label>
                        <input type="text" class="form-control" id="edit_recipe_pattern_name" name="pattern_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_recipe_regex_pattern" class="form-label">Regex Pattern</label>
                        <input type="text" class="form-control" id="edit_recipe_regex_pattern" name="regex_pattern" required>
                        <div class="form-text">Enter a valid regex pattern to identify text to highlight.</div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_highlight_color" class="form-label">Highlight Color</label>
                        <input type="color" class="form-control form-control-color" id="edit_highlight_color" name="highlight_color">
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_bold" name="bold">
                        <label class="form-check-label" for="edit_bold">Bold Text</label>
                    </div>
                    <div class="mb-3">
                        <label for="edit_recipe_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_recipe_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Recipe Pattern Modal -->
<div class="modal fade" id="deleteRecipePatternModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Recipe Highlight Pattern</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="delete_recipe_pattern">
                <input type="hidden" name="id" id="delete_recipe_pattern_id">
                <div class="modal-body">
                    <p>Are you sure you want to delete the pattern "<span id="delete_recipe_pattern_name"></span>"?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Edit Ingredient Section Modal
    document.querySelectorAll('[data-bs-target="#editIngredientSectionModal"]').forEach(function(button) {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const header = this.getAttribute('data-header');
            const order = this.getAttribute('data-order');

            document.getElementById('edit_ingredient_section_id').value = id;
            document.getElementById('edit_header_text').value = header;
            document.getElementById('edit_display_order').value = order;
        });
    });

    // Delete Ingredient Section Modal
    document.querySelectorAll('[data-bs-target="#deleteIngredientSectionModal"]').forEach(function(button) {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const header = this.getAttribute('data-header');

            document.getElementById('delete_ingredient_section_id').value = id;
            document.getElementById('delete_ingredient_section_name').textContent = header;
        });
    });

    // Edit Ingredient Pattern Modal
    document.querySelectorAll('[data-bs-target="#editIngredientPatternModal"]').forEach(function(button) {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const pattern = this.getAttribute('data-pattern');
            const description = this.getAttribute('data-description');

            document.getElementById('edit_ingredient_pattern_id').value = id;
            document.getElementById('edit_pattern_name').value = name;
            document.getElementById('edit_regex_pattern').value = pattern;
            document.getElementById('edit_description').value = description;
        });
    });

    // Delete Ingredient Pattern Modal
    document.querySelectorAll('[data-bs-target="#deleteIngredientPatternModal"]').forEach(function(button) {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');

            document.getElementById('delete_ingredient_pattern_id').value = id;
            document.getElementById('delete_ingredient_pattern_name').textContent = name;
        });
    });

    // Edit Recipe Section Modal
    document.querySelectorAll('[data-bs-target="#editRecipeSectionModal"]').forEach(function(button) {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const header = this.getAttribute('data-header');
            const order = this.getAttribute('data-order');

            document.getElementById('edit_recipe_section_id').value = id;
            document.getElementById('edit_recipe_header_text').value = header;
            document.getElementById('edit_recipe_display_order').value = order;
        });
    });

    // Delete Recipe Section Modal
    document.querySelectorAll('[data-bs-target="#deleteRecipeSectionModal"]').forEach(function(button) {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const header = this.getAttribute('data-header');

            document.getElementById('delete_recipe_section_id').value = id;
            document.getElementById('delete_recipe_section_name').textContent = header;
        });
    });

    // Edit Recipe Pattern Modal
    document.querySelectorAll('[data-bs-target="#editRecipePatternModal"]').forEach(function(button) {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const pattern = this.getAttribute('data-pattern');
            const color = this.getAttribute('data-color');
            const bold = this.getAttribute('data-bold') === '1';
            const description = this.getAttribute('data-description');

            document.getElementById('edit_recipe_pattern_id').value = id;
            document.getElementById('edit_recipe_pattern_name').value = name;
            document.getElementById('edit_recipe_regex_pattern').value = pattern;
            document.getElementById('edit_highlight_color').value = color;
            document.getElementById('edit_bold').checked = bold;
            document.getElementById('edit_recipe_description').value = description;
        });
    });

    // Delete Recipe Pattern Modal
    document.querySelectorAll('[data-bs-target="#deleteRecipePatternModal"]').forEach(function(button) {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');

            document.getElementById('delete_recipe_pattern_id').value = id;
            document.getElementById('delete_recipe_pattern_name').textContent = name;
        });
    });

    // Automatically close alerts after 5 seconds
    window.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    });
</script>
</body>
</html>