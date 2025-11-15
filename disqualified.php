<?php
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

$remark = [
    1 => "uncleared from department head",
    2 => "uncleared from library",
    3 => "uncleared from student affairs",
    4 => "uncleared from school officer",
    5 => "uncleared from academic gown",
    6 => "uncleared from bursary"
];

$selectedRequestnameid = isset($_POST['requestnameid']) ? intval($_POST['requestnameid']) : '';
$sectionName = isset($sections[$selectedRequestnameid]) ? $sections[$selectedRequestnameid] : "Admin Dashboard";
$remarkText = isset($remark[$selectedRequestnameid]) ? $remark[$selectedRequestnameid] : 'unknown remark';

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$uploadedData = [];
$existingData = [];
$errorMessage = '';
$successMessage = '';

// Fetch existing disqualified students from database only if not processing a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $query = "SELECT id, matricno, requestnameid, remark FROM [Final_Clearance].[dbo].[Clearance_Request] WHERE status = 0 ORDER BY id";
    $stmt = sqlsrv_query($conn, $query);

    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $sectionName = isset($sections[$row['requestnameid']]) ? $sections[$row['requestnameid']] : "Unknown Section";
            $existingData[] = [
                'id' => $row['id'],
                'matric_number' => htmlspecialchars($row['matricno']),
                'remark' => $row['remark'],
                'section' => $sectionName
            ];
        }
    } else {
        $errorMessage = "Unable to load existing records. Please try refreshing the page or contact support.";
    }

    // Debug: Show query details
    //$debugMessage = "Debug: Total records found: " . count($existingData);
}

