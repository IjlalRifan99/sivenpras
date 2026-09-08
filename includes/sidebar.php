<?php
$q_ruangan = mysqli_query($koneksi, "SELECT r.id_ruangan, r.nama_ruangan, COUNT(i.id_inventaris) AS total_barang FROM ruangan r LEFT JOIN inventaris i ON r.id_ruangan = i.ruangan_id GROUP BY r.id_ruangan, r.nama_ruangan ORDER BY r.nama_ruangan ASC");
?>

<style>
    .sidebar {
        width: 260px;
        background-color: var(--primary-dark);
        color: white;
        display: flex;
        flex-direction: column;
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        /* Tambahkan scroll otomatis agar isi tidak terpotong saat di-zoom tinggi */
        overflow-y: auto;
        z-index: 1000;
    }

    /* Duplikasi .sidebar-brand digabung jadi satu */
    .sidebar-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        background: white;
        border-radius: 16px;
        margin: 16px;
        padding: 16px;
        color: var(--brand-green);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        flex-shrink: 0;
        /* Mencegah logo terkikis saat zoom */
    }

    .brand-icon {
        background: transparent;
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        box-shadow: none;
        flex-shrink: 0;
    }

    .brand-icon img,
    .brand-icon .brand-logo {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .sidebar-brand .brand-text h2,
    .sidebar-brand .brand-text p {
        color: var(--brand-green);
    }

    /* Menggunakan clamp() agar teks h2 tidak meledak ukurannya saat zoom */
    .brand-text h2 {
        font-size: clamp(13px, 1vw, 16px);
        letter-spacing: 1px;
        font-weight: 700;
    }

    .brand-text p {
        font-size: clamp(10px, 0.75vw, 11px);
        color: var(--text-sidebar);
    }

    .sidebar-menu {
        padding: 10px 0 0;
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        min-height: 0;
    }

    .menu-label {
        font-size: clamp(9px, 0.7vw, 11px);
        font-weight: 700;
        color: rgba(255, 255, 255, 0.4);
        letter-spacing: 1px;
        margin: 15px 24px 10px;
        text-transform: uppercase;
    }

    .menu-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 24px;
        color: var(--text-sidebar);
        text-decoration: none;
        font-size: clamp(12px, 0.85vw, 14px);
        font-weight: 500;
        transition: all 0.2s;
    }

    .menu-item .menu-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .menu-item .menu-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        width: 20px;
        line-height: 1;
        color: inherit;
    }

    .mini-icon {
        font-size: 12px;
        opacity: 0.8;
    }

    .icon-inline {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        line-height: 1;
    }

    .menu-item:hover {
        color: white;
    }

    .menu-item.active {
        background-color: var(--primary-light);
        color: white;
        border-left: 4px solid var(--accent-orange);
        padding-left: 20px;
    }

    .badge {
        background-color: rgba(255, 255, 255, 0.15);
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 700;
    }

    .sidebar-footer {
        padding: 20px 24px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        flex-shrink: 0;
        /* Menjaga footer tidak terdorong keluar screen */
    }

    .sidebar-footer h4 {
        font-size: clamp(11px, 0.8vw, 13px);
        margin-bottom: 4px;
    }

    .sidebar-footer p {
        font-size: clamp(10px, 0.75vw, 12px);
        color: var(--text-sidebar);
    }
</style>

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

        <a href="ruangan.php" class="menu-item <?= ($active_page ?? '') == 'ruangan' ? 'active' : ''; ?>">
            <div class="menu-left">
                <i class="bi bi-building-fill menu-icon"></i> Ruangan
            </div>
        </a>
    </nav>

    <nav class="sidebar-menu" style="margin-bottom:20px; margin-top: 10px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
        <div class="menu-label">Admin</div>
        <a href="manajemen-user.php" class="menu-item <?= ($active_page ?? '') == 'user' ? 'active' : ''; ?>">
            <div class="menu-left">
                <i class="bi bi-people-fill menu-icon"></i> Manajemen User
            </div>
        </a>
        <a href="manajemen-kategori.php" class="menu-item <?= ($active_page ?? '') == 'kategori' ? 'active' : ''; ?>">
            <div class="menu-left">
                <i class="bi bi-tags-fill menu-icon"></i> Manajemen Kategori
            </div>
        </a>
        <a href="manajemen-ruangan.php" class="menu-item <?= ($active_page ?? '') == 'manajemen-ruangan' ? 'active' : ''; ?>">
            <div class="menu-left">
                <i class="bi bi-building-fill menu-icon"></i> Manajemen Ruangan
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