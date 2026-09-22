<?php
$q_ruangan = mysqli_query($koneksi, "SELECT r.id_ruangan, r.nama_ruangan, COUNT(i.id_inventaris) AS total_barang FROM ruangan r LEFT JOIN inventaris i ON r.id_ruangan = i.ruangan_id GROUP BY r.id_ruangan, r.nama_ruangan ORDER BY r.nama_ruangan ASC");

$user_role = strtolower($_SESSION['role'] ?? '');
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
        overflow-y: visible;
        z-index: 1000;
        transition: width 0.25s ease, left 0.25s ease;
    }

    .sidebar.collapsed {
        width: 82px;
    }

    .sidebar.collapsed .brand-text,
    .sidebar.collapsed .menu-label,
    .sidebar.collapsed .menu-text,
    .sidebar.collapsed .sidebar-footer,
    .sidebar.collapsed .logout-item .menu-text {
        display: none;
    }

    .sidebar.collapsed .sidebar-brand {
        justify-content: center;
        padding: 12px 8px;
    }

    .sidebar.collapsed .menu-item {
        justify-content: center;
        padding-left: 0;
        padding-right: 0;
    }

    .sidebar.collapsed .menu-item .menu-left {
        gap: 0;
    }

    .sidebar.collapsed .menu-item.active {
        border-left: none;
        padding-left: 0;
    }

    .sidebar.collapsed .inventory-menu-group .inventory-menu-chevron {
        display: none;
    }

    .sidebar.collapsed .inventory-submenu {
        display: none !important;
    }

    .sidebar.collapsed .sidebar-footer {
        display: none;
    }

    .sidebar.collapsed .menu-item,
    .sidebar.collapsed .inventory-menu-group > .menu-item {
        padding-top: 12px;
        padding-bottom: 12px;
    }

    .sidebar-menu {
        padding: 10px 0 0;
        display: flex;
        flex-direction: column;
        position: relative;
    }

    /* Styling Dropdown Inventaris Menjadi Pop-up di Samping */
    .inventory-menu-group { 
        position: relative; 
        margin: 0; 
    }
    
    .inventory-menu-group > .menu-item { 
        cursor: pointer; 
    }
    
    .inventory-menu-chevron { 
        font-size: 12px; 
        transition: transform .2s ease; 
    }
    
    .inventory-menu-group.open .inventory-menu-chevron { 
        transform: rotate(180deg); 
    }
    
    /* Kotak Pop-up Submenu di Samping Kanan (Menimpa Konten) */
    .inventory-submenu { 
        display: none; 
        position: absolute; 
        left: 245px; 
        top: 0;
        min-width: 180px;
        background-color: var(--primary-dark); 
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
        border-radius: 8px;
        padding: 6px;
        z-index: 9999;
        border: 1px solid rgba(255, 255, 255, 0.15);
    }

    .inventory-menu-group.open .inventory-submenu {
        display: block;
    }

    .inventory-submenu-item { 
        display: block; 
        padding: 8px 12px; 
        color: var(--text-sidebar); 
        font-size: 12px; 
        text-decoration: none; 
        border-radius: 5px; 
        white-space: nowrap;
    }
    .inventory-submenu-item:hover, 
    .inventory-submenu-item.active { 
        background: rgba(255, 255, 255, 0.1); 
        color: #fff; 
    }

    /* Sidebar Brand */
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

    .brand-text h2 {
        font-size: clamp(13px, 1vw, 16px);
        letter-spacing: 1px;
        font-weight: 700;
    }

    .brand-text p {
        font-size: clamp(10px, 0.75vw, 11px);
        color: var(--text-sidebar);
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
        flex-shrink: 0;
    }

    .menu-text {
        white-space: nowrap;
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

    .sidebar-footer {
        padding: 20px 24px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        margin-top: auto;
        flex-shrink: 0;
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

<aside class="sidebar" id="mainSidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <img src="assets/img/logotb.png" alt="SIVENPRAS-TB Logo" class="brand-logo">
        </div>
        <div class="brand-text">
            <h2>SIVENPRAS-TB</h2>
            <p>Sistem Inventaris Sarpras</p>
        </div>
    </div>

    <!-- Menu Utama -->
    <nav class="sidebar-menu">
        <div class="menu-label">Menu Utama</div>
        <a href="index.php" class="menu-item <?= ($active_page ?? '') == 'dashboard' ? 'active' : ''; ?>">
            <div class="menu-left">
                <i class="bi bi-grid-1x2-fill menu-icon"></i>
                <span class="menu-text">Dashboard</span>
            </div>
        </a>

        <!-- Dropdown Pop-up ke Samping -->
        <div class="inventory-menu-group" id="inventoryDropdown">
            <a href="daftar-inventaris.php" class="menu-item <?= in_array(($active_page ?? ''), ['inventaris', 'bhp'], true) ? 'active' : ''; ?>" id="inventoryToggle">
                <div class="menu-left">
                    <i class="bi bi-clipboard-data menu-icon"></i>
                    <span class="menu-text">Daftar Inventaris</span>
                </div>
                <i class="bi bi-chevron-down inventory-menu-chevron"></i>
            </a>
            <div class="inventory-submenu">
                <a href="daftar-inventaris.php" class="inventory-submenu-item <?= ($active_page ?? '') == 'inventaris' ? 'active' : ''; ?>">Aset Tetap</a>
                <a href="daftar-bhp.php" class="inventory-submenu-item <?= ($active_page ?? '') == 'bhp' ? 'active' : ''; ?>">Barang Habis Pakai</a>
            </div>
        </div>

        <?php if($user_role !== 'kepala_sekolah'): ?>
        <a href="tambah-barang.php" class="menu-item <?= ($active_page ?? '') == 'tambah-barang' ? 'active' : ''; ?>">
            <div class="menu-left">
                <i class="bi bi-plus-square-fill menu-icon"></i>
                <span class="menu-text">Tambah Barang</span>
            </div>
        </a>
        <?php endif; ?>

        <a href="scan-barcode.php" class="menu-item <?= ($active_page ?? '') == 'scan-barcode' ? 'active' : ''; ?>">
            <div class="menu-left">
                <i class="bi bi-upc-scan menu-icon"></i>
                <span class="menu-text">Scan Barang</span>
            </div>
        </a>

        <a href="ruangan.php" class="menu-item <?= ($active_page ?? '') == 'ruangan' ? 'active' : ''; ?>">
            <div class="menu-left">
                <i class="bi bi-building-fill menu-icon"></i>
                <span class="menu-text">Ruangan</span>
            </div>
        </a>

        <div class="inventory-menu-group" id="reportDropdown">
            <a href="laporan.php" class="menu-item <?= in_array(($active_page ?? ''), ['laporan', 'laporan-bhp'], true) ? 'active' : ''; ?>" id="reportToggle">
                <div class="menu-left">
                    <i class="bi bi-file-earmark-text menu-icon"></i>
                    <span class="menu-text">Laporan</span>
                </div>
                <i class="bi bi-chevron-down inventory-menu-chevron"></i>
            </a>
            <div class="inventory-submenu">
                <a href="laporan.php" class="inventory-submenu-item <?= ($active_page ?? '') == 'laporan' ? 'active' : ''; ?>">Aset Tetap</a>
                <a href="laporan-bhp.php" class="inventory-submenu-item <?= ($active_page ?? '') == 'laporan-bhp' ? 'active' : ''; ?>">Barang Habis Pakai</a>
            </div>
        </div>
    </nav>

    <!-- Menu Admin -->
    <?php if($user_role === 'admin'): ?>
    <nav class="sidebar-menu" style="border-top: 1px solid rgba(255,255,255,0.1); padding-top: 5px; margin-top: 5px;">
        <div class="menu-label">Admin</div>
        <a href="manajemen-user.php" class="menu-item <?= ($active_page ?? '') == 'user' ? 'active' : ''; ?>">
            <div class="menu-left">
                <i class="bi bi-people-fill menu-icon"></i>
                <span class="menu-text">Manajemen User</span>
            </div>
        </a>
        <a href="manajemen-kategori.php" class="menu-item <?= ($active_page ?? '') == 'kategori' ? 'active' : ''; ?>">
            <div class="menu-left">
                <i class="bi bi-tags-fill menu-icon"></i>
                <span class="menu-text">Manajemen Kategori</span>
            </div>
        </a>
        <a href="manajemen-ruangan.php" class="menu-item <?= ($active_page ?? '') == 'manajemen-ruangan' ? 'active' : ''; ?>">
            <div class="menu-left">
                <i class="bi bi-building-fill menu-icon"></i>
                <span class="menu-text">Manajemen Ruangan</span>
            </div>
        </a>
    </nav>
    <?php endif; ?>

    <!-- Footer Sekolah -->
    <div class="sidebar-footer">
        <div class="menu-label" style="margin-top:0;">Sekolah</div>
        <h4>SMK TARUNA BANGSA</h4>
        <p>Tahun Ajaran 2025/2026</p>
    </div>

    <!-- Tombol Logout -->
    <a href="logout.php" class="menu-item logout-item" onclick="return confirm('Apakah Anda yakin ingin keluar?')" style="padding-bottom: 20px;">
        <div class="menu-left">
            <i class="bi bi-box-arrow-right menu-icon"></i>
            <span class="menu-text">Logout</span>
        </div>
    </a>
</aside>

<!-- JavaScript Pengondisian Pop-up Tertutup Total Saat Diklik -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('mainSidebar');
    const dropdown = document.getElementById('inventoryDropdown');
    const toggleBtn = document.getElementById('inventoryToggle');
    const reportDropdown = document.getElementById('reportDropdown');
    const reportToggle = document.getElementById('reportToggle');
    const submenuItems = document.querySelectorAll('.inventory-submenu-item');

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            const isMobileCollapsed = window.innerWidth <= 640 && document.body.classList.contains('mobile-sidebar-collapsed');

            if (isMobileCollapsed) {
                return true;
            }

            if (sidebar && sidebar.classList.contains('collapsed')) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();
            dropdown.classList.toggle('open');
        });
    }

    if (reportToggle) {
        reportToggle.addEventListener('click', function(e) {
            const isMobileCollapsed = window.innerWidth <= 640 && document.body.classList.contains('mobile-sidebar-collapsed');

            if (isMobileCollapsed || (sidebar && sidebar.classList.contains('collapsed'))) {
                return true;
            }

            e.preventDefault();
            e.stopPropagation();
            reportDropdown.classList.toggle('open');
        });
    }

    submenuItems.forEach(item => {
        item.addEventListener('click', function() {
            dropdown.classList.remove('open');
            reportDropdown.classList.remove('open');
        });
    });

    document.addEventListener('click', function(event) {
        if (dropdown && !dropdown.contains(event.target)) {
            dropdown.classList.remove('open');
        }
        if (reportDropdown && !reportDropdown.contains(event.target)) {
            reportDropdown.classList.remove('open');
        }
    });

    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    if (sidebarToggleBtn && sidebar) {
        const applyMobileSidebarState = () => {
            const isMobile = window.innerWidth <= 640;
            const body = document.body;

            if (!isMobile) {
                body.classList.remove('mobile-sidebar-collapsed');
                sidebar.classList.remove('mobile-collapsed');
                sidebar.classList.remove('collapsed');
                return;
            }
        };

        sidebarToggleBtn.addEventListener('click', function() {
            if (window.innerWidth > 640) {
                return;
            }

            document.body.classList.toggle('mobile-sidebar-collapsed');
            const isCollapsed = document.body.classList.contains('mobile-sidebar-collapsed');
            sidebar.classList.toggle('mobile-collapsed', isCollapsed);
            sidebar.classList.toggle('collapsed', isCollapsed);
        });

        window.addEventListener('resize', function() {
            applyMobileSidebarState();
        });
        applyMobileSidebarState();
    }
});
</script>