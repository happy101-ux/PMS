<?php
// case_allocation_security.php
session_start();
require_once '../config/database.php';
require_once '../RoleManager.php';

if (!isset($_SESSION['officerid'])) {
    header("Location: ../login.php");
    exit();
}

$officerid = $_SESSION['officerid'];
$roleManager = new RoleManager($pdo, $officerid);

// Check if user has CID, CID Superior, or ADMIN access
if (!$roleManager->hasDesignation('CID') && !$roleManager->isAdmin() && $roleManager->getUserRole() !== 'cid_superior') {
    $_SESSION['error'] = "Access denied. CID, CID Superior, or ADMIN privileges required.";
    header("Location: ../admin/dashboard.php");
    exit();
}

// Simple logging
error_log("Case Allocation accessed by: " . $officerid . " at " . date('Y-m-d H:i:s'));
?>