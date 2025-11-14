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
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    min-height: calc(100vh - 76px);
    position: relative;
    overflow-x: hidden;
}

.page-wrapper::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: 
        radial-gradient(circle at 20% 30%, rgba(0, 123, 255, 0.05) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(118, 75, 162, 0.05) 0%, transparent 50%);
    pointer-events: none;
    z-index: 0;
}

.page-wrapper > .container-fluid {
    position: relative;
    z-index: 1;
}

.welcome-card {
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    border-radius: 15px;
    animation: slideInDown 0.6s ease-out;
    position: relative;
    overflow: hidden;
}

.welcome-card::before {
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

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.stat-card {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 15px;
    position: relative;
    overflow: hidden;
    animation: fadeInUp 0.6s ease-out both;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transition: left 0.5s;
}

.stat-card:hover::before {
    left: 100%;
}

.stat-card:hover {
    transform: translateY(-8px) scale(1.05);
    box-shadow: 0 12px 30px rgba(0, 123, 255, 0.2);
    border-color: rgba(0, 123, 255, 0.3);
}

.stat-card:nth-child(1) { animation-delay: 0.1s; }
.stat-card:nth-child(2) { animation-delay: 0.2s; }
.stat-card:nth-child(3) { animation-delay: 0.3s; }
.stat-card:nth-child(4) { animation-delay: 0.4s; }
.stat-card:nth-child(5) { animation-delay: 0.5s; }
.stat-card:nth-child(6) { animation-delay: 0.6s; }

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.stat-icon {
    animation: pulse 2s ease-in-out infinite;
    display: inline-block;
}

.stat-card:hover .stat-icon {
    animation: bounce 0.6s ease;
    transform: scale(1.2);
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

@keyframes bounce {
    0%, 100% { transform: translateY(0) scale(1.2); }
    50% { transform: translateY(-10px) scale(1.2); }
}

.stat-number {
    animation: countUp 1s ease-out;
    display: inline-block;
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

.action-card {
    position: relative;
    overflow: hidden;
}

.action-card .card {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 15px;
    animation: fadeInScale 0.6s ease-out both;
}

.action-card:nth-child(1) .card { animation-delay: 0.1s; }
.action-card:nth-child(2) .card { animation-delay: 0.2s; }
.action-card:nth-child(3) .card { animation-delay: 0.3s; }
.action-card:nth-child(4) .card { animation-delay: 0.4s; }
.action-card:nth-child(5) .card { animation-delay: 0.5s; }
.action-card:nth-child(6) .card { animation-delay: 0.6s; }

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

.action-card::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(0, 123, 255, 0.1);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
    z-index: 0;
}

.action-card:hover::before {
    width: 300px;
    height: 300px;
}

.action-card:hover .card {
    transform: translateY(-8px) scale(1.03);
    box-shadow: 0 15px 35px rgba(0, 123, 255, 0.2);
    border-color: rgba(0, 123, 255, 0.3);
}

.action-icon {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    z-index: 1;
}

.action-card:hover .action-icon {
    transform: scale(1.2) rotate(10deg);
    filter: drop-shadow(0 4px 8px rgba(0, 123, 255, 0.3));
}

.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-3px);
}

.table-hover tbody tr {
    transition: all 0.3s ease;
    animation: fadeInRow 0.5s ease-out both;
}

.table-hover tbody tr:nth-child(1) { animation-delay: 0.1s; }
.table-hover tbody tr:nth-child(2) { animation-delay: 0.2s; }
.table-hover tbody tr:nth-child(3) { animation-delay: 0.3s; }
.table-hover tbody tr:nth-child(4) { animation-delay: 0.4s; }
.table-hover tbody tr:nth-child(5) { animation-delay: 0.5s; }

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

.table-hover tbody tr:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    background-color: rgba(0, 123, 255, 0.05);
}

.badge {
    animation: pulseBadge 2s ease-in-out infinite;
}

@keyframes pulseBadge {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

h5 {
    position: relative;
    display: inline-block;
}

h5::after {
    content: '';
    position: absolute;
    bottom: -5px;
    left: 0;
    width: 0;
    height: 3px;
    background: linear-gradient(90deg, #007bff, #764ba2);
    transition: width 0.5s ease;
    border-radius: 2px;
}

h5:hover::after {
    width: 100%;
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
document.addEventListener('DOMContentLoaded', function() {
    // Animate stat numbers on load
    const statNumbers = document.querySelectorAll('.stat-number');
    statNumbers.forEach((stat, index) => {
        const finalValue = stat.textContent.trim();
        if (!isNaN(finalValue)) {
            let current = 0;
            const increment = finalValue / 30;
            const timer = setInterval(() => {
                current += increment;
                if (current >= finalValue) {
                    stat.textContent = finalValue;
                    clearInterval(timer);
                } else {
                    stat.textContent = Math.floor(current);
                }
            }, 30);
        }
    });

    // Add parallax effect to cards
    const cards = document.querySelectorAll('.stat-card, .action-card .card, .welcome-card');
    let mouseX = 0, mouseY = 0;
    
    document.addEventListener('mousemove', (e) => {
        mouseX = (e.clientX / window.innerWidth - 0.5) * 20;
        mouseY = (e.clientY / window.innerHeight - 0.5) * 20;
    });

    function animateCards() {
        cards.forEach((card, index) => {
            const speed = (index % 3 + 1) * 0.3;
            const x = mouseX * speed;
            const y = mouseY * speed;
            card.style.transform = `translate(${x}px, ${y}px)`;
        });
        requestAnimationFrame(animateCards);
    }
    animateCards();

    // Add ripple effect to cards
    function addRippleEffect(element) {
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
                background: rgba(0, 123, 255, 0.3);
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

    document.querySelectorAll('.stat-card, .action-card, .welcome-card').forEach(addRippleEffect);

    // Add click handlers for action cards with animation
    document.querySelectorAll('.action-card').forEach(card => {
        card.addEventListener('click', function(e) {
            if (this.href) {
                const cardElement = this.querySelector('.card');
                cardElement.style.transform = 'scale(0.95)';
                cardElement.style.opacity = '0.8';
                setTimeout(() => {
                    cardElement.style.transform = '';
                    cardElement.style.opacity = '';
                }, 200);
            }
        });
    });

    // Add floating animation to stat cards
    setInterval(() => {
        document.querySelectorAll('.stat-card').forEach((card, index) => {
            setTimeout(() => {
                card.style.animation = 'floatCard 3s ease-in-out infinite';
            }, index * 100);
        });
    }, 2000);

    // Animate table rows on scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animation = 'fadeInRow 0.5s ease-out';
            }
        });
    }, observerOptions);

    document.querySelectorAll('.table-hover tbody tr').forEach(row => {
        observer.observe(row);
    });

    // Add glow effect to badges
    document.querySelectorAll('.badge').forEach(badge => {
        badge.addEventListener('mouseenter', function() {
            this.style.boxShadow = '0 0 15px rgba(0, 123, 255, 0.5)';
        });
        badge.addEventListener('mouseleave', function() {
            this.style.boxShadow = '';
        });
    });
});

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple-animation {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    @keyframes floatCard {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-5px); }
    }
`;
document.head.appendChild(style);

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
</script>

</body>
</html>