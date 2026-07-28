<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIVENPRAS - Dashboard</title>
    <!-- Memanggil file CSS Anda -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Memanggil Icon Library (Phosphor Icons) -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body>

    <!-- SIDEBAR -->
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
            <a href="#" class="menu-item">
                <div class="menu-left">
                    <i class="ph ph-clipboard-text"></i> Daftar Inventaris
                </div>
            </a>
            <a href="#" class="menu-item">
                <div class="menu-left">
                    <i class="ph ph-plus"></i> Tambah Barang
                </div>
            </a>
            <a href="#" class="menu-item">
                <div class="menu-left">
                    <i class="ph ph-chart-bar"></i> Laporan
                </div>
            </a>

            <div class="menu-label" style="margin-top: 30px;">Ruangan <i class="ph ph-caret-up" style="float: right;"></i></div>
            <!-- Nanti bagian ini bisa di-looping pakai PHP -->
            <a href="#" class="menu-item">
                <div class="menu-left"><i class="ph ph-house"></i> Aula</div>
                <span class="badge">3</span>
            </a>
            <a href="#" class="menu-item">
                <div class="menu-left"><i class="ph ph-house"></i> Gudang</div>
                <span class="badge">25</span>
            </a>
            <a href="#" class="menu-item">
                <div class="menu-left"><i class="ph ph-house"></i> Lab IPA</div>
                <span class="badge">15</span>
            </a>
            <a href="#" class="menu-item">
                <div class="menu-left"><i class="ph ph-house"></i> Lab Komputer</div>
                <span class="badge">35</span>
            </a>
            <a href="#" class="menu-item">
                <div class="menu-left"><i class="ph ph-house"></i> Lapangan</div>
                <span class="badge">8</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="menu-label" style="margin: 0 0 10px 0;">Sekolah</div>
            <h4>SMP Negeri 1 Tasikmalaya</h4>
            <p>Tahun Ajaran 2024/2025</p>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <!-- Topbar -->
        <header class="topbar">
            <div class="breadcrumb">
                SIVENPRAS-TB &rsaquo; <span>Dashboard</span>
            </div>
            <div class="topbar-actions">
                <button class="btn-primary">
                    <i class="ph-bold ph-plus"></i> Tambah Barang
                </button>
                <div class="avatar">AD</div>
            </div>
        </header>

        <!-- Dashboard Konten -->
        <div class="dashboard-container">
            <div class="page-header">
                <h1>Dashboard Inventaris</h1>
                <p>Ringkasan data sarana dan prasarana sekolah — diperbarui hari ini</p>
            </div>

            <!-- Kartu Statistik -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Total Barang</h3>
                        <div class="icon-wrapper icon-teal"><i class="ph-bold ph-cube"></i></div>
                    </div>
                    <div class="stat-value teal">548</div>
                    <div class="stat-desc">12 jenis barang</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Kondisi Baik</h3>
                        <div class="icon-wrapper icon-green"><i class="ph-bold ph-check-circle"></i></div>
                    </div>
                    <div class="stat-value green">541</div>
                    <div class="stat-desc">99% dari total</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Rusak Ringan</h3>
                        <div class="icon-wrapper icon-yellow"><i class="ph-bold ph-warning"></i></div>
                    </div>
                    <div class="stat-value yellow">5</div>
                    <div class="stat-desc">Perlu perhatian</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <h3>Rusak Berat</h3>
                        <div class="icon-wrapper icon-red"><i class="ph-bold ph-x-circle"></i></div>
                    </div>
                    <div class="stat-value red">2</div>
                    <div class="stat-desc">Perlu penggantian</div>
                </div>
            </div>

            <!-- Widget Bawah -->
            <div class="dashboard-widgets">
                
                <!-- Grafik Top Kategori -->
                <div class="widget-card">
                    <h3>Top Kategori Barang</h3>
                    
                    <div class="progress-item">
                        <div class="progress-info">
                            <span>Meja & Kursi</span>
                            <span>242</span>
                        </div>
                        <div class="progress-bg"><div class="progress-fill" style="width: 85%;"></div></div>
                    </div>

                    <div class="progress-item">
                        <div class="progress-info">
                            <span>Perpustakaan</span>
                            <span>208</span>
                        </div>
                        <div class="progress-bg"><div class="progress-fill" style="width: 75%;"></div></div>
                    </div>

                    <div class="progress-item">
                        <div class="progress-info">
                            <span>Elektronik</span>
                            <span>50</span>
                        </div>
                        <div class="progress-bg"><div class="progress-fill" style="width: 30%;"></div></div>
                    </div>
                    
                    <div class="progress-item">
                        <div class="progress-info">
                            <span>Kebersihan & Sanitasi</span>
                            <span>25</span>
                        </div>
                        <div class="progress-bg"><div class="progress-fill" style="width: 15%;"></div></div>
                    </div>

                    <div class="progress-item">
                        <div class="progress-info">
                            <span>Laboratorium</span>
                            <span>15</span>
                        </div>
                        <div class="progress-bg"><div class="progress-fill" style="width: 10%;"></div></div>
                    </div>
                </div>

                <!-- Distribusi Kondisi -->
                <div class="widget-card">
                    <h3>Distribusi Kondisi</h3>
                    <ul class="kondisi-list">
                        <li class="kondisi-item">
                            <div class="kondisi-left">
                                <div class="dot green"></div> Baik
                            </div>
                            <div class="kondisi-value">541</div>
                        </li>
                        <li class="kondisi-item">
                            <div class="kondisi-left">
                                <div class="dot yellow"></div> Rusak Ringan
                            </div>
                            <div class="kondisi-value">5</div>
                        </li>
                        <li class="kondisi-item">
                            <div class="kondisi-left">
                                <div class="dot red"></div> Rusak Berat
                            </div>
                            <div class="kondisi-value">2</div>
                        </li>
                    </ul>
                </div>

            </div>
        </div>
    </main>

</body>
</html>