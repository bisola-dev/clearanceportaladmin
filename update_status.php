<?php
require_once("cann.php");
require_once("check.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF check
    $csrf_token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($csrf_token !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo "CSRF token mismatch.";
        exit();
    }

    $matric = $_POST['matric'];
    $status = $_POST['status'];
    $requestid = $_POST['requestnameid'] ?? null;


    // Define remark text based on requestnameid
    $clearedRemarks = [
        1 => "cleared from department head",
        2 => "cleared from library",
        3 => "cleared from student affairs",
        4 => "cleared from school officer",
        5 => "cleared from academic gown",
        6 => "cleared from bursary school fee"
    ];

    $unclearedRemarks = [
        1 => "no clearance from department head",
        2 => "no clearance from library",
        3 => "no clearance from student affairs",
        4 => "no clearance from school officer",
        5 => "no payment of academic gown",
        6 => "no payment of school fees"
    ];

    if ($status == 0) {
        // Clearing the student (i.e., marking status as 1)
        if (!$requestid) {
            echo "❌ Request ID required for clearing.";
            exit();
        }
        $remarkText = isset($clearedRemarks[$requestid]) ? $clearedRemarks[$requestid] : "cleared";
        $update = "UPDATE [Final_Clearance].[dbo].[Clearance_Request]
                    SET status = 1, remark = ?
                    WHERE matricno = ? AND requestnameid = ?";
        $params = array($remarkText, $matric, $requestid);
    } elseif ($status == 1) {
        // Unclear the student (i.e., marking status as 0) - update all sections
        $update = "UPDATE [Final_Clearance].[dbo].[Clearance_Request]
                    SET status = 0, remark = CASE requestnameid
                        WHEN 1 THEN 'uncleared from department head'
                        WHEN 2 THEN 'uncleared from library'
                        WHEN 3 THEN 'uncleared from student affairs'
                        WHEN 4 THEN 'uncleared from school officer'
                        WHEN 5 THEN 'uncleared from academic gown'
                        WHEN 6 THEN 'uncleared from bursary'
                        ELSE 'uncleared'
                    END
                    WHERE matricno = ? AND status = 1";
        $params = array($matric);
    } else {
        echo "❌ Invalid status code.";
        exit();
    }

    $stmt = sqlsrv_query($conn, $update, $params);
    if ($stmt === false) {
    // Generic user message
    echo '<script>alert("An unexpected error occurred. Please try again later.");</script>';

    // Log full details for developer only
    error_log("DB update failed: " . print_r(sqlsrv_errors(), true));

    // Stop execution
    exit;
}
 else {
        if ($status == 0) {
            echo "✅ Student with Matric No: $matric has been successfully qualified.";
        } else {
            echo "⚠️ Student with Matric No: $matric has been successfully disqualified.";
        }
    }
}
?>
