<?php
// functions.php - Helper Functions
session_start();

function isAuthorized($user) {
    // Check if user is ADMIN, Chief Inspector, or has ADMIN designation (OIC equivalent)
    return ($user['rank'] === 'ADMIN' || $user['designation'] === 'ADMIN' || $user['rank'] === 'Chief Inspector');
}

function getCurrentUser() {
    if (isset($_SESSION['user'])) {
        return $_SESSION['user'];
    }
    header("Location: login.php");
    exit();
}
?>