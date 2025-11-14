<?php
session_start();
require_once '../config/database.php'; // defines $dpo

$officerid = $_SESSION['officerid'] ?? null;
$fullName = 'Officer';
$userType = 'User';
$gender   = 'Not set';

if ($officerid) {
    $stmt = $dpo->prepare("SELECT first_name, last_name, rank, designation, gender FROM userlogin WHERE officerid = ?");
    $stmt->bind_param("s", $officerid);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $fullName = $row['first_name'] . ' ' . $row['last_name'];
        $userType = $row['rank'] . ' (' . $row['designation'] . ')';
        $gender   = $row['gender'];
    }

    $stmt->close();
}
?>

<div class="main-content p-4" id="mainContent">
  <h1>Welcome, <?= htmlspecialchars($fullName) ?>!</h1>
  <p>Type: <?= htmlspecialchars($userType) ?></p>
  <p>Gender: <?= htmlspecialchars($gender) ?></p>
</div>
