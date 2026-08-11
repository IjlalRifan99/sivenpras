<?php
$q_ruangan = mysqli_query($koneksi, "SELECT r.id_ruangan, r.nama_ruangan, COUNT(i.id_inventaris) AS total_barang FROM ruangan r LEFT JOIN inventaris i ON r.id_ruangan = i.ruangan_id GROUP BY r.id_ruangan, r.nama_ruangan ORDER BY r.nama_ruangan ASC");
?>

<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <i class="ph-bold ph-archive-box"></i>
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
                <i class="ph ph-squares-four"></i> Dashboard
            </div>
        </a>

        <a href="daftar-inventaris.php" class="menu-item <?= ($active_page ?? '') == 'inventaris' ? 'active' : ''; ?>">
            <div class="menu-left">
                <i class="ph ph-clipboard-text"></i> Daftar Inventaris
            </div>
        </a>

        <a href="tambah-barang.php" class="menu-item <?= ($active_page ?? '') == 'tambah-barang' ? 'active' : ''; ?>">
            <div class="menu-left">
                <i class="ph ph-plus"></i> Tambah Barang
            </div>
        </a>

        <a href="laporan.php" class="menu-item <?= ($active_page ?? '') == 'laporan' ? 'active' : ''; ?>">
            <div class="menu-left">
                <i class="ph ph-chart-bar"></i> Laporan
            </div>
        </a>
        
        <details class="room-dropdown" id="roomDropdown">
            <summary class="room-dropdown-toggle">
                <span class="menu-left">
                    <i class="ph ph-buildings"></i> Ruangan
                </span>
                <i class="ph"></i>
            </summary>

            <div class="room-dropdown-content">
                <?php if ($q_ruangan && mysqli_num_rows($q_ruangan) > 0): ?>
                    <?php while ($r = mysqli_fetch_assoc($q_ruangan)): ?>
                        <a href="ruangan.php?id=<?= $r['id_ruangan']; ?>" class="menu-item room-item">
                            <div class="menu-left">
                                <i class="ph ph-house"></i>
                                <span><?= htmlspecialchars($r['nama_ruangan']); ?></span>
                            </div>
                            <span class="badge"><?= $r['total_barang']; ?></span>
                        </a>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="empty-room">Belum ada data ruangan</p>
                <?php endif; ?>
            </div>
        </details>
    </nav>

    <div class="sidebar-footer">
        <div class="menu-label">Sekolah</div>
        <h4>SMK TARUNA BANGSA</h4>
        <p>Tahun Ajaran 2025/2026</p>
    </div>

    <a href="logout.php" class="menu-item logout-item" onclick="return confirm('Apakah Anda yakin ingin keluar?')">
        <div class="menu-left">
            <i class="ph ph-sign-out"></i> Logout
        </div>
    </a>
</aside>