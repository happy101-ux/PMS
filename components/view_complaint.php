<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['officerid'])) {
    header("Location: ../login.php");
    exit();
}

// Include required files
require_once '../config/database.php';
require_once '../RoleManager.php';

// Initialize RoleManager
$roleManager = new RoleManager($pdo);
$officerid = $_SESSION['officerid'];
$userInfo = $roleManager->getUserRank($officerid);

// Different queries based on rank
if ($roleManager->hasAccess($officerid, 'Sergeant')) {
    // NCOs see all complaints
    $stmt = $pdo->prepare("SELECT * FROM complaints ORDER BY date_reported DESC");
    $stmt->execute();
} else {
    // Constables see only complaints they received
    $stmt = $pdo->prepare("SELECT * FROM complaints WHERE received_by = ? ORDER BY date_reported DESC");
    $stmt->execute([$officerid]);
}

$complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ----------------------
// AJAX endpoint: update status
// ----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    if($_POST['action'] === 'update_status') {
        $complaint_id = intval($_POST['complaint_id'] ?? 0);
        $new_status = trim($_POST['new_status'] ?? '');

        $allowed = ['Waiting for Action','Assigned as Case','Under Investigation','Resolved','Closed','False Report'];
        if ($complaint_id <= 0 || !in_array($new_status, $allowed, true)) {
            echo json_encode(['success' => false, 'msg' => 'Invalid request']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Check if complaint exists
            $stmt = $pdo->prepare("SELECT id, ob_number, offence_type, statement FROM complaints WHERE id = ?");
            $stmt->execute([$complaint_id]);
            $compl = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$compl) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'msg' => 'Complaint not found']);
                exit;
            }

            // Update complaint status
            $stmt = $pdo->prepare("UPDATE complaints SET complaint_status = ? WHERE id = ?");
            $stmt->execute([$new_status, $complaint_id]);

            // Create case if status requires
            if (in_array($new_status, ['Assigned as Case', 'Under Investigation'], true)) {
                $stmt = $pdo->prepare("SELECT caseid FROM case_table WHERE complaint_id = ?");
                $stmt->execute([$complaint_id]);
                
                if ($stmt->rowCount() === 0) {
                    $casetype = $compl['offence_type'] ?: 'General';
                    $description = $compl['statement'] ?: '';
                    $status_case = 'Active';
                    
                    $stmt = $pdo->prepare("INSERT INTO case_table (officerid, casetype, status, description, complaint_id) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$officerid, $casetype, $status_case, $description, $complaint_id]);
                }
            }

            // Close case if resolved
            if (in_array($new_status, ['Resolved', 'Closed'], true)) {
                $stmt = $pdo->prepare("UPDATE case_table SET status = 'Closed' WHERE complaint_id = ?");
                $stmt->execute([$complaint_id]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'msg' => 'Status updated', 'new_status' => $new_status]);
            exit;

        } catch (Exception $ex) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'msg' => 'Exception: ' . $ex->getMessage()]);
            exit;
        }
    }

    // ----------------------
    // Assign Complaint as Case
    // ----------------------
    if($_POST['action'] === 'assign_case') {
        $complaint_id = intval($_POST['complaint_id'] ?? 0);
        if($complaint_id <= 0){
            echo json_encode(['success'=>false,'msg'=>'Invalid complaint ID']);
            exit;
        }

        // Check if case already exists
        $stmt = $pdo->prepare("SELECT caseid FROM case_table WHERE complaint_id = ?");
        $stmt->execute([$complaint_id]);
        
        if($stmt->rowCount() > 0){
            echo json_encode(['success'=>false,'msg'=>'Complaint already assigned as case']);
            exit;
        }

        // Fetch complaint details
        $stmt = $pdo->prepare("SELECT offence_type, statement FROM complaints WHERE id = ?");
        $stmt->execute([$complaint_id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        $casetype = $res['offence_type'] ?: 'General';
        $description = $res['statement'] ?: '';
        $status_case = 'Active';

        // Insert new case
        $stmt = $pdo->prepare("INSERT INTO case_table (officerid, casetype, status, description, complaint_id) VALUES (?, ?, ?, ?, ?)");
        if($stmt->execute([$officerid, $casetype, $status_case, $description, $complaint_id])){
            // Update complaint status
            $stmt = $pdo->prepare("UPDATE complaints SET complaint_status = 'Assigned as Case' WHERE id = ?");
            $stmt->execute([$complaint_id]);
            
            echo json_encode(['success'=>true]);
        } else {
            echo json_encode(['success'=>false,'msg'=>'Failed to create case']);
        }
        exit;
    }

    // ----------------------
    // DELETE Complaint (AJAX)
    // ----------------------
    if($_POST['action'] === 'delete_complaint') {
        $complaint_id = intval($_POST['complaint_id'] ?? 0);
        
        if($complaint_id <= 0){
            echo json_encode(['success'=>false,'msg'=>'Invalid complaint ID']);
            exit;
        }

        // Check if user has permission to delete (NCO and above)
        if (!$roleManager->hasAccess($officerid, 'Sergeant')) {
            echo json_encode(['success'=>false,'msg'=>'You do not have permission to delete complaints']);
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Check if complaint exists
            $stmt = $pdo->prepare("SELECT id FROM complaints WHERE id = ?");
            $stmt->execute([$complaint_id]);
            
            if($stmt->rowCount() === 0){
                echo json_encode(['success'=>false,'msg'=>'Complaint not found']);
                exit;
            }

            // Delete related records first (if any)
            // Delete from case_table
            $stmt = $pdo->prepare("DELETE FROM case_table WHERE complaint_id = ?");
            $stmt->execute([$complaint_id]);

            // Delete from investigation (if exists)
            $stmt = $pdo->prepare("DELETE FROM investigation WHERE case_id IN (SELECT ob_number FROM complaints WHERE id = ?)");
            $stmt->execute([$complaint_id]);

            // Finally delete the complaint
            $stmt = $pdo->prepare("DELETE FROM complaints WHERE id = ?");
            $stmt->execute([$complaint_id]);

            $pdo->commit();
            echo json_encode(['success'=>true,'msg'=>'Complaint deleted successfully']);
            exit;

        } catch (Exception $ex) {
            $pdo->rollBack();
            echo json_encode(['success'=>false,'msg'=>'Delete failed: ' . $ex->getMessage()]);
            exit;
        }
    }
}

// ----------------------
// Fetch complaint for modal
// ----------------------
if (isset($_GET['fetch_complaint'])) {
    $id = intval($_GET['fetch_complaint']);
    $stmt = $pdo->prepare("SELECT * FROM complaints WHERE id = ?");
    $stmt->execute([$id]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($r) {
        ?>
        <div id="printArea" class="bg-white p-4">
            <div class="report-header text-center mb-3">
                <div class="bg-primary text-white p-3 rounded">
                    <h4 class="mb-1">ZAMBIA POLICE SERVICE</h4>
                    <h6 class="mb-0">Official Complaint Report Sheet</h6>
                </div>
            </div>

            <!-- Complaint Details -->
            <div class="report-section">
                <h5 class="text-primary border-bottom pb-2">Complaint Details</h5>
                <table class="table table-sm table-bordered">
                    <tbody>
                        <tr><th>OB Number</th><td><?= htmlspecialchars($r['ob_number']) ?></td></tr>
                        <tr><th>Station</th><td><?= htmlspecialchars($r['station']) ?></td></tr>
                        <tr><th>Date Reported</th><td><?= htmlspecialchars($r['date_reported']) ?> at <?= htmlspecialchars($r['time_reported']) ?></td></tr>
                        <tr><th>Complainant</th><td><?= htmlspecialchars($r['full_name']) ?></td></tr>
                        <tr><th>ID Number</th><td><?= htmlspecialchars($r['id_number']) ?></td></tr>
                        <tr><th>Gender</th><td><?= htmlspecialchars($r['gender']) ?></td></tr>
                        <tr><th>Date of Birth</th><td><?= htmlspecialchars($r['dob']) ?></td></tr>
                        <tr><th>Occupation</th><td><?= htmlspecialchars($r['occupation']) ?></td></tr>
                        <tr><th>Residential Address</th><td><?= htmlspecialchars($r['residential_address']) ?></td></tr>
                        <tr><th>Postal Address</th><td><?= htmlspecialchars($r['postal_address']) ?></td></tr>
                        <tr><th>Phone</th><td><?= htmlspecialchars($r['phone']) ?></td></tr>
                        <tr><th>Next of Kin</th><td><?= htmlspecialchars($r['next_of_kin']) ?> (<?= htmlspecialchars($r['next_of_kin_contact']) ?>)</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Offence Details -->
            <div class="report-section">
                <h5 class="text-primary border-bottom pb-2 mt-4">Offence Details</h5>
                <table class="table table-sm table-bordered">
                    <tbody>
                        <tr><th>Offence Type</th><td><?= htmlspecialchars($r['offence_type']) ?></td></tr>
                        <tr><th>Date & Time of Occurrence</th><td><?= htmlspecialchars($r['date_occurrence']) ?> at <?= htmlspecialchars($r['time_occurrence']) ?></td></tr>
                        <tr><th>Place of Occurrence</th><td><?= htmlspecialchars($r['place_occurrence']) ?></td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Suspect Details -->
            <?php if (!empty($r['suspect_name'])): ?>
            <div class="report-section">
                <h5 class="text-primary border-bottom pb-2 mt-4">Suspect Details</h5>
                <table class="table table-sm table-bordered">
                    <tbody>
                        <tr><th>Name</th><td><?= htmlspecialchars($r['suspect_name']) ?></td></tr>
                        <tr><th>Address</th><td><?= htmlspecialchars($r['suspect_address']) ?></td></tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Statement -->
            <div class="report-section">
                <h5 class="text-primary border-bottom pb-2 mt-4">Statement</h5>
                <div class="p-3 bg-light rounded">
                    <p class="mb-0"><?= nl2br(htmlspecialchars($r['statement'])) ?></p>
                </div>
            </div>

            <?php if (!empty($r['witnesses'])): ?>
                <div class="report-section">
                    <h5 class="text-primary border-bottom pb-2 mt-4">Witnesses</h5>
                    <div class="p-3 bg-light rounded">
                        <p class="mb-0"><?= htmlspecialchars($r['witnesses']) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Officer Remarks -->
            <?php if (!empty($r['remarks'])): ?>
            <div class="report-section">
                <h5 class="text-primary border-bottom pb-2 mt-4">Officer Remarks</h5>
                <div class="p-3 bg-success bg-opacity-10 rounded">
                    <p class="mb-0 text-success fw-semibold"><?= htmlspecialchars($r['remarks']) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Status Management - Only for NCO and above -->
            <?php if ($roleManager->hasAccess($officerid, 'Sergeant')): ?>
            <div class="report-section">
                <h5 class="text-primary border-bottom pb-2 mt-4">Status Management</h5>
                <div class="d-flex gap-2 align-items-center mb-3">
                    <select class="form-select status-update" data-id="<?= $r['id'] ?>" style="max-width:250px;">
                        <?php
                        $statuses = ['Waiting for Action','Assigned as Case','Under Investigation','Resolved','Closed','False Report'];
                        foreach ($statuses as $s):
                            $sel = ($s === $r['complaint_status']) ? 'selected' : '';
                        ?>
                            <option value="<?= htmlspecialchars($s) ?>" <?= $sel ?>><?= htmlspecialchars($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-success" onclick="updateStatus(<?= $r['id'] ?>)">Update Status</button>
                </div>

                <div class="d-flex justify-content-end mt-3 gap-2">
                    <button class="btn btn-secondary print-btn" onclick="printComplaint()">
                        <i class="bi bi-printer"></i> Print
                    </button>
                    <button class="btn btn-warning" onclick="assignComplaintAsCase(<?= $r['id'] ?>)">
                        <i class="bi bi-folder-plus"></i> Assign as Case
                    </button>
                </div>
            </div>
            <?php else: ?>
            <!-- Read-only status for constables -->
            <div class="report-section">
                <h5 class="text-primary border-bottom pb-2 mt-4">Current Status</h5>
                <div class="p-3 bg-light rounded">
                    <span class="badge bg-primary fs-6"><?= htmlspecialchars($r['complaint_status']) ?></span>
                </div>
                <div class="d-flex justify-content-end mt-3">
                    <button class="btn btn-secondary print-btn" onclick="printComplaint()">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaints - Police Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { font-family:"Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background:#f8f9fa; }
        .card { border-radius:10px; }
        .table-sm td, .table-sm th { padding:.5rem; }
        
        /* Print Styles */
        @media print {
            body * { visibility: hidden; }
            #printArea, #printArea * { visibility: visible; }
            #printArea { position: absolute; left: 0; top: 0; width: 100%; }
            .btn, .status-update, .modal-header { display: none !important; }
        }
        
        .badge-status {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        .fade-out {
            opacity: 0;
            transition: opacity 0.3s ease-out;
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
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0 fw-bold">
                                <i class="bi bi-journal-text me-2"></i>Complaints Management
                            </h4>
                            <span class="badge bg-light text-dark">
                                <?php echo $userInfo['rank']; ?> View
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($roleManager->hasAccess($officerid, 'Sergeant')): ?>
                        <div class="alert alert-info mb-4">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>NCO Access:</strong> You can view all complaints and manage their status.
                        </div>
                        <?php else: ?>
                        <div class="alert alert-secondary mb-4">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Constable Access:</strong> You can only view complaints you received.
                        </div>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="complaints-table">
                                <thead class="table-dark">
                                    <tr>
                                        <th>OB Number</th>
                                        <th>Complainant</th>
                                        <th>Offence Type</th>
                                        <th>Date Reported</th>
                                        <th>Station</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="complaints-body">
                                <?php if (!empty($complaints)): ?>
                                    <?php foreach($complaints as $row): ?>
                                    <tr id="complaint-<?= $row['id'] ?>">
                                        <td>
                                            <strong><?= htmlspecialchars($row['ob_number']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($row['full_name']) ?></td>
                                        <td>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($row['offence_type']) ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($row['date_reported']) ?></td>
                                        <td><?= htmlspecialchars($row['station']) ?></td>
                                        <td>
                                            <?php
                                            $statusClass = [
                                                'Waiting for Action' => 'bg-warning',
                                                'Assigned as Case' => 'bg-info',
                                                'Under Investigation' => 'bg-primary',
                                                'Resolved' => 'bg-success',
                                                'Closed' => 'bg-secondary',
                                                'False Report' => 'bg-danger'
                                            ];
                                            $class = $statusClass[$row['complaint_status']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge <?= $class ?> badge-status">
                                                <?= htmlspecialchars($row['complaint_status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary view-btn" data-id="<?= $row['id'] ?>">
                                                <i class="bi bi-eye"></i> View
                                            </button>
                                            <?php if ($roleManager->hasAccess($officerid, 'Sergeant')): ?>
                                            <button class="btn btn-sm btn-outline-danger delete-btn" data-id="<?= $row['id'] ?>" data-ob="<?= htmlspecialchars($row['ob_number']) ?>">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="bi bi-inbox display-4 text-muted d-block mb-2"></i>
                                            <span class="text-muted">No complaints found</span>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-journal-text me-2"></i>Complaint Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="complaint-content"></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Modal & View Button
    const modalEl = document.getElementById('viewModal');
    const complaintContent = document.getElementById('complaint-content');
    const modal = new bootstrap.Modal(modalEl);

    document.querySelectorAll('.view-btn').forEach(btn => {
        btn.addEventListener('click', function(){
            const id = this.getAttribute('data-id');
            fetch('?fetch_complaint=' + id)
                .then(res => res.text())
                .then(html => {
                    complaintContent.innerHTML = html;
                    modal.show();
                }).catch(console.error);
        });
    });

    // Delete Complaint
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function(){
            const id = this.getAttribute('data-id');
            const obNumber = this.getAttribute('data-ob');
            
            if(!confirm(`Are you sure you want to delete complaint ${obNumber}? This action cannot be undone.`)) {
                return;
            }

            const form = new FormData();
            form.append('action', 'delete_complaint');
            form.append('complaint_id', id);

            // Add fade-out effect
            const row = document.getElementById('complaint-' + id);
            row.classList.add('fade-out');

            fetch('', { 
                method: 'POST', 
                body: form 
            })
            .then(r => r.json())
            .then(resp => {
                if(resp.success){
                    // Remove row after fade animation
                    setTimeout(() => {
                        row.remove();
                        
                        // Check if table is empty
                        const tbody = document.getElementById('complaints-body');
                        if (tbody.children.length === 0) {
                            tbody.innerHTML = `
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="bi bi-inbox display-4 text-muted d-block mb-2"></i>
                                        <span class="text-muted">No complaints found</span>
                                    </td>
                                </tr>
                            `;
                        }
                    }, 300);
                } else {
                    // Remove fade-out if failed
                    row.classList.remove('fade-out');
                    alert('Delete failed: ' + resp.msg);
                }
            }).catch(err => {
                console.error(err);
                row.classList.remove('fade-out');
                alert('Network error while deleting complaint');
            });
        });
    });

    // Status Update
    function updateStatus(id) {
        const select = document.querySelector('.status-update[data-id="'+id+'"]');
        const newStatus = select.value;
        const form = new FormData();
        form.append('action','update_status');
        form.append('complaint_id', id);
        form.append('new_status', newStatus);

        fetch('', { method:'POST', body:form })
            .then(r => r.json())
            .then(resp => {
                if(resp.success){
                    alert('Status updated to "' + resp.new_status + '"');
                    location.reload();
                } else {
                    alert('Update failed: ' + resp.msg);
                }
            }).catch(err => {
                console.error(err);
                alert('Network error while updating status');
            });
    }

    // Print
    function printComplaint() {
        window.print();
    }

    // Assign Complaint as Case
    function assignComplaintAsCase(id){
        if(!confirm('Assign this complaint as a case?')) return;
        const form = new FormData();
        form.append('action','assign_case');
        form.append('complaint_id', id);

        fetch('', { method:'POST', body: form })
            .then(r => r.json())
            .then(resp => {
                if(resp.success){
                    alert('Complaint assigned as case successfully');
                    bootstrap.Modal.getInstance(modalEl).hide();
                    location.reload();
                } else {
                    alert('Failed: ' + resp.msg);
                }
            }).catch(console.error);
    }
    </script>
</body>
</html>