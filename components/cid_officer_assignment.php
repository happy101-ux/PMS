<?php
require_once '../config/database.php';
require_once '../cid_security.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_cid'])) {
        $officerId = $_POST['officer_id'];
        
        try {
            $stmt = $pdo->prepare("UPDATE userlogin SET designation = 'CID' WHERE officerid = ?");
            $stmt->execute([$officerId]);
            
            $_SESSION['success'] = "Officer successfully assigned to CID!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error assigning officer to CID: " . $e->getMessage();
        }
    } elseif (isset($_POST['remove_cid'])) {
        $officerId = $_POST['officer_id'];
        
        try {
            $stmt = $pdo->prepare("UPDATE userlogin SET designation = '' WHERE officerid = ?");
            $stmt->execute([$officerId]);
            
            $_SESSION['success'] = "Officer successfully removed from CID!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error removing officer from CID: " . $e->getMessage();
        }
    }
    
    header("Location: cid_officer_assignment.php");
    exit();
}

// Get all officers
$officers = $pdo->query("
    SELECT officerid, rank, designation, first_name, last_name, gender, disabled 
    FROM userlogin 
    WHERE disabled = 0 
    ORDER BY 
        CASE 
            WHEN rank = 'ADMIN' THEN 1
            WHEN rank = 'Chief Inspector' THEN 2
            WHEN rank = 'Inspector' THEN 3
            WHEN rank = 'Sergeant' THEN 4
            WHEN rank = 'Constable' THEN 5
            ELSE 6
        END,
        last_name, first_name
")->fetchAll(PDO::FETCH_ASSOC);

// Get CID officers count
$cidCount = $pdo->query("SELECT COUNT(*) as count FROM userlogin WHERE designation = 'CID' AND disabled = 0")->fetch(PDO::FETCH_ASSOC)['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CID Officer Assignment - Police Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .cid-badge {
            background: #c0392b;
            color: white;
        }
        .officer-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        .officer-card.cid-member {
            border-left-color: #c0392b;
            background: #fff5f5;
        }
        .officer-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .stats-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <?php include '../components/navbar.php'; ?>

    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col">
                                <h4 class="card-title mb-1">
                                    <i class="bi bi-people-fill me-2"></i>
                                    CID Officer Assignment
                                </h4>
                                <p class="card-text mb-0">
                                    Manage CID personnel assignments and team composition
                                </p>
                            </div>
                            <div class="col-auto">
                                <span class="badge bg-light text-dark fs-6">
                                    <i class="bi bi-shield-check me-1"></i>
                                    <?php echo $roleManager->isAdmin() ? 'ADMIN' : 'CID'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <h5 class="text-primary"><?php echo count($officers); ?></h5>
                    <p class="text-muted mb-0">Total Officers</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h5 class="text-success"><?php echo $cidCount; ?></h5>
                    <p class="text-muted mb-0">CID Members</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h5 class="text-warning"><?php echo count($officers) - $cidCount; ?></h5>
                    <p class="text-muted mb-0">Available for Assignment</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h5 class="text-info"><?php echo round(($cidCount / count($officers)) * 100, 1); ?>%</h5>
                    <p class="text-muted mb-0">CID Coverage</p>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Officers Grid -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-person-badge me-2"></i>
                            Officer Roster
                            <small class="text-muted">- Click on officers to manage CID assignments</small>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach($officers as $officer): ?>
                            <div class="col-xl-3 col-lg-4 col-md-6 mb-3">
                                <div class="card officer-card <?php echo $officer['designation'] === 'CID' ? 'cid-member' : ''; ?>">
                                    <div class="card-body text-center">
                                        <!-- Officer Avatar -->
                                        <div class="mb-3">
                                            <div class="bg-<?php echo $officer['gender'] === 'Female' ? 'pink' : 'primary'; ?> text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                                <i class="bi bi-person-fill fs-4"></i>
                                            </div>
                                        </div>
                                        
                                        <!-- Officer Info -->
                                        <h6 class="card-title mb-1">
                                            <?php echo htmlspecialchars($officer['first_name'] . ' ' . $officer['last_name']); ?>
                                        </h6>
                                        <p class="text-muted small mb-1">
                                            ID: <?php echo htmlspecialchars($officer['officerid']); ?>
                                        </p>
                                        <p class="text-muted small mb-2">
                                            <?php echo htmlspecialchars($officer['rank']); ?>
                                        </p>
                                        
                                        <!-- Designation Badge -->
                                        <?php if ($officer['designation'] === 'CID'): ?>
                                            <span class="badge cid-badge mb-3">
                                                <i class="bi bi-shield-check me-1"></i>CID Member
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary mb-3">
                                                <i class="bi bi-person me-1"></i>General Duty
                                            </span>
                                        <?php endif; ?>
                                        
                                        <!-- Action Buttons -->
                                        <div class="mt-3">
                                            <?php if ($officer['designation'] !== 'CID'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="officer_id" value="<?php echo htmlspecialchars($officer['officerid']); ?>">
                                                    <button type="submit" name="assign_cid" class="btn btn-success btn-sm">
                                                        <i class="bi bi-plus-circle me-1"></i>Assign to CID
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="officer_id" value="<?php echo htmlspecialchars($officer['officerid']); ?>">
                                                    <button type="submit" name="remove_cid" class="btn btn-danger btn-sm" 
                                                            onclick="return confirm('Are you sure you want to remove this officer from CID?')">
                                                        <i class="bi bi-dash-circle me-1"></i>Remove from CID
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CID Team Summary -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-cid text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-diagram-3 me-2"></i>
                            CID Team Summary
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $cidOfficers = array_filter($officers, function($officer) {
                            return $officer['designation'] === 'CID';
                        });
                        
                        if (empty($cidOfficers)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-people display-4 text-muted"></i>
                                <h5 class="text-muted mt-3">No CID Officers Assigned</h5>
                                <p class="text-muted">Assign officers to CID to build your investigation team.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Officer ID</th>
                                            <th>Name</th>
                                            <th>Rank</th>
                                            <th>Gender</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($cidOfficers as $officer): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($officer['officerid']); ?></td>
                                            <td><?php echo htmlspecialchars($officer['first_name'] . ' ' . $officer['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($officer['rank']); ?></td>
                                            <td><?php echo htmlspecialchars($officer['gender']); ?></td>
                                            <td>
                                                <span class="badge cid-badge">
                                                    <i class="bi bi-shield-check me-1"></i>Active CID
                                                </span>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add confirmation for all actions
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const button = this.querySelector('button[type="submit"]');
                    if (button.name === 'remove_cid') {
                        if (!confirm('Are you sure you want to remove this officer from CID?')) {
                            e.preventDefault();
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>