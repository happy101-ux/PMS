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

// Get comprehensive admin dashboard data
$adminData = getAdminDashboardData($pdo);

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_system_stats':
            echo json_encode(getSystemStats($pdo));
            exit;
            
        case 'get_recent_activity':
            echo json_encode(getRecentActivity($pdo));
            exit;
            
        case 'get_user_analytics':
            echo json_encode(getUserAnalytics($pdo));
            exit;
            
        case 'get_case_analytics':
            echo json_encode(getCaseAnalytics($pdo));
            exit;
    }
}

/**
 * Get comprehensive admin dashboard data
 */
function getAdminDashboardData($pdo) {
    $data = [];
    
    try {
        // System Overview Statistics
        $data['system_stats'] = [
            'total_officers' => getTotalOfficers($pdo),
            'active_cases' => getActiveCases($pdo),
            'pending_complaints' => getPendingComplaints($pdo),
            'completed_investigations' => getCompletedInvestigations($pdo),
            'total_resources' => getTotalResources($pdo),
            'today_duties' => getTodayDuties($pdo)
        ];
        
        // User Management Statistics
        $data['user_stats'] = [
            'officers_by_rank' => getOfficersByRank($pdo),
            'officers_by_designation' => getOfficersByDesignation($pdo),
            'recent_registrations' => getRecentRegistrations($pdo),
            'inactive_officers' => getInactiveOfficers($pdo)
        ];
        
        // Case Management Statistics
        $data['case_stats'] = [
            'cases_by_status' => getCasesByStatus($pdo),
            'cases_by_type' => getCasesByType($pdo),
            'investigation_performance' => getInvestigationPerformance($pdo),
            'monthly_case_trends' => getMonthlyCaseTrends($pdo)
        ];
        
        // Performance Metrics
        $data['performance'] = [
            'resolution_rate' => calculateResolutionRate($pdo),
            'average_case_duration' => getAverageCaseDuration($pdo),
            'officer_workload' => getOfficerWorkload($pdo),
            'system_utilization' => getSystemUtilization($pdo)
        ];
        
        // System Health
        $data['system_health'] = [
            'database_size' => getDatabaseSize($pdo),
            'active_sessions' => getActiveSessions(),
            'system_alerts' => getSystemAlerts(),
            'recent_backups' => getRecentBackups($pdo)
        ];
        
        return $data;
        
    } catch (Exception $e) {
        error_log("Error getting admin dashboard data: " . $e->getMessage());
        return [];
    }
}

/**
 * System Statistics Functions
 */
function getTotalOfficers($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM userlogin WHERE status != 'Inactive'");
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
}

function getActiveCases($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM case_table WHERE status IN ('Active', 'Pending')");
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
}

function getPendingComplaints($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM complaints WHERE complaint_status = 'Waiting for Action'");
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
}

function getCompletedInvestigations($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM investigation WHERE status2 = 'Completed'");
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
}

function getTotalResources($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM resources");
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
}

function getTodayDuties($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM duties WHERE dutydate = CURDATE()");
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
}

/**
 * User Management Functions
 */
