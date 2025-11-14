<?php
/**
 * Dashboard Module for Police Management System
 * Provides role-specific dashboards with statistics and quick actions
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/AuthManager.php';
require_once 'CaseManager.php';
require_once 'UserManager.php';
require_once 'ResourceManager.php';

class DashboardManager {
    private $pdo;
    private $auth;
    
    public function __construct() {
        $this->pdo = getPDO();
        $this->auth = auth();
    }
    
    /**
     * Get dashboard data based on user role
     */
    public function getDashboardData() {
        $user = $this->auth->getCurrentUser();
        if (!$user) {
            throw new Exception("User not authenticated");
        }
        
        $userRole = $this->getUserRole($user);
        
        switch ($userRole) {
            case 'admin':
                return $this->getAdminDashboardData($user);
            case 'chief_inspector':
                return $this->getChiefInspectorDashboardData($user);
            case 'cid_superior':
                return $this->getCIDSuperiorDashboardData($user);
            case 'inspector':
                return $this->getInspectorDashboardData($user);
            case 'sergeant':
                return $this->getSergeantDashboardData($user);
            case 'cid':
                return $this->getCIDDashboardData($user);
            case 'traffic':
                return $this->getTrafficDashboardData($user);
            case 'constable':
            default:
                return $this->getConstableDashboardData($user);
        }
    }
    
    /**
     * Determine user role for dashboard purposes
     */
    private function getUserRole($user) {
        // Check for special roles first
        if ($user['rank'] === 'ADMIN') {
            return 'admin';
        }
        
        if ($user['rank'] === 'Chief Inspector') {
            return 'chief_inspector';
        }
        
        if ($user['rank'] === 'Inspector' && $user['designation'] === 'CID') {
            return 'cid_superior';
        }
        
        if ($user['designation'] === 'CID') {
            return 'cid';
        }
        
        if ($user['designation'] === 'Traffic') {
            return 'traffic';
        }
        
        if ($user['rank'] === 'Inspector') {
            return 'inspector';
        }
        
        if ($user['rank'] === 'Sergeant') {
            return 'sergeant';
        }
        
        return 'constable';
    }
    
    /**
     * Admin Dashboard Data
     */
    private function getAdminDashboardData($user) {
        return [
            'overview' => [
                'total_officers' => $this->getTotalOfficers(),
                'total_complaints' => $this->getTotalComplaints(),
                'total_cases' => $this->getTotalCases(),
                'active_investigations' => $this->getActiveInvestigations(),
                'pending_requests' => $this->getPendingResourceRequests()
            ],
            'recent_activity' => $this->getRecentActivity(10),
            'quick_stats' => [
                'today_complaints' => $this->getTodayComplaints(),
                'today_duties' => $this->getTodayDuties(),
                'pending_actions' => $this->getPendingActions(),
                'system_health' => $this->getSystemHealth()
            ]
        ];
    }
    
    /**
     * Chief Inspector Dashboard Data
     */
    private function getChiefInspectorDashboardData($user) {
        return [
            'overview' => [
                'subordinate_officers' => $this->getSubordinateOfficersCount(),
                'active_cases' => $this->getActiveCases(),
                'pending_complaints' => $this->getPendingComplaints(),
                'team_performance' => $this->getTeamPerformance()
            ],
            'recent_activity' => $this->getTeamActivity($user['officerid']),
            'quick_stats' => [
                'today_complaints' => $this->getTodayComplaints(),
                'active_investigations' => $this->getActiveInvestigations(),
                'resource_requests' => $this->getPendingResourceRequests(),
                'case_closure_rate' => $this->getCaseClosureRate()
            ]
        ];
    }
    
    /**
     * CID Superior Dashboard Data
     */
    private function getCIDSuperiorDashboardData($user) {
        return [
            'overview' => [
                'cid_personnel' => $this->getCIDPersonnelCount(),
                'total_cid_cases' => $this->getTotalCIDCases(),
                'active_investigations' => $this->getActiveCIDInvestigations(),
                'evidence_files' => $this->getCIDEvidenceCount()
            ],
            'team_performance' => $this->getCIDTeamPerformance(),
            'recent_activity' => $this->getCIDActivity(),
            'quick_stats' => [
                'pending_cases' => $this->getPendingCIDCases(),
                'closed_cases' => $this->getClosedCIDCases(),
                'overdue_investigations' => $this->getOverdueInvestigations(),
                'case_assignments' => $this->getRecentCaseAssignments()
            ]
        ];
    }
    
    /**
     * Inspector Dashboard Data
     */
    private function getInspectorDashboardData($user) {
        return [
            'overview' => [
                'team_officers' => $this->getTeamOfficersCount($user['officerid']),
                'assigned_cases' => $this->getAssignedCasesCount($user['officerid']),
                'pending_review' => $this->getPendingReviewCount(),
                'operational_metrics' => $this->getOperationalMetrics()
            ],
            'team_activity' => $this->getTeamActivity($user['officerid']),
            'quick_stats' => [
                'today_complaints' => $this->getTodayComplaints(),
                'team_duties' => $this->getTeamDutiesCount($user['officerid']),
                'pending_requests' => $this->getPendingResourceRequests(),
                'performance_indicators' => $this->getPerformanceIndicators()
            ]
        ];
    }
    
    /**
     * Sergeant Dashboard Data
     */
    private function getSergeantDashboardData($user) {
        return [
            'overview' => [
                'my_cases' => $this->getMyCasesCount($user['officerid']),
                'assigned_duties' => $this->getAssignedDutiesCount($user['officerid']),
                'team_members' => $this->getTeamMembersCount($user['officerid']),
                'performance_summary' => $this->getPerformanceSummary($user['officerid'])
            ],
            'team_management' => $this->getTeamManagementData($user['officerid']),
            'quick_stats' => [
                'my_duties' => $this->getMyDutiesCount($user['officerid']),
                'pending_complaints' => $this->getPendingComplaints(),
                'resource_requests' => $this->getPendingResourceRequests(),
                'case_assignments' => $this->getRecentCaseAssignments()
            ]
        ];
    }
    
    /**
     * CID Officer Dashboard Data
     */
    private function getCIDDashboardData($user) {
        return [
            'overview' => [
                'assigned_cases' => $this->getMyCasesCount($user['officerid']),
                'active_investigations' => $this->getMyActiveInvestigations($user['officerid']),
                'evidence_files' => $this->getMyEvidenceCount($user['officerid']),
                'case_progress' => $this->getCaseProgress($user['officerid'])
            ],
            'investigation_summary' => $this->getInvestigationSummary($user['officerid']),
            'quick_stats' => [
                'my_duties' => $this->getMyDutiesCount($user['officerid']),
                'pending_actions' => $this->getMyPendingActions($user['officerid']),
                'completed_cases' => $this->getMyCompletedCases($user['officerid']),
                'evidence_pending' => $this->getMyEvidencePending($user['officerid'])
            ]
        ];
    }
    
    /**
     * Traffic Officer Dashboard Data
     */
    private function getTrafficDashboardData($user) {
        return [
            'overview' => [
                'traffic_cases' => $this->getTrafficCasesCount($user['officerid']),
                'traffic_violations' => $this->getTrafficViolationsCount(),
                'patrol_duties' => $this->getPatrolDutiesCount($user['officerid']),
                'enforcement_actions' => $this->getEnforcementActions()
            ],
            'traffic_summary' => $this->getTrafficSummary(),
            'quick_stats' => [
                'my_duties' => $this->getMyDutiesCount($user['officerid']),
                'today_complaints' => $this->getTodayTrafficComplaints(),
                'violation_reports' => $this->getViolationReports(),
                'checkpoint_activities' => $this->getCheckpointActivities()
            ]
        ];
    }
    
    /**
     * Constable Dashboard Data
     */
    private function getConstableDashboardData($user) {
        return [
            'overview' => [
                'my_duties' => $this->getMyDutiesCount($user['officerid']),
                'my_cases' => $this->getMyCasesCount($user['officerid']),
                'my_investigations' => $this->getMyActiveInvestigations($user['officerid']),
                'daily_activities' => $this->getDailyActivities($user['officerid'])
            ],
            'personal_summary' => $this->getPersonalSummary($user['officerid']),
            'quick_stats' => [
                'today_complaints' => $this->getTodayComplaints(),
                'pending_actions' => $this->getMyPendingActions($user['officerid']),
                'resource_requests' => $this->getMyResourceRequests($user['officerid']),
                'performance_metrics' => $this->getPersonalMetrics($user['officerid'])
            ]
        ];
    }
    
    // Database query methods for statistics
    
    private function getTotalOfficers() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM userlogin WHERE disabled = 0");
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getTotalComplaints() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM complaints");
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getTotalCases() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM case_table");
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getActiveInvestigations() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM investigation WHERE status2 = 'Under Investigation'");
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getPendingResourceRequests() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM resource_requests WHERE status = 'Pending'");
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getTodayComplaints() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM complaints WHERE DATE(date_reported) = CURDATE()");
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getTodayDuties() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM duties WHERE dutydate = CURDATE()");
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getActiveCases() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM case_table WHERE status IN ('Active', 'Pending')");
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getPendingComplaints() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM complaints WHERE complaint_status = 'Waiting for Action'");
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getCIDPersonnelCount() {
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM userlogin WHERE designation = 'CID' AND disabled = 0");
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getTotalCIDCases() {
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as count FROM case_table 
            WHERE officerid IN (SELECT officerid FROM userlogin WHERE designation = 'CID')
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getActiveCIDInvestigations() {
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as count FROM investigation 
            WHERE investigator IN (SELECT officerid FROM userlogin WHERE designation = 'CID')
            AND status2 = 'Under Investigation'
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getCIDEvidenceCount() {
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as count FROM case_evidence 
            WHERE caseid IN (SELECT caseid FROM case_table WHERE officerid IN (SELECT officerid FROM userlogin WHERE designation = 'CID'))
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getMyCasesCount($officerId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM case_table WHERE officerid = ?");
        $stmt->execute([$officerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getMyDutiesCount($officerId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM duties WHERE officerid = ? AND dutydate = CURDATE()");
        $stmt->execute([$officerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getMyActiveInvestigations($officerId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count FROM investigation 
            WHERE investigator = ? AND status2 = 'Under Investigation'
        ");
        $stmt->execute([$officerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    private function getRecentActivity($limit = 10) {
        $stmt = $this->pdo->prepare("
            SELECT al.*, ul.first_name, ul.last_name
            FROM access_logs al
            LEFT JOIN userlogin ul ON al.officerid = ul.officerid
            ORDER BY al.timestamp DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getSystemHealth() {
        try {
            // Check database connection
            $dbHealth = $this->pdo->query("SELECT 1") !== false;
            
            // Check file permissions
            $uploadDir = 'uploads/';
            $uploadWritable = is_writable($uploadDir);
            
            return [
                'database' => $dbHealth,
                'file_uploads' => $uploadWritable,
                'status' => ($dbHealth && $uploadWritable) ? 'Good' : 'Issues Detected'
            ];
        } catch (Exception $e) {
            return [
                'database' => false,
                'file_uploads' => false,
                'status' => 'Error'
            ];
        }
    }
    
    // Additional helper methods
    
    private function getSubordinateOfficersCount() { return 0; }
    private function getTeamPerformance() { return []; }
    private function getTeamActivity($officerId) { return []; }
    private function getCaseClosureRate() { return 0; }
    private function getCIDTeamPerformance() { return []; }
    private function getCIDActivity() { return []; }
    private function getPendingCIDCases() { return 0; }
    private function getClosedCIDCases() { return 0; }
    private function getOverdueInvestigations() { return 0; }
    private function getRecentCaseAssignments() { return []; }
    private function getTeamOfficersCount($officerId) { return 0; }
    private function getAssignedCasesCount($officerId) { return 0; }
    private function getPendingReviewCount() { return 0; }
    private function getOperationalMetrics() { return []; }
    private function getTeamDutiesCount($officerId) { return 0; }
    private function getPerformanceIndicators() { return []; }
    private function getAssignedDutiesCount($officerId) { return 0; }
    private function getTeamMembersCount($officerId) { return 0; }
    private function getPerformanceSummary($officerId) { return []; }
    private function getTeamManagementData($officerId) { return []; }
    private function getMyEvidenceCount($officerId) { return 0; }
    private function getCaseProgress($officerId) { return []; }
    private function getInvestigationSummary($officerId) { return []; }
    private function getMyPendingActions($officerId) { return 0; }
    private function getMyCompletedCases($officerId) { return 0; }
    private function getMyEvidencePending($officerId) { return 0; }
    private function getTrafficCasesCount($officerId) { return 0; }
    private function getTrafficViolationsCount() { return 0; }
    private function getPatrolDutiesCount($officerId) { return 0; }
    private function getEnforcementActions() { return 0; }
    private function getTrafficSummary() { return []; }
    private function getTodayTrafficComplaints() { return 0; }
    private function getViolationReports() { return 0; }
    private function getCheckpointActivities() { return 0; }
    private function getDailyActivities($officerId) { return []; }
    private function getPersonalSummary($officerId) { return []; }
    private function getMyResourceRequests($officerId) { return 0; }
    private function getPersonalMetrics($officerId) { return []; }
    private function getPendingActions() { return 0; }
}

// Convenience function
function dashboardManager() {
    return new DashboardManager();
}

function getDashboardData() {
    return dashboardManager()->getDashboardData();
}
?>