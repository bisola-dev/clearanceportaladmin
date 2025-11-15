<?php
require_once("cann.php");
require_once("check.php");

if (!isset($_SESSION['requestnameid'])) {
    header("Location: index.php");
    exit();
}

$requestnameid = $_SESSION['requestnameid'];

$sections = [
    1 => "Department Head",
    2 => "Library",
    3 => "Student Affairs",
    4 => "School Officer",
    5 => "Academic Gown",
    6 => "Bursary, School fee"
];

if (!array_key_exists($requestnameid, $sections)) {
    echo "Invalid Request ID.";
    exit();
}

$sectionName = $sections[$requestnameid];
$Y = date("Y");

// Debug: Show current requestnameid and session info
echo "<!-- DEBUG: requestnameid = $requestnameid, sectionName = $sectionName -->\n";
if (isset($_SESSION['PNName'])) {
    echo "<!-- DEBUG: PNName = " . $_SESSION['PNName'] . " -->\n";
}
if (isset($_SESSION['SchoolName'])) {
    echo "<!-- DEBUG: SchoolName = " . $_SESSION['SchoolName'] . " -->\n";
}

// Fetch students based on requestnameid and optionally by PNName or SchoolName
$students = [];
if ($requestnameid == 1 && isset($_SESSION['PNName'])) {
    $pnName = $_SESSION['PNName'];
    $query = "SELECT matricno FROM [Final_Clearance].[dbo].[vw_Clearance_Request]
              WHERE requestnameid = ? AND Program = ? AND status = 0";
    $params = [$requestnameid, $pnName];

} elseif ($requestnameid == 4 && isset($_SESSION['SchoolName'])) {
    $schoolName = $_SESSION['SchoolName'];
    $query = "SELECT matricno FROM [Final_Clearance].[dbo].[vw_Clearance_Request]
              WHERE requestnameid = ? AND SchoolName = ? AND status = 0";
    $params = [$requestnameid, $schoolName];

} else {
    $query = "SELECT matricno FROM [Final_Clearance].[dbo].[Clearance_Request]
              WHERE requestnameid = ? AND status = 0";
    $params = [$requestnameid];
}

$stmt = sqlsrv_query($conn, $query, $params);

if ($stmt === false) {
    // Generate a unique error ID for tracking
    $errorId = uniqid('sqlerr_', true);

    // Log detailed SQL errors on the server
    error_log("[$errorId] SQLSRV query failed: " . print_r(sqlsrv_errors(), true));

    // Show a generic message to the user
    echo '<script>
            alert("A system error occurred (ref: ' . $errorId . '). Please contact support.");
            window.location.href = "index.php";
          </script>';
    exit;
}


while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $students[] = $row['matricno'];
}

