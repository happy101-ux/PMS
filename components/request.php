<?php
session_start();
require_once '../config/database.php';
require_once '../RoleManager.php';

// Check if user is logged in
if (!isset($_SESSION['officerid'])) {
    header("Location: ../login.php");
    exit();
}

// Initialize RoleManager
$roleManager = new RoleManager($pdo);
$officerid = $_SESSION['officerid'];

// Initialize the user in RoleManager
$roleManager->initializeUser($officerid);
$userInfo = $roleManager->getUserRank($officerid);

// Check if user has permission to approve requests (ADMIN, Chief Inspector, Inspector, or Sergeant)
$canApprove = $roleManager->isAdmin() || 
              $roleManager->hasPermission('Chief Inspector') || 
              $roleManager->hasPermission('Inspector') || 
              $roleManager->hasPermission('Sergeant');

if (!$canApprove) {
    header("Location: ../unauthorized.php");
    exit();
}

// Database functions for resource request management
function getAllPendingResourceRequests($pdo) {
    $stmt = $pdo->prepare("
        SELECT rr.*, r.title as resource_name, 
               u.first_name, u.last_name, u.rank, u.designation
        FROM resource_requests rr
        LEFT JOIN resources r ON rr.resource_id = r.id
        LEFT JOIN userlogin u ON rr.requester_id = u.officerid
        WHERE rr.status = 'Pending'
        ORDER BY rr.request_date DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllResourceRequests($pdo) {
    $stmt = $pdo->prepare("
        SELECT rr.*, r.title as resource_name, 
               u.first_name, u.last_name, u.rank, u.designation
        FROM resource_requests rr
        LEFT JOIN resources r ON rr.resource_id = r.id
        LEFT JOIN userlogin u ON rr.requester_id = u.officerid
        ORDER BY rr.request_date DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPendingResourceRequestsCount($pdo) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM resource_requests 
        WHERE status = 'Pending'
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'];
}

function updateResourceRequestStatus($pdo, $requestId, $status) {
    $stmt = $pdo->prepare("
        UPDATE resource_requests 
        SET status = ?
        WHERE id = ?
    ");
    return $stmt->execute([$status, $requestId]);
}

// Handle approval/rejection actions
$success = $error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_request'])) {
        $requestId = $_POST['request_id'];
        if (updateResourceRequestStatus($pdo, $requestId, 'Approved')) {
            $success = "Resource request approved successfully!";
        } else {
            $error = "Failed to approve resource request.";
        }
    } elseif (isset($_POST['reject_request'])) {
        $requestId = $_POST['request_id'];
        if (updateResourceRequestStatus($pdo, $requestId, 'Rejected')) {
            $success = "Resource request rejected successfully!";
        } else {
            $error = "Failed to reject resource request.";
        }
    }
}

// Get all resource requests for management
$allRequests = getAllResourceRequests($pdo);
$pendingRequests = getAllPendingResourceRequests($pdo);
$pendingCount = getPendingResourceRequestsCount($pdo);
$approvedCount = count(array_filter($allRequests, function($r) { return $r['status'] === 'Approved'; }));
$rejectedCount = count(array_filter($allRequests, function($r) { return $r['status'] === 'Rejected'; }));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resource Request Approval - Police Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { 
            background: #f8f9fa; 
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .request-card {
            border-left: 4px solid #0d6efd;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .request-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .request-card.pending {
            border-left-color: #ffc107;
        }
        
        .request-card.approved {
            border-left-color: #198754;
        }
        
        .request-card.rejected {
            border-left-color: #dc3545;
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
        }
        
        .stats-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .requester-info {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 10px;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .header-nav {
            background: #343a40;
            padding: 1rem 0;
            margin-bottom: 2rem;
        }
        
        .nav-links {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
    </style>
</head>
<body>
    <!-- Header Navigation -->
    <div class="header-nav">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center text-white">
                    <i class="bi bi-shield-check display-6 text-primary me-3"></i>
                    <div>
                        <h4 class="mb-0">Police Management System</h4>
                        <small class="text-muted">Resource Request Approval</small>
                    </div>
                </div>
                
                <div class="nav-links">
                    <a href="../cp/" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-speedometer2 me-1"></i> Dashboard
                    </a>
                    <a href="request.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-toolbox me-1"></i> Approve Requests
                        <?php if ($pendingCount > 0): ?>
                            <span class="badge bg-warning ms-1"><?= $pendingCount ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown">
                        <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i>
                            <?= htmlspecialchars($userInfo['first_name'] ?? 'User') ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li><span class="dropdown-item-text">
                                <small><?= htmlspecialchars($userInfo['rank'] ?? 'User') ?></small>
                            </span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i> Logout
                            </a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <div class="p-4">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="text-primary mb-1">
                        <i class="bi bi-clipboard-check me-2"></i>Resource Request Approval
                    </h2>
                    <p class="text-muted mb-0">Review and approve resource requests from officers</p>
                </div>
                <div class="text-end">
                    <span class="badge bg-primary fs-6 p-2">
                        <i class="bi bi-person-check me-1"></i>
                        <?= htmlspecialchars($userInfo['rank'] ?? 'User') ?>
                    </span>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show d-flex align-items-center">
                    <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                    <div class="flex-grow-1"><?= htmlspecialchars($success) ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                    <div class="flex-grow-1"><?= htmlspecialchars($error) ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card stats-card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-clock-history fs-1"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h4 class="mb-0"><?= $pendingCount ?></h4>
                                    <p class="mb-0">Pending</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stats-card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-check-circle fs-1"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h4 class="mb-0"><?= $approvedCount ?></h4>
                                    <p class="mb-0">Approved</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stats-card bg-danger text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-x-circle fs-1"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h4 class="mb-0"><?= $rejectedCount ?></h4>
                                    <p class="mb-0">Rejected</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stats-card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-list-check fs-1"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h4 class="mb-0"><?= count($allRequests) ?></h4>
                                    <p class="mb-0">Total Requests</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Requests Section -->
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark d-flex align-items-center">
                    <i class="bi bi-clock me-2"></i>
                    <h5 class="mb-0">Pending Requests (<?= $pendingCount ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pendingRequests)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-check-circle display-4"></i>
                            <h4 class="mt-3">No Pending Requests</h4>
                            <p class="mb-0">All resource requests have been processed.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-warning">
                                    <tr>
                                        <th>Request Date</th>
                                        <th>Requester</th>
                                        <th>Resource</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingRequests as $request): ?>
                                        <tr class="request-card pending">
                                            <td>
                                                <div class="fw-bold"><?= date('M j, Y', strtotime($request['request_date'])) ?></div>
                                                <small class="text-muted"><?= date('H:i', strtotime($request['request_date'])) ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($request['rank']) ?> • <?= htmlspecialchars($request['designation']) ?></small>
                                            </td>
                                            <td>
                                                <?php if ($request['resource_name']): ?>
                                                    <strong><?= htmlspecialchars($request['resource_name']) ?></strong>
                                                <?php else: ?>
                                                    <em class="text-info"><?= htmlspecialchars($request['custom_resource'] ?? 'Custom Resource') ?></em>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="text-truncate" style="max-width: 200px;" 
                                                     title="<?= htmlspecialchars($request['reason']) ?>">
                                                    <?= htmlspecialchars($request['reason']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                                        <button type="submit" name="approve_request" class="btn btn-success btn-sm" 
                                                                onclick="return confirm('Approve this resource request?')">
                                                            <i class="bi bi-check-lg"></i> Approve
                                                        </button>
                                                    </form>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                                        <button type="submit" name="reject_request" class="btn btn-danger btn-sm" 
                                                                onclick="return confirm('Reject this resource request?')">
                                                            <i class="bi bi-x-lg"></i> Reject
                                                        </button>
                                                    </form>
                                                    <button class="btn btn-outline-primary btn-sm" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#requestDetailsModal"
                                                            data-request='<?= htmlspecialchars(json_encode($request)) ?>'>
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- All Requests History -->
            <div class="card">
                <div class="card-header bg-secondary text-white d-flex align-items-center">
                    <i class="bi bi-clock-history me-2"></i>
                    <h5 class="mb-0">All Requests History</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($allRequests)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-inbox display-1"></i>
                            <h4 class="mt-3">No Resource Requests</h4>
                            <p class="mb-0">No resource requests have been submitted yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped">
                                <thead class="table-light">
                                    <tr>
                                        <th>Request Date</th>
                                        <th>Requester</th>
                                        <th>Resource</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allRequests as $request): ?>
                                        <?php
                                        $statusClass = [
                                            'Pending' => 'bg-warning',
                                            'Approved' => 'bg-success',
                                            'Rejected' => 'bg-danger'
                                        ];
                                        $rowClass = [
                                            'Pending' => 'pending',
                                            'Approved' => 'approved',
                                            'Rejected' => 'rejected'
                                        ];
                                        ?>
                                        <tr class="request-card <?= $rowClass[$request['status']] ?? '' ?>">
                                            <td>
                                                <div class="fw-bold"><?= date('M j, Y', strtotime($request['request_date'])) ?></div>
                                                <small class="text-muted"><?= date('H:i', strtotime($request['request_date'])) ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?= htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($request['rank']) ?></small>
                                            </td>
                                            <td>
                                                <?php if ($request['resource_name']): ?>
                                                    <strong><?= htmlspecialchars($request['resource_name']) ?></strong>
                                                <?php else: ?>
                                                    <em class="text-info"><?= htmlspecialchars($request['custom_resource'] ?? 'Custom Resource') ?></em>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="text-truncate" style="max-width: 200px;" 
                                                     title="<?= htmlspecialchars($request['reason']) ?>">
                                                    <?= htmlspecialchars($request['reason']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge <?= $statusClass[$request['status']] ?? 'bg-secondary' ?> status-badge">
                                                    <i class="bi <?= 
                                                        $request['status'] === 'Pending' ? 'bi-clock' : 
                                                        ($request['status'] === 'Approved' ? 'bi-check-circle' : 'bi-x-circle')
                                                    ?> me-1"></i>
                                                    <?= $request['status'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#requestDetailsModal"
                                                        data-request='<?= htmlspecialchars(json_encode($request)) ?>'
                                                        title="View Details">
                                                    <i class="bi bi-eye"></i> Details
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Request Details Modal -->
    <div class="modal fade" id="requestDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-info-circle me-2"></i>Request Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="requestDetailsContent">
                    <!-- Content will be loaded via JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Request details modal
        const requestDetailsModal = document.getElementById('requestDetailsModal');
        requestDetailsModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const requestData = JSON.parse(button.getAttribute('data-request'));
            
            const statusClass = {
                'Pending': 'bg-warning',
                'Approved': 'bg-success',
                'Rejected': 'bg-danger'
            };
            
            const content = `
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <tr>
                                <th class="bg-light" style="width: 40%">Request ID</th>
                                <td>#${requestData.id}</td>
                            </tr>
                            <tr>
                                <th class="bg-light">Request Date</th>
                                <td>${new Date(requestData.request_date).toLocaleString()}</td>
                            </tr>
                            <tr>
                                <th class="bg-light">Status</th>
                                <td>
                                    <span class="badge ${statusClass[requestData.status] || 'bg-secondary'} status-badge">
                                        ${requestData.status}
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <tr>
                                <th class="bg-light" style="width: 40%">Requester</th>
                                <td>${requestData.first_name} ${requestData.last_name}</td>
                            </tr>
                            <tr>
                                <th class="bg-light">Rank & Designation</th>
                                <td>${requestData.rank} • ${requestData.designation}</td>
                            </tr>
                            <tr>
                                <th class="bg-light">Resource Type</th>
                                <td>${requestData.resource_name ? 'Pre-defined' : 'Custom'}</td>
                            </tr>
                            <tr>
                                <th class="bg-light">Resource</th>
                                <td>${requestData.resource_name || requestData.custom_resource || 'N/A'}</td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6 class="border-bottom pb-2">Reason for Request:</h6>
                        <div class="p-3 bg-light rounded">
                            ${requestData.reason}
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('requestDetailsContent').innerHTML = content;
        });
    </script>
</body>
</html>