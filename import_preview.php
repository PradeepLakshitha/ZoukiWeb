<?php
require_once 'session_check.php';
check_session(['Admin', 'Manager']);
include 'db_connection.php';
include 'includes/functions.php';

// Page-specific variables
$page_title = 'Import Preview';
$active_page = 'settings';

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

// Check if we have an import file in the session
if (!isset($_SESSION['import_file']) || empty($_SESSION['import_file'])) {
    $_SESSION['error'] = "No import file found. Please upload a file first.";
    header("Location: import_export_setup.php");
    exit();
}

$import_file = $_SESSION['import_file'];
$file_info = pathinfo($import_file);
$file_extension = strtolower($file_info['extension']);

// Initialize preview data
$preview_data = [];
$column_headers = [];
$total_rows = 0;
$error_rows = [];

// Extract data from uploaded file (sample implementation)
try {
    if ($file_extension == 'csv') {
        // Parse CSV file
        if (($handle = fopen($import_file, "r")) !== FALSE) {
            // Read first row as headers
            if (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $column_headers = $data;
            }
            
            // Read up to 10 rows for preview
            $row_count = 0;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE && $row_count < 10) {
                $preview_data[] = $data;
                $row_count++;
            }
            
            // Count total rows
            $total_rows = $row_count;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $total_rows++;
            }
            
            fclose($handle);
        }
    } elseif ($file_extension == 'xlsx' || $file_extension == 'xls') {
        // For XLSX/XLS, we'll just show a placeholder message
        // In a real implementation, you would use a library like PhpSpreadsheet
        $column_headers = ['Column data will be extracted from Excel file'];
        $preview_data = [['Excel data preview would appear here']];
        $total_rows = 'Unknown (Excel file)';
    } else {
        throw new Exception("Unsupported file format");
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error processing file: " . $e->getMessage();
    header("Location: import_export_setup.php");
    exit();
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action']) && $_POST['action'] == 'confirm_import') {
        // In a real implementation, this would actually import the data
        // For now, just simulate successful import
        
        // Simulate recording the import in history
        $file_name = basename($import_file);
        $history_query = "INSERT INTO import_export_history (type, file_name, status, records, created_by, created_at) 
                         VALUES ('Import', ?, 'Completed', ?, ?, NOW())";
        
        // Check if the table exists before attempting to insert
        $table_exists = false;
        $check_table = $conn->query("SHOW TABLES LIKE 'import_export_history'");
        if ($check_table && $check_table->num_rows > 0) {
            $table_exists = true;
        }
        
        if ($table_exists) {
            try {
                $stmt = $conn->prepare($history_query);
                $stmt->bind_param("sis", $file_name, $total_rows, $userName);
                $stmt->execute();
            } catch (Exception $e) {
                // Silently handle any errors with the history recording
            }
        }
        
        $_SESSION['success'] = "Successfully imported " . $total_rows . " records.";
        unset($_SESSION['import_file']); // Clear the import file from session
        header("Location: import_export_setup.php");
        exit();
    } elseif (isset($_POST['action']) && $_POST['action'] == 'cancel_import') {
        // Cancel import
        if (file_exists($import_file)) {
            unlink($import_file); // Delete the uploaded file
        }
        unset($_SESSION['import_file']); // Clear the import file from session
        $_SESSION['info'] = "Import cancelled.";
        header("Location: import_export_setup.php");
        exit();
    }
}

// Add page-specific CSS
$additional_css = '
.preview-table {
    border-collapse: separate;
    border-spacing: 0;
    width: 100%;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
}

.preview-table th {
    background-color: #f8f9fa;
    font-weight: 600;
    color: #2c3e50;
    position: sticky;
    top: 0;
    z-index: 10;
}

.preview-table th, .preview-table td {
    padding: 10px;
    border-bottom: 1px solid #dee2e6;
}

.preview-table tr:last-child td {
    border-bottom: none;
}

.preview-container {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    margin-bottom: 20px;
}

.file-info {
    background-color: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.file-info-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 1px solid #dee2e6;
}