// Debug: Show query and results
echo "<!-- DEBUG: Query = " . htmlspecialchars($query) . " -->\n";
echo "<!-- DEBUG: Params = " . json_encode($params) . " -->\n";
echo "<!-- DEBUG: Found " . count($students) . " students -->\n";
if (!empty($students)) {
    echo "<!-- DEBUG: Sample matric numbers: " . implode(", ", array_slice($students, 0, 5)) . " -->\n";
} else {
    echo "<!-- DEBUG: No students found. Check if data exists in Clearance_Request table with status=0 for requestnameid=$requestnameid -->\n";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($sectionName); ?> - Clearance Page</title>

    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.dataTables.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css"/>

    
    <style>
body {
    font-family: Arial, sans-serif;
    background-color: #f5f5f5;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    margin: 0;
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
.welcome {
    background-color: #FFD700;
    padding: 15px;
    text-align: center;
    font-weight: bold;
    border-radius: 5px;
    margin-bottom: 20px;
}

/* Buttons Shared Styles */
.btn-clear,
.btn-unclear,
.btn-flag,
.btn-flag-view,
.btn-flag-delete,
.btn-view-receipt,
.btn-download {
    font-size: 14px;
    padding: 6px 12px;
    font-weight: bold;
    border-radius: 5px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    margin: 2px;
}

/* Specific Button Colors */
.btn-clear {
    background-color: #006400;
    color: white;
}
.btn-verify {
    background-color: #28a745;
    color: white;
}
.btn-verify:hover {
    background-color: #1e7e34;
}
.btn-unclear {
    background-color: #006400;
    color: white;
}
.btn-flag {
    background-color: #FFD700;
    color: black;
}
.btn-flag[disabled] {
    background-color: #cccc00;
    cursor: not-allowed;
}
.btn-flag-view {
    background-color: #007bff;
    color: white;
}
.btn-flag-view:hover {
    background-color: #0056b3;
}
.btn-flag-delete {
    background-color: #dc3545;
    color: white;
}
.btn-flag-delete:hover {
    background-color: #a71d2a;
}
.btn-view-receipt {
    background-color: #28a745;
    color: white;
}
.btn-view-receipt:hover {
    background-color: #1e7e34;
}

/* Right-Aligned Buttons Container */
.top-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-bottom: 1px;
    flex-wrap: wrap;

}

.logout-btn {
    background-color: red;
    color: white;
    padding: 10px 20px;
    border-radius: 5px;
    font-weight: bold;
    text-decoration: none;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.5);
}
.modal-content {
    background-color: #fff;
    margin: 10% auto;
    padding: 20px;
    border-radius: 8px;
    width: 90%;
    max-width: 400px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
}
.close-btn {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}
.close-btn:hover {
    color: black;
}
#unflagReason {
    margin-top: 10px;
    padding: 8px;
    font-size: 14px;
    border-radius: 5px;
    border: 1px solid #ccc;
    resize: vertical;
    box-sizing: border-box;
    width: 100%;
}

/* Footer */
footer {
    background-color: #006400;
    color: white;
    text-align: center;
    padding: 15px 0;
    font-weight: bold;
    margin-top: auto;
}

/* Responsive Layout */
@media (max-width: 768px) {
    .container {
        margin: 20px auto;
        padding: 15px;
        width: 95%;
    }

    .top-buttons {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }

    .logout-btn,
    .btn-clear,
    .btn-verify {
        width: 100%;
        text-align: center;
    }

    .action-buttons .d-flex {
        flex-direction: column;
        gap: 5px;
    }

    .action-buttons .btn {
        width: 100%;
        margin: 2px 0;
    }

    .btn-download {
        width: 100%;
        margin-bottom: 15px;
    }

    table {
        font-size: 12px;
    }

    td, th {
        padding: 6px;
    }

    h1 {
        font-size: 20px;
    }

    .welcome {
        font-size: 14px;
        padding: 10px;
    }

    .modal-content {
        width: 95%;
        margin: 5% auto;
        padding: 15px;
    }

    #unflagReason {
        font-size: 14px;
        padding: 10px;
    }
}
</style>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>

