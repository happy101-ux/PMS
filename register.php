<?php
session_start();
require_once __DIR__ . '/config/database.php'; // DB connection

$success = "";
$error = "";

// When the form is submitted
if (isset($_POST['register'])) {
    $officerid = trim($_POST['officerid']);
    $status = trim($_POST['status']);
    $password = trim($_POST['password']);
    $last_name = trim($_POST['last_name']);
    $first_name = trim($_POST['first_name']);

    // Escape inputs
    $officerid = mysqli_real_escape_string($conn, $officerid);
    $status = mysqli_real_escape_string($conn, $status);
    $last_name = mysqli_real_escape_string($conn, $last_name);
    $first_name = mysqli_real_escape_string($conn, $first_name);

    // Hash password using SHA1
    $hashedPassword = sha1($password);

    // Check if officerid exists
    $check = mysqli_query($conn, "SELECT officerid FROM userlogin WHERE officerid = '$officerid' LIMIT 1");
    if (mysqli_num_rows($check) > 0) {
        $error = "Officer ID already exists!";
    } else {
        // Insert new user
        $sql = "INSERT INTO userlogin (officerid, status, password, last_name, first_name) 
                VALUES ('$officerid', '$status', '$hashedPassword', '$last_name', '$first_name')";
        if (mysqli_query($conn, $sql)) {
            $success = "Registration successful! You can now log in.";
            header("refresh:2;url=login.php"); // Redirect after 2 seconds
        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}
?>

<?php include 'components/head.php'; ?>

<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow rounded-4">
                <div class="card-header bg-primary text-white text-center rounded-top-4">
                    <h4 class="mb-0">Create New Account</h4>
                </div>
                <div class="card-body">

                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form action="" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Officer ID</label>
                            <input type="text" name="officerid" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                <option value="">Select Status</option>
                                <option value="Admin">Admin</option>
                                <option value="Chief Inspector">Chief Inspector</option>
                                <option value="Inspector">Inspector</option>
                                <option value="Sergeant">Sergeant</option>
                                <option value="Constable">Constable</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Surname</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Other Names</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>

                        <div class="d-grid">
                            <button type="submit" name="register" class="btn btn-primary">Register</button>
                        </div>
                    </form>

                    <div class="text-center mt-3">
                        Already have an account? <a href="login.php">Login here</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom CSS -->
<style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .card {
        border-radius: 1rem;
    }
    .card-header {
        border-top-left-radius: 1rem;
        border-top-right-radius: 1rem;
    }
    .form-control, .form-select {
        border-radius: 0.5rem;
        transition: all 0.3s ease;
    }
    .form-control:focus, .form-select:focus {
        box-shadow: 0 0 5px rgba(0, 123, 255, 0.5);
        border-color: #0d6efd;
    }
</style>

<!-- Bootstrap JS Bundle -->
<script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
