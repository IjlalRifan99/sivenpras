<?php
session_start();
include 'config/koneksi.php';

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

// Cek apakah ada ruangan yang dipilih
if (!isset($_POST['ruangan']) || empty($_POST['ruangan'])) {
    header("Location: daftar-inventaris.php?error=Pilih minimal satu ruangan");
    exit;
}

$ruangan_ids = $_POST['ruangan'];
// Sanitize input
$ruangan_ids = array_map(function ($id) use ($koneksi) {
    return (int)$id;
}, $ruangan_ids);

$ruangan_placeholder = implode(',', $ruangan_ids);

// Query data inventaris berdasarkan ruangan yang dipilih
$query = "
    SELECT 
        i.id_inventaris,
        b.kode_barang,
        b.nama_barang,
        b.deskripsi,
        k.nama_kategori,
        i.barcode,
        i.kondisi,
        i.keterangan,
        r.nama_ruangan
    FROM inventaris i
    JOIN barang b ON i.barang_id = b.id_barang
    LEFT JOIN kategori k ON b.kategori_id = k.id_kategori
    LEFT JOIN ruangan r ON i.ruangan_id = r.id_ruangan
    WHERE i.ruangan_id IN ($ruangan_placeholder)
    ORDER BY r.nama_ruangan, b.kode_barang, i.barcode ASC
";

$result = mysqli_query($koneksi, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    header("Location: daftar-inventaris.php?error=Tidak ada data untuk ruangan yang dipilih");
    exit;
}

// Prepare for Excel export
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="Inventaris_' . date('Ymd_His') . '.xls"');
header('Cache-Control: max-age=0');

// Output BOM untuk UTF-8
echo chr(0xEF) . chr(0xBB) . chr(0xBF);

// Buat header tabel Excel
echo "NO\t";
echo "KODE BARANG\t";
echo "BARCODE\t";
echo "NAMA BARANG\t";
echo "DESKRIPSI\t";
echo "KATEGORI\t";
echo "RUANGAN\t";
echo "KONDISI\t";
echo "KETERANGAN\t";
echo "\n";

// Output data
$no = 1;
while ($row = mysqli_fetch_assoc($result)) {
    echo $no++ . "\t";
    echo htmlspecialchars($row['kode_barang']) . "\t";
    echo htmlspecialchars($row['barcode']) . "\t";
    echo htmlspecialchars($row['nama_barang']) . "\t";
    echo htmlspecialchars($row['deskripsi']) . "\t";
    echo htmlspecialchars($row['nama_kategori'] ?? '-') . "\t";
    echo htmlspecialchars($row['nama_ruangan'] ?? '-') . "\t";
    echo ucfirst(htmlspecialchars($row['kondisi'])) . "\t";
    echo htmlspecialchars($row['keterangan']) . "\t";
    echo "\n";
}

mysqli_close($koneksi);
?>
