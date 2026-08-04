<?php
include 'config/koneksi.php';

$pesan_sukses = "";
$pesan_error = "";

// MODE PAGE: master barang atau tambah unit ke ruangan
$id_ruangan = isset($_GET['id_ruangan']) ? (int) $_GET['id_ruangan'] : (isset($_POST['id_ruangan']) ? (int) $_POST['id_ruangan'] : 0);
$current_year = date('Y');

// Query Ruangan untuk Sidebar
$q_ruangan_sidebar = mysqli_query($koneksi, "
    SELECT r.id_ruangan, r.nama_ruangan, COUNT(i.id_inventaris) AS total_barang 
    FROM ruangan r 
    LEFT JOIN inventaris i ON r.id_ruangan = i.ruangan_id 
    GROUP BY r.id_ruangan, r.nama_ruangan
");

if ($id_ruangan > 0) {
    $q_ruangan_detail = mysqli_query($koneksi, "SELECT * FROM ruangan WHERE id_ruangan = '$id_ruangan' LIMIT 1");
    $ruangan = mysqli_fetch_assoc($q_ruangan_detail);
    if (!$ruangan) {
        header('Location: tambah-barang.php');
        exit;
    }
    $q_barang_katalog = mysqli_query($koneksi, "SELECT * FROM barang ORDER BY nama_barang ASC");
} else {
    $q_kategori = mysqli_query($koneksi, "SELECT * FROM kategori ORDER BY nama_kategori ASC");
}

// PROSES FORM SUBMIT
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($id_ruangan > 0) {
        $id_barang       = isset($_POST['id_barang']) ? (int) $_POST['id_barang'] : 0;
        $jumlah          = isset($_POST['jumlah']) ? (int) $_POST['jumlah'] : 0;
        $tahun_perolehan = isset($_POST['tahun_perolehan']) ? (int) $_POST['tahun_perolehan'] : $current_year;
        $keterangan      = mysqli_real_escape_string($koneksi, $_POST['keterangan'] ?? '');

        if ($id_barang === 0 || $jumlah <= 0) {
            $pesan_error = "Harap pilih jenis barang dan masukkan jumlah unit yang valid.";
        } else {
            $q_barang = mysqli_query($koneksi, "SELECT * FROM barang WHERE id_barang = '$id_barang' LIMIT 1");
            $barang = mysqli_fetch_assoc($q_barang);

            if (!$barang) {
                $pesan_error = "Barang tidak ditemukan. Silakan pilih barang yang valid.";
            } elseif (empty(trim($barang['kode_barang']))) {
                $pesan_error = "Barang yang dipilih belum memiliki kode master. Silakan lengkapi data barang terlebih dahulu.";
            } else {
                $kode_master = trim($barang['kode_barang']);
                $ruangan_code = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($ruangan['nama_ruangan']));
                if ($ruangan_code === '') {
                    $ruangan_code = $ruangan['id_ruangan'];
                }

                $like_pattern = $kode_master . '-' . $ruangan_code . '-%';
                $q_last = mysqli_query($koneksi, "SELECT barcode FROM inventaris WHERE barang_id = '$id_barang' AND ruangan_id = '$id_ruangan' AND barcode LIKE '$like_pattern' ORDER BY id_inventaris DESC LIMIT 1");
                $next_seq = 1;

                if ($q_last && mysqli_num_rows($q_last) > 0) {
                    $d_last = mysqli_fetch_assoc($q_last);
                    $parts = explode('-', $d_last['barcode']);
                    $last_seq = (int) end($parts);
                    $next_seq = max(1, $last_seq + 1);
                }

                $kode_awal = $kode_master . '-' . $ruangan_code . '-' . str_pad($next_seq, 3, '0', STR_PAD_LEFT);
                $saved = 0;
                $errors = [];

                for ($i = 0; $i < $jumlah; $i++) {
                    $seq = $next_seq + $i;
                    $barcode = $kode_master . '-' . $ruangan_code . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
                    $query_inv = "INSERT INTO inventaris (barang_id, ruangan_id, tahun_perolehan, kondisi, keterangan, barcode) VALUES ('$id_barang', '$id_ruangan', '$tahun_perolehan', 'baik', '$keterangan', '$barcode')";

                    if (mysqli_query($koneksi, $query_inv)) {
                        $saved++;
                    } else {
                        $errors[] = mysqli_error($koneksi);
                        break;
                    }
                }

                if ($saved === $jumlah) {
                    $pesan_sukses = "Berhasil menambahkan $jumlah unit barang ke ruangan " . htmlspecialchars($ruangan['nama_ruangan']) . ". Kode mulai dari $kode_awal.";
                } else {
                    $pesan_error = "Gagal menyimpan semua unit. " . implode(' ', $errors);
                }
            }
        }
    } else {
        $nama_barang = trim(mysqli_real_escape_string($koneksi, $_POST['nama_barang'] ?? ''));
        $id_kategori = isset($_POST['id_kategori']) ? (int) $_POST['id_kategori'] : 0;
        $kode_barang = trim(mysqli_real_escape_string($koneksi, $_POST['kode_barang'] ?? ''));
        $keterangan  = mysqli_real_escape_string($koneksi, $_POST['keterangan'] ?? '');

        if ($nama_barang === '' || $id_kategori <= 0 || $kode_barang === '') {
            $pesan_error = "Silakan lengkapi Nama Barang, Kategori, dan Kode Barang.";
        } else {
            $q_kategori_check = mysqli_query($koneksi, "SELECT id_kategori FROM kategori WHERE id_kategori = '$id_kategori' LIMIT 1");
            if ($q_kategori_check && mysqli_num_rows($q_kategori_check) > 0) {
                $id_kategori = (int) mysqli_fetch_assoc($q_kategori_check)['id_kategori'];
            } else {
                $pesan_error = "Kategori tidak valid. Silakan pilih kategori yang tersedia.";
            }
        }

        if (empty($pesan_error)) {
            $cek_kode = mysqli_query($koneksi, "SELECT id_barang FROM barang WHERE kode_barang = '$kode_barang' LIMIT 1");
            if ($cek_kode && mysqli_num_rows($cek_kode) > 0) {
                $pesan_error = "Kode Barang sudah dipakai. Silakan gunakan kode lain.";
            } else {
                $query_barang = "INSERT INTO barang (nama_barang, kategori_id, deskripsi, kode_barang) VALUES ('$nama_barang', '$id_kategori', '$keterangan', '$kode_barang')";
                if (mysqli_query($koneksi, $query_barang)) {
                    $pesan_sukses = "Master barang berhasil ditambahkan.";
                    $nama_barang = '';
                    $id_kategori = 0;
                    $kode_barang = '';
                    $keterangan = '';
                } else {
                    $pesan_error = "Gagal menyimpan master barang: " . mysqli_error($koneksi);
                }
            }
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
                <h2>SIVENPRAS-TB</h2>
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

            <form method="POST" action="tambah-barang.php<?= $id_ruangan > 0 ? '?id_ruangan=' . $id_ruangan : '' ?>" class="widget-card" style="padding: 24px; max-width: 900px; background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <?php if ($id_ruangan > 0) : ?>
                    <input type="hidden" name="id_ruangan" value="<?= $id_ruangan; ?>">
                    <div style="margin-bottom: 24px;">
                        <h4 style="color: #0f172a; margin-bottom: 16px; font-size: 14px; letter-spacing: 0.5px; border-left: 3px solid #0d9488; padding-left: 8px;">
                            TAMBAH UNIT BARANG DI RUANGAN <?= htmlspecialchars($ruangan['nama_ruangan']); ?>
                        </h4>

                        <div style="display: grid; grid-template-columns: 1fr; gap: 16px; margin-bottom: 16px;">
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                                    NAMA BARANG <span style="color:red;">*</span>
                                </label>
                                <select name="id_barang" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                                    <option value="">-- Pilih Barang --</option>
                                    <?php while ($b = mysqli_fetch_assoc($q_barang_katalog)) { ?>
                                        <option value="<?= $b['id_barang']; ?>"><?= htmlspecialchars($b['nama_barang']); ?> (<?= htmlspecialchars($b['kode_barang']); ?>)</option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                                    JUMLAH UNIT <span style="color:red;">*</span>
                                </label>
                                <input type="number" name="jumlah" value="1" min="1" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                            </div>
                        </div>
                    </div>

                    <div style="margin-bottom: 24px;">
                        <h4 style="color: #0f172a; margin-bottom: 16px; font-size: 14px; letter-spacing: 0.5px; border-left: 3px solid #0d9488; padding-left: 8px;">
                            DATA PEROLEHAN
                        </h4>

                        <div style="display: grid; grid-template-columns: 1fr; gap: 16px; margin-bottom: 16px;">
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                                    TAHUN PEROLEHAN
                                </label>
                                <input type="number" name="tahun_perolehan" value="<?= $current_year; ?>" min="2000" max="2099" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                            </div>
                        </div>

                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">KETERANGAN</label>
                            <textarea name="keterangan" rows="3" placeholder="Catatan tambahan tentang unit ini (opsional)" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
                        </div>
                    </div>
                <?php else : ?>
                    <div style="margin-bottom: 24px;">
                        <h4 style="color: #0f172a; margin-bottom: 16px; font-size: 14px; letter-spacing: 0.5px; border-left: 3px solid #0d9488; padding-left: 8px;">
                            TAMBAH MASTER BARANG
                        </h4>

                        <div style="display: grid; grid-template-columns: 1fr; gap: 16px; margin-bottom: 16px;">
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                                    NAMA BARANG <span style="color:red;">*</span>
                                </label>
                                <input type="text" name="nama_barang" value="<?= htmlspecialchars($nama_barang ?? ''); ?>" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                                    KODE BARANG <span style="color:red;">*</span>
                                </label>
                                <input type="text" name="kode_barang" value="<?= htmlspecialchars($kode_barang ?? ''); ?>" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">
                                    KATEGORI <span style="color:red;">*</span>
                                </label>
                                <select name="id_kategori" required style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                                    <option value="">-- Pilih Kategori --</option>
                                    <?php while ($k = mysqli_fetch_assoc($q_kategori)) { ?>
                                        <option value="<?= $k['id_kategori']; ?>" <?= isset($id_kategori) && $id_kategori == $k['id_kategori'] ? 'selected' : ''; ?>><?= htmlspecialchars($k['nama_kategori']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 700; color: #475569; margin-bottom: 6px;">KETERANGAN</label>
                            <textarea name="keterangan" rows="3" placeholder="Catatan tambahan tentang barang ini (opsional)" style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; resize: vertical;"><?= htmlspecialchars($keterangan ?? ''); ?></textarea>
                        </div>
                    </div>
                <?php endif; ?>

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