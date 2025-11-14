<?php
session_start();
// Debug mode
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['officerid'])) {
    header("Location: ../login.php");
    exit();
}

// Database connection - using PDO
require_once __DIR__ . '/../config/database.php';

// Check if connection was successful
if (!$pdo) {
    die("Database connection failed");
}

include '../components/navbar.php';
// =====================
// Handle Delete Request
// =====================
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM userlogin WHERE id = ?");
        $stmt->execute([$delete_id]);
        echo "<script>window.location.href='view_officer.php';</script>";
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Error deleting officer: " . $e->getMessage();
        echo "<script>window.location.href='view_officer.php';</script>";
        exit();
    }
}

// ==========================
// Handle Disable/Enable User
// ==========================
if (isset($_GET['toggle_id']) && isset($_GET['disable'])) {
    $toggle_id = (int)$_GET['toggle_id'];
    $disable   = (int)$_GET['disable']; // 1 = disable, 0 = enable
    try {
        $stmt = $pdo->prepare("UPDATE userlogin SET disabled = ? WHERE id = ?");
        $stmt->execute([$disable, $toggle_id]);
        echo "<script>window.location.href='view_officer.php';</script>";
        exit();
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Error updating officer status: " . $e->getMessage();
        echo "<script>window.location.href='view_officer.php';</script>";
        exit();
    }
}

