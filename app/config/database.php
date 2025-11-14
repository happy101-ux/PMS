<?php
/**
 * Database Configuration and Connection Manager
 * Centralized database handling for the Police Management System
 */

class DatabaseManager {
    private $host;
    private $username;
    private $password;
    private $database;
    private $connection;
    private $pdo;
    
    // Singleton instance
    private static $instance = null;
    
    private function __construct() {
        $this->host = "localhost";
        $this->username = "root";
        $this->password = "";
        $this->database = "pms";
        
        $this->initializeConnections();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize database connections
     */
    private function initializeConnections() {
        try {
            // MySQLi connection (legacy support)
            $this->connection = new mysqli($this->host, $this->username, $this->password, $this->database);
            
            if ($this->connection->connect_error) {
                throw new Exception("MySQLi connection failed: " . $this->connection->connect_error);
            }
            
            // PDO connection (modern approach)
            $this->pdo = new PDO(
                "mysql:host={$this->host};dbname={$this->database};charset=utf8mb4", 
                $this->username, 
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new Exception("Database connection failed. Please check configuration.");
        }
    }
    
    /**
     * Get MySQLi connection (legacy support)
     */
    public function getMySQLiConnection() {
        return $this->connection;
    }
    
    /**
     * Get PDO connection (recommended)
     */
    public function getPDOConnection() {
        return $this->pdo;
    }
    
    /**
     * Test database connection
     */
    public function testConnection() {
        try {
            $stmt = $this->pdo->query("SELECT 1");
            return $stmt !== false;
        } catch (PDOException $e) {
            error_log("Connection test failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Close connections
     */
    public function closeConnections() {
        if ($this->connection) {
            $this->connection->close();
        }
        $this->connection = null;
        $this->pdo = null;
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Convenience functions for backward compatibility
function getDatabaseConnection() {
    return DatabaseManager::getInstance();
}

function getPDO() {
    return DatabaseManager::getInstance()->getPDOConnection();
}

function getMySQLi() {
    return DatabaseManager::getInstance()->getMySQLiConnection();
}
?>