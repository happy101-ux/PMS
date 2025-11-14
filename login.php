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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #7e22ce 50%, #9333ea 75%, #3b82f6 100%);
            background-size: 400% 400%;
            animation: gradientShift 20s ease infinite;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            position: relative;
            overflow: hidden;
            padding: 20px;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        body::before {
            content: '';
            position: fixed;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(255, 255, 255, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(255, 255, 255, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(255, 255, 255, 0.06) 0%, transparent 50%);
            animation: float 25s ease-in-out infinite;
            pointer-events: none;
            z-index: 0;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-30px) rotate(5deg); }
        }

        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 480px;
            animation: slideUp 0.8s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-card {
            background: rgba(255, 255, 255, 0.12);
            border-radius: 25px;
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 
                0 12px 40px rgba(0, 0, 0, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
        }

        .login-header {
            background: rgba(255, 255, 255, 0.1);
            padding: 40px 30px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 20px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(255, 255, 255, 0.3);
            animation: pulse 3s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .login-header h2 {
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 10px;
            letter-spacing: 1px;
        }

        .login-header p {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 300;
        }

        .login-body {
            padding: 40px 35px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            font-size: 0.95rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.95);
            margin-bottom: 10px;
            letter-spacing: 0.5px;
        }

        .form-label i {
            margin-right: 8px;
            font-size: 1.1rem;
        }

        .input-wrapper {
            position: relative;
        }

        .form-control {
            width: 100%;
            padding: 15px 20px;
            padding-right: 50px;
            font-size: 1rem;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            color: #fff;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .form-control:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.8);
            cursor: pointer;
            font-size: 1.2rem;
            padding: 5px;
            transition: color 0.3s ease;
            z-index: 10;
        }

        .password-toggle:hover {
            color: #fff;
        }

        .password-toggle:focus {
            outline: none;
        }

        .btn-login {
            width: 100%;
            padding: 16px 32px;
            font-size: 1.1rem;
            font-weight: 700;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-align: center;
            border: none;
            cursor: pointer;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            box-shadow: 
                0 6px 20px rgba(102, 126, 234, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            transform: translateY(-3px) scale(1.02);
            box-shadow: 
                0 10px 30px rgba(102, 126, 234, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
        }

        .btn-login:active {
            transform: translateY(-1px) scale(0.98);
            box-shadow: 
                0 4px 15px rgba(102, 126, 234, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }

        .btn-login i {
            margin-right: 8px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-top: 20px;
            animation: shake 0.5s ease;
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.5);
            color: #fff;
            backdrop-filter: blur(10px);
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .alert i {
            margin-right: 8px;
            font-size: 1.1rem;
        }

        .back-link {
            text-align: center;
            margin-top: 25px;
        }

        .back-link a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 0.95rem;
            transition: color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .back-link a:hover {
            color: #fff;
        }

        .back-link a i {
            font-size: 0.9rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .login-header {
                padding: 30px 25px;
            }

            .login-header h2 {
                font-size: 1.5rem;
            }

            .login-body {
                padding: 30px 25px;
            }

            .logo-icon {
                width: 70px;
                height: 70px;
                font-size: 35px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 15px;
            }

            .login-header {
                padding: 25px 20px;
            }

            .login-body {
                padding: 25px 20px;
            }

            .login-header h2 {
                font-size: 1.3rem;
            }

            .form-control {
                padding: 12px 18px;
                padding-right: 45px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-icon">🚔</div>
                <h2>Police Management System</h2>
                <p>Secure Officer Authentication</p>
            </div>
            
            <div class="login-body">
                <form method="POST" id="loginForm">
                    <div class="form-group">
                        <label for="un" class="form-label">
                            <i class="bi bi-person-badge"></i>Officer ID
                        </label>
                        <div class="input-wrapper">
                            <input type="text" class="form-control" id="un" name="username" 
                                   placeholder="Enter your Officer ID" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="pwd" class="form-label">
                            <i class="bi bi-key"></i>Password
                        </label>
                        <div class="input-wrapper">
                            <input type="password" class="form-control" id="pwd" name="pwd" 
                                   placeholder="Enter your password" required>
                            <button type="button" id="togglePwd" class="password-toggle">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" name="login" class="btn-login">
                        <i class="bi bi-box-arrow-in-right"></i>Access System
                    </button>

                    <?php if ($error != ""): ?>
                        <div class="alert">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <strong>Authentication Failed:</strong> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                </form>

                <div class="back-link">
                    <a href="index.php">
                        <i class="bi bi-arrow-left"></i>Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>

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