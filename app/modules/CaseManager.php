<?php
/**
 * Case Management Module for Police Management System
 * Handles all case-related operations including complaints, investigations, and assignments
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/AuthManager.php';

class CaseManager {
    private $pdo;
    private $auth;
    
    public function __construct() {
        $this->pdo = getPDO();
        $this->auth = auth();
    }
    
    /**
     * Create a new case from a complaint
     */
    public function createCaseFromComplaint($complaintId, $officerId = null) {
        try {
            // Get complaint details
            $complaint = $this->getComplaint($complaintId);
            if (!$complaint) {
                throw new Exception("Complaint not found");
            }
            
            // If no officer specified, use current user
            if (!$officerId) {
                $user = $this->auth->getCurrentUser();
                $officerId = $user['officerid'];
            }
            
            // Generate case ID (auto-increment)
            $this->pdo->beginTransaction();
            
            // Insert into case_table
            $stmt = $this->pdo->prepare("
                INSERT INTO case_table (complaint_id, officerid, casetype, status, description)
                VALUES (?, ?, ?, 'Active', ?)
            ");
            
            $description = "Case created from complaint: " . $complaint['ob_number'] . 
                          " - " . $complaint['offence_type'];
            
            $stmt->execute([
                $complaintId,
                $officerId,
                $complaint['offence_type'],
                $description
            ]);
            
            $caseId = $this->pdo->lastInsertId();
            
            // Update complaint status
            $updateStmt = $this->pdo->prepare("
                UPDATE complaints 
                SET complaint_status = 'Assigned as Case' 
                WHERE id = ?
            ");
            $updateStmt->execute([$complaintId]);
            
            // Assign the case to the officer
            $assignStmt = $this->pdo->prepare("
                INSERT INTO case_assignments (case_id, officer_id, assigned_by, role, status, notes)
                VALUES (?, ?, ?, 'Lead Investigator', 'Active', 'Case created from complaint')
            ");
            $assignStmt->execute([$caseId, $officerId, $user['officerid']]);
            
            $this->pdo->commit();
            
            // Log the action
            $this->logCaseAction($caseId, $officerId, 'CASE_CREATED', 
                               "Case created from complaint {$complaint['ob_number']}");
            
            return $caseId;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error creating case: " . $e->getMessage());
            throw new Exception("Failed to create case: " . $e->getMessage());
        }
    }
    
    /**
     * Get all cases with optional filtering
     */
    public function getCases($filters = []) {
        try {
            $whereConditions = [];
            $params = [];
            
            // Build WHERE clause based on filters
            if (!empty($filters['officer_id'])) {
                $whereConditions[] = "ct.officerid = ?";
                $params[] = $filters['officer_id'];
            }
            
            if (!empty($filters['status'])) {
                $whereConditions[] = "ct.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['casetype'])) {
                $whereConditions[] = "ct.casetype = ?";
                $params[] = $filters['casetype'];
            }
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "DATE(ct.created_at) >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "DATE(ct.created_at) <= ?";
                $params[] = $filters['date_to'];
            }
            
            // Role-based filtering
            $user = $this->auth->getCurrentUser();
            if ($user && !$this->auth->isAdmin() && !$this->auth->hasRole('Inspector')) {
                $whereConditions[] = "ct.officerid = ?";
                $params[] = $user['officerid'];
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            $sql = "
                SELECT 
                    ct.*,
                    ul.first_name,
                    ul.last_name,
                    ul.rank,
                    ul.designation,
                    c.ob_number,
                    c.date_reported,
                    c.complaint_status,
                    (SELECT COUNT(*) FROM case_evidence ce WHERE ce.caseid = ct.caseid) as evidence_count,
                    (SELECT COUNT(*) FROM case_assignments ca WHERE ca.case_id = ct.caseid AND ca.status = 'Active') as assigned_officers
                FROM case_table ct
                LEFT JOIN userlogin ul ON ct.officerid = ul.officerid
                LEFT JOIN complaints c ON ct.complaint_id = c.id
                $whereClause
                ORDER BY ct.caseid DESC
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting cases: " . $e->getMessage());
            throw new Exception("Failed to retrieve cases");
        }
    }
    
    /**
     * Get case details by ID
     */
    public function getCase($caseId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    ct.*,
                    ul.first_name,
                    ul.last_name,
                    ul.rank,
                    ul.designation,
                    c.ob_number,
                    c.date_reported,
                    c.complaint_status,
                    c.full_name as complainant_name,
                    c.offence_type,
                    c.date_occurrence,
                    c.place_occurrence,
                    c.statement
                FROM case_table ct
                LEFT JOIN userlogin ul ON ct.officerid = ul.officerid
                LEFT JOIN complaints c ON ct.complaint_id = c.id
                WHERE ct.caseid = ?
            ");
            $stmt->execute([$caseId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting case: " . $e->getMessage());
            throw new Exception("Failed to retrieve case details");
        }
    }
    
    /**
     * Update case status
     */
    public function updateCaseStatus($caseId, $status, $closureReason = null) {
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("
                UPDATE case_table 
                SET status = ?, closure_reason = ?
                WHERE caseid = ?
            ");
            
            $stmt->execute([$status, $closureReason, $caseId]);
            
            // If case is closed, update assignment status
            if ($status === 'Closed' || $status === 'Case Dropped') {
                $assignStmt = $this->pdo->prepare("
                    UPDATE case_assignments 
                    SET status = 'Completed'
                    WHERE case_id = ? AND status = 'Active'
                ");
                $assignStmt->execute([$caseId]);
            }
            
            $this->pdo->commit();
            
            // Log the action
            $user = $this->auth->getCurrentUser();
            $this->logCaseAction($caseId, $user['officerid'], 'STATUS_UPDATED', 
                               "Case status updated to: $status");
            
            return true;
            
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error updating case status: " . $e->getMessage());
            throw new Exception("Failed to update case status");
        }
    }
    
    /**
     * Assign officer to case
     */
    public function assignOfficerToCase($caseId, $officerId, $role = 'Supporting Officer', $notes = '') {
        try {
            // Check if officer is already assigned
            $checkStmt = $this->pdo->prepare("
                SELECT id FROM case_assignments 
                WHERE case_id = ? AND officer_id = ? AND status = 'Active'
            ");
            $checkStmt->execute([$caseId, $officerId]);
            
            if ($checkStmt->fetch()) {
                throw new Exception("Officer is already assigned to this case");
            }
            
            // Assign officer
            $user = $this->auth->getCurrentUser();
            $stmt = $this->pdo->prepare("
                INSERT INTO case_assignments (case_id, officer_id, assigned_by, role, status, notes)
                VALUES (?, ?, ?, ?, 'Active', ?)
            ");
            
            $stmt->execute([$caseId, $officerId, $user['officerid'], $role, $notes]);
            
            // Log the action
            $this->logCaseAction($caseId, $officerId, 'OFFICER_ASSIGNED', 
                               "Officer assigned as: $role");
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Error assigning officer to case: " . $e->getMessage());
            throw new Exception("Failed to assign officer to case");
        }
    }
    
    /**
     * Get case assignments
     */
    public function getCaseAssignments($caseId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    ca.*,
                    ul.first_name,
                    ul.last_name,
                    ul.rank,
                    ul.designation,
                    assigned_by_user.first_name as assigned_by_first_name,
                    assigned_by_user.last_name as assigned_by_last_name
                FROM case_assignments ca
                LEFT JOIN userlogin ul ON ca.officer_id = ul.officerid
                LEFT JOIN userlogin assigned_by_user ON ca.assigned_by = assigned_by_user.officerid
                WHERE ca.case_id = ?
                ORDER BY ca.assignment_date DESC
            ");
            $stmt->execute([$caseId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting case assignments: " . $e->getMessage());
            throw new Exception("Failed to retrieve case assignments");
        }
    }
    
    /**
     * Add evidence to case
     */
    public function addEvidenceToCase($caseId, $filePath, $uploadedBy = null) {
        try {
            if (!$uploadedBy) {
                $user = $this->auth->getCurrentUser();
                $uploadedBy = $user['officerid'];
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO case_evidence (caseid, file_path, uploaded_by)
                VALUES (?, ?, ?)
            ");
            
            $stmt->execute([$caseId, $filePath, $uploadedBy]);
            
            // Log the action
            $this->logCaseAction($caseId, $uploadedBy, 'EVIDENCE_ADDED', 
                               "Evidence file added: " . basename($filePath));
            
            return $this->pdo->lastInsertId();
            
        } catch (PDOException $e) {
            error_log("Error adding evidence to case: " . $e->getMessage());
            throw new Exception("Failed to add evidence to case");
        }
    }
    
    /**
     * Get case evidence
     */
    public function getCaseEvidence($caseId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    ce.*,
                    ul.first_name,
                    ul.last_name
                FROM case_evidence ce
                LEFT JOIN userlogin ul ON ce.uploaded_by = ul.officerid
                WHERE ce.caseid = ?
                ORDER BY ce.uploaded_at DESC
            ");
            $stmt->execute([$caseId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting case evidence: " . $e->getMessage());
            throw new Exception("Failed to retrieve case evidence");
        }
    }
    
    /**
     * Add meeting to case
     */
    public function addCaseMeeting($caseId, $meetingData) {
        try {
            $user = $this->auth->getCurrentUser();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO case_meetings (
                    case_id, meeting_title, meeting_date, meeting_location,
                    agenda, attendees, minutes, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $caseId,
                $meetingData['title'],
                $meetingData['date'],
                $meetingData['location'],
                $meetingData['agenda'] ?? null,
                $meetingData['attendees'] ?? null,
                $meetingData['minutes'] ?? null,
                $user['officerid']
            ]);
            
            $meetingId = $this->pdo->lastInsertId();
            
            // Log the action
            $this->logCaseAction($caseId, $user['officerid'], 'MEETING_ADDED', 
                               "Meeting scheduled: " . $meetingData['title']);
            
            return $meetingId;
            
        } catch (PDOException $e) {
            error_log("Error adding case meeting: " . $e->getMessage());
            throw new Exception("Failed to add case meeting");
        }
    }
    
    /**
     * Get case meetings
     */
    public function getCaseMeetings($caseId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    cm.*,
                    ul.first_name,
                    ul.last_name
                FROM case_meetings cm
                LEFT JOIN userlogin ul ON cm.created_by = ul.officerid
                WHERE cm.case_id = ?
                ORDER BY cm.meeting_date DESC
            ");
            $stmt->execute([$caseId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting case meetings: " . $e->getMessage());
            throw new Exception("Failed to retrieve case meetings");
        }
    }
    
    /**
     * Get complaint details
     */
    private function getComplaint($complaintId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM complaints WHERE id = ?");
            $stmt->execute([$complaintId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting complaint: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Log case actions
     */
    private function logCaseAction($caseId, $officerId, $action, $description) {
        try {
            // This would typically go to an audit log table
            // For now, we'll use the access_logs table
            $stmt = $this->pdo->prepare("
                INSERT INTO access_logs (officerid, action, success, ip_address, timestamp)
                VALUES (?, ?, 1, ?, NOW())
            ");
            
            $stmt->execute([
                $officerId,
                "CASE_{$action}: {$description} (Case ID: {$caseId})",
                $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
            ]);
            
        } catch (PDOException $e) {
            error_log("Failed to log case action: " . $e->getMessage());
        }
    }
    
    /**
     * Get case statistics for dashboard
     */
    public function getCaseStatistics($officerId = null) {
        try {
            $whereClause = "";
            $params = [];
            
            if ($officerId) {
                $whereClause = "WHERE officerid = ?";
                $params[] = $officerId;
            }
            
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(*) as total_cases,
                    SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_cases,
                    SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) as closed_cases,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_cases,
                    SUM(CASE WHEN status = 'Case Dropped' THEN 1 ELSE 0 END) as dropped_cases
                FROM case_table $whereClause
            ");
            
            if ($officerId) {
                $stmt->execute($params);
            }
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting case statistics: " . $e->getMessage());
            return [
                'total_cases' => 0,
                'active_cases' => 0,
                'closed_cases' => 0,
                'pending_cases' => 0,
                'dropped_cases' => 0
            ];
        }
    }
    
    /**
     * Get case types for dropdown
     */
    public function getCaseTypes() {
        try {
            $stmt = $this->pdo->query("
                SELECT DISTINCT casetype 
                FROM case_table 
                ORDER BY casetype
            ");
            
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
            
        } catch (PDOException $e) {
            error_log("Error getting case types: " . $e->getMessage());
            return [];
        }
    }
}

// Convenience functions
function caseManager() {
    return new CaseManager();
}

function createCaseFromComplaint($complaintId, $officerId = null) {
    return caseManager()->createCaseFromComplaint($complaintId, $officerId);
}

function getCases($filters = []) {
    return caseManager()->getCases($filters);
}

function getCase($caseId) {
    return caseManager()->getCase($caseId);
}

function updateCaseStatus($caseId, $status, $closureReason = null) {
    return caseManager()->updateCaseStatus($caseId, $status, $closureReason);
}
?>