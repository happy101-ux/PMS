<?php
/**
 * Resource Management Module for Police Management System
 * Handles all resource-related operations including inventory, requests, and assignments
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/AuthManager.php';

class ResourceManager {
    private $pdo;
    private $auth;
    
    public function __construct() {
        $this->pdo = getPDO();
        $this->auth = auth();
    }
    
    /**
     * Create new resource
     */
    public function createResource($resourceData) {
        try {
            // Validate required fields
            $required = ['title', 'description'];
            foreach ($required as $field) {
                if (empty($resourceData[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }
            
            $user = $this->auth->getCurrentUser();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO resources (
                    title, description, resource_image, uploaded_by, resource_number
                ) VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $resourceData['title'],
                $resourceData['description'],
                $resourceData['resource_image'] ?? null,
                $user['officerid'],
                $resourceData['resource_number'] ?? null
            ]);
            
            $resourceId = $this->pdo->lastInsertId();
            
            // Log the action
            $this->logResourceAction($user['officerid'], 'RESOURCE_CREATED', 
                                    "Created resource: {$resourceData['title']}");
            
            return $resourceId;
            
        } catch (PDOException $e) {
            error_log("Error creating resource: " . $e->getMessage());
            throw new Exception("Failed to create resource");
        }
    }
    
    /**
     * Get all resources with filtering options
     */
    public function getResources($filters = []) {
        try {
            $whereConditions = [];
            $params = [];
            
            // Build WHERE clause
            if (!empty($filters['search'])) {
                $whereConditions[] = "(title LIKE ? OR description LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            if (!empty($filters['uploaded_by'])) {
                $whereConditions[] = "uploaded_by = ?";
                $params[] = $filters['uploaded_by'];
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            $sql = "
                SELECT 
                    r.*,
                    ul.first_name,
                    ul.last_name,
                    (SELECT COUNT(*) FROM duty_resources dr WHERE dr.resource_id = r.id) as assigned_count,
                    (SELECT COUNT(*) FROM resource_requests rr WHERE rr.resource_id = r.id AND rr.status = 'Approved') as active_requests
                FROM resources r
                LEFT JOIN userlogin ul ON r.uploaded_by = ul.officerid
                $whereClause
                ORDER BY r.upload_date DESC
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting resources: " . $e->getMessage());
            throw new Exception("Failed to retrieve resources");
        }
    }
    
    /**
     * Get resource details
     */
    public function getResource($resourceId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    r.*,
                    ul.first_name,
                    ul.last_name,
                    (SELECT COUNT(*) FROM duty_resources dr WHERE dr.resource_id = r.id) as assigned_count,
                    (SELECT COUNT(*) FROM resource_requests rr WHERE rr.resource_id = r.id AND rr.status = 'Approved') as active_requests
                FROM resources r
                LEFT JOIN userlogin ul ON r.uploaded_by = ul.officerid
                WHERE r.id = ?
            ");
            $stmt->execute([$resourceId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting resource: " . $e->getMessage());
            throw new Exception("Failed to retrieve resource details");
        }
    }
    
    /**
     * Update resource
     */
    public function updateResource($resourceId, $updateData) {
        try {
            $allowedFields = ['title', 'description', 'resource_image', 'resource_number'];
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
            
            $params[] = $resourceId;
            
            $stmt = $this->pdo->prepare("
                UPDATE resources 
                SET " . implode(', ', $setFields) . "
                WHERE id = ?
            ");
            
            $stmt->execute($params);
            
            // Log the action
            $user = $this->auth->getCurrentUser();
            $this->logResourceAction($user['officerid'], 'RESOURCE_UPDATED', 
                                   "Updated resource ID: $resourceId");
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Error updating resource: " . $e->getMessage());
            throw new Exception("Failed to update resource");
        }
    }
    
    /**
     * Delete resource
     */
    public function deleteResource($resourceId) {
        try {
            // Check if resource is in use
            $usageCheck = $this->pdo->prepare("
                SELECT COUNT(*) as count FROM duty_resources WHERE resource_id = ?
            ");
            $usageCheck->execute([$resourceId]);
            $usage = $usageCheck->fetch(PDO::FETCH_ASSOC);
            
            if ($usage['count'] > 0) {
                throw new Exception("Cannot delete resource that is assigned to duties");
            }
            
            // Delete resource requests first
            $this->pdo->prepare("DELETE FROM resource_requests WHERE resource_id = ?")->execute([$resourceId]);
            
            // Delete the resource
            $stmt = $this->pdo->prepare("DELETE FROM resources WHERE id = ?");
            $stmt->execute([$resourceId]);
            
            // Log the action
            $user = $this->auth->getCurrentUser();
            $this->logResourceAction($user['officerid'], 'RESOURCE_DELETED', 
                                   "Deleted resource ID: $resourceId");
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Error deleting resource: " . $e->getMessage());
            throw new Exception("Failed to delete resource");
        }
    }
    
    /**
     * Create resource request
     */
    public function createResourceRequest($requestData) {
        try {
            $user = $this->auth->getCurrentUser();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO resource_requests (
                    requester_id, resource_id, custom_resource, reason,
                    status, request_date, quantity, urgency, needed_date
                ) VALUES (?, ?, ?, ?, 'Pending', NOW(), ?, ?, ?)
            ");
            
            $stmt->execute([
                $user['officerid'],
                $requestData['resource_id'] ?? null,
                $requestData['custom_resource'] ?? null,
                $requestData['reason'],
                $requestData['quantity'] ?? 1,
                $requestData['urgency'] ?? 'Medium',
                $requestData['needed_date'] ?? null
            ]);
            
            $requestId = $this->pdo->lastInsertId();
            
            // Log the action
            $this->logResourceAction($user['officerid'], 'REQUEST_CREATED', 
                                   "Created resource request ID: $requestId");
            
            return $requestId;
            
        } catch (PDOException $e) {
            error_log("Error creating resource request: " . $e->getMessage());
            throw new Exception("Failed to create resource request");
        }
    }
    
    /**
     * Get resource requests with filtering
     */
    public function getResourceRequests($filters = []) {
        try {
            $whereConditions = [];
            $params = [];
            
            // Role-based filtering
            $user = $this->auth->getCurrentUser();
            if (!$this->auth->isAdmin() && !$this->auth->hasRole('Sergeant')) {
                // Regular users can only see their own requests
                $whereConditions[] = "rr.requester_id = ?";
                $params[] = $user['officerid'];
            } else {
                if (!empty($filters['requester_id'])) {
                    $whereConditions[] = "rr.requester_id = ?";
                    $params[] = $filters['requester_id'];
                }
            }
            
            if (!empty($filters['status'])) {
                $whereConditions[] = "rr.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['urgency'])) {
                $whereConditions[] = "rr.urgency = ?";
                $params[] = $filters['urgency'];
            }
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "DATE(rr.request_date) >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "DATE(rr.request_date) <= ?";
                $params[] = $filters['date_to'];
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            $sql = "
                SELECT 
                    rr.*,
                    ul.first_name as requester_first_name,
                    ul.last_name as requester_last_name,
                    ul.rank as requester_rank,
                    r.title as resource_title,
                    approved_by_user.first_name as approved_by_first_name,
                    approved_by_user.last_name as approved_by_last_name
                FROM resource_requests rr
                LEFT JOIN userlogin ul ON rr.requester_id = ul.officerid
                LEFT JOIN resources r ON rr.resource_id = r.id
                LEFT JOIN userlogin approved_by_user ON rr.approved_by = approved_by_user.officerid
                $whereClause
                ORDER BY rr.request_date DESC
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting resource requests: " . $e->getMessage());
            throw new Exception("Failed to retrieve resource requests");
        }
    }
    
    /**
     * Approve/Reject resource request
     */
    public function updateResourceRequestStatus($requestId, $status, $notes = null) {
        try {
            if (!in_array($status, ['Approved', 'Rejected'])) {
                throw new Exception("Invalid status. Must be 'Approved' or 'Rejected'");
            }
            
            $user = $this->auth->getCurrentUser();
            
            $stmt = $this->pdo->prepare("
                UPDATE resource_requests 
                SET status = ?, approved_by = ?, review_date = NOW(), notes = ?
                WHERE id = ?
            ");
            
            $stmt->execute([$status, $user['officerid'], $notes, $requestId]);
            
            // Log the action
            $this->logResourceAction($user['officerid'], 'REQUEST_' . strtoupper($status), 
                                   "Request ID $requestId $status");
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Error updating resource request status: " . $e->getMessage());
            throw new Exception("Failed to update resource request status");
        }
    }
    
    /**
     * Assign resource to duty
     */
    public function assignResourceToDuty($dutyId, $resourceId, $quantity = 1, $notes = null) {
        try {
            $user = $this->auth->getCurrentUser();
            
            // Check if assignment already exists
            $checkStmt = $this->pdo->prepare("
                SELECT id FROM duty_resources 
                WHERE dutyid = ? AND resource_id = ?
            ");
            $checkStmt->execute([$dutyId, $resourceId]);
            
            if ($checkStmt->fetch()) {
                throw new Exception("Resource is already assigned to this duty");
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO duty_resources (dutyid, resource_id, assigned_by, quantity, notes)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([$dutyId, $resourceId, $user['officerid'], $quantity, $notes]);
            
            // Log the action
            $this->logResourceAction($user['officerid'], 'RESOURCE_ASSIGNED', 
                                   "Assigned resource ID $resourceId to duty ID $dutyId");
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Error assigning resource to duty: " . $e->getMessage());
            throw new Exception("Failed to assign resource to duty");
        }
    }
    
    /**
     * Get duty resources
     */
    public function getDutyResources($dutyId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    dr.*,
                    r.title,
                    r.description,
                    ul.first_name,
                    ul.last_name,
                    assigned_by_user.first_name as assigned_by_first_name,
                    assigned_by_user.last_name as assigned_by_last_name
                FROM duty_resources dr
                LEFT JOIN resources r ON dr.resource_id = r.id
                LEFT JOIN userlogin ul ON dr.resource_id IN (
                    SELECT id FROM resources WHERE uploaded_by = ul.officerid
                )
                LEFT JOIN userlogin assigned_by_user ON dr.assigned_by = assigned_by_user.officerid
                WHERE dr.dutyid = ?
                ORDER BY dr.assignment_date DESC
            ");
            $stmt->execute([$dutyId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting duty resources: " . $e->getMessage());
            throw new Exception("Failed to retrieve duty resources");
        }
    }
    
    /**
     * Get resource statistics
     */
    public function getResourceStatistics() {
        try {
            $sql = "
                SELECT 
                    (SELECT COUNT(*) FROM resources) as total_resources,
                    (SELECT COUNT(*) FROM resource_requests) as total_requests,
                    (SELECT COUNT(*) FROM resource_requests WHERE status = 'Pending') as pending_requests,
                    (SELECT COUNT(*) FROM resource_requests WHERE status = 'Approved') as approved_requests,
                    (SELECT COUNT(*) FROM resource_requests WHERE status = 'Rejected') as rejected_requests,
                    (SELECT COUNT(*) FROM duty_resources) as total_assignments
            ";
            
            $stmt = $this->pdo->query($sql);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting resource statistics: " . $e->getMessage());
            return [
                'total_resources' => 0,
                'total_requests' => 0,
                'pending_requests' => 0,
                'approved_requests' => 0,
                'rejected_requests' => 0,
                'total_assignments' => 0
            ];
        }
    }
    
    /**
     * Upload resource file
     */
    public function uploadResourceFile($file, $uploadDir = 'uploads/resources/') {
        try {
            // Validate file
            if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("File upload error");
            }
            
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/plain'];
            if (!in_array($file['type'], $allowedTypes)) {
                throw new Exception("File type not allowed");
            }
            
            // Validate file size (5MB max)
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception("File size too large");
            }
            
            // Create upload directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate unique filename
            $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
            $filePath = $uploadDir . $fileName;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception("Failed to move uploaded file");
            }
            
            return $filePath;
            
        } catch (Exception $e) {
            error_log("Error uploading resource file: " . $e->getMessage());
            throw new Exception("Failed to upload file: " . $e->getMessage());
        }
    }
    
    /**
     * Log resource actions
     */
    private function logResourceAction($officerId, $action, $description) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO access_logs (officerid, action, success, ip_address, timestamp)
                VALUES (?, ?, 1, ?, NOW())
            ");
            
            $stmt->execute([
                $officerId,
                "RESOURCE_{$action}: {$description}",
                $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
            ]);
            
        } catch (PDOException $e) {
            error_log("Failed to log resource action: " . $e->getMessage());
        }
    }
}

// Convenience functions
function resourceManager() {
    return new ResourceManager();
}

function createResource($resourceData) {
    return resourceManager()->createResource($resourceData);
}

function getResources($filters = []) {
    return resourceManager()->getResources($filters);
}

function getResource($resourceId) {
    return resourceManager()->getResource($resourceId);
}

function createResourceRequest($requestData) {
    return resourceManager()->createResourceRequest($requestData);
}

function getResourceRequests($filters = []) {
    return resourceManager()->getResourceRequests($filters);
}
?>