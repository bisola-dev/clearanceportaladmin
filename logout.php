<?php
session_start();

// Check if this is an admin/HOD logout or student logout
$isAdminLogout = isset($_SESSION['admin_matric']) || isset($_SESSION['hod_matric']) || isset($_SESSION['requestnameid']);

session_unset();
session_destroy();

// Redirect based on user type
if ($isAdminLogout) {
    header("Location: index.php");  // Admin login page
} else {
    header("Location: index.php");  // Student login page (index.php)
}
exit();
?>
