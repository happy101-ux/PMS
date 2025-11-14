<?php
/**
 * Logout Handler for Police Management System
 * Uses the new modular authentication system
 */

// Start session
session_start();

// Include the new authentication system
require_once __DIR__ . '/app/core/AuthManager.php';

try {
    $auth = auth();
    
    // Perform logout
    $auth->logout();
    
    // Set success message
    $_SESSION['success'] = "You have been successfully logged out.";
    
    // Redirect to login page
    header("Location: login_new.php");
    exit();
    
} catch (Exception $e) {
    // Log the error
    error_log("Logout error: " . $e->getMessage());
    
    // Even if there's an error, redirect to login
    session_destroy();
    header("Location: login_new.php");
    exit();
}
?>