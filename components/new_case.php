<?php
session_start();
require_once '../config/database.php';
include '../components/navbar.php';
//include '../components/sidebar.php';

?>
<?php
// Redirect if not logged in
if (!isset($_SESSION['officerid'])) {
    header("Location: ../login.php");
    exit();
}

// Auto-filled officer details (your existing PHP code remains the same)
$officer_name  = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$rank          = $_SESSION['rank'];
$designation   = $_SESSION['designation'] ?? '';
$force_number  = $_SESSION['officerid'];
$received_by   = $officer_name;
$signature     = strtoupper(substr($officer_name, 0, 1)) . substr($officer_name, -1);

// Auto-filled date and time
$date_reported = date("Y-m-d");
$time_reported = date("H:i");

// Sequential OB Number per day
$stmt = $conn->prepare("SELECT ob_number FROM complaints WHERE date_reported = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("s", $date_reported);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    $last_ob = $result->fetch_assoc()['ob_number'];
    $last_num = (int)substr($last_ob, -3);
    $new_num = str_pad($last_num + 1, 3, "0", STR_PAD_LEFT);
} else {
    $new_num = "001";
}
$ob_number = "OB-" . date("Ymd") . "-" . $new_num;

// Handle form submission (your existing PHP code remains the same)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $conn->begin_transaction();
    try {
        $station             = trim($_POST['station'] ?? '');
        $full_name           = trim($_POST['full_name'] ?? '');
        $id_number           = trim($_POST['id_number'] ?? '');
        $dob                 = trim($_POST['dob'] ?? '');
        $gender              = trim($_POST['gender'] ?? '');
        $occupation          = ($_POST['occupation'] ?? '') === 'Other' ? trim($_POST['occupation_other'] ?? '') : ($_POST['occupation'] ?? '');
        $residential_address = trim($_POST['residential_address'] ?? '');
        $postal_address      = trim($_POST['postal_address'] ?? '');
        $phone               = trim($_POST['phone'] ?? '');
        $next_of_kin         = trim($_POST['next_of_kin'] ?? '');
        $next_of_kin_contact = trim($_POST['next_of_kin_contact'] ?? '');
        $offence_type        = ($_POST['offence_type'] ?? '') === 'Other' ? trim($_POST['offence_other'] ?? '') : ($_POST['offence_type'] ?? '');
        $date_occurrence     = trim($_POST['date_occurrence'] ?? '');
        $time_occurrence     = trim($_POST['time_occurrence'] ?? '');
        $place_occurrence    = trim($_POST['place_occurrence'] ?? '') . ' ' . trim($_POST['place_occurrence_details'] ?? '');
        $suspect_name        = trim($_POST['suspect_name'] ?? '');
        $suspect_address     = trim($_POST['suspect_address'] ?? '');
        $statement           = trim($_POST['statement'] ?? '');
        $witnesses           = $_POST['witnesses'] ?? [];
        $remarks             = ($_POST['remarks'] ?? '') === 'Others' ? trim($_POST['remarks_other'] ?? '') : ($_POST['remarks'] ?? '');
        $save_option         = $_POST['save_option'] ?? 'complaint';

        $witnesses_json = json_encode($witnesses);

        if ($save_option === 'complaint' || $save_option === 'both') {
            $sql = "INSERT INTO complaints (
                station, date_reported, time_reported, ob_number, full_name, id_number, dob, gender, occupation,
                residential_address, postal_address, phone, next_of_kin, next_of_kin_contact, offence_type,
                date_occurrence, time_occurrence, place_occurrence, suspect_name, suspect_address, statement,
                witnesses, declaration_name, signature, declaration_date, received_by, rank, designation, force_number, remarks
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "ssssssssssssssssssssssssssssss",
                $station, $date_reported, $time_reported, $ob_number, $full_name, $id_number, $dob, $gender, $occupation,
                $residential_address, $postal_address, $phone, $next_of_kin, $next_of_kin_contact, $offence_type,
                $date_occurrence, $time_occurrence, $place_occurrence, $suspect_name, $suspect_address, $statement,
                $witnesses_json, $officer_name, $signature, $date_reported, $force_number, $rank, $designation, $force_number, $remarks
            );
            $stmt->execute();
        }

        if ($save_option === 'case' || $save_option === 'both') {
            $stmt2 = $conn->prepare("INSERT INTO case_table (officerid, casetype, status, description) VALUES (?, ?, ?, ?)");
            $case_status = "Pending";
            $stmt2->bind_param("ssss", $force_number, $offence_type, $case_status, $statement);
            $stmt2->execute();
        }

        $conn->commit();
        $success_msg = "Record saved successfully! OB Number: $ob_number";

    } catch (Exception $e) {
        $conn->rollback();
        $error_msg = "Error: " . $e->getMessage();
    }
}
?>

