<?php
// cid_security.php - Working version
require_once '../config/database.php';
require_once '../RoleManager.php';

if (!isset($_SESSION['officerid'])) {
    header("Location: ../login.php");
    exit();
}

$officerid = $_SESSION['officerid'];
$roleManager = new RoleManager($pdo, $officerid);

// Check if user has CID or ADMIN access
if (!$roleManager->hasDesignation('CID') && !$roleManager->isAdmin()) {
    $_SESSION['error'] = "Access denied. CID or ADMIN privileges required.";
    header("Location: ../admin/dashboard.php");
    exit();
}

// Simple logging to error log
error_log("CID Access: " . $officerid . " - " . date('Y-m-d H:i:s'));

class RoleManager {
    private $pdo;
    private $officerid;
    private $userInfo;
    private $isAdmin;
    private $userRole;
    
    // Constants for better security and maintainability
    const ROLE_ADMIN = 'ADMIN';
    const ROLE_CHIEF_INSPECTOR = 'Chief Inspector';
    const ROLE_INSPECTOR = 'Inspector';
    const ROLE_SERGEANT = 'Sergeant';
    const ROLE_CONSTABLE = 'Constable';
    const ROLE_CADET = 'Cadet';
    
    const DESIGNATION_CID = 'CID';
    const DESIGNATION_TRAFFIC = 'Traffic';
    const DESIGNATION_NCO = 'NCO';
    
    private $rankHierarchy = [
        self::ROLE_ADMIN => 6,
        self::ROLE_CHIEF_INSPECTOR => 5,
        self::ROLE_INSPECTOR => 4,
        self::ROLE_SERGEANT => 3,
        self::ROLE_CONSTABLE => 2,
        self::ROLE_CADET => 1
    ];
    
    public function __construct($pdo, $officerid = null) {
        $this->pdo = $pdo;
        if ($officerid) {
            $this->initializeUser($officerid);
        }
    }
    
    /**
     * Initialize user role and permissions
     */
    public function initializeUser($officerid) {
        $this->officerid = $officerid;
        $this->userInfo = $this->getUserRank($officerid);
        $this->isAdmin = $this->userInfo && $this->userInfo['rank'] === self::ROLE_ADMIN;
        $this->userRole = $this->determineUserRole();
    }
    
    /**
     * Enhanced user role determination with CID superior
     */
    private function determineUserRole() {
        if (!$this->userInfo) return 'unknown';
        
        // Check for CID Superior (Inspector + CID designation)
        if ($this->userInfo['rank'] === self::ROLE_INSPECTOR && 
            $this->userInfo['designation'] === self::DESIGNATION_CID) {
            return 'cid_superior';
        }
        
        // Check designation first for special roles
        if ($this->userInfo['designation'] === self::DESIGNATION_CID) {
            return 'cid';
        }
        if ($this->userInfo['designation'] === self::DESIGNATION_TRAFFIC) {
            return 'traffic';
        }
        if ($this->userInfo['designation'] === self::DESIGNATION_NCO) {
            return 'nco';
        }
        
        // Fall back to rank-based roles
        switch($this->userInfo['rank']) {
            case self::ROLE_ADMIN:
                return 'admin';
            case self::ROLE_CHIEF_INSPECTOR:
                return 'chief_inspector';
            case self::ROLE_INSPECTOR:
                return 'inspector';
            case self::ROLE_SERGEANT:
                return 'sergeant';
            case self::ROLE_CONSTABLE:
                return 'constable';
            case self::ROLE_CADET:
                return 'cadet';
            default:
                return 'constable';
        }
    }
    
    /**
     * Get comprehensive user information
     */
    public function getUserInfo() {
        return $this->userInfo;
    }
    
    /**
     * Check if user is ADMIN
     */
    public function isAdmin() {
        return $this->isAdmin;
    }
    
    /**
     * Get user's role type for dashboard display
     */
    public function getUserRole() {
        return $this->userRole;
    }
    
