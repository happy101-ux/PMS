on the  Pending Cases (Awaiting Assignment) also add a view and assign model form, just remove , Assign To CID on the main form 
 <?php
session_start();
require_once '../config/database.php';

// Show errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect if not logged in
if (!isset($_SESSION['officerid'])) {
    header("Location: ../login.php");
    exit();
}

$officerid = $_SESSION['officerid'];
$rank = $_SESSION['rank'];
$designation = $_SESSION['designation'];

if ($designation !== 'ADMIN' && $rank !== 'Chief Inspector') {
    echo "<h5>Access Denied: Only OIC or Admin can access this dashboard.</h5>";
    exit();
}

/* =======================
   HELPER FUNCTION
======================= */
function safeCountQuery($conn, $query) {
    $result = $conn->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        return $row['total'] ?? 0;
    }
    return 0;
}

/* =======================
   DASHBOARD STATISTICS
======================= */
$pending = safeCountQuery($conn, "
    SELECT COUNT(*) AS total 
    FROM complaints c
    LEFT JOIN investigation i ON c.ob_number = i.case_id
    WHERE i.case_id IS NULL
");
$assigned = safeCountQuery($conn, "SELECT COUNT(*) AS total FROM investigation");
$resolved = safeCountQuery($conn, "SELECT COUNT(*) AS total FROM complaints WHERE complaint_status='Resolved'");

/* =======================
   FETCH CASES & OFFICERS
======================= */
$cases = $conn->query("
    SELECT c.ob_number, c.full_name, c.offence_type, c.date_reported, c.place_occurrence
    FROM complaints c
    LEFT JOIN investigation i ON c.ob_number = i.case_id
    WHERE i.case_id IS NULL
    ORDER BY c.date_reported DESC
");

$assigned_cases = $conn->query("
    SELECT i.case_id, i.investigator, i.status2, u.rank, u.first_name, u.last_name
    FROM investigation i
    JOIN userlogin u ON i.investigator = u.officerid
    ORDER BY i.assigned_date DESC
");

$cid_officers = $conn->query("SELECT officerid, rank, first_name, last_name FROM userlogin WHERE designation='CID' AND disabled=0");

/* =======================
   HANDLE CASE ASSIGNMENT
======================= */
$success = $error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_case'])) {
        $case_id = $_POST['case_id'];
        $cid_officer = $_POST['cid_officer'];
        if (!empty($case_id) && !empty($cid_officer)) {
            $stmt = $conn->prepare("INSERT INTO investigation (case_id, investigator, status2, assigned_date) VALUES (?, ?, 'Under Investigation', NOW())");
            $stmt->bind_param("ss", $case_id, $cid_officer);
            if ($stmt->execute()) {
                $update_stmt = $conn->prepare("UPDATE complaints SET complaint_status='Assigned as Case' WHERE ob_number=?");
                $update_stmt->bind_param("s", $case_id);
                $update_stmt->execute();
                $update_stmt->close();
                $success = "Case $case_id assigned successfully to officer $cid_officer.";
            } else {
                $error = "Error assigning case: " . $conn->error;
            }
            $stmt->close();
        }
    }

    if (isset($_POST['update_case'])) {
        $case_id = $_POST['modal_case_id'];
        $new_status = $_POST['status2'];
        $stmt = $conn->prepare("UPDATE investigation SET status2=? WHERE case_id=?");
        $stmt->bind_param("ss", $new_status, $case_id);
        if ($stmt->execute()) {
            if ($new_status === 'Completed') {
                $update_complaint = $conn->prepare("UPDATE complaints SET complaint_status='Resolved' WHERE ob_number=?");
                $update_complaint->bind_param("s", $case_id);
                $update_complaint->execute();
                $update_complaint->close();
            }
            $success = "Case $case_id updated successfully.";
        } else {
            $error = "Error updating case: " . $conn->error;
        }
        $stmt->close();
    }
}

/* =======================
   FETCH CASE DETAILS (AJAX)
======================= */
if (isset($_GET['fetch_case']) && !empty($_GET['fetch_case'])) {
    $case_id = $_GET['fetch_case'];
    $stmt = $conn->prepare("
        SELECT c.ob_number, c.full_name, c.offence_type, c.date_reported, c.place_occurrence,
               CONCAT(u.rank,' ',u.first_name,' ',u.last_name) AS investigator_name
        FROM complaints c
        LEFT JOIN investigation i ON c.ob_number = i.case_id
        LEFT JOIN userlogin u ON i.investigator = u.officerid
        WHERE c.ob_number = ?
    ");
    $stmt->bind_param("s", $case_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
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
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f4f6f9; font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; }
.stats-card { border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.08); transition: 0.3s; }
.stats-card:hover { transform: scale(1.02); }
.table th { background: #0d6efd; color: white; }
.section-title { margin-top: 40px; color: #0d6efd; font-weight: 600; }
</style>
</head>
<body>
<div class="container mt-4">
<h3 class="mb-4 text-center">OIC Dashboard – Case Assignment & Progress Tracking</h3>

<?php if (!empty($success)): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Dashboard Summary Cards -->
<div class="row mb-4 text-center">
    <div class="col-md-4"><div class="card stats-card bg-warning text-white p-3"><h4><?= $pending ?></h4><p>Pending Cases</p></div></div>
    <div class="col-md-4"><div class="card stats-card bg-primary text-white p-3"><h4><?= $assigned ?></h4><p>Assigned Cases</p></div></div>
    <div class="col-md-4"><div class="card stats-card bg-success text-white p-3"><h4><?= $resolved ?></h4><p>Solved Cases</p></div></div>
</div>

<!-- Pending Cases -->
<h5 class="section-title">🕒 Pending Cases (Awaiting Assignment)</h5>
<div class="card p-3 mb-4 table-responsive">
<table class="table table-bordered text-center align-middle">
<thead><tr><th>OB Number</th><th>Complainant</th><th>Offence</th><th>Date Reported</th><th>Location</th><th>Assign To CID</th></tr></thead>
<tbody>
<?php if ($cases && $cases->num_rows > 0): ?>
<?php while ($row = $cases->fetch_assoc()): ?>
<tr>
<td><?= htmlspecialchars($row['ob_number']) ?></td>
<td><?= htmlspecialchars($row['full_name']) ?></td>
<td><?= htmlspecialchars($row['offence_type']) ?></td>
<td><?= htmlspecialchars($row['date_reported']) ?></td>
<td><?= htmlspecialchars($row['place_occurrence']) ?></td>
<td>
<form method="POST" class="d-flex">
<input type="hidden" name="case_id" value="<?= htmlspecialchars($row['ob_number']) ?>">
<select name="cid_officer" class="form-select form-select-sm" required>
<option value="">Select CID</option>
<?php mysqli_data_seek($cid_officers, 0); while ($cid = $cid_officers->fetch_assoc()): ?>
<option value="<?= $cid['officerid'] ?>"><?= htmlspecialchars($cid['rank'].' '.$cid['first_name'].' '.$cid['last_name']) ?></option>
<?php endwhile; ?>
</select>
<button type="submit" name="assign_case" class="btn btn-sm btn-primary ms-2">Assign</button>
</form>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="6">All cases have been assigned or resolved.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<!-- Assigned Cases -->
<h5 class="section-title">🔍 Assigned Cases (CID Department)</h5>
<div class="card p-3 mb-4 table-responsive">
<table class="table table-striped table-bordered align-middle text-center">
<thead><tr><th>Case ID</th><th>Assigned To</th><th>Status</th><th>Action</th></tr></thead>
<tbody>
<?php if ($assigned_cases && $assigned_cases->num_rows > 0): ?>
<?php while ($a = $assigned_cases->fetch_assoc()):
$fullname = $a['rank']." ".$a['first_name']." ".$a['last_name'];
$status = $a['status2'];
?>
<tr>
<td><?= htmlspecialchars($a['case_id']) ?></td>
<td><?= htmlspecialchars($fullname) ?></td>
<td><span class="badge <?= ($status=='Completed')?'bg-success':'bg-primary' ?>"><?= htmlspecialchars($status) ?></span></td>
<td>
<button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewCaseModal" 
data-case="<?= htmlspecialchars($a['case_id']) ?>" data-status="<?= htmlspecialchars($status) ?>">View & Judge</button>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="4">No assigned cases yet.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<!-- Modal -->
<div class="modal fade" id="viewCaseModal" tabindex="-1" aria-labelledby="viewCaseLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewCaseLabel">Case Details & Judgment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="modal_case_id" id="modal_case_id">
        <table class="table table-bordered">
          <tbody>
            <tr><th>OB Number</th><td id="modal_ob_number"></td></tr>
            <tr><th>Complainant Name</th><td id="modal_full_name"></td></tr>
            <tr><th>Offence Type</th><td id="modal_offence_type"></td></tr>
            <tr><th>Date Reported</th><td id="modal_date_reported"></td></tr>
            <tr><th>Place of Occurrence</th><td id="modal_place_occurrence"></td></tr>
            <tr><th>Assigned Investigator</th><td id="modal_investigator"></td></tr>
            <tr><th>Current Status</th>
                <td>
                    <select name="status2" id="modal_status" class="form-select">
                        <option value="Under Investigation">Under Investigation</option>
                        <option value="Completed">Completed</option>
                    </select>
                </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="submit" name="update_case" class="btn btn-success">Update Status</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </form>
  </div>
</div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Pass case data to modal
var viewModal = document.getElementById('viewCaseModal')
viewModal.addEventListener('show.bs.modal', function (event) {
  var button = event.relatedTarget
  var caseId = button.getAttribute('data-case')
  var status = button.getAttribute('data-status')

  document.getElementById('modal_case_id').value = caseId
  document.getElementById('modal_status').value = status

  // Fetch full case details
  fetch('?fetch_case=' + caseId)
    .then(response => response.json())
    .then(data => {
        document.getElementById('modal_ob_number').innerText = data.ob_number
        document.getElementById('modal_full_name').innerText = data.full_name
        document.getElementById('modal_offence_type').innerText = data.offence_type
        document.getElementById('modal_date_reported').innerText = data.date_reported
        document.getElementById('modal_place_occurrence').innerText = data.place_occurrence
        document.getElementById('modal_investigator').innerText = data.investigator_name ?? 'Not Assigned'
    })
    .catch(err => console.error('Error fetching case details:', err));
})
</script>
</body>
</html>
    