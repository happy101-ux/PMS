<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../RoleManager.php';

// Redirect if not logged in
if (!isset($_SESSION['officerid'])) {
    header("Location: ../login.php");
    exit();
}

$officerid = $_SESSION['officerid'];
$roleManager = new RoleManager($pdo);
$userInfo = $roleManager->getUserRank($officerid);

// User info
$userType = $userInfo['rank'] ?? 'User';
$username = htmlspecialchars($userInfo['last_name'] . ' ' . $userInfo['first_name']);
?>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark px-3">
    <div class="container-fluid">
        <!-- Brand -->
        <a class="navbar-brand fw-bold" href="..admin/enhanced_dashboard.php">
            <i class="bi bi-shield-check"></i> Police Management System
        </a>

        <!-- Mobile Toggle -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navigation Links -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="../admin/enhanced_dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="../cp">
                        <i class="bi bi-list-task"></i> My Duties
                    </a>
                </li>
                <!-- Admin Tools -->
                <?php if ($roleManager->hasAccess($officerid, 'Chief Inspector')): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-tools"></i> Admin Tools
                    </a>
                    <ul class="dropdown-menu">
                        <?php if ($roleManager->hasAccess($officerid, 'Chief Inspector')): ?>
                        <li><a class="dropdown-item" href="reports.php">Generate Reports</a></li>
                        <li><a class="dropdown-item" href="resource_management.php">Resource Management</a></li>
                        <?php endif; ?>
                        <?php if ($roleManager->hasAccess($officerid, 'ADMIN')): ?>
                        <li><a class="dropdown-item" href="user_management.php">User Management</a></li>
                        <li><a class="dropdown-item" href="system_settings.php">System Settings</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>

            <!-- Right Side -->
            <ul class="navbar-nav ms-auto">
                <!-- Constable Tools -->
                <?php if (!$roleManager->hasAccess($officerid, 'Sergeant')): ?>
                <li class="nav-item">
                    <a class="nav-link" href="request_resources.php">
                        <i class="bi bi-tools"></i> Request Resources
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="my_investigations.php">
                        <i class="bi bi-search"></i> My Investigations
                    </a>
                </li>
                <?php endif; ?>

                <!-- User Menu -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?= $username ?>
                        <span class="badge bg-secondary ms-1"><?= $userType ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="../components/edit_profile.php">
                            <i class="bi bi-person"></i> Profile
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../login.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>