<div class="container">
<div class="top-buttons">
    <?php if ($requestnameid == 1): ?>
    <a href="super_admin.php" class="btn btn-sm btn-primary">Manage Admins</a>
    <?php endif; ?>
    <a href="verify_clearance.php" class="btn btn-sm btn-verify">Verify Document</a>
    <a href="cleared_list.php" class="btn btn-sm btn-clear">View Cleared List </a>
    <?php if ($requestnameid == 6): ?>
    <a href="disqualified_no_payment.php" class="btn btn-sm btn-flag" style="font-weight: bold;">Upload No Payment</a>
    <a href="disqualified_incomplete_payment.php" class="btn btn-sm btn-flag-view" style="font-weight: bold;">Upload Incomplete Payment</a>
    <?php else: ?>
    <a href="disqualified.php" class="btn btn-sm btn-flag-view" style="font-weight: bold;">Upload Disqualified</a>
    <?php endif; ?>
    <a href="logout.php" class="btn btn-sm logout-btn">Logout</a>
    </div>
    <h1>Clearance Portal</h1>
    <?php if ($requestnameid == 1 && isset($_SESSION['PNName'])): ?>
    <div class="welcome">
        <?php echo htmlspecialchars($sectionName); ?> Section <br>
        Programme: <?php echo htmlspecialchars($_SESSION['PNName']); ?>
    </div>
    <?php elseif ($requestnameid == 4 && isset($_SESSION['SchoolName'])): ?>
    <div class="welcome">
        <?php echo htmlspecialchars($sectionName); ?> Section <br>
        School: <?php echo htmlspecialchars($_SESSION['SchoolName']); ?>
    </div>
    <?php else: ?>
    <div class="welcome">
        Welcome to <?php echo htmlspecialchars($sectionName); ?> Section
    </div>
    <?php endif; ?>
    <button class="btn btn-sm btn-primary btn-download" onclick="downloadPDF()">Download PDF</button>
    <table id="clearanceTable" class="display table table-striped dt-responsive" style="width:100%">
        <thead>
            <tr>
                <th>S/N</th>
                <th>Full Name</th>
                <th>Matric ID</th>
                <th>Programme</th>
                <th>Action</th>
         
            </tr>
        </thead>

        <tbody>
        <?php foreach ($students as $matricno): 
    // Get student details
    $studentQuery = "SELECT Surname, Firstname, Othername, Program
                     FROM [Final_Clearance].[dbo].[vw_Clearance_Request]
                     WHERE matricno = ?";
    $studentParams = array($matricno);
    $studentStmt = sqlsrv_query($conn, $studentQuery, $studentParams);

    if ($studentStmt === false) {
    $errorId = uniqid('dberr_', true);
     error_log("[$errorId] SQLSRV error (studentStmt): " . print_r(sqlsrv_errors(), true));
    echo '<script>alert("Database error loading student details. Continuing with available data.");</script>';
    continue;
}



    
    $details = sqlsrv_fetch_array($studentStmt, SQLSRV_FETCH_ASSOC);
    $fullName = trim($details['Surname']." ".$details['Firstname']." ".$details['Othername']);
    $program = $details['Program'];

    // Get Remark
    $remarkQuery = "SELECT Remark FROM [Final_Clearance].[dbo].[Clearance_Request] WHERE matricno = ? AND requestnameid = ?";
    $remarkStmt = sqlsrv_query($conn, $remarkQuery, [$matricno, $requestnameid]);
 

    if ($remarkStmt === false) {
    // Generate a unique error ID for tracking
    $errorId = uniqid('dberr_', true);

    // Log the full SQL Server error to the server log
    error_log("[$errorId] SQLSRV error (remarkStmt): " . print_r(sqlsrv_errors(), true));

    // Show a safe message to the user without revealing sensitive info
    echo '<script>alert("Database error loading remark details. Continuing with available data.");</script>';
    $remark = null;
}

    $remarkRow = sqlsrv_fetch_array($remarkStmt, SQLSRV_FETCH_ASSOC);
    $remark = $remarkRow['Remark'] ?? null;

    // Get Receipt path (for requestnameid == 6)
$receiptPath = null;
if ($requestnameid == 6) {
    $receiptQuery = "SELECT FileName FROM [Final_Clearance].[dbo].[UploadedGraduateFile] WHERE MatricNumber = ?";
    $receiptStmt = sqlsrv_query($conn, $receiptQuery, [$matricno]);
    if ($receiptStmt !== false && sqlsrv_has_rows($receiptStmt)) {
        $receiptRow = sqlsrv_fetch_array($receiptStmt, SQLSRV_FETCH_ASSOC);
        if ($receiptRow && !empty($receiptRow['FileName'])) {
            // We will use a PHP file to serve the PDF dynamically
            $receiptPath = "serve_pdf.php?matric=" . urlencode($matricno);
        }
    }
}



