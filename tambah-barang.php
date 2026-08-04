<?php
include 'config/koneksi.php';

$pesan_sukses = "";
$pesan_error = "";

// QUERY DROPDOWN & SIDEBAR
$q_barang_katalog = mysqli_query($koneksi, "SELECT * FROM barang ORDER BY nama_barang ASC");
$q_kategori       = mysqli_query($koneksi, "SELECT * FROM kategori ORDER BY nama_kategori ASC");
$q_ruangan_option  = mysqli_query($koneksi, "SELECT * FROM ruangan ORDER BY nama_ruangan ASC");

// Query Ruangan untuk Sidebar
$q_ruangan_sidebar = mysqli_query($koneksi, "
    SELECT r.id_ruangan, r.nama_ruangan, COUNT(d.id_detail) AS total_barang 
    FROM ruangan r 
    LEFT JOIN inventaris i ON r.id_ruangan = i.id_ruangan 
    LEFT JOIN detail_inventaris d ON i.id_inventaris = d.id_inventaris 
    GROUP BY r.id_ruangan, r.nama_ruangan
");

// PROSES FORM SUBMIT
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_barang       = (int) $_POST['id_barang'];
    $kode_barang     = mysqli_real_escape_string($koneksi, $_POST['kode_barang']);
    $id_kategori     = (int) $_POST['id_kategori'];
    $kondisi         = mysqli_real_escape_string($koneksi, $_POST['kondisi']);
    $jumlah          = (int) $_POST['jumlah'];
    $satuan          = mysqli_real_escape_string($koneksi, $_POST['satuan']);
    $id_ruangan      = (int) $_POST['id_ruangan'];
    $tahun_perolehan = (int) $_POST['tahun_perolehan'];
    $keterangan      = mysqli_real_escape_string($koneksi, $_POST['keterangan']);

    // Validasi Sederhana Input Wajib
    if ($id_barang == 0 || $id_kategori == 0 || $id_ruangan == 0 || empty($kode_barang)) {
        $pesan_error = "Harap lengkapi semua pilihan wajib (Nama Barang, Kategori, dan Ruangan)!";
    } else {
        // Ambil nama barang dari tabel master
        $q_get_nama  = mysqli_query($koneksi, "SELECT nama_barang FROM barang WHERE id_barang = '$id_barang'");
        $d_get_nama  = mysqli_fetch_assoc($q_get_nama);
        $nama_barang = $d_get_nama ? mysqli_real_escape_string($koneksi, $d_get_nama['nama_barang']) : '';

        // 1. Simpan ke Tabel Inventaris
        $query_inv = "INSERT INTO inventaris (id_barang, kode_barang, nama_barang, id_kategori, satuan, id_ruangan, tahun_perolehan, deskripsi) 
                      VALUES ('$id_barang', '$kode_barang', '$nama_barang', '$id_kategori', '$satuan', '$id_ruangan', '$tahun_perolehan', '$keterangan')";

        if (mysqli_query($koneksi, $query_inv)) {
            $inventaris_id = mysqli_insert_id($koneksi);

            // 2. Generate Detail Unit Fisik ke detail_inventaris
            $sukses_detail = true;
            for ($i = 1; $i <= $jumlah; $i++) {
                $kode_barcode = $kode_barang . "-" . str_pad($i, 2, "0", STR_PAD_LEFT);

                $query_detail = "INSERT INTO detail_inventaris (id_inventaris, kode_barcode, kondisi, keterangan) 
                                 VALUES ('$inventaris_id', '$kode_barcode', '$kondisi', '$keterangan')";

                if (!mysqli_query($koneksi, $query_detail)) {
                    $sukses_detail = false;
                    $error_detail_msg = mysqli_error($koneksi);
                    break;
                }
            }

            if ($sukses_detail) {
                $pesan_sukses = "Data barang berhasil disimpan beserta $jumlah unit detail fisiknya!";
            } else {
                $pesan_error = "Barang utama tersimpan, namun gagal membuat detail fisik: " . $error_detail_msg;
            }
        } else {
            // Tampilkan error database secara spesifik
            $pesan_error = "Gagal menyimpan ke tabel inventaris: " . mysqli_error($koneksi);
        }
    }
}
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
                <a href="ruangan.php?id=<?= $r['id_ruangan']; ?>" class="menu-item">
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
                <div style="background: #dcfce7; color: #15803d; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; border-left: 4px solid #22c55e;">
                    <?= $pesan_sukses; ?>
                </div>
            <?php } ?>

            <?php if (!empty($pesan_error)) { ?>
                <div style="background: #fee2e2; color: #b91c1c; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; border-left: 4px solid #ef4444;">
                    <?= $pesan_error; ?>
                </div>
            <?php } ?>

            <form method="POST" action="tambah-barang.php" class="widget-card" style="padding: 24px; max-width: 900px; background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">

                <div style="margin-bottom: 24px;">
                    <h4 style="color: #0f172a; margin-bottom: 16px; font-size: 14px; letter-spacing: 0.5px; border-left: 3px solid #0d9488; padding-left: 8px;">
                        IDENTITAS BARANG
                    </h4>

                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                                NAMA BARANG KATALOG <span style="color:red;">*</span>
                            </label>
                            <select name="id_barang" id="id_barang" onchange="generateKodeOtomatis()" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                                <option value="">-- Pilih Barang --</option>
                                <?php while ($b = mysqli_fetch_assoc($q_barang_katalog)) { ?>
                                    <option value="<?= $b['id_barang']; ?>"><?= htmlspecialchars($b['nama_barang']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                                KODE BARANG
                            </label>
                            <input type="text" name="kode_barang" id="kode_barang" readonly placeholder="Kode Otomatis..." style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; background: #f8fafc; font-weight: 600;">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                                KATEGORI <span style="color:red;">*</span>
                            </label>
                            <select name="id_kategori" id="id_kategori" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                                <option value="">-- Pilih Kategori --</option>
                                <?php while ($k = mysqli_fetch_assoc($q_kategori)) { ?>
                                    <option value="<?= $k['id_kategori']; ?>"><?= htmlspecialchars($k['nama_kategori']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                                KONDISI BARANG <span style="color:red;">*</span>
                            </label>
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
                    <h4 style="color: #0f172a; margin-bottom: 16px; font-size: 14px; letter-spacing: 0.5px; border-left: 3px solid #0d9488; padding-left: 8px;">
                        DETAIL & LOKASI
                    </h4>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                                JUMLAH <span style="color:red;">*</span>
                            </label>
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
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                                LOKASI / RUANGAN <span style="color:red;">*</span>
                            </label>
                            <select name="id_ruangan" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                                <option value="">-- Pilih Ruangan --</option>
                                <?php while ($r = mysqli_fetch_assoc($q_ruangan_option)) { ?>
                                    <option value="<?= $r['id_ruangan']; ?>"><?= htmlspecialchars($r['nama_ruangan']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: 24px;">
                    <h4 style="color: #0f172a; margin-bottom: 16px; font-size: 14px; letter-spacing: 0.5px; border-left: 3px solid #0d9488; padding-left: 8px;">
                        DATA PEROLEHAN
                    </h4>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                                TAHUN PEROLEHAN <span style="color:red;">*</span>
                            </label>
                            <input type="number" name="tahun_perolehan" value="<?= date('Y'); ?>" min="2000" max="2099" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                                NILAI PEROLEHAN (RP / UNIT)
                            </label>
                            <input type="number" name="nilai_perolehan" value="0" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                        </div>
                    </div>

                    <div>
                        <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">KETERANGAN</label>
                        <textarea name="keterangan" rows="3" placeholder="Catatan tambahan tentang barang ini (opsional)" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
                    </div>
                </div>

                <div style="display: flex; gap: 12px; justify-content: flex-end; border-top: 1px solid #f1f5f9; padding-top: 16px;">
                    <a href="daftar-inventaris.php" style="padding: 10px 20px; border: 1px solid #cbd5e1; border-radius: 8px; text-decoration: none; color: #475569; font-weight: 600; font-size: 14px;">Batal</a>
                    <button type="submit" class="btn-primary" style="padding: 10px 24px; font-size: 14px; cursor: pointer;">Simpan Barang</button>
                </div>

            </form>
        </div>
    </main>

    <script>
        function generateKodeOtomatis() {
            var idBarang = document.getElementById("id_barang").value;
            var inputKode = document.getElementById("kode_barang");
            var selectKategori = document.getElementById("id_kategori");

            if (idBarang === "") {
                inputKode.value = "";
                selectKategori.value = "";
                return;
            }

            inputKode.value = "Memuat...";

            var formData = new FormData();
            formData.append('id_barang', idBarang);

            fetch('get_kode_otomatis.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                try {
                    var data = JSON.parse(text);
                    if (data.status === 'success') {
                        inputKode.value = data.kode_barang;
                        if (data.kategori_id && data.kategori_id != 0) {
                            selectKategori.value = String(data.kategori_id);
                        } else {
                            selectKategori.value = "";
                        }
                    } else {
                        inputKode.value = "";
                        selectKategori.value = "";
                    }
                } catch (e) {
                    console.error("Respon bukan JSON valid:", text);
                    inputKode.value = "";
                }
            })
            .catch(error => {
                console.error('Error Fetch:', error);
                inputKode.value = "";
            });
        }
    </script>

</body>

</html>