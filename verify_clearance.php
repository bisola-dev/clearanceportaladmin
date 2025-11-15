<?php
require_once("cann.php");

if (!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

$message = '';
$verificationResult = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['document_id'])) {
    $documentId = trim($_POST['document_id']);

    // Extract the hash part from document ID (remove YCT- prefix)
    if (strpos($documentId, 'YCT-') === 0) {
        $hash = substr($documentId, 4);

        // Query to find matching clearance records by Document ID
        $query = "SELECT
                    c.matricnum,
                    c.surname,
                    c.firstname,
                    c.othername,
                    c.programme,
                    c.School,
                    pt.Session,
                    c.programmetypeid,
                    dv.generated_date,
                    dv.status as doc_status
                  FROM [Final_Clearance].[dbo].[Document_Verification] dv
                  INNER JOIN [Final_Clearance].[dbo].[vw_graduandslist] c ON dv.matricnum = c.matricnum
                  INNER JOIN [student].[dbo].[vw_programme_type] pt ON c.programmetypeid = pt.programmetypeid
                  WHERE dv.document_id = ? AND dv.status = 1";

        $stmt = sqlsrv_query($conn, $query, [$documentId]);
        if ($stmt && sqlsrv_has_rows($stmt)) {
            $verificationResult = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

            // Check if payment was verified for this student
            $paymentQuery = "SELECT COUNT(*) as count FROM [EBPORTAL].[dbo].[Transactions]
                           WHERE Payeenum = ? AND Paymentid = ?";
            $paymentStmt = sqlsrv_query($ebportal_conn, $paymentQuery, [$verificationResult['matricnum'], '61']);

            if ($paymentStmt && sqlsrv_has_rows($paymentStmt)) {
                $paymentResult = sqlsrv_fetch_array($paymentStmt, SQLSRV_FETCH_ASSOC);
                $verificationResult['payment_verified'] = $paymentResult['count'] > 0;
            }

            $message = "Document ID verified successfully!";
        } else {
            $message = "Invalid Document ID.";
        }
    } else {
        $message = "Invalid Document ID format.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Clearance Document</title>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #006400, #FFD700);
            min-height: 100vh;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .verify-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .logo {
            width: 100px;
            margin-bottom: 20px;
        }

        h1 {
            color: #006400;
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .subtitle {
            color: #333;
            font-size: 16px;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
        }

        .form-group input:focus {
            border-color: #006400;
            outline: none;
        }

        .verify-btn {
            background: linear-gradient(45deg, #FFD700, #FFC107);
            color: #006400;
            border: none;
            padding: 15px 30px;
            font-size: 18px;
            font-weight: bold;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3);
            width: 100%;
        }

        .verify-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 215, 0, 0.4);
        }

        .message {
            margin-top: 20px;
            padding: 15px;
            border-radius: 8px;
            font-weight: 600;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .verification-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            text-align: left;
        }

        .verification-details h3 {
            color: #006400;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }

        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .detail-label {
            font-weight: 600;
            color: #555;
        }

        .detail-value {
            color: #333;
        }

        .status-verified {
            color: #28a745;
            font-weight: bold;
        }

        .status-unverified {
            color: #dc3545;
            font-weight: bold;
        }

        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 14px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 20px;
            text-decoration: none;
            display: inline-block;
        }

        .logout-btn:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <div class="verify-card">
        <img src="imgg/yabalogo.jpg" alt="YABATECH Logo" class="logo" />
        <h1>Verify Clearance Document</h1>
        <p class="subtitle">Enter the Document ID from a clearance slip to verify its authenticity</p>

        <form method="post">
            <div class="form-group">
                <label for="document_id">Document ID:</label>
                <input type="text" id="document_id" name="document_id" placeholder="YCT-XXXXXXXX" required>
            </div>
            <button type="submit" class="verify-btn">Verify Document</button>
        </form>

        <?php if ($message): ?>
        <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <?php if ($verificationResult): ?>
        <div class="verification-details">
            <h3>Document Details</h3>
            <div class="detail-row">
                <div class="detail-label">Full Name:</div>
                <div class="detail-value"><?php echo htmlspecialchars($verificationResult['surname'] . ' ' . $verificationResult['firstname'] . ' ' . $verificationResult['othername']); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Matric Number:</div>
                <div class="detail-value"><?php echo htmlspecialchars($verificationResult['matricnum']); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Programme:</div>
                <div class="detail-value"><?php echo htmlspecialchars($verificationResult['programme']); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">School:</div>
                <div class="detail-value"><?php echo htmlspecialchars($verificationResult['School']); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Session:</div>
                <div class="detail-value"><?php echo htmlspecialchars($verificationResult['Session']); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Payment Status:</div>
                <div class="detail-value <?php echo $verificationResult['payment_verified'] ? 'status-verified' : 'status-unverified'; ?>">
                    <?php echo $verificationResult['payment_verified'] ? 'VERIFIED' : 'NOT VERIFIED'; ?>
                </div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Clearance Status:</div>
                <div class="detail-value <?php echo $verificationResult['payment_verified'] ? 'status-verified' : 'status-unverified'; ?>">
                    <?php echo $verificationResult['payment_verified'] ? 'CLEARED FOR GRADUATION' : 'NOT CLEARED'; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <br>
        <a href="hod.php" class="logout-btn">Back to Dashboard</a>
    </div>
</body>
</html>