?>
<tr id="row_<?php echo htmlspecialchars($matricno); ?>">
    <td></td> <!-- For S/N -->
    <td><?php echo htmlspecialchars($fullName); ?></td>
    <td><?php echo htmlspecialchars($matricno); ?></td>
    <td><?php echo htmlspecialchars($program); ?></td>
    <td>
        <div class="action-buttons d-flex flex-column gap-2">
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-sm btn-unclear" onclick="clearStudent('<?php echo htmlspecialchars($matricno); ?>', <?php echo json_encode($requestnameid); ?>)">
                   Clear Student
                </button>
                <?php if ($requestnameid == 6 && $receiptPath): ?>
       <button class="btn btn-sm btn-view-receipt" onclick="window.open('<?php echo htmlspecialchars($receiptPath); ?>', '_blank')">
        View Receipt
        </button>
       <?php endif; ?>

                <?php if (isset($remark) && trim($remark) !== ''): ?>
                    <button class="btn btn-sm btn-flag" disabled title="Already flagged">Flagged</button>

                <button class="btn btn-sm btn-flag-view" onclick="toggleRemark('<?php echo htmlspecialchars($matricno . '_' . $requestnameid); ?>')">
                View Reason
                </button>
                <?php else: ?>
                    <button
                        id="flagBtn_<?php echo htmlspecialchars($matricno); ?>"
                        class="btn btn-sm btn-flag"
                        onclick="openFlagModal('<?php echo htmlspecialchars($matricno); ?>', <?php echo json_encode($requestnameid); ?>)">
                        Flag
                    </button>
                <?php endif; ?>
            </div>

            <div id="remarkDiv_<?php echo htmlspecialchars($matricno); ?>_<?php echo $requestnameid; ?>"
     class="remark-container" style="display: none; background-color: #fffbe6; padding: 10px; font-style: italic; color: #b36b00; border-radius: 5px; max-height: 80px; overflow-y: auto;">
    <strong>Flagged Reason:</strong>
    <p><?php echo nl2br(htmlspecialchars($remark)); ?></p>
</div>

                </div>
        </div>
    </td>
</tr>
<?php endforeach; ?>

        

        </tbody>
    </table>
</div>

<!-- Modal -->
<div id="unflagModal" class="modal">
  <div class="modal-content">
    <span class="close-btn" id="closeModal">&times;</span>
    <h3>Reasons for flagging</h3>
    <textarea id="unflagReason" rows="5" placeholder="Type your reasons here..."></textarea>
    <button id="submitUnflag" class="btn btn-sm btn-unclear">Submit</button>
  </div>
</div>

<footer>
Centre for Information & Technology Management — Yaba College of Technology © <?php echo $Y; ?>
</footer>

