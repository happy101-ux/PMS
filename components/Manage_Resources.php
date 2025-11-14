<?php
// === VERY TOP - Start output buffering ===
ob_start();
session_start();
require_once '../config/database.php';
include '../components/navbar.php';


// Redirect if not logged in
if (!isset($_SESSION['officerid'])) {
    header("Location: ../login.php");
    exit();
}

// Auto-filled officer details
$officer_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$force_number = $_SESSION['officerid'];

// Initialize variables
$success_msg = $error_msg = "";
$resources = $all_resources = $pending_requests = [];

// Fetch existing data for display
try {
    // Fetch resources
    $resource_result = $conn->query("SELECT * FROM resources ORDER BY upload_date DESC");
    if ($resource_result) {
        $resources = $resource_result->fetch_all(MYSQLI_ASSOC);
        $all_resources = $resources;
    }
    
    // Fetch pending requests (if table exists)
    $result = $conn->query("SHOW TABLES LIKE 'resource_requests'");
    if ($result->num_rows > 0) {
        $req_result = $conn->query("
            SELECT rr.id, ul.first_name, ul.last_name, r.title, rr.request_date, rr.reason 
            FROM resource_requests rr 
            JOIN userlogin ul ON rr.requester_id = ul.officerid 
            JOIN resources r ON rr.resource_id = r.id 
            WHERE rr.status = 'Pending'
        ");
        if ($req_result) {
            $pending_requests = $req_result->fetch_all(MYSQLI_ASSOC);
        }
    }
} catch (Exception $e) {
    error_log("Error fetching data: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $conn->begin_transaction();
    try {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $uploaded_by = $force_number;
        
        // Handle file upload
        $resource_image = null;
        if (isset($_FILES['resource_image']) && $_FILES['resource_image']['error'] == 0) {
            $target_dir = "../uploads/resources/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES["resource_image"]["name"], PATHINFO_EXTENSION);
            $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
            $target_file = $target_dir . $unique_filename;
            
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
            if (in_array(strtolower($file_extension), $allowed_types)) {
                if ($_FILES['resource_image']['size'] <= 5 * 1024 * 1024) {
                    if (move_uploaded_file($_FILES["resource_image"]["tmp_name"], $target_file)) {
                        $resource_image = $target_file;
                    }
                }
            }
        }
        
        $sql = "INSERT INTO resources (title, description, resource_image, uploaded_by) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $title, $description, $resource_image, $uploaded_by);
        $stmt->execute();
        $resource_id = $conn->insert_id;
        
        $conn->commit();
        $success_msg = "Resource added successfully! ID: " . $resource_id;
        
        // Refresh data after successful submission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_msg = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Resources - Police Management System</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            background-color: #f8f9fa; 
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; 
        }
        .main-content { margin-left: 250px; padding: 20px; }
        .btn-manage { margin: 10px 5px; padding: 10px 20px; font-weight: 600; }
        .modal-header { background-color: #0d6efd; color: #fff; }
        .modal-content { border-radius: 12px; }
        .form-label { font-weight: 600; }
        .modal-dialog { max-width: 800px; }
        .section-title { 
            font-weight: 700; 
            margin-top: 15px; 
            margin-bottom: 10px; 
            color: #0d6efd; 
            background-color: #e9ecef;
            padding: 10px 15px;
            border-radius: 6px;
        }
        table { margin-top: 10px; }
        .stats-card { 
            background: white; 
            border-radius: 10px; 
            padding: 20px; 
            margin: 10px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stats-number { 
            font-size: 2rem; 
            font-weight: bold; 
            color: #0d6efd; 
        }
        .resource-card {
            transition: transform 0.2s;
        }
        .resource-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>
    <div class="main-content p-4" id="mainContent">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="text-primary">📁 Resource Management</h3>
                <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#resourceModal">
                    <i class="bi bi-plus-circle"></i> Add New Resource
                </button>
            </div>

            <?php if(isset($success_msg)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= $success_msg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($error_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= $error_msg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stats-card">
                        <h6>Total Resources</h6>
                        <div class="stats-number"><?= count($resources) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <h6>With Images/Files</h6>
                        <div class="stats-number">
                            <?= count(array_filter($resources, function($r) { return !empty($r['resource_image']); })) ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <h6>Pending Requests</h6>
                        <div class="stats-number"><?= count($pending_requests) ?></div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-2">
                                <button class="btn btn-info" id="viewAllResourcesBtn">
                                    <i class="bi bi-list-ul"></i> View All Resources
                                </button>
                                <button class="btn btn-warning" id="viewPendingRequestsBtn">
                                    <i class="bi bi-clock-history"></i> View Pending Requests
                                </button>
                                <button class="btn btn-success" id="manageRequestsBtn">
                                    <i class="bi bi-check-circle"></i> Manage Requests
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resources Display Area -->
            <div id="resourcesDisplay">
                <!-- Content will be loaded here by JavaScript -->
                <div class="alert alert-info">
                    <h6>Welcome to Resource Management</h6>
                    <p class="mb-0">Use the buttons above to view and manage resources. Click "Add New Resource" to create a new resource.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Resource Modal -->
    <div class="modal fade" id="resourceModal" tabindex="-1" aria-labelledby="resourceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="resourceModalLabel">Add New Resource</h5>
                    <button type="button" class="btn-close bg-light" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" class="form-control" name="title" placeholder="Enter resource title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description *</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Enter description" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Resource Image/File</label>
                            <input type="file" class="form-control" name="resource_image" accept="image/*,.pdf,.doc,.docx">
                            <div class="form-text">Supported formats: JPG, PNG, GIF, PDF, DOC, DOCX (Max 5MB)</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Resource</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const resources = <?php echo json_encode($resources); ?>;
        const allResources = <?php echo json_encode($all_resources); ?>;
        const pendingRequests = <?php echo json_encode($pending_requests); ?>;
        const displayArea = document.getElementById('resourcesDisplay');

        // Render functions
        function renderAllResourcesTable() {
            if (!allResources || allResources.length === 0) {
                return '<div class="alert alert-info">No resources found</div>';
            }
            
            let table = `
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0">All Resources (${allResources.length})</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Description</th>
                                        <th>Uploaded By</th>
                                        <th>Upload Date</th>
                                        <th>File</th>
                                    </tr>
                                </thead>
                                <tbody>`;
            
            allResources.forEach(r => {
                let fileCell = 'No File';
                if (r.resource_image) {
                    const fileExt = r.resource_image.split('.').pop().toLowerCase();
                    if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExt)) {
                        fileCell = `<img src="${r.resource_image}" alt="${r.title}" style="width:50px; height:50px; object-fit:cover;" class="rounded">`;
                    } else {
                        fileCell = `<span class="badge bg-secondary">${fileExt.toUpperCase()}</span>`;
                    }
                }
                
                table += `
                    <tr>
                        <td>${r.id}</td>
                        <td><strong>${r.title}</strong></td>
                        <td>${r.description}</td>
                        <td>${r.uploaded_by}</td>
                        <td>${new Date(r.upload_date).toLocaleDateString()}</td>
                        <td>${fileCell}</td>
                    </tr>`;
            });
            
            table += `</tbody></table></div></div></div>`;
            return table;
        }

        function renderPendingRequestsTable() {
            if (!pendingRequests || pendingRequests.length === 0) {
                return '<div class="alert alert-info">No pending requests</div>';
            }
            
            let table = `
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0">Pending Resource Requests (${pendingRequests.length})</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Requester</th>
                                        <th>Resource</th>
                                        <th>Date</th>
                                        <th>Reason</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>`;
            
            pendingRequests.forEach(req => {
                table += `
                    <tr>
                        <td>${req.id}</td>
                        <td>${req.first_name} ${req.last_name}</td>
                        <td><strong>${req.title}</strong></td>
                        <td>${new Date(req.request_date).toLocaleDateString()}</td>
                        <td>${req.reason}</td>
                        <td>
                            <button class="btn btn-success btn-sm" onclick="handleRequest(${req.id}, 'approve')">Approve</button>
                            <button class="btn btn-danger btn-sm" onclick="handleRequest(${req.id}, 'reject')">Reject</button>
                        </td>
                    </tr>`;
            });
            
            table += `</tbody></table></div></div></div>`;
            return table;
        }

        function renderResourcesGrid() {
            if (!resources || resources.length === 0) {
                return '<div class="alert alert-info">No resources available</div>';
            }
            
            let grid = `<div class="row">`;
            resources.slice(0, 6).forEach(resource => {
                let fileBadge = '';
                if (resource.resource_image) {
                    const fileExt = resource.resource_image.split('.').pop().toLowerCase();
                    fileBadge = `<span class="badge bg-info">${fileExt.toUpperCase()}</span>`;
                }
                
                grid += `
                    <div class="col-md-4 mb-3">
                        <div class="card resource-card h-100">
                            <div class="card-body">
                                <h6 class="card-title">${resource.title}</h6>
                                <p class="card-text text-muted small">${resource.description.substring(0, 100)}${resource.description.length > 100 ? '...' : ''}</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">By: ${resource.uploaded_by}</small>
                                    ${fileBadge}
                                </div>
                                <small class="text-muted d-block mt-2">${new Date(resource.upload_date).toLocaleDateString()}</small>
                            </div>
                        </div>
                    </div>`;
            });
            grid += `</div>`;
            return grid;
        }

        // Event listeners for buttons
        document.getElementById('viewAllResourcesBtn').addEventListener('click', function() {
            displayArea.innerHTML = renderAllResourcesTable();
        });

        document.getElementById('viewPendingRequestsBtn').addEventListener('click', function() {
            displayArea.innerHTML = renderPendingRequestsTable();
        });

        document.getElementById('manageRequestsBtn').addEventListener('click', function() {
            displayArea.innerHTML = renderPendingRequestsTable();
        });

        // Initial display
        displayArea.innerHTML = renderResourcesGrid();
    });

    // Global function for handling requests
    function handleRequest(requestId, action) {
        if (confirm(`Are you sure you want to ${action} this request?`)) {
            // Implement AJAX call here to handle the request
            fetch('handle_resource_request.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    request_id: requestId,
                    action: action
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Request ${action}d successfully!`);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error processing request');
            });
        }
    }
    </script>
</body>
</html>
<?php
// === VERY BOTTOM - End output buffering ===
ob_end_flush();
?>