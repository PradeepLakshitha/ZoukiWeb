<?php
require_once 'session_check.php';
check_session(['Admin', 'Manager']);
include 'db_connection.php';
include 'includes/functions.php';

// Check database connection
if (!$conn) {
    error_log("Database connection failed in dashboard.php");
}

// Redirect to login if not logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['uType'])) {
    header("Location: index.php");
    exit();
}

// Restrict access to only Admin and Manager
if ($_SESSION['uType'] !== 'Admin' && $_SESSION['uType'] !== 'Manager') {
    $_SESSION['error'] = "Access denied! You don't have permission.";
    header("Location: home.php");
    exit();
}

// Page-specific variables
$page_title = 'Dashboard';
$active_page = 'dashboard';
$use_chart_js = true; // Enable Chart.js for this page

// Get the logged-in user's information
$userName = $_SESSION['username'];
$userType = $_SESSION['uType'];
$userId = $_SESSION['userID'] ?? 0;

// Get user details
$userDetails = getUserDetails($conn, $userName);

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

// Fetch some basic statistics for the dashboard
// Count total products
$totalProductsQuery = $conn->query("SELECT COUNT(*) as count FROM products");
if (!$totalProductsQuery) {
    error_log("Total products query failed: " . $conn->error);
    $totalProducts = 0;
} else {
    $totalProducts = $totalProductsQuery->fetch_assoc()['count'] ?? 0;
}

// Count total categories
$totalCategoriesQuery = $conn->query("SELECT COUNT(*) as count FROM categories");
if (!$totalCategoriesQuery) {
    error_log("Total categories query failed: " . $conn->error);
    $totalCategories = 0;
} else {
    $totalCategories = $totalCategoriesQuery->fetch_assoc()['count'] ?? 0;
}

// Count total users
$totalUsersQuery = $conn->query("SELECT COUNT(*) as count FROM z_user");
if (!$totalUsersQuery) {
    error_log("Total users query failed: " . $conn->error);
    $totalUsers = 0;
} else {
    $totalUsers = $totalUsersQuery->fetch_assoc()['count'] ?? 0;
}

// Get recent products (limit to 5)
$recentProducts = $conn->query("SELECT product_name, healthy_option, DATE_FORMAT(created_at, '%d %b %Y') as date_created FROM products ORDER BY created_at DESC LIMIT 5");
if (!$recentProducts) {
    error_log("Recent products query failed: " . $conn->error);
}

// Get health distribution data
$healthDistributionQuery = $conn->query("SELECT healthy_option, COUNT(*) as count FROM products GROUP BY healthy_option");
if (!$healthDistributionQuery) {
    error_log("Health distribution query failed: " . $conn->error);
}

$healthDistribution = [
    'Green' => 0,
    'Amber' => 0,
    'Red' => 0
];

if ($healthDistributionQuery && $healthDistributionQuery->num_rows > 0) {
    while ($row = $healthDistributionQuery->fetch_assoc()) {
        if (isset($row['healthy_option']) && array_key_exists($row['healthy_option'], $healthDistribution)) {
            $healthDistribution[$row['healthy_option']] = (int)$row['count'];
        }
    }
}

