<?php
$q_ruangan = mysqli_query($koneksi, "SELECT r.id_ruangan, r.nama_ruangan, COUNT(i.id_inventaris) AS total_barang FROM ruangan r LEFT JOIN inventaris i ON r.id_ruangan = i.ruangan_id GROUP BY r.id_ruangan, r.nama_ruangan ORDER BY r.nama_ruangan ASC");
?>

<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <img src="assets/img/logotb.png" alt="SIVENPRAS-TB Logo" class="brand-logo">
        </div>
        <div class="brand-text">
            <h2>SIVENPRAS-TB</h2>
            <p>Sistem Inventaris Sarpras</p>
        </div>
    </div>

    <nav class="sidebar-menu">
        <div class="menu-label">Menu Utama</div>

        <a href="index.php" class="menu-item <?= ($active_page ?? '') == 'dashboard' ? 'active' : ''; ?>">
            <div class="menu-left">
                <i class="bi bi-grid-1x2-fill menu-icon"></i> Dashboard
            </div>
        </a>

        <a href="daftar-inventaris.php" class="menu-item <?= ($active_page ?? '') == 'inventaris' ? 'active' : ''; ?>">
            <div class="menu-left">
                <i class="bi bi-clipboard-data menu-icon"></i> Daftar Inventaris
            </div>
        </a>

        <a href="tambah-barang.php" class="menu-item <?= ($active_page ?? '') == 'tambah-barang' ? 'active' : ''; ?>">
            <div class="menu-left">
                <i class="bi bi-plus-square-fill menu-icon"></i> Tambah Barang
            </div>
        </a>

        <a href="scan-barcode.php" class="menu-item <?= ($active_page ?? '') == 'scan-barcode' ? 'active' : ''; ?>">
            <div class="menu-left">
                <i class="bi bi-upc-scan menu-icon"></i> Scan Barcode
            </div>
        </a>

        <a href="laporan.php" class="menu-item <?= ($active_page ?? '') == 'laporan' ? 'active' : ''; ?>">
            <div class="menu-left">
                <i class="bi bi-bar-chart-line-fill menu-icon"></i> Laporan
            </div>
        </a>
        
        <a href="ruangan.php" class="menu-item <?= ($active_page ?? '') == 'ruangan' ? 'active' : ''; ?>">
            <div class="menu-left">
                <i class="bi bi-building-fill menu-icon"></i> Ruangan
            </div>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="menu-label">Sekolah</div>
        <h4>SMK TARUNA BANGSA</h4>
        <p>Tahun Ajaran 2025/2026</p>
    </div>

    <a href="logout.php" class="menu-item logout-item" onclick="return confirm('Apakah Anda yakin ingin keluar?')">
        <div class="menu-left">
            <i class="bi bi-box-arrow-right menu-icon"></i> Logout
        </div>
    </a>
</aside>