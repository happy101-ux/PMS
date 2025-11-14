<?php
session_start();
require_once '../config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect if not logged in
if (!isset($_SESSION['officerid'])) {
    header("Location: ../login.php");
    exit();
}

$officerid = $_SESSION['officerid'];
$designation = $_SESSION['designation'];

// Allow access for CID and Admin
if ($designation !== 'CID' && $designation !== 'ADMIN') {
    echo "<h5>Access Denied: Only CID Officers or Admin can access this page.</h5>";
    exit();
}

/* ============================================================
   AJAX: fetch case details (must come BEFORE any HTML output)
   ============================================================ */
if (isset($_GET['fetch_caseid'])) {
    $caseid = intval($_GET['fetch_caseid']);

    $stmt = $conn->prepare("
        SELECT ct.caseid, ct.complaint_id, ct.officerid, ct.casetype, ct.status, ct.description, ct.closure_reason,
               c.ob_number, c.full_name, c.offence_type, c.date_reported, c.place_occurrence, c.statement, c.witnesses,
               CONCAT(u.first_name, ' ', u.last_name) AS officer_name
        FROM case_table ct
        LEFT JOIN complaints c ON ct.complaint_id = c.id
        LEFT JOIN userlogin u ON ct.officerid = u.officerid
        WHERE ct.caseid = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $caseid);
    $stmt->execute();
    $res = $stmt->get_result();
    $case = $res->fetch_assoc();
    $stmt->close();

    if (!$case) {
        http_response_code(404);
        echo json_encode(['error' => 'Case not found']);
        exit();
    }

    // Fetch evidence
    $ev_stmt = $conn->prepare("SELECT id, file_path, uploaded_at FROM case_evidence WHERE caseid = ? ORDER BY uploaded_at DESC");
    $ev_stmt->bind_param("i", $caseid);
    $ev_stmt->execute();
    $ev_res = $ev_stmt->get_result();
    $evidence = [];
    while ($r = $ev_res->fetch_assoc()) $evidence[] = $r;
    $ev_stmt->close();

    // Fetch investigations
    $investigations = [];
    $inv_stmt = $conn->prepare("
        SELECT id, case_id, investigator, statement2, status2, completed_date, remarks, assigned_date, last_updated
        FROM investigation
        WHERE case_id = ?
        ORDER BY assigned_date DESC
    ");
    $inv_stmt->bind_param("i", $caseid);
    $inv_stmt->execute();
    $inv_res = $inv_stmt->get_result();
    while ($r = $inv_res->fetch_assoc()) $investigations[] = $r;
    $inv_stmt->close();

    header('Content-Type: application/json');
    echo json_encode([
        'case' => $case,
        'evidence' => $evidence,
        'investigations' => $investigations
    ]);
    exit();
}

/* ============================================================
   AFTER AJAX HANDLER — now include HTML components
   ============================================================ */
include '../components/navbar.php';

// -------------------------
// Handle status update / close case POST
// -------------------------
$success = $error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($designation === 'CID' || $designation === 'ADMIN')) {
    $case_id = intval($_POST['case_id'] ?? 0);
    $status_update = trim($_POST['status_update'] ?? '');
    $findings = trim($_POST['findings'] ?? '');
    $closure_statement = trim($_POST['closure_statement'] ?? '');

    if ($case_id <= 0 || $status_update === '') {
        $error = "Missing required fields.";
    } else {
        if ($status_update === 'Closed') {
            $upd = $conn->prepare("UPDATE case_table SET status = ?, description = CONCAT(IFNULL(description,''), '\n', ?), closure_reason = ? WHERE caseid = ?");
            $upd->bind_param("sssi", $status_update, $findings, $closure_statement, $case_id);
        } else {
            $upd = $conn->prepare("UPDATE case_table SET status = ?, description = CONCAT(IFNULL(description,''), '\n', ?) WHERE caseid = ?");
            $upd->bind_param("ssi", $status_update, $findings, $case_id);
        }

        if ($upd->execute()) {
            $success = "Case #$case_id updated to '$status_update'.";

            if (!empty($_FILES['evidence_photo']['name'][0])) {
                $uploadDir = "../uploads/evidence/";
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                foreach ($_FILES['evidence_photo']['name'] as $k => $name) {
                    $tmp = $_FILES['evidence_photo']['tmp_name'][$k];
                    if (!is_uploaded_file($tmp)) continue;
                    $safeName = time() . "_" . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($name));
                    $target = $uploadDir . $safeName;
                    if (move_uploaded_file($tmp, $target)) {
                        $ins = $conn->prepare("INSERT INTO case_evidence (caseid, file_path, uploaded_at) VALUES (?, ?, NOW())");
                        $ins->bind_param("is", $case_id, $target);
                        $ins->execute();
                        $ins->close();
                    }
                }
            }
        } else {
            $error = "Error updating case: " . $conn->error;
        }
        $upd->close();
    }
}

