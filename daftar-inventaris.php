<?php
include 'config/koneksi.php';

// --- LOGIKA FILTER & PENCARIAN ---
$search   = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, $_GET['search']) : '';
$kategori = isset($_GET['kategori']) ? mysqli_real_escape_string($koneksi, $_GET['kategori']) : '';
$kondisi  = isset($_GET['kondisi']) ? mysqli_real_escape_string($koneksi, $_GET['kondisi']) : '';

$where_clauses = [];

if (!empty($search)) {
    $where_clauses[] = "(i.nama_barang LIKE '%$search%' OR i.kode_barang LIKE '%$search%' OR r.nama_ruangan LIKE '%$search%')";
}
if (!empty($kategori)) {
    $where_clauses[] = "i.id_kategori = '$kategori'";
}
if (!empty($kondisi)) {
    $where_clauses[] = "d.kondisi = '$kondisi'";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(' AND ', $where_clauses);
}

// Query Utama Fetch Data Barang
$query = "
    SELECT 
        i.id_inventaris,
        i.kode_barang,
        i.nama_barang,
        i.deskripsi,
        i.satuan,
        k.nama_kategori,
        r.nama_ruangan,
        COUNT(d.id_detail) AS jumlah_unit,
        GROUP_CONCAT(DISTINCT d.kondisi SEPARATOR ', ') AS daftar_kondisi
    FROM inventaris i
    LEFT JOIN kategori k ON i.id_kategori = k.id_kategori
    LEFT JOIN ruangan r ON i.id_ruangan = r.id_ruangan
    LEFT JOIN detail_inventaris d ON i.id_inventaris = d.id_inventaris
    $where_sql
    GROUP BY i.id_inventaris
    ORDER BY i.id_inventaris DESC
";
$result_barang = mysqli_query($koneksi, $query);

// Query untuk Dropdown Kategori di Filter
$q_filter_kategori = mysqli_query($koneksi, "SELECT * FROM kategori ORDER BY nama_kategori ASC");

