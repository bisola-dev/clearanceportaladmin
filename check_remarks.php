<?php
require_once("cann.php");
require_once("check.php");

$query = "SELECT DISTINCT requestnameid, remark, COUNT(*) as count FROM [Final_Clearance].[dbo].[Clearance_Request] WHERE status = 0 GROUP BY requestnameid, remark ORDER BY requestnameid, remark";
$stmt = sqlsrv_query($conn, $query);

if ($stmt) {
    echo "<table border='1'><tr><th>Section</th><th>Remark</th><th>Count</th></tr>";
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        echo "<tr><td>{$row['requestnameid']}</td><td>'{$row['remark']}'</td><td>{$row['count']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . print_r(sqlsrv_errors(), true);
}
}
?>
