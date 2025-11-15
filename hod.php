<?php
ini_set('max_execution_time', 600); // 10 minutes
require_once("cann.php");
require_once("check.php");

$sections = [
    1 => "Department Head",
    2 => "Library",
    3 => "Student Affairs",
    4 => "School Officer",
    5 => "Academic Gown",
    6 => "Bursary, School fee"
];

$selectedRequestnameid = isset($_POST['requestnameid']) ? intval($_POST['requestnameid']) : 1;
$sectionName = "Admin Dashboard";

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$uploadedData = [];
$existingData = [];
$errorMessage = '';
$successMessage = '';

// Fetch existing graduands from database only if not processing a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $query = "SELECT id, matricnum FROM [Final_Clearance].[dbo].[graduandslist] ORDER BY id";
    $stmt = sqlsrv_query($conn, $query);

    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $existingData[] = [
                'id' => $row['id'],
                'matric_number' => htmlspecialchars($row['matricnum'])
            ];
        }
    } else {
        $errorMessage = "Unable to load existing records. Please try refreshing the page or contact support.";
    }
}

// Debug: Show count of existing data
if (!empty($existingData)) {
    $successMessage = "Found " . count($existingData) . " existing records in database.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    // Delete individual record
    $deleteId = intval($_POST['delete_id']);
    $deleteQuery = "DELETE FROM [Final_Clearance].[dbo].[graduandslist] WHERE id = ?";
    $deleteStmt = sqlsrv_query($conn, $deleteQuery, [$deleteId]);

    if ($deleteStmt && sqlsrv_rows_affected($deleteStmt) > 0) {
        $successMessage = "Record deleted successfully.";
    } else {
        $errorMessage = "Error deleting record from database or record not found.";
    }

    // Refetch existing data after delete to show updated list
    $existingData = [];
    $query = "SELECT id, matricno FROM [Final_Clearance].[dbo].[Clearance_Request] WHERE status = 0 ORDER BY id";
    $stmt = sqlsrv_query($conn, $query);
    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $existingData[] = [
                'id' => $row['id'],
                'matric_number' => htmlspecialchars($row['matricnum'])
            ];
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excelFile'])) {
    $file = $_FILES['excelFile'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessage = 'File upload error.';
    } else {
        $fileType = IOFactory::identify($file['tmp_name']);
        $reader = IOFactory::createReader($fileType);
        $spreadsheet = $reader->load($file['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // Filter out empty rows and instruction/header rows
        $rows = array_filter($rows, function($row) {
            $cell = trim($row[0] ?? '');
            if (empty($cell)) return false;
            // Skip if looks like header or instruction
            if (stripos($cell, 'list of graduands') !== false || stripos($cell, 'matric number') !== false || stripos($cell, 'add more rows') !== false || stripos($cell, 'do not change') !== false || stripos($cell, 'instructions') !== false || stripos($cell, 'enter matric') !== false || stripos($cell, 'each matric') !== false || stripos($cell, 'save the file') !== false) {
                return false;
            }
            return true;
        });

        // Deduplicate matric numbers from the sheet
        $uniqueMatrics = [];
        foreach ($rows as $row) {
            $matricNumber = trim($row[0]);
            if (!empty($matricNumber) && !in_array($matricNumber, $uniqueMatrics)) {
                $uniqueMatrics[] = $matricNumber;
            }
        }

        $validMatrics = $uniqueMatrics;
        $insertedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $rowIndex = 1; // Always start numbering from 1

        foreach ($validMatrics as $matricNumber) {
            // Always add to display data
            $uploadedData[] = [
                'id' => $rowIndex,
                'matric_number' => htmlspecialchars($matricNumber)
            ];
            $rowIndex++;
        }

        $totalProcessed = count($validMatrics);

        if (!empty($validMatrics)) {
            // Create temporary table
            $createTempQuery = "CREATE TABLE #TempMatrics (matricnum NVARCHAR(50))";
            $createStmt = sqlsrv_query($conn, $createTempQuery);
            if (!$createStmt) {
                $errorMessage = "Failed to create temporary table.";
            } else {
                // Bulk insert into temp table
                $insertError = false;
                foreach ($validMatrics as $matric) {
                    $insertTempQuery = "INSERT INTO #TempMatrics (matricnum) VALUES (?)";
                    $insertStmt = sqlsrv_query($conn, $insertTempQuery, [$matric]);
                    if (!$insertStmt) {
                        $errorMessage = "Failed to insert into temp table.";
                        $insertError = true;
                        break;
                    }
                }

                if (!$insertError) {
                    // Bulk insert into main table where not exists
                    $bulkInsertQuery = "
                        INSERT INTO [Final_Clearance].[dbo].[graduandslist] (matricnum)
                        SELECT DISTINCT t.matricnum
                        FROM #TempMatrics t
                        WHERE NOT EXISTS (
                            SELECT 1 FROM [Final_Clearance].[dbo].[graduandslist] m
                            WHERE m.matricnum = t.matricnum
                        )
                    ";
                    $bulkInsertStmt = sqlsrv_query($conn, $bulkInsertQuery);

                    if ($bulkInsertStmt) {
                        $insertedCount = sqlsrv_rows_affected($bulkInsertStmt);
                        $skippedCount = $totalProcessed - $insertedCount;
                    } else {
                        $errorCount = $totalProcessed;
                    }
                }

                // Drop temp table
                sqlsrv_query($conn, "DROP TABLE #TempMatrics");
            }
        } else {
            $insertedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;
        }

        $networkErrorCount = 0;
        $networkFailedMatrics = [];
        $insertFailedMatrics = [];

        // Set comprehensive status message
        $totalProcessed = count($uploadedData);
        if ($insertedCount > 0) {
            $successMessage = "Successfully processed $totalProcessed matric numbers. $insertedCount new records inserted into database.";
            if ($skippedCount > 0) {
                $successMessage .= " $skippedCount records already existed and were skipped.";
            }
            if ($errorCount > 0) {
                $successMessage .= " $errorCount records failed to insert.";
            }
        } elseif ($skippedCount > 0 && $totalProcessed > 0) {
            $successMessage = "Processed $totalProcessed matric numbers. All records already exist in the database. No new insertions made.";
        } elseif ($errorCount > 0) {
            $errorMessage = "Processed $totalProcessed matric numbers. $errorCount records failed to insert. Please check your data and try again.";
        } else {
            $successMessage = "No valid matric numbers found in the uploaded file.";
        }

        // Add network error message if any
        if ($networkErrorCount > 0) {
            $errorMessage .= " $networkErrorCount records failed due to network issues. Please try again.";
        }

        // Refetch existing data after upload to show updated list with delete buttons
        // Skip if network issues
        $refetchStmt = sqlsrv_query($conn, "SELECT id, matricnum FROM [Final_Clearance].[dbo].[graduandslist] ORDER BY id");
        if ($refetchStmt) {
            $existingData = [];
            while ($row = sqlsrv_fetch_array($refetchStmt, SQLSRV_FETCH_ASSOC)) {
                $existingData[] = [
                    'id' => $row['id'],
                    'matric_number' => htmlspecialchars($row['matricnum'])
                ];
            }
        }
        // If refetch fails, keep the old $existingData
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HOD Page - Upload Excel</title>
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
        .upload-form select {
            font-size: 16px;
            padding: 8px;
            width: 100%;
            max-width: 300px;
            margin-bottom: 10px;
        }
        .error {
            color: red;
            text-align: center;
        }
        .progress {
            height: 20px;
            background-color: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 5px;
        }
        .progress-bar {
            height: 100%;
            background-color: #006400;
            width: 0%;
            transition: width 0.3s ease;
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
    <div class="container">
        <h1>Welcome  Admin, <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
        <div class="top-buttons" style="display: flex; justify-content: center; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
            <?php if ($_SESSION['id'] == 1): ?>
            <button onclick="window.location.href='super_admin.php'" class="btn btn-sm btn-primary">Manage Admins</button>
            <?php endif; ?>
            <button onclick="window.location.href='verify_clearance.php'" class="btn btn-sm btn-success">Verify Document</button>
            <button onclick="window.location.href='cleared_list.php'" class="btn btn-sm btn-info">View Cleared List</button>
            <button onclick="window.location.href='disqualified.php'" class="btn btn-sm btn-warning">Upload Disqualified</button>
            <button onclick="window.location.href='logout.php'" class="btn btn-sm btn-danger">Logout</button>
        </div>
        <div class="upload-form">
            <br><br>
            <p style="text-align: center; margin-bottom: 10px;">
                <a href="generate_template.php" class="btn btn-success" style="margin-right: 10px;">Download Excel Template</a>
                Download the template, fill in the matric numbers, then upload the file below.
            </p>
            <form action="" method="post" enctype="multipart/form-data" id="uploadForm">
                <input type="file" name="excelFile" accept=".xlsx,.xls" required id="excelFile">
                <button type="submit" class="btn btn-primary" id="uploadBtn">Upload and Display</button>
                <div id="progressContainer" style="display: none; margin-top: 10px;">
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" style="width: 0%" id="progressBar"></div>
                    </div>
                    <small id="progressText">Preparing upload...</small>
                </div>
            </form>
        </div>
        <?php if ($errorMessage): ?>
            <div class="error"><?php echo $errorMessage; ?></div>
        <?php endif; ?>
        <?php if ($successMessage): ?>
            <div style="color: green; text-align: center; margin-bottom: 20px;"><?php echo $successMessage; ?></div>
        <?php endif; ?>
        <?php if (!empty($existingData)): ?>
            <h3 style="text-align: center; color: #006400; margin-bottom: 20px;">Existing Graduands in Database</h3>
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
                        // Create a form to submit delete request
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

            // Fix for search input paste issue
            $('#dataTable_filter input').on('paste', function() {
                var that = this;
                setTimeout(function() {
                    $(that).trigger('keyup');
                    that.selectionStart = that.selectionEnd = that.value.length;
                }, 0);
            });
        });

        // Handle form submission with progress tracking
        $('#uploadForm').on('submit', function(e) {
            var fileInput = $('#excelFile')[0];
            if (!fileInput.files[0]) {
                alert('Please select a file to upload.');
                e.preventDefault();
                return false;
            }

            var fileSize = fileInput.files[0].size;
            var maxSize = 10 * 1024 * 1024; // 10MB limit

            if (fileSize > maxSize) {
                alert('File size too large. Maximum allowed size is 10MB.');
                e.preventDefault();
                return false;
            }

            // Show progress bar
            $('#progressContainer').show();
            $('#progressBar').css('width', '0%');
            $('#progressText').text('Uploading file...');
            $('#uploadBtn').prop('disabled', true).text('Uploading...');

            // Simulate progress for large files
            var progressInterval = setInterval(function() {
                var currentWidth = parseInt($('#progressBar').css('width'));
                if (currentWidth < 90) {
                    $('#progressBar').css('width', (currentWidth + 5) + '%');
                    $('#progressText').text('Processing data... ' + (currentWidth + 5) + '%');
                }
            }, 500);

            // Clear interval when form is submitted
            setTimeout(function() {
                clearInterval(progressInterval);
                $('#progressText').text('Processing completed. Please wait for results...');
            }, 2000);
        });
    </script>
</body>
</html>