<?php
include 'cann.php';

$username = 'admin_section1';
$new_password = 'please';
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

$sql = "UPDATE [Final_Clearance].[dbo].[ClearanceAdmins] SET password = ? WHERE username = ?";
$params = [$hashed_password, $username];

$stmt = sqlsrv_prepare($conn, $sql, $params);
if ($stmt === false) {
    echo "Error preparing statement: ";
    print_r(sqlsrv_errors());
} else {
    if (sqlsrv_execute($stmt)) {
        echo "Password updated successfully for $username to '$new_password'.";
    } else {
        echo "Error updating password: ";
        print_r(sqlsrv_errors());
    }
}

sqlsrv_close($conn);
?>