    /**
     * Get user's basic information from database
     */
    public function getUserRank($officerid) {
        try {
            $stmt = $this->pdo->prepare("SELECT rank, designation, first_name, last_name, disabled FROM userlogin WHERE officerid = ?");
            $stmt->execute([$officerid]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting user rank: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user has access to a specific feature based on rank hierarchy
     */
    public function hasAccess($requiredRank) {
        if ($this->isAdmin) {
            return true; // ADMIN has access to everything
        }
        
        if (!$this->userInfo) {
            return false;
        }
        
        $userRank = $this->userInfo['rank'];
        $userLevel = $this->rankHierarchy[$userRank] ?? 0;
        $requiredLevel = $this->rankHierarchy[$requiredRank] ?? 0;
        
        return $userLevel >= $requiredLevel;
    }
    
    /**
     * Check if user has specific designation
     */
    public function hasDesignation($designation) {
        return $this->userInfo && $this->userInfo['designation'] === $designation;
    }
    
    /**
     * Enhanced permission check with multiple conditions
     */
    public function hasPermission($requiredRank = null, $requiredDesignation = null, $specificRole = null) {
        // ADMIN has all permissions
        if ($this->isAdmin) {
            return true;
        }
        
        if (!$this->userInfo) {
            return false;
        }
        
        // Check rank hierarchy
        if ($requiredRank && !$this->hasAccess($requiredRank)) {
            return false;
        }
        
        // Check designation
        if ($requiredDesignation && !$this->hasDesignation($requiredDesignation)) {
            return false;
        }
        
        // Check specific role
        if ($specificRole && $this->userRole !== $specificRole) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get role-specific dashboard data with CID superior
     */
    public function getDashboardData() {
        if (!$this->userInfo) {
            return $this->getDefaultDashboard();
        }
        
        switch($this->userRole) {
            case 'admin':
                return $this->getAdminDashboard();
            case 'chief_inspector':
                return $this->getChiefInspectorDashboard();
            case 'inspector':
                return $this->getInspectorDashboard();
            case 'cid_superior':
                return $this->getCidSuperiorDashboard();
            case 'sergeant':
            case 'nco':
                return $this->getSergeantDashboard();
            case 'cid':
                return $this->getCidDashboard();
            case 'traffic':
                return $this->getTrafficDashboard();
            case 'constable':
            default:
                return $this->getConstableDashboard();
        }
    }
    
    /**
     * Get CID Superior Dashboard Data
     */
    private function getCidSuperiorDashboard() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM case_table WHERE officerid IN (
                        SELECT officerid FROM userlogin WHERE designation = 'CID'
                    )) as total_cid_cases,
                    (SELECT COUNT(*) FROM investigation WHERE investigator IN (
                        SELECT officerid FROM userlogin WHERE designation = 'CID'
                    ) AND status2 = 'Under Investigation') as cid_active_investigations,
                    (SELECT COUNT(*) FROM userlogin WHERE designation = 'CID' AND disabled = 0) as cid_personnel,
                    (SELECT COUNT(*) FROM case_table WHERE officerid IN (
                        SELECT officerid FROM userlogin WHERE designation = 'CID'
                    ) AND status = 'Closed') as cid_closed_cases,
                    (SELECT COUNT(*) FROM case_evidence WHERE caseid IN (
                        SELECT caseid FROM case_table WHERE officerid IN (
                            SELECT officerid FROM userlogin WHERE designation = 'CID'
                        )
                    )) as cid_evidence_files,
                    (SELECT COUNT(*) FROM case_table WHERE officerid IN (
                        SELECT officerid FROM userlogin WHERE designation = 'CID'
                    ) AND status IN ('Active', 'Pending')) as cid_pending_cases
            ");
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("Error getting CID superior dashboard data: " . $e->getMessage());
            return $this->getDefaultDashboard();
        }
    }
    
    // ... include all your existing dashboard data methods (getAdminDashboard, getChiefInspectorDashboard, etc.)
    // Make sure to keep all your original methods and add the new CID superior method
    
    /**
     * Enhanced dashboard actions with CID superior
     */
    public function getDashboardActions() {
        $actions = [];
        
        // Universal actions for all roles
        $actions['universal'] = [
            [
                'title' => 'Register Complaint',
                'url' => '../components/new_case.php',
                'icon' => 'bi-list-task',
                'description' => 'File new complaint report'
            ],
            [
                'title' => $this->hasDesignation(self::DESIGNATION_CID) ? 'CID Cases' : 'View Complaints',
                'url' => $this->hasDesignation(self::DESIGNATION_CID) ? '../components/cid_dashboard.php' : '../components/view_complaint.php',
                'icon' => $this->hasDesignation(self::DESIGNATION_CID) ? 'bi-folder-check' : 'bi-journal-text',
                'badge' => $this->hasDesignation(self::DESIGNATION_CID) ? 'CID' : null,
                'badge_class' => 'cid-badge',
                'description' => 'Manage case assignments'
            ]
        ];

        // CID Superior specific actions
        if ($this->userRole === 'cid_superior') {
            $actions['cid_superior'] = [
                [
                    'title' => 'CID Team Management',
                    'url' => '../components/cid_officer_assignment.php',
                    'icon' => 'bi-people-fill',
                    'badge' => 'CID Superior',
                    'badge_class' => 'cid-superior-badge',
                    'description' => 'Manage CID personnel'
                ],
                [
                    'title' => 'Case Allocation',
                    'url' => '../components/case_allocation.php',
                    'icon' => 'bi-diagram-3',
                    'badge' => 'Supervisor',
                    'badge_class' => 'cid-superior-badge',
                    'description' => 'Assign cases to CID officers'
                ],
                [
                    'title' => 'Investigation Reports',
                    'url' => '../components/cid_reports.php',
                    'icon' => 'bi-clipboard-data',
                    'badge' => 'CID',
                    'badge_class' => 'cid-badge',
                    'description' => 'Review investigation progress'
                ]
            ];
        }

        // Constable actions
        if ($this->hasPermission(self::ROLE_CONSTABLE)) {
            $pendingCount = $this->getPendingResourceRequestsCount();
            $actions['constable'] = [
                [
                    'title' => 'My Investigations',
                    'url' => '../components/my_investigations.php',
                    'icon' => 'bi-search',
                    'description' => 'View assigned investigations'
                ],
                [
                    'title' => 'Resource Requests',
                    'url' => '../components/request.php',
                    'icon' => 'bi-toolbox',
                    'description' => 'Request equipment and resources'
                ],
                [
                    'title' => 'Daily Duties',
                    'url' => '../cp',
                    'icon' => 'bi-calendar-check',
                    'description' => 'View assigned duties'
                ]
            ];
        }

        // Sergeant actions
        if ($this->hasPermission(self::ROLE_SERGEANT)) {
            $actions['sergeant'] = [
                [
                    'title' => 'Assign Duties',
                    'url' => '../components/duty_management.php',
                    'icon' => 'bi-person-plus',
                    'description' => 'Assign tasks to constables'
                ],
                [
                    'title' => 'Case Management',
                    'url' => '../components/oic_dashboard.php',
                    'icon' => 'bi-folder',
                    'description' => 'Oversee case progress'
                ],
                [
                    'title' => 'Team Resources',
                    'url' => '../components/resource_request.php',
                    'icon' => 'bi-clipboard-check',
                    'description' => 'Manage team resources'
                ]
            ];
        }

        // Inspector actions
        if ($this->hasPermission(self::ROLE_INSPECTOR) && $this->userRole !== 'cid_superior') {
            $actions['inspector'] = [
                [
                    'title' => 'Operational Reports',
                    'url' => 'reports.php',
                    'icon' => 'bi-graph-up',
                    'description' => 'Generate operational reports'
                ],
                [
                    'title' => 'Personnel Management',
                    'url' => '../components/add_staff.php',
                    'icon' => 'bi-people',
                    'description' => 'Manage station personnel'
                ]
            ];
        }

        // Chief Inspector actions
        if ($this->hasPermission(self::ROLE_CHIEF_INSPECTOR)) {
            $actions['chief'] = [
                [
                    'title' => 'Resource Management',
                    'url' => '../components/resource_management.php',
                    'icon' => 'bi-tools',
                    'description' => 'Manage station resources'
                ]
            ];
        }

        // ADMIN only actions
        if ($this->isAdmin) {
            $actions['admin'] = [
                [
                    'title' => 'User Management',
                    'url' => '../components/view_officer.php',
                    'icon' => 'bi-person-gear',
                    'badge' => 'ADMIN',
                    'badge_class' => 'admin-badge',
                    'description' => 'Manage user accounts'
                ]
            ];
        }

        return $actions;
    }
    
    /**
     * Get role-specific color
     */
    public function getRoleColor($role) {
        $colors = [
            'admin' => '#dc3545',
            'chief_inspector' => '#9b59b6',
            'inspector' => '#3498db',
            'cid_superior' => '#e74c3c',
            'sergeant' => '#f39c12',
            'cid' => '#c0392b',
            'traffic' => '#e67e22',
            'constable' => '#27ae60'
        ];
        return $colors[$role] ?? '#3498db';
    }

    /**
     * Get icon for statistics
     */
    public function getStatIcon($statKey) {
        $icons = [
            'my_duties' => 'bi-calendar-check',
            'my_cases' => 'bi-folder',
            'my_investigations' => 'bi-search',
            'today_complaints' => 'bi-journal-text',
            'total_complaints' => 'bi-journals',
            'active_cases' => 'bi-folder2-open',
            'total_officers' => 'bi-people',
            'today_duties' => 'bi-calendar-event',
            'pending_complaints' => 'bi-clock',
            'active_investigations' => 'bi-search-heart',
            'total_resources' => 'bi-tools',
            'assigned_cases' => 'bi-folder-check',
            'closed_cases' => 'bi-folder-x',
            'evidence_files' => 'bi-file-earmark',
            'traffic_complaints' => 'bi-traffic-cone',
            'traffic_cases' => 'bi-signpost',
            'subordinate_officers' => 'bi-person-check',
            'team_duties' => 'bi-person-workspace',
            'pending_action' => 'bi-exclamation-triangle',
            'ongoing_investigations' => 'bi-binoculars',
            'assigned_duties' => 'bi-person-plus',
            'total_cid_cases' => 'bi-folder-symlink',
            'cid_active_investigations' => 'bi-search',
            'cid_personnel' => 'bi-people-fill',
            'cid_closed_cases' => 'bi-folder-check',
            'cid_evidence_files' => 'bi-file-earmark-check',
            'cid_pending_cases' => 'bi-folder-symlink-fill'
        ];
        return $icons[$statKey] ?? 'bi-graph-up';
    }

    /**
     * Get formatted label for statistics
     */
    public function getStatLabel($statKey) {
        $labels = [
            'my_duties' => 'My Duties',
            'my_cases' => 'My Cases',
            'my_investigations' => 'My Investigations',
            'today_complaints' => "Today's Complaints",
            'total_complaints' => 'Total Complaints',
            'active_cases' => 'Active Cases',
            'total_officers' => 'Total Officers',
            'today_duties' => "Today's Duties",
            'pending_complaints' => 'Pending Complaints',
            'active_investigations' => 'Active Investigations',
            'total_resources' => 'Total Resources',
            'assigned_cases' => 'Assigned Cases',
            'closed_cases' => 'Closed Cases',
            'evidence_files' => 'Evidence Files',
            'traffic_complaints' => 'Traffic Complaints',
            'traffic_cases' => 'Traffic Cases',
            'subordinate_officers' => 'Subordinate Officers',
            'team_duties' => 'Team Duties',
            'pending_action' => 'Pending Action',
            'ongoing_investigations' => 'Ongoing Investigations',
            'assigned_duties' => 'Assigned Duties',
            'total_cid_cases' => 'CID Cases',
            'cid_active_investigations' => 'CID Investigations',
            'cid_personnel' => 'CID Personnel',
            'cid_closed_cases' => 'CID Closed Cases',
            'cid_evidence_files' => 'CID Evidence Files',
            'cid_pending_cases' => 'CID Pending Cases'
        ];
        return $labels[$statKey] ?? ucwords(str_replace('_', ' ', $statKey));
    }

    /**
     * Get role-specific quick stats section
     */
    public function getRoleQuickStats($userRole, $dashboardData) {
        switch($userRole) {
            case 'constable':
                return '
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="quick-stats">
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <h4 class="text-primary mb-1">' . ($dashboardData['my_duties'] ?? 0) . '</h4>
                                    <small class="text-muted">Today\'s Duties</small>
                                </div>
                                <div class="col-md-3">
                                    <h4 class="text-success mb-1">' . ($dashboardData['my_cases'] ?? 0) . '</h4>
                                    <small class="text-muted">Active Cases</small>
                                </div>
                                <div class="col-md-3">
                                    <h4 class="text-warning mb-1">' . ($dashboardData['my_investigations'] ?? 0) . '</h4>
                                    <small class="text-muted">Investigations</small>
                                </div>
                                <div class="col-md-3">
                                    <h4 class="text-info mb-1">' . ($dashboardData['today_complaints'] ?? 0) . '</h4>
                                    <small class="text-muted">Complaints Filed</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>';
                
            case 'sergeant':
                return '
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="quick-stats">
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <h4 class="text-primary mb-1">' . ($dashboardData['assigned_duties'] ?? 0) . '</h4>
                                    <small class="text-muted">Duties Assigned</small>
                                </div>
                                <div class="col-md-3">
                                    <h4 class="text-success mb-1">' . ($dashboardData['my_cases'] ?? 0) . '</h4>
                                    <small class="text-muted">Cases Managed</small>
                                </div>
                                <div class="col-md-3">
                                    <h4 class="text-warning mb-1">' . ($dashboardData['today_complaints'] ?? 0) . '</h4>
                                    <small class="text-muted">New Complaints</small>
                                </div>
                                <div class="col-md-3">
                                    <h4 class="text-info mb-1">' . ($dashboardData['pending_complaints'] ?? 0) . '</h4>
                                    <small class="text-muted">Pending Review</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>';
                
            case 'cid_superior':
                return '
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="quick-stats">
                            <div class="row text-center">
                                <div class="col-md-2">
                                    <h4 class="text-primary mb-1">' . ($dashboardData['total_cid_cases'] ?? 0) . '</h4>
                                    <small class="text-muted">Total CID Cases</small>
                                </div>
                                <div class="col-md-2">
                                    <h4 class="text-success mb-1">' . ($dashboardData['cid_active_investigations'] ?? 0) . '</h4>
                                    <small class="text-muted">Active Investigations</small>
                                </div>
                                <div class="col-md-2">
                                    <h4 class="text-warning mb-1">' . ($dashboardData['cid_personnel'] ?? 0) . '</h4>
                                    <small class="text-muted">CID Personnel</small>
                                </div>
                                <div class="col-md-2">
                                    <h4 class="text-info mb-1">' . ($dashboardData['cid_closed_cases'] ?? 0) . '</h4>
                                    <small class="text-muted">Cases Closed</small>
                                </div>
                                <div class="col-md-2">
                                    <h4 class="text-danger mb-1">' . ($dashboardData['cid_evidence_files'] ?? 0) . '</h4>
                                    <small class="text-muted">Evidence Files</small>
                                </div>
                                <div class="col-md-2">
                                    <h4 class="text-secondary mb-1">' . ($dashboardData['cid_pending_cases'] ?? 0) . '</h4>
                                    <small class="text-muted">Pending Cases</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>';
                
            default:
                return '';
        }
    }

    /**
     * Get role-specific dashboard section
     */
    public function getRoleSpecificSection($userRole, $dashboardData) {
        switch($userRole) {
            case 'cid_superior':
                return $this->getCidSuperiorSection($dashboardData);
            case 'constable':
                return $this->getConstableSection($dashboardData);
            case 'sergeant':
                return $this->getSergeantSection($dashboardData);
            case 'inspector':
                return $this->getInspectorSection($dashboardData);
            case 'chief_inspector':
                return $this->getChiefInspectorSection($dashboardData);
            default:
                return '';
        }
    }

    private function getCidSuperiorSection($data) {
        $totalCases = $data['total_cid_cases'] ?? 1; // Avoid division by zero
        $closedPercentage = $totalCases > 0 ? ($data['cid_closed_cases'] / $totalCases * 100) : 0;
        $pendingPercentage = $totalCases > 0 ? ($data['cid_pending_cases'] / $totalCases * 100) : 0;
        $avgCases = $data['cid_personnel'] > 0 ? round(($data['total_cid_cases'] / $data['cid_personnel']), 1) : 0;
        
        return '
        <div class="row mt-4">
            <div class="col-12">
                <div class="card border-0 shadow-lg">
                    <div class="card-header text-white" style="background: #e74c3c;">
                        <h5 class="mb-0">
                            <i class="bi bi-graph-up-arrow me-2"></i>
                            CID Unit Performance Overview
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Case Distribution</h6>
                                <div class="progress mb-3" style="height: 25px;">
                                    <div class="progress-bar bg-success" style="width: ' . $closedPercentage . '%">
                                        Closed: ' . ($data['cid_closed_cases'] ?? 0) . '
                                    </div>
                                    <div class="progress-bar bg-warning" style="width: ' . $pendingPercentage . '%">
                                        Pending: ' . ($data['cid_pending_cases'] ?? 0) . '
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>Team Workload</h6>
                                <p>Average cases per officer: ' . $avgCases . '</p>
                                <p>Evidence processing rate: ' . ($data['cid_evidence_files'] ?? 0) . ' files</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
    }

    private function getConstableSection($data) {
        return '
        <div class="row mt-4">
            <div class="col-12">
                <div class="card border-0 shadow-lg">
                    <div class="card-header text-white" style="background: #27ae60;">
                        <h5 class="mb-0">
                            <i class="bi bi-person-check me-2"></i>
                            My Performance Summary
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <div class="p-3">
                                    <h3 class="text-primary">' . ($data['my_duties'] ?? 0) . '</h3>
                                    <p class="text-muted mb-0">Duties Today</p>
                                </div>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="p-3">
                                    <h3 class="text-success">' . ($data['my_cases'] ?? 0) . '</h3>
                                    <p class="text-muted mb-0">Active Cases</p>
                                </div>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="p-3">
                                    <h3 class="text-warning">' . ($data['my_investigations'] ?? 0) . '</h3>
                                    <p class="text-muted mb-0">Investigations</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
    }

    // Add other section methods as needed
    private function getSergeantSection($data) { return ''; }
    private function getInspectorSection($data) { return ''; }
    private function getChiefInspectorSection($data) { return ''; }

    /**
     * Get pending resource requests count for current user
     */
    public function getPendingResourceRequestsCount() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM resource_requests 
                WHERE requester_id = ? AND status = 'Pending'
            ");
            $stmt->execute([$this->officerid]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] ?? 0;
        } catch (PDOException $e) {
            error_log("Error getting pending resource requests: " . $e->getMessage());
            return 0;
        }
    }

    // ... include all your existing methods (getAdminDashboard, getChiefInspectorDashboard, etc.)
    // Make sure to keep all your original database query methods
    
    private function getAdminDashboard() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    (SELECT COUNT(*) FROM complaints) as total_complaints,
                    (SELECT COUNT(*) FROM case_table) as total_cases,
                    (SELECT COUNT(*) FROM userlogin WHERE disabled = 0) as total_officers,
                    (SELECT COUNT(*) FROM duties WHERE dutydate = CURDATE()) as today_duties,
                    (SELECT COUNT(*) FROM complaints WHERE complaint_status = 'Waiting for Action') as pending_complaints,
                    (SELECT COUNT(*) FROM investigation WHERE status2 = 'Under Investigation') as active_investigations,
                    (SELECT COUNT(*) FROM resources) as total_resources
            ");
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("Error getting admin dashboard data: " . $e->getMessage());
            return $this->getDefaultDashboard();
        }
    }
    
    private function getChiefInspectorDashboard() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    (SELECT COUNT(*) FROM complaints WHERE DATE(date_reported) = CURDATE()) as today_complaints,
                    (SELECT COUNT(*) FROM case_table WHERE status IN ('Active', 'Pending')) as active_cases,
                    (SELECT COUNT(*) FROM userlogin WHERE rank IN ('Inspector', 'Sergeant', 'Constable') AND disabled = 0) as subordinate_officers,
                    (SELECT COUNT(*) FROM duties WHERE dutydate = CURDATE()) as today_duties,
                    (SELECT COUNT(*) FROM complaints WHERE complaint_status = 'Waiting for Action') as pending_complaints,
                    (SELECT COUNT(*) FROM resources) as total_resources
            ");
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("Error getting chief inspector dashboard data: " . $e->getMessage());
            return $this->getDefaultDashboard();
        }
    }
    
    private function getInspectorDashboard() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    (SELECT COUNT(*) FROM complaints WHERE DATE(date_reported) = CURDATE()) as today_complaints,
                    (SELECT COUNT(*) FROM case_table WHERE status IN ('Active', 'Pending')) as active_cases,
                    (SELECT COUNT(*) FROM duties WHERE dutydate = CURDATE() AND officerid IN (
                        SELECT officerid FROM userlogin WHERE rank IN ('Sergeant', 'Constable')
                    )) as team_duties,
                    (SELECT COUNT(*) FROM complaints WHERE complaint_status = 'Waiting for Action') as pending_action,
                    (SELECT COUNT(*) FROM investigation WHERE status2 = 'Under Investigation') as ongoing_investigations,
                    (SELECT COUNT(*) FROM resources) as total_resources
            ");
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("Error getting inspector dashboard data: " . $e->getMessage());
            return $this->getDefaultDashboard();
        }
    }
    
    private function getSergeantDashboard() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM complaints WHERE DATE(date_reported) = CURDATE()) as today_complaints,
                    (SELECT COUNT(*) FROM case_table WHERE officerid = ?) as my_cases,
                    (SELECT COUNT(*) FROM duties WHERE officerid = ? AND dutydate = CURDATE()) as my_duties,
                    (SELECT COUNT(*) FROM duties WHERE assigner_id = ? AND dutydate = CURDATE()) as assigned_duties,
                    (SELECT COUNT(*) FROM complaints WHERE complaint_status = 'Waiting for Action') as pending_complaints,
                    (SELECT COUNT(*) FROM investigation WHERE investigator = ? AND status2 = 'Under Investigation') as my_investigations
            ");
            $stmt->execute([$this->officerid, $this->officerid, $this->officerid, $this->officerid]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("Error getting sergeant dashboard data: " . $e->getMessage());
            return $this->getDefaultDashboard();
        }
    }
    
    private function getCidDashboard() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM case_table WHERE officerid = ?) as assigned_cases,
                    (SELECT COUNT(*) FROM investigation WHERE investigator = ? AND status2 = 'Under Investigation') as active_investigations,
                    (SELECT COUNT(*) FROM case_table WHERE officerid = ? AND status = 'Closed') as closed_cases,
                    (SELECT COUNT(*) FROM case_evidence WHERE caseid IN (SELECT caseid FROM case_table WHERE officerid = ?)) as evidence_files,
                    (SELECT COUNT(*) FROM duties WHERE officerid = ? AND dutydate = CURDATE()) as today_duties,
                    (SELECT COUNT(*) FROM case_table WHERE officerid = ? AND status IN ('Active', 'Pending')) as pending_cases
            ");
            $stmt->execute([$this->officerid, $this->officerid, $this->officerid, $this->officerid, $this->officerid, $this->officerid]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("Error getting CID dashboard data: " . $e->getMessage());
            return $this->getDefaultDashboard();
        }
    }
    
    private function getTrafficDashboard() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM complaints WHERE offence_type = 'TRAFFIC' AND DATE(date_reported) = CURDATE()) as traffic_complaints,
                    (SELECT COUNT(*) FROM duties WHERE officerid = ? AND dutydate = CURDATE()) as my_duties,
                    (SELECT COUNT(*) FROM case_table WHERE officerid = ? AND casetype = 'TRAFFIC') as traffic_cases,
                    (SELECT COUNT(*) FROM complaints WHERE received_by = ? AND DATE(date_reported) = CURDATE()) as today_complaints
            ");
            $stmt->execute([$this->officerid, $this->officerid, $this->officerid]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("Error getting traffic dashboard data: " . $e->getMessage());
            return $this->getDefaultDashboard();
        }
    }
    
    private function getConstableDashboard() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM duties WHERE officerid = ? AND dutydate = CURDATE()) as my_duties,
                    (SELECT COUNT(*) FROM investigation WHERE investigator = ? AND status2 = 'Under Investigation') as my_investigations,
                    (SELECT COUNT(*) FROM case_table WHERE officerid = ? AND status IN ('Active', 'Pending')) as my_cases,
                    (SELECT COUNT(*) FROM complaints WHERE received_by = ? AND DATE(date_reported) = CURDATE()) as today_complaints
            ");
            $stmt->execute([$this->officerid, $this->officerid, $this->officerid, $this->officerid]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("Error getting constable dashboard data: " . $e->getMessage());
            return $this->getDefaultDashboard();
        }
    }
    
    private function getDefaultDashboard() {
        return [
            'my_duties' => 0,
            'my_cases' => 0,
            'my_investigations' => 0
        ];
    }
}
?>