.file-info-item:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.import-warning {
    background-color: rgba(255, 193, 7, 0.1);
    border-left: 4px solid #ffc107;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}
';

// Include header
include 'includes/header.php';
?>

<!-- Main Content -->
<div class="container-fluid">
    <div class="app-card mb-4">
        <div class="app-card-header">
            <h5 class="app-card-title">
                <i class="bi bi-file-earmark-spreadsheet"></i> Import Preview
            </h5>
            <div class="app-card-toolbar">
                <a href="import_export_setup.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Back to Import/Export
                </a>
            </div>
        </div>
        <div class="app-card-body">
            <div class="file-info">
                <div class="file-info-item">
                    <strong>File Name:</strong>
                    <span><?php echo basename($import_file); ?></span>
                </div>
                <div class="file-info-item">
                    <strong>File Type:</strong>
                    <span><?php echo strtoupper($file_extension); ?></span>
                </div>
                <div class="file-info-item">
                    <strong>File Size:</strong>
                    <span><?php echo number_format(filesize($import_file) / 1024, 2); ?> KB</span>
                </div>
                <div class="file-info-item">
                    <strong>Total Records:</strong>
                    <span><?php echo $total_rows; ?></span>
                </div>
            </div>

            <?php if (!empty($error_rows)): ?>
                <div class="alert alert-warning">
                    <h6><i class="bi bi-exclamation-triangle"></i> Import Warnings</h6>
                    <p>The following rows have potential issues:</p>
                    <ul>
                        <?php foreach ($error_rows as $row_num => $error): ?>
                            <li>Row <?php echo $row_num; ?>: <?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="import-warning">
                <h6><i class="bi bi-info-circle"></i> Import Preview</h6>
                <p>Below is a preview of the data that will be imported. Please review it carefully before proceeding.</p>
                <p class="mb-0">
                    <strong>Note:</strong> This preview shows up to 10 rows. The actual import will process all 
                    <?php echo $total_rows; ?> rows in the file.
                </p>
            </div>

            <div class="preview-container">
                <table class="preview-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">Row</th>
                            <?php foreach ($column_headers as $header): ?>
                                <th><?php echo htmlspecialchars($header); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview_data as $row_index => $row): ?>
                            <tr>
                                <td class="text-center"><?php echo $row_index + 1; ?></td>
                                <?php foreach ($row as $cell): ?>
                                    <td><?php echo htmlspecialchars($cell); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <h6>Field Mapping</h6>
                        <p class="text-muted small">
                            Match columns from your file to product fields in the database.
                            System will attempt to auto-map based on column names.
                        </p>
                        
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>File Column</th>
                                        <th>Database Field</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($column_headers as $index => $header): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($header); ?></td>
                                            <td>
                                                <select class="form-select form-select-sm" name="field_mapping[<?php echo $index; ?>]">
                                                    <option value="">-- Ignore this column --</option>
                                                    <option value="product_name" <?php echo strtolower($header) == 'product_name' || strtolower($header) == 'name' ? 'selected' : ''; ?>>Product Name</option>
                                                    <option value="allergens" <?php echo strtolower($header) == 'allergens' ? 'selected' : ''; ?>>Allergens</option>
                                                    <option value="ingredients" <?php echo strtolower($header) == 'ingredients' ? 'selected' : ''; ?>>Ingredients</option>
                                                    <option value="healthy_option" <?php echo strtolower($header) == 'healthy_option' || strtolower($header) == 'health' ? 'selected' : ''; ?>>Health Rating</option>
                                                    <option value="recipe" <?php echo strtolower($header) == 'recipe' ? 'selected' : ''; ?>>Recipe</option>
                                                    <option value="categories" <?php echo strtolower($header) == 'categories' || strtolower($header) == 'category' ? 'selected' : ''; ?>>Categories</option>
                                                    <option value="groups" <?php echo strtolower($header) == 'groups' || strtolower($header) == 'group' ? 'selected' : ''; ?>>Groups</option>
                                                </select>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <h6>Import Options</h6>
                        
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="updateExisting" name="update_existing" checked>
                            <label class="form-check-label" for="updateExisting">
                                Update existing products if they already exist
                            </label>
                        </div>
                        
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="skipErrors" name="skip_errors" checked>
                            <label class="form-check-label" for="skipErrors">
                                Skip rows with errors
                            </label>
                        </div>
                        
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="createCategories" name="create_categories" checked>
                            <label class="form-check-label" for="createCategories">
                                Create categories and groups if they don't exist
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="d-flex justify-content-between mt-4">
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="cancel_import">
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg"></i> Cancel Import
                    </button>
                </form>
                
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="confirm_import">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Confirm and Import
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Define page-specific scripts
$page_scripts = '';

// Include footer
include 'includes/footer.php';
?>