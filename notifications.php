<?php
require_once 'session_check.php';
check_session(); // Allow all authenticated users
include 'db_connection.php';
include 'includes/functions.php';  // Include common functions

// Page-specific variables
$page_title = 'Notifications';
$active_page = 'notifications';

// Get the logged-in user's information
$userName = $_SESSION['username'];
$userType = $_SESSION['uType'];
$userId = $_SESSION['userID'] ?? 0;

// Debug info
error_log("Notifications.php loading for user $userName (ID: $userId)");

// Get user details
$userDetails = getUserDetails($conn, $userName);

// Get unread notification count
$unreadCount = getUnreadNotificationCount($conn, $userId);

// Setup pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Items per page
$offset = ($page - 1) * $limit;

// Filter setup
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$filterCondition = '';
switch ($filter) {
    case 'unread':
        $filterCondition = " AND is_read = 0";
        break;
    case 'read':
        $filterCondition = " AND is_read = 1";
        break;
    default:
        $filterCondition = "";
}

// Type filter
$typeFilter = isset($_GET['type']) ? $_GET['type'] : 'all';
$typeCondition = '';
if ($typeFilter !== 'all') {
    $typeCondition = " AND type = '" . $conn->real_escape_string($typeFilter) . "'";
}

// Direct SQL query to ensure results
$sql = "SELECT notification_id, type, title, message, is_read, created_at 
        FROM notifications 
        WHERE user_id = $userId $filterCondition $typeCondition
        ORDER BY created_at DESC 
        LIMIT $offset, $limit";

error_log("Executing SQL: $sql");

// Execute query directly
$notificationResult = $conn->query($sql);
$notificationCount = $notificationResult ? $notificationResult->num_rows : 0;
error_log("Found $notificationCount notifications");

// Count total for pagination
$totalSql = "SELECT COUNT(*) as total FROM notifications WHERE user_id = $userId $filterCondition $typeCondition";
$totalResult = $conn->query($totalSql);
$totalRow = $totalResult->fetch_assoc();
$totalNotifications = $totalRow['total'];
$totalPages = ceil($totalNotifications / $limit);

