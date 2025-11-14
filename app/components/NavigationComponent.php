<?php
/**
 * Navigation Component for Police Management System
 * Unified top navigation bar with role-based menu items
 */

require_once __DIR__ . '/../core/AuthManager.php';

class NavigationComponent {
    private $auth;
    private $pdo;
    
    public function __construct() {
        $this->auth = auth();
        $this->pdo = getPDO();
    }
    
    /**
     * Render the complete navigation bar
     */
    public function render() {
        $user = $this->auth->getCurrentUser();
        
        echo '<nav class="navbar navbar-expand-lg navbar-dark bg-dark px-3 fixed-top">';
        echo '<div class="container-fluid">';
        
        // Brand
        $this->renderBrand();
        
        // Mobile toggle button
        $this->renderMobileToggle();
        
        // Navigation menu
        echo '<div class="collapse navbar-collapse" id="navbarNav">';
        $this->renderNavigationMenu($user);
        $this->renderUserMenu($user);
        echo '</div>';
        
        echo '</div>';
        echo '</nav>';
        
        // Add CSS
        $this->renderCSS();
    }
    
    /**
     * Render brand/logo section
     */
    private function renderBrand() {
        $dashboardUrl = $this->auth->getDashboardUrl();
        echo '<a class="navbar-brand fw-bold" href="' . htmlspecialchars($dashboardUrl) . '">';
        echo '<i class="bi bi-shield-check me-2"></i>Police Management System';
        echo '</a>';
    }
    
    /**
     * Render mobile toggle button
     */
    private function renderMobileToggle() {
        echo '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">';
        echo '<span class="navbar-toggler-icon"></span>';
        echo '</button>';
    }
    
    /**
     * Render main navigation menu
     */
    private function renderNavigationMenu($user) {
        echo '<ul class="navbar-nav me-auto">';
        
        // Universal menu items
        $this->renderUniversalMenuItems();
        
        // Role-specific menu items
        if ($user) {
            $this->renderRoleBasedMenuItems($user);
        }
        
        echo '</ul>';
    }
    
    /**
     * Render universal menu items (available to all authenticated users)
     */
    private function renderUniversalMenuItems() {
        echo '<li class="nav-item">';
        echo '<a class="nav-link" href="' . htmlspecialchars(auth()->getDashboardUrl()) . '">';
        echo '<i class="bi bi-speedometer2"></i> Dashboard';
        echo '</a>';
        echo '</li>';
        
        echo '<li class="nav-item">';
        echo '<a class="nav-link" href="/components/new_case.php">';
        echo '<i class="bi bi-file-plus"></i> New Complaint';
        echo '</a>';
        echo '</li>';
        
        echo '<li class="nav-item">';
        echo '<a class="nav-link" href="/components/view_complaint.php">';
        echo '<i class="bi bi-journal-text"></i> View Complaints';
        echo '</a>';
        echo '</li>';
    }
    
    /**
     * Render role-based menu items
     */
    private function renderRoleBasedMenuItems($user) {
        // CID-specific menu items
        if ($this->auth->hasDesignation('CID')) {
            $this->renderCIDMenu();
        }
        
        // Traffic-specific menu items
        if ($this->auth->hasDesignation('Traffic')) {
            $this->renderTrafficMenu();
        }
        
        // Management and admin menu items
        if ($this->auth->hasRole('Sergeant')) {
            $this->renderManagementMenu();
        }
        
        // Inspector and above menu items
        if ($this->auth->hasRole('Inspector')) {
            $this->renderInspectorMenu();
        }
        
        // Chief Inspector and admin menu items
        if ($this->auth->hasRole('Chief Inspector')) {
            $this->renderChiefInspectorMenu();
        }
        
        // Admin only menu items
        if ($this->auth->isAdmin()) {
            $this->renderAdminMenu();
        }
    }
    
    /**
     * Render CID-specific menu
     */
    private function renderCIDMenu() {
        echo '<li class="nav-item dropdown">';
        echo '<a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">';
        echo '<i class="bi bi-folder-check"></i> CID Operations';
        echo '</a>';
        echo '<ul class="dropdown-menu">';
        echo '<li><a class="dropdown-item" href="/components/cid_dashboard.php"><i class="bi bi-speedometer2 me-2"></i>CID Dashboard</a></li>';
        echo '<li><a class="dropdown-item" href="/components/cid_officer_assignment.php"><i class="bi bi-people me-2"></i>Officer Assignment</a></li>';
        echo '<li><a class="dropdown-item" href="/components/cid_reports.php"><i class="bi bi-clipboard-data me-2"></i>Investigation Reports</a></li>';
        echo '<li><hr class="dropdown-divider"></li>';
        echo '<li><a class="dropdown-item" href="/components/case_allocation.php"><i class="bi bi-diagram-3 me-2"></i>Case Allocation</a></li>';
        echo '</ul>';
        echo '</li>';
    }
    
