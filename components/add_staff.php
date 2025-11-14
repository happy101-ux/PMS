<?php
// Debug mode (turn off in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/database.php'; // DB connection

include '../components/navbar.php';
include '../components/styles-sidebar.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // Collect & sanitize inputs
    $officerid   = trim($_POST['officerid'] ?? '');
    $rank        = trim($_POST['rank'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $gender      = trim($_POST['gender'] ?? '');
    $lastname    = trim($_POST['lastname'] ?? '');
    $firstname   = trim($_POST['firstname'] ?? '');
    $password    = trim($_POST['password'] ?? '');

    if ($officerid === '' || $rank === '' || $designation === '' || $gender === '' || $lastname === '' || $firstname === '') {
        $error = "All fields are required.";
    } else {
        // Hash password (default = officerid)
        $hashedPassword = password_hash(($password ?: $officerid), PASSWORD_BCRYPT);

        // Check if officer already exists
        $checkStmt = $conn->prepare("SELECT officerid FROM userlogin WHERE officerid = ?");
        if (!$checkStmt) {
            $error = "Prepare failed: " . $conn->error;
        } else {
            $checkStmt->bind_param("s", $officerid);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows > 0) {
                $error = "Officer ID " . htmlspecialchars($officerid) . " already exists.";
            } else {
                // Insert officer
                $insertStmt = $conn->prepare("
                    INSERT INTO userlogin (officerid, rank, designation, gender, password, last_name, first_name)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                if (!$insertStmt) {
                    $error = "Insert prepare failed: " . $conn->error;
                } else {
                    $insertStmt->bind_param("sssssss", $officerid, $rank, $designation, $gender, $hashedPassword, $lastname, $firstname);

                    if ($insertStmt->execute()) {
                        $success = "Officer registered successfully with ID: " . htmlspecialchars($officerid);
                    } else {
                        $error = "Execute failed: " . $insertStmt->error;
                    }
                    $insertStmt->close();
                }
            }
            $checkStmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Officer - Police Management System</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        
        .main-content {
            padding: 20px;
            min-height: calc(100vh - 80px);
        }
        
        .registration-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border: 1px solid #dee2e6;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .card-header {
            background: #2c3e50;
            color: white;
            padding: 15px 20px;
            border-radius: 8px 8px 0 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .form-section {
            padding: 20px;
        }
        
        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .form-control, .form-select {
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 8px 12px;
            font-size: 0.9rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.1);
        }
        
        .btn-register {
            background: #2c3e50;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 10px 20px;
            font-weight: 600;
            width: 100%;
            margin-top: 10px;
        }
        
        .btn-register:hover {
            background: #3498db;
        }
        
        .password-hint {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .input-group .btn {
            border: 1px solid #ced4da;
            border-left: none;
        }
        
        /* Ensure proper spacing */
        .row.g-3 {
            margin: 0 -8px;
        }
        
        .row.g-3 > [class*="col-"] {
            padding: 0 8px;
        }
        
        /* Make it responsive */
        @media (max-width: 768px) {
            .main-content {
                padding: 10px;
            }
            
            .form-section {
                padding: 15px;
            }
            
            .registration-card {
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="registration-card">
            <!-- Header -->
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-person-plus me-2"></i>
                    Register New Officer
                </h5>
            </div>
            
            <!-- Alerts -->
            <div class="form-section">
                <?php if ($success): ?>
                    <div class="alert alert-success mb-3">
                        <i class="bi bi-check-circle me-2"></i>
                        <?= $success ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger mb-3">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <!-- Registration Form -->
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="firstname" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="lastname" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Officer ID</label>
                            <input type="text" class="form-control" name="officerid" id="officeridInput" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Rank</label>
                            <select class="form-select" name="rank" required>
                                <option value="">Select Rank</option>
                                <option value="Constable">Constable</option>
                                <option value="Sergeant">Sergeant</option>
                                <option value="Inspector">Inspector</option>
                                <option value="Chief Inspector">Chief Inspector</option>
                                <option value="ADMIN">ADMIN</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Designation</label>
                            <select class="form-select" name="designation" required>
                                <option value="">Select Designation</option>
                                <option value="General Duties">General Duties</option>
                                <option value="CID">CID</option>
                                <option value="Traffic">Traffic</option>
                                <option value="Training">Training</option>
                                <option value="NCO">NCO</option>
                                <option value="ADMIN">ADMIN</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Default Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="password" id="passwordInput" readonly>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="password-hint">
                                Auto-generated from Officer ID
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" name="register" class="btn btn-register">
                            <i class="bi bi-person-plus me-2"></i>
                            Register Officer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-fill password = officer ID
        const officerInput = document.getElementById('officeridInput');
        const passwordInput = document.getElementById('passwordInput');
        const togglePassword = document.getElementById('togglePassword');

        // Initialize password with officer ID if it exists
        if (officerInput && passwordInput) {
            // Set initial value
            passwordInput.value = officerInput.value;
            
            // Update on input
            officerInput.addEventListener('input', function() {
                passwordInput.value = this.value;
            });
        }

        // Toggle password visibility
        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function() {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    this.innerHTML = '<i class="bi bi-eye-slash"></i>';
                } else {
                    passwordInput.type = 'password';
                    this.innerHTML = '<i class="bi bi-eye"></i>';
                }
            });
        }

        // Initialize form on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Ensure password is synced on page load
            if (officerInput && passwordInput && officerInput.value) {
                passwordInput.value = officerInput.value;
            }
        });
    </script>
</body>
</html>