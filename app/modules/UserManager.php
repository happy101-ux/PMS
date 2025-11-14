<?php
/**
 * User Management Module for Police Management System
 * Handles all user-related operations including officers, roles, and permissions
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/AuthManager.php';

class UserManager {
    private $pdo;
    private $auth;
    
    public function __construct() {
        $this->pdo = getPDO();
        $this->auth = auth();
    }
    
    /**
     * Create new user/officer
     */
    public function createOfficer($officerData) {
        try {
            // Validate required fields
            $required = ['officerid', 'rank', 'designation', 'gender', 'password', 'first_name', 'last_name'];
            foreach ($required as $field) {
                if (empty($officerData[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }
            
            // Check if officer ID already exists
            if ($this->officerExists($officerData['officerid'])) {
                throw new Exception("Officer ID already exists");
            }
            
            // Hash password
            $hashedPassword = password_hash($officerData['password'], PASSWORD_BCRYPT);
            
            // Insert new officer
            $stmt = $this->pdo->prepare("
                INSERT INTO userlogin (
                    officerid, rank, designation, gender, password,
                    first_name, last_name, date_added, disabled
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 0)
            ");
            
            $stmt->execute([
                $officerData['officerid'],
                $officerData['rank'],
                $officerData['designation'],
                $officerData['gender'],
                $hashedPassword,
                $officerData['first_name'],
                $officerData['last_name']
            ]);
            
            $userId = $this->pdo->lastInsertId();
            
            // Log the action
            $currentUser = $this->auth->getCurrentUser();
            $this->logUserAction($currentUser['officerid'], 'OFFICER_CREATED', 
                               "Created officer: {$officerData['first_name']} {$officerData['last_name']} ({$officerData['officerid']})");
            
            return $userId;
            
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                throw new Exception("Officer ID already exists");
            }
            error_log("Error creating officer: " . $e->getMessage());
            throw new Exception("Failed to create officer");
        }
    }
    
    /**
     * Get all officers with filtering options
     */
    public function getOfficers($filters = []) {
        try {
            $whereConditions = [];
            $params = [];
            
            // Build WHERE clause
            if (!empty($filters['rank'])) {
                $whereConditions[] = "rank = ?";
                $params[] = $filters['rank'];
            }
            
            if (!empty($filters['designation'])) {
                $whereConditions[] = "designation = ?";
                $params[] = $filters['designation'];
            }
            
            if (isset($filters['disabled'])) {
                $whereConditions[] = "disabled = ?";
                $params[] = $filters['disabled'] ? 1 : 0;
            } else {
                // Default to active officers only
                $whereConditions[] = "disabled = 0";
            }
            
            if (!empty($filters['search'])) {
                $whereConditions[] = "(first_name LIKE ? OR last_name LIKE ? OR officerid LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            // Role-based filtering
            $user = $this->auth->getCurrentUser();
            if ($user && !$this->auth->isAdmin() && !$this->auth->hasRole('Inspector')) {
                // Non-admin users can only see their own record
                $whereConditions[] = "officerid = ?";
                $params[] = $user['officerid'];
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            $sql = "
                SELECT 
                    id, officerid, rank, designation, gender,
                    first_name, last_name, date_added, disabled,
                    (SELECT COUNT(*) FROM case_table WHERE officerid = ul.officerid) as case_count,
                    (SELECT COUNT(*) FROM duties WHERE officerid = ul.officerid AND dutydate >= CURDATE()) as upcoming_duties
                FROM userlogin ul
                $whereClause
                ORDER BY last_name, first_name
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting officers: " . $e->getMessage());
            throw new Exception("Failed to retrieve officers");
        }
    }
    
    /**
     * Get officer details
     */
    public function getOfficer($officerId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    id, officerid, rank, designation, gender,
                    first_name, last_name, date_added, disabled
                FROM userlogin 
                WHERE officerid = ?
            ");
            $stmt->execute([$officerId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting officer: " . $e->getMessage());
            throw new Exception("Failed to retrieve officer details");
        }
    }
    
    /**
     * Update officer information
     */
    public function updateOfficer($officerId, $updateData) {
        try {
            $allowedFields = ['rank', 'designation', 'gender', 'first_name', 'last_name'];
            $setFields = [];
            $params = [];
            
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $updateData)) {
                    $setFields[] = "$field = ?";
                    $params[] = $updateData[$field];
                }
            }
            
            if (empty($setFields)) {
                throw new Exception("No valid fields to update");
            }
            
            $params[] = $officerId;
            
            $stmt = $this->pdo->prepare("
                UPDATE userlogin 
                SET " . implode(', ', $setFields) . "
                WHERE officerid = ?
            ");
            
            $stmt->execute($params);
            
            // Log the action
            $currentUser = $this->auth->getCurrentUser();
            $this->logUserAction($currentUser['officerid'], 'OFFICER_UPDATED', 
                               "Updated officer: $officerId");
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Error updating officer: " . $e->getMessage());
            throw new Exception("Failed to update officer");
        }
    }
    
    /**
     * Disable/Enable officer account
     */
    public function toggleOfficerStatus($officerId, $disabled = true) {
        try {
            // Prevent self-disable
            $currentUser = $this->auth->getCurrentUser();
            if ($currentUser['officerid'] === $officerId) {
                throw new Exception("Cannot disable your own account");
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE userlogin 
                SET disabled = ? 
                WHERE officerid = ?
            ");
            
            $stmt->execute([$disabled ? 1 : 0, $officerId]);
            
            // Log the action
            $action = $disabled ? 'OFFICER_DISABLED' : 'OFFICER_ENABLED';
            $this->logUserAction($currentUser['officerid'], $action, 
                               ($disabled ? 'Disabled' : 'Enabled') . " officer: $officerId");
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Error toggling officer status: " . $e->getMessage());
            throw new Exception("Failed to toggle officer status");
        }
    }
    
    /**
     * Reset officer password
     */
    public function resetOfficerPassword($officerId, $newPassword) {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            
            $stmt = $this->pdo->prepare("
                UPDATE userlogin 
                SET password = ? 
                WHERE officerid = ?
            ");
            
            $stmt->execute([$hashedPassword, $officerId]);
            
            // Log the action
            $currentUser = $this->auth->getCurrentUser();
            $this->logUserAction($currentUser['officerid'], 'PASSWORD_RESET', 
                               "Reset password for officer: $officerId");
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Error resetting password: " . $e->getMessage());
            throw new Exception("Failed to reset password");
        }
    }
    
    /**
     * Change current user's password
     */
    public function changePassword($officerId, $currentPassword, $newPassword) {
        try {
            // Verify current password
            $stmt = $this->pdo->prepare("SELECT password FROM userlogin WHERE officerid = ?");
            $stmt->execute([$officerId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($currentPassword, $user['password'])) {
                throw new Exception("Current password is incorrect");
            }
            
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            
            $updateStmt = $this->pdo->prepare("
                UPDATE userlogin 
                SET password = ? 
                WHERE officerid = ?
            ");
            
            $updateStmt->execute([$hashedPassword, $officerId]);
            
            // Log the action
            $this->logUserAction($officerId, 'PASSWORD_CHANGED', "Changed password");
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Error changing password: " . $e->getMessage());
            throw new Exception("Failed to change password");
        }
    }
    
    /**
     * Get available ranks
     */
    public function getAvailableRanks() {
        return [
            'ADMIN' => 'Administrator',
            'Chief Inspector' => 'Chief Inspector',
            'Inspector' => 'Inspector',
            'Sergeant' => 'Sergeant',
            'Constable' => 'Constable',
            'Cadet' => 'Cadet'
        ];
    }
    
    /**
     * Get available designations
     */
    public function getAvailableDesignations() {
        return [
            'Admin' => 'Administration',
            'CID' => 'Criminal Investigation Department',
            'Traffic' => 'Traffic Department',
            'NCO' => 'Non-Commissioned Officer',
            'Operations' => 'Operations',
            'Intelligence' => 'Intelligence Unit'
        ];
    }
    
    /**
     * Get officer statistics
     */
    public function getOfficerStatistics($officerId = null) {
        try {
            $whereClause = "";
            $params = [];
            
            if ($officerId) {
                $whereClause = "WHERE officerid = ?";
                $params[] = $officerId;
            }
            
            $sql = "
                SELECT 
                    COUNT(*) as total_officers,
                    SUM(CASE WHEN rank = 'ADMIN' THEN 1 ELSE 0 END) as admin_count,
                    SUM(CASE WHEN rank = 'Chief Inspector' THEN 1 ELSE 0 END) as chief_inspector_count,
                    SUM(CASE WHEN rank = 'Inspector' THEN 1 ELSE 0 END) as inspector_count,
                    SUM(CASE WHEN rank = 'Sergeant' THEN 1 ELSE 0 END) as sergeant_count,
                    SUM(CASE WHEN rank = 'Constable' THEN 1 ELSE 0 END) as constable_count,
                    SUM(CASE WHEN designation = 'CID' THEN 1 ELSE 0 END) as cid_count,
                    SUM(CASE WHEN designation = 'Traffic' THEN 1 ELSE 0 END) as traffic_count,
                    SUM(CASE WHEN disabled = 1 THEN 1 ELSE 0 END) as disabled_count
                FROM userlogin $whereClause
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting officer statistics: " . $e->getMessage());
            return [
                'total_officers' => 0,
                'admin_count' => 0,
                'chief_inspector_count' => 0,
                'inspector_count' => 0,
                'sergeant_count' => 0,
                'constable_count' => 0,
                'cid_count' => 0,
                'traffic_count' => 0,
                'disabled_count' => 0
            ];
        }
    }
    
    /**
     * Get user activity log
     */
    public function getUserActivityLog($officerId = null, $limit = 50) {
        try {
            $whereClause = "";
            $params = [];
            
            if ($officerId) {
                $whereClause = "WHERE officerid = ?";
                $params[] = $officerId;
            }
            
            $sql = "
                SELECT 
                    al.*,
                    ul.first_name,
                    ul.last_name
                FROM access_logs al
                LEFT JOIN userlogin ul ON al.officerid = ul.officerid
                $whereClause
                ORDER BY al.timestamp DESC
                LIMIT ?
            ";
            
            $params[] = $limit;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting user activity log: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if officer exists
     */
    private function officerExists($officerId) {
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM userlogin WHERE officerid = ?");
            $stmt->execute([$officerId]);
            
            return $stmt->fetch() !== false;
            
        } catch (PDOException $e) {
            error_log("Error checking officer existence: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log user actions
     */
    private function logUserAction($officerId, $action, $description) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO access_logs (officerid, action, success, ip_address, timestamp)
                VALUES (?, ?, 1, ?, NOW())
            ");
            
            $stmt->execute([
                $officerId,
                "USER_{$action}: {$description}",
                $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
            ]);
            
        } catch (PDOException $e) {
            error_log("Failed to log user action: " . $e->getMessage());
        }
    }
    
    /**
     * Get subordinates (for supervisors)
     */
    public function getSubordinates($officerId) {
        try {
            // Get officer's rank level
            $officer = $this->getOfficer($officerId);
            if (!$officer) {
                return [];
            }
            
            $rankHierarchy = [
                'ADMIN' => 6,
                'Chief Inspector' => 5,
                'Inspector' => 4,
                'Sergeant' => 3,
                'Constable' => 2,
                'Cadet' => 1
            ];
            
            $userLevel = $rankHierarchy[$officer['rank']] ?? 0;
            
            // Get officers with lower rank levels
            $placeholders = str_repeat('?,', count($rankHierarchy) - 1) . '?';
            $sql = "
                SELECT officerid, first_name, last_name, rank, designation
                FROM userlogin 
                WHERE disabled = 0 AND rank IN ($placeholders)
                ORDER BY rank DESC, last_name, first_name
            ";
            
            // Get ranks with lower levels
            $lowerRanks = array_filter($rankHierarchy, function($level) use ($userLevel) {
                return $level < $userLevel;
            }, ARRAY_FILTER_USE_BOTH);
            
            $params = array_keys($lowerRanks);
            $params[] = $officerId; // Exclude self
            
            $sql .= " AND officerid != ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting subordinates: " . $e->getMessage());
            return [];
        }
    }
}

// Convenience functions
function userManager() {
    return new UserManager();
}

function createOfficer($officerData) {
    return userManager()->createOfficer($officerData);
}

function getOfficers($filters = []) {
    return userManager()->getOfficers($filters);
}

function getOfficer($officerId) {
    return userManager()->getOfficer($officerId);
}

function updateOfficer($officerId, $updateData) {
    return userManager()->updateOfficer($officerId, $updateData);
}

function toggleOfficerStatus($officerId, $disabled = true) {
    return userManager()->toggleOfficerStatus($officerId, $disabled);
}
?>