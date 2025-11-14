<?php
require_once '../case_allocation_security.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_officer'])) {
        // Assign officer to case
        $caseId = $_POST['case_id'];
        $officerId = $_POST['officer_id'];
        $role = $_POST['role'];
        $notes = $_POST['assignment_notes'];
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO case_assignments (case_id, officer_id, assigned_by, role, notes) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$caseId, $officerId, $officerid, $role, $notes]);
            
            $_SESSION['success'] = "Officer successfully assigned to case!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error assigning officer: " . $e->getMessage();
        }
    } elseif (isset($_POST['schedule_meeting'])) {
        // Schedule meeting
        $caseId = $_POST['meeting_case_id'];
        $meetingTitle = $_POST['meeting_title'];
        $meetingDate = $_POST['meeting_date'];
        $meetingLocation = $_POST['meeting_location'];
        $agenda = $_POST['agenda'];
        $attendees = isset($_POST['attendees']) ? implode(',', $_POST['attendees']) : '';
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO case_meetings (case_id, meeting_title, meeting_date, meeting_location, agenda, attendees, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$caseId, $meetingTitle, $meetingDate, $meetingLocation, $agenda, $attendees, $officerid]);
            
            $_SESSION['success'] = "Meeting successfully scheduled!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error scheduling meeting: " . $e->getMessage();
        }
    }
    
    header("Location: case_allocation.php");
    exit();
}

