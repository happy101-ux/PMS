<?php
session_start();

// Debug: Check what's happening
error_log("Login attempt started");

// Check if database.php exists in the correct location
$databasePath = __DIR__ . '/config/database.php';
if (!file_exists($databasePath)) {
    die("Database configuration file not found at: " . $databasePath);
}

require_once $databasePath;

$success = "";
$error   = "";

// When the form is submitted
if (isset($_POST['login'])) {
    $officerid = trim($_POST['username'] ?? '');
    $password  = trim($_POST['pwd'] ?? '');

    error_log("Login attempt for officer: " . $officerid);

    if (empty($officerid) || empty($password)) {
        $error = "Please enter both Officer ID and Password.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM userlogin WHERE officerid = ? AND disabled = 0 LIMIT 1");
            $stmt->execute([$officerid]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                error_log("User found: " . $user['first_name'] . " " . $user['last_name']);
                $db_pass = $user['password'];
                $isValid = false;

                // 1. Check bcrypt
                if (substr($db_pass, 0, 4) === '$2y$') {
                    $isValid = password_verify($password, $db_pass);
                    error_log("BCrypt verification: " . ($isValid ? "SUCCESS" : "FAILED"));
                }
                // 2. Check sha1
                elseif (strlen($db_pass) === 40 && ctype_xdigit($db_pass)) {
                    $isValid = (sha1($password) === $db_pass);
                    error_log("SHA1 verification: " . ($isValid ? "SUCCESS" : "FAILED"));
                }
                // 3. Check plain text fallback
                else {
                    $isValid = ($password === $db_pass);
                    error_log("Plain text verification: " . ($isValid ? "SUCCESS" : "FAILED"));
                }

                if ($isValid) {
                    // Upgrade to bcrypt if not already
                    if (substr($db_pass, 0, 4) !== '$2y$') {
                        $newHash = password_hash($password, PASSWORD_BCRYPT);
                        $upd = $pdo->prepare("UPDATE userlogin SET password = ? WHERE officerid = ?");
                        $upd->execute([$newHash, $officerid]);
                    }

                    // Secure the session
                    session_regenerate_id(true);

                    // Set session variables
                    $_SESSION['officerid']   = $user['officerid'];
                    $_SESSION['first_name']  = $user['first_name'];
                    $_SESSION['last_name']   = $user['last_name'];
                    $_SESSION['rank']        = $user['rank'];
                    $_SESSION['designation'] = $user['designation'];
                    $_SESSION['gender']      = $user['gender'];

                    error_log("Session set for: " . $_SESSION['officerid']);
                    error_log("Redirecting to dashboard...");

                    // Redirect to dashboard - FIXED PATH
                    header("Location: admin/dashboard.php");
                    exit();
                } else {
                    $error = "Invalid Officer ID or Password.";
                    error_log("Password verification failed");
                }
            } else {
                $error = "Invalid Officer ID or Password.";
                error_log("User not found in database");
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
            error_log("Database error: " . $e->getMessage());
        }
    }
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
        .login-body {
            padding: 2.5rem;
        }
        /* ... rest of your CSS ... */
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <i class="bi bi-shield-check"></i>
            <h3 class="mb-2">Police Management System</h3>
            <p class="mb-0 opacity-85">Secure Officer Authentication</p>
        </div>
        
        <div class="login-body">
            <form method="POST" id="loginForm">
                <div class="mb-4">
                    <label for="un" class="form-label">
                        <i class="bi bi-person-badge me-2"></i>Officer ID
                    </label>
                    <input type="text" class="form-control" id="un" name="username" 
                           placeholder="Enter your Officer ID" required autofocus>
                </div>

                <div class="mb-4">
                    <label for="pwd" class="form-label">
                        <i class="bi bi-key me-2"></i>Password
                    </label>
                    <div class="password-container">
                        <input type="password" class="form-control" id="pwd" name="pwd" 
                               placeholder="Enter your password" required>
                        <button type="button" id="togglePwd" class="password-toggle">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="d-grid mb-3">
                    <button type="submit" name="login" class="btn btn-login">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Access System
                    </button>
                </div>

                <?php if ($error != ""): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Authentication Failed:</strong> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
    </script>
</body>
</html>