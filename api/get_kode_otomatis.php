<?php
error_reporting(0);
ini_set('display_errors', 0);

include __DIR__ . '/../config/koneksi.php';

header('Content-Type: application/json');

if (ob_get_length()) ob_clean();

if (isset($_POST['id_barang']) && !empty($_POST['id_barang'])) {
    $id_barang = (int)$_POST['id_barang'];
    $id_ruangan = isset($_POST['id_ruangan']) ? (int) $_POST['id_ruangan'] : 0;

    // 1. Ambil data dari tabel master barang
    $q_barang = mysqli_query($koneksi, "SELECT * FROM barang WHERE id_barang = '$id_barang'");
    
    if ($q_barang && mysqli_num_rows($q_barang) > 0) {
        $d_barang = mysqli_fetch_assoc($q_barang);

        // default kategori_id
        $kat_id = intval($d_barang['kategori_id'] ?? 0);
        $kode_baru = '';

        // Next sequence based on existing barcode pattern: {id_barang}-{ruangan_code}-{seq}
        if ($id_ruangan > 0) {
            // derive ruangan_code from ruangan.nama_ruangan (digits only) fallback to id
            $q_r = mysqli_query($koneksi, "SELECT nama_ruangan FROM ruangan WHERE id_ruangan = '$id_ruangan' LIMIT 1");
            $ruangan_code = '';
            if ($q_r && mysqli_num_rows($q_r) > 0) {
                $d_r = mysqli_fetch_assoc($q_r);
                $ruangan_code = preg_replace('/[^0-9]/', '', $d_r['nama_ruangan']);
            }
            if ($ruangan_code === '') $ruangan_code = (string) $id_ruangan;

            $like_pattern = $id_barang . '-' . $ruangan_code . '-%';
            $q_max = mysqli_query($koneksi, "SELECT barcode FROM inventaris WHERE barang_id = '$id_barang' AND ruangan_id = '$id_ruangan' AND barcode LIKE '$like_pattern' ORDER BY id_inventaris DESC LIMIT 1");
        } else {
            $like_pattern = $id_barang . '-%';
            $q_max = mysqli_query($koneksi, "SELECT barcode FROM inventaris WHERE barang_id = '$id_barang' AND barcode LIKE '$like_pattern' ORDER BY id_inventaris DESC LIMIT 1");
        }

        $no_next = 1;
        if ($q_max && mysqli_num_rows($q_max) > 0) {
            $d_max = mysqli_fetch_assoc($q_max);
            $parts = explode('-', $d_max['barcode']);
            $last_seq = intval(end($parts));
            $no_next = max(1, $last_seq + 1);
        }

        if ($id_ruangan > 0) {
            $kode_baru = $id_barang . '-' . $ruangan_code . '-' . str_pad($no_next, 3, '0', STR_PAD_LEFT);
        } else {
            $kode_baru = $id_barang . '-' . str_pad($no_next, 3, '0', STR_PAD_LEFT);
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