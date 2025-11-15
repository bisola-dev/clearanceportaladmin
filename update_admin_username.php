
<?php
include 'cann.php';

// Update the username for requestnameid 1 (Department Head) to 'hod_admin'
$sql = "UPDATE [Final_Clearance].[dbo].[ClearanceAdmins] SET username = 'hod_admin' WHERE requestnameid = 1";

$stmt = sqlsrv_query($conn, $sql);
if ($stmt === false) {
    echo "Error updating username: ";
    print_r(sqlsrv_errors());
} else {
