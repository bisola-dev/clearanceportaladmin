<?php
include 'cann.php';

$sql = "ALTER TABLE [Final_Clearance].[dbo].[ClearanceAdmins] DROP COLUMN requestnameid";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    echo "Error dropping column: ";
    print_r(sqlsrv_errors());
} else {
    echo "Column 'requestnameid' dropped successfully.";
}

sqlsrv_close($conn);
?>