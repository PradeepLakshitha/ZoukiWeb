<?php
require_once 'session_check.php';
check_session(['Admin', 'Manager']);
include 'db_connection.php';
include 'includes/functions.php';

// Page-specific variables
$page_title = 'User Management';
$active_page = 'users';

// Get the logged-in user's information
$userName = $_SESSION['username'];
$userType = $_SESSION['uType'];
$userId = $_SESSION['userID'] ?? 0;

// Get user details
$userDetails = getUserDetails($conn, $userName);

// Get unread notification count
$unreadCount = getUnreadNotificationCount($conn, $userId);

// Get recent notifications
$notificationsResult = getRecentNotifications($conn, $userId);

// Ensure only Admin & Manager can access
if (!isset($_SESSION['username']) || ($_SESSION['uType'] !== 'Admin' && $_SESSION['uType'] !== 'Manager')) {
    $_SESSION['error'] = "Access denied!";
    header("Location: dashboard.php");
    exit();
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

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Items per page
$offset = ($page - 1) * $limit;

// Search functionality
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$search_condition = '';
if (!empty($search)) {
    $search_condition = "WHERE username LIKE '%$search%' OR first_name LIKE '%$search%' OR last_name LIKE '%$search%' OR email LIKE '%$search%'";
}

// Filter by user type
$typeFilter = isset($_GET['type']) ? $conn->real_escape_string($_GET['type']) : '';
if (!empty($typeFilter)) {
    $search_condition = $search_condition ? $search_condition . " AND uType = '$typeFilter'" : "WHERE uType = '$typeFilter'";
}

// Filter by status
$statusFilter = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
if (!empty($statusFilter)) {
    $search_condition = $search_condition ? $search_condition . " AND status = '$statusFilter'" : "WHERE status = '$statusFilter'";
}

// Count total users for pagination
$count_query = "SELECT COUNT(*) as total FROM z_user $search_condition";
$count_result = $conn->query($count_query);
$total_users = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_users / $limit);

// Fetch users with pagination
$users_query = "
    SELECT userID, username, first_name, last_name, email, uType, status, profile_photo, 
           DATE_FORMAT(created_at, '%d %b %Y') as joined_date
    FROM z_user
    $search_condition
    ORDER BY created_at DESC
    LIMIT $offset, $limit
";
$users_result = $conn->query($users_query);

// Check for session messages
$successMessage = $_SESSION['success'] ?? '';
$errorMessage = $_SESSION['error'] ?? '';

// Clear session messages
if (isset($_SESSION['success'])) unset($_SESSION['success']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);

// Handle User Status Toggle
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $user_id = (int) $_POST['user_id'];
    $new_status = $_POST['new_status'];
    
    // Don't allow users to deactivate themselves
    if ($user_id == $userId) {
        $_SESSION['error'] = "You cannot change your own status.";
        header("Location: users.php");
        exit();
    }
    
    // Update user status
    $stmt = $conn->prepare("UPDATE z_user SET status = ? WHERE userID = ?");
    $stmt->bind_param("si", $new_status, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "User status updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update user status.";
    }
    
    header("Location: users.php");
    exit();
}

// Add page-specific CSS
$additional_css = '
.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    background-color: #f1f1f1;
}

.user-avatar-placeholder {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6c757d;
    font-weight: 500;
    font-size: 1rem;
}

