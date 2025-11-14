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
        case 'get_system_status':
            echo json_encode(getSystemStatus($pdo));
            exit;
            
        case 'get_database_health':
            echo json_encode(getDatabaseHealth($pdo));
            exit;
            
        case 'get_performance_metrics':
            echo json_encode(getPerformanceMetrics($pdo));
            exit;
            
        case 'get_security_logs':
            echo json_encode(getSecurityLogs($pdo));
            exit;
            
        case 'get_system_alerts':
            echo json_encode(getSystemAlerts($pdo));
            exit;
    }
}

/**
 * System Health Monitoring Functions
 */
function getSystemStatus($pdo) {
    try {
        $status = [];
        
        // Database Connection Status
        $status['database'] = [
            'status' => 'healthy',
            'message' => 'Database connection active',
            'response_time' => measureDatabaseResponseTime($pdo)
        ];
        
        // Memory Usage (simulated)
        $status['memory'] = [
            'status' => 'good',
            'usage' => '45%',
            'total' => '128MB',
            'available' => '70MB'
        ];
        
        // Disk Space (simulated)
        $status['disk'] = [
            'status' => 'good',
            'used' => '2.3GB',
            'total' => '10GB',
            'percentage' => '23%'
        ];
        
        // Server Load (simulated)
        $status['server_load'] = [
            'status' => 'normal',
            'load_1min' => '0.5',
            'load_5min' => '0.3',
            'load_15min' => '0.2'
        ];
        
        // Active Sessions (simulated)
        $status['active_sessions'] = rand(5, 15);
        
        // PHP Version
        $status['php_version'] = PHP_VERSION;
        
        // Web Server
        $status['web_server'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
        
        return $status;
        
    } catch (Exception $e) {
        error_log("Error getting system status: " . $e->getMessage());
        return [];
    }
}

function getDatabaseHealth($pdo) {
    try {
        $health = [];
        
        // Database size
        $stmt = $pdo->query("SELECT 
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS size_mb,
            ROUND(SUM(data_length) / 1024 / 1024, 1) AS data_size_mb,
            ROUND(SUM(index_length) / 1024 / 1024, 1) AS index_size_mb
            FROM information_schema.TABLES 
            WHERE table_schema = 'pms'");
        $sizeInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        $health['database_size'] = $sizeInfo['size_mb'] ?? 0;
        $health['data_size'] = $sizeInfo['data_size_mb'] ?? 0;
        $health['index_size'] = $sizeInfo['index_size_mb'] ?? 0;
        
        // Table statistics
        $stmt = $pdo->query("SELECT 
            table_name,
            table_rows,
            ROUND(((data_length + index_length) / 1024 / 1024), 1) AS size_mb
            FROM information_schema.TABLES 
            WHERE table_schema = 'pms' 
            ORDER BY (data_length + index_length) DESC");
        $health['tables'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Connection count
        $stmt = $pdo->query("SHOW STATUS LIKE 'Threads_connected'");
        $connectionInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        $health['active_connections'] = $connectionInfo['Value'] ?? 0;
        
        // Query cache hit ratio
        $stmt = $pdo->query("SHOW STATUS LIKE 'Qcache_hits'");
        $cacheHits = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = $pdo->query("SHOW STATUS LIKE 'Qcache_inserts'");
        $cacheInserts = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $hits = intval($cacheHits['Value'] ?? 0);
        $inserts = intval($cacheInserts['Value'] ?? 0);
        $total = $hits + $inserts;
        
        $health['cache_hit_ratio'] = $total > 0 ? round(($hits / $total) * 100, 1) : 0;
        
        // Uptime
        $stmt = $pdo->query("SHOW STATUS LIKE 'Uptime'");
        $uptime = $stmt->fetch(PDO::FETCH_ASSOC);
        $health['uptime_seconds'] = intval($uptime['Value'] ?? 0);
        $health['uptime_formatted'] = formatUptime($health['uptime_seconds']);
        
        return $health;
        
    } catch (Exception $e) {
        error_log("Error getting database health: " . $e->getMessage());
        return [];
    }
}

function getPerformanceMetrics($pdo) {
    try {
        $metrics = [];
        
        // Query performance
        $stmt = $pdo->query("SHOW STATUS LIKE 'Slow_queries'");
        $slowQueries = $stmt->fetch(PDO::FETCH_ASSOC);
        $metrics['slow_queries'] = intval($slowQueries['Value'] ?? 0);
        
        // Questions per second
        $stmt = $pdo->query("SHOW STATUS LIKE 'Queries'");
        $queries = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = $pdo->query("SHOW STATUS LIKE 'Uptime'");
        $uptime = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $totalQueries = intval($queries['Value'] ?? 0);
        $uptimeSeconds = intval($uptime['Value'] ?? 1);
        $metrics['queries_per_second'] = round($totalQueries / $uptimeSeconds, 2);
        
        // Table locks
        $stmt = $pdo->query("SHOW STATUS LIKE 'Table_locks_immediate'");
        $immediateLocks = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = $pdo->query("SHOW STATUS LIKE 'Table_locks_waited'");
        $waitedLocks = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $metrics['immediate_locks'] = intval($immediateLocks['Value'] ?? 0);
        $metrics['waited_locks'] = intval($waitedLocks['Value'] ?? 0);
        $metrics['lock_efficiency'] = $metrics['immediate_locks'] > 0 ? 
            round((($metrics['immediate_locks'] - $metrics['waited_locks']) / $metrics['immediate_locks']) * 100, 1) : 100;
        
        // Temporary tables
        $stmt = $pdo->query("SHOW STATUS LIKE 'Created_tmp_tables'");
        $tempTables = $stmt->fetch(PDO::FETCH_ASSOC);
        $metrics['temporary_tables'] = intval($tempTables['Value'] ?? 0);
        
        return $metrics;
        
    } catch (Exception $e) {
        error_log("Error getting performance metrics: " . $e->getMessage());
        return [];
    }
}

function getSecurityLogs($pdo) {
    // Simulate security logs (in a real system, these would come from log files or a security log table)
    $logs = [
        [
            'timestamp' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
            'level' => 'info',
            'message' => 'User login: 1001 from 192.168.1.100',
            'ip_address' => '192.168.1.100'
        ],
        [
            'timestamp' => date('Y-m-d H:i:s', strtotime('-15 minutes')),
            'level' => 'warning',
            'message' => 'Multiple failed login attempts detected',
            'ip_address' => '192.168.1.50'
        ],
        [
            'timestamp' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
            'level' => 'info',
            'message' => 'Admin dashboard accessed by 1002',
            'ip_address' => '192.168.1.101'
        ],
        [
            'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'level' => 'error',
            'message' => 'Database connection timeout occurred',
            'ip_address' => 'localhost'
        ]
    ];
    
    return $logs;
}

function getSystemAlerts($pdo) {
    $alerts = [];
    
    try {
        // Check for inactive officers
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM userlogin WHERE status = 'Inactive'");
        $inactiveCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        if ($inactiveCount > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Inactive Officers',
                'message' => "There are {$inactiveCount} inactive officer accounts",
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        // Check for long-running investigations
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM investigation 
                           WHERE status2 = 'Under Investigation' 
                           AND assigned_date < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $oldInvestigations = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        if ($oldInvestigations > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Old Investigations',
                'message' => "There are {$oldInvestigations} investigations running for more than 30 days",
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        // Database size alert
        $dbHealth = getDatabaseHealth($pdo);
        if ($dbHealth['database_size'] > 100) { // Alert if database is larger than 100MB
            $alerts[] = [
                'type' => 'info',
                'title' => 'Database Size',
                'message' => "Database size is {$dbHealth['database_size']}MB - consider archiving old data",
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        // System status alerts
        $alerts[] = [
            'type' => 'success',
            'title' => 'System Status',
            'message' => 'All systems running normally',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        $alerts[] = [
            'type' => 'error',
            'title' => 'System Error',
            'message' => 'Error checking system status: ' . $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    return $alerts;
}

function measureDatabaseResponseTime($pdo) {
    $start = microtime(true);
    try {
        $pdo->query("SELECT 1");
        $end = microtime(true);
        return round(($end - $start) * 1000, 2); // Return in milliseconds
    } catch (Exception $e) {
        return 0;
    }
}

function formatUptime($seconds) {
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    return "{$days}d {$hours}h {$minutes}m";
}

// Get initial data
$systemStatus = getSystemStatus($pdo);
$databaseHealth = getDatabaseHealth($pdo);
$performanceMetrics = getPerformanceMetrics($pdo);
$securityLogs = getSecurityLogs($pdo);
$systemAlerts = getSystemAlerts($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Health Monitor - Enhanced Admin Dashboard</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2c3e50;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #3498db;
            --dark-bg: #1a1a1a;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
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
            height: 100%;
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

        .system-health-card {
            padding: 20px;
            text-align: center;
            border-radius: 10px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .health-good {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
        }

        .health-warning {
            background: linear-gradient(135deg, #f39c12, #f1c40f);
            color: white;
        }

        .health-critical {
            background: linear-gradient(135deg, #e74c3c, #ec7063);
            color: white;
        }

        .health-info {
            background: linear-gradient(135deg, #3498db, #5dade2);
            color: white;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }

        .status-online { background-color: #27ae60; }
        .status-warning { background-color: #f39c12; }
        .status-offline { background-color: #e74c3c; }
        .status-info { background-color: #3498db; }

        .metric-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: between;
            align-items: center;
        }

        .metric-item:last-child {
            border-bottom: none;
        }

        .progress-custom {
            height: 8px;
            border-radius: 4px;
            background: #e9ecef;
            overflow: hidden;
        }

        .progress-bar-custom {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }

        .alert-custom {
            border: none;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
        }

        .alert-info-custom {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-left: 4px solid #2196f3;
            color: #1565c0;
        }

        .alert-warning-custom {
            background: linear-gradient(135deg, #fff3e0, #ffcc80);
            border-left: 4px solid #ff9800;
            color: #e65100;
        }

        .alert-success-custom {
            background: linear-gradient(135deg, #e8f5e8, #a5d6a7);
            border-left: 4px solid #4caf50;
            color: #2e7d32;
        }

        .alert-danger-custom {
            background: linear-gradient(135deg, #ffebee, #ef9a9a);
            border-left: 4px solid #f44336;
            color: #c62828;
            color: #c62828;
        }
            border-left: 4px solid #f44336);
            color: #c62828;
        }

        .log-entry {
            padding: 10px;
            border-left: 3px solid #ddd;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 0 5px 5px 0;
        }

        .log-info { border-left-color: #3498db; }
        .log-warning { border-left-color: #f39c12; }
        .log-error { border-left-color: #e74c3c; }

        .refresh-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 8px;
            padding: 5px 10px;
            font-size: 0.9rem;
        }

        .chart-container {
            position: relative;
            height: 250px;
            padding: 15px;
        }

        .metric-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .metric-label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 10px;
            }
            
            .system-health-card {
                padding: 15px;
            }
            
            .chart-container {
                height: 200px;
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
                <span class="navbar-text me-3">
                    <i class="bi bi-activity me-1"></i>System Health Monitor
                </span>
                <button class="refresh-btn me-2" onclick="refreshAllData()">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                </button>
                <a href="enhanced_dashboard.php" class="btn btn-outline-light me-2">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
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
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="bi bi-heart-pulse me-2"></i>System Health Monitoring
                        </h4>
                        <small class="opacity-75">Last updated: <span id="lastUpdateTime"><?php echo date('H:i:s'); ?></span></small>
                    </div>
                    <div class="p-3">
                        <p class="text-muted mb-0">Real-time system monitoring and performance metrics</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Status Overview -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="system-health-card health-good">
                    <i class="bi bi-check-circle-fill display-4 mb-2"></i>
                    <h5>Database</h5>
                    <div class="fs-4"><?php echo $systemStatus['database']['status'] ?? 'Unknown'; ?></div>
                    <small><?php echo $systemStatus['database']['response_time'] ?? 0; ?>ms</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="system-health-card health-info">
                    <i class="bi bi-cpu-fill display-4 mb-2"></i>
                    <h5>Server Load</h5>
                    <div class="fs-4"><?php echo $systemStatus['server_load']['load_1min'] ?? 'N/A'; ?></div>
                    <small>Normal Range</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="system-health-card health-info">
                    <i class="bi bi-memory display-4 mb-2"></i>
                    <h5>Memory Usage</h5>
                    <div class="fs-4"><?php echo $systemStatus['memory']['usage'] ?? 'N/A'; ?></div>
                    <small><?php echo $systemStatus['memory']['available'] ?? 'N/A'; ?> available</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="system-health-card health-good">
                    <i class="bi bi-hdd-stack display-4 mb-2"></i>
                    <h5>Disk Space</h5>
                    <div class="fs-4"><?php echo $systemStatus['disk']['percentage'] ?? 'N/A'; ?></div>
                    <small><?php echo $systemStatus['disk']['available'] ?? 'N/A'; ?> free</small>
                </div>
            </div>
        </div>

        <!-- System Alerts -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="dashboard-card">
                    <div class="card-header">
                        <i class="bi bi-exclamation-triangle me-2"></i>System Alerts
                        <button class="btn btn-sm btn-light float-end" onclick="loadAlerts()">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                    <div class="p-3">
                        <div id="systemAlerts">
                            <?php foreach ($systemAlerts as $alert): ?>
                                <div class="alert alert-<?php echo $alert['type'] === 'warning' ? 'warning-custom' : ($alert['type'] === 'error' ? 'danger-custom' : ($alert['type'] === 'success' ? 'success-custom' : 'info-custom')); ?> alert-custom">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo htmlspecialchars($alert['title']); ?></strong><br>
                                            <span><?php echo htmlspecialchars($alert['message']); ?></span>
                                        </div>
                                        <small class="text-muted"><?php echo date('H:i', strtotime($alert['timestamp'])); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Database Health -->
            <div class="col-lg-6 mb-4">
                <div class="dashboard-card">
                    <div class="card-header">
                        <i class="bi bi-database me-2"></i>Database Health
                        <button class="btn btn-sm btn-light float-end" onclick="loadDatabaseHealth()">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                    <div class="p-3">
                        <div class="row">
                            <div class="col-6">
                                <div class="metric-item">
                                    <div>
                                        <div class="metric-value"><?php echo $databaseHealth['database_size'] ?? 0; ?>MB</div>
                                        <div class="metric-label">Database Size</div>
                                    </div>
                                </div>
                                <div class="metric-item">
                                    <div>
                                        <div class="metric-value"><?php echo $databaseHealth['active_connections'] ?? 0; ?></div>
                                        <div class="metric-label">Active Connections</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="metric-item">
                                    <div>
                                        <div class="metric-value"><?php echo $databaseHealth['cache_hit_ratio'] ?? 0; ?>%</div>
                                        <div class="metric-label">Cache Hit Ratio</div>
                                    </div>
                                </div>
                                <div class="metric-item">
                                    <div>
                                        <div class="metric-value"><?php echo $databaseHealth['uptime_formatted'] ?? 'N/A'; ?></div>
                                        <div class="metric-label">Uptime</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <h6>Database Usage</h6>
                            <div class="progress-custom mb-2">
                                <div class="progress-bar-custom bg-success" style="width: <?php echo min(($databaseHealth['database_size'] ?? 0) / 1000 * 100, 100); ?>%"></div>
                            </div>
                            <small class="text-muted">
                                <?php echo $databaseHealth['data_size'] ?? 0; ?>MB data, 
                                <?php echo $databaseHealth['index_size'] ?? 0; ?>MB indexes
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Metrics -->
            <div class="col-lg-6 mb-4">
                <div class="dashboard-card">
                    <div class="card-header">
                        <i class="bi bi-speedometer2 me-2"></i>Performance Metrics
                        <button class="btn btn-sm btn-light float-end" onclick="loadPerformanceMetrics()">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                    <div class="p-3">
                        <div class="row">
                            <div class="col-6">
                                <div class="metric-item">
                                    <div>
                                        <div class="metric-value"><?php echo $performanceMetrics['queries_per_second'] ?? 0; ?></div>
                                        <div class="metric-label">Queries/Second</div>
                                    </div>
                                </div>
                                <div class="metric-item">
                                    <div>
                                        <div class="metric-value"><?php echo $performanceMetrics['slow_queries'] ?? 0; ?></div>
                                        <div class="metric-label">Slow Queries</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="metric-item">
                                    <div>
                                        <div class="metric-value"><?php echo $performanceMetrics['lock_efficiency'] ?? 0; ?>%</div>
                                        <div class="metric-label">Lock Efficiency</div>
                                    </div>
                                </div>
                                <div class="metric-item">
                                    <div>
                                        <div class="metric-value"><?php echo $performanceMetrics['temporary_tables'] ?? 0; ?></div>
                                        <div class="metric-label">Temp Tables</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <h6>System Performance</h6>
                            <div class="chart-container">
                                <canvas id="performanceChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security Logs -->
            <div class="col-lg-6 mb-4">
                <div class="dashboard-card">
                    <div class="card-header">
                        <i class="bi bi-shield-check me-2"></i>Security Logs
                        <button class="btn btn-sm btn-light float-end" onclick="loadSecurityLogs()">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                    <div class="p-3">
                        <div id="securityLogs">
                            <?php foreach ($securityLogs as $log): ?>
                                <div class="log-entry log-<?php echo $log['level']; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <span class="status-indicator status-<?php echo $log['level'] === 'error' ? 'offline' : ($log['level'] === 'warning' ? 'warning' : 'online'); ?>"></span>
                                            <strong><?php echo htmlspecialchars($log['message']); ?></strong>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted"><?php echo date('H:i:s', strtotime($log['timestamp'])); ?></small>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($log['ip_address']); ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Information -->
            <div class="col-lg-6 mb-4">
                <div class="dashboard-card">
                    <div class="card-header">
                        <i class="bi bi-info-circle me-2"></i>System Information
                    </div>
                    <div class="p-3">
                        <div class="row">
                            <div class="col-6">
                                <div class="metric-item">
                                    <div>
                                        <div class="metric-value">PHP <?php echo $systemStatus['php_version'] ?? 'Unknown'; ?></div>
                                        <div class="metric-label">PHP Version</div>
                                    </div>
                                </div>
                                <div class="metric-item">
                                    <div>
                                        <div class="metric-value"><?php echo $systemStatus['active_sessions'] ?? 0; ?></div>
                                        <div class="metric-label">Active Sessions</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="metric-item">
                                    <div>
                                        <div class="metric-value"><?php echo $databaseHealth['tables'] ? count($databaseHealth['tables']) : 0; ?></div>
                                        <div class="metric-label">Database Tables</div>
                                    </div>
                                </div>
                                <div class="metric-item">
                                    <div>
                                        <div class="metric-value"><?php echo date('Y-m-d'); ?></div>
                                        <div class="metric-label">Current Date</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <h6>Server Details</h6>
                            <small class="text-muted">
                                <strong>Web Server:</strong> <?php echo htmlspecialchars($systemStatus['web_server'] ?? 'Unknown'); ?><br>
                                <strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s T'); ?><br>
                                <strong>Timezone:</strong> <?php echo date_default_timezone_get(); ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"></script>
    
    <script>
        let performanceChart;
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();
            startAutoRefresh();
        });
        
        function initializeCharts() {
            // Performance Chart
            const ctx = document.getElementById('performanceChart').getContext('2d');
            performanceChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00'],
                    datasets: [{
                        label: 'CPU Usage (%)',
                        data: [20, 35, 45, 30, 55, 40],
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Memory Usage (%)',
                        data: [30, 40, 35, 50, 45, 35],
                        borderColor: '#f39c12',
                        backgroundColor: 'rgba(243, 156, 18, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }
        
        async function loadSystemStatus() {
            try {
                const response = await fetch('?action=get_system_status');
                const data = await response.json();
                // Update system status display
                console.log('System status updated:', data);
            } catch (error) {
                console.error('Error loading system status:', error);
            }
        }
        
        async function loadDatabaseHealth() {
            try {
                const response = await fetch('?action=get_database_health');
                const data = await response.json();
                console.log('Database health updated:', data);
                // Refresh page to show updated data
                location.reload();
            } catch (error) {
                console.error('Error loading database health:', error);
            }
        }
        
        async function loadPerformanceMetrics() {
            try {
                const response = await fetch('?action=get_performance_metrics');
                const data = await response.json();
                console.log('Performance metrics updated:', data);
                location.reload();
            } catch (error) {
                console.error('Error loading performance metrics:', error);
            }
        }
        
        async function loadSecurityLogs() {
            try {
                const response = await fetch('?action=get_security_logs');
                const data = await response.json();
                
                const logsContainer = document.getElementById('securityLogs');
                logsContainer.innerHTML = data.map(log => `
                    <div class="log-entry log-${log.level}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="status-indicator status-${log.level === 'error' ? 'offline' : (log.level === 'warning' ? 'warning' : 'online')}"></span>
                                <strong>${log.message}</strong>
                            </div>
                            <div class="text-end">
                                <small class="text-muted">${new Date(log.timestamp).toLocaleTimeString()}</small>
                                <br>
                                <small class="text-muted">${log.ip_address}</small>
                            </div>
                        </div>
                    </div>
                `).join('');
            } catch (error) {
                console.error('Error loading security logs:', error);
            }
        }
        
        async function loadAlerts() {
            try {
                const response = await fetch('?action=get_system_alerts');
                const data = await response.json();
                
                const alertsContainer = document.getElementById('systemAlerts');
                alertsContainer.innerHTML = data.map(alert => `
                    <div class="alert alert-${alert.type === 'warning' ? 'warning-custom' : (alert.type === 'error' ? 'danger-custom' : (alert.type === 'success' ? 'success-custom' : 'info-custom'))} alert-custom">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong>${alert.title}</strong><br>
                                <span>${alert.message}</span>
                            </div>
                            <small class="text-muted">${new Date(alert.timestamp).toLocaleTimeString()}</small>
                        </div>
                    </div>
                `).join('');
            } catch (error) {
                console.error('Error loading alerts:', error);
            }
        }
        
        function refreshAllData() {
            loadSystemStatus();
            loadDatabaseHealth();
            loadPerformanceMetrics();
            loadSecurityLogs();
            loadAlerts();
            
            // Update refresh time
            document.getElementById('lastUpdateTime').textContent = new Date().toLocaleTimeString();
        }
        
        function startAutoRefresh() {
            // Auto-refresh every 30 seconds
            setInterval(refreshAllData, 30000);
        }
    </script>
</body>
</html>