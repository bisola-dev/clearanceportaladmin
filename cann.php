<?php
// Database configuration - consider moving to environment variables for security
$serverName        = "77.68.113.42";
$connectionOptions = array(
    "Database" => "Final_Clearance",
    "Uid"      => "OakDev",
    "PWD"      => "oakj4o0Nj@bisoHUBLLH",
    "TrustServerCertificate"=> 'true',
    "Encrypt"=>'Yes'
);
//Establishes the connection
$conn = sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) {
    // Do NOT log or expose sensitive SQL details
    header("HTTP/1.1 503 Service Unavailable");
    echo "We are experiencing temporary technical issues. Please try 1again later.";
    exit;
}

// Connect to student database
$studentServerName = "77.68.113.42";
$studentConnectionOptions = array(
    "Database" => "student",
    "Uid"      => "OakDev",
    "PWD"      => "oakj4o0Nj@bisoHUBLLH",
    "TrustServerCertificate"=> 'true',
    "Encrypt"=>'Yes'
);
$student_conn = sqlsrv_connect($studentServerName, $studentConnectionOptions);
if (!$student_conn) {
    // Do NOT log or expose sensitive SQL details
    header("HTTP/1.1 503 Service Unavailable");
    echo "We are experiencing temporary technical issues. Please try again later.";
    exit;
}

// Connect to EBPORTAL database
$ebportalServerName = "77.68.113.42";
$ebportalConnectionOptions = array(
    "Database" => "EBPORTAL",
    "Uid"      => "OakDev",
    "PWD"      => "oakj4o0Nj@bisoHUBLLH",
    "TrustServerCertificate"=> 'true',
    "Encrypt"=>'Yes'
);
$ebportal_conn = sqlsrv_connect($ebportalServerName, $ebportalConnectionOptions);
if (!$ebportal_conn) {
    // Do NOT log or expose sensitive SQL details
    header("HTTP/1.1 503 Service Unavailable");
    echo "We are experiencing temporary technical issues. Please try again later.";
    exit;
}

$tstamp= date('Y-m-d');
session_start([
  'cookie_httponly' => true,
  'cookie_secure'   => isset($_SERVER['HTTPS']), // only secure if using HTTPS
  'cookie_samesite' => 'Strict',
]);

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

?>
