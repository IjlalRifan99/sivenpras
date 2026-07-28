<?php
$host     = "localhost";
$username = "root";       
$password = "";           
$dbname   = "sivenpras_tb";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Koneksi ke database gagal: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>