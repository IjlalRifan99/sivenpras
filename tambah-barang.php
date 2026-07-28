<?php
include 'config/koneksi.php';

$pesan_sukses = "";
$pesan_error  = "";

// --- PROSES SIMPAN DATA SAAT FORM DISUBMIT ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_barang     = mysqli_real_escape_string($koneksi, $_POST['nama_barang']);
    $kategori_id     = mysqli_real_escape_string($koneksi, $_POST['kategori_id']);
    $kondisi         = mysqli_real_escape_string($koneksi, $_POST['kondisi']);
    $jumlah          = (int) $_POST['jumlah'];
    $satuan          = mysqli_real_escape_string($koneksi, $_POST['satuan']);
    $ruangan_id      = mysqli_real_escape_string($koneksi, $_POST['ruangan_id']);
    $tahun_perolehan = mysqli_real_escape_string($koneksi, $_POST['tahun_perolehan']);
    $keterangan      = mysqli_real_escape_string($koneksi, $_POST['keterangan']);

    if (empty($nama_barang) || empty($kategori_id) || empty($ruangan_id) || $jumlah < 1) {
        $pesan_error = "Harap isi semua kolom wajib dengan benar!";
    } else {
        // Generate Kode Barang Otomatis (misal: BRG-001)
        $q_code = mysqli_query($koneksi, "SELECT MAX(id) AS max_id FROM inventaris");
        $d_code = mysqli_fetch_assoc($q_code);
        $next_id = ($d_code['max_id'] ?? 0) + 1;
        $kode_barang = "BRG-" . str_pad($next_id, 3, "0", STR_PAD_LEFT);

        // Transaksi Database (Mulai simpan)
        mysqli_begin_transaction($koneksi);

        try {
            // 1. Simpan Ke Tabel inventaris
            $q_inv = "INSERT INTO inventaris (kode_barang, nama_barang, deskripsi, kategori_id, satuan, ruangan_id, tahun_perolehan) 
                      VALUES ('$kode_barang', '$nama_barang', '$keterangan', '$kategori_id', '$satuan', '$ruangan_id', '$tahun_perolehan')";
            mysqli_query($koneksi, $q_inv);
            
            $inventaris_id = mysqli_insert_id($koneksi);

            // 2. Looping Simpan Ke Tabel detail_inventaris Sebanyak Jumlah Unit
            for ($i = 1; $i <= $jumlah; $i++) {
                $kode_barcode = $kode_barang . "-" . str_pad($i, 2, "0", STR_PAD_LEFT);
                $q_detail = "INSERT INTO detail_inventaris (inventaris_id, kode_barcode, kondisi, keterangan) 
                             VALUES ('$inventaris_id', '$kode_barcode', '$kondisi', '$keterangan')";
                mysqli_query($koneksi, $q_detail);
            }

            // Commit jika semua query berhasil
            mysqli_commit($koneksi);
            $pesan_sukses = "Barang berhasil ditambahkan dengan $jumlah unit detail!";
        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $pesan_error = "Gagal menyimpan data: " . $e->getMessage();
        }
    }
}

// Fetch Dropdown Data
$q_kategori = mysqli_query($koneksi, "SELECT * FROM kategori ORDER BY nama_kategori ASC");
$q_ruangan_option = mysqli_query($koneksi, "SELECT * FROM ruangan ORDER BY nama_ruangan ASC");

