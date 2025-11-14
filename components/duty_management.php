<?php
// === VERY TOP - Start output buffering ===
ob_start();
session_start();

// Check if user is logged in
if (!isset($_SESSION['officerid'])) {
    header("Location: ../login.php");
    exit();
}

// Include required files
require_once '../config/database.php';
require_once '../RoleManager.php';
//require_once '../components/navbar.php';

// Initialize RoleManager
$roleManager = new RoleManager($pdo);
$officerid = $_SESSION['officerid'];
$userInfo = $roleManager->getUserRank($officerid);

// Check and create duty_resources table if it doesn't exist
function checkAndCreateDutyResourcesTable($pdo) {
    try {
        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'duty_resources'");
        if ($stmt->rowCount() == 0) {
            $sql = "CREATE TABLE duty_resources (
                id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                dutyid INT(11) NOT NULL,
                resource_id INT(11) NOT NULL,
                assigned_by VARCHAR(20) NOT NULL,
                assignment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                quantity INT(11) DEFAULT 1,
                notes TEXT,
                UNIQUE KEY unique_duty_resource (dutyid, resource_id)
            )";
            
            $pdo->exec($sql);
            error_log("Created duty_resources table");
        } else {
            // Check if quantity column exists, if not add it
            try {
                $pdo->query("SELECT quantity FROM duty_resources LIMIT 1");
            } catch (PDOException $e) {
                $pdo->exec("ALTER TABLE duty_resources ADD COLUMN quantity INT(11) DEFAULT 1");
            }
            
            // Check if notes column exists, if not add it
            try {
                $pdo->query("SELECT notes FROM duty_resources LIMIT 1");
            } catch (PDOException $e) {
                $pdo->exec("ALTER TABLE duty_resources ADD COLUMN notes TEXT");
            }
        }
    } catch (Exception $e) {
        error_log("Error in checkAndCreateDutyResourcesTable: " . $e->getMessage());
    }
}

// Call the table check function
checkAndCreateDutyResourcesTable($pdo);

// Auto-filled officer details
$officer_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$force_number = $_SESSION['officerid'];

// Initialize variables
$success_msg = $error_msg = "";
$assigned_duties = $officers = $resources = [];

// Fetch existing data for display
try {
    // Fetch duties with officer names
    $stmt = $pdo->query("
        SELECT d.*, u.first_name, u.last_name, u.rank 
        FROM duties d
        JOIN userlogin u ON d.officerid = u.officerid 
        ORDER BY d.dutydate DESC
    ");
    $assigned_duties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch officers for assignment (all active officers except current admin users)
    $stmt = $pdo->query("
        SELECT officerid, rank, designation, first_name, last_name,
        CONCAT(first_name, ' ', last_name, ' (', rank, ', ', designation, ', ', officerid, ')') AS officer_label 
        FROM userlogin 
        WHERE disabled = 0 AND rank IN ('Constable', 'Sergeant', 'Inspector', 'Chief Inspector')
        ORDER BY rank DESC, first_name ASC
    ");
    $officers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch resources for linking
    $stmt = $pdo->query("SELECT id, title, description, resource_image FROM resources ORDER BY title");
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching data: " . $e->getMessage());
    $error_msg = "Failed to fetch data from database: " . $e->getMessage();
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $pdo->beginTransaction();

        // Handle Duty Assignment
        if (isset($_POST['assign_duty'])) {
            $task = trim($_POST['task'] ?? '');
            $dutydate = trim($_POST['dutydate'] ?? '');
            $assigner_id = $force_number;
            
            // Handle officer assignment
            $officerids = [];
            if (isset($_POST['officerid']) && !empty($_POST['officerid'])) {
                $officerids = [$_POST['officerid']];
            } elseif (isset($_POST['officerids']) && is_array($_POST['officerids'])) {
                $officerids = array_filter($_POST['officerids']);
            }
            
            if (empty($officerids)) {
                throw new Exception("No officers selected for assignment.");
            }
            
            if (empty($task)) {
                throw new Exception("Task description is required.");
            }
            
            if (empty($dutydate)) {
                throw new Exception("Duty date is required.");
            }
            
            $success_count = 0;
            foreach ($officerids as $officerid) {
                $sql = "INSERT INTO duties (officerid, assigner_id, task, dutydate) VALUES (?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$officerid, $assigner_id, $task, $dutydate])) {
                    $success_count++;
                }
            }
            
            $success_msg = "Duty assigned successfully to " . $success_count . " officer(s)!";
        }
        
        // Handle Resource Linking to Duty
        if (isset($_POST['link_resources'])) {
            $dutyid = $_POST['dutyid'] ?? '';
            $resource_ids = $_POST['resource_ids'] ?? [];
            $quantity = $_POST['quantity'] ?? 1;
            $notes = trim($_POST['notes'] ?? '');

            if (empty($dutyid)) {
                throw new Exception("Please select a duty.");
            }
            
            if (empty($resource_ids)) {
                throw new Exception("Please select at least one resource.");
            }

            $linked_count = 0;
            
            foreach ($resource_ids as $resource_id) {
                // Use INSERT IGNORE to avoid duplicates
                $sql = "INSERT IGNORE INTO duty_resources (dutyid, resource_id, assigned_by, quantity, notes) 
                        VALUES (?, ?, ?, ?, ?)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$dutyid, $resource_id, $force_number, $quantity, $notes]);
                
                if ($stmt->rowCount() > 0) {
                    $linked_count++;
                }
            }
            
            if ($linked_count > 0) {
                $success_msg = "Successfully linked " . $linked_count . " resource(s) to duty!";
            } else {
                $error_msg = "All selected resources were already linked to this duty.";
            }
        }
        
        $pdo->commit();
        
        // Refresh data after successful operation
        $stmt = $pdo->query("
            SELECT d.*, u.first_name, u.last_name, u.rank 
            FROM duties d
            JOIN userlogin u ON d.officerid = u.officerid 
            ORDER BY d.dutydate DESC
        ");
        $assigned_duties = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = "Error: " . $e->getMessage();
    }
}

