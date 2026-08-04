<?php
include 'config/koneksi.php';

// 1. Query Statistik Utama (Total Barang & Kondisi)
$q_stats = mysqli_query($koneksi, "
    SELECT 
        COUNT(*) AS total_barang,
        SUM(CASE WHEN kondisi = 'Baik' THEN 1 ELSE 0 END) AS kondisi_baik,
        SUM(CASE WHEN kondisi = 'Rusak Ringan' THEN 1 ELSE 0 END) AS rusak_ringan,
        SUM(CASE WHEN kondisi = 'Rusak Berat' THEN 1 ELSE 0 END) AS rusak_berat
    FROM detail_inventaris
");
$d_stats = mysqli_fetch_assoc($q_stats);

$total_barang = $d_stats['total_barang'] ?? 0;
$kondisi_baik = $d_stats['kondisi_baik'] ?? 0;
$rusak_ringan  = $d_stats['rusak_ringan'] ?? 0;
$rusak_berat   = $d_stats['rusak_berat'] ?? 0;

$persen_baik  = ($total_barang > 0) ? round(($kondisi_baik / $total_barang) * 100) : 0;

// 2. Total Jenis Barang (Dari tabel master inventaris)
$q_jenis = mysqli_query($koneksi, "SELECT COUNT(*) AS total_jenis FROM inventaris");
$d_jenis = mysqli_fetch_assoc($q_jenis);
$total_jenis = $d_jenis['total_jenis'] ?? 0;

// 3. Top Kategori Barang
$q_top_kategori = mysqli_query($koneksi, "
    SELECT k.nama_kategori, COUNT(d.id_detail) AS total_unit
    FROM detail_inventaris d
    JOIN inventaris i ON d.id_inventaris = i.id_inventaris
    JOIN kategori k ON i.id_kategori = k.id_kategori
    GROUP BY k.id_kategori
    ORDER BY total_unit DESC
    LIMIT 5
");

// 4. Query List Ruangan + Jumlah Barang Per Ruangan (Sidebar Dinamis)
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
    <title>SIVENPRAS - Dashboard</title>
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
            <a href="index.php" class="menu-item active">
                <div class="menu-left">
                    <i class="ph ph-squares-four"></i> Dashboard
                </div>
            </a>
            <a href="daftar-inventaris.php" class="menu-item">
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
            } else {
                echo "<p style='padding: 0 15px; color: #aaa; font-size: 12px;'>Belum ada data ruangan</p>";
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
                SIVENPRAS-TB &rsaquo; <span>Dashboard</span>
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
                <h1>Dashboard Inventaris</h1>
                <p>Ringkasan data sarana dan prasarana sekolah — diperbarui hari ini</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Total Barang</h3>
                        <div class="icon-wrapper icon-teal"><i class="ph-bold ph-cube"></i></div>
                    </div>
                    <div class="stat-value teal"><?= number_format($total_barang); ?></div>
                    <div class="stat-desc"><?= $total_jenis; ?> jenis barang</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Kondisi Baik</h3>
                        <div class="icon-wrapper icon-green"><i class="ph-bold ph-check-circle"></i></div>
                    </div>
                    <div class="stat-value green"><?= number_format($kondisi_baik); ?></div>
                    <div class="stat-desc"><?= $persen_baik; ?>% dari total</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Rusak Ringan</h3>
                        <div class="icon-wrapper icon-yellow"><i class="ph-bold ph-warning"></i></div>
                    </div>
                    <div class="stat-value yellow"><?= number_format($rusak_ringan); ?></div>
                    <div class="stat-desc">Perlu perhatian</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Rusak Berat</h3>
                        <div class="icon-wrapper icon-red"><i class="ph-bold ph-x-circle"></i></div>
                    </div>
                    <div class="stat-value red"><?= number_format($rusak_berat); ?></div>
                    <div class="stat-desc">Perlu penggantian</div>
                </div>
            </div>

            <div class="dashboard-widgets">

                <div class="widget-card">
                    <h3>Top Kategori Barang</h3>

                    <?php
                    if ($q_top_kategori && mysqli_num_rows($q_top_kategori) > 0) {
                        while ($row = mysqli_fetch_assoc($q_top_kategori)) {
                            $persen_bar = ($total_barang > 0) ? round(($row['total_unit'] / $total_barang) * 100) : 0;
                            ?>
                            <div class="progress-item">
                                <div class="progress-info">
                                    <span><?= htmlspecialchars($row['nama_kategori']); ?></span>
                                    <span><?= $row['total_unit']; ?></span>
                                </div>
                                <div class="progress-bg">
                                    <div class="progress-fill" style="width: <?= $persen_bar; ?>%;"></div>
                                </div>
                            </div>
                        <?php
                        }
                    } else {
                        echo "<p style='color:#888; font-size:14px;'>Belum ada data barang.</p>";
                    }
                    ?>
                </div>

                <div class="widget-card">
                    <h3>Distribusi Kondisi</h3>
                    <ul class="kondisi-list">
                        <li class="kondisi-item">
                            <div class="kondisi-left">
                                <div class="dot green"></div> Baik
                            </div>
                            <div class="kondisi-value"><?= number_format($kondisi_baik); ?></div>
                        </li>
                        <li class="kondisi-item">
                            <div class="kondisi-left">
                                <div class="dot yellow"></div> Rusak Ringan
                            </div>
                            <div class="kondisi-value"><?= number_format($rusak_ringan); ?></div>
                        </li>
                        <li class="kondisi-item">
                            <div class="kondisi-left">
                                <div class="dot red"></div> Rusak Berat
                            </div>
                            <div class="kondisi-value"><?= number_format($rusak_berat); ?></div>
                        </li>
                    </ul>
                </div>

            </div>
        </div>
    </main>

</body>

</html>