<script>
    let table; 
  $(document).ready(function() {
  table = $('#clearanceTable').DataTable({
      order: [[1, 'asc']],
      columnDefs: [
        { targets: 0, orderable: false, searchable: false }
      ],
      responsive: true,
      pageLength: 10,
      lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]]
    });

    // Add index number for the first column every time table is drawn or ordered or searched
    table.on('order.dt search.dt draw.dt', function() {
      const pageInfo = table.page.info();
      table.column(0, { page: 'current' }).nodes().each(function(cell, i) {
        cell.innerHTML = pageInfo.start + i + 1;
      });
    }).draw();
  });

  function downloadPDF() {
  const headers = ["S/N", "Full Name", "Matric No", "Programme"];
  const data = [];

  // Extract only visible rows and skip the Action buttons column
  $('#clearanceTable tbody tr:visible').each(function(index) {
    const cells = $(this).find('td');
    if (cells.length >= 4) {
      data.push([
        index + 1,
        $(cells[1]).text().trim(),
        $(cells[2]).text().trim(),
        $(cells[3]).text().trim()
      ]);
    }
  });

  // 🔒 If no data, show alert and stop
  if (data.length === 0) {
    alert("No data available to download.");
    return;
  }

  const today = new Date();
  const formattedDate = today.toLocaleDateString('en-GB', {
    day: '2-digit', month: 'short', year: 'numeric'
  });

  const docDefinition = {
    content: [
      { text: 'List of Uncleared Students', style: 'header' },
      { text: `Date: ${formattedDate}`, alignment: 'right', fontSize: 12, bold: true, margin: [0, 0, 0, 10] },
      {
        table: {
          headerRows: 1,
          widths: ['auto', '*', '*', '*'],
          body: [
            headers,
            ...data
          ]
        },
        layout: {
          fillColor: function (rowIndex) {
            return rowIndex === 0 ? '#f0f0f0' : null;  // Light gray header
          },
          hLineWidth: function () { return 0.5; },
          vLineWidth: function () { return 0.5; },
          hLineColor: function () { return '#aaa'; },
          vLineColor: function () { return '#aaa'; }
        }
      }
    ],
    styles: {
      header: {
        fontSize: 16,
        bold: true,
        alignment: 'center',
        margin: [0, 10, 0, 10]
      }
    },
    pageOrientation: 'portrait',
    defaultStyle: {
      fontSize: 11
    },
    footer: function(currentPage, pageCount) {
      return {
        text: `Generated on ${formattedDate} | Page ${currentPage} of ${pageCount}`,
        alignment: 'center',
        fontSize: 9,
        margin: [0, 10, 0, 0]
      };
    }
  };

  pdfMake.createPdf(docDefinition).download(`Uncleared_List_${formattedDate.replace(/ /g, "_")}.pdf`);
}

  function viewReceipt(path) {
    if (!path) {
        alert("Receipt path not available.");
        return;
    }
    window.open(path, '_blank');
}


  function clearStudent(matricno, requestnameid) {
      if (!confirm("Are you sure you want to mark this student as cleared?")) return;

      $.post("update_status.php", { matric: matricno, requestnameid: requestnameid, status: 0, csrf_token: '<?php echo $_SESSION['csrf_token']; ?>' }, function(response) {
          alert(response);
          location.reload();
      })
      .fail(function() {
          alert("Error updating status. Please try again.");
      });
  }

  // Modal related code
  let currentFlagMatric = null;
  let currentRequestId = null;

  function openFlagModal(matricno, requestnameid) {
      currentFlagMatric = matricno;
      currentRequestId = requestnameid;
      $('#unflagReason').val('');
      $('#unflagModal').fadeIn();
  }

  $('#closeModal').on('click', function() {
      $('#unflagModal').fadeOut();
  });

  $(window).on('click', function(event) {
      if (event.target.id === 'unflagModal') {
          $('#unflagModal').fadeOut();
      }
  });

  $('#submitUnflag').on('click', function() {
      const reason = $('#unflagReason').val().trim();

      if (!reason) {
          alert('Please enter a reason for unflagging.');
          return;
      }

      $.post('submit_unflag_reason.php', {
          matricno: currentFlagMatric,
          requestnameid: currentRequestId,
          reason: reason,
          csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
      }, function(response) {
          alert(response);
          $('#unflagModal').fadeOut();
          location.reload();
      }).fail(function() {
          alert('Failed to submit reason.');
      });
  });

  function deleteRemark(matricno, requestnameid) {
      if (!confirm("Are you sure you want to delete the reason?")) return;

      $.post("delete_flag_reason.php", { matric: matricno, requestnameid: requestnameid, csrf_token: '<?php echo $_SESSION['csrf_token']; ?>' }, function(response) {
          alert(response);
          location.reload();
      }).fail(function() {
          alert("Failed to delete reason.");
      });
  }
  function toggleRemark(id) {
    const el = document.getElementById('remarkDiv_' + id);
    if (!el) return;

    if (el.style.display === 'none' || el.style.display === '') {
        el.style.display = 'block';
    } else {
        el.style.display = 'none';
    }
}


</script>

</body>
</html>
