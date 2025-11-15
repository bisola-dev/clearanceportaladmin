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

$requestnameid = $_SESSION['requestnameid'] ?? 1;
$sectionName = "All Cleared Students";

// Get Current Year for footer
$Y = date("Y");

// Fetch all cleared students (status = 1)
$students = [];
$query = "SELECT DISTINCT matricno FROM [Final_Clearance].[dbo].[Clearance_Request] WHERE status = 1";
$params = [];
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


$students = [];
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $students[] = $row['matricno'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title><?php echo htmlspecialchars($sectionName); ?> - Cleared Students</title>

    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" />

    <style>
html, body {
    height: 100%;
    margin: 0;
    padding: 0;
}
body {
    font-family: Arial, sans-serif;
    background-color: #f5f5f5;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}
.page-wrapper {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
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
    font-size: 28px;
}
.welcome {
    background-color: #FFD700; 
    padding: 15px; 
    text-align: center; 
    font-weight: bold; 
    border-radius: 5px; 
    margin-bottom: 20px;
}

/* Flex container for right-side buttons */
.top-buttons {
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 1px;
}

/* Button styles */
.btn-clear,
.logout-btn,
.btn-cleared,
.btn-back {
    font-weight: bold;
    color: white;
    text-decoration: none;
    padding: 10px 20px;
    border-radius: 5px;
    border: none;
    cursor: pointer;
    display: inline-block;
    font-size: 14px;
}
.btn-clear {
    background-color: #006400;
}
.logout-btn {
    background-color: red;
}
.btn-cleared,
.btn-back {
    background-color: #FF0000;
}
.btn-cleared:hover {
    background-color: #a71d2a;
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

/* ========== Responsive Mobile Adjustments ========== */
@media (max-width: 768px) {
    .container {
        width: 95%;
        margin: 20px auto;
        padding: 15px;
    }

    .top-buttons {
        flex-direction: column;
        align-items: stretch;
    }

    .btn-clear,
    .logout-btn,
    .btn-cleared,
    .btn-back {
        width: 100%;
        text-align: center;
        font-size: 16px;
        padding: 12px 0;
    }

    h1 {
        font-size: 22px;
    }

    .welcome {
        font-size: 16px;
    }
}
</style>

</head>
<body>

<div class="page-wrapper">
    <div class="container">
    <div class="top-buttons">
    <a href="logout.php" class="logout-btn">Logout</a>
    <a href="disqualified.php" class="btn-clear">Back to Unqualified List</a>
</div>
      <h1>Cleared Students List</h1>
        <div class="welcome">All Cleared Students</div>
        <button class="btn-download" onclick="downloadPDF()">Download PDF</button>
        <table id="clearedTable" class="display table table-striped">
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Full Name</th>
                    <th>Matric ID</th>
                    <th>Programme</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $matricno): 
                    $studentQuery = "SELECT Surname, Firstname, Othername, Program 
                                     FROM [Final_Clearance].[dbo].[vw_Clearance_Request]
                                     WHERE matricno = ?";
                    $studentParams = array($matricno);
                    $studentStmt = sqlsrv_query($conn, $studentQuery, $studentParams);
                   
                    if ($studentStmt === false) {
    $errorId = uniqid('dberr_', true);
    error_log("[$errorId] SQLSRV error (studentStmt): " . print_r(sqlsrv_errors(), true));
    echo '<script>alert("A system error occurred (ref: ' . $errorId . '). Please contact support.");</script>';
    exit;
}


                    $details = sqlsrv_fetch_array($studentStmt, SQLSRV_FETCH_ASSOC);
                    $fullName = trim($details['Surname'] . " " . $details['Firstname'] . " " . $details['Othername']);
                    $program = $details['Program'];
                ?>
                <tr>
                    <td></td>
                    <td><?php echo htmlspecialchars($fullName); ?></td>
                    <td><?php echo htmlspecialchars($matricno); ?></td>
                    <td><?php echo htmlspecialchars($program); ?></td>
                    <td>
                        <?php
                        // Get the remark for this cleared student
                        $remarkQuery = "SELECT Remark FROM [Final_Clearance].[dbo].[Clearance_Request] WHERE matricno = ? AND status = 1";
                        $remarkStmt = sqlsrv_query($conn, $remarkQuery, [$matricno]);
                        $remark = '';
                        if ($remarkStmt && sqlsrv_has_rows($remarkStmt)) {
                            $remarkRow = sqlsrv_fetch_array($remarkStmt, SQLSRV_FETCH_ASSOC);
                            $remark = $remarkRow['Remark'] ?? '';
                        }
                        echo htmlspecialchars($remark);
                        ?>
                    </td>
                    <td>
                        <button class="btn btn-cleared" onclick="showModal('<?php echo htmlspecialchars($matricno); ?>')">Unclear</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <footer>
        Centre for Information & Technology Management — Yaba College of Technology © <?php echo $Y; ?>
    </footer>
</div>


<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>


<script>
$(document).ready(function() {
    var t = $('#clearedTable').DataTable({
        columnDefs: [{ searchable: false, orderable: false, targets: 0 }],
        order: [[1, 'asc']]
    });

    t.on('order.dt search.dt', function() {
        t.column(0, {search:'applied', order:'applied'}).nodes().each(function(cell, i) {
            cell.innerHTML = i + 1;
        });
    }).draw();
});

function downloadPDF() {
  const headers = ["S/N", "Full Name", "Matric No", "Programme", "Status"];
  const data = [];

  // Use DataTables API to get visible rows
  const table = $('#clearedTable').DataTable();
  const visibleRows = table.rows({ search: 'applied' }).nodes();

  $(visibleRows).each(function(index, row) {
    const cells = $(row).find('td');
    if (cells.length >= 5) {
      data.push([
        index + 1,
        $(cells[1]).text().trim(), // Full Name
        $(cells[2]).text().trim(), // Matric No
        $(cells[3]).text().trim(), // Programme
        $(cells[4]).text().trim()  // Status
      ]);
    }
  });

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
      { text: 'List of Cleared Students', style: 'header' },
      { text: `Date: ${formattedDate}`, alignment: 'right', fontSize: 12, bold: true, margin: [0, 0, 0, 10] },
      {
        table: {
          headerRows: 1,
          widths: ['auto', '*', '*', '*', '*'],
          body: [
            headers,
            ...data
          ]
        },
        layout: {
          fillColor: function (rowIndex) {
            return rowIndex === 0 ? '#f0f0f0' : null;
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

  pdfMake.createPdf(docDefinition).download(`Cleared_List_${formattedDate.replace(/ /g, "_")}.pdf`);
}

function showModal(matricno) {
    if (confirm("Are you sure you want to unclear this student?")) {
        // Directly submit without modal
        var requestnameid = <?php echo json_encode($requestnameid); ?>;
        var $btn = $(event.target);

        $btn.prop('disabled', true).text('Processing...');

        $.post("update_status.php", {
            matric: matricno,
            status: 1,
            csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
        })
        .done(function(response) {
            alert(response);
            location.reload();
        })
        .fail(function() {
            alert("Error submitting. Please try again.");
        })
        .always(function() {
            $btn.prop('disabled', false).text('Unclear');
        });
    }
}




</script>

</body>
</html>
