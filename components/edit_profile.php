<?php
session_start();
require_once '../config/database.php';

// Ensure user is logged in
if (!isset($_SESSION['officerid'])) {
    exit("<p class='text-danger'>Not authorized.</p>");
}

$officerid = $_SESSION['officerid'];
$success = $error = "";

// Fetch user details
$stmt = $conn->prepare("SELECT * FROM userlogin WHERE officerid=?");
$stmt->bind_param("s", $officerid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// --- Handle password update ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['updatePassword'])) {
    $oldPassword = trim($_POST['old_password']);
    $newPassword = trim($_POST['password']);
    $confirmPassword = trim($_POST['confirm_password']);

    if (!password_verify($oldPassword, $user['password'])) {
        $error = "Old password is incorrect.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (strlen($newPassword) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE userlogin SET password=? WHERE officerid=?");
        $stmt->bind_param("ss", $hashedPassword, $officerid);
        $stmt->execute();
        $stmt->close();
        $success = "Password updated successfully!";
    }
}

// --- Handle photo update ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['updatePhoto'])) {
    $uploadDir = "../uploads/";
    if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }

    $fileName = time() . "_" . basename($_FILES['profile_photo']['name']);
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $targetPath)) {
        $stmt = $conn->prepare("UPDATE userlogin SET photo=? WHERE officerid=?");
        $stmt->bind_param("ss", $fileName, $officerid);
        $stmt->execute();
        $stmt->close();
        $success = "Profile photo updated successfully!";
        $user['photo'] = $fileName;
    } else {
        $error = "Failed to upload photo.";
    }
}
?>

<!-- Slide-in Profile Panel -->
<div id="profilePanel" style="
    position: fixed;
    top: 0;
    right: -400px;
    width: 400px;
    height: 100%;
    background: #fff;
    box-shadow: -2px 0 8px rgba(0,0,0,0.3);
    transition: right 0.3s ease;
    overflow-y: auto;
    z-index: 1050;
">
    <div style="padding:1rem; display:flex; justify-content:space-between; align-items:center; background:#343a40; color:#fff;">
        <h5>Profile</h5>
        <button id="closeProfile" style="background:none; border:none; color:#fff; font-size:1.2rem;">✖</button>
    </div>

    <div style="padding:1rem;">

        <?php if ($success): ?>
          <div class="alert alert-success"><?= $success ?></div>
        <?php elseif ($error): ?>
          <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <!-- Profile Photo & Info -->
        <div class="text-center mb-3">
          <?php if (!empty($user['photo'])): ?>
            <img src="../uploads/<?= htmlspecialchars($user['photo']) ?>" 
                 alt="Profile Photo" class="rounded-circle mb-2"
                 style="width:100px;height:100px;object-fit:cover;">
          <?php else: ?>
            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center mb-2" 
                 style="width:100px;height:100px;">
              <span class="text-white">No Photo</span>
            </div>
          <?php endif; ?>

          <h6 class="mt-2 mb-0"><?= htmlspecialchars($user['rank']) ?> - <?= htmlspecialchars($user['designation']) ?></h6>
          <p class="fw-bold mb-0"><?= htmlspecialchars($user['last_name'] . " " . $user['first_name']) ?></p>
          <p class="text-muted small">ID: <?= htmlspecialchars($user['officerid']) ?> | <?= htmlspecialchars($user['gender']) ?></p>
        </div>

        <!-- Update Photo -->
        <form method="POST" enctype="multipart/form-data" class="mb-3">
          <h6 class="fw-bold text-center">Update Profile Photo</h6>
          <input type="file" name="profile_photo" class="form-control mb-2" accept="image/*" onchange="previewImage(event)">
          <img id="preview" class="img-thumbnail d-none mb-2 mx-auto rounded-circle" style="max-width:100px;">
          <button type="submit" name="updatePhoto" class="btn btn-secondary w-100">Upload Photo</button>
        </form>

        <hr class="my-3">

        <!-- Update Password -->
        <form method="POST">
          <h6 class="fw-bold text-center">Change Password</h6>

          <div class="input-group mb-2">
            <input type="password" name="old_password" placeholder="Old Password" class="form-control" required>
            <button type="button" class="btn btn-outline-secondary toggle-pass">👁</button>
          </div>

          <div class="input-group mb-2">
            <input type="password" name="password" placeholder="New Password" class="form-control" required>
            <button type="button" class="btn btn-outline-secondary toggle-pass">👁</button>
          </div>
          <div class="input-group mb-2">
            <input type="password" name="confirm_password" placeholder="Confirm Password" class="form-control" required>
            <button type="button" class="btn btn-outline-secondary toggle-pass">👁</button>
          </div>
          <button type="submit" name="updatePassword" class="btn btn-primary w-100">Update Password</button>
        </form>
    </div>
</div>

<script>
// Slide-in panel logic
const panel = document.getElementById('profilePanel');
document.getElementById('profileBtn')?.addEventListener('click', e=>{
    e.preventDefault();
    panel.style.right = '0';
});
document.getElementById('closeProfile')?.addEventListener('click', ()=>{
    panel.style.right = '-400px';
});

// Password toggle
document.querySelectorAll('.toggle-pass').forEach(btn => {
  btn.addEventListener('click', function() {
    const input = this.previousElementSibling;
    input.type = input.type === "password" ? "text" : "password";
  });
});

// Preview image before upload
function previewImage(event) {
  const reader = new FileReader();
  reader.onload = function() {
    const output = document.getElementById('preview');
    output.src = reader.result;
    output.classList.remove('d-none');
  };
  reader.readAsDataURL(event.target.files[0]);
}
</script>