// Process AJAX actions
if (isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    if ($_POST['action'] === 'mark_read') {
        $notificationId = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
        
        if ($notificationId > 0) {
            $updateSql = "UPDATE notifications SET is_read = 1 WHERE notification_id = $notificationId AND user_id = $userId";
            if ($conn->query($updateSql)) {
                $response['success'] = true;
                $response['message'] = 'Notification marked as read';
            } else {
                $response['message'] = 'Failed to mark notification as read';
            }
        }
    }
    elseif ($_POST['action'] === 'delete') {
        $notificationId = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
        
        if ($notificationId > 0) {
            $deleteSql = "DELETE FROM notifications WHERE notification_id = $notificationId AND user_id = $userId";
            if ($conn->query($deleteSql)) {
                $response['success'] = true;
                $response['message'] = 'Notification deleted';
            } else {
                $response['message'] = 'Failed to delete notification';
            }
        }
    }
    elseif ($_POST['action'] === 'mark_all_read') {
        $updateAllSql = "UPDATE notifications SET is_read = 1 WHERE user_id = $userId AND is_read = 0";
        if ($conn->query($updateAllSql)) {
            $affectedRows = $conn->affected_rows;
            $response['success'] = true;
            $response['count'] = $affectedRows;
            $response['message'] = "$affectedRows notification(s) marked as read";
        } else {
            $response['message'] = 'Failed to mark notifications as read';
        }
    }
    elseif ($_POST['action'] === 'delete_all') {
        $deleteAllSql = "DELETE FROM notifications WHERE user_id = $userId $filterCondition $typeCondition";
        if ($conn->query($deleteAllSql)) {
            $affectedRows = $conn->affected_rows;
            $response['success'] = true;
            $response['count'] = $affectedRows;
            $response['message'] = "$affectedRows notification(s) deleted";
        } else {
            $response['message'] = 'Failed to delete notifications';
        }
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Process form submissions
if (isset($_POST['form_action'])) {
    if ($_POST['form_action'] === 'mark_selected') {
        $selected = isset($_POST['selected']) ? $_POST['selected'] : [];
        if (!empty($selected)) {
            $ids = implode(',', array_map('intval', $selected));
            $updateSelectedSql = "UPDATE notifications SET is_read = 1 WHERE notification_id IN ($ids) AND user_id = $userId";
            if ($conn->query($updateSelectedSql)) {
                $_SESSION['success'] = $conn->affected_rows . " notification(s) marked as read";
            } else {
                $_SESSION['error'] = "Failed to update notifications";
            }
        } else {
            $_SESSION['error'] = "No notifications selected";
        }
    }
    elseif ($_POST['form_action'] === 'delete_selected') {
        $selected = isset($_POST['selected']) ? $_POST['selected'] : [];
        if (!empty($selected)) {
            $ids = implode(',', array_map('intval', $selected));
            $deleteSelectedSql = "DELETE FROM notifications WHERE notification_id IN ($ids) AND user_id = $userId";
            if ($conn->query($deleteSelectedSql)) {
                $_SESSION['success'] = $conn->affected_rows . " notification(s) deleted";
            } else {
                $_SESSION['error'] = "Failed to delete notifications";
            }
        } else {
            $_SESSION['error'] = "No notifications selected";
        }
    }
    
    // Redirect to refresh page
    header("Location: notifications.php?filter=$filter&type=$typeFilter&page=$page");
    exit;
}

// Get session messages
$successMessage = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$errorMessage = isset($_SESSION['error']) ? $_SESSION['error'] : '';

// Clear session messages
if (isset($_SESSION['success'])) unset($_SESSION['success']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);

// Icon and color helper functions
function getIcon($type) {
    switch ($type) {
        case 'product': return 'bi-box';
        case 'system': return 'bi-gear';
        case 'user': return 'bi-person';
        default: return 'bi-bell';
    }
}

function getColor($type, $message = '') {
    switch ($type) {
        case 'product':
            if (strpos($message, 'Amber') !== false) return 'amber';
            if (strpos($message, 'Red') !== false) return 'red';
            return 'green';
        case 'system': return 'blue';
        case 'user': return 'purple';
        default: return 'primary';
    }
}
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

// Add CSS for notifications
$additional_css = '
.notification-item {
    padding: 16px;
    border-bottom: 1px solid #e5e5e5;
    display: flex;
    align-items: flex-start;
}
.notification-item:hover {
    background-color: rgba(0,0,0,0.02);
}
.notification-item.read {
    opacity: 0.7;
}
.notification-checkbox {
    margin-right: 10px;
    margin-top: 4px;
}
.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    margin-right: 16px;
    color: white;
}
.notification-icon.green { background-color: #28a745; }
.notification-icon.amber { background-color: #ffc107; }
.notification-icon.red { background-color: #dc3545; }
.notification-icon.blue { background-color: #17a2b8; }
.notification-icon.purple { background-color: #6f42c1; }
.notification-content {
    flex: 1;
}
.notification-title {
    font-weight: bold;
    margin-bottom: 5px;
}
.notification-message {
    margin-bottom: 5px;
    color: #666;
}
.notification-time {
    font-size: 12px;
    color: #999;
}
.notification-actions {
    margin-left: 10px;
    display: flex;
    gap: 5px;
}
.btn-action {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border: none;
    color: #6c757d;
    cursor: pointer;
}
.btn-action:hover {
    background-color: rgba(0,0,0,0.05);
}
.btn-action.delete:hover {
    color: #dc3545;
}
';

// JavaScript for notifications
$page_scripts = '
document.addEventListener("DOMContentLoaded", function() {
    // Mark as read buttons
    document.querySelectorAll(".btn-mark-read").forEach(btn => {
        btn.addEventListener("click", function() {
            const id = this.getAttribute("data-id");
            const item = this.closest(".notification-item");
            
            fetch("notifications.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: `action=mark_read&notification_id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
                    item.classList.add("read");
                    this.remove();
                    showAlert("success", data.message);
                    
                    // Update sidebar badge if exists
                    updateUnreadBadge(-1);
                } else {
                    showAlert("danger", data.message || "Failed to mark as read");
                }
            })
            .catch(error => {
                console.error("Error:", error);
                showAlert("danger", "An error occurred");
            });
        });
    });
    
    // Delete buttons
    document.querySelectorAll(".btn-delete").forEach(btn => {
        btn.addEventListener("click", function() {
            if (confirm("Are you sure you want to delete this notification?")) {
                const id = this.getAttribute("data-id");
                const item = this.closest(".notification-item");
                const isUnread = !item.classList.contains("read");
                
                fetch("notifications.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                    },
                    body: `action=delete&notification_id=${id}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update UI
                        item.remove();
                        showAlert("success", data.message);
                        
                        // Update counter if unread
                        if (isUnread) {
                            updateUnreadBadge(-1);
                        }
                        
                        // If no notifications left, show empty state
                        if (document.querySelectorAll(".notification-item").length === 0) {
                            document.querySelector(".notifications-list").innerHTML = `
                                <div class="text-center p-5">
                                    <div class="display-1 text-muted mb-3">
                                        <i class="bi bi-bell-slash"></i>
                                    </div>
                                    <h3 class="text-muted mb-3">No notifications found</h3>
                                    <p class="text-muted mb-4">You don\'t have any notifications at the moment.</p>
                                </div>
                            `;
                        }
                    } else {
                        showAlert("danger", data.message || "Failed to delete notification");
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    showAlert("danger", "An error occurred");
                });
            }
        });
    });
    
    // Mark all as read button
    const markAllBtn = document.getElementById("mark-all-read");
    if (markAllBtn) {
        markAllBtn.addEventListener("click", function() {
            if (confirm("Mark all notifications as read?")) {
                fetch("notifications.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                    },
                    body: "action=mark_all_read"
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update UI
                        document.querySelectorAll(".notification-item").forEach(item => {
                            item.classList.add("read");
                            const markBtn = item.querySelector(".btn-mark-read");
                            if (markBtn) markBtn.remove();
                        });
                        showAlert("success", data.message);
                        
                        // Update sidebar badge
                        updateUnreadBadge(-(data.count || 0));
                    } else {
                        showAlert("danger", data.message || "Failed to mark all as read");
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    showAlert("danger", "An error occurred");
                });
            }
        });
    }
    
    // Delete all button
    const deleteAllBtn = document.getElementById("delete-all");
    if (deleteAllBtn) {
        deleteAllBtn.addEventListener("click", function() {
            if (confirm("Delete all notifications? This cannot be undone.")) {
                fetch("notifications.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                    },
                    body: "action=delete_all"
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reload page to update everything
                        showAlert("success", data.message);
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showAlert("danger", data.message || "Failed to delete notifications");
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    showAlert("danger", "An error occurred");
                });
            }
        });
    }
    
    // Select all checkbox
    const selectAllCheckbox = document.getElementById("select-all");
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener("click", function() {
            const checkboxes = document.querySelectorAll(".notification-checkbox");
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }
    
    // Filter change
    const filterSelect = document.getElementById("filter-select");
    const typeSelect = document.getElementById("type-select");
    const applyFiltersBtn = document.getElementById("apply-filters");
    
    if (applyFiltersBtn && filterSelect && typeSelect) {
        applyFiltersBtn.addEventListener("click", function() {
            const filter = filterSelect.value;
            const type = typeSelect.value;
            window.location.href = `notifications.php?filter=${filter}&type=${type}`;
        });
    }
});

// Helper function to update unread badge
function updateUnreadBadge(change) {
    const badge = document.querySelector(".sidebar-notifications-badge");
    if (badge) {
        const currentCount = parseInt(badge.textContent || "0");
        const newCount = Math.max(0, currentCount + change);
        
        if (newCount <= 0) {
            badge.style.display = "none";
        } else {
            badge.textContent = newCount;
            badge.style.display = "inline-block";
        }
    }
}

// Helper function to show alerts
function showAlert(type, message) {
    // Create alert container if it doesn\'t exist
    let alertContainer = document.querySelector(".alert-container");
    if (!alertContainer) {
        alertContainer = document.createElement("div");
        alertContainer.className = "alert-container position-fixed top-0 end-0 p-3";
        document.body.appendChild(alertContainer);
    }
    
    // Create alert
    const alertId = "alert-" + Date.now();
    const alertHTML = `
        <div id="${alertId}" class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    alertContainer.insertAdjacentHTML("beforeend", alertHTML);
    
    // Auto-dismiss after 3 seconds
    setTimeout(() => {
        const alertElement = document.getElementById(alertId);
        if (alertElement) {
            const bsAlert = new bootstrap.Alert(alertElement);
            bsAlert.close();
        }
    }, 3000);
}
';

// Include header
include 'includes/header.php';
?>

<!-- Main Content -->
<div class="container-fluid">
    <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <?php echo htmlspecialchars($successMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <?php echo htmlspecialchars($errorMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Notification Controls -->
    <div class="app-card mb-4">
        <div class="app-card-header">
            <h5 class="app-card-title">Notification Settings</h5>
            <div class="app-card-toolbar">
                <button type="button" class="btn btn-outline-primary btn-sm" id="mark-all-read">
                    <i class="bi bi-envelope-open"></i> Mark All as Read
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm ms-2" id="delete-all">
                    <i class="bi bi-trash"></i> Delete All
                </button>
            </div>
        </div>
        <div class="app-card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
                <div class="d-flex align-items-center mb-3 mb-md-0">
    <select class="form-select me-2" id="filter-select">
        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All</option>
        <option value="unread" <?php echo $filter === 'unread' ? 'selected' : ''; ?>>Unread Only</option>
        <option value="read" <?php echo $filter === 'read' ? 'selected' : ''; ?>>Read Only</option>
    </select>
    <select class="form-select" id="type-select">
        <option value="all" <?php echo $typeFilter === 'all' ? 'selected' : ''; ?>>All Types</option>
        <option value="product" <?php echo $typeFilter === 'product' ? 'selected' : ''; ?>>Product</option>
        <option value="system" <?php echo $typeFilter === 'system' ? 'selected' : ''; ?>>System</option>
        <option value="user" <?php echo $typeFilter === 'user' ? 'selected' : ''; ?>>User</option>
    </select>
    <!-- Apply Filters button removed -->
</div>
                
                <div class="d-flex align-items-center">
                    <form action="notifications.php" method="POST" class="d-flex">
                        <input type="hidden" name="form_action" id="form-action">
                        <button type="button" class="btn btn-outline-primary btn-sm me-2" onclick="submitForm('mark_selected')">
                            <i class="bi bi-envelope-open"></i> Mark Selected
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="submitForm('delete_selected')">
                            <i class="bi bi-trash"></i> Delete Selected
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications List -->
    <div class="app-card">
        <div class="app-card-header">
            <h5 class="app-card-title">Your Notifications</h5>
            <div class="app-card-toolbar">
                <span class="text-muted">Total: <?php echo $totalNotifications; ?> notifications</span>
            </div>
        </div>
        <div class="app-card-body">
            <form action="notifications.php" method="POST" id="notification-form">
                <input type="hidden" name="form_action" id="form_action">
                
                <?php if ($notificationCount > 0): ?>
                    <div class="d-flex align-items-center mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="select-all">
                            <label class="form-check-label" for="select-all">
                                Select All
                            </label>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="notifications-list">
                    <?php if ($notificationResult && $notificationResult->num_rows > 0): ?>
                        <?php while ($notification = $notificationResult->fetch_assoc()): ?>
                            <?php
                            $icon = getIcon($notification['type']);
                            $color = getColor($notification['type'], $notification['message']);
                            $time = getTimeAgo($notification['created_at']);
                            ?>
                            <div class="notification-item <?php echo $notification['is_read'] ? 'read' : ''; ?>">
                                <div class="notification-checkbox">
                                    <input type="checkbox" class="form-check-input" name="selected[]" value="<?php echo $notification['notification_id']; ?>">
                                </div>
                                <div class="notification-icon <?php echo $color; ?>">
                                    <i class="bi <?php echo $icon; ?>"></i>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                    <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                                    <div class="notification-time"><?php echo $time; ?></div>
                                </div>
                                <div class="notification-actions">
                                    <?php if (!$notification['is_read']): ?>
                                        <button type="button" class="btn-action btn-mark-read" title="Mark as read" data-id="<?php echo $notification['notification_id']; ?>">
                                            <i class="bi bi-envelope-open"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn-action btn-delete" title="Delete" data-id="<?php echo $notification['notification_id']; ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center p-5">
                            <div class="display-1 text-muted mb-3">
                                <i class="bi bi-bell-slash"></i>
                            </div>
                            <h3 class="text-muted mb-3">No notifications found</h3>
                            <p class="text-muted mb-4">You don't have any notifications at the moment.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="d-flex justify-content-center mt-4">
                    <nav aria-label="Page navigation">
                        <ul class="pagination">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?>&type=<?php echo $typeFilter; ?>" aria-label="Previous">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>&type=<?php echo $typeFilter; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?>&type=<?php echo $typeFilter; ?>" aria-label="Next">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Helper function to submit form with correct action
function submitForm(action) {
    document.getElementById('form_action').value = action;
    document.getElementById('notification-form').submit();
}
</script>
<script>
// Fix for the Select All checkbox
document.addEventListener("DOMContentLoaded", function() {
    const selectAllCheckbox = document.getElementById("select-all");
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener("click", function() {
            // Target the actual checkbox inputs inside the notification-checkbox divs
            const checkboxes = document.querySelectorAll(".notification-checkbox input[type='checkbox']");
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }
});
// Replace the button click handler with dropdown change handlers
const filterSelect = document.getElementById("filter-select");
const typeSelect = document.getElementById("type-select");

// Add change event listeners to both dropdowns
if (filterSelect && typeSelect) {
    // Function to apply both filters
    const applyFilters = function() {
        const filter = filterSelect.value;
        const type = typeSelect.value;
        window.location.href = `notifications.php?filter=${filter}&type=${type}`;
    };
    
    // Add change event listeners to both dropdowns
    filterSelect.addEventListener("change", applyFilters);
    typeSelect.addEventListener("change", applyFilters);
}
</script>

<?php
// Include footer
include 'includes/footer.php';
?>