// -------------------------
// Fetch cases for table
// -------------------------
if ($designation === 'ADMIN') {
    $query = $conn->prepare("
        SELECT ct.caseid, c.ob_number, c.full_name, c.offence_type, c.date_reported, c.place_occurrence,
               ct.status, CONCAT(u.first_name, ' ', u.last_name) AS officer_name
        FROM case_table ct
        LEFT JOIN complaints c ON ct.complaint_id = c.id
        LEFT JOIN userlogin u ON ct.officerid = u.officerid
        ORDER BY COALESCE(c.date_reported, '1970-01-01') DESC
    ");
} else {
    $query = $conn->prepare("
        SELECT ct.caseid, c.ob_number, c.full_name, c.offence_type, c.date_reported, c.place_occurrence, ct.status
        FROM case_table ct
        LEFT JOIN complaints c ON ct.complaint_id = c.id
        WHERE ct.officerid = ?
        ORDER BY COALESCE(c.date_reported, '1970-01-01') DESC
    ");
    $query->bind_param("s", $officerid);
}
$query->execute();
$result = $query->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>CID Dashboard - Assigned Cases</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
  font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
  background: #eef1f6;
  margin: 0;
}
.main-content { padding: 2rem; }
h2 { font-weight: 600; letter-spacing: 0.5px; }

.card {
  border: none;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.table thead {
  background-color: #0078d7;
  color: #fff;
  font-weight: 500;
}
.table tbody tr:hover { background-color: #f2f7ff; transition: 0.2s; }

.badge { padding: 6px 10px; border-radius: 6px; font-size: 0.85rem; }

.modal-content {
  border-radius: 10px;
  border: none;
  box-shadow: 0 2px 12px rgba(0,0,0,0.2);
}
.modal-header {
  background: linear-gradient(135deg, #0078d7, #0053a4);
  color: #fff;
  border-bottom: none;
}
.modal-body {
  background: #fafbfc;
  padding: 1.5rem;
  border-radius: 0 0 10px 10px;
}

.statement-box {
  background: #fff;
  border: 1px solid #ddd;
  padding: 10px 12px;
  border-radius: 8px;
  max-height: 220px;
  overflow: auto;
  font-size: 0.9rem;
  line-height: 1.4;
}
.evidence-img {
  max-height: 120px;
  margin: 6px;
  border-radius: 6px;
  border: 1px solid #ddd;
  object-fit: cover;
  transition: transform 0.2s;
}
.evidence-img:hover { transform: scale(1.05); }

.invest-card {
  background: #fff;
  border: 1px solid #e6e6e6;
  padding: 10px 12px;
  border-radius: 8px;
  margin-bottom: 8px;
  transition: 0.2s;
}
.invest-card:hover { background: #f7faff; }

.btn-info { background: #0d6efd; border: none; }
.btn-info:hover { background: #0b5ed7; }
.btn-outline-secondary { border-radius: 6px; }
.alert { border-radius: 8px; font-size: 0.9rem; }
</style>
</head>
<body>

<!-- Modern Navbar -->
<nav class="navbar navbar-dark" style="background: linear-gradient(90deg, #0078d7, #0053a4);">
  <div class="container-fluid">
    <span class="navbar-brand mb-0 h1">👮‍♂️ Criminal Investigation Dashboard</span>
  </div>
</nav>

<div class="container-fluid main-content">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="text-primary mb-0">📁 Assigned Cases</h2>
    <span class="text-muted fs-6"><?= htmlspecialchars($designation) ?> Officer</span>
  </div>

  <?php if ($success): ?><div class="alert alert-success shadow-sm"><?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger shadow-sm"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="card p-3">
    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle text-center">
        <thead>
          <tr>
            <th>Case ID</th>
            <th>OB Number</th>
            <th>Complainant</th>
            <th>Offence</th>
            <th>Date Reported</th>
            <th>Location</th>
            <?php if ($designation === 'ADMIN'): ?><th>Assigned Officer</th><?php endif; ?>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
          <?php while ($row = $result->fetch_assoc()): ?>
          <tr>
            <td><?= (int)$row['caseid'] ?></td>
            <td><?= htmlspecialchars($row['ob_number'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['full_name'] ?? 'N/A') ?></td>
            <td><?= htmlspecialchars($row['offence_type'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['date_reported'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['place_occurrence'] ?? '-') ?></td>
            <?php if ($designation === 'ADMIN'): ?>
            <td><?= htmlspecialchars($row['officer_name'] ?? 'Unassigned') ?></td>
            <?php endif; ?>
            <td><span class="badge <?= ($row['status']=='Closed') ? 'bg-success' : (($row['status']=='Case Dropped') ? 'bg-danger' : 'bg-warning text-dark') ?>"><?= htmlspecialchars($row['status']) ?></span></td>
            <td><button class="btn btn-sm btn-info view-case-btn" data-case="<?= (int)$row['caseid'] ?>">View / Update</button></td>
          </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="<?= ($designation === 'ADMIN') ? 9 : 8 ?>">No cases available.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal -->
<div class="modal fade" id="viewCaseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <form id="caseForm" method="POST" enctype="multipart/form-data" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Case Details & Update</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="case_id" id="case_id">
        <div class="row mb-2">
          <div class="col-md-4"><strong>OB Number:</strong> <div id="view_ob_number">-</div></div>
          <div class="col-md-4"><strong>Complainant:</strong> <div id="view_full_name">-</div></div>
          <div class="col-md-4"><strong>Assigned Officer:</strong> <div id="view_officer_name">-</div></div>
        </div>
        <div class="row mb-3">
          <div class="col-md-4"><strong>Offence:</strong> <div id="view_offence_type">-</div></div>
          <div class="col-md-4"><strong>Date Reported:</strong> <div id="view_date_reported">-</div></div>
          <div class="col-md-4"><strong>Place:</strong> <div id="view_place_occurrence">-</div></div>
        </div>
        <div class="mb-3"><strong>Statement:</strong><div class="statement-box" id="view_statement">-</div></div>
        <div class="mb-3"><strong>Witnesses:</strong><div class="statement-box" id="view_witnesses">-</div></div>
        <hr>
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">📌 Update Status</label>
            <select name="status_update" id="status_update" class="form-select" required>
              <option value="">-- Select --</option>
              <option value="Investigation in Progress">Investigation in Progress</option>
              <option value="Closed">Closed</option>
              <option value="Case Dropped">Case Dropped</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">🧾 Findings / Investigation Summary</label>
            <textarea name="findings" id="findings" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="mb-3 closure-section" style="display:none;">
          <label class="form-label">📄 Closure Statement (why closed)</label>
          <textarea name="closure_statement" id="closure_statement" class="form-control" rows="3"></textarea>
          <label class="form-label mt-2">📷 Upload Supporting Evidence (Optional)</label>
          <input type="file" name="evidence_photo[]" id="evidence_photo" class="form-control" multiple>
        </div>
        <hr>
        <div class="row">
          <div class="col-md-6">
            <h6>📁 Existing Evidence</h6>
            <div id="evidence_container" class="d-flex flex-wrap"></div>
          </div>
          <div class="col-md-6">
            <h6>🕵️ Investigations</h6>
            <div id="investigation_container"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('status_update').addEventListener('change', function() {
    const show = this.value === 'Closed';
    document.querySelectorAll('.closure-section').forEach(el => el.style.display = show ? 'block' : 'none');
  });

  document.querySelectorAll('.view-case-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const caseId = btn.dataset.case;
      loadCaseIntoModal(caseId);
      const modal = new bootstrap.Modal(document.getElementById('viewCaseModal'));
      modal.show();
    });
  });

  function loadCaseIntoModal(caseId) {
    fetch('?fetch_caseid=' + encodeURIComponent(caseId))
    .then(res => res.json())
    .then(data => {
      if (data.error) { alert(data.error); return; }
      const c = data.case;
      document.getElementById('case_id').value = c.caseid;
      document.getElementById('view_ob_number').innerText = c.ob_number || '-';
      document.getElementById('view_full_name').innerText = c.full_name || 'N/A';
      document.getElementById('view_officer_name').innerText = c.officer_name || (c.officerid ? c.officerid : 'Unassigned');
      document.getElementById('view_offence_type').innerText = c.offence_type || '-';
      document.getElementById('view_date_reported').innerText = c.date_reported || '-';
      document.getElementById('view_place_occurrence').innerText = c.place_occurrence || '-';
      document.getElementById('view_statement').innerText = c.statement || '-';
      document.getElementById('view_witnesses').innerText = c.witnesses || '-';
      document.getElementById('status_update').value = c.status || '';
      document.getElementById('findings').value = '';
      document.getElementById('closure_statement').value = '';

      const evc = document.getElementById('evidence_container'); evc.innerHTML = '';
      if (data.evidence && data.evidence.length) {
        data.evidence.forEach(e => {
          const wrap = document.createElement('div'); wrap.style.minWidth='120px'; wrap.style.margin='6px';
          if (e.file_path.match(/\.pdf$/i)) {
            wrap.innerHTML=`<a href="${e.file_path}" target="_blank" class="d-block text-decoration-none"><div class="p-2 border rounded bg-light text-center">📄 PDF File</div></a>`;
          } else {
            wrap.innerHTML=`<a href="${e.file_path}" target="_blank"><img src="${e.file_path}" class="evidence-img" alt="evidence"></a>`;
          }
          evc.appendChild(wrap);
        });
      } else { evc.innerHTML='<div class="text-muted">No evidence uploaded.</div>'; }

      const invc = document.getElementById('investigation_container'); invc.innerHTML='';
      if (data.investigations && data.investigations.length) {
        data.investigations.forEach(inv => {
          const d=document.createElement('div'); d.className='invest-card';
          d.innerHTML=`<strong>Investigator:</strong> ${inv.investigator || '-'}<br>
                       <strong>Status:</strong> ${inv.status2 || '-'}<br>
                       <strong>Assigned:</strong> ${inv.assigned_date || '-'}<br>
                       <div class="mt-2 text-muted" style="white-space:pre-wrap;">${inv.statement2 || inv.remarks || '-'}</div>`;
          invc.appendChild(d);
        });
      } else { invc.innerHTML='<div class="text-muted">No investigation records found.</div>'; }
    })
    .catch(err=>{console.error(err);alert('Failed to load case details.');});
  }
});
</script>
</body>
</html>
