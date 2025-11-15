<?php
include 'cann.php';

$query = "SELECT id, username, password FROM [Final_Clearance].[dbo].[ClearanceAdmins]";
$stmt = sqlsrv_query($conn, $query);

if ($stmt === false) {
    echo "Error: ";
    print_r(sqlsrv_errors());
} else {
    echo "<table border='1'><tr><th>ID</th><th>Username</th><th>Password Hash</th><th>RequestNameID</th></tr>";
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        echo "<tr><td>" . $row['id'] . "</td><td>" . $row['username'] . "</td><td>" . $row['password'] . "</td><td>" . $row['requestnameid'] . "</td></tr>";
    }
    echo "</table>";
}

sqlsrv_close($conn);
?>