<div class="main-content" id="mainContent">
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #e74c3c;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --success: #27ae60;
            --warning: #f39c12;
        }
        
        body { 
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .main-content {
            background: transparent;
            padding: 20px;
        }
        
        .form-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .form-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: none;
            overflow: hidden;
        }
        
        .form-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 25px 30px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .form-header h4 {
            margin: 0;
            font-weight: 600;
            font-size: 1.5rem;
        }
        
        .form-body {
            padding: 30px;
        }
        
        .section-card {
            border: 1px solid #e3e8f0;
            border-radius: 12px;
            margin-bottom: 25px;
            background: #fafbfc;
            transition: all 0.3s ease;
        }
        
        .section-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .section-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-body {
            padding: 25px;
        }
        
        .form-control, .form-select {
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--secondary), #2980b9);
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }
        
        .btn-secondary {
            background: #95a5a6;
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
        }
        
        .btn-danger {
            background: var(--accent);
            border: none;
            border-radius: 6px;
        }
        
        .witness-item {
            background: white;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
        }
        
        .witness-item:hover {
            border-color: var(--secondary);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
        }
        
        .form-check-input:checked {
            background-color: var(--secondary);
            border-color: var(--secondary);
        }
        
        .readonly-field {
            background-color: #f8f9fa;
            border-color: #e9ecef;
            color: #6c757d;
        }
        
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }
        
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e2e8f0;
            z-index: 1;
        }
        
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
        }
        
        .step-circle {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: white;
            border: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 8px;
        }
        
        .step.active .step-circle {
            background: var(--secondary);
            border-color: var(--secondary);
            color: white;
        }
        
        .step-label {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 500;
        }
        
        .step.active .step-label {
            color: var(--secondary);
            font-weight: 600;
        }
    </style>

    <div class="form-container">
        <div class="form-card">
            <div class="form-header">
                <h4>📝 Digital Complaint Form</h4>
                <p class="mb-0 mt-2 opacity-75">Complete the form below to register a new complaint</p>
            </div>
            
            <div class="form-body">
                <?php if(isset($success_msg)): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill"></i> <?= $success_msg ?>
                    </div>
                    <script>
                        setTimeout(() => {
                            document.querySelector('form').reset();
                            if (confirm("Record saved successfully.\nDo you want to print the details?")) {
                                window.print();
                            }
                        }, 800);
                    </script>
                <?php endif; ?>

                <?php if(isset($error_msg)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill"></i> <?= $error_msg ?>
                    </div>
                <?php endif; ?>

                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step active">
                        <div class="step-circle">1</div>
                        <div class="step-label">Complainant</div>
                    </div>
                    <div class="step">
                        <div class="step-circle">2</div>
                        <div class="step-label">Incident</div>
                    </div>
                    <div class="step">
                        <div class="step-circle">3</div>
                        <div class="step-label">Witnesses</div>
                    </div>
                    <div class="step">
                        <div class="step-circle">4</div>
                        <div class="step-label">Officer</div>
                    </div>
                </div>

                <form method="POST" autocomplete="off">
                    <!-- Complainant Details Section -->
                    <div class="section-card">
                        <div class="section-header">
                            <i class="bi bi-person-badge"></i> Complainant Details
                        </div>
                        <div class="section-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Station</label>
                                    <input type="text" name="station" class="form-control" placeholder="Enter station name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="full_name" class="form-control" placeholder="Enter full name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">ID / NRC / Passport</label>
                                    <input type="text" name="id_number" class="form-control" placeholder="Enter identification number" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" name="dob" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Gender</label>
                                    <select name="gender" class="form-select" required>
                                        <option value="">-- Select Gender --</option>
                                        <option>Male</option>
                                        <option>Female</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Occupation</label>
                                    <select name="occupation" id="occupationSelect" class="form-select">
                                        <option value="">-- Select Occupation --</option>
                                        <option>Farmer</option>
                                        <option>Teacher</option>
                                        <option>Healthcare Worker</option>
                                        <option>Retail Worker</option>
                                        <option>Engineer</option>
                                        <option>Driver</option>
                                        <option>Student</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6" id="occupationOtherDiv" style="display:none;">
                                    <label class="form-label">Specify Other Occupation</label>
                                    <input type="text" name="occupation_other" class="form-control" placeholder="Enter occupation">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Incident Details Section -->
                    <div class="section-card">
                        <div class="section-header">
                            <i class="bi bi-clock-history"></i> Incident Details
                        </div>
                        <div class="section-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Offence Type</label>
                                    <select name="offence_type" id="offenceSelect" class="form-select" required>
                                        <option value="">-- Select Offence --</option>
                                        <option>Theft</option>
                                        <option>Assault</option>
                                        <option>Fraud</option>
                                        <option>Robbery</option>
                                        <option>Vandalism</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6" id="offenceOtherDiv" style="display:none;">
                                    <label class="form-label">Specify Other Offence</label>
                                    <input type="text" name="offence_other" class="form-control" placeholder="Enter offence type">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Date of Occurrence</label>
                                    <input type="date" name="date_occurrence" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Time of Occurrence</label>
                                    <input type="time" name="time_occurrence" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Place of Occurrence</label>
                                    <select name="place_occurrence" class="form-select mb-2" required>
                                        <option value="">-- Select Province --</option>
                                        <option>Lusaka</option>
                                        <option>Central</option>
                                        <option>Copperbelt</option>
                                        <option>Eastern</option>
                                        <option>Luapula</option>
                                        <option>Muchinga</option>
                                        <option>North-Western</option>
                                        <option>Southern</option>
                                        <option>Western</option>
                                    </select>
                                    <input type="text" name="place_occurrence_details" class="form-control" placeholder="City / Sub-Street (Optional)">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Statement</label>
                                    <textarea name="statement" class="form-control" rows="4" placeholder="Provide detailed statement of the incident"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Witness Section -->
                    <div class="section-card">
                        <div class="section-header">
                            <i class="bi bi-people"></i> Witness Information
                        </div>
                        <div class="section-body">
                            <div class="mb-3">
                                <label class="form-label">Witnesses</label>
                                <div id="witnessContainer"></div>
                                <button type="button" id="addWitness" class="btn btn-secondary mt-2">
                                    <i class="bi bi-plus-circle"></i> Add Witness
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Officer & Save Options Section -->
                    <div class="section-card">
                        <div class="section-header">
                            <i class="bi bi-shield-check"></i> Officer & Save Options
                        </div>
                        <div class="section-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Officer Name</label>
                                    <input type="text" class="form-control readonly-field" value="<?= htmlspecialchars($officer_name) ?>" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Rank</label>
                                    <input type="text" class="form-control readonly-field" value="<?= htmlspecialchars($rank) ?>" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Designation</label>
                                    <input type="text" class="form-control readonly-field" value="<?= htmlspecialchars($designation) ?>" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Force Number</label>
                                    <input type="text" class="form-control readonly-field" value="<?= htmlspecialchars($force_number) ?>" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Signature</label>
                                    <input type="text" class="form-control readonly-field" value="<?= htmlspecialchars($signature) ?>" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Date Reported</label>
                                    <input type="date" class="form-control readonly-field" value="<?= htmlspecialchars($date_reported) ?>" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Time Reported</label>
                                    <input type="time" class="form-control readonly-field" value="<?= htmlspecialchars($time_reported) ?>" readonly>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">OB Number</label>
                                    <input type="text" class="form-control readonly-field" value="<?= htmlspecialchars($ob_number) ?>" readonly>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Remarks</label>
                                    <select name="remarks" id="remarksSelect" class="form-select">
                                        <option value="">-- Select Remarks --</option>
                                        <option>Needs Immediate Attention</option>
                                        <option>Needs Attention</option>
                                        <option value="Others">Others</option>
                                    </select>
                                </div>
                                <div class="col-md-6" id="remarksOtherDiv" style="display:none;">
                                    <label class="form-label">Specify Other Remarks</label>
                                    <input type="text" name="remarks_other" class="form-control" placeholder="Enter remarks">
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Save To:</label>
                                    <div class="d-flex gap-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="save_option" id="saveComplaint" value="complaint" checked>
                                            <label class="form-check-label" for="saveComplaint">
                                                <strong>Complaint Only</strong><br>
                                                <small class="text-muted">Save as complaint record</small>
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="save_option" id="saveCase" value="case">
                                            <label class="form-check-label" for="saveCase">
                                                <strong>Case Only</strong><br>
                                                <small class="text-muted">Create a new case file</small>
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="save_option" id="saveBoth" value="both">
                                            <label class="form-check-label" for="saveBoth">
                                                <strong>Both</strong><br>
                                                <small class="text-muted">Save as complaint and create case</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-send-check"></i> Submit Complaint
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Witness Management
document.getElementById('addWitness').addEventListener('click', () => {
    const container = document.getElementById('witnessContainer');
    const witnessItem = document.createElement('div');
    witnessItem.className = 'witness-item';
    witnessItem.innerHTML = `
        <input type="text" name="witnesses[]" class="form-control" placeholder="Witness Full Name" required>
        <button type="button" class="btn btn-danger removeWitness">
            <i class="bi bi-trash"></i> Remove
        </button>
    `;
    container.appendChild(witnessItem);
    
    witnessItem.querySelector('.removeWitness').addEventListener('click', () => {
        container.removeChild(witnessItem);
    });
});

// Dynamic Other fields
document.getElementById('occupationSelect').addEventListener('change', function() {
    document.getElementById('occupationOtherDiv').style.display = this.value === 'Other' ? 'block' : 'none';
});

document.getElementById('offenceSelect').addEventListener('change', function() {
    document.getElementById('offenceOtherDiv').style.display = this.value === 'Other' ? 'block' : 'none';
});

document.getElementById('remarksSelect').addEventListener('change', function() {
    document.getElementById('remarksOtherDiv').style.display = this.value === 'Others' ? 'block' : 'none';
});

// Simple form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const requiredFields = this.querySelectorAll('[required]');
    let valid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            valid = false;
            field.style.borderColor = '#e74c3c';
        } else {
            field.style.borderColor = '';
        }
    });
    
    if (!valid) {
        e.preventDefault();
        alert('Please fill in all required fields marked in red.');
    }
});
</script>
</body>
</html>
</div>