// Query List Ruangan untuk Sidebar Dinamis
$q_ruangan = mysqli_query($koneksi, "
    SELECT r.id_ruangan, r.nama_ruangan, COUNT(d.id_detail) AS total_barang
    FROM ruangan r
    LEFT JOIN inventaris i ON r.id_ruangan = i.id_ruangan
    LEFT JOIN detail_inventaris d ON i.id_inventaris = d.id_inventaris
    GROUP BY r.id_ruangan, r.nama_ruangan
    ORDER BY r.nama_ruangan ASC
");
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIVENPRAS - Daftar Inventaris</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>

<body>

    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <i class="ph-bold ph-archive-box"></i>
            </div>
            <div class="brand-text">
                <h2>SIVENPRAS</h2>
                <p>Sistem Inventaris Sarpras</p>
            </div>
        </div>

        <nav class="sidebar-menu">
            <div class="menu-label">Menu Utama</div>
            <a href="index.php" class="menu-item">
                <div class="menu-left">
                    <i class="ph ph-squares-four"></i> Dashboard
                </div>
            </a>
            <a href="daftar-inventaris.php" class="menu-item active">
                <div class="menu-left">
                    <i class="ph ph-clipboard-text"></i> Daftar Inventaris
                </div>
            </a>
            <a href="tambah-barang.php" class="menu-item">
                <div class="menu-left">
                    <i class="ph ph-plus"></i> Tambah Barang
                </div>
            </a>
            <a href="laporan.php" class="menu-item">
                <div class="menu-left">
                    <i class="ph ph-chart-bar"></i> Laporan
                </div>
            </a>

            <div class="menu-label" style="margin-top: 30px;">
                Ruangan <i class="ph ph-caret-up" style="float: right;"></i>
            </div>
            
            <?php 
            if ($q_ruangan && mysqli_num_rows($q_ruangan) > 0) {
                while ($r = mysqli_fetch_assoc($q_ruangan)) { 
            ?>
                <a href="ruangan/index.php?id=<?= $r['id_ruangan']; ?>" class="menu-item">
                    <div class="menu-left">
                        <i class="ph ph-house"></i> <?= htmlspecialchars($r['nama_ruangan']); ?>
                    </div>
                    <span class="badge"><?= $r['total_barang']; ?></span>
                </a>
            <?php 
                }
            } 
            ?>
        </nav>

        <div class="sidebar-footer">
            <div class="menu-label" style="margin: 0 0 10px 0;">Sekolah</div>
            <h4>SMK TARUNA BANGSA</h4>
            <p>Tahun Ajaran 2025/2026</p>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumb">
                SIVENPRAS-TB &rsaquo; <span>Daftar Inventaris</span>
            </div>
            <div class="topbar-actions">
                <a href="tambah-barang.php" class="btn-primary" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="ph-bold ph-plus"></i> Tambah Barang
                </a>
                <div class="avatar">AD</div>
            </div>
        </header>

        <div class="dashboard-container">
            <div class="page-header">
                <h1>Daftar Inventaris</h1>
                <p>Total <?= $result_barang ? mysqli_num_rows($result_barang) : 0; ?> jenis barang tercatat dalam sistem</p>
            </div>

            <form method="GET" action="daftar-inventaris.php" class="filter-bar" style="display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 250px; position: relative;">
                    <input type="text" name="search" placeholder="Cari nama, kode, atau lokasi..." value="<?= htmlspecialchars($search); ?>" style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">
                </div>

                <select name="kategori" onchange="this.form.submit()" style="padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">
                    <option value="">Semua Kategori</option>
                    <?php 
                    if ($q_filter_kategori) {
                        while ($kat = mysqli_fetch_assoc($q_filter_kategori)) { ?>
                            <option value="<?= $kat['id_kategori']; ?>" <?= ($kategori == $kat['id_kategori']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($kat['nama_kategori']); ?>
                            </option>
                        <?php 
                        } 
                    }
                    ?>
                </select>

                <select name="kondisi" onchange="this.form.submit()" style="padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">
                    <option value="">Semua Kondisi</option>
                    <option value="Baik" <?= ($kondisi == 'Baik') ? 'selected' : ''; ?>>Baik</option>
                    <option value="Rusak Ringan" <?= ($kondisi == 'Rusak Ringan') ? 'selected' : ''; ?>>Rusak Ringan</option>
                    <option value="Rusak Berat" <?= ($kondisi == 'Rusak Berat') ? 'selected' : ''; ?>>Rusak Berat</option>
                </select>

                <?php if (!empty($search) || !empty($kategori) || !empty($kondisi)) { ?>
                    <a href="daftar-inventaris.php" style="padding: 10px 14px; background: #eee; color: #333; text-decoration: none; border-radius: 8px; font-size: 14px;">Reset</a>
                <?php } ?>
            </form>

            <div class="widget-card" style="padding: 0; overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b;">
                            <th style="padding: 14px 18px;">NO</th>
                            <th style="padding: 14px 18px;">KODE</th>
                            <th style="padding: 14px 18px;">NAMA BARANG</th>
                            <th style="padding: 14px 18px;">KATEGORI</th>
                            <th style="padding: 14px 18px;">KONDISI</th>
                            <th style="padding: 14px 18px;">JUMLAH</th>
                            <th style="padding: 14px 18px;">LOKASI</th>
                            <th style="padding: 14px 18px; text-align: center;">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($result_barang && mysqli_num_rows($result_barang) > 0) {
                            $no = 1;
                            while ($row = mysqli_fetch_assoc($result_barang)) { 
                                $badge_class = 'green';
                                $kondisi_text = $row['daftar_kondisi'] ?? 'Baik';
                                
                                if (strpos($kondisi_text, 'Rusak Berat') !== false) {
                                    $badge_class = 'red';
                                } elseif (strpos($kondisi_text, 'Rusak Ringan') !== false) {
                                    $badge_class = 'yellow';
                                }
                        ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 14px 18px; font-weight: 500; color: #64748b;"><?= $no++; ?></td>
                                <td style="padding: 14px 18px; font-weight: 700; color: #0f172a;"><?= htmlspecialchars($row['kode_barang']); ?></td>
                                <td style="padding: 14px 18px;">
                                    <strong style="display: block; color: #1e293b;"><?= htmlspecialchars($row['nama_barang']); ?></strong>
                                    <small style="color: #94a3b8;"><?= htmlspecialchars($row['deskripsi']); ?></small>
                                </td>
                                <td style="padding: 14px 18px; color: #475569;"><?= htmlspecialchars($row['nama_kategori'] ?? '-'); ?></td>
                                <td style="padding: 14px 18px;">
                                    <span class="dot <?= $badge_class; ?>" style="display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:5px;"></span>
                                    <?= htmlspecialchars($kondisi_text); ?>
                                </td>
                                <td style="padding: 14px 18px; font-weight: 600; color: #0f172a;">
                                    <?= number_format($row['jumlah_unit']); ?> <span style="font-weight: 400; font-size: 12px; color: #64748b;"><?= htmlspecialchars($row['satuan']); ?></span>
                                </td>
                                <td style="padding: 14px 18px; color: #475569;"><?= htmlspecialchars($row['nama_ruangan'] ?? '-'); ?></td>
                                <td style="padding: 14px 18px; text-align: center;">
                                    <div style="display: flex; gap: 6px; justify-content: center;">
                                        <a href="detail-barang.php?id=<?= $row['id_inventaris']; ?>" style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; text-decoration: none; color: #334155; font-size: 12px; font-weight: 500;">Detail</a>
                                        <a href="edit-barang.php?id=<?= $row['id_inventaris']; ?>" style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; text-decoration: none; color: #334155; font-size: 12px; font-weight: 500;">Edit</a>
                                        <a href="hapus-barang.php?id=<?= $row['id_inventaris']; ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus barang ini?')" style="padding: 6px 10px; border: 1px solid #fecaca; background: #fef2f2; border-radius: 6px; text-decoration: none; color: #dc2626; font-size: 12px; font-weight: 500;">Hapus</a>
                                    </div>
                                </td>
                            </tr>
                        <?php 
                            }
                        } else {
                            echo "<tr><td colspan='8' style='text-align: center; padding: 30px; color: #94a3b8;'>Tidak ada data barang yang ditemukan.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

</body>

</html>