// Fetch duty resources for display
function getDutyResources($pdo, $dutyid = null) {
    $duty_resources = [];
    
    try {
        if ($dutyid) {
            // Get resources for specific duty
            $sql = "SELECT dr.*, r.title, r.description, r.resource_image, 
                           u.first_name, u.last_name, u.rank as assigner_rank
                    FROM duty_resources dr
                    JOIN resources r ON dr.resource_id = r.id
                    JOIN userlogin u ON dr.assigned_by = u.officerid
                    WHERE dr.dutyid = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$dutyid]);
            $duty_resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Get all duty-resource relationships
            $sql = "SELECT dr.*, r.title, r.description, r.resource_image, d.task, d.dutydate, 
                           u1.first_name as officer_first, u1.last_name as officer_last,
                           u2.first_name as assigner_first, u2.last_name as assigner_last
                    FROM duty_resources dr
                    JOIN resources r ON dr.resource_id = r.id
                    JOIN duties d ON dr.dutyid = d.dutyid
                    JOIN userlogin u1 ON d.officerid = u1.officerid
                    JOIN userlogin u2 ON dr.assigned_by = u2.officerid
                    ORDER BY dr.assignment_date DESC";
            $stmt = $pdo->query($sql);
            $duty_resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Error in getDutyResources: " . $e->getMessage());
    }
    
    return $duty_resources;
}

