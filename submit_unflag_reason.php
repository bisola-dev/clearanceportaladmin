<?php
require_once("cann.php");  
require_once("check.php"); 

header('Content-Type: text/plain');

// CSRF check
$csrf_token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($csrf_token !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo "CSRF token mismatch.";
    exit();
}

// Check for required POST parameters
if (!isset($_POST['matricNo'], $_POST['reason'], $_POST['requestnameid'])) {
    http_response_code(400);
    echo "Missing parameters.";
    exit();
}

$matric = trim($_POST['matricNo']); // Changed from currentFlagMatric to matricNo
$reason = trim($_POST['reason']);
$requestnameid = (int) $_POST['requestnameid']; // Changed from currentRequestId to requestnameid


// Input validation
if ($matric === "" || $reason === "" || !$requestnameid) {
    http_response_code(400);
    echo "Matric number, reason, and requestnameid cannot be empty.";
    exit();
}

// Optional: Validate matric number format
if (!preg_match('/^[A-Za-z0-9\/\-]+$/', $matric)) {
    http_response_code(400);
    echo "Invalid matric number format.";
    exit();
}

// Check if record exists and if already flagged
$checkSql = "SELECT Remark FROM [Final_Clearance].[dbo].[Clearance_Request] 
             WHERE MatricNo = ? AND requestnameid = ?";
$checkStmt = sqlsrv_query($conn, $checkSql, [$matric, $requestnameid]);

if ($checkStmt === false) {
    http_response_code(500);
    echo "Database error during check.";
    exit();
}

$current = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
if (!$current) {
    http_response_code(404);
    echo "Student record not found for this request.";
    exit();
}

if (!empty($current['Remark'])) {
    http_response_code(409); 
    echo "Student is already flagged for this section.";
    exit();
}

// Update the remark field
$updateSql = "UPDATE [Final_Clearance].[dbo].[Clearance_Request] 
              SET Remark = ? WHERE MatricNo = ? AND requestnameid = ?";
$updateParams = [$reason, $matric, $requestnameid];

$updateStmt = sqlsrv_query($conn, $updateSql, $updateParams);

if ($updateStmt === false) {
    http_response_code(500);
    echo "Database error: Could not save reason.";
    exit();
}

echo "Reason successfully submitted.";
?>