// ==================
// Handle Edit Request
// ==================
if (isset($_POST['edit_officer'])) {
    $id         = (int)$_POST['id'];
    $officerid  = $_POST['officerid'];
    $gender     = $_POST['gender'];
    $rank       = $_POST['rank'];
    $designation= $_POST['designation'];
    $last_name  = $_POST['last_name'];
    $first_name = $_POST['first_name'];

    try {
        $stmt = $pdo->prepare("UPDATE userlogin 
                                SET officerid=?, gender=?, rank=?, designation=?, last_name=?, first_name=?
                                WHERE id=?");
        if ($stmt->execute([$officerid, $gender, $rank, $designation, $last_name, $first_name, $id])) {
            $_SESSION['success_msg'] = "Officer updated successfully!";
        } else {
            $_SESSION['error_msg'] = "Error updating officer";
        }
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
    }
    
    echo "<script>window.location.href='view_officer.php';</script>";
    exit();
}

// ==================
// Fetch Officers
// ==================
try {
    $sql = "SELECT id, officerid, gender, rank, designation, last_name, first_name, disabled, date_added
            FROM userlogin 
            ORDER BY id DESC";
    $stmt = $pdo->query($sql);
    $officers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Officers - Police Management System</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>

        .table th {
            cursor: pointer;
            user-select: none;
        }
        .table th:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
        <h3 class="mb-4 text-primary">
            <i class="bi bi-people-fill me-2"></i>View Officers
        </h3>

        <!-- Display Success/Error Messages -->
        <?php if (isset($_SESSION['success_msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i>
                <?= htmlspecialchars($_SESSION['success_msg']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_msg']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_msg'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($_SESSION['error_msg']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_msg']); ?>
        <?php endif; ?>

        <!-- Search box -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="searchInput" class="form-control" placeholder="Search by ID, Officer ID, Name, Rank...">
                </div>
            </div>
            <div class="col-md-6 text-end">
                <span class="badge bg-primary fs-6">
                    Total Officers: <?= count($officers) ?>
                </span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped" id="officerTable">
                <thead class="table-dark">
                    <tr>
                        <th onclick="sortTable(0)">ID <i class="bi bi-arrow-down-up"></i></th>
                        <th onclick="sortTable(1)">Officer ID <i class="bi bi-arrow-down-up"></i></th>
                        <th onclick="sortTable(2)">Gender <i class="bi bi-arrow-down-up"></i></th>
                        <th onclick="sortTable(3)">Rank <i class="bi bi-arrow-down-up"></i></th>
                        <th onclick="sortTable(4)">Designation <i class="bi bi-arrow-down-up"></i></th>
                        <th onclick="sortTable(5)">Last Name <i class="bi bi-arrow-down-up"></i></th>
                        <th onclick="sortTable(6)">First Name <i class="bi bi-arrow-down-up"></i></th>
                        <th onclick="sortTable(7)">Date Added <i class="bi bi-arrow-down-up"></i></th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($officers) > 0): ?>
                        <?php foreach ($officers as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['id']) ?></td>
                                <td><strong><?= htmlspecialchars($row['officerid']) ?></strong></td>
                                <td><?= htmlspecialchars($row['gender']) ?></td>
                                <td><span class="badge bg-info"><?= htmlspecialchars($row['rank']) ?></span></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($row['designation']) ?></span></td>
                                <td><?= htmlspecialchars($row['last_name']) ?></td>
                                <td><?= htmlspecialchars($row['first_name']) ?></td>
                                <td><?= date('M j, Y', strtotime($row['date_added'])) ?></td>
                                <td>
                                    <?php if ($row['disabled'] == 1): ?>
                                        <span class="badge bg-danger">Disabled</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editOfficerModal"
                                                data-id="<?= $row['id'] ?>"
                                                data-officerid="<?= htmlspecialchars($row['officerid']) ?>"
                                                data-gender="<?= htmlspecialchars($row['gender']) ?>"
                                                data-rank="<?= htmlspecialchars($row['rank']) ?>"
                                                data-designation="<?= htmlspecialchars($row['designation']) ?>"
                                                data-lastname="<?= htmlspecialchars($row['last_name']) ?>"
                                                data-firstname="<?= htmlspecialchars($row['first_name']) ?>">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <a href="view_officer.php?toggle_id=<?= $row['id'] ?>&disable=<?= $row['disabled'] ? 0 : 1 ?>" 
                                           class="btn btn-sm <?= $row['disabled'] ? 'btn-outline-success' : 'btn-outline-warning' ?>"
                                           onclick="return confirm('Are you sure you want to <?= $row['disabled'] ? 'enable' : 'disable' ?> this officer?');">
                                            <i class="bi bi-<?= $row['disabled'] ? 'check' : 'x' ?>-circle"></i> 
                                            <?= $row['disabled'] ? 'Enable' : 'Disable' ?>
                                        </a>
                                        <a href="view_officer.php?delete_id=<?= $row['id'] ?>" 
                                           class="btn btn-outline-danger" 
                                           onclick="return confirm('Are you sure you want to delete this officer? This action cannot be undone.');">
                                            <i class="bi bi-trash"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">
                                <i class="bi bi-people display-4 d-block mb-2"></i>
                                No officers found in the system.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit Officer Modal -->
    <div class="modal fade" id="editOfficerModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <form method="POST" enctype="multipart/form-data">
            <div class="modal-header bg-primary text-white">
              <h5 class="modal-title">
                <i class="bi bi-person-gear me-2"></i>Edit Officer
              </h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
              <input type="hidden" name="id" id="edit-id">

              <div class="row g-3 text-start">

                <!-- Officer ID -->
                <div class="col-md-6">
                  <label class="form-label">Officer ID *</label>
                  <input type="text" class="form-control" name="officerid" id="edit-officerid" required>
                </div>

                <!-- Gender Dropdown -->
                <div class="col-md-6">
                  <label class="form-label">Gender *</label>
                  <select class="form-select" name="gender" id="edit-gender" required>
                    <option value="" disabled>Choose Gender</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                  </select>
                </div>

                <!-- Rank Dropdown -->
                <div class="col-md-6">
                  <label class="form-label">Rank *</label>
                  <select class="form-select" name="rank" id="edit-rank" required>
                    <option value="" disabled>Choose Rank</option>
                    <option value="Constable">Constable</option>
                    <option value="Sergeant">Sergeant</option>
                    <option value="Inspector">Inspector</option>
                    <option value="Chief Inspector">Chief Inspector</option>
                    <option value="ADMIN">ADMIN</option>
                  </select>
                </div>

                <!-- Designation Dropdown -->
                <div class="col-md-6">
                  <label class="form-label">Designation *</label>
                  <select class="form-select" name="designation" id="edit-designation" required>
                    <option value="" disabled>Choose Designation</option>
                    <option value="General Duties">General Duties</option>
                    <option value="CID">CID</option>
                    <option value="Traffic">Traffic</option>
                    <option value="Training">Training</option>
                    <option value="NCO">NCO</option>
                    <option value="Admin">Admin</option>
                  </select>
                </div>

                <!-- Last Name -->
                <div class="col-md-6">
                  <label class="form-label">Last Name *</label>
                  <input type="text" class="form-control" name="last_name" id="edit-lastname" required>
                </div>

                <!-- First Name -->
                <div class="col-md-6">
                  <label class="form-label">First Name *</label>
                  <input type="text" class="form-control" name="first_name" id="edit-firstname" required>
                </div>

              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="bi bi-x-circle me-1"></i> Cancel
              </button>
              <button type="submit" name="edit_officer" class="btn btn-primary">
                <i class="bi bi-check-circle me-1"></i> Save Changes
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
    // Fill modal with row data
    const editOfficerModal = document.getElementById('editOfficerModal');
    editOfficerModal.addEventListener('show.bs.modal', event => {
      const button = event.relatedTarget;

      // Text inputs
      document.getElementById('edit-id').value = button.getAttribute('data-id');
      document.getElementById('edit-officerid').value = button.getAttribute('data-officerid');
      document.getElementById('edit-lastname').value = button.getAttribute('data-lastname');
      document.getElementById('edit-firstname').value = button.getAttribute('data-firstname');

      // Dropdowns
      const gender = button.getAttribute('data-gender');
      const rank = button.getAttribute('data-rank');
      const designation = button.getAttribute('data-designation');

      // Set selected option
      document.getElementById('edit-gender').value = gender || '';
      document.getElementById('edit-rank').value = rank || '';
      document.getElementById('edit-designation').value = designation || '';
    });

    // Search filter
    document.getElementById("searchInput").addEventListener("keyup", function () {
      let filter = this.value.toLowerCase();
      let rows = document.querySelectorAll("#officerTable tbody tr");
      rows.forEach(row => {
        let text = row.innerText.toLowerCase();
        row.style.display = text.includes(filter) ? "" : "none";
      });
    });

    // Sort table
    function sortTable(n) {
      let table = document.getElementById("officerTable");
      let switching = true, dir = "asc", switchcount = 0;

      while (switching) {
        switching = false;
        let rows = table.rows;
        for (let i = 1; i < rows.length - 1; i++) {
          let shouldSwitch = false;
          let x = rows[i].getElementsByTagName("TD")[n].innerText.toLowerCase();
          let y = rows[i + 1].getElementsByTagName("TD")[n].innerText.toLowerCase();
          if ((dir === "asc" && x > y) || (dir === "desc" && x < y)) shouldSwitch = true;
          if (shouldSwitch) {
            rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
            switching = true;
            switchcount++;
          }
        }
        if (switchcount === 0 && dir === "asc") {
          dir = "desc";
          switching = true;
        }
      }
    }

    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    </script>
</body>
</html>
<?php
// PDO connection doesn't need to be closed explicitly in the same way as MySQLi
// The connection will be automatically closed when the script ends
?>