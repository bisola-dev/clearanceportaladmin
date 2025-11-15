<?php
require_once("cann.php");
require_once("check.php");

if (!isset($_SESSION['requestnameid'])) {
    header("Location: index.php");
    exit();
}

$requestnameid = $_SESSION['requestnameid'];

if ($requestnameid != 6) {
    header("Location: main_clearance.php");
    exit();
}

$sectionName = "Bursary, School fee";
$remarkText = "no payment of school fees";

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$uploadedData = [];
$existingData = [];
$errorMessage = '';
$successMessage = '';

// Fetch existing disqualified students for no payment
 if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
     $query = "SELECT id, matricno FROM [Final_Clearance].[dbo].[Clearance_Request] WHERE requestnameid = 6 AND remark = ? AND status = 0 ORDER BY id";
     $stmt = sqlsrv_query($conn, $query, [$remarkText]);

    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $existingData[] = [
                'id' => $row['id'],
                'matric_number' => htmlspecialchars($row['matricno'])
            ];
        }
    } else {
        $errorMessage = "Database query failed.";
    }
}

if (!empty($existingData)) {
    $successMessage = "Found " . count($existingData) . " existing disqualified records for no payment.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = intval($_POST['delete_id']);
    $deleteQuery = "DELETE FROM [Final_Clearance].[dbo].[Clearance_Request] WHERE id = ? AND requestnameid = 6 AND remark = ?";
    $deleteStmt = sqlsrv_query($conn, $deleteQuery, [$deleteId, $remarkText]);

    if ($deleteStmt && sqlsrv_rows_affected($deleteStmt) > 0) {
        $successMessage = "Record deleted successfully.";
    } else {
        $errorMessage = "Error deleting record from database or record not found.";
    }

    $existingData = [];
     $query = "SELECT id, matricno FROM [Final_Clearance].[dbo].[Clearance_Request] WHERE requestnameid = 6 AND remark = ? AND status = 0 ORDER BY id";
     $stmt = sqlsrv_query($conn, $query, [$remarkText]);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $existingData[] = [
                'id' => $row['id'],
                'matric_number' => htmlspecialchars($row['matricno'])
            ];
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excelFile'])) {
    $file = $_FILES['excelFile'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessage = 'File upload error.';
    } else {
        $spreadsheet = null;
        try {
            $fileType = IOFactory::identify($file['tmp_name']);
            $reader = IOFactory::createReader($fileType);
            $spreadsheet = $reader->load($file['tmp_name']);
        } catch (Exception $e) {
            $errorMessage = 'Invalid file format. Please upload a valid Excel file (.xlsx or .xls).';
        }

        if ($spreadsheet !== null) {
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            $firstRow = $rows[0] ?? [];
            $isHeaderRow = false;

            if (!empty($firstRow[0]) && (stripos($firstRow[0], 'matric') !== false || stripos($firstRow[0], 'number') !== false || stripos($firstRow[0], 'list of') !== false)) {
                $isHeaderRow = true;
                array_shift($rows);
            }

            $insertedCount = 0;
            $rowIndex = 1;

            foreach ($rows as $row) {
                if (!empty($row[0])) {
                    $matricNumber = trim($row[0]);

                    if (stripos($matricNumber, 'matric') !== false || stripos($matricNumber, 'number') !== false || stripos($matricNumber, 'list of disqualified') !== false) {
                        continue;
                    }

                    $checkQuery = "SELECT id FROM [Final_Clearance].[dbo].[Clearance_Request] WHERE matricno = ? AND requestnameid = 6 AND remark = ?";
                     $checkStmt = sqlsrv_query($conn, $checkQuery, [$matricNumber, $remarkText]);

                    if ($checkStmt && !sqlsrv_has_rows($checkStmt)) {
                        $insertQuery = "INSERT INTO [Final_Clearance].[dbo].[Clearance_Request] (matricno, requestnameid, remark, status) VALUES (?, 6, ?, 0)";
                         $insertStmt = sqlsrv_query($conn, $insertQuery, [$matricNumber, $remarkText]);

                        if ($insertStmt) {
                            $insertedCount++;
                        } else {
                            $errorMessage = 'Error inserting data into database.';
                            break;
                        }
                    }

                    $uploadedData[] = [
                        'id' => $rowIndex,
                        'matric_number' => htmlspecialchars($matricNumber)
                    ];
                    $rowIndex++;
                }
            }

            if ($insertedCount > 0 && empty($errorMessage)) {
                $successMessage = "Processed " . count($uploadedData) . " records. Successfully inserted $insertedCount new records into the database.";
            } elseif ($insertedCount == 0 && empty($errorMessage) && !empty($uploadedData)) {
                $successMessage = "Processed " . count($uploadedData) . " records. All records already exist in the database. No new insertions made.";
            }

            $existingData = [];
             $query = "SELECT id, matricno FROM [Final_Clearance].[dbo].[Clearance_Request] WHERE requestnameid = 6 AND remark = ? AND status = 0 ORDER BY id";
             $stmt = sqlsrv_query($conn, $query, [$remarkText]);
            if ($stmt) {
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $existingData[] = [
                        'id' => $row['id'],
                        'matric_number' => htmlspecialchars($row['matricno'])
                    ];
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bursary - No Payment Disqualified Students</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            margin: 0;
        }
        .top-bar {
            background-color: #006400;
            padding: 10px 20px;
            display: flex;
            justify-content: flex-end;
        }
        .logout-btn {
            background-color: red;
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            font-weight: bold;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }
        .logout-btn:hover {
            background-color: darkred;
        }
        .container {
            flex: 1;
            max-width: 1200px;
            width: 90%;
            margin: 30px auto;
            padding: 20px;
            background-color: white;
            border: 2px solid #006400;
            border-radius: 10px;
        }
        h1 {
            color: #006400;
            text-align: center;
        }
        .upload-form {
            margin-bottom: 20px;
            text-align: center;
        }
        .upload-form input[type="file"] {
            margin-bottom: 10px;
        }
        .error {
            color: red;
            text-align: center;
        }
        footer {
            background-color: #006400;
            color: white;
            text-align: center;
            padding: 15px 0;
            font-weight: bold;
            margin-top: auto;
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <a href="main_clearance.php" class="btn btn-sm btn-primary" style="margin-right: 10px;">Back to Dashboard</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
    <div class="container">
        <h1><?php echo htmlspecialchars($sectionName); ?> - Upload Disqualified Students (No Payment)</h1>
        <div class="welcome">
            <?php echo htmlspecialchars($sectionName); ?> Section
        </div>
        <div class="upload-form">
            <br><br>
            <p style="text-align: center; margin-bottom: 10px;">
                <a href="generate_disqualified_template.php?remark=no_payment" class="btn btn-success" style="margin-right: 10px;">Download Excel Template</a>
                Download the template, fill in the matric numbers, then upload the file below.
            </p>
            <form action="" method="post" enctype="multipart/form-data">
                <input type="file" name="excelFile" accept=".xlsx,.xls" required>
                <button type="submit" class="btn btn-primary">Upload and Display</button>
            </form>
        </div>
        <?php if ($errorMessage): ?>
            <div class="error"><?php echo $errorMessage; ?></div>
        <?php endif; ?>
        <?php if ($successMessage): ?>
            <div style="color: green; text-align: center; margin-bottom: 20px;"><?php echo $successMessage; ?></div>
        <?php endif; ?>
        <?php if (!empty($existingData)): ?>
            <h3 style="text-align: center; color: #006400; margin-bottom: 20px;">Existing Disqualified Students (No Payment)</h3>
            <table id="dataTable" class="display table table-striped" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Matric Number</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rowNumber = 1; ?>
                    <?php foreach ($existingData as $data): ?>
                        <tr>
                            <td><?php echo $rowNumber++; ?></td>
                            <td><?php echo $data['matric_number']; ?></td>
                            <td>
                                <button onclick="deleteRecord(<?php echo $data['id']; ?>, '<?php echo $data['matric_number']; ?>')" class="btn btn-danger btn-sm">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <script>
                function deleteRecord(id, matricNumber) {
                    if (confirm('Are you sure you want to delete the record for matric number: ' + matricNumber + '?')) {
                        var form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '';

                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'delete_id';
                        input.value = id;

                        form.appendChild(input);
                        document.body.appendChild(form);
                        form.submit();
                    }
                }
            </script>
        <?php else: ?>
            <div style="text-align: center; color: #666; margin: 40px 0;">
                <p>No data uploaded yet. Please upload an Excel file to see the data table.</p>
            </div>
        <?php endif; ?>
    </div>
    <footer>
        Centre for Information & Technology Management — Yaba College of Technology © <?php echo date("Y"); ?>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#dataTable').DataTable();
        });
    </script>
</body>
</html>