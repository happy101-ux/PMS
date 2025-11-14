<?php
// investigation_reports.php
session_start();
require_once '../config/database.php'; // Your database connection file

// Check if user is logged in
if (!isset($_SESSION['officerid'])) {
    header("Location: login.php");
    exit();
}

// Get user role and ID
$officer_id = $_SESSION['officerid'];
$user_role = $_SESSION['rank'] ?? '';
$is_admin = in_array($user_role, ['ADMIN', 'Chief Inspector']);

// Handle filters
$status_filter = $_GET['status'] ?? 'all';
$investigator_filter = $_GET['investigator'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$query = "
    SELECT 
        c.*,
        ct.caseid,
        ct.status as case_status,
        ct.description as case_description,
        ct.closure_reason,
        u.first_name,
        u.last_name,
        inv.status2 as investigation_status,
        inv.assigned_date,
        inv.completed_date,
        inv.remarks as investigation_remarks,
        COUNT(ce.id) as evidence_count
    FROM complaints c
    LEFT JOIN case_table ct ON c.id = ct.complaint_id
    LEFT JOIN investigation inv ON c.ob_number = inv.case_id
    LEFT JOIN userlogin u ON inv.investigator = u.officerid
    LEFT JOIN case_evidence ce ON ct.caseid = ce.caseid
    WHERE c.complaint_status = 'Assigned as Case'
";

// Apply filters
if ($status_filter !== 'all') {
    $query .= " AND ct.status = ?";
}
if (!empty($investigator_filter)) {
    $query .= " AND inv.investigator = ?";
}
if (!empty($date_from)) {
    $query .= " AND DATE(c.date_added) >= ?";
}
if (!empty($date_to)) {
    $query .= " AND DATE(c.date_added) <= ?";
}

$query .= " GROUP BY c.id ORDER BY c.date_added DESC";

// Prepare and execute
$stmt = $conn->prepare($query);
$params = [];
$types = '';

if ($status_filter !== 'all') {
    $params[] = $status_filter;
    $types .= 's';
}
if (!empty($investigator_filter)) {
    $params[] = $investigator_filter;
    $types .= 's';
}
if (!empty($date_from)) {
    $params[] = $date_from;
    $types .= 's';
}
if (!empty($date_to)) {
    $params[] = $date_to;
    $types .= 's';
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$cases = $result->fetch_all(MYSQLI_ASSOC);

// Get investigators for filter
$investigators_query = "SELECT officerid, first_name, last_name FROM userlogin WHERE designation = 'CID'";
$investigators_result = $conn->query($investigators_query);
$investigators = $investigators_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investigation Reports & Progress</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card {
            transition: transform 0.2s;
            margin-bottom: 20px;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 0.8em;
            padding: 5px 10px;
        }
        .progress-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .evidence-badge {
            cursor: pointer;
        }
        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col">
                <h1 class="h3 mb-0">
                    <i class="fas fa-clipboard-list text-primary"></i>
                    Investigation Reports & Progress Tracking
                </h1>
                <p class="text-muted">Monitor and manage all active investigations</p>
            </div>
            <div class="col-auto">
                <button class="btn btn-outline-primary" onclick="printReport()">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?php echo count($cases); ?></h4>
                                <p class="mb-0">Total Cases</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-folder fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?php echo count(array_filter($cases, fn($c) => $c['case_status'] === 'Active')); ?></h4>
                                <p class="mb-0">Active Cases</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-tasks fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?php echo count(array_filter($cases, fn($c) => $c['case_status'] === 'Closed')); ?></h4>
                                <p class="mb-0">Closed Cases</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0"><?php echo count(array_filter($cases, fn($c) => $c['evidence_count'] > 0)); ?></h4>
                                <p class="mb-0">Cases with Evidence</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-camera fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Case Status</label>
                    <select name="status" class="form-select">
                        <option value="all">All Statuses</option>
                        <option value="Active" <?php echo $status_filter === 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Closed" <?php echo $status_filter === 'Closed' ? 'selected' : ''; ?>>Closed</option>
                        <option value="Case Dropped" <?php echo $status_filter === 'Case Dropped' ? 'selected' : ''; ?>>Case Dropped</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Investigator</label>
                    <select name="investigator" class="form-select">
                        <option value="">All Investigators</option>
                        <?php foreach ($investigators as $inv): ?>
                            <option value="<?php echo $inv['officerid']; ?>" 
                                <?php echo $investigator_filter === $inv['officerid'] ? 'selected' : ''; ?>>
                                <?php echo $inv['first_name'] . ' ' . $inv['last_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Cases List -->
        <div class="row">
            <?php foreach ($cases as $case): ?>
                <div class="col-lg-6 col-xl-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">OB: <?php echo htmlspecialchars($case['ob_number']); ?></h6>
                            <span class="badge 
                                <?php echo match($case['case_status']) {
                                    'Active' => 'bg-warning',
                                    'Closed' => 'bg-success',
                                    'Case Dropped' => 'bg-danger',
                                    default => 'bg-secondary'
                                }; ?> status-badge">
                                <?php echo $case['case_status']; ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <!-- Case Details -->
                            <div class="mb-3">
                                <strong>Complainant:</strong> <?php echo htmlspecialchars($case['full_name']); ?><br>
                                <strong>Offence:</strong> <?php echo htmlspecialchars($case['offence_type']); ?><br>
                                <strong>Station:</strong> <?php echo htmlspecialchars($case['station']); ?><br>
                                <strong>Date Reported:</strong> <?php echo date('M j, Y', strtotime($case['date_reported'])); ?>
                            </div>

                            <!-- Investigation Progress -->
                            <div class="progress-section">
                                <h6 class="border-bottom pb-2">Investigation Progress</h6>
                                <?php if ($case['investigator']): ?>
                                    <p class="mb-1">
                                        <strong>Investigator:</strong> 
                                        <?php echo htmlspecialchars($case['first_name'] . ' ' . $case['last_name']); ?>
                                    </p>
                                    <p class="mb-1">
                                        <strong>Status:</strong> 
                                        <span class="badge bg-info"><?php echo $case['investigation_status'] ?? 'Not Started'; ?></span>
                                    </p>
                                    <p class="mb-1">
                                        <strong>Assigned:</strong> 
                                        <?php echo $case['assigned_date'] ? date('M j, Y', strtotime($case['assigned_date'])) : 'Not assigned'; ?>
                                    </p>
                                <?php else: ?>
                                    <p class="text-muted mb-0">No investigator assigned</p>
                                <?php endif; ?>
                            </div>

                            <!-- Evidence Summary -->
                            <div class="d-flex justify-content-between align-items-center">
                                <span>
                                    <i class="fas fa-paperclip"></i>
                                    Evidence: 
                                    <span class="badge bg-secondary evidence-badge" 
                                          onclick="viewEvidence(<?php echo $case['caseid']; ?>)">
                                        <?php echo $case['evidence_count']; ?> files
                                    </span>
                                </span>
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick="viewCaseDetails(<?php echo $case['id']; ?>)">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent">
                            <small class="text-muted">
                                Last updated: <?php echo date('M j, Y g:i A', strtotime($case['date_added'])); ?>
                            </small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($cases)): ?>
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No cases found</h4>
                <p class="text-muted">No investigation cases match your current filters.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewCaseDetails(complaintId) {
            window.open(`case_details.php?id=${complaintId}`, '_blank');
        }

        function viewEvidence(caseId) {
            window.open(`evidence_view.php?caseid=${caseId}`, '_blank');
        }

        function printReport() {
            window.print();
        }

        // Auto-refresh every 5 minutes
        setInterval(() => {
            window.location.reload();
        }, 300000);
    </script>
</body>
</html>