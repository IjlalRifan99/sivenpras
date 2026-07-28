<?php 
$host     = "localhost";
$user     = "root";
$password = "";
$database = "sivenpras_tb";

$koneksi = mysqli_connect($host, $user, $password, $database);

if (!$koneksi) {
    die("Koneksi Database Gagal: " . mysqli_connect_error());
}