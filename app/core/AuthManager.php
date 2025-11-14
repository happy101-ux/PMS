<?php
/**
 * Authentication Manager for Police Management System
 * Handles user login, session management, and authorization
 */

require_once __DIR__ . '/../config/database.php';

class AuthManager {
    private $pdo;
    private $sessionStarted = false;
    
    // User roles and permissions
    const ROLES = [
        'ADMIN' => ['level' => 6, 'permissions' => ['*']],
        'Chief Inspector' => ['level' => 5, 'permissions' => ['manage', 'reports', 'resources']],
        'Inspector' => ['level' => 4, 'permissions' => ['manage', 'reports']],
        'Sergeant' => ['level' => 3, 'permissions' => ['manage', 'duties']],
        'Constable' => ['level' => 2, 'permissions' => ['basic']],
        'Cadet' => ['level' => 1, 'permissions' => ['basic']]
    ];
    
    const DESIGNATIONS = [
        'CID', 'Traffic', 'NCO', 'Admin'
    ];
    
    public function __construct() {
        $this->pdo = getPDO();
        $this->startSession();
    }
    
    /**
     * Start session if not already started
     */
    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
            $this->sessionStarted = true;
        }
    }
    
    /**
     * Authenticate user
     */
    public function authenticate($officerId, $password) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM userlogin 
                WHERE officerid = ? AND disabled = 0 
                LIMIT 1
            ");
            $stmt->execute([$officerId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $this->logAccess($officerId, 'LOGIN', false);
                return false;
            }
            
            // Verify password
            $isValid = $this->verifyPassword($password, $user['password']);
            
            if ($isValid) {
                // Upgrade to bcrypt if not already
                if (!$this->isBcrypt($user['password'])) {
                    $this->upgradePassword($officerId, $password);
                }
                
                // Set session variables
                $this->setUserSession($user);
                
                // Log successful login
                $this->logAccess($officerId, 'LOGIN_SUCCESS', true);
                
                return true;
            } else {
                $this->logAccess($officerId, 'LOGIN_FAILED', false);
                return false;
            }
            
        } catch (PDOException $e) {
            error_log("Authentication error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify password with multiple methods
     */
    private function verifyPassword($inputPassword, $storedPassword) {
        // Check bcrypt
        if ($this->isBcrypt($storedPassword)) {
            return password_verify($inputPassword, $storedPassword);
        }
        
        // Check SHA1
        if (strlen($storedPassword) === 40 && ctype_xdigit($storedPassword)) {
            return sha1($inputPassword) === $storedPassword;
        }
        
        // Plain text fallback
        return $inputPassword === $storedPassword;
    }
    
    /**
     * Check if password is bcrypt
     */
    private function isBcrypt($password) {
        return strpos($password, '$2y$') === 0;
    }
    
    /**
     * Upgrade password to bcrypt
     */
    private function upgradePassword($officerId, $password) {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare("UPDATE userlogin SET password = ? WHERE officerid = ?");
        $stmt->execute([$hashedPassword, $officerId]);
    }
    
    /**
     * Set user session data
     */
    private function setUserSession($user) {
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        $_SESSION['officerid'] = $user['officerid'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['rank'] = $user['rank'];
        $_SESSION['designation'] = $user['designation'];
        $_SESSION['gender'] = $user['gender'];
        $_SESSION['authenticated'] = true;
        $_SESSION['login_time'] = time();
    }
    
    /**
     * Check if user is authenticated
     */
    public function isAuthenticated() {
        return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
    }
    
    /**
     * Get current user info
     */
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        return [
            'officerid' => $_SESSION['officerid'] ?? null,
            'first_name' => $_SESSION['first_name'] ?? null,
            'last_name' => $_SESSION['last_name'] ?? null,
            'rank' => $_SESSION['rank'] ?? null,
            'designation' => $_SESSION['designation'] ?? null,
            'gender' => $_SESSION['gender'] ?? null
        ];
    }
    
    /**
     * Check if user has specific role
     */
    public function hasRole($requiredRole) {
        $user = $this->getCurrentUser();
        if (!$user) return false;
        
        $userLevel = self::ROLES[$user['rank']]['level'] ?? 0;
        $requiredLevel = self::ROLES[$requiredRole]['level'] ?? 0;
        
        return $userLevel >= $requiredLevel;
    }
    
    /**
     * Check if user has specific designation
     */
    public function hasDesignation($designation) {
        $user = $this->getCurrentUser();
        if (!$user) return false;
        
        return $user['designation'] === $designation;
    }
    
    /**
     * Check if user has permission
     */
    public function hasPermission($permission) {
        $user = $this->getCurrentUser();
        if (!$user) return false;
        
        $userPermissions = self::ROLES[$user['rank']]['permissions'] ?? [];
        
        // Admin has all permissions
        if (in_array('*', $userPermissions)) {
            return true;
        }
        
        return in_array($permission, $userPermissions);
    }
    
    /**
     * Require authentication - redirect if not logged in
     */
    public function requireAuth($redirectTo = '/login.php') {
        if (!$this->isAuthenticated()) {
            header("Location: " . $redirectTo);
            exit();
        }
    }
    
    /**
     * Require specific role
     */
    public function requireRole($role, $redirectTo = '/admin/dashboard.php') {
        $this->requireAuth();
        
        if (!$this->hasRole($role)) {
            $_SESSION['error'] = "Access denied. $role privileges required.";
            header("Location: " . $redirectTo);
            exit();
        }
    }
    
    /**
     * Require specific designation
     */
    public function requireDesignation($designation, $redirectTo = '/admin/dashboard.php') {
        $this->requireAuth();
        
        if (!$this->hasDesignation($designation)) {
            $_SESSION['error'] = "Access denied. $designation designation required.";
            header("Location: " . $redirectTo);
            exit();
        }
    }
    
    /**
     * Require specific permission
     */
    public function requirePermission($permission, $redirectTo = '/admin/dashboard.php') {
        $this->requireAuth();
        
        if (!$this->hasPermission($permission)) {
            $_SESSION['error'] = "Access denied. $permission permission required.";
            header("Location: " . $redirectTo);
            exit();
        }
    }
    
    /**
     * Log user access attempts
     */
    private function logAccess($officerId, $action, $success) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO access_logs (officerid, action, success, ip_address, timestamp)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $officerId,
                $action,
                $success ? 1 : 0,
                $this->getClientIP()
            ]);
        } catch (PDOException $e) {
            error_log("Failed to log access: " . $e->getMessage());
        }
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 
               $_SERVER['HTTP_X_REAL_IP'] ?? 
               $_SERVER['REMOTE_ADDR'] ?? 
               'Unknown';
    }
    
    /**
     * Logout user
     */
    public function logout() {
        if ($this->isAuthenticated()) {
            $this->logAccess($_SESSION['officerid'], 'LOGOUT', true);
        }
        
        session_destroy();
        session_start();
        session_regenerate_id(true);
        
        // Clear all session variables
        $_SESSION = [];
        
        // Destroy session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
    }
    
    /**
     * Get user role level
     */
    public function getUserLevel() {
        $user = $this->getCurrentUser();
        return self::ROLES[$user['rank']]['level'] ?? 0;
    }
    
    /**
     * Check if user is admin
     */
    public function isAdmin() {
        return $this->hasRole('ADMIN');
    }
    
    /**
     * Get available dashboard URL based on user role
     */
    public function getDashboardUrl() {
        $user = $this->getCurrentUser();
        if (!$user) return '/login.php';
        
        switch ($user['rank']) {
            case 'ADMIN':
                return '/admin/dashboard.php';
            case 'Chief Inspector':
                return '/admin/dashboard.php';
            case 'Inspector':
                return '/admin/dashboard.php';
            default:
                return '/dashboard/standard.php';
        }
    }
}

// Convenience functions
function auth() {
    return new AuthManager();
}

function requireAuth($redirectTo = '/login.php') {
    auth()->requireAuth($redirectTo);
}

function requireRole($role, $redirectTo = '/admin/dashboard.php') {
    auth()->requireRole($role, $redirectTo);
}

function requireDesignation($designation, $redirectTo = '/admin/dashboard.php') {
    auth()->requireDesignation($designation, $redirectTo);
}

function isAuthenticated() {
    return auth()->isAuthenticated();
}

function getCurrentUser() {
    return auth()->getCurrentUser();
}

function hasRole($role) {
    return auth()->hasRole($role);
}

function hasDesignation($designation) {
    return auth()->hasDesignation($designation);
}
?>