// Get monthly product data for chart
$monthlyProductQuery = $conn->query("
    SELECT 
        MONTH(created_at) as month,
        COUNT(*) as total_count,
        SUM(CASE WHEN healthy_option = 'Green' THEN 1 ELSE 0 END) as healthy_count
    FROM products
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
    GROUP BY MONTH(created_at)
    ORDER BY MONTH(created_at)
");

if (!$monthlyProductQuery) {
    error_log("Monthly product query failed: " . $conn->error);
}

$monthlyProductData = [];
$monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

// Initialize with zeros for all months
for ($i = 0; $i < 12; $i++) {
    $monthlyProductData[$i] = [
        'label' => $monthNames[$i],
        'total' => 0,
        'healthy' => 0
    ];
}

// Fill in actual data
if ($monthlyProductQuery && $monthlyProductQuery->num_rows > 0) {
    while ($row = $monthlyProductQuery->fetch_assoc()) {
        $monthIndex = (int)$row['month'] - 1; // Convert 1-based month to 0-based index
        if ($monthIndex >= 0 && $monthIndex < 12) {
            $monthlyProductData[$monthIndex]['total'] = (int)$row['total_count'];
            $monthlyProductData[$monthIndex]['healthy'] = (int)$row['healthy_count'];
        }
    }
}

// Get unread notification count
$unreadCount = getUnreadNotificationCount($conn, $userId);

// Get recent notifications
$notificationsResult = getRecentNotifications($conn, $userId);

// Check for messages
$successMessage = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';

// Clear session messages
if (isset($_SESSION['success'])) unset($_SESSION['success']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);

// Define page-specific scripts
$page_scripts = '
// Count-up animation for stats
function animateCountUp() {
    const countElements = document.querySelectorAll(\'.count-up\');
    
    countElements.forEach(el => {
        const target = parseInt(el.getAttribute(\'data-count\')) || 0;
        const duration = 1500; // animation duration in ms
        const frameDuration = 1000/60; // 60fps
        const totalFrames = Math.round(duration / frameDuration);
        const easeOutQuad = t => t * (2 - t);
        
        let frame = 0;
        let currentValue = 0;
        
        const counter = setInterval(() => {
            frame++;
            const progress = easeOutQuad(frame / totalFrames);
            currentValue = Math.round(target * progress);
            
            if (frame === totalFrames) {
                clearInterval(counter);
                el.textContent = target;
            } else {
                el.textContent = currentValue;
            }
        }, frameDuration);
    });
}

// Define charts after DOM is loaded
function initPageFunctions() {
    // Products Overview Chart
    const productChartData = {
        labels: ' . json_encode(array_column($monthlyProductData, 'label')) . ',
        total: ' . json_encode(array_column($monthlyProductData, 'total')) . ',
        healthy: ' . json_encode(array_column($monthlyProductData, 'healthy')) . '
    };

    const productOverviewChart = new Chart(
        document.getElementById(\'productOverviewChart\'),
        {
            type: \'line\',
            data: {
                labels: productChartData.labels,
                datasets: [
                    {
                        label: \'Total Products\',
                        data: productChartData.total,
                        borderColor: \'#4CAF50\',
                        backgroundColor: \'rgba(76, 175, 80, 0.1)\',
                        tension: 0.4,
                        borderWidth: 2,
                        fill: true
                    },
                    {
                        label: \'Healthy Products\',
                        data: productChartData.healthy,
                        borderColor: \'#2196F3\',
                        backgroundColor: \'rgba(33, 150, 243, 0.1)\',
                        tension: 0.4,
                        borderWidth: 2,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: \'top\',
                    },
                    tooltip: {
                        mode: \'index\',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        }
    );

    // Health Distribution Chart
    const healthDistributionChart = new Chart(
        document.getElementById(\'healthDistributionChart\'),
        {
            type: \'doughnut\',
            data: {
                labels: [\'Green (Healthy)\', \'Amber (Moderate)\', \'Red (Less Healthy)\'],
                datasets: [
                    {
                        data: [
                            ' . ($healthDistribution['Green'] ?? 0) . ',
                            ' . ($healthDistribution['Amber'] ?? 0) . ',
                            ' . ($healthDistribution['Red'] ?? 0) . '
                        ],
                        backgroundColor: [\'#28a745\', \'#ffc107\', \'#dc3545\'],
                        borderColor: [\'#28a745\', \'#ffc107\', \'#dc3545\'],
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: \'bottom\'
                    }
                },
                cutout: \'70%\'
            }
        }
    );

    // Chart Range Selector
    const chartRangeSelector = document.getElementById(\'chartRangeSelector\');
    if (chartRangeSelector) {
        chartRangeSelector.addEventListener(\'change\', (e) => {
            const range = e.target.value;

            // For now, reset to the default data we already have (monthly)
            if (range === \'monthly\') {
                productOverviewChart.data.labels = productChartData.labels;
                productOverviewChart.data.datasets[0].data = productChartData.total;
                productOverviewChart.data.datasets[1].data = productChartData.healthy;
                productOverviewChart.update();
            } else {
                // In a real implementation, we would fetch data via AJAX here
                // For now, let\'s simulate weekly and yearly data based on monthly
                if (range === \'weekly\') {
                    // Simulate weekly data (last 7 days)
                    const weeklyLabels = [\'Mon\', \'Tue\', \'Wed\', \'Thu\', \'Fri\', \'Sat\', \'Sun\'];
                    const weeklyTotal = [0, 0, 0, 0, 0, 0, 0];
                    const weeklyHealthy = [0, 0, 0, 0, 0, 0, 0];

                    // Generate some sample data based on the most recent month
                    const latestMonthTotal = productChartData.total[productChartData.total.length - 1] || 0;
                    const latestMonthHealthy = productChartData.healthy[productChartData.healthy.length - 1] || 0;

                    for (let i = 0; i < 7; i++) {
                        // Create some random variation around the monthly average
                        const factor = 0.85 + (Math.random() * 0.3); // Between 0.85 and 1.15
                        weeklyTotal[i] = Math.round((latestMonthTotal / 30) * 7 * factor);
                        weeklyHealthy[i] = Math.round((latestMonthHealthy / 30) * 7 * factor);
                    }

                    productOverviewChart.data.labels = weeklyLabels;
                    productOverviewChart.data.datasets[0].data = weeklyTotal;
                    productOverviewChart.data.datasets[1].data = weeklyHealthy;

                } else if (range === \'yearly\') {
                    // Simulate yearly data (last 4 years)
                    const yearlyLabels = [\'2022\', \'2023\', \'2024\', \'2025\'];
                    const yearlyTotal = [0, 0, 0, 0];
                    const yearlyHealthy = [0, 0, 0, 0];

                    // Aggregate monthly data into yearly data
                    const monthsPerYear = Math.floor(productChartData.total.length / 4);
                    for (let i = 0; i < productChartData.total.length && i < 12; i++) {
                        const yearIndex = Math.min(3, Math.floor(i / monthsPerYear));
                        yearlyTotal[yearIndex] += productChartData.total[i] || 0;
                        yearlyHealthy[yearIndex] += productChartData.healthy[i] || 0;
                    }

                    productOverviewChart.data.labels = yearlyLabels;
                    productOverviewChart.data.datasets[0].data = yearlyTotal;
                    productOverviewChart.data.datasets[1].data = yearlyHealthy;
                }

                productOverviewChart.update();
            }
        });
    }
}
';

// Include header
include 'includes/header.php';
?>

<!-- Dashboard content -->
<div class="container-fluid">
    <!-- Stats Cards -->
    <div class="row">
        <div class="col-md-6 col-lg-3">
            <div class="app-card">
                <div class="stats-card">
                    <div class="stats-icon primary">
                        <i class="bi bi-box"></i>
                    </div>
                    <div class="stats-data">
                        <div class="stats-value count-up" data-count="<?php echo $totalProducts; ?>">0</div>
                        <div class="stats-label">Total Products</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="app-card">
                <div class="stats-card">
                    <div class="stats-icon info">
                        <i class="bi bi-tags"></i>
                    </div>
                    <div class="stats-data">
                        <div class="stats-value count-up" data-count="<?php echo $totalCategories; ?>">0</div>
                        <div class="stats-label">Total Categories</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="app-card">
                <div class="stats-card">
                    <div class="stats-icon success">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stats-data">
                        <div class="stats-value count-up" data-count="<?php echo $totalUsers; ?>">0</div>
                        <div class="stats-label">Total Users</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="app-card">
                <div class="stats-card">
                    <div class="stats-icon warning">
                        <i class="bi bi-heart"></i>
                    </div>
                    <div class="stats-data">
                        <div class="stats-value count-up" data-count="<?php
                            $totalHealthProducts = $healthDistribution['Green'] + $healthDistribution['Amber'] + $healthDistribution['Red'];
                            $healthPercentage = $totalHealthProducts > 0 ?
                                round(($healthDistribution['Green'] / $totalHealthProducts) * 100) : 0;
                            echo $healthPercentage;
                            ?>">0</div>
                        <div class="stats-label">Health Rating %</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Charts -->
        <div class="col-lg-8">
            <div class="app-card">
                <div class="app-card-header">
                    <h5 class="app-card-title">Products Overview</h5>
                    <div class="app-card-toolbar">
                        <select class="form-select form-select-sm" id="chartRangeSelector">
                            <option value="weekly">Weekly</option>
                            <option value="monthly" selected>Monthly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                </div>
                <div class="app-card-body">
                    <div class="chart-container">
                        <canvas id="productOverviewChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="app-card">
                <div class="app-card-header">
                    <h5 class="app-card-title">Health Distribution</h5>
                </div>
                <div class="app-card-body">
                    <div class="chart-container">
                        <canvas id="healthDistributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Products -->
        <div class="col-lg-6">
            <div class="app-card">
                <div class="app-card-header">
                    <h5 class="app-card-title">Recent Products</h5>
                    <div class="app-card-toolbar">
                        <a href="products_management.php" class="btn btn-outline-primary btn-sm">View All</a>
                    </div>
                </div>
                <div class="app-card-body">
                    <?php if ($recentProducts && $recentProducts->num_rows > 0): ?>
                        <?php while ($product = $recentProducts->fetch_assoc()): ?>
                            <div class="recent-item">
                                <div class="recent-item-icon <?php echo strtolower($product['healthy_option']); ?>">
                                    <i class="bi bi-box"></i>
                                </div>
                                <div class="recent-item-content">
                                    <span class="recent-item-title"><?php echo htmlspecialchars($product['product_name']); ?></span>
                                    <span class="recent-item-info">Health Rating: <?php echo htmlspecialchars($product['healthy_option']); ?></span>
                                </div>
                                <div class="recent-item-date">
                                    <?php echo $product['date_created']; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-3 text-center">
                            <div class="text-muted">No recent products found</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Project Progress -->
        <div class="col-lg-6">
            <div class="app-card">
                <div class="app-card-header">
                    <h5 class="app-card-title">Project Progress</h5>
                </div>
                <div class="app-card-body">
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Product Catalog Update</span>
                            <span>75%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 75%" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Nutritional Analysis</span>
                            <span>45%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-info" role="progressbar" style="width: 45%" aria-valuenow="45" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <span>User Database Migration</span>
                            <span>90%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: 90%" aria-valuenow="90" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Recipe Documentation</span>
                            <span>30%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-danger" role="progressbar" style="width: 30%" aria-valuenow="30" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Mobile App Development</span>
                            <span>60%</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: 60%" aria-valuenow="60" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Quick Actions -->
        <div class="col-12">
            <div class="app-card">
                <div class="app-card-header">
                    <h5 class="app-card-title">Quick Actions</h5>
                </div>
                <div class="app-card-body">
                    <div class="row justify-content-center">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="product.php" class="btn btn-outline-primary btn-lg w-100 h-100 d-flex flex-column align-items-center justify-content-center p-4">
                                <i class="bi bi-plus-circle mb-2" style="font-size: 2rem;"></i>
                                <span>Add Product</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="categories_groups.php?tab=categories" class="btn btn-outline-info btn-lg w-100 h-100 d-flex flex-column align-items-center justify-content-center p-4">
                                <i class="bi bi-tags mb-2" style="font-size: 2rem;"></i>
                                <span>Manage Categories</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="add_user.php" class="btn btn-outline-success btn-lg w-100 h-100 d-flex flex-column align-items-center justify-content-center p-4">
                                <i class="bi bi-person-plus mb-2" style="font-size: 2rem;"></i>
                                <span>Add User</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="reports.php" class="btn btn-outline-warning btn-lg w-100 h-100 d-flex flex-column align-items-center justify-content-center p-4">
                                <i class="bi bi-file-earmark-text mb-2" style="font-size: 2rem;"></i>
                                <span>Generate Report</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="mt-4 mb-3">
        <div class="text-center text-muted">
            Copyright © <?php echo date("Y"); ?> Zouki Group of Companies. All rights reserved.
        </div>
    </footer>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>