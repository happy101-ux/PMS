<?php
session_start();
$_SESSION = [];
session_destroy();
header("Location: ../login.php"); // Redirect to login page
exit();
?>
