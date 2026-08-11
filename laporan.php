<?php
session_start();
include 'config/koneksi.php';

if (!isset($_SESSION['login'])) {
    header("Location:login.php");
    exit;
}

$kategori_id = isset($_GET['kategori']) ? mysqli_real_escape_string($koneksi, $_GET['kategori']) : '';
$ruangan_id = isset($_GET['ruangan']) ? mysqli_real_escape_string($koneksi, $_GET['ruangan']) : '';
$kondisi = isset($_GET['kondisi']) ? mysqli_real_escape_string($koneksi, $_GET['kondisi']) : '';

$where_kategori = !empty($kategori_id) ? "AND b.kategori_id = '$kategori_id'" : "";
$where_ruangan = !empty($ruangan_id) ? "AND i.ruangan_id = '$ruangan_id'" : "";
$where_kondisi = !empty($kondisi) ? "AND i.kondisi = '$kondisi'" : "";

if (isset($_GET['action']) && $_GET['action'] == 'export_excel') {
    $file_name = "Laporan inventaris SIVENPRAS" . date('Y-m-d') . ".xls";

    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");

    $q_excel_kat = mysqli_query($koneksi, "SELECT
    k.nama_kategori,
    COUNT(i.id_inventaris) AS total,
    SUM(CASE WHEN i.kondisi = 'baik' THEN 1 ELSE 0 END) AS baik,
    SUM(CASE WHEN i.kondisi = 'rusak' THEN 1 ELSE 0 END) AS rusak,
    SUM(CASE WHEN i.kondisi = 'hilang' THEN 1 ELSE 0 END) AS hilang
    FROM kategori k
    LEFT JOIN barang b ON k.id_kategori = b.kategori_id $where_kategori
    LEFT JOIN inventaris i ON b.id_barang = i.barang_id $where_ruangan $where_kondisi
    GROUP BY k.id_kategori
    HAVING total > 0 
    ORDER BY k.nama_kategori ASC");

    $q_excel_ruang = mysqli_query($koneksi, "SELECT
    r.nama_ruangan,
    COUNT(i.id_inventaris) AS total,
    SUM(CASE WHEN i.kondisi = 'baik' THEN 1 ELSE 0 END) AS baik,
    SUM(CASE WHEN i.kondisi = 'rusak' THEN 1 ELSE 0 END) AS rusak,
    SUM(CASE WHEN i.kondisi = 'hilang' THEN 1 ELSE 0 END) AS hilang
    FROM ruangan r
    LEFT JOIN inventaris i ON r.id_ruangan = i.ruangan_id $where_ruangan $where_kondisi
    LEFT JOIN barang b ON i.barang_id = b.id_barang $where_kategori
    GROUP BY r.id_ruangan
    HAVING total > 0
    ORDER BY r.nama_ruangan ASC");
?>
<h3>LAPORAN REKAP INVENTARIS SARPRAS</h3>
<p>SMK TARUNA BANGSA - Tanggal Unduh: <?= date('d-m-Y H:i'); ?></p>
<br>

<h4>1. REKAP PER KATEGORI</h4>
    <table border="1" cellpadding="5">
        <thead>
            <tr style="background-color: #0d9488; color: #ffffff;">
                <th>No</th>
                <th>Kategori</th>
                <th>Total</th>
                <th>Baik</th>
                <th>Rusak</th>
                <th>Hilang</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            while($row = mysqli_fetch_assoc($q_excel_kat)): 
            ?>
            <tr>
                <td><?= $no++; ?></td>
                <td><?= $row['nama_kategori']; ?></td>
                <td><?= $row['total']; ?></td>
                <td><?= $row['baik']; ?></td>
                <td><?= $row['rusak']; ?></td>
                <td><?= $row['hilang']; ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <br>
    <h4>2. REKAP PER RUANGAN</h4>
    <table border="1" cellpadding="5">
        <thead>
            <tr style="background-color: #0d9488; color: #ffffff;">
                <th>No</th>
                <th>Ruangan</th>
                <th>Total Barang</th>
                <th>Baik</th>
                <th>Rusak</th>
                <th>Hilang</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            while($row = mysqli_fetch_assoc($q_excel_ruang)): 
            ?>
            <tr>
                <td><?= $no++; ?></td>
                <td><?= $row['nama_ruangan']; ?></td>
                <td><?= $row['total']; ?></td>
                <td><?= $row['baik']; ?></td>
                <td><?= $row['rusak']; ?></td>
                <td><?= $row['hilang']; ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php
    exit;
}

$active_page = 'laporan';
$page_title = 'Laporan Inventaris';
$breadcrumb = 'Laporan';

// Query Rekap Kategori
$query_rekap_kategori = "
    SELECT 
        k.nama_kategori,
        COUNT(i.id_inventaris) AS total,
        SUM(CASE WHEN i.kondisi = 'baik' THEN 1 ELSE 0 END) AS baik,
        SUM(CASE WHEN i.kondisi = 'rusak' THEN 1 ELSE 0 END) AS rusak,
        SUM(CASE WHEN i.kondisi = 'hilang' THEN 1 ELSE 0 END) AS hilang
    FROM kategori k
    LEFT JOIN barang b ON k.id_kategori = b.kategori_id $where_kategori
    LEFT JOIN inventaris i ON b.id_barang = i.barang_id $where_ruangan $where_kondisi
    GROUP BY k.id_kategori
    HAVING total > 0
    ORDER BY k.nama_kategori ASC
";
$result_rekap_kategori = mysqli_query($koneksi, $query_rekap_kategori);

// Query Rekap Ruangan
$query_rekap_ruangan = "
    SELECT 
        r.nama_ruangan,
        COUNT(i.id_inventaris) AS total,
        SUM(CASE WHEN i.kondisi = 'baik' THEN 1 ELSE 0 END) AS baik,
        SUM(CASE WHEN i.kondisi = 'rusak' THEN 1 ELSE 0 END) AS rusak,
        SUM(CASE WHEN i.kondisi = 'hilang' THEN 1 ELSE 0 END) AS hilang
    FROM ruangan r
    LEFT JOIN inventaris i ON r.id_ruangan = i.ruangan_id $where_ruangan $where_kondisi
    LEFT JOIN barang b ON i.barang_id = b.id_barang $where_kategori
    GROUP BY r.id_ruangan
    HAVING total > 0
    ORDER BY r.nama_ruangan ASC
";
$result_rekap_ruangan = mysqli_query($koneksi, $query_rekap_ruangan);

// Dropdown Data
$q_kategori = mysqli_query($koneksi, "SELECT * FROM kategori ORDER BY nama_kategori ASC");
$q_ruangan  = mysqli_query($koneksi, "SELECT * FROM ruangan ORDER BY nama_ruangan ASC");
?>

<?php include 'includes/header.php'; ?>

<div class="dashboard-container">
    
    <div class="page-header no-print">
        <h1>Laporan Rekapitulasi Inventaris</h1>
        <p>Rekapitulasi total data sarana dan prasarana berdasarkan kategori dan ruangan</p>
    </div>

    <div class="widget-card no-print" style="margin-bottom: 20px; padding: 20px;">
        <form method="GET" action="laporan.php" class="filter-grid">
            
            <div class="filter-item">
                <label>Kategori</label>
                <select name="kategori" class="filter-select">
                    <option value="">Semua Kategori</option>
                    <?php while ($kat = mysqli_fetch_assoc($q_kategori)): ?>
                        <option value="<?= $kat['id_kategori']; ?>" <?= ($kategori_id == $kat['id_kategori']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($kat['nama_kategori']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="filter-item">
                <label>Ruangan</label>
                <select name="ruangan" class="filter-select">
                    <option value="">Semua Ruangan</option>
                    <?php while ($ruang = mysqli_fetch_assoc($q_ruangan)): ?>
                        <option value="<?= $ruang['id_ruangan']; ?>" <?= ($ruangan_id == $ruang['id_ruangan']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($ruang['nama_ruangan']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="filter-item">
                <label>Kondisi</label>
                <select name="kondisi" class="filter-select">
                    <option value="">Semua Kondisi</option>
                    <option value="baik" <?= ($kondisi == 'baik') ? 'selected' : ''; ?>>Baik</option>
                    <option value="rusak" <?= ($kondisi == 'rusak') ? 'selected' : ''; ?>>Rusak</option>
                    <option value="hilang" <?= ($kondisi == 'hilang') ? 'selected' : ''; ?>>Hilang</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn-filter">🔍 Filter</button>
                <a href="laporan.php" class="btn-reset">Reset</a>
            </div>

        </form>

        <div style="margin-top: 15px; pt-3; border-top: 1px solid #f1f5f9; display: flex; gap: 10px; justify-content: flex-end;">
            <a href="laporan.php?action=export_excel&kategori=<?= $kategori_id; ?>&ruangan=<?= $ruangan_id; ?>&kondisi=<?= $kondisi; ?>" class="btn-export-excel">
                📊 Export Excel
            </a>
            <button onclick="window.print()" class="btn-print">
                🖨️ Cetak / PDF
            </button>
        </div>
    </div>

    <div class="print-header print-only">
        <h2 style="margin: 0; font-size: 18px;">SMK TARUNA BANGSA</h2>
        <p style="margin: 2px 0; font-size: 14px;">LAPORAN REKAPITULASI SARANA DAN PRASARANA</p>
        <p style="margin: 0; font-size: 11px; color: #555;">Tahun Ajaran 2025/2026 | Tanggal Cetak: <?= date('d F Y'); ?></p>
        <hr style="border: 1px solid #000; margin: 15px 0;">
    </div>

    <h3 class="section-title">Rekap Per Kategori</h3>
    <div class="widget-card table-wrapper" style="margin-bottom: 25px;">
        <table class="custom-table print-table">
            <thead>
                <tr>
                    <th style="width: 50px;">NO</th>
                    <th>KATEGORI</th>
                    <th style="text-align: center;">TOTAL</th>
                    <th style="text-align: center;">BAIK</th>
                    <th style="text-align: center;">RUSAK</th>
                    <th style="text-align: center;">HILANG</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($result_rekap_kategori && mysqli_num_rows($result_rekap_kategori) > 0) {
                    $no = 1;
                    while ($row = mysqli_fetch_assoc($result_rekap_kategori)) {
                        ?>
                        <tr>
                            <td><?= $no++; ?></td>
                            <td><strong><?= htmlspecialchars($row['nama_kategori']); ?></strong></td>
                            <td style="text-align: center; font-weight: 600;"><?= number_format($row['total']); ?></td>
                            <td style="text-align: center; color: #16a34a; font-weight: 600;"><?= number_format($row['baik']); ?></td>
                            <td style="text-align: center; color: #ca8a04; font-weight: 600;"><?= number_format($row['rusak']); ?></td>
                            <td style="text-align: center; color: #dc2626; font-weight: 600;"><?= number_format($row['hilang']); ?></td>
                        </tr>
                        <?php
                    }
                } else {
                    echo "<tr><td colspan='6' style='text-align: center; padding: 20px; color: #94a3b8;'>Data tidak ditemukan.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <h3 class="section-title">Rekap Per Ruangan</h3>
    <div class="widget-card table-wrapper">
        <table class="custom-table print-table">
            <thead>
                <tr>
                    <th style="width: 50px;">NO</th>
                    <th>RUANGAN</th>
                    <th style="text-align: center;">TOTAL BARANG</th>
                    <th style="text-align: center;">BAIK</th>
                    <th style="text-align: center;">RUSAK</th>
                    <th style="text-align: center;">HILANG</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($result_rekap_ruangan && mysqli_num_rows($result_rekap_ruangan) > 0) {
                    $no = 1;
                    while ($row = mysqli_fetch_assoc($result_rekap_ruangan)) {
                        ?>
                        <tr>
                            <td><?= $no++; ?></td>
                            <td><strong><?= htmlspecialchars($row['nama_ruangan']); ?></strong></td>
                            <td style="text-align: center; font-weight: 600;"><?= number_format($row['total']); ?></td>
                            <td style="text-align: center; color: #16a34a; font-weight: 600;"><?= number_format($row['baik']); ?></td>
                            <td style="text-align: center; color: #ca8a04; font-weight: 600;"><?= number_format($row['rusak']); ?></td>
                            <td style="text-align: center; color: #dc2626; font-weight: 600;"><?= number_format($row['hilang']); ?></td>
                        </tr>
                        <?php
                    }
                } else {
                    echo "<tr><td colspan='6' style='text-align: center; padding: 20px; color: #94a3b8;'>Data tidak ditemukan.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- <div class="print-footer print-only" style="margin-top: 50px; display: flex; justify-content: space-between;">
        <div style="text-align: center; width: 220px;">
            <p style="margin: 0;">Mengetahui,</p>
            <p style="margin: 0; font-weight: bold;">Kepala Sekolah</p>
            <br><br><br><br>
            <p style="margin: 0;">____________________</p>
        </div>
        <div style="text-align: center; width: 220px;">
            <p style="margin: 0;">Bekasi, <?= date('d F Y'); ?></p>
            <p style="margin: 0; font-weight: bold;">Pengelola Sarpras</p>
            <br><br><br><br>
            <p style="margin: 0;">____________________</p>
        </div>
    </div> -->

</div>

<style>
    .section-title {
        font-size: 16px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 12px;
    }
    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
        align-items: end;
    }
    .filter-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .filter-item label {
        font-size: 12px;
        font-weight: 600;
        color: #475569;
    }
    .filter-select {
        padding: 8px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 13px;
        outline: none;
        background: #fff;
    }
    .filter-actions {
        display: flex;
        gap: 6px;
    }
    .btn-filter {
        padding: 8px 14px;
        background: #0d9488;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
        font-size: 13px;
    }
    .btn-reset {
        padding: 8px 14px;
        background: #e2e8f0;
        color: #334155;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 500;
        font-size: 13px;
        text-align: center;
    }
    .btn-export-excel {
        background: #16a34a;
        color: white;
        padding: 8px 14px;
        border-radius: 6px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
    }
    .btn-print {
        background: #0284c7;
        color: white;
        padding: 8px 14px;
        border: none;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
    }

    .print-only {
        display: none;
    }

    /* MEDIA PRINT (Saat Di-Cetak) */
    @media print {
        .no-print, .sidebar, header, nav, .top-navbar {
            display: none !important;
        }
        .print-only {
            display: block !important;
        }
        body {
            background: #fff !important;
            font-family: Arial, sans-serif;
            color: #000;
        }
        .dashboard-container {
            padding: 0 !important;
            margin: 0 !important;
        }
        .widget-card {
            box-shadow: none !important;
            border: none !important;
            padding: 0 !important;
        }
        .print-table {
            width: 100%;
            border-collapse: collapse !important;
            margin-bottom: 20px;
        }
        .print-table th, .print-table td {
            border: 1px solid #000 !important;
            padding: 6px 10px !important;
            font-size: 12px !important;
        }
        .print-table th {
            background-color: #f1f5f9 !important;
            -webkit-print-color-adjust: exact;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>