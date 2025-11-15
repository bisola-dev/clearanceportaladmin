<?php
require_once("cann.php");
require_once("check.php");

// Check if user is logged in
if (!isset($_SESSION['requestnameid'])) {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['matric'])) {
    http_response_code(400);
    echo "Matric number not provided.";
    exit();
}

$matric = trim($_GET['matric']);

// Validate matric format
if (!preg_match('/^[A-Za-z0-9\/\-]+$/', $matric)) {
    http_response_code(400);
    echo "Invalid matric number format.";
    exit();
}

$query = "SELECT FileName, FileData FROM [Final_Clearance].[dbo].[UploadedGraduateFile] WHERE MatricNumber = ?";
$stmt = sqlsrv_query($conn, $query, [$matric]);

if ($stmt === false || !($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    http_response_code(404);
    echo "Receipt not found for this student.";
    exit();
}

$fileName = $row['FileName'];
$fileData = $row['FileData'];

if (!$fileData) {
    http_response_code(404);
    echo "File data missing.";
    exit();
}

// Send appropriate headers for PDF
header("Content-Type: application/pdf");
header("Content-Disposition: inline; filename=\"" . $fileName . "\"");
header("Content-Length: " . strlen($fileData));

echo $fileData;
exit();
?>
