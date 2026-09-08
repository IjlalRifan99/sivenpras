<?php
session_start();
header('Content-Type: application/json');
include 'config/koneksi.php';

if (!isset($_SESSION['login'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi telah berakhir. Silakan login kembali.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barcode = trim($_POST['barcode'] ?? '');

    if (empty($barcode)) {
        echo json_encode(['status' => 'error', 'message' => 'Kode barcode kosong.']);
        exit;
    }

    $barcode_escaped = mysqli_real_escape_string($koneksi, $barcode);

    $query = mysqli_query($koneksi, "
        SELECT i.id_inventaris, i.barcode, i.keterangan, i.kondisi, i.ruangan_id,
               b.id_barang, b.nama_barang,
               k.nama_kategori, r.nama_ruangan
        FROM inventaris i
        JOIN barang b ON i.barang_id = b.id_barang
        LEFT JOIN kategori k ON b.kategori_id = k.id_kategori
        LEFT JOIN ruangan r ON i.ruangan_id = r.id_ruangan
        WHERE i.barcode = '$barcode_escaped' 
        LIMIT 1
    ");

    if ($query && mysqli_num_rows($query) > 0) {
        $data = mysqli_fetch_assoc($query);
        echo json_encode([
            'status' => 'success',
            'data' => $data
        ]);
    } else {
        echo json_encode([
            'status' => 'not_found',
            'message' => 'Barcode "' . $barcode . '" tidak ditemukan di database.',
            'barcode' => $barcode
        ]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Metode request tidak valid.']);