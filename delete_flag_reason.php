<?php
require_once("cann.php");
require_once("check.php");

// CSRF check
$csrf_token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($csrf_token !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo "CSRF token mismatch.";
    exit();
}

if (!isset($_POST['matric'], $_POST['requestnameid'])) {
    http_response_code(400);
    echo "Missing parameters.";
    exit();
}

$matric = trim($_POST['matric']);
$requestnameid = (int) $_POST['requestnameid'];

if ($matric === "" || !$requestnameid) {
    http_response_code(400);
    echo "Invalid parameters.";
    exit();
}

// Clear the Remark field by setting it to NULL or empty string
$sql = "UPDATE [Final_Clearance].[dbo].[Clearance_Request] SET Remark = NULL WHERE MatricNo = ? AND requestnameid = ?";
$params = [$matric, $requestnameid];

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt === false) {
    http_response_code(500);
    echo "Database error: Could not delete reason.";
    exit();
}

echo "Flagged reason successfully deleted.";
?>
