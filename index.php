<?php
require_once("cann.php");

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $message = '<div style="color: red; text-align: center; margin-bottom: 20px;">Please fill in all fields.</div>';
    } else {
        // Query ClearanceAdmins table
        $query = "SELECT id, username, password FROM [Final_Clearance].[dbo].[ClearanceAdmins] WHERE username = ?";
        $stmt = sqlsrv_prepare($conn, $query, [$username]);

        if ($stmt === false) {
            $message = '<div style="color: red; text-align: center; margin-bottom: 20px;">Database error. Please try again.</div>';
        } else {
            if (sqlsrv_execute($stmt)) {
                if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    if (password_verify($password, $row['password'])) {
                        // Successful login
                        session_start();
                        $_SESSION['username'] = $row['username'];
                        $_SESSION['id'] = $row['id'];

                        header("Location: hod.php");
                        exit();
                    } else {
                        $message = '<div style="color: red; text-align: center; margin-bottom: 20px;">Invalid username or password.</div>';
                    }
                } else {
                    $message = '<div style="color: red; text-align: center; margin-bottom: 20px;">Invalid username or password.</div>';
                }
            } else {
                $message = '<div style="color: red; text-align: center; margin-bottom: 20px;">Database error. Please try again.</div>';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>YABATECH Clearance Admin  Login</title>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Poppins', sans-serif;
      height: 100vh;
      background: linear-gradient(to right, #006400, #FFD700);
      background-image: url('https://upload.wikimedia.org/wikipedia/commons/thumb/1/1e/Yaba_College_of_Technology_logo.svg/1200px-Yaba_College_of_Technology_logo.svg.png');
      background-repeat: no-repeat;
      background-position: center center;
      background-size: 60%;
      background-blend-mode: lighten;
      display: flex;
      justify-content: center;
      align-items: center;
      overflow: hidden;
    }

    .login-card {
      position: relative;
      background: rgba(255, 255, 255, 0.95);
      border-radius: 16px;
      padding: 40px;
      width: 100%;
      max-width: 400px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
      z-index: 1;
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .login-card .logo {
      width: 80px;
      margin-bottom: 15px;
    }

    .login-card h2 {
      margin-bottom: 20px;
      color: #006400;
      font-weight: 700;
    }

    .login-card p {
      margin-bottom: 30px;
      font-size: 14px;
      color: #333;
    }

    .form-group {
      margin-bottom: 20px;
      text-align: left;
    }

    .form-group label {
      display: block;
      margin-bottom: 6px;
      font-weight: 600;
      color: #333;
    }

    .form-group input {
      width: 100%;
      padding: 12px;
      border: none;
      border-radius: 8px;
      background: #f0f0f0;
      font-size: 14px;
    }

    .form-group input:focus {
      background: #fff;
      outline: none;
      box-shadow: 0 0 0 3px rgba(0, 128, 0, 0.2);
    }

    .login-btn {
      width: 100%;
      background-color: #FFD700;
      color: #006400;
      font-size: 16px;
      font-weight: bold;
      border: none;
      padding: 14px;
      border-radius: 10px;
      cursor: pointer;
      transition: background-color 0.3s ease;
    }

    .login-btn:hover {
      background-color: #FFC107;
    }

    .footer-text {
      margin-top: 20px;
      font-size: 13px;
      color: #555;
    }

    .back-link {
      margin-top: 15px;
      text-align: center;
    }

    .back-link a {
      color: #006400;
      text-decoration: none;
      font-weight: bold;
      font-size: 12px;
    }

    .back-link a:hover {
      text-decoration: underline;
    }

    @media (max-width: 480px) {
      .login-card {
        margin: 20px;
        padding: 30px 20px;
      }

      body {
        background-size: 80%;
      }
    }
  </style>
</head>
<body>
  <div class="login-card">

    <img src="imgg/yabalogo.jpg" alt="YABATECH Logo" class="logo" />
    <h2>Clearance Admin Login Portal</h2>
    <p>Authorized access only. Use your admin credentials to proceed.</p>

    <div class="back-link">
  <a href="Manual.pdf" target="_blank">📖 </a>
    </div>

    <?php echo $message; ?>

    <form method="post" action="">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" name="username" placeholder="Enter your username" required />
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" name="password" placeholder="Enter your password" required />
      </div>
      <button type="submit" class="login-btn">Login</button>
    </form>

    
    <p class="footer-text">Need help? Contact the CITM unit or clearance office.</p>
  </div>
</body>
</html>