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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #7e22ce 50%, #9333ea 75%, #3b82f6 100%);
            background-size: 400% 400%;
            animation: gradientShift 20s ease infinite;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        body::before {
            content: '';
            position: fixed;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(255, 255, 255, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
            animation: float 25s ease-in-out infinite;
            pointer-events: none;
            z-index: 0;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-30px) rotate(5deg); }
        }

        .admin-navbar {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            border-bottom: 3px solid #3498db;
            box-shadow: var(--card-shadow);
            position: relative;
            z-index: 1000;
        }

        .main-content {
            padding: 20px;
            margin-top: 20px;
            position: relative;
            z-index: 1;
        }

        .dashboard-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
            animation: slideInUp 0.6s ease-out;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dashboard-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
        }

        .card-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-weight: 600;
            border: none;
            padding: 20px 25px;
            position: relative;
            overflow: hidden;
        }

        .card-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: rotate 15s linear infinite;
            pointer-events: none;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .stats-card {
            text-align: center;
            padding: 25px 20px;
            border-left: 5px solid;
            border-radius: 15px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            animation: fadeInScale 0.6s ease-out both;
        }

        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .stats-card:hover::before {
            left: 100%;
        }

        .stats-card.primary { border-left-color: var(--info-color); }
        .stats-card.success { border-left-color: var(--success-color); }
        .stats-card.warning { border-left-color: var(--warning-color); }
        .stats-card.danger { border-left-color: var(--danger-color); }

        .stats-card:hover {
            transform: translateY(-8px) scale(1.05);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.2);
        }

        .stats-card:nth-child(1) { animation-delay: 0.1s; }
        .stats-card:nth-child(2) { animation-delay: 0.2s; }
        .stats-card:nth-child(3) { animation-delay: 0.3s; }
        .stats-card:nth-child(4) { animation-delay: 0.4s; }

        .stats-number {
            font-size: 2.8rem;
            font-weight: 800;
            margin-bottom: 8px;
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: countUp 1s ease-out;
        }

        @keyframes countUp {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.5);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .stats-label {
            font-size: 0.95rem;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .chart-container {
            position: relative;
            height: 300px;
            padding: 20px;
        }

        .activity-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: all 0.3s ease;
            animation: fadeInRow 0.5s ease-out both;
        }

        .activity-item:nth-child(1) { animation-delay: 0.1s; }
        .activity-item:nth-child(2) { animation-delay: 0.2s; }
        .activity-item:nth-child(3) { animation-delay: 0.3s; }

        @keyframes fadeInRow {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .activity-item:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .activity-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            transition: all 0.3s ease;
        }

        .activity-item:hover .activity-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .user-workload-item {
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            margin-bottom: 12px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        .user-workload-item:hover {
            background-color: #f8f9fa;
            border-color: #3498db;
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .progress-bar-custom {
            height: 10px;
            border-radius: 5px;
            background: #e9ecef;
            overflow: hidden;
        }

        .admin-sidebar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: slideInLeft 0.6s ease-out;
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .nav-pills .nav-link {
            border-radius: 12px;
            margin-bottom: 8px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 12px 15px;
            position: relative;
            overflow: hidden;
        }

        .nav-pills .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .nav-pills .nav-link:hover::before {
            left: 100%;
        }

        .nav-pills .nav-link:hover {
            background-color: #e3f2fd;
            color: #1976d2;
            transform: translateX(5px);
        }

        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .alert-custom {
            border: none;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            backdrop-filter: blur(10px);
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
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            color: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            animation: slideInDown 0.6s ease-out;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .quick-action-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 12px;
            padding: 12px 18px;
            margin: 6px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            white-space: nowrap;
            position: relative;
            overflow: hidden;
            font-weight: 500;
        }

        .quick-action-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .quick-action-btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .quick-action-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            transform: translateY(-4px) scale(1.05);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .quick-action-btn.active {
            background: rgba(255, 255, 255, 0.4);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            transform: scale(1.05);
        }

        .quick-action-btn i {
            margin-right: 6px;
            transition: transform 0.3s ease;
        }

        .quick-action-btn:hover i {
            transform: scale(1.2) rotate(10deg);
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
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
            animation: fadeIn 0.4s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .btn {
            transition: all 0.3s ease;
            border-radius: 10px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
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
            animateStats();
            addParallaxEffect();
            addRippleEffects();
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

        // Animate statistics numbers
        function animateStats() {
            const statNumbers = document.querySelectorAll('.stats-number');
            statNumbers.forEach((stat, index) => {
                const finalValue = stat.textContent.trim().replace('%', '');
                if (!isNaN(finalValue)) {
                    let current = 0;
                    const increment = finalValue / 40;
                    const timer = setInterval(() => {
                        current += increment;
                        if (current >= finalValue) {
                            stat.textContent = stat.textContent.includes('%') ? finalValue + '%' : finalValue;
                            clearInterval(timer);
                        } else {
                            stat.textContent = stat.textContent.includes('%') 
                                ? Math.floor(current) + '%' 
                                : Math.floor(current);
                        }
                    }, 30);
                }
            });
        }

        // Add parallax effect to cards
        function addParallaxEffect() {
            const cards = document.querySelectorAll('.dashboard-card, .stats-card, .admin-sidebar');
            let mouseX = 0, mouseY = 0;
            
            document.addEventListener('mousemove', (e) => {
                mouseX = (e.clientX / window.innerWidth - 0.5) * 15;
                mouseY = (e.clientY / window.innerHeight - 0.5) * 15;
            });

            function animateCards() {
                cards.forEach((card, index) => {
                    const speed = (index % 3 + 1) * 0.2;
                    const x = mouseX * speed;
                    const y = mouseY * speed;
                    card.style.transform = `translate(${x}px, ${y}px)`;
                });
                requestAnimationFrame(animateCards);
            }
            animateCards();
        }

        // Add ripple effects
        function addRippleEffects() {
            function addRipple(element) {
                element.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.cssText = `
                        position: absolute;
                        width: ${size}px;
                        height: ${size}px;
                        left: ${x}px;
                        top: ${y}px;
                        border-radius: 50%;
                        background: rgba(102, 126, 234, 0.3);
                        transform: scale(0);
                        animation: ripple-animation 0.6s ease-out;
                        pointer-events: none;
                        z-index: 1000;
                    `;
                    
                    this.style.position = 'relative';
                    this.style.overflow = 'hidden';
                    this.appendChild(ripple);
                    
                    setTimeout(() => ripple.remove(), 600);
                });
            }

            document.querySelectorAll('.stats-card, .quick-action-btn, .dashboard-card').forEach(addRipple);
        }

        // Add CSS for ripple animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple-animation {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

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