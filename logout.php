<?php
session_start();
session_unset();   // Clear all session variables (safer)
session_destroy(); // Destroy the session completely
header("Location: login.php");
exit();
?>