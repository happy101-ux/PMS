<?php
session_start();

// Show errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect if not logged in
if (!isset($_SESSION['officerid'])) {
    header("Location: ../login.php");
    exit();
}

// Define database constants if not already defined
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'pms');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');

// Initialize database connection
try {
    // Create mysqli connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    // Create PDO connection for RoleManager
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Include RoleManager after establishing connection
require_once '../RoleManager.php';

$officerid = $_SESSION['officerid'];
$rank = $_SESSION['rank'];
$designation = $_SESSION['designation'];

// Initialize RoleManager
$roleManager = new RoleManager($pdo);
$userInfo = $roleManager->getUserRank($officerid);

// Check if user has permission (OIC or Admin)
if ($designation !== 'ADMIN' && $rank !== 'Inspector') {
    echo "<h5>Access Denied: Only OIC or Admin can access this dashboard.</h5>";
    exit();
}

// Helper function using mysqli
function safeCountQuery($conn, $query) {
    if (!$conn) {
        error_log("Database connection is null in safeCountQuery");
        return 0;
    }
    
    $result = $conn->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'] ?? 0;
    }
    error_log("Query failed: " . $conn->error);
    return 0;
}

// Function to fetch dashboard data
function fetchDashboardData($conn) {
    $data = [];
    
    // Dashboard statistics
    $data['pending'] = safeCountQuery($conn, "
        SELECT COUNT(*) AS total 
        FROM complaints c
        LEFT JOIN investigation i ON c.ob_number = i.case_id
        WHERE i.case_id IS NULL
    ");
    $data['assigned'] = safeCountQuery($conn, "SELECT COUNT(*) AS total FROM investigation");
    $data['resolved'] = safeCountQuery($conn, "SELECT COUNT(*) AS total FROM complaints WHERE complaint_status='Resolved'");

    // Fetch data with evidence
    $data['cases'] = $conn->query("
        SELECT c.ob_number, c.full_name, c.offence_type, c.date_reported, c.place_occurrence, c.statement, c.witnesses,
               GROUP_CONCAT(e.file_path SEPARATOR '|') AS evidence_files
        FROM complaints c
        LEFT JOIN investigation i ON c.ob_number = i.case_id
        LEFT JOIN case_table ct ON c.id = ct.complaint_id
        LEFT JOIN case_evidence e ON ct.caseid = e.caseid
        WHERE i.case_id IS NULL
        GROUP BY c.ob_number
        ORDER BY c.date_reported DESC
    ");

    $data['assigned_cases'] = $conn->query("
        SELECT i.case_id, i.investigator, ct.status, u.rank, u.first_name, u.last_name,
               GROUP_CONCAT(e.file_path SEPARATOR '|') AS evidence_files
        FROM investigation i
        JOIN userlogin u ON i.investigator = u.officerid
        JOIN complaints c ON i.case_id = c.ob_number
        JOIN case_table ct ON c.id = ct.complaint_id
        LEFT JOIN case_evidence e ON ct.caseid = e.caseid
        GROUP BY i.case_id
        ORDER BY i.assigned_date DESC
    ");

    $data['cid_officers'] = $conn->query("SELECT officerid, rank, first_name, last_name FROM userlogin WHERE designation='CID' AND disabled=0");
    
    return $data;
}

// Handle AJAX requests for data refresh
if (isset($_GET['refresh_data'])) {
    $dashboardData = fetchDashboardData($conn);
    
    // Prepare data for JSON response
    $response = [
        'stats' => [
            'pending' => $dashboardData['pending'],
            'assigned' => $dashboardData['assigned'],
            'resolved' => $dashboardData['resolved']
        ],
        'pending_cases' => [],
        'assigned_cases' => []
    ];
    
    // Format pending cases
    if ($dashboardData['cases'] && $dashboardData['cases']->num_rows > 0) {
        while ($row = $dashboardData['cases']->fetch_assoc()) {
            $response['pending_cases'][] = $row;
        }
    }
    
    // Format assigned cases
    if ($dashboardData['assigned_cases'] && $dashboardData['assigned_cases']->num_rows > 0) {
        while ($a = $dashboardData['assigned_cases']->fetch_assoc()) {
            $response['assigned_cases'][] = $a;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Initial data fetch
$dashboardData = fetchDashboardData($conn);
$pending = $dashboardData['pending'];
$assigned = $dashboardData['assigned'];
$resolved = $dashboardData['resolved'];
$cases = $dashboardData['cases'];
$assigned_cases = $dashboardData['assigned_cases'];
$cid_officers = $dashboardData['cid_officers'];

// Handle case assignment/update
$success = $error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_case'])) {
        $case_id = $_POST['modal_case_id'];
        $cid_officer = $_POST['cid_officer'];
        if (!empty($case_id) && !empty($cid_officer)) {
            $stmt = $conn->prepare("INSERT INTO investigation (case_id, investigator, status2, assigned_date) VALUES (?, ?, 'Under Investigation', NOW())");
            $stmt->bind_param("ss", $case_id, $cid_officer);
            if ($stmt->execute()) {
                $update_stmt = $conn->prepare("UPDATE complaints SET complaint_status='Assigned as Case' WHERE ob_number=?");
                $update_stmt->bind_param("s", $case_id);
                $update_stmt->execute();

                // Create entry in case_table
                $comp_stmt = $conn->prepare("SELECT id, offence_type, statement FROM complaints WHERE ob_number = ?");
                $comp_stmt->bind_param("s", $case_id);
                $comp_stmt->execute();
                $comp_result = $comp_stmt->get_result();
                $comp_row = $comp_result->fetch_assoc();
                $complaint_id = $comp_row['id'];
                $casetype = $comp_row['offence_type'];
                $description = $comp_row['statement'];
                $comp_stmt->close();

                $case_insert = $conn->prepare("INSERT INTO case_table (complaint_id, officerid, casetype, status, description, closure_reason) VALUES (?, ?, ?, 'Active', ?, NULL)");
                $case_insert->bind_param("isss", $complaint_id, $cid_officer, $casetype, $description);
                $case_insert->execute();
                $case_insert->close();

                $success = "Case $case_id assigned successfully.";
            } else {
                $error = "Error assigning case: " . $conn->error;
            }
            $stmt->close();
        }
    }

    if (isset($_POST['update_case'])) {
        $case_id = $_POST['modal_case_id'];
        $new_status = $_POST['status'];
        $comp_stmt = $conn->prepare("SELECT id FROM complaints WHERE ob_number = ?");
        $comp_stmt->bind_param("s", $case_id);
        $comp_stmt->execute();
        $comp_result = $comp_stmt->get_result();
        $complaint_id = $comp_result->fetch_assoc()['id'];
        $comp_stmt->close();

        $stmt = $conn->prepare("UPDATE case_table SET status=? WHERE complaint_id=?");
        $stmt->bind_param("si", $new_status, $complaint_id);
        if ($stmt->execute()) {
            if ($new_status === 'Closed') {
                $update_complaint = $conn->prepare("UPDATE complaints SET complaint_status='Resolved' WHERE ob_number=?");
                $update_complaint->bind_param("s", $case_id);
                $update_complaint->execute();
                $update_complaint->close();

                $update_inv = $conn->prepare("UPDATE investigation SET status2='Completed', completed_date=NOW() WHERE case_id=?");
                $update_inv->bind_param("s", $case_id);
                $update_inv->execute();
                $update_inv->close();
            } else {
                $update_inv = $conn->prepare("UPDATE investigation SET status2='Under Investigation' WHERE case_id=?");
                $update_inv->bind_param("s", $case_id);
                $update_inv->execute();
                $update_inv->close();
            }
            $success = "Case $case_id updated successfully.";
        } else {
            $error = "Error updating case: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch case details (AJAX)
if (isset($_GET['fetch_case']) && !empty($_GET['fetch_case'])) {
    $case_id = $_GET['fetch_case'];
    $stmt = $conn->prepare("
        SELECT 
            c.ob_number,
            c.full_name,
            c.offence_type,
            c.date_reported,
            c.place_occurrence,
            c.statement,
            c.witnesses,
            GROUP_CONCAT(e.file_path SEPARATOR '|') AS evidence_files,
            ct.status
        FROM complaints c
        LEFT JOIN case_table ct ON c.id = ct.complaint_id
        LEFT JOIN case_evidence e ON ct.caseid = e.caseid
        WHERE c.ob_number = ?
        GROUP BY c.ob_number
    ");
    $stmt->bind_param("s", $case_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    if (!empty($data['witnesses']) && is_string($data['witnesses'])) {
        $decoded = json_decode($data['witnesses'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $data['witnesses'] = implode(", ", $decoded);
        }
    }

    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OIC Dashboard - Case Management</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
/* Fix modal text color - change white text to black */
.modal-content {
    color: #000000; /* Force black text throughout modal */
}

.modal-body th.bg-light {
    color: #000000 !important; /* Ensure table headers are black */
}

.modal-body td {
    color: #000000 !important; /* Ensure table data is black */
}

.modal-body .statement-box {
    color: #333333 !important; /* Dark gray for statement text */
}

.modal-body .evidence-box {
    color: #000000 !important;
}

/* Keep modal header text white since it's on dark background */
.modal-header {
    color: #ffffff !important;
}

.modal-header .modal-title {
    color: #ffffff !important;
}
        /* Print styling */
        @media print {
            body * { 
                visibility: hidden; 
            }
            #printArea, #printArea * { 
                visibility: visible; 
            }
            #printArea { 
                position: absolute; 
                left: 0; 
                top: 0; 
                width: 100%; 
                padding: 20px; 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            }
            .print-header { 
                text-align: center; 
                margin-bottom: 15px; 
                border-bottom: 2px solid #000; 
                padding-bottom: 5px; 
            }
            .print-header img { 
                width: 80px; 
                margin-bottom: 5px; 
            }
            .print-section { 
                margin-top: 15px; 
            }
            .print-section h5 { 
                border-bottom: 1px solid #ccc; 
                padding-bottom: 3px; 
            }
            .print-footer { 
                margin-top: 25px; 
                text-align: right; 
                font-size: 14px; 
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark px-3">
        <div class="container-fluid">
            <!-- Brand -->
            <a class="navbar-brand fw-bold" href="../admin/dashboard.php">
                <i class="bi bi-shield-check"></i> Police Management System
            </a>

            <!-- Mobile Toggle -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Navigation Links -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../admin/dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="../cp">
                            <i class="bi bi-list-task"></i> My Duties
                        </a>
                    </li>
                    <!-- Admin Tools -->
                    <?php if ($roleManager->hasAccess($officerid, 'Chief Inspector')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-tools"></i> Admin Tools
                        </a>
                        <ul class="dropdown-menu">
                            <?php if ($roleManager->hasAccess($officerid, 'Chief Inspector')): ?>
                            <li><a class="dropdown-item" href="reports.php">Generate Reports</a></li>
                            <li><a class="dropdown-item" href="resource_management.php">Resource Management</a></li>
                            <?php endif; ?>
                            <?php if ($roleManager->hasAccess($officerid, 'ADMIN')): ?>
                            <li><a class="dropdown-item" href="user_management.php">User Management</a></li>
                            <li><a class="dropdown-item" href="system_settings.php">System Settings</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>

                <!-- Right Side -->
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <button class="btn btn-refresh me-2" id="refreshDashboard">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                    </li>

                    <!-- User Menu -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> 
                            <?= htmlspecialchars($userInfo['first_name'] . ' ' . $userInfo['last_name']) ?>
                            <span class="badge bg-light text-dark ms-1"><?= htmlspecialchars($userInfo['rank']) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="profile.php">
                                <i class="bi bi-person"></i> Profile
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="main-content p-4" id="mainContent">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <h3 class="text-center mb-3 text-primary">
                        <i class="bi bi-clipboard-data me-2"></i>OIC Dashboard – Case Management
                    </h3>
                </div>
            </div>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4 text-center" id="statsSection">
                <div class="col-md-4 mb-3">
                    <div class="card stats-card bg-warning text-white p-3">
                        <div class="stats-number" id="pendingCount"><?= $pending ?></div>
                        <p class="mb-0">Pending Cases</p>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card stats-card bg-primary text-white p-3">
                        <div class="stats-number" id="assignedCount"><?= $assigned ?></div>
                        <p class="mb-0">Assigned Cases</p>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card stats-card bg-success text-white p-3">
                        <div class="stats-number" id="resolvedCount"><?= $resolved ?></div>
                        <p class="mb-0">Solved Cases</p>
                    </div>
                </div>
            </div>

            <!-- Pending Cases Section -->
            <div id="pendingCasesSection">
                <h5 class="section-title">
                    <i class="bi bi-clock me-2"></i>Pending Cases (Awaiting Assignment)
                </h5>
                <div class="card table-card p-3 mb-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>OB Number</th>
                                    <th>Complainant</th>
                                    <th>Offence</th>
                                    <th>Date Reported</th>
                                    <th>Location</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="pendingCasesTable">
                                <?php if ($cases && $cases->num_rows > 0): ?>
                                    <?php while ($row = $cases->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?= htmlspecialchars($row['ob_number']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($row['full_name']) ?></td>
                                            <td>
                                                <span class="badge bg-warning text-dark">
                                                    <?= htmlspecialchars($row['offence_type']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($row['date_reported']) ?></td>
                                            <td><?= htmlspecialchars($row['place_occurrence']) ?></td>
                                            <td>
                                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#assignCaseModal" data-case="<?= htmlspecialchars($row['ob_number']) ?>">
                                                    <i class="bi bi-eye me-1"></i> View & Assign
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            All cases have been assigned or resolved.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Assigned Cases Section -->
            <div id="assignedCasesSection">
                <h5 class="section-title">
                    <i class="bi bi-search me-2"></i>Assigned Cases (CID Department)
                </h5>
                <div class="card table-card p-3 mb-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Case ID</th>
                                    <th>Assigned To</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="assignedCasesTable">
                                <?php if ($assigned_cases && $assigned_cases->num_rows > 0): ?>
                                    <?php while ($a = $assigned_cases->fetch_assoc()): ?>
                                        <?php
                                        $fullname = $a['rank']." ".$a['first_name']." ".$a['last_name'];
                                        $badge_class = ($a['status'] == 'Closed') ? 'bg-success' : (($a['status'] == 'Active') ? 'bg-primary' : 'bg-warning');
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-dark">
                                                    <?= htmlspecialchars($a['case_id']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($fullname) ?></td>
                                            <td>
                                                <span class="badge <?= $badge_class ?>">
                                                    <?= htmlspecialchars($a['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#viewCaseModal" data-case="<?= htmlspecialchars($a['case_id']) ?>" data-status="<?= htmlspecialchars($a['status']) ?>">
                                                    <i class="bi bi-eye me-1"></i> View Details
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            No assigned cases yet.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- View & Assign Modal -->
            <div class="modal fade" id="assignCaseModal" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <form method="POST" class="modal-content" id="assignCaseForm">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="bi bi-person-plus me-2"></i>View & Assign Case
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="modal_case_id" id="assign_case_id">
                            <table class="table table-bordered">
                                <tbody>
                                    <tr>
                                        <th class="bg-light">OB Number</th>
                                        <td id="assign_ob_number" class="fw-bold text-primary"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Complainant</th>
                                        <td id="assign_full_name"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Offence</th>
                                        <td id="assign_offence_type"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Date Reported</th>
                                        <td id="assign_date_reported"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Place</th>
                                        <td id="assign_place_occurrence"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Statement</th>
                                        <td>
                                            <div id="assign_statement" class="statement-box"></div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Witnesses</th>
                                        <td>
                                            <div id="assign_witnesses" class="statement-box"></div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Evidence</th>
                                        <td>
                                            <div id="assign_evidence" class="evidence-box">No evidence uploaded.</div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Assign to CID Officer</th>
                                        <td>
                                            <select name="cid_officer" class="form-select" required>
                                                <option value="">Select CID Officer</option>
                                                <?php 
                                                if ($cid_officers && $cid_officers->num_rows > 0) {
                                                    mysqli_data_seek($cid_officers, 0); 
                                                    while ($cid = $cid_officers->fetch_assoc()): 
                                                ?>
                                                    <option value="<?= $cid['officerid'] ?>">
                                                        <?= htmlspecialchars($cid['rank'].' '.$cid['first_name'].' '.$cid['last_name']) ?>
                                                    </option>
                                                <?php 
                                                    endwhile; 
                                                }
                                                ?>
                                            </select>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" id="printBtn">
                                <i class="bi bi-printer me-1"></i> Print Report
                            </button>
                            <button type="submit" name="assign_case" class="btn btn-success">
                                <i class="bi bi-check-circle me-1"></i> Assign Case
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                Close
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- View & Judge Modal -->
            <div class="modal fade" id="viewCaseModal" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="bi bi-clipboard-data me-2"></i>Case Details & Judgment
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <table class="table table-bordered">
                                <tbody>
                                    <tr>
                                        <th class="bg-light">OB Number</th>
                                        <td id="modal_ob_number" class="fw-bold text-primary"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Complainant</th>
                                        <td id="modal_full_name"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Offence</th>
                                        <td id="modal_offence_type"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Date Reported</th>
                                        <td id="modal_date_reported"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Place</th>
                                        <td id="modal_place_occurrence"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Statement</th>
                                        <td>
                                            <div id="modal_statement" class="statement-box"></div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Witnesses</th>
                                        <td>
                                            <div id="modal_witnesses" class="statement-box"></div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Evidence</th>
                                        <td>
                                            <div id="modal_evidence" class="evidence-box">No evidence uploaded.</div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Status</th>
                                        <td id="modal_status"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" id="printBtn2">
                                <i class="bi bi-printer me-1"></i> Print Report
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hidden Printable Area -->
            <div id="printArea" style="display:none;">
                <div class="print-header">
                    <img src="../assets/images/police_logo.png" alt="Police Logo">
                    <h4>Republic of Zambia<br>Zambia Police Service</h4>
                    <h5>Case Summary Report</h5>
                </div>
                <div id="printContent"></div>
                <div class="print-footer">
                    <p>Printed by: <strong><?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></strong></p>
                    <p>Date: <strong><span id="printDate"></span></strong></p>
                    <p>Signature: ____________________________</p>
                    <p>Verified By: ____________________________</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper (Required for dropdowns) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let currentCaseData = null;

        // Function to show loading overlay
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }

        // Function to hide loading overlay
        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }

        // Function to refresh dashboard data
        async function refreshDashboard() {
            showLoading();
            
            try {
                const response = await fetch('?refresh_data=1');
                const data = await response.json();
                
                // Update statistics
                document.getElementById('pendingCount').textContent = data.stats.pending;
                document.getElementById('assignedCount').textContent = data.stats.assigned;
                document.getElementById('resolvedCount').textContent = data.stats.resolved;
                
                // Update pending cases table
                const pendingTable = document.getElementById('pendingCasesTable');
                if (data.pending_cases.length > 0) {
                    pendingTable.innerHTML = data.pending_cases.map(row => `
                        <tr>
                            <td>
                                <span class="badge bg-primary">${row.ob_number}</span>
                            </td>
                            <td>${row.full_name}</td>
                            <td>
                                <span class="badge bg-warning text-dark">${row.offence_type}</span>
                            </td>
                            <td>${row.date_reported}</td>
                            <td>${row.place_occurrence}</td>
                            <td>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#assignCaseModal" data-case="${row.ob_number}">
                                    <i class="bi bi-eye me-1"></i> View & Assign
                                </button>
                            </td>
                        </tr>
                    `).join('');
                } else {
                    pendingTable.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                All cases have been assigned or resolved.
                            </td>
                        </tr>
                    `;
                }
                
                // Update assigned cases table
                const assignedTable = document.getElementById('assignedCasesTable');
                if (data.assigned_cases.length > 0) {
                    assignedTable.innerHTML = data.assigned_cases.map(a => {
                        const fullname = `${a.rank} ${a.first_name} ${a.last_name}`;
                        const badge_class = a.status === 'Closed' ? 'bg-success' : (a.status === 'Active' ? 'bg-primary' : 'bg-warning');
                        
                        return `
                            <tr>
                                <td>
                                    <span class="badge bg-dark">${a.case_id}</span>
                                </td>
                                <td>${fullname}</td>
                                <td>
                                    <span class="badge ${badge_class}">${a.status}</span>
                                </td>
                                <td>
                                    <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#viewCaseModal" data-case="${a.case_id}" data-status="${a.status}">
                                        <i class="bi bi-eye me-1"></i> View Details
                                    </button>
                                </td>
                            </tr>
                        `;
                    }).join('');
                } else {
                    assignedTable.innerHTML = `
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">
                                No assigned cases yet.
                            </td>
                        </tr>
                    `;
                }
                
            } catch (error) {
                console.error('Error refreshing dashboard:', error);
                alert('Error refreshing dashboard data');
            } finally {
                hideLoading();
            }
        }

        // Function to render evidence previews
        function renderEvidence(evidence) {
            if (!evidence) return 'No evidence uploaded.';
            return evidence.split('|').map(f => `<img src="${f}" alt="${f.split('/').pop()}" onerror="this.src='../assets/images/placeholder.png';">`).join('');
        }

        // View & Assign Modal
        document.getElementById('assignCaseModal').addEventListener('show.bs.modal', e => {
            const caseId = e.relatedTarget.getAttribute('data-case');
            document.getElementById('assign_case_id').value = caseId;
            fetch('?fetch_case=' + caseId)
                .then(r => r.json())
                .then(data => {
                    currentCaseData = data;
                    document.getElementById('assign_ob_number').innerText = data.ob_number;
                    document.getElementById('assign_full_name').innerText = data.full_name;
                    document.getElementById('assign_offence_type').innerText = data.offence_type;
                    document.getElementById('assign_date_reported').innerText = data.date_reported;
                    document.getElementById('assign_place_occurrence').innerText = data.place_occurrence;
                    document.getElementById('assign_statement').innerText = data.statement || 'No statement available.';
                    document.getElementById('assign_witnesses').innerText = data.witnesses || 'No witnesses available.';
                    document.getElementById('assign_evidence').innerHTML = renderEvidence(data.evidence_files);
                });
        });

        // View & Judge Modal
        document.getElementById('viewCaseModal').addEventListener('show.bs.modal', e => {
            const caseId = e.relatedTarget.getAttribute('data-case');
            fetch('?fetch_case=' + caseId)
                .then(r => r.json())
                .then(data => {
                    currentCaseData = data;
                    document.getElementById('modal_ob_number').innerText = data.ob_number;
                    document.getElementById('modal_full_name').innerText = data.full_name;
                    document.getElementById('modal_offence_type').innerText = data.offence_type;
                    document.getElementById('modal_date_reported').innerText = data.date_reported;
                    document.getElementById('modal_place_occurrence').innerText = data.place_occurrence;
                    document.getElementById('modal_statement').innerText = data.statement || 'No statement available.';
                    document.getElementById('modal_witnesses').innerText = data.witnesses || 'No witnesses available.';
                    document.getElementById('modal_evidence').innerHTML = renderEvidence(data.evidence_files);
                    document.getElementById('modal_status').innerText = data.status || 'N/A';
                });
        });

        // Print Button Functionality
        function generatePrintContent(data) {
            const evidenceHTML = data.evidence_files ? data.evidence_files.split('|').map(f => `<li>${f.split('/').pop()}</li>`).join('') : '<li>No evidence uploaded.</li>';
            const html = `
            <div class="print-section">
                <h5>Case Information</h5>
                <p><strong>OB Number:</strong> ${data.ob_number}</p>
                <p><strong>Complainant:</strong> ${data.full_name}</p>
                <p><strong>Offence:</strong> ${data.offence_type}</p>
                <p><strong>Date Reported:</strong> ${data.date_reported}</p>
                <p><strong>Location:</strong> ${data.place_occurrence}</p>
            </div>
            <div class="print-section">
                <h5>Statement</h5>
                <p>${data.statement || 'No statement available.'}</p>
            </div>
            <div class="print-section">
                <h5>Witnesses</h5>
                <p>${data.witnesses || 'No witnesses listed.'}</p>
            </div>
            <div class="print-section">
                <h5>Evidence</h5>
                <ul>${evidenceHTML}</ul>
            </div>`;
            document.getElementById('printContent').innerHTML = html;
            document.getElementById('printDate').innerText = new Date().toLocaleString();
            document.getElementById('printArea').style.display = 'block';
            window.print();
            document.getElementById('printArea').style.display = 'none';
        }

        // Event Listeners
        document.getElementById('printBtn').onclick = () => currentCaseData && generatePrintContent(currentCaseData);
        document.getElementById('printBtn2').onclick = () => currentCaseData && generatePrintContent(currentCaseData);
        document.getElementById('refreshDashboard').addEventListener('click', refreshDashboard);

        // Add shadow when scrolled
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 10) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Auto-refresh after form submissions
        document.getElementById('assignCaseForm').addEventListener('submit', function() {
            setTimeout(refreshDashboard, 1000);
        });
    </script>
</body>
</html>