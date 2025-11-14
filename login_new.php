<?php
/**
 * Updated Login Page for Police Management System
 * Uses the new modular authentication system
 */

// Start session
session_start();

// Include the new authentication system
require_once __DIR__ . '/app/core/AuthManager.php';

$error = "";
$success = "";

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $officerid = trim($_POST['username'] ?? '');
    $password = trim($_POST['pwd'] ?? '');
    
    if (empty($officerid) || empty($password)) {
        $error = "Please enter both Officer ID and Password.";
    } else {
        try {
            $auth = auth();
            if ($auth->authenticate($officerid, $password)) {
                // Redirect to appropriate dashboard
                header("Location: " . $auth->getDashboardUrl());
                exit();
            } else {
                $error = "Invalid Officer ID or Password.";
            }
        } catch (Exception $e) {
            $error = "Login error: " . $e->getMessage();
        }
    }
}

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    $auth = auth();
    header("Location: " . $auth->getDashboardUrl());
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Police Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 420px;
            width: 100%;
            margin: 20px;
        }
        .login-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 2.5rem 1rem;
            text-align: center;
            position: relative;
        }
        .login-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
        }
        .login-header h3 {
            position: relative;
            z-index: 1;
        }
        .login-body {
            padding: 2.5rem;
        }
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #3498db, #2980b9);
            border: none;
            border-radius: 8px;
            padding: 0.75rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #2980b9, #1f5f8b);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
            color: white;
        }
        .password-container {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 0.25rem;
        }
        .password-toggle:hover {
            color: #495057;
        }
        .alert {
            border: none;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .system-info {
            text-align: center;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            margin-top: 1rem;
        }
        .system-info small {
            color: rgba(255, 255, 255, 0.8);
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <i class="bi bi-shield-check fs-1 mb-3"></i>
            <h3 class="mb-2">Police Management System</h3>
            <p class="mb-0 opacity-85">Secure Officer Authentication</p>
        </div>
        
        <div class="login-body">
            <form method="POST" id="loginForm">
                <div class="mb-4">
                    <label for="username" class="form-label">
                        <i class="bi bi-person-badge me-2"></i>Officer ID
                    </label>
                    <input type="text" class="form-control" id="username" name="username" 
                           placeholder="Enter your Officer ID" required autofocus 
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>

                <div class="mb-4">
                    <label for="pwd" class="form-label">
                        <i class="bi bi-key me-2"></i>Password
                    </label>
                    <div class="password-container">
                        <input type="password" class="form-control" id="pwd" name="pwd" 
                               placeholder="Enter your password" required>
                        <button type="button" class="password-toggle" id="togglePwd">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="d-grid mb-3">
                    <button type="submit" name="login" class="btn btn-login">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Access System
                    </button>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Authentication Failed:</strong> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
            </form>
            
            <div class="system-info">
                <small>
                    <i class="bi bi-info-circle me-1"></i>
                    For technical support, contact IT Department<br>
                    <i class="bi bi-shield-lock me-1"></i>
                    All activities are logged and monitored
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password toggle functionality
        document.getElementById('togglePwd').addEventListener('click', function() {
            const pwd = document.getElementById('pwd');
            const icon = this.querySelector('i');
            
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                pwd.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        });

        // Form submission loading state
        document.getElementById('loginForm').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Authenticating...';
            submitBtn.disabled = true;
        });

        // Auto-focus username field if empty
        const usernameField = document.getElementById('username');
        if (!usernameField.value) {
            usernameField.focus();
        }
    </script>
</body>
</html>