// Query List Ruangan untuk Sidebar Dinamis
$q_ruangan_sidebar = mysqli_query($koneksi, "
    SELECT r.id, r.nama_ruangan, COUNT(d.id) AS total_barang
    FROM ruangan r
    LEFT JOIN inventaris i ON r.id = i.ruangan_id
    LEFT JOIN detail_inventaris d ON i.id = d.inventaris_id
    GROUP BY r.id
    ORDER BY r.nama_ruangan ASC
");
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIVENPRAS - Tambah Barang</title>
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
                <div class="menu-left"><i class="ph ph-squares-four"></i> Dashboard</div>
            </a>
            <a href="daftar-inventaris.php" class="menu-item">
                <div class="menu-left"><i class="ph ph-clipboard-text"></i> Daftar Inventaris</div>
            </a>
            <a href="tambah-barang.php" class="menu-item active">
                <div class="menu-left"><i class="ph ph-plus"></i> Tambah Barang</div>
            </a>
            <a href="laporan.php" class="menu-item">
                <div class="menu-left"><i class="ph ph-chart-bar"></i> Laporan</div>
            </a>

            <div class="menu-label" style="margin-top: 30px;">
                Ruangan <i class="ph ph-caret-up" style="float: right;"></i>
            </div>
            
            <?php while ($r = mysqli_fetch_assoc($q_ruangan_sidebar)) { ?>
                <a href="ruangan.php?id=<?= $r['id']; ?>" class="menu-item">
                    <div class="menu-left"><i class="ph ph-house"></i> <?= htmlspecialchars($r['nama_ruangan']); ?></div>
                    <span class="badge"><?= $r['total_barang']; ?></span>
                </a>
            <?php } ?>
        </nav>

        <div class="sidebar-footer">
            <div class="menu-label" style="margin: 0 0 10px 0;">Sekolah</div>
            <h4>SMK TARUNA BANGSA</h4>
            <p>Tahun Ajaran 2024/2025</p>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumb">
                SIVENPRAS-TB &rsaquo; <span>Tambah Barang</span>
            </div>
            <div class="topbar-actions">
                <div class="avatar">AD</div>
            </div>
        </header>

        <div class="dashboard-container">
            <?php if (!empty($pesan_sukses)) { ?>
                <div style="background: #dcfce7; color: #15803d; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 500;">
                    <?= $pesan_sukses; ?>
                </div>
            <?php } ?>

            <?php if (!empty($pesan_error)) { ?>
                <div style="background: #fee2e2; color: #b91c1c; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 500;">
                    <?= $pesan_error; ?>
                </div>
            <?php } ?>

            <form method="POST" action="tambah-barang.php" class="widget-card" style="padding: 24px; max-width: 900px;">
                
                <div style="margin-bottom: 24px;">
                    <h4 style="color: #0f172a; margin-bottom: 16px; font-size: 14px; letter-spacing: 0.5px; border-left: 3px solid #0d9488; padding-left: 8px;">IDENTITAS BARANG</h4>
                    
                    <div style="margin-bottom: 16px;">
                        <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">NAMA BARANG <span style="color:red;">*</span></label>
                        <input type="text" name="nama_barang" placeholder="Contoh: Meja Siswa Kayu" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">KATEGORI <span style="color:red;">*</span></label>
                            <select name="kategori_id" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                                <option value="">-- Pilih Kategori --</option>
                                <?php while ($k = mysqli_fetch_assoc($q_kategori)) { ?>
                                    <option value="<?= $k['id']; ?>"><?= htmlspecialchars($k['nama_kategori']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">KONDISI BARANG <span style="color:red;">*</span></label>
                            <select name="kondisi" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                                <option value="Baik">Baik</option>
                                <option value="Rusak Ringan">Rusak Ringan</option>
                                <option value="Rusak Berat">Rusak Berat</option>
                                <option value="Tidak Layak">Tidak Layak</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: 24px;">
                    <h4 style="color: #0f172a; margin-bottom: 16px; font-size: 14px; letter-spacing: 0.5px; border-left: 3px solid #0d9488; padding-left: 8px;">DETAIL & LOKASI</h4>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">JUMLAH <span style="color:red;">*</span></label>
                            <input type="number" name="jumlah" value="1" min="1" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">SATUAN</label>
                            <select name="satuan" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                                <option value="Unit">Unit</option>
                                <option value="Buah">Buah</option>
                                <option value="Set">Set</option>
                                <option value="Pcs">Pcs</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">LOKASI / RUANGAN <span style="color:red;">*</span></label>
                            <select name="ruangan_id" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                                <option value="">-- Pilih Ruangan --</option>
                                <?php while ($r = mysqli_fetch_assoc($q_ruangan_option)) { ?>
                                    <option value="<?= $r['id']; ?>"><?= htmlspecialchars($r['nama_ruangan']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: 24px;">
                    <h4 style="color: #0f172a; margin-bottom: 16px; font-size: 14px; letter-spacing: 0.5px; border-left: 3px solid #0d9488; padding-left: 8px;">DATA PEROLEHAN</h4>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">TAHUN PEROLEHAN <span style="color:red;">*</span></label>
                            <input type="number" name="tahun_perolehan" value="<?= date('Y'); ?>" min="2000" max="2099" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">NILAI PEROLEHAN (RP / UNIT)</label>
                            <input type="number" name="nilai_perolehan" value="0" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                        </div>
                    </div>

                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">KETERANGAN</label>
                        <textarea name="keterangan" rows="3" placeholder="Catatan tambahan tentang barang ini (opsional)" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
                    </div>
                </div>

                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <a href="daftar-inventaris.php" style="padding: 10px 20px; border: 1px solid #cbd5e1; border-radius: 8px; text-decoration: none; color: #475569; font-weight: 600; font-size: 14px;">Batal</a>
                    <button type="submit" class="btn-primary" style="padding: 10px 24px; font-size: 14px;">Simpan Barang</button>
                </div>

            </form>
        </div>
    </main>

</body>

</html>