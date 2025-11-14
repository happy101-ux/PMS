<?php
// resource_requests.php
ob_start();
session_start();

// Redirect if not logged in
if (!isset($_SESSION['officerid'])) {
    header("Location: ../login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'pms');
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

include '../components/navbar.php';

$officerid = $_SESSION['officerid'];
$officer_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$rank = $_SESSION['rank'];

// Initialize variables
$success_msg = $error_msg = "";
$available_resources = [];
$my_requests = [];
$pending_requests_count = 0;

// Create tables if they don't exist
$conn->query("
    CREATE TABLE IF NOT EXISTS resources (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        resource_image VARCHAR(500),
        uploaded_by VARCHAR(20),
        upload_date DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$conn->query("
    CREATE TABLE IF NOT EXISTS resource_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        resource_id INT NOT NULL,
        requester_id VARCHAR(20) NOT NULL,
        request_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        reason TEXT NOT NULL,
        status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
        reviewed_by VARCHAR(20),
        review_date DATETIME,
        review_notes TEXT,
        quantity INT DEFAULT 1,
        urgency ENUM('Low','Medium','High','Critical') DEFAULT 'Medium',
        needed_date DATE,
        FOREIGN KEY (resource_id) REFERENCES resources(id),
        FOREIGN KEY (requester_id) REFERENCES userlogin(officerid)
    )
");

// Add sample resources if table is empty
$result = $conn->query("SELECT COUNT(*) as count FROM resources");
$row = $result->fetch_assoc();
if ($row['count'] == 0) {
    $sample_resources = [
        ["Patrol Vehicle", "Standard police patrol vehicle", NULL, "SYSTEM"],
        ["Body Camera", "Body-worn camera for evidence", NULL, "SYSTEM"],
        ["Radio", "Police communication radio", NULL, "SYSTEM"],
        ["First Aid Kit", "Emergency medical supplies", NULL, "SYSTEM"],
        ["Protective Vest", "Bulletproof vest", NULL, "SYSTEM"]
    ];
    
    $stmt = $conn->prepare("INSERT INTO resources (title, description, resource_image, uploaded_by) VALUES (?, ?, ?, ?)");
    foreach ($sample_resources as $resource) {
        $stmt->bind_param("ssss", $resource[0], $resource[1], $resource[2], $resource[3]);
        $stmt->execute();
    }
    $stmt->close();
}

// Fetch data
$available_resources = $conn->query("
    SELECT * FROM resources ORDER BY title ASC
")->fetch_all(MYSQLI_ASSOC);

$my_requests = $conn->query("
    SELECT rr.*, r.title as resource_title, r.description as resource_description
    FROM resource_requests rr
    JOIN resources r ON rr.resource_id = r.id
    WHERE rr.requester_id = '$officerid'
    ORDER BY rr.request_date DESC
")->fetch_all(MYSQLI_ASSOC);

$pending_result = $conn->query("
    SELECT COUNT(*) as count FROM resource_requests 
    WHERE requester_id = '$officerid' AND status = 'Pending'
");
$pending_requests_count = $pending_result->fetch_assoc()['count'];

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['submit_request'])) {
        $resource_id = $_POST['resource_id'];
        $reason = trim($_POST['reason']);
        $quantity = intval($_POST['quantity'] ?? 1);
        $urgency = $_POST['urgency'] ?? 'Medium';
        $needed_date = $_POST['needed_date'] ?: null;

        if (empty($resource_id) || empty($reason)) {
            $error_msg = "Please select a resource and provide a reason.";
        } else {
            // Check for existing pending request
            $check = $conn->prepare("SELECT id FROM resource_requests WHERE requester_id = ? AND resource_id = ? AND status = 'Pending'");
            $check->bind_param("si", $officerid, $resource_id);
            $check->execute();
            
            if ($check->get_result()->fetch_assoc()) {
                $error_msg = "You already have a pending request for this resource.";
            } else {$stmt = $conn->prepare("INSERT INTO resource_requests (resource_id, requester_id, reason, quantity, urgency, needed_date, custom_resource) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ississs", $resource_id, $officerid, $reason, $quantity, $urgency, $needed_date, $custom_resource);
                if ($stmt->execute()) {
                    $success_msg = "Request submitted successfully!";
                    header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                    exit();
                } else {
                    $error_msg = "Error submitting request.";
                }
            }
        }
    }

    if (isset($_POST['cancel_request'])) {
        $request_id = $_POST['request_id'];
        $stmt = $conn->prepare("DELETE FROM resource_requests WHERE id = ? AND requester_id = ? AND status = 'Pending'");
        $stmt->bind_param("is", $request_id, $officerid);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $success_msg = "Request cancelled.";
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
            exit();
        } else {
            $error_msg = "Unable to cancel request.";
        }
    }
}

if (isset($_GET['success'])) {
    $success_msg = "Operation completed successfully!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resource Requests - Police Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-container {
            background: white;
            border-radius: 10px;
            margin: 20px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stats-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #007bff;
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
        }
        
        .resource-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border: 1px solid #dee2e6;
        }
        
        .resource-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .request-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-left: 4px solid #ffc107;
        }
        
        .request-card.approved {
            border-left-color: #28a745;
        }
        
        .request-card.rejected {
            border-left-color: #dc3545;
        }
        
        .nav-tabs .nav-link.active {
            background: #007bff;
            color: white;
            border: none;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: #495057;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="container-fluid">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <h3 class="text-primary">
                        <i class="bi bi-tools me-2"></i>Resource Requests
                    </h3>
                    <p class="text-muted">Request equipment and resources for your duties</p>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success_msg); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_msg); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo count($available_resources); ?></div>
                        <p class="mb-0">Available Resources</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo $pending_requests_count; ?></div>
                        <p class="mb-0">Pending Requests</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="stats-number">
                            <?php echo count(array_filter($my_requests, function($r) { return $r['status'] === 'Approved'; })); ?>
                        </div>
                        <p class="mb-0">Approved Requests</p>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4" id="resourceTabs">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#available">
                        <i class="bi bi-grid me-1"></i>Available Resources
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#requests">
                        <i class="bi bi-list-check me-1"></i>My Requests
                        <?php if ($pending_requests_count > 0): ?>
                            <span class="badge bg-warning ms-1"><?php echo $pending_requests_count; ?></span>
                        <?php endif; ?>
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Available Resources -->
                <div class="tab-pane fade show active" id="available">
                    <?php if (empty($available_resources)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-inbox display-4"></i>
                            <p class="mt-2">No resources available</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($available_resources as $resource): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="resource-card">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($resource['title']); ?></h6>
                                                <p class="text-muted small mb-2">
                                                    <?php echo htmlspecialchars(substr($resource['description'], 0, 100)); ?>
                                                    <?php if (strlen($resource['description']) > 100): ?>...<?php endif; ?>
                                                </p>
                                                <small class="text-muted">
                                                    Added: <?php echo date('M j, Y', strtotime($resource['upload_date'])); ?>
                                                </small>
                                            </div>
                                            <button class="btn btn-primary btn-sm" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#requestModal"
                                                    data-resource-id="<?php echo $resource['id']; ?>"
                                                    data-resource-title="<?php echo htmlspecialchars($resource['title']); ?>">
                                                <i class="bi bi-send me-1"></i>Request
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- My Requests -->
                <div class="tab-pane fade" id="requests">
                    <?php if (empty($my_requests)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-file-earmark-text display-4"></i>
                            <p class="mt-2">No requests yet</p>
                            <button class="btn btn-primary mt-2" onclick="switchToAvailableTab()">
                                Browse Resources
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($my_requests as $request): ?>
                            <div class="request-card <?php echo strtolower($request['status']); ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center mb-2">
                                            <h6 class="fw-bold mb-0 me-3"><?php echo htmlspecialchars($request['resource_title']); ?></h6>
                                            <span class="badge 
                                                <?php echo $request['status'] === 'Approved' ? 'bg-success' : 
                                                       ($request['status'] === 'Rejected' ? 'bg-danger' : 'bg-warning'); ?>">
                                                <?php echo htmlspecialchars($request['status']); ?>
                                            </span>
                                        </div>
                                        <p class="text-muted mb-2 small">
                                            <strong>Reason:</strong> <?php echo htmlspecialchars($request['reason']); ?>
                                        </p>
                                        <div class="text-muted small">
                                            <span class="me-3">
                                                <i class="bi bi-calendar me-1"></i>
                                                <?php echo date('M j, Y', strtotime($request['request_date'])); ?>
                                            </span>
                                           <?php if (isset($request['quantity']) && $request['quantity'] > 1): ?>
    <span class="me-3">
        <i class="bi bi-hash me-1"></i>
        Qty: <?php echo htmlspecialchars($request['quantity']); ?>
    </span>
<?php endif; ?>

                                           
                                            <?php if ($request['status'] !== 'Pending' && $request['review_date']): ?>
                                                <span>
                                                    <i class="bi bi-check-circle me-1"></i>
                                                    Reviewed: <?php echo date('M j, Y', strtotime($request['review_date'])); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ($request['status'] === 'Pending'): ?>
                                        <form method="POST" class="ms-2">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <button type="submit" name="cancel_request" class="btn btn-outline-danger btn-sm"
                                                    onclick="return confirm('Cancel this request?')">
                                                <i class="bi bi-x"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Request Modal -->
    <div class="modal fade" id="requestModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Resource</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="submit_request" value="1">
                    <input type="hidden" name="resource_id" id="modal_resource_id">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Resource</label>
                            <div class="form-control bg-light" id="modal_resource_title"></div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Quantity</label>
                                    <input type="number" class="form-control" name="quantity" value="1" min="1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Urgency</label>
                                    <select class="form-select" name="urgency">
                                        <option value="Low">Low</option>
                                        <option value="Medium" selected>Medium</option>
                                        <option value="High">High</option>
                                        <option value="Critical">Critical</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Needed By (Optional)</label>
                            <input type="date" class="form-control" name="needed_date" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason *</label>
                            <textarea class="form-control" name="reason" rows="3" placeholder="Explain why you need this resource..." required></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize modal
        const requestModal = document.getElementById('requestModal');
        requestModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('modal_resource_id').value = button.getAttribute('data-resource-id');
            document.getElementById('modal_resource_title').textContent = button.getAttribute('data-resource-title');
        });

        function switchToAvailableTab() {
            const tab = new bootstrap.Tab(document.querySelector('[data-bs-target="#available"]'));
            tab.show();
        }
    </script>
</body>
</html>
<?php
ob_end_flush();
?>