// Get all active cases
$cases = $pdo->query("
    SELECT c.caseid, c.casetype, c.status, c.description, 
           comp.ob_number, comp.full_name, comp.offence_type
    FROM case_table c 
    LEFT JOIN complaints comp ON c.complaint_id = comp.id 
    WHERE c.status IN ('Active', 'Pending')
    ORDER BY c.caseid DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get all CID officers
$cidOfficers = $pdo->query("
    SELECT officerid, rank, first_name, last_name 
    FROM userlogin 
    WHERE designation = 'CID' AND disabled = 0 
    ORDER BY rank, first_name, last_name
")->fetchAll(PDO::FETCH_ASSOC);

// Get case assignments
$caseAssignments = $pdo->query("
    SELECT ca.*, u.first_name, u.last_name, u.rank,
           c.caseid, c.casetype, comp.ob_number
    FROM case_assignments ca
    JOIN userlogin u ON ca.officer_id = u.officerid
    JOIN case_table c ON ca.case_id = c.caseid
    LEFT JOIN complaints comp ON c.complaint_id = comp.id
    WHERE ca.status = 'Active'
    ORDER BY ca.assignment_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get upcoming meetings
$upcomingMeetings = $pdo->query("
    SELECT cm.*, c.caseid, comp.ob_number, comp.full_name,
           u.first_name as creator_first, u.last_name as creator_last
    FROM case_meetings cm
    JOIN case_table c ON cm.case_id = c.caseid
    LEFT JOIN complaints comp ON c.complaint_id = comp.id
    JOIN userlogin u ON cm.created_by = u.officerid
    WHERE cm.meeting_date >= NOW()
    ORDER BY cm.meeting_date ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Case Allocation & Meetings - Police Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .case-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        .case-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .meeting-card {
            border-left: 4px solid #28a745;
        }
        .assignment-card {
            border-left: 4px solid #ffc107;
        }
        .stats-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #6f42c1;
        }
        .nav-tabs .nav-link.active {
            font-weight: 600;
            border-bottom: 3px solid #007bff;
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
                                    <i class="bi bi-diagram-3 me-2"></i>
                                    Case Allocation & Meeting Management
                                </h4>
                                <p class="card-text mb-0">
                                    Assign officers to cases and schedule investigation meetings
                                </p>
                            </div>
                            <div class="col-auto">
                                <span class="badge bg-light text-dark fs-6">
                                    <i class="bi bi-shield-check me-1"></i>
                                    <?php 
                                    if ($roleManager->isAdmin()) echo 'ADMIN';
                                    elseif ($roleManager->getUserRole() === 'cid_superior') echo 'CID SUPERVISOR';
                                    else echo 'CID';
                                    ?>
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
                    <h5 class="text-primary"><?php echo count($cases); ?></h5>
                    <p class="text-muted mb-0">Active Cases</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h5 class="text-success"><?php echo count($cidOfficers); ?></h5>
                    <p class="text-muted mb-0">CID Officers</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h5 class="text-warning"><?php echo count($caseAssignments); ?></h5>
                    <p class="text-muted mb-0">Active Assignments</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <h5 class="text-info"><?php echo count($upcomingMeetings); ?></h5>
                    <p class="text-muted mb-0">Upcoming Meetings</p>
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

        <!-- Main Content Tabs -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="allocationTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="assign-tab" data-bs-toggle="tab" data-bs-target="#assign" type="button" role="tab">
                                    <i class="bi bi-person-plus me-1"></i>Assign Officers
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="meetings-tab" data-bs-toggle="tab" data-bs-target="#meetings" type="button" role="tab">
                                    <i class="bi bi-calendar-event me-1"></i>Schedule Meetings
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="assignments-tab" data-bs-toggle="tab" data-bs-target="#assignments" type="button" role="tab">
                                    <i class="bi bi-list-check me-1"></i>Current Assignments
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="upcoming-tab" data-bs-toggle="tab" data-bs-target="#upcoming" type="button" role="tab">
                                    <i class="bi bi-clock me-1"></i>Upcoming Meetings
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="allocationTabsContent">
                            <!-- Assign Officers Tab -->
                            <div class="tab-pane fade show active" id="assign" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5>Assign Officer to Case</h5>
                                        <form method="POST">
                                            <div class="mb-3">
                                                <label class="form-label">Select Case</label>
                                                <select class="form-select" name="case_id" required>
                                                    <option value="">Choose a case...</option>
                                                    <?php foreach($cases as $case): ?>
                                                    <option value="<?php echo $case['caseid']; ?>">
                                                        Case #<?php echo $case['caseid']; ?> - 
                                                        <?php echo htmlspecialchars($case['casetype']); ?> -
                                                        OB: <?php echo htmlspecialchars($case['ob_number'] ?? 'N/A'); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Select Officer</label>
                                                <select class="form-select" name="officer_id" required>
                                                    <option value="">Choose an officer...</option>
                                                    <?php foreach($cidOfficers as $officer): ?>
                                                    <option value="<?php echo $officer['officerid']; ?>">
                                                        <?php echo htmlspecialchars($officer['rank'] . ' ' . $officer['first_name'] . ' ' . $officer['last_name']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Role</label>
                                                <select class="form-select" name="role" required>
                                                    <option value="Lead Investigator">Lead Investigator</option>
                                                    <option value="Supporting Officer">Supporting Officer</option>
                                                    <option value="Supervisor">Supervisor</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Assignment Notes</label>
                                                <textarea class="form-control" name="assignment_notes" rows="3" placeholder="Add any specific instructions or notes..."></textarea>
                                            </div>
                                            <button type="submit" name="assign_officer" class="btn btn-primary">
                                                <i class="bi bi-person-check me-1"></i>Assign Officer
                                            </button>
                                        </form>
                                    </div>
                                    <div class="col-md-6">
                                        <h5>Available Cases</h5>
                                        <div class="row">
                                            <?php foreach(array_slice($cases, 0, 4) as $case): ?>
                                            <div class="col-12 mb-3">
                                                <div class="card case-card">
                                                    <div class="card-body">
                                                        <h6 class="card-title">
                                                            Case #<?php echo $case['caseid']; ?>
                                                            <span class="badge bg-secondary float-end"><?php echo $case['status']; ?></span>
                                                        </h6>
                                                        <p class="card-text mb-1">
                                                            <strong>Type:</strong> <?php echo htmlspecialchars($case['casetype']); ?>
                                                        </p>
                                                        <p class="card-text mb-1">
                                                            <strong>OB Number:</strong> <?php echo htmlspecialchars($case['ob_number'] ?? 'N/A'); ?>
                                                        </p>
                                                        <?php if ($case['description']): ?>
                                                        <p class="card-text mb-0">
                                                            <strong>Description:</strong> <?php echo htmlspecialchars(substr($case['description'], 0, 100)) . '...'; ?>
                                                        </p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Schedule Meetings Tab -->
                            <div class="tab-pane fade" id="meetings" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5>Schedule Case Meeting</h5>
                                        <form method="POST">
                                            <div class="mb-3">
                                                <label class="form-label">Select Case</label>
                                                <select class="form-select" name="meeting_case_id" required>
                                                    <option value="">Choose a case...</option>
                                                    <?php foreach($cases as $case): ?>
                                                    <option value="<?php echo $case['caseid']; ?>">
                                                        Case #<?php echo $case['caseid']; ?> - 
                                                        <?php echo htmlspecialchars($case['casetype']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Meeting Title</label>
                                                <input type="text" class="form-control" name="meeting_title" required placeholder="e.g., Initial Investigation Briefing">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Meeting Date & Time</label>
                                                <input type="datetime-local" class="form-control" name="meeting_date" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Location</label>
                                                <input type="text" class="form-control" name="meeting_location" required placeholder="e.g., Conference Room A">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Agenda</label>
                                                <textarea class="form-control" name="agenda" rows="3" placeholder="Meeting agenda items..."></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Attendees</label>
                                                <div class="border p-3 rounded">
                                                    <?php foreach($cidOfficers as $officer): ?>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="attendees[]" value="<?php echo $officer['officerid']; ?>" id="attendee_<?php echo $officer['officerid']; ?>">
                                                        <label class="form-check-label" for="attendee_<?php echo $officer['officerid']; ?>">
                                                            <?php echo htmlspecialchars($officer['rank'] . ' ' . $officer['first_name'] . ' ' . $officer['last_name']); ?>
                                                        </label>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <button type="submit" name="schedule_meeting" class="btn btn-success">
                                                <i class="bi bi-calendar-plus me-1"></i>Schedule Meeting
                                            </button>
                                        </form>
                                    </div>
                                    <div class="col-md-6">
                                        <h5>Meeting Guidelines</h5>
                                        <div class="alert alert-info">
                                            <h6><i class="bi bi-info-circle me-2"></i>Best Practices</h6>
                                            <ul class="mb-0">
                                                <li>Schedule meetings at least 24 hours in advance</li>
                                                <li>Include clear agenda items</li>
                                                <li>Invite all relevant case officers</li>
                                                <li>Choose appropriate meeting locations</li>
                                                <li>Document meeting minutes for future reference</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Current Assignments Tab -->
                            <div class="tab-pane fade" id="assignments" role="tabpanel">
                                <h5>Current Case Assignments</h5>
                                <?php if (empty($caseAssignments)): ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-person-x display-4 text-muted"></i>
                                        <h5 class="text-muted mt-3">No Active Assignments</h5>
                                        <p class="text-muted">Assign officers to cases to see them here.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Case #</th>
                                                    <th>OB Number</th>
                                                    <th>Officer</th>
                                                    <th>Role</th>
                                                    <th>Assigned By</th>
                                                    <th>Date</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($caseAssignments as $assignment): ?>
                                                <tr>
                                                    <td><?php echo $assignment['caseid']; ?></td>
                                                    <td><?php echo htmlspecialchars($assignment['ob_number'] ?? 'N/A'); ?></td>
                                                    <td>
                                                        <?php echo htmlspecialchars($assignment['rank'] . ' ' . $assignment['first_name'] . ' ' . $assignment['last_name']); ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $assignment['role'] === 'Lead Investigator' ? 'primary' : 
                                                                 ($assignment['role'] === 'Supporting Officer' ? 'warning' : 'success'); 
                                                        ?>">
                                                            <?php echo $assignment['role']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($assignment['assigned_by']); ?></td>
                                                    <td><?php echo date('M j, Y', strtotime($assignment['assignment_date'])); ?></td>
                                                    <td>
                                                        <span class="badge bg-success">Active</span>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Upcoming Meetings Tab -->
                            <div class="tab-pane fade" id="upcoming" role="tabpanel">
                                <h5>Upcoming Case Meetings</h5>
                                <?php if (empty($upcomingMeetings)): ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-calendar-x display-4 text-muted"></i>
                                        <h5 class="text-muted mt-3">No Upcoming Meetings</h5>
                                        <p class="text-muted">Schedule meetings to see them here.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach($upcomingMeetings as $meeting): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="card meeting-card">
                                                <div class="card-body">
                                                    <h6 class="card-title">
                                                        <?php echo htmlspecialchars($meeting['meeting_title']); ?>
                                                        <span class="badge bg-info float-end">
                                                            <?php echo date('M j, g:i A', strtotime($meeting['meeting_date'])); ?>
                                                        </span>
                                                    </h6>
                                                    <p class="card-text mb-1">
                                                        <strong>Case:</strong> #<?php echo $meeting['caseid']; ?> 
                                                        (OB: <?php echo htmlspecialchars($meeting['ob_number']); ?>)
                                                    </p>
                                                    <p class="card-text mb-1">
                                                        <strong>Location:</strong> <?php echo htmlspecialchars($meeting['meeting_location']); ?>
                                                    </p>
                                                    <?php if ($meeting['agenda']): ?>
                                                    <p class="card-text mb-2">
                                                        <strong>Agenda:</strong> <?php echo htmlspecialchars(substr($meeting['agenda'], 0, 100)) . '...'; ?>
                                                    </p>
                                                    <?php endif; ?>
                                                    <p class="card-text mb-0">
                                                        <small class="text-muted">
                                                            Created by: <?php echo htmlspecialchars($meeting['creator_first'] . ' ' . $meeting['creator_last']); ?>
                                                        </small>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set minimum datetime for meeting scheduling (current time)
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.querySelector('input[type="datetime-local"]').min = now.toISOString().slice(0, 16);
        });
    </script>
</body>
</html>