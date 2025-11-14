<?php
// cid_security.php - Simplified version without logging
session_start();
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

// Optional: Simple access logging without the method call
error_log("CID Officer Assignment accessed by: " . $officerid . " at " . date('Y-m-d H:i:s'));
?>