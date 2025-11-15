<?php
$serverName        = "77.68.113.42";
$connectionOptions = array(
    "Database" => "erp",
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
    echo "We are experiencing temporary technical issues. Please try again later.";
    exit;
}




?>