    /**
     * Render Traffic-specific menu
     */
    private function renderTrafficMenu() {
        echo '<li class="nav-item dropdown">';
        echo '<a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">';
        echo '<i class="bi bi-traffic-cone"></i> Traffic Operations';
        echo '</a>';
        echo '<ul class="dropdown-menu">';
        echo '<li><a class="dropdown-item" href="/dashboard/traffic.php"><i class="bi bi-speedometer2 me-2"></i>Traffic Dashboard</a></li>';
        echo '<li><a class="dropdown-item" href="/components/traffic_reports.php"><i class="bi bi-clipboard-data me-2"></i>Traffic Reports</a></li>';
        echo '</ul>';
        echo '</li>';
    }
    
    /**
     * Render Management menu
     */
    private function renderManagementMenu() {
        echo '<li class="nav-item dropdown">';
        echo '<a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">';
        echo '<i class="bi bi-tools"></i> Management';
        echo '</a>';
        echo '<ul class="dropdown-menu">';
        echo '<li><a class="dropdown-item" href="/components/oic_dashboard.php"><i class="bi bi-card-checklist me-2"></i>OIC Dashboard</a></li>';
        echo '<li><a class="dropdown-item" href="/components/duty_management.php"><i class="bi bi-calendar-check me-2"></i>Duty Management</a></li>';
        echo '<li><a class="dropdown-item" href="/components/resource_request.php"><i class="bi bi-clipboard-check me-2"></i>Resource Requests</a></li>';
        echo '</ul>';
        echo '</li>';
    }
    
    /**
     * Render Inspector menu
     */
    private function renderInspectorMenu() {
        echo '<li class="nav-item dropdown">';
        echo '<a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">';
        echo '<i class="bi bi-people"></i> Personnel';
        echo '</a>';
        echo '<ul class="dropdown-menu">';
        echo '<li><a class="dropdown-item" href="/components/add_staff.php"><i class="bi bi-person-plus me-2"></i>Add Staff</a></li>';
        echo '<li><a class="dropdown-item" href="/components/view_officer.php"><i class="bi bi-people me-2"></i>View Officers</a></li>';
        echo '<li><a class="dropdown-item" href="/admin/user_management.php"><i class="bi bi-person-gear me-2"></i>User Management</a></li>';
        echo '</ul>';
        echo '</li>';
    }
    
    /**
     * Render Chief Inspector menu
     */
    private function renderChiefInspectorMenu() {
        echo '<li class="nav-item dropdown">';
        echo '<a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">';
        echo '<i class="bi bi-graph-up"></i> Reports & Analytics';
        echo '</a>';
        echo '<ul class="dropdown-menu">';
        echo '<li><a class="dropdown-item" href="/admin/reports.php"><i class="bi bi-graph-up-arrow me-2"></i>Generate Reports</a></li>';
        echo '<li><a class="dropdown-item" href="/components/resource_management.php"><i class="bi bi-tools me-2"></i>Resource Management</a></li>';
        echo '<li><a class="dropdown-item" href="/admin/system_health.php"><i class="bi bi-heart-pulse me-2"></i>System Health</a></li>';
        echo '</ul>';
        echo '</li>';
    }
    
    /**
     * Render Admin menu
     */
    private function renderAdminMenu() {
        echo '<li class="nav-item dropdown">';
        echo '<a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">';
        echo '<i class="bi bi-gear"></i> Admin Panel';
        echo '</a>';
        echo '<ul class="dropdown-menu">';
        echo '<li><a class="dropdown-item" href="/admin/system_settings.php"><i class="bi bi-sliders me-2"></i>System Settings</a></li>';
        echo '<li><a class="dropdown-item" href="/admin/backup.php"><i class="bi bi-archive me-2"></i>Backup & Restore</a></li>';
        echo '<li><a class="dropdown-item" href="/admin/audit_logs.php"><i class="bi bi-file-earmark-text me-2"></i>Audit Logs</a></li>';
        echo '</ul>';
        echo '</li>';
    }
    