$all_duty_resources = getDutyResources($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Duties - Police Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { 
            background-color: #f8f9fa; 
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; 
        }
        .main-content { padding: 20px; }
        .btn-manage { margin: 10px 5px; padding: 10px 20px; font-weight: 600; }
        .modal-header { background-color: #28a745; color: #fff; }
        .modal-content { border-radius: 12px; }
        .form-label { font-weight: 600; }
        .modal-dialog { max-width: 800px; }
        .section-title { 
            font-weight: 700; 
            margin-top: 15px; 
            margin-bottom: 10px; 
            color: #28a745; 
            background-color: #e9ecef;
            padding: 10px 15px;
            border-radius: 6px;
        }
        .stats-card { 
            background: white; 
            border-radius: 10px; 
            padding: 20px; 
            margin: 10px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .stats-number { 
            font-size: 2rem; 
            font-weight: bold; 
            color: #28a745; 
        }
        .officer-selection, .resource-selection {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 10px;
            background-color: #fff;
        }
        .search-input {
            margin-bottom: 10px;
        }
        .duty-card {
            transition: transform 0.2s;
            border-left: 4px solid #28a745;
        }
        .duty-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .resource-badge {
            background-color: #e9ecef;
            border: 1px solid #dee2e6;
            border-radius: 15px;
            padding: 2px 8px;
            font-size: 0.8rem;
            margin: 2px;
        }
        .resource-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 10px;
        }
        .resource-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            padding: 8px;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            transition: background-color 0.2s;
        }
        .resource-item:hover {
            background-color: #f8f9fa;
        }
        .resource-info {
            flex: 1;
        }
        .access-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .btn-group .btn {
            transition: all 0.3s;
        }
        .btn-group .btn:hover {
            transform: scale(1.05);
        }
        .alert {
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php include '../components/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-success text-white position-relative">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">
                                <i class="bi bi-person-plus me-2"></i>Duty & Resource Management
                            </h4>
                            <span class="badge bg-light text-dark access-badge">
                                <?php echo $userInfo['rank']; ?> Access
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Role-based access message -->
                        <div class="alert alert-info mb-4">
                            <i class="bi bi-shield-check me-2"></i>
                            <strong>Open Access:</strong> All logged-in users can assign duties to officers and manage resource allocations.
                        </div>

                        <?php if($success_msg): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <i class="bi bi-check-circle me-2"></i>
                                <?= htmlspecialchars($success_msg) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if($error_msg): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <?= htmlspecialchars($error_msg) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Action Buttons -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="btn-group">
                                <button class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#dutyModal">
                                    <i class="bi bi-plus-circle"></i> Assign New Duty
                                </button>
                                <button class="btn btn-info btn-lg" data-bs-toggle="modal" data-bs-target="#linkResourcesModal">
                                    <i class="bi bi-link"></i> Link Resources
                                </button>
                            </div>
                        </div>

                        <!-- Statistics Cards -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="stats-card">
                                    <h6>Total Duties</h6>
                                    <div class="stats-number"><?= count($assigned_duties) ?></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card">
                                    <h6>Today's Duties</h6>
                                    <div class="stats-number">
                                        <?= count(array_filter($assigned_duties, function($d) { 
                                            return $d['dutydate'] == date('Y-m-d'); 
                                        })) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card">
                                    <h6>Available Officers</h6>
                                    <div class="stats-number"><?= count($officers) ?></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card">
                                    <h6>Resource Links</h6>
                                    <div class="stats-number"><?= count($all_duty_resources) ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- View Toggle Buttons -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex flex-wrap gap-2">
                                            <button class="btn btn-primary" id="viewAllDutiesBtn">
                                                <i class="bi bi-list-ul"></i> View All Duties
                                            </button>
                                            <button class="btn btn-info" id="viewTodayDutiesBtn">
                                                <i class="bi bi-calendar-day"></i> View Today's Duties
                                            </button>
                                            <button class="btn btn-warning" id="viewResourceLinksBtn">
                                                <i class="bi bi-diagram-3"></i> View Resource Links
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Duties Display Area -->
                        <div id="dutiesDisplay">
                            <div class="alert alert-info">
                                <h6><i class="bi bi-info-circle me-2"></i>Welcome to Duty Management</h6>
                                <p class="mb-0">Assign duties to officers and link resources. Use the buttons above to view different aspects of duty management.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Duty Modal -->
    <div class="modal fade" id="dutyModal" tabindex="-1" aria-labelledby="dutyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="dutyModalLabel">
                        <i class="bi bi-person-plus me-2"></i>Assign New Duty
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="assign_duty" value="1">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Task *</label>
                            <input type="text" class="form-control" name="task" list="taskList" placeholder="Select from list or enter custom task" required>
                            <datalist id="taskList">
                                <option value="Patrol Duty">
                                <option value="Crime Investigation">
                                <option value="Traffic Control">
                                <option value="Emergency Response">
                                <option value="Arrest and Detention">
                                <option value="Report Writing">
                                <option value="Victim Assistance">
                                <option value="Community Engagement">
                                <option value="Evidence Collection">
                                <option value="Witness Interview">
                                <option value="Law Enforcement">
                                <option value="Dispute Mediation">
                                <option value="Vehicle Maintenance">
                                <option value="Administrative Tasks">
                                <option value="Training Session">
                                <option value="Court Testimony">
                                <option value="Special Assignment">
                                <option value="Mental Health Response">
                                <option value="Crowd Control">
                                <option value="Surveillance">
                                <option value="Others">
                            </datalist>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date *</label>
                            <input type="date" class="form-control" name="dutydate" required min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Officer Selection Mode</label>
                            <select class="form-select" id="selectMode">
                                <option value="single">Single Officer (Dropdown)</option>
                                <option value="multi">Multiple Officers (Checkboxes)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assign Officers *</label>
                            <div id="singleSelect" style="display: block;">
                                <select class="form-select" name="officerid" required>
                                    <option value="">Select Officer</option>
                                    <?php foreach($officers as $officer): ?>
                                        <option value="<?= htmlspecialchars($officer['officerid']) ?>"><?= htmlspecialchars($officer['officer_label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div id="multiSelect" style="display: none;">
                                <input type="text" id="officerSearch" class="form-control search-input" placeholder="Search officers...">
                                <div class="officer-selection" id="officerList">
                                    <?php foreach($officers as $officer): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="officerids[]" value="<?= htmlspecialchars($officer['officerid']) ?>" id="officer_<?= $officer['officerid'] ?>">
                                            <label class="form-check-label" for="officer_<?= $officer['officerid'] ?>"><?= htmlspecialchars($officer['officer_label']) ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Assign Duty</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Link Resources Modal -->
    <div class="modal fade" id="linkResourcesModal" tabindex="-1" aria-labelledby="linkResourcesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="linkResourcesModalLabel">
                        <i class="bi bi-link me-2"></i>Link Resources to Duty
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="linkResourcesForm">
                    <input type="hidden" name="link_resources" value="1">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select Duty *</label>
                            <select class="form-select" name="dutyid" required>
                                <option value="">Select Duty</option>
                                <?php foreach($assigned_duties as $duty): ?>
                                    <option value="<?= $duty['dutyid'] ?>">
                                        <?= htmlspecialchars($duty['task']) ?> - <?= $duty['dutydate'] ?> (<?= $duty['first_name'] ?> <?= $duty['last_name'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Select Resources *</label>
                            <input type="text" id="resourceSearch" class="form-control search-input" placeholder="Search resources...">
                            <div class="resource-selection" id="resourceList">
                                <?php if(empty($resources)): ?>
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        No resources available. Please add resources first.
                                    </div>
                                <?php else: ?>
                                    <?php foreach($resources as $resource): ?>
                                        <div class="resource-item">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="resource_ids[]" value="<?= $resource['id'] ?>" id="resource_<?= $resource['id'] ?>">
                                            </div>
                                            <?php if(!empty($resource['resource_image'])): ?>
                                                <img src="../<?= htmlspecialchars($resource['resource_image']) ?>" alt="<?= htmlspecialchars($resource['title']) ?>" class="resource-image">
                                            <?php else: ?>
                                                <div class="resource-image bg-light d-flex align-items-center justify-content-center">
                                                    <small class="text-muted">No Image</small>
                                                </div>
                                            <?php endif; ?>
                                            <div class="resource-info">
                                                <label class="form-check-label" for="resource_<?= $resource['id'] ?>">
                                                    <strong><?= htmlspecialchars($resource['title']) ?></strong>
                                                    <?php if($resource['description']): ?>
                                                        <br><small class="text-muted"><?= substr(htmlspecialchars($resource['description']), 0, 100) ?><?= strlen($resource['description']) > 100 ? '...' : '' ?></small>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Quantity</label>
                                    <input type="number" class="form-control" name="quantity" value="1" min="1">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" name="notes" rows="2" placeholder="Add any notes about this resource assignment..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">Link Resources</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const assignedDuties = <?php echo json_encode($assigned_duties); ?>;
        const dutyResources = <?php echo json_encode($all_duty_resources); ?>;
        const displayArea = document.getElementById('dutiesDisplay');

        // Officer selection mode toggle
        const selectMode = document.getElementById('selectMode');
        const singleSelect = document.getElementById('singleSelect');
        const multiSelect = document.getElementById('multiSelect');

        if (selectMode && singleSelect && multiSelect) {
            selectMode.addEventListener('change', function() {
                if (this.value === 'single') {
                    singleSelect.style.display = 'block';
                    multiSelect.style.display = 'none';
                } else {
                    singleSelect.style.display = 'none';
                    multiSelect.style.display = 'block';
                }
            });
        }

        // Search for officers in multi-select
        const officerSearch = document.getElementById('officerSearch');
        const officerList = document.getElementById('officerList');
        if (officerSearch && officerList) {
            officerSearch.addEventListener('input', function() {
                const filter = this.value.toLowerCase();
                Array.from(officerList.querySelectorAll('.form-check')).forEach(item => {
                    const label = item.querySelector('label').textContent.toLowerCase();
                    item.style.display = label.includes(filter) ? 'block' : 'none';
                });
            });
        }

        // Search for resources
        const resourceSearch = document.getElementById('resourceSearch');
        const resourceList = document.getElementById('resourceList');
        if (resourceSearch && resourceList) {
            resourceSearch.addEventListener('input', function() {
                const filter = this.value.toLowerCase();
                Array.from(resourceList.querySelectorAll('.resource-item')).forEach(item => {
                    const title = item.querySelector('strong').textContent.toLowerCase();
                    item.style.display = title.includes(filter) ? 'flex' : 'none';
                });
            });
        }

        // Form validation for resource linking
        const linkResourcesForm = document.getElementById('linkResourcesForm');
        if (linkResourcesForm) {
            linkResourcesForm.addEventListener('submit', function(e) {
                const dutyid = this.querySelector('[name="dutyid"]').value;
                const resource_ids = Array.from(this.querySelectorAll('[name="resource_ids[]"]:checked')).map(cb => cb.value);
                
                if (!dutyid || resource_ids.length === 0) {
                    alert('Please select both a duty and at least one resource.');
                    e.preventDefault();
                }
            });
        }

        // Render functions
        function renderAllDutiesTable() {
            if (!assignedDuties || assignedDuties.length === 0) {
                return '<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No duties assigned</div>';
            }
            
            let table = `
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="bi bi-list-ul me-2"></i>All Assigned Duties (${assignedDuties.length})</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Task</th>
                                        <th>Date</th>
                                        <th>Officer</th>
                                        <th>Rank</th>
                                        <th>Resources</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>`;
            
            assignedDuties.forEach(duty => {
                const dutyDate = new Date(duty.dutydate);
                const today = new Date();
                
                // Get resources for this duty
                const dutyResourcesForThisDuty = dutyResources.filter(dr => dr.dutyid == duty.dutyid);
                let resourcesBadge = dutyResourcesForThisDuty.length > 0 ? 
                    `<span class="badge bg-success">${dutyResourcesForThisDuty.length} resources</span>` :
                    `<span class="badge bg-secondary">No resources</span>`;
                
                let statusBadge = '';
                if (dutyDate.toDateString() === today.toDateString()) {
                    statusBadge = '<span class="badge bg-warning">Today</span>';
                } else if (dutyDate < today) {
                    statusBadge = '<span class="badge bg-secondary">Completed</span>';
                } else {
                    statusBadge = '<span class="badge bg-success">Upcoming</span>';
                }
                
                table += `
                    <tr>
                        <td>${duty.dutyid}</td>
                        <td><strong>${duty.task}</strong></td>
                        <td>${dutyDate.toLocaleDateString()}</td>
                        <td>${duty.first_name} ${duty.last_name}</td>
                        <td>${duty.rank}</td>
                        <td>${resourcesBadge}</td>
                        <td>${statusBadge}</td>
                    </tr>`;
            });
            
            table += `</tbody></table></div></div></div>`;
            return table;
        }

        function renderResourceLinks() {
            if (!dutyResources || dutyResources.length === 0) {
                return '<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No resources linked to duties</div>';
            }
            
            let table = `
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Resource-Duty Links (${dutyResources.length})</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Resource</th>
                                        <th>Duty</th>
                                        <th>Officer</th>
                                        <th>Date</th>
                                        <th>Quantity</th>
                                        <th>Assigned By</th>
                                        <th>Assignment Date</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>`;
            
            dutyResources.forEach(link => {
                let imageCell = '<div class="text-muted">No Image</div>';
                if (link.resource_image) {
                    imageCell = `<img src="../${link.resource_image}" alt="${link.title}" class="resource-image">`;
                }
                
                table += `
                    <tr>
                        <td>${imageCell}</td>
                        <td><strong>${link.title}</strong></td>
                        <td>${link.task}</td>
                        <td>${link.officer_first} ${link.officer_last}</td>
                        <td>${new Date(link.dutydate).toLocaleDateString()}</td>
                        <td>${link.quantity || 1}</td>
                        <td>${link.assigner_first} ${link.assigner_last}</td>
                        <td>${new Date(link.assignment_date).toLocaleDateString()}</td>
                        <td>${link.notes || '-'}</td>
                    </tr>`;
            });
            
            table += `</tbody></table></div></div></div>`;
            return table;
        }

        function renderTodayDuties() {
            const today = new Date().toDateString();
            const todayDuties = assignedDuties.filter(duty => 
                new Date(duty.dutydate).toDateString() === today
            );
            
            if (todayDuties.length === 0) {
                return '<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No duties scheduled for today</div>';
            }
            
            return renderDutiesGrid(todayDuties, "Today's Duties");
        }

        function renderDutiesGrid(duties, title) {
            let grid = `
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="bi bi-calendar-day me-2"></i>${title} (${duties.length})</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">`;
            
            duties.forEach(duty => {
                const dutyDate = new Date(duty.dutydate);
                const dutyResourcesForThisDuty = dutyResources.filter(dr => dr.dutyid == duty.dutyid);
                
                let resourcesHtml = '';
                if (dutyResourcesForThisDuty.length > 0) {
                    resourcesHtml = `<div class="mt-2"><strong>Resources:</strong><br>`;
                    dutyResourcesForThisDuty.forEach(resource => {
                        resourcesHtml += `<span class="resource-badge">${resource.title} (${resource.quantity})</span>`;
                    });
                    resourcesHtml += `</div>`;
                }
                
                grid += `
                    <div class="col-md-6 mb-3">
                        <div class="card duty-card h-100">
                            <div class="card-body">
                                <h6 class="card-title">${duty.task}</h6>
                                <p class="card-text mb-1">
                                    <strong>Officer:</strong> ${duty.first_name} ${duty.last_name}
                                </p>
                                <p class="card-text mb-1">
                                    <strong>Rank:</strong> ${duty.rank}
                                </p>
                                <p class="card-text mb-1">
                                    <strong>Date:</strong> ${dutyDate.toLocaleDateString()}
                                </p>
                                <p class="card-text mb-0">
                                    <strong>Assigner:</strong> ${duty.assigner_id || 'System'}
                                </p>
                                ${resourcesHtml}
                            </div>
                        </div>
                    </div>`;
            });
            
            grid += `</div></div></div>`;
            return grid;
        }

        function renderRecentDuties() {
            if (!assignedDuties || assignedDuties.length === 0) {
                return '<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No duties assigned</div>';
            }
            
            return renderDutiesGrid(assignedDuties.slice(0, 6), "Recent Duties");
        }

        // Event listeners for buttons
        document.getElementById('viewAllDutiesBtn').addEventListener('click', function() {
            displayArea.innerHTML = renderAllDutiesTable();
        });

        document.getElementById('viewTodayDutiesBtn').addEventListener('click', function() {
            displayArea.innerHTML = renderTodayDuties();
        });

        document.getElementById('viewResourceLinksBtn').addEventListener('click', function() {
            displayArea.innerHTML = renderResourceLinks();
        });

        // Initial display
        displayArea.innerHTML = renderRecentDuties();
    });
    </script>
</body>
</html>
<?php
// === VERY BOTTOM - End output buffering ===
ob_end_flush();
?>