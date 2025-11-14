<?php
/**
 * Modern Unified Dashboard for Police Management System
 * Uses all new modular components
 */

// Include required modules
require_once __DIR__ . '/app/core/AuthManager.php';
require_once __DIR__ . '/app/components/HeaderComponent.php';
require_once __DIR__ . '/app/components/NavigationComponent.php';
require_once __DIR__ . '/app/modules/DashboardManager.php';

// Require authentication
auth()->requireAuth();

// Get dashboard data
try {
    $dashboardData = getDashboardData();
    $user = auth()->getCurrentUser();
} catch (Exception $e) {
    $_SESSION['error'] = "Error loading dashboard: " . $e->getMessage();
    header("Location: login_new.php");
    exit();
}

// Render header
renderHeader('Dashboard - Police Management System', [
    'additionalCSS' => ['/assets/css/dashboard.css'],
    'additionalJS' => ['/assets/js/dashboard.js']
]);
?>

<div class="page-wrapper">
    <div class="container-fluid">
        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card welcome-card border-0 shadow-sm">
                    <div class="card-body py-4">
                        <div class="row align-items-center">
                            <div class="col">
                                <h2 class="card-title mb-1 text-primary">
                                    <i class="bi bi-shield-check me-2"></i>
                                    Welcome, <?php echo htmlspecialchars($user['rank'] . ' ' . $user['first_name'] . ' ' . $user['last_name']); ?>
                                </h2>
                                <p class="card-text text-muted mb-0">
                                    <strong>Officer ID:</strong> <?php echo htmlspecialchars($user['officerid']); ?> | 
                                    <strong>Designation:</strong> <?php echo htmlspecialchars($user['designation']); ?> | 
                                    <strong>Date:</strong> <?php echo date('l, F j, Y'); ?>
                                </p>
                            </div>
                            <div class="col-auto">
                                <?php if (auth()->isAdmin()): ?>
                                    <span class="badge bg-danger fs-6 me-1">ADMIN</span>
                                <?php endif; ?>
                                <span class="badge bg-primary fs-6"><?php echo htmlspecialchars($user['rank']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overview Statistics -->
        <?php if (isset($dashboardData['overview'])): ?>
        <div class="row mb-4">
            <div class="col-12">
                <h5 class="mb-3">
                    <i class="bi bi-graph-up me-2"></i>Overview Statistics
                </h5>
            </div>
            <?php foreach ($dashboardData['overview'] as $key => $value): ?>
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                <div class="card stat-card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="stat-icon mb-2">
                            <?php
                            $icons = [
                                'total_officers' => 'bi-people',
                                'total_complaints' => 'bi-journal-text',
                                'total_cases' => 'bi-folder',
                                'active_investigations' => 'bi-search',
                                'pending_requests' => 'bi-clock',
                                'subordinate_officers' => 'bi-person-check',
                                'active_cases' => 'bi-folder2-open',
                                'pending_complaints' => 'bi-exclamation-triangle',
                                'team_performance' => 'bi-graph-up',
                                'cid_personnel' => 'bi-shield-check',
                                'total_cid_cases' => 'bi-folder-symlink',
                                'evidence_files' => 'bi-file-earmark',
                                'assigned_cases' => 'bi-folder-check',
                                'my_cases' => 'bi-file-earmark-text',
                                'my_duties' => 'bi-calendar-check'
                            ];
                            $icon = $icons[$key] ?? 'bi-graph-up';
                            ?>
                            <i class="bi <?php echo $icon; ?> text-primary fs-2"></i>
                        </div>
                        <h3 class="stat-number text-primary mb-1">
                            <?php echo is_array($value) ? count($value) : $value; ?>
                        </h3>
                        <small class="text-muted text-uppercase fw-bold">
                            <?php echo ucwords(str_replace('_', ' ', $key)); ?>
                        </small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <h5 class="mb-3">
                    <i class="bi bi-lightning me-2"></i>Quick Actions
                </h5>
            </div>
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                <a href="/components/new_case.php" class="action-card text-decoration-none d-block">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="action-icon text-success mb-3">
                                <i class="bi bi-file-plus fs-1"></i>
                            </div>
                            <h6 class="mb-2">New Complaint</h6>
                            <small class="text-muted">File a new complaint report</small>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                <a href="/components/view_complaint.php" class="action-card text-decoration-none d-block">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="action-icon text-info mb-3">
                                <i class="bi bi-journal-text fs-1"></i>
                            </div>
                            <h6 class="mb-2">View Complaints</h6>
                            <small class="text-muted">Browse all complaints</small>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                <a href="/components/my_duties.php" class="action-card text-decoration-none d-block">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="action-icon text-warning mb-3">
                                <i class="bi bi-calendar-check fs-1"></i>
                            </div>
                            <h6 class="mb-2">My Duties</h6>
                            <small class="text-muted">View assigned duties</small>
                        </div>
                    </div>
                </a>
            </div>
            
            <?php if (auth()->hasDesignation('CID')): ?>
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                <a href="/components/cid_dashboard.php" class="action-card text-decoration-none d-block">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="action-icon text-danger mb-3">
                                <i class="bi bi-search fs-1"></i>
                            </div>
                            <h6 class="mb-2">CID Dashboard</h6>
                            <small class="text-muted">CID operations</small>
                        </div>
                    </div>
                </a>
            </div>
            <?php endif; ?>
            
            <?php if (auth()->hasRole('Sergeant')): ?>
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                <a href="/components/duty_management.php" class="action-card text-decoration-none d-block">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="action-icon text-primary mb-3">
                                <i class="bi bi-person-plus fs-1"></i>
                            </div>
                            <h6 class="mb-2">Manage Duties</h6>
                            <small class="text-muted">Assign tasks</small>
                        </div>
                    </div>
                </a>
            </div>
            <?php endif; ?>
            
            <?php if (auth()->hasRole('Inspector')): ?>
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                <a href="/admin/user_management.php" class="action-card text-decoration-none d-block">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="action-icon text-secondary mb-3">
                                <i class="bi bi-people fs-1"></i>
                            </div>
                            <h6 class="mb-2">User Management</h6>
                            <small class="text-muted">Manage officers</small>
                        </div>
                    </div>
                </a>
            </div>
            <?php endif; ?>
            
            <?php if (auth()->isAdmin()): ?>
            <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-3">
                <a href="/admin/system_settings.php" class="action-card text-decoration-none d-block">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <div class="action-icon text-dark mb-3">
                                <i class="bi bi-gear fs-1"></i>
                            </div>
                            <h6 class="mb-2">System Settings</h6>
                            <small class="text-muted">Configure system</small>
                        </div>
                    </div>
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Recent Activity -->
        <?php if (isset($dashboardData['recent_activity']) && !empty($dashboardData['recent_activity'])): ?>
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history me-2"></i>Recent Activity
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Officer</th>
                                        <th>Action</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($dashboardData['recent_activity'], 0, 10) as $activity): ?>
                                    <tr>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('M j, g:i A', strtotime($activity['timestamp'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars(($activity['first_name'] ?? '') . ' ' . ($activity['last_name'] ?? '')); ?>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($activity['action']); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($activity['success']): ?>
                                                <span class="badge bg-success">Success</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Failed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Stats Footer -->
        <?php if (isset($dashboardData['quick_stats'])): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="row text-center">
                            <?php foreach ($dashboardData['quick_stats'] as $key => $value): ?>
                            <div class="col-md-3 col-sm-6 mb-2">
                                <div class="d-flex align-items-center justify-content-center">
                                    <div class="me-3">
                                        <?php
                                        $icons = [
                                            'today_complaints' => 'bi-journal-plus',
                                            'today_duties' => 'bi-calendar-event',
                                            'pending_actions' => 'bi-exclamation-circle',
                                            'system_health' => 'bi-heart-pulse',
                                            'active_investigations' => 'bi-search-heart',
                                            'resource_requests' => 'bi-tools'
                                        ];
                                        $icon = $icons[$key] ?? 'bi-graph-up';
                                        ?>
                                        <i class="bi <?php echo $icon; ?> text-primary"></i>
                                    </div>
                                    <div>
                                        <strong><?php echo is_array($value) ? 'Check' : $value; ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo ucwords(str_replace('_', ' ', $key)); ?></small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Additional CSS for dashboard styling -->
<style>
.page-wrapper {
    margin-top: 76px;
    padding: 20px;
    background-color: #f8f9fa;
    min-height: calc(100vh - 76px);
}

.welcome-card {
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
}

.stat-card {
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.action-card .card {
    transition: all 0.3s ease;
}

.action-card:hover .card {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.1);
}

.action-icon {
    transition: all 0.3s ease;
}

.action-card:hover .action-icon {
    transform: scale(1.1);
}

@media (max-width: 768px) {
    .page-wrapper {
        padding: 10px;
    }
    
    .stat-card, .action-card {
        margin-bottom: 15px;
    }
}
</style>

<script>
// Auto-refresh dashboard every 5 minutes
setInterval(function() {
    // Optional: Add AJAX refresh for real-time updates
    // fetch('/api/dashboard_refresh.php')
    //     .then(response => response.json())
    //     .then(data => {
    //         // Update dashboard with new data
    //         console.log('Dashboard refreshed');
    //     });
}, 300000); // 5 minutes

// Add click handlers for action cards
document.querySelectorAll('.action-card').forEach(card => {
    card.addEventListener('click', function(e) {
        if (this.href) {
            // Add loading state
            this.style.opacity = '0.7';
            this.style.pointerEvents = 'none';
        }
    });
});
</script>

</body>
</html>