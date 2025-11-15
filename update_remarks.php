<?php
require_once("cann.php");
require_once("check.php");

// New remarks array
$newRemarks = [
    1 => "uncleared from department head",
    2 => "uncleared from library",
    3 => "uncleared from student affairs",
    4 => "uncleared from school officer",
    5 => "uncleared from academic gown",
    6 => "uncleared from bursary"
];

$updatedCount = 0;
$errorMessages = [];

foreach ($newRemarks as $requestnameid => $newRemark) {
    $updateQuery = "UPDATE [Final_Clearance].[dbo].[Clearance_Request] SET remark = ? WHERE requestnameid = ? AND status = 0";
    $updateStmt = sqlsrv_query($conn, $updateQuery, [$newRemark, $requestnameid]);

    if ($updateStmt) {
        $rowsAffected = sqlsrv_rows_affected($updateStmt);
        $updatedCount += $rowsAffected;
        echo "Updated $rowsAffected records for section $requestnameid to '$newRemark'.<br>";
    } else {
        $errorMessages[] = "Failed to update records for section $requestnameid: " . print_r(sqlsrv_errors(), true);
    }
}

echo "<br>Total records updated: $updatedCount<br>";

if (!empty($errorMessages)) {
    echo "<br>Errors:<br>" . implode("<br>", $errorMessages);
} else {
    echo "<br>All updates completed successfully.";
}
?>