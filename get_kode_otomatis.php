<?php
error_reporting(0);
ini_set('display_errors', 0);

include 'config/koneksi.php';

header('Content-Type: application/json');

if (ob_get_length()) ob_clean();

if (isset($_POST['id_barang']) && !empty($_POST['id_barang'])) {
    $id_barang = (int)$_POST['id_barang'];

    // 1. Ambil data dari tabel master barang
    $q_barang = mysqli_query($koneksi, "SELECT * FROM barang WHERE id_barang = '$id_barang'");
    
    if ($q_barang && mysqli_num_rows($q_barang) > 0) {
        $d_barang = mysqli_fetch_assoc($q_barang);
        $kode_master = $d_barang['kode_barang']; // Misal: 'KR', 'MJ', 'LTP'

        // 2. Cari kode_barang terakhir di tabel inventaris yang diawali kode_master tersebut
        $q_max = mysqli_query($koneksi, "SELECT kode_barang FROM inventaris WHERE kode_barang LIKE '$kode_master-%' ORDER BY id_inventaris DESC LIMIT 1");
        
        if ($q_max && mysqli_num_rows($q_max) > 0) {
            $d_max = mysqli_fetch_assoc($q_max);
            // Ambil angka setelah tanda '-'
            $exp = explode('-', $d_max['kode_barang']);
            $no_urut = (int) end($exp);
            $no_next = $no_urut + 1;
        } else {
            $no_next = 1;
        }

        // Format kode baru: KR-001, KR-002, dst.
        $kode_baru = $kode_master . "-" . str_pad($no_next, 3, "0", STR_PAD_LEFT);

        // Ambil ID Kategori
        $kat_id = 0;
        if (isset($d_barang['kategori_id'])) {
            $kat_id = $d_barang['kategori_id'];
        } elseif (isset($d_barang['id_kategori'])) {
            $kat_id = $d_barang['id_kategori'];
        }

        echo json_encode([
            'status'      => 'success',
            'kode_barang' => $kode_baru,
            'kategori_id' => $kat_id
        ]);
        exit;
    }
}

echo json_encode([
    'status'  => 'error',
    'message' => 'Data tidak ditemukan'
]);
exit;
?>