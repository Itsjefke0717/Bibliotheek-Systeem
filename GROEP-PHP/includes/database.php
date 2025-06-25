<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "bibliotheek";

//$servername = "sql210.infinityfree.com";
//$username = "if0_38663356";
//$password = "Moortje2017";
//$dbname = "if0_38663356_Bibliotheek";

// Maak verbinding met de database
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Verbinding mislukt: " . $conn->connect_error);
}

