<?php
// ================================
// Database Configuration
// ================================
$host = "localhost";   // Database host
$username = "root";    // Default username (XAMPP)
$password = "";        // Default password (usually empty)
$dbname = "pms";       // Your database name

// ================================
// ✅ MySQLi Connection (for old modules)
// ================================
$conn = new mysqli($host, $username, $password, $dbname);

// Check MySQLi connection
if ($conn->connect_error) {
    die("MySQLi connection failed: " . $conn->connect_error);
}

// ================================
// ✅ PDO Connection (for new modules)
// ================================
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("PDO connection failed: " . $e->getMessage());
}

// ================================
// Both $conn (MySQLi) and $pdo (PDO) are now available
// ================================

?>