    /**
     * Render user menu (right side)
     */
    private function renderUserMenu($user) {
        echo '<ul class="navbar-nav ms-auto">';
        
        // Notification bell (for logged in users)
        if ($user) {
            echo '<li class="nav-item dropdown">';
            echo '<a class="nav-link" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown">';
            echo '<i class="bi bi-bell"></i>';
            echo '<span class="badge bg-danger rounded-pill" id="notificationCount">0</span>';
            echo '</a>';
            echo '<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown">';
            echo '<li><h6 class="dropdown-header">Notifications</h6></li>';
            echo '<li><a class="dropdown-item text-muted" href="#">No new notifications</a></li>';
            echo '</ul>';
            echo '</li>';
        }
        
        // User dropdown
        if ($user) {
            $fullName = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
            $userType = htmlspecialchars($user['rank']);
            
            echo '<li class="nav-item dropdown">';
            echo '<a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">';
            echo '<i class="bi bi-person-circle"></i> ' . $fullName;
            echo '<span class="badge bg-secondary ms-1">' . $userType . '</span>';
            echo '</a>';
            echo '<ul class="dropdown-menu dropdown-menu-end">';
            
            echo '<li><a class="dropdown-item" href="/components/edit_profile.php">';
            echo '<i class="bi bi-person me-2"></i>Profile';
            echo '</a></li>';
            
            echo '<li><a class="dropdown-item" href="/components/change_password.php">';
            echo '<i class="bi bi-key me-2"></i>Change Password';
            echo '</a></li>';
            
            echo '<li><hr class="dropdown-divider"></li>';
            
            echo '<li><a class="dropdown-item text-danger" href="/logout.php">';
            echo '<i class="bi bi-box-arrow-right me-2"></i>Logout';
            echo '</a></li>';
            
            echo '</ul>';
            echo '</li>';
        } else {
            // Login link for non-authenticated users
            echo '<li class="nav-item">';
            echo '<a class="nav-link" href="/login.php">';
            echo '<i class="bi bi-box-arrow-in-right"></i> Login';
            echo '</a>';
            echo '</li>';
        }
        
        echo '</ul>';
    }
    
    /**
     * Render CSS styles
     */
    private function renderCSS() {
        echo '<style>';
        echo '
        .navbar-brand {
            font-size: 1.25rem;
        }
        
        .navbar-nav .nav-link {
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
        }
        
        .navbar-nav .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .dropdown-item {
            padding: 0.5rem 1.5rem;
            transition: all 0.2s ease;
        }
        
        .dropdown-item:hover {
            background-color: #f8f9fa;
            color: #0d6efd;
        }
        
        .badge {
            font-size: 0.7rem;
        }
        
        #notificationCount {
            position: absolute;
            top: -5px;
            right: -5px;
            font-size: 0.75rem;
        }
        
        @media (max-width: 991.98px) {
            .navbar-nav {
                padding-top: 1rem;
            }
            
            .dropdown-menu {
                position: static !important;
                border: none;
                box-shadow: none;
                background-color: transparent;
            }
            
            .dropdown-item {
                color: rgba(255, 255, 255, 0.8) !important;
                padding-left: 2rem;
            }
            
            .dropdown-item:hover {
                background-color: rgba(255, 255, 255, 0.1) !important;
                color: white !important;
            }
        }
        ';
        echo '</style>';
    }
    
    /**
     * Get navigation data for AJAX requests
     */
    public function getNavigationData() {
        $user = $this->auth->getCurrentUser();
        
        return [
            'user' => $user,
            'isAuthenticated' => $this->auth->isAuthenticated(),
            'userLevel' => $user ? $this->auth->getUserLevel() : 0,
            'dashboardUrl' => $this->auth->getDashboardUrl(),
            'permissions' => [
                'isAdmin' => $this->auth->isAdmin(),
                'hasCID' => $this->auth->hasDesignation('CID'),
                'hasTraffic' => $this->auth->hasDesignation('Traffic'),
                'canManage' => $this->auth->hasRole('Sergeant'),
                'canSupervise' => $this->auth->hasRole('Inspector')
            ]
        ];
    }
}

// Convenience function
function renderNavigation() {
    $nav = new NavigationComponent();
    $nav->render();
}
?>