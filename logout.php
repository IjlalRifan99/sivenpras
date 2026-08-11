<?php
session_start();

// Hapus semua data session
$_SESSION = [];
session_unset();
session_destroy();

// Redirect / kembalikan user ke halaman login
header("Location: login.php");
exit;
?>