function getOfficersByRank($pdo) {
    $stmt = $pdo->query("SELECT status as rank, COUNT(*) as count FROM userlogin WHERE status != 'Inactive' GROUP BY status ORDER BY count DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getOfficersByDesignation($pdo) {
    $stmt = $pdo->query("SELECT designation, COUNT(*) as count FROM userlogin WHERE designation IS NOT NULL GROUP BY designation ORDER BY count DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRecentRegistrations($pdo) {
    $stmt = $pdo->query("SELECT officerid, status, first_name, last_name, date_added FROM userlogin ORDER BY date_added DESC LIMIT 10");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getInactiveOfficers($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM userlogin WHERE status = 'Inactive'");
    return $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
}

/**
 * Case Management Functions
 */
function getCasesByStatus($pdo) {
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM case_table GROUP BY status ORDER BY count DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCasesByType($pdo) {
    $stmt = $pdo->query("SELECT casetype, COUNT(*) as count FROM case_table GROUP BY casetype ORDER BY count DESC LIMIT 10");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getInvestigationPerformance($pdo) {
    $stmt = $pdo->query("SELECT 
        status2, 
        COUNT(*) as count,
        AVG(DATEDIFF(COALESCE(completed_date, NOW()), assigned_date)) as avg_duration
        FROM investigation 
        GROUP BY status2 
        ORDER BY count DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getMonthlyCaseTrends($pdo) {
    $stmt = $pdo->query("SELECT 
        DATE_FORMAT(assigned_date, '%Y-%m') as month,
        COUNT(*) as cases_created
        FROM investigation 
        WHERE assigned_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(assigned_date, '%Y-%m')
        ORDER BY month DESC
        LIMIT 12");
    return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
}

/**
 * Performance Metrics Functions
 */
function calculateResolutionRate($pdo) {
    $stmt = $pdo->query("SELECT 
        (SELECT COUNT(*) FROM case_table WHERE status = 'Closed') as closed_cases,
        (SELECT COUNT(*) FROM case_table) as total_cases");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['total_cases'] > 0 ? round(($result['closed_cases'] / $result['total_cases']) * 100, 1) : 0;
}

function getAverageCaseDuration($pdo) {
    $stmt = $pdo->query("SELECT AVG(DATEDIFF(COALESCE(completed_date, NOW()), assigned_date)) as avg_duration 
                        FROM investigation 
                        WHERE completed_date IS NOT NULL");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return round($result['avg_duration'] ?? 0, 1);
}

function getOfficerWorkload($pdo) {
    $stmt = $pdo->query("SELECT 
        u.officerid,
        u.first_name,
        u.last_name,
        u.status,
        COUNT(i.id) as active_investigations
        FROM userlogin u
        LEFT JOIN investigation i ON u.officerid = i.investigator AND i.status2 = 'Under Investigation'
        GROUP BY u.officerid, u.first_name, u.last_name, u.status
        HAVING active_investigations > 0
        ORDER BY active_investigations DESC
        LIMIT 10");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSystemUtilization($pdo) {
    // Simple utilization metrics based on active cases vs total officers
    $totalOfficers = getTotalOfficers($pdo);
    $activeCases = getActiveCases($pdo);
    return $totalOfficers > 0 ? round(($activeCases / $totalOfficers) * 100, 1) : 0;
}

/**
 * System Health Functions
 */
function getDatabaseSize($pdo) {
    try {
        $stmt = $pdo->query("SELECT 
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS size_mb
            FROM information_schema.TABLES 
            WHERE table_schema = 'pms'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['size_mb'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

function getActiveSessions() {
    // In a real system, this would track actual sessions
    return rand(5, 15); // Placeholder
}

function getSystemAlerts() {
    // Placeholder for system alerts
    return [
        ['type' => 'info', 'message' => 'System running normally', 'time' => date('Y-m-d H:i:s')],
        ['type' => 'warning', 'message' => '3 inactive officers detected', 'time' => date('Y-m-d H:i:s', strtotime('-1 hour'))]
    ];
}

function getRecentBackups($pdo) {
    // Placeholder for backup information
    return [
        ['date' => date('Y-m-d H:i:s', strtotime('-1 day')), 'size' => '2.3 MB', 'status' => 'Success'],
        ['date' => date('Y-m-d H:i:s', strtotime('-2 days')), 'size' => '2.1 MB', 'status' => 'Success']
    ];
}

/**
 * AJAX Helper Functions
 */
function getSystemStats($pdo) {
    return [
        'total_officers' => getTotalOfficers($pdo),
        'active_cases' => getActiveCases($pdo),
        'pending_complaints' => getPendingComplaints($pdo),
        'resolution_rate' => calculateResolutionRate($pdo)
    ];
}

function getRecentActivity($pdo) {
    $stmt = $pdo->query("SELECT 
        'user_registration' as type,
        CONCAT('New officer registered: ', first_name, ' ', last_name) as activity,
        date_added as timestamp
        FROM userlogin 
        WHERE date_added >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        UNION ALL
        SELECT 
        'case_created' as type,
        CONCAT('New case created: ', casetype) as activity,
        NOW() as timestamp
        FROM case_table 
        WHERE DATE(created_at) = CURDATE()
        ORDER BY timestamp DESC
        LIMIT 10");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserAnalytics($pdo) {
    return [
        'officers_by_rank' => getOfficersByRank($pdo),
        'officers_by_designation' => getOfficersByDesignation($pdo),
        'recent_registrations' => getRecentRegistrations($pdo)
    ];
}

function getCaseAnalytics($pdo) {
    return [
        'cases_by_status' => getCasesByStatus($pdo),
        'cases_by_type' => getCasesByType($pdo),
        'monthly_trends' => getMonthlyCaseTrends($pdo)
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Admin Dashboard - Police Management System</title>
    
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
            --light-bg: #ecf0f1;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
            box-shadow: var(--card-shadow);
        }

        .main-content {
            padding: 20px;
            margin-top: 20px;
        }

        .dashboard-card {
            background: white;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            border: none;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            font-weight: 600;
            border: none;
            padding: 15px 20px;
        }

        .stats-card {
            text-align: center;
            padding: 20px;
            border-left: 5px solid;
        }

        .stats-card.primary { border-left-color: var(--info-color); }
        .stats-card.success { border-left-color: var(--success-color); }
        .stats-card.warning { border-left-color: var(--warning-color); }
        .stats-card.danger { border-left-color: var(--danger-color); }

        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--primary-color);
        }

        .stats-label {
            font-size: 0.9rem;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .chart-container {
            position: relative;
            height: 300px;
            padding: 20px;
        }

        .activity-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s ease;
        }

        .activity-item:hover {
            background-color: #f8f9fa;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }

        .user-workload-item {
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.2s ease;
        }

        .user-workload-item:hover {
            background-color: #f8f9fa;
            border-color: #3498db;
        }

        .progress-bar-custom {
            height: 8px;
            border-radius: 4px;
            background: #e9ecef;
        }

        .admin-sidebar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            box-shadow: var(--card-shadow);
        }

        .nav-pills .nav-link {
            border-radius: 10px;
            margin-bottom: 5px;
            transition: all 0.3s ease;
        }

        .nav-pills .nav-link:hover {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }

        .alert-custom {
            border: none;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .loading-spinner {
            display: none;
            text-align: center;
            padding: 20px;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 10px;
            }
            
            .stats-number {
                font-size: 2rem;
            }
            
            .chart-container {
                height: 250px;
            }
        }

        .quick-actions {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .quick-action-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 10px;
            padding: 10px 15px;
            margin: 5px;
            transition: all 0.3s ease;
        }

        .quick-action-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            transform: translateY(-2px);
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .content-section {
            display: none;
        }

        .content-section.active {
            display: block;
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .quick-action-btn {
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .quick-action-btn.active {
            background: rgba(255, 255, 255, 0.4);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
<?php include '../components/navbar.php'; ?>
    <div class="container-fluid main-content">
        <div class="row">
            <!-- Admin Sidebar -->
            <div class="col-lg-2 col-md-3 mb-4">
                <div class="admin-sidebar">
                    <h6 class="text-muted mb-3">Admin Navigation</h6>
                    <ul class="nav nav-pills flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="#overview" data-bs-toggle="pill">
                                <i class="bi bi-speedometer2 me-2"></i>Overview
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#users" data-bs-toggle="pill">
                                <i class="bi bi-people me-2"></i>User Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#cases" data-bs-toggle="pill">
                                <i class="bi bi-folder me-2"></i>Case Analytics
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#reports" data-bs-toggle="pill">
                                <i class="bi bi-file-earmark-text me-2"></i>Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#system" data-bs-toggle="pill">
                                <i class="bi bi-gear me-2"></i>System Health
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Dashboard Content -->
            <div class="col-lg-10 col-md-9">
                <div class="tab-content">
                    <!-- Overview Tab -->
                    <div class="tab-pane fade show active" id="overview">
                        <!-- Enhanced Quick Actions Navigation -->
                        <div class="quick-actions mb-4">
                            <h5><i class="bi bi-lightning me-2"></i>Quick Actions</h5>
                            <div class="mt-3">
                                <button onclick="loadContent('add_staff')" class="quick-action-btn">
                                    <i class="bi bi-person-plus me-1"></i>Add Officer
                                </button>
                                <button onclick="loadContent('view_officer')" class="quick-action-btn">
                                    <i class="bi bi-people me-1"></i>Manage Users
                                </button>
                                <button onclick="loadContent('resource_management')" class="quick-action-btn">
                                    <i class="bi bi-folder me-1"></i>Resources
                                </button>
                                <button onclick="loadContent('oic_dashboard')" class="quick-action-btn">
                                    <i class="bi bi-clipboard-check me-1"></i>Case Overview
                                </button>
                                <button onclick="loadContent('case_allocation')" class="quick-action-btn">
                                    <i class="bi bi-shuffle me-1"></i>Case Allocation
                                </button>
                                <button onclick="loadContent('duty_management')" class="quick-action-btn">
                                    <i class="bi bi-calendar-check me-1"></i>Duty Management
                                </button>
                                <button onclick="loadContent('cid_dashboard')" class="quick-action-btn">
                                    <i class="bi bi-shield-check me-1"></i>CID Dashboard
                                </button>
                                <button onclick="loadContent('reports')" class="quick-action-btn">
                                    <i class="bi bi-file-earmark-text me-1"></i>Reports
                                </button>
                                <button onclick="loadContent('system_stats')" class="quick-action-btn">
                                    <i class="bi bi-graph-up me-1"></i>System Stats
                                </button>
                                <button onclick="loadContent('user_analytics')" class="quick-action-btn">
                                    <i class="bi bi-pie-chart me-1"></i>User Analytics
                                </button>
                            </div>
                        </div>

                        <!-- Dynamic Content Display Area -->
                        <div id="dynamic-content" class="dashboard-card">
                            <div class="card-header">
                                <i class="bi bi-speedometer2 me-2"></i>Dashboard Overview
                            </div>
                            <div class="p-4">
                                <div class="row">
                                    <div class="col-lg-3 col-md-6 mb-3">
                                        <div class="stats-card primary">
                                            <div class="stats-number"><?php echo $adminData['system_stats']['total_officers']; ?></div>
                                            <div class="stats-label">Total Officers</div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6 mb-3">
                                        <div class="stats-card success">
                                            <div class="stats-number"><?php echo $adminData['system_stats']['active_cases']; ?></div>
                                            <div class="stats-label">Active Cases</div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6 mb-3">
                                        <div class="stats-card warning">
                                            <div class="stats-number"><?php echo $adminData['system_stats']['pending_complaints']; ?></div>
                                            <div class="stats-label">Pending Complaints</div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3 col-md-6 mb-3">
                                        <div class="stats-card danger">
                                            <div class="stats-number"><?php echo $adminData['performance']['resolution_rate']; ?>%</div>
                                            <div class="stats-label">Resolution Rate</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-center mt-4">
                                    <h6 class="text-muted">Welcome to the Police Management System</h6>
                                    <p class="text-muted">Select an action above to manage your system components</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay" style="display: none;">
        <div class="spinner-border text-light" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Dynamic content loading system
        document.addEventListener('DOMContentLoaded', function() {
            // Remove any existing event listeners and initialize
            initializeDynamicNavigation();
        });

        function initializeDynamicNavigation() {
            // Add click handlers to all quick action buttons
            document.querySelectorAll('.quick-action-btn').forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    document.querySelectorAll('.quick-action-btn').forEach(btn => btn.classList.remove('active'));
                    // Add active class to clicked button
                    this.classList.add('active');
                });
            });
        }

        // Main content loading function
        async function loadContent(component) {
            showLoading();
            
            try {
                const contentArea = document.getElementById('dynamic-content');
                let content = '';
                
                switch(component) {
                    case 'add_staff':
                        content = await loadAddStaff();
                        break;
                    case 'view_officer':
                        content = await loadViewOfficer();
                        break;
                    case 'resource_management':
                        content = await loadResourceManagement();
                        break;
                    case 'oic_dashboard':
                        content = await loadOicDashboard();
                        break;
                    case 'case_allocation':
                        content = await loadCaseAllocation();
                        break;
                    case 'duty_management':
                        content = await loadDutyManagement();
                        break;
                    case 'cid_dashboard':
                        content = await loadCidDashboard();
                        break;
                    case 'reports':
                        content = await loadReports();
                        break;
                    case 'system_stats':
                        content = await loadSystemStats();
                        break;
                    case 'user_analytics':
                        content = await loadUserAnalytics();
                        break;
                    default:
                        content = getDefaultContent();
                }
                
                contentArea.innerHTML = content;
                contentArea.querySelector('.card-header').innerHTML = getHeaderContent(component);
                
            } catch (error) {
                console.error('Error loading content:', error);
                showError('Failed to load content. Please try again.');
            } finally {
                hideLoading();
            }
        }

        // Content loading functions
        async function loadAddStaff() {
            return `
                <div class="p-4">
                    <h6><i class="bi bi-person-plus me-2"></i>Add New Officer</h6>
                    <p class="text-muted">Redirect to officer registration form</p>
                    <a href="../components/add_staff.php" class="btn btn-primary">
                        <i class="bi bi-arrow-right me-1"></i>Open Add Staff Form
                    </a>
                </div>
            `;
        }

        async function loadViewOfficer() {
            return `
                <div class="p-4">
                    <h6><i class="bi bi-people me-2"></i>Manage Users</h6>
                    <p class="text-muted">View and manage all system users</p>
                    <a href="../components/view_officer.php" class="btn btn-primary">
                        <i class="bi bi-arrow-right me-1"></i>Open User Management
                    </a>
                </div>
            `;
        }

        async function loadResourceManagement() {
            return `
                <div class="p-4">
                    <h6><i class="bi bi-folder me-2"></i>Resource Management</h6>
                    <p class="text-muted">Manage system resources and assets</p>
                    <a href="../components/resource_management.php" class="btn btn-primary">
                        <i class="bi bi-arrow-right me-1"></i>Open Resource Management
                    </a>
                </div>
            `;
        }

        async function loadOicDashboard() {
            return `
                <div class="p-4">
                    <h6><i class="bi bi-clipboard-check me-2"></i>Case Overview</h6>
                    <p class="text-muted">Monitor case status and investigation progress</p>
                    <a href="../components/oic_dashboard.php" class="btn btn-primary">
                        <i class="bi bi-arrow-right me-1"></i>Open Case Dashboard
                    </a>
                </div>
            `;
        }

        async function loadCaseAllocation() {
            return `
                <div class="p-4">
                    <h6><i class="bi bi-shuffle me-2"></i>Case Allocation</h6>
                    <p class="text-muted">Assign cases to investigating officers</p>
                    <a href="../components/case_allocation.php" class="btn btn-primary">
                        <i class="bi bi-arrow-right me-1"></i>Open Case Allocation
                    </a>
                </div>
            `;
        }

        async function loadDutyManagement() {
            return `
                <div class="p-4">
                    <h6><i class="bi bi-calendar-check me-2"></i>Duty Management</h6>
                    <p class="text-muted">Schedule and manage officer duties</p>
                    <a href="../components/duty_management.php" class="btn btn-primary">
                        <i class="bi bi-arrow-right me-1"></i>Open Duty Management
                    </a>
                </div>
            `;
        }

        async function loadCidDashboard() {
            return `
                <div class="p-4">
                    <h6><i class="bi bi-shield-check me-2"></i>CID Dashboard</h6>
                    <p class="text-muted">Criminal Investigation Department overview</p>
                    <a href="../components/cid_dashboard.php" class="btn btn-primary">
                        <i class="bi bi-arrow-right me-1"></i>Open CID Dashboard
                    </a>
                </div>
            `;
        }

        async function loadReports() {
            return `
                <div class="p-4">
                    <h6><i class="bi bi-file-earmark-text me-2"></i>System Reports</h6>
                    <div class="row mt-3">
                        <div class="col-md-4 mb-3">
                            <div class="text-center p-3 border rounded">
                                <i class="bi bi-file-pdf text-danger" style="font-size: 2rem;"></i>
                                <h6 class="mt-2">Monthly Case Report</h6>
                                <button class="btn btn-sm btn-outline-primary" onclick="generateReport('monthly_cases')">Generate</button>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="text-center p-3 border rounded">
                                <i class="bi bi-file-excel text-success" style="font-size: 2rem;"></i>
                                <h6 class="mt-2">User Activity Report</h6>
                                <button class="btn btn-sm btn-outline-success" onclick="generateReport('user_activity')">Generate</button>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="text-center p-3 border rounded">
                                <i class="bi bi-file-word text-primary" style="font-size: 2rem;"></i>
                                <h6 class="mt-2">Performance Summary</h6>
                                <button class="btn btn-sm btn-outline-info" onclick="generateReport('performance')">Generate</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        async function loadSystemStats() {
            return `
                <div class="p-4">
                    <h6><i class="bi bi-graph-up me-2"></i>System Statistics</h6>
                    <div class="row mt-3">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="stats-card primary">
                                <div class="stats-number"><?php echo $adminData['system_stats']['total_officers']; ?></div>
                                <div class="stats-label">Total Officers</div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="stats-card success">
                                <div class="stats-number"><?php echo $adminData['system_stats']['active_cases']; ?></div>
                                <div class="stats-label">Active Cases</div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="stats-card warning">
                                <div class="stats-number"><?php echo $adminData['system_stats']['pending_complaints']; ?></div>
                                <div class="stats-label">Pending Complaints</div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="stats-card danger">
                                <div class="stats-number"><?php echo $adminData['performance']['resolution_rate']; ?>%</div>
                                <div class="stats-label">Resolution Rate</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        async function loadUserAnalytics() {
            return `
                <div class="p-4">
                    <h6><i class="bi bi-pie-chart me-2"></i>User Analytics</h6>
                    <div class="row mt-3">
                        <div class="col-lg-4 mb-3">
                            <div class="stats-card primary">
                                <div class="stats-number"><?php echo $adminData['system_stats']['total_officers']; ?></div>
                                <div class="stats-label">Active Officers</div>
                            </div>
                        </div>
                        <div class="col-lg-4 mb-3">
                            <div class="stats-card success">
                                <div class="stats-number"><?php echo count($adminData['user_stats']['officers_by_rank']); ?></div>
                                <div class="stats-label">Ranks Represented</div>
                            </div>
                        </div>
                        <div class="col-lg-4 mb-3">
                            <div class="stats-card warning">
                                <div class="stats-number"><?php echo $adminData['user_stats']['inactive_officers']; ?></div>
                                <div class="stats-label">Inactive Officers</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        function getDefaultContent() {
            return `
                <div class="p-4">
                    <div class="text-center">
                        <h6 class="text-muted">Welcome to the Police Management System</h6>
                        <p class="text-muted">Select an action above to manage your system components</p>
                    </div>
                </div>
            `;
        }

        function getHeaderContent(component) {
            const headers = {
                'add_staff': '<i class="bi bi-person-plus me-2"></i>Add New Officer',
                'view_officer': '<i class="bi bi-people me-2"></i>User Management',
                'resource_management': '<i class="bi bi-folder me-2"></i>Resource Management',
                'oic_dashboard': '<i class="bi bi-clipboard-check me-2"></i>Case Overview',
                'case_allocation': '<i class="bi bi-shuffle me-2"></i>Case Allocation',
                'duty_management': '<i class="bi bi-calendar-check me-2"></i>Duty Management',
                'cid_dashboard': '<i class="bi bi-shield-check me-2"></i>CID Dashboard',
                'reports': '<i class="bi bi-file-earmark-text me-2"></i>System Reports',
                'system_stats': '<i class="bi bi-graph-up me-2"></i>System Statistics',
                'user_analytics': '<i class="bi bi-pie-chart me-2"></i>User Analytics'
            };
            return headers[component] || '<i class="bi bi-speedometer2 me-2"></i>Dashboard Overview';
        }

        function generateReport(type) {
            alert('Generating ' + type + ' report... This feature will be implemented soon.');
        }

        function showLoading() {
            document.getElementById('loading-overlay').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loading-overlay').style.display = 'none';
        }

        function showError(message) {
            const contentArea = document.getElementById('dynamic-content');
            contentArea.innerHTML = `
                <div class="p-4">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>${message}
                    </div>
                </div>
            `;
        }
    </script>
</body>
</html>