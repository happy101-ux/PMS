<?php
session_start();

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['officerid'])) {
    header("Location: ../login.php");
    exit();
}

include '../config/database.php';
require_once '../RoleManager.php';

// Initialize RoleManager with user
$officerid = $_SESSION['officerid'];
$roleManager = new RoleManager($pdo, $officerid);

// Check if user has admin privileges
if (!$roleManager->isAdmin()) {
    header("Location: ../admin/dashboard.php");
    exit();
}

$userInfo = $roleManager->getUserInfo();

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'search_users':
            $search = $_GET['search'] ?? '';
            echo json_encode(searchUsers($pdo, $search));
            exit;
            
        case 'get_user_details':
            $userId = $_GET['user_id'] ?? '';
            echo json_encode(getUserDetails($pdo, $userId));
            exit;
            
        case 'update_user_status':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $userId = $_POST['user_id'] ?? '';
                $status = $_POST['status'] ?? '';
                $result = updateUserStatus($pdo, $userId, $status);
                echo json_encode(['success' => $result]);
                exit;
            }
            break;
            
        case 'get_user_analytics':
            echo json_encode(getUserAnalytics($pdo));
            exit;
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_user':
                $userId = $_POST['user_id'] ?? '';
                $firstName = trim($_POST['first_name'] ?? '');
                $lastName = trim($_POST['last_name'] ?? '');
                $rank = trim($_POST['rank'] ?? '');
                $designation = trim($_POST['designation'] ?? '');
                $gender = trim($_POST['gender'] ?? '');
                
                $success = updateUser($pdo, $userId, [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'rank' => $rank,
                    'designation' => $designation,
                    'gender' => $gender
                ]);
                
                $message = $success ? 'User updated successfully!' : 'Failed to update user.';
                break;
                
            case 'deactivate_user':
                $userId = $_POST['user_id'] ?? '';
                $success = deactivateUser($pdo, $userId);
                $message = $success ? 'User deactivated successfully!' : 'Failed to deactivate user.';
                break;
                
            case 'activate_user':
                $userId = $_POST['user_id'] ?? '';
                $success = activateUser($pdo, $userId);
                $message = $success ? 'User activated successfully!' : 'Failed to activate user.';
                break;
        }
    }
}

/**
 * User Management Functions
 */
function searchUsers($pdo, $search = '') {
    try {
        $sql = "SELECT officerid, rank, first_name, last_name, designation, gender, date_added, 
                       (SELECT COUNT(*) FROM case_table WHERE officerid = userlogin.officerid) as case_count,
                       (SELECT COUNT(*) FROM investigation WHERE investigator = userlogin.officerid) as investigation_count
                FROM userlogin 
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($search)) {
            $sql .= " AND (officerid LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR rank LIKE ? OR designation LIKE ?)";
            $searchTerm = "%$search%";
            $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
        }
        
        $sql .= " ORDER BY first_name, last_name LIMIT 100";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error searching users: " . $e->getMessage());
        return [];
    }
}

function getUserDetails($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT u.*, 
                              (SELECT COUNT(*) FROM case_table WHERE officerid = u.officerid) as total_cases,
                              (SELECT COUNT(*) FROM investigation WHERE investigator = u.officerid) as total_investigations,
                              (SELECT COUNT(*) FROM duties WHERE officerid = u.officerid) as total_duties,
                              (SELECT COUNT(*) FROM investigation WHERE investigator = u.officerid AND status2 = 'Completed') as completed_investigations
                              FROM userlogin u 
                              WHERE u.officerid = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting user details: " . $e->getMessage());
        return null;
    }
}

function updateUserStatus($pdo, $userId, $status) {
    try {
        $stmt = $pdo->prepare("UPDATE userlogin SET status = ? WHERE officerid = ?");
        return $stmt->execute([$status, $userId]);
    } catch (Exception $e) {
        error_log("Error updating user status: " . $e->getMessage());
        return false;
    }
}

function updateUser($pdo, $userId, $data) {
    try {
        $sql = "UPDATE userlogin SET 
                first_name = ?, 
                last_name = ?, 
                rank = ?, 
                designation = ?, 
                gender = ?
                WHERE officerid = ?";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['rank'],
            $data['designation'],
            $data['gender'],
            $userId
        ]);
    } catch (Exception $e) {
        error_log("Error updating user: " . $e->getMessage());
        return false;
    }
}

function deactivateUser($pdo, $userId) {
    return updateUserStatus($pdo, $userId, 'Inactive');
}

function activateUser($pdo, $userId) {
    return updateUserStatus($pdo, $userId, 'Active');
}