// Only show initial message if not processing a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (!empty($existingData)) {
        $successMessage = "Found " . count($existingData) . " existing disqualified records in database.";
    } else {
        $successMessage = "No existing records found.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
     // Delete individual record
      $deleteId = intval($_POST['delete_id']);
      $deleteQuery = "DELETE FROM [Final_Clearance].[dbo].[Clearance_Request] WHERE id = ?";
      $deleteStmt = sqlsrv_query($conn, $deleteQuery, [$deleteId]);

     if ($deleteStmt && sqlsrv_rows_affected($deleteStmt) > 0) {
         $successMessage = "Record deleted successfully.";
     } else {
         $errorMessage = "Unable to delete the record. It may have already been removed or you may not have permission to delete it.";
     }

     // Refetch existing data after delete to show updated list
     $existingData = [];
     $query = "SELECT id, matricno, requestnameid, remark FROM [Final_Clearance].[dbo].[Clearance_Request] WHERE status = 0 ORDER BY id";
     $stmt = sqlsrv_query($conn, $query);
     if ($stmt) {
         while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
             $sectionName = isset($sections[$row['requestnameid']]) ? $sections[$row['requestnameid']] : "Unknown Section";
             $existingData[] = [
                 'id' => $row['id'],
                 'matric_number' => htmlspecialchars($row['matricno'] ?? ''),
                 'remark' => $row['remark'],
                 'section' => $sectionName
             ];
         }
     }
 } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_id'])) {
     // Clear individual record
     $clearId = intval($_POST['clear_id']);
     // First get the requestnameid for the record
     $getQuery = "SELECT requestnameid FROM [Final_Clearance].[dbo].[Clearance_Request] WHERE id = ?";
     $getStmt = sqlsrv_query($conn, $getQuery, [$clearId]);
     if ($getStmt && sqlsrv_has_rows($getStmt)) {
         $row = sqlsrv_fetch_array($getStmt, SQLSRV_FETCH_ASSOC);
         $requestnameid = $row['requestnameid'];
         $clearedRemark = "cleared from " . strtolower($sections[$requestnameid] ?? "unknown");

         $clearQuery = "UPDATE [Final_Clearance].[dbo].[Clearance_Request] SET status = 1, remark = ? WHERE id = ?";
         $clearStmt = sqlsrv_query($conn, $clearQuery, [$clearedRemark, $clearId]);

         if ($clearStmt && sqlsrv_rows_affected($clearStmt) > 0) {
             $successMessage = "Record cleared successfully.";
         } else {
             $errorMessage = "Unable to clear the record.";
         }
     } else {
         $errorMessage = "Record not found.";
     }

     // Refetch existing data after clear to show updated list
     $existingData = [];
     $query = "SELECT id, matricno, requestnameid, remark FROM [Final_Clearance].[dbo].[Clearance_Request] WHERE status = 0 ORDER BY id";
     $stmt = sqlsrv_query($conn, $query);
     if ($stmt) {
         while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
             $sectionName = isset($sections[$row['requestnameid']]) ? $sections[$row['requestnameid']] : "Unknown Section";
             $existingData[] = [
                 'id' => $row['id'],
                 'matric_number' => htmlspecialchars($row['matricno'] ?? ''),
                 'remark' => $row['remark'],
                 'section' => $sectionName
             ];
         }
     }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excelFile'])) {
    // Validate that a section is selected before processing upload
    if (!isset($_POST['requestnameid']) || empty($_POST['requestnameid']) || !isset($sections[intval($_POST['requestnameid'])])) {
        $errorMessage = 'Please select a valid section before uploading the file.';
    } else {
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

            // Check if first row contains headers or data
            $firstRow = $rows[0] ?? [];
            $isHeaderRow = false;

            // Check if first row looks like headers (contains text that might be "matricnum" or similar)
            if (!empty($firstRow[0]) && (stripos($firstRow[0], 'matric') !== false || stripos($firstRow[0], 'number') !== false || stripos($firstRow[0], 'list of') !== false)) {
                $isHeaderRow = true;
                array_shift($rows); // Skip header row
            }

            // Filter out empty rows
            $rows = array_filter($rows, function($row) {
                return !empty(trim($row[0] ?? ''));
            });

            // Deduplicate matric numbers from the sheet
            $uniqueMatrics = [];
            foreach ($rows as $row) {
                $matricNumber = trim($row[0]);
                if (!empty($matricNumber) && !in_array($matricNumber, $uniqueMatrics)) {
                    $uniqueMatrics[] = $matricNumber;
                }
            }

            $insertedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;
            $networkErrorCount = 0;
            $rowIndex = 1; // Always start numbering from 1
            $batchSize = 10; // Process in smaller batches to avoid connection issues
            $processedRows = 0;
            $totalRows = count($uniqueMatrics);

            foreach ($uniqueMatrics as $matricNumber) {
                // Skip if this looks like a header row
                if (stripos($matricNumber, 'matric') !== false || stripos($matricNumber, 'number') !== false || stripos($matricNumber, 'list of disqualified') !== false) {
                    continue;
                }

                // Check if matric number already exists for this section with any status
                $checkQuery = "SELECT id FROM [Final_Clearance].[dbo].[Clearance_Request] WHERE matricno = ? AND requestnameid = ?";
                $checkStmt = sqlsrv_query($conn, $checkQuery, [$matricNumber, $selectedRequestnameid]);

                if ($checkStmt && !sqlsrv_has_rows($checkStmt)) {
                    // Insert into database only if it doesn't exist for this section
                    $insertQuery = "INSERT INTO [Final_Clearance].[dbo].[Clearance_Request] (matricno, requestnameid, remark, status) VALUES (?, ?, ?, 0)";
                    $insertStmt = sqlsrv_query($conn, $insertQuery, [$matricNumber, $selectedRequestnameid, $remarkText]);

                    if ($insertStmt) {
                        $insertedCount++;
                    } else {
                        $errorCount++;
                        // Continue processing instead of breaking
                    }
                } elseif (!$checkStmt) {
                    $networkErrorCount++;
                    continue;
                } else {
                    $skippedCount++;
                }

                // Always add to display data
                $uploadedData[] = [
                    'id' => $rowIndex,
                    'matric_number' => htmlspecialchars($matricNumber)
                ];
                $rowIndex++;
                $processedRows++;

                // Process in batches to prevent timeout and allow network recovery
                if ($processedRows % $batchSize == 0) {
                    // Small delay to prevent overwhelming the server and allow network recovery
                    usleep(50000); // 50ms delay between batches
                }
            }
            }

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
            $existingData = [];
            $query = "SELECT id, matricno, requestnameid, remark FROM [Final_Clearance].[dbo].[Clearance_Request] WHERE status = 0 ORDER BY id";
            $stmt = sqlsrv_query($conn, $query);
            if ($stmt) {
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $sectionName = isset($sections[$row['requestnameid']]) ? $sections[$row['requestnameid']] : "Unknown Section";
                    $existingData[] = [
                        'id' => $row['id'],
                        'matric_number' => htmlspecialchars($row['matricno'] ?? ''),
                        'remark' => $row['remark'],
                        'section' => $sectionName
                    ];
                }
            } else {
                $errorMessage .= "Error loading updated data. Network issues, please refresh the page.<br>";
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
    <title>Disqualified Students - Upload Excel</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
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
    <div class="top-bar">
        <a href="hod.php" class="btn btn-sm btn-primary" style="margin-right: 10px;">Back to Dashboard</a>
        <a href="cleared_list.php" class="btn btn-sm btn-info" style="margin-right: 10px;">View Cleared List</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
    <div class="container">
        <h1>Upload Disqualified Students</h1>
        <div class="upload-form">
            <br><br>
            <p style="text-align: center; margin-bottom: 10px;">
                <a href="generate_disqualified_template.php?remark=general" class="btn btn-success" style="margin-right: 10px;">Download Excel Template</a>
                Download the template, fill in the matric numbers, then upload the file below.
            </p>
            <form action="" method="post" enctype="multipart/form-data" id="uploadForm">
                <div class="mb-3">
                    <label for="requestnameid" class="form-label">Select Section:</label>
                    <select name="requestnameid" id="requestnameid" class="form-select" required>
                        <option value="">Select a section</option>
                        <?php foreach ($sections as $id => $name): ?>
                            <option value="<?php echo $id; ?>" <?php echo ($selectedRequestnameid == $id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
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
        <?php if (isset($debugMessage)): ?>
            <div style="color: blue; text-align: center; margin-bottom: 20px;"><?php echo $debugMessage; ?></div>
        <?php endif; ?>
        <?php if (!empty($existingData)): ?>
            <h3 style="text-align: center; color: #006400; margin-bottom: 20px;">Existing Disqualified Students in Database</h3>
            <table id="dataTable" class="display table table-striped" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Matric Number</th>
                        <th>Section</th>
                        <th>Remark</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rowNumber = 1; ?>
                    <?php foreach ($existingData as $data): ?>
                        <tr>
                            <td><?php echo $rowNumber++; ?></td>
                            <td><?php echo $data['matric_number']; ?></td>
                            <td><?php echo htmlspecialchars($data['section']); ?></td>
                            <td><?php echo htmlspecialchars($data['remark']); ?></td>
                            <td>
            <button onclick="clearRecord(<?php echo $data['id']; ?>, '<?php echo $data['matric_number']; ?>')" class="btn btn-success btn-sm" style="margin-right: 5px;">Clear</button>
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

                function clearRecord(id, matricNumber) {
                    if (confirm('Are you sure you want to clear the record for matric number: ' + matricNumber + '? This will mark them as cleared.')) {
                        // Create a form to submit clear request
                        var form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '';

                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'clear_id';
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
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script>
        $(document).ready(function() {
            $('#dataTable').DataTable({
                paging: true,
                pageLength: 10,
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'pdf',
                        text: 'Download PDF',
                        title: 'Disqualified Students List',
                        exportOptions: {
                            columns: [0, 1, 2, 3] // Exclude Action column
                        }
                    }
                ]
            });

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

            var sectionSelect = $('#requestnameid');
            if (!sectionSelect.val()) {
                alert('Please select a section.');
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