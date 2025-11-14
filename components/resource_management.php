<?php
ob_start();
session_start();
require_once '../config/database.php';
include '../components/navbar.php';

// Redirect if not logged in
if (!isset($_SESSION['officerid'])) {
    header("Location: ../login.php");
    exit();
}

$officer_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$force_number = $_SESSION['officerid'];

// Initialize variables
$success_msg = $error_msg = "";
$resources = [];

// Fetch existing resources
try {
    $stmt = $pdo->query("SELECT r.*, u.first_name, u.last_name 
                        FROM resources r 
                        LEFT JOIN userlogin u ON r.uploaded_by = u.officerid 
                        ORDER BY r.upload_date DESC");
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching resources: " . $e->getMessage());
    $error_msg = "Error loading resources: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $uploaded_by = $force_number;
        
        // Validate required fields
        if (empty($title) || empty($description)) {
            throw new Exception("Title and description are required");
        }
        
        // Verify officer exists using PDO
        $officer_check = $pdo->prepare("SELECT officerid FROM userlogin WHERE officerid = ?");
        $officer_check->execute([$uploaded_by]);
        
        if ($officer_check->rowCount() === 0) {
            throw new Exception("Invalid officer ID");
        }
        
        // Handle file upload
        $resource_image = null;
        if (isset($_FILES['resource_image']) && $_FILES['resource_image']['error'] == 0) {
            $target_dir = "../uploads/resources/";
            if (!is_dir($target_dir)) {
                if (!mkdir($target_dir, 0755, true)) {
                    throw new Exception("Failed to create upload directory");
                }
            }
            
            $file_name = $_FILES["resource_image"]["name"];
            $file_tmp = $_FILES["resource_image"]["tmp_name"];
            $file_size = $_FILES["resource_image"]["size"];
            $file_error = $_FILES["resource_image"]["error"];
            
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
            $target_file = $target_dir . $unique_filename;
            
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
            $max_file_size = 5 * 1024 * 1024; // 5MB
            
            // Validate file type
            if (!in_array($file_extension, $allowed_types)) {
                throw new Exception("File type not allowed. Allowed types: " . implode(', ', $allowed_types));
            }
            
            // Validate file size
            if ($file_size > $max_file_size) {
                throw new Exception("File too large. Maximum size: 5MB");
            }
            
            // Additional security checks
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file_tmp);
            finfo_close($finfo);
            
            $allowed_mime_types = [
                'image/jpeg',
                'image/png',
                'image/gif',
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];
            
            if (!in_array($mime_type, $allowed_mime_types)) {
                throw new Exception("Invalid file type detected");
            }
            
            // Move uploaded file
            if (move_uploaded_file($file_tmp, $target_file)) {
                $resource_image = $target_file;
            } else {
                throw new Exception("Failed to upload file");
            }
        } elseif ($_FILES['resource_image']['error'] != 4) { // Error code 4 = No file uploaded
            throw new Exception("File upload error: " . $_FILES['resource_image']['error']);
        }
        
        // Insert resource using PDO
        $sql = "INSERT INTO resources (title, description, resource_image, uploaded_by) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$title, $description, $resource_image, $uploaded_by])) {
            $success_msg = "Resource added successfully!";
            
            // Refresh data
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            throw new Exception("Failed to insert resource");
        }
        
    } catch (Exception $e) {
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { 
            background-color: #f8f9fa; 
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; 
        }
        .main-content { 
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
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
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .resource-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .btn-primary {
            background: #007bff;
            border: none;
            padding: 10px 20px;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .file-preview {
            max-width: 100%;
            max-height: 200px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="text-primary">
                <i class="bi bi-folder me-2"></i>Resource Management
            </h3>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#resourceModal">
                <i class="bi bi-plus-circle me-2"></i>Add Resource
            </button>
        </div>

        <!-- Alerts -->
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

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <h6>Total Resources</h6>
                    <div class="stats-number"><?= count($resources) ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <h6>With Files</h6>
                    <div class="stats-number">
                        <?= count(array_filter($resources, function($r) { return !empty($r['resource_image']); })) ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <h6>Recently Added</h6>
                    <div class="stats-number">
                        <?= count(array_filter($resources, function($r) { 
                            return strtotime($r['upload_date']) > strtotime('-7 days'); 
                        })) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resources List -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="bi bi-list-ul me-2"></i>All Resources
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($resources)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-inbox display-4"></i>
                        <p class="mt-2">No resources found</p>
                        <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#resourceModal">
                            Add Your First Resource
                        </button>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($resources as $resource): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="resource-card">
                                    <div class="d-flex align-items-start mb-2">
                                        <?php if (!empty($resource['resource_image'])): ?>
                                            <?php 
                                            $fileExt = pathinfo($resource['resource_image'], PATHINFO_EXTENSION);
                                            if (in_array(strtolower($fileExt), ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                                <img src="<?= htmlspecialchars($resource['resource_image']) ?>" 
                                                     alt="<?= htmlspecialchars($resource['title']) ?>" 
                                                     style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px; margin-right: 10px;">
                                            <?php else: ?>
                                                <div class="bg-light d-flex align-items-center justify-content-center" 
                                                     style="width: 50px; height: 50px; border-radius: 4px; margin-right: 10px;">
                                                    <i class="bi bi-file-earmark-text text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="bg-light d-flex align-items-center justify-content-center" 
                                                 style="width: 50px; height: 50px; border-radius: 4px; margin-right: 10px;">
                                                <i class="bi bi-file-earmark text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <h6 class="mb-1"><?= htmlspecialchars($resource['title']) ?></h6>
                                            <small class="text-muted">
                                                By: <?= htmlspecialchars($resource['first_name'] . ' ' . $resource['last_name']) ?> (<?= htmlspecialchars($resource['uploaded_by']) ?>)
                                            </small>
                                        </div>
                                    </div>
                                    <p class="text-muted small mb-2">
                                        <?= htmlspecialchars(substr($resource['description'], 0, 100)) ?>
                                        <?php if (strlen($resource['description']) > 100): ?>...<?php endif; ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <?= date('M j, Y', strtotime($resource['upload_date'])) ?>
                                        </small>
                                        <?php if (!empty($resource['resource_image'])): ?>
                                            <span class="badge bg-info">
                                                <?= strtoupper(pathinfo($resource['resource_image'], PATHINFO_EXTENSION)) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Resource Modal -->
    <div class="modal fade" id="resourceModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Add New Resource</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="resourceForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" class="form-control" name="title" placeholder="Enter resource title" required maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description *</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Enter description" required maxlength="500"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Resource File (Optional)</label>
                            <input type="file" class="form-control" name="resource_image" id="resource_image" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                            <div class="form-text">JPG, PNG, GIF, PDF, DOC, DOCX (Max 5MB)</div>
                            <div id="filePreview" class="mt-2"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">Add Resource</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // File preview and validation
        document.getElementById('resource_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('filePreview');
            preview.innerHTML = '';
            
            if (file) {
                // File size validation
                const maxSize = 5 * 1024 * 1024; // 5MB
                if (file.size > maxSize) {
                    alert('File size exceeds 5MB limit');
                    e.target.value = '';
                    return;
                }
                
                // File type validation
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 
                                    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                if (!allowedTypes.includes(file.type)) {
                    alert('File type not allowed');
                    e.target.value = '';
                    return;
                }
                
                // Preview for images
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'file-preview img-thumbnail';
                        preview.appendChild(img);
                    }
                    reader.readAsDataURL(file);
                } else {
                    // Show file info for non-images
                    const fileInfo = document.createElement('div');
                    fileInfo.className = 'alert alert-info';
                    fileInfo.innerHTML = `<i class="bi bi-file-earmark"></i> Selected file: ${file.name} (${(file.size/1024/1024).toFixed(2)} MB)`;
                    preview.appendChild(fileInfo);
                }
            }
        });

        // Form submission handling
        document.getElementById('resourceForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Adding...';
        });
    </script>
</body>
</html>
<?php
ob_end_flush();
?>