.user-type-badge {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.user-type-badge.admin {
    background-color: rgba(220, 53, 69, 0.1);
    color: var(--danger-color);
}

.user-type-badge.manager {
    background-color: rgba(255, 193, 7, 0.1);
    color: var(--warning-color);
}

.user-type-badge.user {
    background-color: rgba(40, 167, 69, 0.1);
    color: var(--success-color);
}

.status-badge {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.status-badge.active {
    background-color: rgba(40, 167, 69, 0.1);
    color: var(--success-color);
}

.status-badge.inactive {
    background-color: rgba(108, 117, 125, 0.1);
    color: var(--secondary-color);
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
    <a href="add_user.php" class="btn btn-add">
        <i class="bi bi-person-plus"></i>
        <span>Add User</span>
    </a>
</div>

<!-- Main Content -->
<div class="container-fluid">
    <!-- Filter & Search Bar -->
    <div class="app-card mb-4">
        <div class="app-card-body">
            <form action="" method="GET" class="filter-bar">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Search users..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-outline-primary" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </div>

                <select class="form-select" name="type" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <option value="Admin" <?php echo $typeFilter === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="Manager" <?php echo $typeFilter === 'Manager' ? 'selected' : ''; ?>>Manager</option>
                    <option value="User" <?php echo $typeFilter === 'User' ? 'selected' : ''; ?>>User</option>
                </select>

                <select class="form-select" name="status" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>

                <?php if ($search || $typeFilter || $statusFilter): ?>
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg"></i> Clear Filters
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="app-card">
        <div class="app-card-header">
            <h5 class="app-card-title">Users</h5>
            <div class="app-card-toolbar">
                <span class="text-muted">Total: <?php echo $total_users; ?> users</span>
            </div>
        </div>
        <div class="app-card-body p-0">
            <?php if ($users_result && $users_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th width="5%"></th>
                            <th width="20%">Name</th>
                            <th width="15%">Username</th>
                            <th width="20%">Email</th>
                            <th width="10%">Type</th>
                            <th width="10%">Status</th>
                            <th width="10%">Joined</th>
                            <th width="10%" class="text-center">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php while ($user = $users_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($user['profile_photo']) && file_exists($user['profile_photo'])): ?>
                                        <img src="<?php echo htmlspecialchars($user['profile_photo']); ?>" alt="<?php echo htmlspecialchars($user['username']); ?>" class="user-avatar">
                                    <?php else: ?>
                                        <div class="user-avatar-placeholder">
                                            <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <?php
                                    $type_class = strtolower($user['uType']);
                                    echo '<span class="user-type-badge ' . $type_class . '">' . htmlspecialchars($user['uType']) . '</span>';
                                    ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $user['status']; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $user['joined_date']; ?></td>
                                <td class="text-center">
                                    <div class="action-buttons">
                                        <button type="button" class="btn btn-sm btn-outline-primary me-1"
                                                data-bs-toggle="modal"
                                                data-bs-target="#viewUserModal"
                                                data-id="<?php echo $user['userID']; ?>"
                                                data-name="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>"
                                                data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                data-type="<?php echo htmlspecialchars($user['uType']); ?>"
                                                data-status="<?php echo htmlspecialchars($user['status']); ?>"
                                                data-joined="<?php echo $user['joined_date']; ?>"
                                                data-photo="<?php echo htmlspecialchars($user['profile_photo'] ?? ''); ?>">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        
                                        <?php if ($user['userID'] != $userId): // Don't allow toggling own status ?>
                                            <?php if ($user['status'] === 'active'): ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#toggleStatusModal"
                                                        data-id="<?php echo $user['userID']; ?>"
                                                        data-name="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>"
                                                        data-status="<?php echo $user['status']; ?>"
                                                        data-new-status="inactive">
                                                    <i class="bi bi-toggle-off"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-outline-success"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#toggleStatusModal"
                                                        data-id="<?php echo $user['userID']; ?>"
                                                        data-name="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>"
                                                        data-status="<?php echo $user['status']; ?>"
                                                        data-new-status="active">
                                                    <i class="bi bi-toggle-on"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                                                <i class="bi bi-lock"></i>
                                            </button>
                                        <?php endif; ?>
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
                                        <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($typeFilter); ?>&status=<?php echo urlencode($statusFilter); ?>" aria-label="Previous">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($typeFilter); ?>&status=<?php echo urlencode($statusFilter); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo urlencode($typeFilter); ?>&status=<?php echo urlencode($statusFilter); ?>" aria-label="Next">
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
                        <i class="bi bi-people text-muted" style="font-size: 3rem;"></i>
                    </div>
                    <h5>No Users Found</h5>
                    <?php if ($search || $typeFilter || $statusFilter): ?>
                        <p class="text-muted mb-4">No users match your search or filter criteria. Try changing your filters or adding new users.</p>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline-secondary me-2">
                            <i class="bi bi-x-lg"></i> Clear Filters
                        </a>
                    <?php else: ?>
                        <p class="text-muted mb-4">You haven't added any users yet. Start by adding your first user.</p>
                    <?php endif; ?>
                    <a href="add_user.php" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i> Add User
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- View User Modal -->
<div class="modal fade" id="viewUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">User Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <div id="modal-user-photo" class="mb-3" style="width: 100px; height: 100px; margin: 0 auto;"></div>
                    <h5 id="modal-user-name" class="mb-1">User Name</h5>
                    <span id="modal-user-type" class="user-type-badge admin">Admin</span>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Username</label>
                        <p id="modal-username" class="mb-0">username</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Email</label>
                        <p id="modal-email" class="mb-0">email@example.com</p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Status</label>
                        <p id="modal-status" class="mb-0">
                            <span class="status-badge active">Active</span>
                        </p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Joined Date</label>
                        <p id="modal-joined" class="mb-0">01 Jan 2025</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Toggle Status Modal -->
<div class="modal fade" id="toggleStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change User Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="users.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="user_id" id="toggle-user-id">
                    <input type="hidden" name="new_status" id="toggle-new-status">
                    
                    <p>Are you sure you want to <span id="toggle-action-text">activate</span> user <strong id="toggle-user-name"></strong>?</p>
                    
                    <div id="toggle-activate-message" class="alert alert-success d-none">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        The user will be able to log in and access the system again.
                    </div>
                    
                    <div id="toggle-deactivate-message" class="alert alert-warning d-none">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        The user will not be able to log in until their account is activated again.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        Confirm
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Define page-specific scripts
$page_scripts = '
// View User Modal
const viewUserModal = document.getElementById("viewUserModal");
if (viewUserModal) {
    viewUserModal.addEventListener("show.bs.modal", function (event) {
        // Button that triggered the modal
        const button = event.relatedTarget;

        // Extract info from data-* attributes
        const userId = button.getAttribute("data-id");
        const userName = button.getAttribute("data-name");
        const username = button.getAttribute("data-username");
        const email = button.getAttribute("data-email");
        const userType = button.getAttribute("data-type");
        const status = button.getAttribute("data-status");
        const joined = button.getAttribute("data-joined");
        const photo = button.getAttribute("data-photo");

        // Update the modal\'s content
        document.getElementById("modal-user-name").textContent = userName;
        document.getElementById("modal-username").textContent = username;
        document.getElementById("modal-email").textContent = email;
        document.getElementById("modal-joined").textContent = joined;

        // Set type badge
        const typeBadge = document.getElementById("modal-user-type");
        typeBadge.textContent = userType;
        typeBadge.className = "user-type-badge " + userType.toLowerCase();

        // Set status badge
        const statusElement = document.getElementById("modal-status");
        statusElement.innerHTML = `<span class="status-badge ${status}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;

        // Handle user photo
        const photoContainer = document.getElementById("modal-user-photo");
        if (photo && photo !== "") {
            photoContainer.innerHTML = `<img src="${photo}" alt="${userName}" class="rounded-circle" style="width: 100px; height: 100px; object-fit: cover;">`;
        } else {
            const initials = userName.split(" ").map(name => name.charAt(0)).join("").toUpperCase();
            photoContainer.innerHTML = `
                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                    <span style="font-size: 2rem; color: #6c757d;">${initials}</span>
                </div>
            `;
        }
    });
}

// Toggle Status Modal
const toggleStatusModal = document.getElementById("toggleStatusModal");
if (toggleStatusModal) {
    toggleStatusModal.addEventListener("show.bs.modal", function (event) {
        // Button that triggered the modal
        const button = event.relatedTarget;

        // Extract info from data-* attributes
        const userId = button.getAttribute("data-id");
        const userName = button.getAttribute("data-name");
        const newStatus = button.getAttribute("data-new-status");

        // Update the modal\'s content
        document.getElementById("toggle-user-id").value = userId;
        document.getElementById("toggle-user-name").textContent = userName;
        document.getElementById("toggle-new-status").value = newStatus;

        // Update text based on action
        if (newStatus === "active") {
            document.getElementById("toggle-action-text").textContent = "activate";
            document.getElementById("toggle-activate-message").classList.remove("d-none");
            document.getElementById("toggle-deactivate-message").classList.add("d-none");
        } else {
            document.getElementById("toggle-action-text").textContent = "deactivate";
            document.getElementById("toggle-activate-message").classList.add("d-none");
            document.getElementById("toggle-deactivate-message").classList.remove("d-none");
        }
    });
}
';

// Include footer
include 'includes/footer.php';
?>