function getUserAnalytics($pdo) {
    try {
        $analytics = [];
        
        // Officers by rank
        $stmt = $pdo->query("SELECT rank, COUNT(*) as count FROM userlogin WHERE rank IS NOT NULL GROUP BY rank ORDER BY count DESC");
        $analytics['by_rank'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Officers by designation
        $stmt = $pdo->query("SELECT designation, COUNT(*) as count FROM userlogin WHERE designation IS NOT NULL GROUP BY designation ORDER BY count DESC");
        $analytics['by_designation'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Monthly registrations
        $stmt = $pdo->query("SELECT DATE_FORMAT(date_added, '%Y-%m') as month, COUNT(*) as registrations 
                            FROM userlogin 
                            WHERE date_added >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                            GROUP BY DATE_FORMAT(date_added, '%Y-%m')
                            ORDER BY month");
        $analytics['monthly_registrations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Active vs Inactive
        $stmt = $pdo->query("SELECT 
                            SUM(CASE WHEN status != 'Inactive' THEN 1 ELSE 0 END) as active,
                            SUM(CASE WHEN status = 'Inactive' THEN 1 ELSE 0 END) as inactive
                            FROM userlogin");
        $analytics['status_distribution'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $analytics;
    } catch (Exception $e) {
        error_log("Error getting user analytics: " . $e->getMessage());
        return [];
    }
}

// Get initial data
$users = searchUsers($pdo);
$userAnalytics = getUserAnalytics($pdo);
$message = $message ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Enhanced Admin Dashboard</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #3498db;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .admin-navbar {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            border-bottom: 3px solid #3498db;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .main-content {
            padding: 20px;
            margin-top: 20px;
        }

        .dashboard-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: none;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            font-weight: 600;
            border: none;
        }

        .user-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            background: white;
        }

        .user-card:hover {
            border-color: #3498db;
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.2);
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3498db, #2980b9);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .status-suspended { background: #fff3cd; color: #856404; }

        .search-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .stats-card {
            text-align: center;
            padding: 20px;
            border-left: 5px solid;
            height: 100%;
        }

        .stats-card.primary { border-left-color: var(--info-color); }
        .stats-card.success { border-left-color: var(--success-color); }
        .stats-card.warning { border-left-color: var(--warning-color); }
        .stats-card.danger { border-left-color: var(--danger-color); }

        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--primary-color);
        }

        .chart-container {
            position: relative;
            height: 300px;
            padding: 20px;
        }

        .modal-header {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }

        .btn-gradient {
            background: linear-gradient(135deg, #3498db, #2980b9);
            border: none;
            color: white;
        }

        .btn-gradient:hover {
            background: linear-gradient(135deg, #2980b9, #21618c);
            color: white;
        }

        .pagination-custom {
            justify-content: center;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 10px;
            }
            
            .user-card {
                padding: 10px;
            }
            
            .user-avatar {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark admin-navbar">
        <div class="container-fluid">
            <a class="navbar-brand" href="enhanced_dashboard.php">
                <i class="bi bi-shield-check me-2"></i>Police Management System
            </a>
            
            <div class="navbar-nav ms-auto">
                <a href="enhanced_dashboard.php" class="btn btn-outline-light me-2">
                    <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
                </a>
                <span class="navbar-text me-3">
                    <i class="bi bi-person-circle me-1"></i>
                    Admin: <?php echo htmlspecialchars($userInfo['first_name'] . ' ' . $userInfo['last_name']); ?>
                </span>
                <a href="../logout.php" class="btn btn-outline-light">
                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid main-content">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="bi bi-people me-2"></i>User Management
                        </h4>
                    </div>
                    <div class="p-3">
                        <p class="text-muted mb-0">Manage police officers, their roles, and system access</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="dashboard-card">
                    <div class="stats-card primary">
                        <div class="stats-number"><?php echo count($users); ?></div>
                        <div class="stats-label">Total Users</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="dashboard-card">
                    <div class="stats-card success">
                        <div class="stats-number"><?php echo $userAnalytics['status_distribution']['active'] ?? 0; ?></div>
                        <div class="stats-label">Active Officers</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="dashboard-card">
                    <div class="stats-card warning">
                        <div class="stats-number"><?php echo $userAnalytics['status_distribution']['inactive'] ?? 0; ?></div>
                        <div class="stats-label">Inactive Officers</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="dashboard-card">
                    <div class="stats-card danger">
                        <div class="stats-number"><?php echo count(array_filter($users, function($u) { return $u['case_count'] > 0; })); ?></div>
                        <div class="stats-label">Active Investigators</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="search-container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="searchInput" placeholder="Search by name, ID, rank, or designation...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                        <option value="Suspended">Suspended</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="rankFilter">
                        <option value="">All Ranks</option>
                        <option value="ADMIN">ADMIN</option>
                        <option value="Chief Inspector">Chief Inspector</option>
                        <option value="Inspector">Inspector</option>
                        <option value="Sergeant">Sergeant</option>
                        <option value="Constable">Constable</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- User List -->
            <div class="col-lg-8">
                <div class="dashboard-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-list-ul me-2"></i>User Directory</span>
                        <button class="btn btn-light btn-sm" onclick="refreshUserList()">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                    </div>
                    <div class="p-3">
                        <div id="userList">
                            <!-- Users will be loaded here -->
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary" role="status"></div>
                                <p class="mt-2 text-muted">Loading users...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analytics -->
            <div class="col-lg-4">
                <div class="dashboard-card mb-4">
                    <div class="card-header">
                        <i class="bi bi-graph-up me-2"></i>User Analytics
                    </div>
                    <div class="chart-container">
                        <canvas id="userAnalyticsChart"></canvas>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="card-header">
                        <i class="bi bi-calendar-check me-2"></i>Recent Registrations
                    </div>
                    <div class="p-3" id="recentRegistrations">
                        <?php foreach (array_slice($users, 0, 5) as $user): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                                <small class="text-muted"><?php echo date('M j', strtotime($user['date_added'])); ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Details Modal -->
    <div class="modal fade" id="userDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-person-circle me-2"></i>User Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="userDetailsContent">
                    <!-- User details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="editUser()">
                        <i class="bi bi-pencil me-1"></i>Edit User
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil me-2"></i>Edit User
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="editUserForm">
                    <div class="modal-body">
                        <input type="hidden" id="editUserId" name="user_id">
                        <input type="hidden" name="action" value="update_user">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="editFirstName" name="first_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="editLastName" name="last_name" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Rank</label>
                                    <select class="form-select" id="editRank" name="rank" required>
                                        <option value="">Select Rank</option>
                                        <option value="ADMIN">ADMIN</option>
                                        <option value="Chief Inspector">Chief Inspector</option>
                                        <option value="Inspector">Inspector</option>
                                        <option value="Sergeant">Sergeant</option>
                                        <option value="Constable">Constable</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Designation</label>
                                    <select class="form-select" id="editDesignation" name="designation" required>
                                        <option value="">Select Designation</option>
                                        <option value="General Duties">General Duties</option>
                                        <option value="CID">CID</option>
                                        <option value="Traffic">Traffic</option>
                                        <option value="Training">Training</option>
                                        <option value="NCO">NCO</option>
                                        <option value="ADMIN">ADMIN</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Gender</label>
                            <select class="form-select" id="editGender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-gradient">
                            <i class="bi bi-check me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"></script>
    
    <script>
        let usersData = <?php echo json_encode($users); ?>;
        let userAnalyticsData = <?php echo json_encode($userAnalytics); ?>;
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            loadUsers();
            initializeCharts();
            
            // Event listeners
            document.getElementById('searchInput').addEventListener('input', filterUsers);
            document.getElementById('statusFilter').addEventListener('change', filterUsers);
            document.getElementById('rankFilter').addEventListener('change', filterUsers);
            
            // Form submission
            document.getElementById('editUserForm').addEventListener('submit', handleEditUser);
        });
        
        function loadUsers() {
            // Load users data
            displayUsers(usersData);
        }
        
        function displayUsers(users) {
            const userList = document.getElementById('userList');
            
            if (users.length === 0) {
                userList.innerHTML = `
                    <div class="text-center py-4">
                        <i class="bi bi-inbox display-4 text-muted"></i>
                        <p class="mt-2 text-muted">No users found</p>
                    </div>
                `;
                return;
            }
            
            userList.innerHTML = users.map(user => `
                <div class="user-card">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div class="user-avatar">
                                ${user.first_name.charAt(0)}${user.last_name.charAt(0)}
                            </div>
                        </div>
                        <div class="col">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">${user.first_name} ${user.last_name}</h6>
                                    <p class="text-muted mb-1">ID: ${user.officerid} | ${user.rank}</p>
                                    <small class="text-muted">${user.designation || 'General Duties'}</small>
                                </div>
                                <div class="text-end">
                                    <span class="status-badge status-${user.status ? user.status.toLowerCase() : 'active'}">
                                        ${user.status || 'Active'}
                                    </span>
                                    <div class="mt-2">
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewUserDetails('${user.officerid}')">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="editUserModal('${user.officerid}')">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        ${user.status !== 'Inactive' ? 
                                            `<button class="btn btn-sm btn-outline-danger" onclick="deactivateUser('${user.officerid}')">
                                                <i class="bi bi-pause"></i>
                                            </button>` : 
                                            `<button class="btn btn-sm btn-outline-success" onclick="activateUser('${user.officerid}')">
                                                <i class="bi bi-play"></i>
                                            </button>`
                                        }
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-6">
                                    <small class="text-muted">
                                        <i class="bi bi-folder me-1"></i>
                                        ${user.case_count} cases
                                    </small>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">
                                        <i class="bi bi-search me-1"></i>
                                        ${user.investigation_count} investigations
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        }
        
        function filterUsers() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const rankFilter = document.getElementById('rankFilter').value;
            
            let filtered = usersData.filter(user => {
                const matchesSearch = !searchTerm || 
                    user.officerid.toLowerCase().includes(searchTerm) ||
                    user.first_name.toLowerCase().includes(searchTerm) ||
                    user.last_name.toLowerCase().includes(searchTerm) ||
                    user.rank.toLowerCase().includes(searchTerm) ||
                    (user.designation || '').toLowerCase().includes(searchTerm);
                
                const matchesStatus = !statusFilter || user.status === statusFilter;
                const matchesRank = !rankFilter || user.rank === rankFilter;
                
                return matchesSearch && matchesStatus && matchesRank;
            });
            
            displayUsers(filtered);
        }
        
        async function viewUserDetails(userId) {
            try {
                const response = await fetch(`?action=get_user_details&user_id=${userId}`);
                const user = await response.json();
                
                if (!user) {
                    alert('User not found');
                    return;
                }
                
                const content = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Personal Information</h6>
                            <table class="table table-sm">
                                <tr><td><strong>Officer ID:</strong></td><td>${user.officerid}</td></tr>
                                <tr><td><strong>Full Name:</strong></td><td>${user.first_name} ${user.last_name}</td></tr>
                                <tr><td><strong>Rank:</strong></td><td>${user.rank}</td></tr>
                                <tr><td><strong>Designation:</strong></td><td>${user.designation || 'General Duties'}</td></tr>
                                <tr><td><strong>Gender:</strong></td><td>${user.gender || 'Not specified'}</td></tr>
                                <tr><td><strong>Status:</strong></td><td><span class="status-badge status-${user.status.toLowerCase()}">${user.status}</span></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Performance Statistics</h6>
                            <table class="table table-sm">
                                <tr><td><strong>Total Cases:</strong></td><td>${user.total_cases || 0}</td></tr>
                                <tr><td><strong>Total Investigations:</strong></td><td>user.total_investigations || 0</td></tr>
                                <tr><td><strong>Completed Investigations:</strong></td><td>${user.completed_investigations || 0}</td></tr>
                                <tr><td><strong>Total Duties:</strong></td><td>${user.total_duties || 0}</td></tr>
                                <tr><td><strong>Date Registered:</strong></td><td>${new Date(user.date_added).toLocaleDateString()}</td></tr>
                            </table>
                        </div>
                    </div>
                `;
                
                document.getElementById('userDetailsContent').innerHTML = content;
                new bootstrap.Modal(document.getElementById('userDetailsModal')).show();
            } catch (error) {
                console.error('Error loading user details:', error);
                alert('Error loading user details');
            }
        }
        
        function editUserModal(userId) {
            const user = usersData.find(u => u.officerid === userId);
            if (!user) return;
            
            document.getElementById('editUserId').value = user.officerid;
            document.getElementById('editFirstName').value = user.first_name;
            document.getElementById('editLastName').value = user.last_name;
            document.getElementById('editRank').value = user.rank;
            document.getElementById('editDesignation').value = user.designation || '';
            document.getElementById('editGender').value = user.gender || '';
            
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }
        
        async function handleEditUser(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.text();
                alert('User updated successfully!');
                location.reload();
            } catch (error) {
                console.error('Error updating user:', error);
                alert('Error updating user');
            }
        }
        
        async function deactivateUser(userId) {
            if (!confirm('Are you sure you want to deactivate this user?')) return;
            
            try {
                const formData = new FormData();
                formData.append('action', 'deactivate_user');
                formData.append('user_id', userId);
                
                await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                location.reload();
            } catch (error) {
                console.error('Error deactivating user:', error);
                alert('Error deactivating user');
            }
        }
        
        async function activateUser(userId) {
            try {
                const formData = new FormData();
                formData.append('action', 'activate_user');
                formData.append('user_id', userId);
                
                await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                location.reload();
            } catch (error) {
                console.error('Error activating user:', error);
                alert('Error activating user');
            }
        }
        
        function refreshUserList() {
            location.reload();
        }
        
        function initializeCharts() {
            // User Analytics Chart
            const ctx = document.getElementById('userAnalyticsChart').getContext('2d');
            
            if (userAnalyticsData.by_rank) {
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: userAnalyticsData.by_rank.map(item => item.rank),
                        datasets: [{
                            data: userAnalyticsData.by_rank.map(item => item.count),
                            backgroundColor: ['#3498db', '#27ae60', '#f39c12', '#e74c3c', '#9b59b6']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        }
    </script>
</body>
</html>