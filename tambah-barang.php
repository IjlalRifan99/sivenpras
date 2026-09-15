<?php
session_start();
include 'config/koneksi.php';

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

function buatKodeBarang($nama_barang)
{
    $words = explode(' ', strtoupper(trim($nama_barang)));
    $code = '';
    if (count($words) >= 2) {
        for ($i = 0; $i < 2; $i++) {
            $kata = $words[$i];
            $konsonan = preg_replace('/[AIUEO]/', '', $kata);
            if (empty($konsonan))
                $konsonan = $kata;
            $code .= substr($konsonan, 0, 2);
        }
    } else {
        $konsonan = preg_replace('/[AIUEO]/', '', $words[0]);
        if (empty($konsonan))
            $konsonan = $words[0];
        $code = substr($konsonan, 0, 4);
    }
    $code = preg_replace('/[^A-Z0-9]/', '', $code);
    return !empty($code) ? $code : 'BRG';
}

$active_page = 'tambah';
$breadcrumb = 'Tambah barang';
$pesan_sukses = "";
$pesan_error = "";
$id_ruangan = isset($_GET['id_ruangan']) ? (int) $_GET['id_ruangan'] : (isset($_POST['id_ruangan']) ? (int) $_POST['id_ruangan'] : 0);
$current_year = date('Y');

$bhp_barcode_ready = false;
$q_bhp_barcode_column = mysqli_query($koneksi, "SHOW COLUMNS FROM bhp LIKE 'barcode'");
if ($q_bhp_barcode_column && mysqli_num_rows($q_bhp_barcode_column) > 0) {
    $bhp_barcode_ready = true;
} else {
    $bhp_barcode_ready = (bool) mysqli_query($koneksi, "ALTER TABLE bhp ADD COLUMN barcode VARCHAR(255) NULL UNIQUE");
}

$bhp_stok_ready = false;
$q_bhp_stok_column = mysqli_query($koneksi, "SHOW COLUMNS FROM bhp LIKE 'stok'");
if ($q_bhp_stok_column && mysqli_num_rows($q_bhp_stok_column) > 0) {
    $bhp_stok_ready = true;
} else {
    $bhp_stok_ready = (bool) mysqli_query($koneksi, "ALTER TABLE bhp ADD COLUMN stok INT NOT NULL DEFAULT 0");
}

$bhp_ruangan_ready = false;
$q_bhp_ruangan_column = mysqli_query($koneksi, "SHOW COLUMNS FROM inventaris_bhp LIKE 'ruangan_id'");
if ($q_bhp_ruangan_column && mysqli_num_rows($q_bhp_ruangan_column) > 0) {
    $bhp_ruangan_ready = true;
} else {
    $bhp_ruangan_ready = (bool) mysqli_query($koneksi, "ALTER TABLE inventaris_bhp ADD COLUMN ruangan_id INT NULL AFTER bhp_id");
}

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
    $q_bhp_katalog = mysqli_query($koneksi, "SELECT h.id_bhp, h.nama_barang, h.stok, k.nama_kategori FROM bhp h LEFT JOIN kategori_bhp k ON h.kategori_id = k.id_kategori ORDER BY h.nama_barang ASC");
} else {
    $q_kategori = mysqli_query($koneksi, "SELECT * FROM kategori ORDER BY nama_kategori ASC");
    $q_kategori_bhp = mysqli_query($koneksi, "SELECT * FROM kategori_bhp ORDER BY nama_kategori ASC");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($id_ruangan > 0 && ($_POST['jenis_tambah'] ?? '') === 'bhp') {
        $id_bhp = (int) ($_POST['id_bhp'] ?? 0);
        $jumlah_bhp = (int) ($_POST['jumlah_bhp'] ?? 0);
        $satuan_bhp = strtoupper(trim($_POST['satuan_bhp'] ?? ''));
        $keterangan_bhp_ruangan = mysqli_real_escape_string($koneksi, trim($_POST['keterangan_bhp_ruangan'] ?? ''));
        $satuan_map_bhp = ['PCS' => 'PCS', 'BOX' => 'Box', 'PACK' => 'Pack', 'LUSIN' => 'Lusin'];

        if ($id_bhp <= 0 || $jumlah_bhp <= 0 || !isset($satuan_map_bhp[$satuan_bhp])) {
            $pesan_error = 'Pilih barang BHP, jumlah, dan satuan yang valid.';
        } elseif (!$bhp_stok_ready || !$bhp_ruangan_ready) {
            $pesan_error = 'Struktur data BHP belum siap. Jalankan migrasi database terlebih dahulu.';
        } else {
            mysqli_begin_transaction($koneksi);
            $q_bhp = mysqli_query($koneksi, "SELECT stok FROM bhp WHERE id_bhp = '$id_bhp' LIMIT 1 FOR UPDATE");
            $d_bhp = $q_bhp ? mysqli_fetch_assoc($q_bhp) : null;
            $stok_tersedia = (int) ($d_bhp['stok'] ?? 0);

            if (!$d_bhp) {
                $pesan_error = 'Barang BHP tidak ditemukan.';
            } elseif ($jumlah_bhp > $stok_tersedia) {
                $pesan_error = "Stok tidak cukup. Stok tersedia: $stok_tersedia.";
            } else {
                $satuan_bhp_db = mysqli_real_escape_string($koneksi, $satuan_map_bhp[$satuan_bhp]);
                $insert_bhp_room = mysqli_query($koneksi, "INSERT INTO inventaris_bhp (bhp_id, ruangan_id, jumlah, satuan, keterangan, barcode) VALUES ('$id_bhp', '$id_ruangan', '$jumlah_bhp', '$satuan_bhp_db', '$keterangan_bhp_ruangan', '')");
                $update_stok = mysqli_query($koneksi, "UPDATE bhp SET stok = stok - '$jumlah_bhp' WHERE id_bhp = '$id_bhp' AND stok >= '$jumlah_bhp'");
                if ($insert_bhp_room && $update_stok && mysqli_affected_rows($koneksi) > 0) {
                    mysqli_commit($koneksi);
                    header("Location: ruangan.php?id=" . $id_ruangan);
                    exit;
                }
                $pesan_error = 'Gagal menyimpan distribusi BHP.';
            }
            mysqli_rollback($koneksi);
        }
    } elseif ($id_ruangan > 0) {
        $id_barang = isset($_POST['id_barang']) ? (int) $_POST['id_barang'] : 0;
        $jumlah = isset($_POST['jumlah']) ? (int) $_POST['jumlah'] : 0;
        $tahun_perolehan = isset($_POST['tahun_perolehan']) ? (int) $_POST['tahun_perolehan'] : $current_year;
        $keterangan = mysqli_real_escape_string($koneksi, $_POST['keterangan'] ?? '');

        if ($id_barang === 0 || $jumlah <= 0) {
            $pesan_error = "Harap pilih jenis barang dan masukkan jumlah unit yang valid.";
        } else {
            $q_barang = mysqli_query($koneksi, "SELECT * FROM barang WHERE id_barang = '$id_barang' LIMIT 1");
            if (!$q_barang || mysqli_num_rows($q_barang) == 0) {
                $pesan_error = "Barang tidak ditemukan. Silakan pilih barang yang valid.";
            } else {
                $d_barang = mysqli_fetch_assoc($q_barang);
                $barcode_produk = trim($d_barang['barcode'] ?? '');
                $kode_barang_singkat = $barcode_produk !== ''
                    ? preg_replace('/[^A-Za-z0-9]/', '', $barcode_produk)
                    : buatKodeBarang($d_barang['nama_barang']);
                $ruangan_code = isset($ruangan['nama_ruangan']) ? preg_replace('/[^0-9]/', '', $ruangan['nama_ruangan']) : '';
                if ($ruangan_code === '')
                    $ruangan_code = (string) $id_ruangan;
                $like_pattern = $kode_barang_singkat . '-' . $ruangan_code . '-%';
                $q_last = mysqli_query($koneksi, "SELECT barcode FROM inventaris WHERE barang_id = '$id_barang' AND ruangan_id = '$id_ruangan' AND barcode LIKE '$like_pattern' ORDER BY id_inventaris DESC LIMIT 1");
                $next_seq = 1;

                if ($q_last && mysqli_num_rows($q_last) > 0) {
                    $d_last = mysqli_fetch_assoc($q_last);
                    $parts = explode('-', $d_last['barcode']);
                    $last_seq = (int) end($parts);
                    $next_seq = max(1, $last_seq + 1);
                }

                $saved = 0;
                $errors = [];

                for ($i = 0; $i < $jumlah; $i++) {
                    $seq = $next_seq + $i;
                    $barcode = $kode_barang_singkat . '-' . $ruangan_code . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
                    $query_inv = "INSERT INTO inventaris (barang_id, ruangan_id, tahun_perolehan, kondisi, keterangan, barcode) VALUES ('$id_barang', '$id_ruangan', '$tahun_perolehan', 'baik', '$keterangan', '$barcode')";

                    if (mysqli_query($koneksi, $query_inv)) {
                        $saved++;
                    } else {
                        $errors[] = mysqli_error($koneksi);
                        break;
                    }
                }

                if ($saved === $jumlah) {
                    header("Location: ruangan.php?id=" . $id_ruangan);
                    exit;
                } else {
                    $pesan_error = "Gagal menyimpan semua unit. " . implode(' ', $errors);
                }
            }
        }
    } elseif (($_POST['jenis_master'] ?? '') === 'bhp') {
        $nama_bhp = trim(mysqli_real_escape_string($koneksi, $_POST['nama_bhp'] ?? ''));
        $id_kategori_bhp = (int) ($_POST['id_kategori_bhp'] ?? 0);
        $barcode_bhp = trim(mysqli_real_escape_string($koneksi, $_POST['barcode_bhp'] ?? ''));

        if ($nama_bhp === '' || $id_kategori_bhp <= 0 || $barcode_bhp === '') {
            $pesan_error = 'Barcode, nama barang, dan kategori BHP wajib diisi.';
        } else {
            $q_kategori_bhp_check = mysqli_query($koneksi, "SELECT id_kategori FROM kategori_bhp WHERE id_kategori = '$id_kategori_bhp' LIMIT 1");
            if (!$q_kategori_bhp_check || mysqli_num_rows($q_kategori_bhp_check) === 0) {
                $pesan_error = 'Kategori BHP tidak valid.';
            } elseif (!$bhp_barcode_ready) {
                $pesan_error = 'Kolom barcode BHP belum tersedia.';
            } else {
                $barcode_bhp_db = mysqli_real_escape_string($koneksi, $barcode_bhp);
                $query_bhp = "INSERT INTO bhp (nama_barang, kategori_id, deskripsi, barcode, stok) VALUES ('$nama_bhp', '$id_kategori_bhp', '', '$barcode_bhp_db', 0)";
                if (mysqli_query($koneksi, $query_bhp)) {
                    $pesan_sukses = 'Master BHP berhasil ditambahkan.';
                    $nama_bhp = '';
                    $id_kategori_bhp = 0;
                    $barcode_bhp = '';
                } else {
                    $pesan_error = 'Gagal menyimpan master BHP: ' . mysqli_error($koneksi);
                }
            }
        }
    } else {
        $nama_barang = trim(mysqli_real_escape_string($koneksi, $_POST['nama_barang'] ?? ''));
        $id_kategori = isset($_POST['id_kategori']) ? (int) $_POST['id_kategori'] : 0;
        $keterangan = mysqli_real_escape_string($koneksi, $_POST['keterangan'] ?? '');
        $gambar = '';

        if ($nama_barang === '' || $id_kategori <= 0) {
            $pesan_error = "Silakan lengkapi Nama Barang dan Kategori.";
        } else {
            $q_kategori_check = mysqli_query($koneksi, "SELECT id_kategori FROM kategori WHERE id_kategori = '$id_kategori' LIMIT 1");
            if ($q_kategori_check && mysqli_num_rows($q_kategori_check) > 0) {
                $id_kategori = (int) mysqli_fetch_assoc($q_kategori_check)['id_kategori'];
            } else {
                $pesan_error = "Kategori tidak valid. Silakan pilih kategori yang tersedia.";
            }
        }

        if (empty($pesan_error) && isset($_FILES['gambar']) && $_FILES['gambar']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['gambar']['error'] !== UPLOAD_ERR_OK) {
                $pesan_error = "Gagal mengupload foto barang.";
            } elseif ($_FILES['gambar']['size'] > 2 * 1024 * 1024) {
                $pesan_error = "Ukuran foto maksimal 2 MB.";
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $_FILES['gambar']['tmp_name']);
                finfo_close($finfo);

                $allowed = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp'
                ];

                if (!isset($allowed[$mime])) {
                    $pesan_error = "Format foto harus JPG, PNG, atau WEBP.";
                } else {
                    $folder = 'uploads/barang/';
                    if (!is_dir($folder)) {
                        mkdir($folder, 0777, true);
                    }

                    $nama_file = 'barang_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
                    $tujuan = $folder . $nama_file;

                    if (move_uploaded_file($_FILES['gambar']['tmp_name'], $tujuan)) {
                        $gambar = $nama_file;
                    } else {
                        $pesan_error = "Foto barang gagal disimpan.";
                    }
                }
            }
        }

        if (empty($pesan_error)) {
            $gambar_db = mysqli_real_escape_string($koneksi, $gambar);
            $query_barang = "INSERT INTO barang (nama_barang, kategori_id, deskripsi, gambar) VALUES ('$nama_barang', '$id_kategori', '$keterangan', '$gambar_db')";

            if (empty($pesan_error) && mysqli_query($koneksi, $query_barang)) {
                $pesan_sukses = "Master barang berhasil ditambahkan.";
                $nama_barang = '';
                $id_kategori = 0;
                $keterangan = '';
            } else {
                if (!empty($gambar) && file_exists('uploads/barang/' . $gambar)) {
                    unlink('uploads/barang/' . $gambar);
                }
                if (empty($pesan_error)) {
                    $pesan_error = "Gagal menyimpan master barang: " . mysqli_error($koneksi);
                }
            }
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<?php if ($id_ruangan === 0): ?>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<?php endif; ?>

<div class="dashboard-container">
    <?php if (!empty($pesan_sukses)) { ?>
        <div
            style="background:#dcfce7;color:#15803d;padding:14px 18px;border-radius:8px;margin-bottom:20px;font-weight:500;border-left:4px solid #22c55e;">
            <?= htmlspecialchars($pesan_sukses); ?>
        </div>
    <?php } ?>

    <?php if (!empty($pesan_error)) { ?>
        <div
            style="background:#fee2e2;color:#b91c1c;padding:14px 18px;border-radius:8px;margin-bottom:20px;font-weight:500;border-left:4px solid #ef4444;">
            <?= htmlspecialchars($pesan_error); ?>
        </div>
    <?php } ?>

    <?php if ($id_ruangan > 0): ?>
        <form method="POST" action="tambah-barang.php?id_ruangan=<?= $id_ruangan; ?>" class="widget-card bhp-room-form">
            <input type="hidden" name="jenis_tambah" value="bhp">
            <h4 class="master-panel-title">TAMBAH BHP KE RUANGAN <?= htmlspecialchars($ruangan['nama_ruangan']); ?></h4>
            <div class="master-form-grid">
                <div>
                    <label>Barang BHP <span>*</span></label>
                    <select name="id_bhp" required>
                        <option value="">-- Pilih Barang BHP --</option>
                        <?php while ($hb = mysqli_fetch_assoc($q_bhp_katalog)) { ?>
                            <option value="<?= $hb['id_bhp']; ?>"><?= htmlspecialchars($hb['nama_barang']); ?> (stok <?= (int) $hb['stok']; ?>)</option>
                        <?php } ?>
                    </select>
                </div>
                <div>
                    <label>Jumlah <span>*</span></label>
                    <input type="number" name="jumlah_bhp" value="1" min="1" required>
                </div>
                <div>
                    <label>Satuan <span>*</span></label>
                    <select name="satuan_bhp" required>
                        <option value="">-- Pilih satuan --</option>
                        <option value="PCS">PCS</option>
                        <option value="BOX">Box</option>
                        <option value="PACK">Pack</option>
                        <option value="LUSIN">Lusin</option>
                    </select>
                </div>
                <div>
                    <label>Keterangan</label>
                    <input type="text" name="keterangan_bhp_ruangan" placeholder="Opsional">
                </div>
            </div>
            <div class="master-form-actions"><button type="submit" class="btn-primary">Simpan ke Ruangan</button></div>
        </form>
    <?php endif; ?>

    <?php if ($id_ruangan === 0): ?>
        <div class="master-type-switcher">
            <button type="button" class="master-type-option active" data-target="assetMasterPanel">
                <i class="bi bi-box-seam"></i><span><strong>Aset Tetap</strong><small>Masuk ke tabel barang dan menggunakan QR Code.</small></span>
            </button>
            <button type="button" class="master-type-option" data-target="bhpMasterPanel">
                <i class="bi bi-upc-scan"></i><span><strong>Barang Habis Pakai</strong><small>Masuk ke tabel BHP dan menggunakan barcode.</small></span>
            </button>
        </div>
        <div id="bhpMasterPanel" class="bhp-master-panel">
            <div class="bhp-panel-heading">
                <h4>Tambah Master Barang Habis Pakai</h4>
                <p>Scan barcode untuk mendaftarkan BHP baru.</p>
            </div>
        <div class="barcode-entry-banner">
            <div>
                <strong>Daftarkan barang dari barcode</strong>
                <span>Scan kode pada barang untuk membuka form data barang baru.</span>
            </div>
            <button type="button" class="btn-primary barcode-scan-trigger" id="openBarcodeScanner">
                <i class="bi bi-upc-scan"></i> Scan Barcode
            </button>
        </div>

        <div id="barcodeScannerModal" class="barcode-modal" aria-hidden="true">
            <div class="barcode-modal-card" role="dialog" aria-modal="true" aria-labelledby="barcodeModalTitle">
                <div class="barcode-modal-header">
                    <div>
                        <h2 id="barcodeModalTitle">Scan Barcode Barang</h2>
                        <p>Arahkan kamera ke barcode produk.</p>
                    </div>
                    <button type="button" class="barcode-modal-close" id="closeBarcodeScanner" aria-label="Tutup">&times;</button>
                </div>

                <div id="barcodeScanStep">
                    <div id="addBarcodeReader" class="barcode-reader"></div>
                    <div class="barcode-manual-row">
                        <input type="text" id="manualBarcode" placeholder="Atau ketik kode barcode" autocomplete="off">
                        <button type="button" class="btn-primary" id="useManualBarcode">Gunakan Kode</button>
                    </div>
                    <p id="barcodeScanStatus" class="barcode-scan-status">Memuat kamera...</p>
                </div>

                <div id="barcodeFormStep" style="display:none;">
                    <form method="POST" action="tambah-barang.php" enctype="multipart/form-data" id="scannedBarangForm">
                        <input type="hidden" name="jenis_master" value="bhp">
                        <input type="hidden" name="barcode_bhp" id="scannedBarcodeValue">
                        <div class="scanned-code-display">
                            <span>Barcode terbaca</span>
                            <strong id="scannedBarcodeText"></strong>
                        </div>
                        <div class="barcode-form-field">
                            <label for="scannedNamaBarang">Nama Barang BHP <span>*</span></label>
                            <input type="text" name="nama_bhp" id="scannedNamaBarang" required autofocus>
                        </div>
                        <div class="barcode-form-field">
                            <label for="scannedKategori">Kategori BHP <span>*</span></label>
                            <select name="id_kategori_bhp" id="scannedKategori" required>
                                <option value="">-- Pilih Kategori BHP --</option>
                                <?php $q_kategori_modal = mysqli_query($koneksi, "SELECT * FROM kategori_bhp ORDER BY nama_kategori ASC"); ?>
                                <?php while ($kategori_modal = mysqli_fetch_assoc($q_kategori_modal)) { ?>
                                    <option value="<?= $kategori_modal['id_kategori']; ?>"><?= htmlspecialchars($kategori_modal['nama_kategori']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="barcode-modal-actions">
                            <button type="button" class="barcode-secondary-btn" id="rescanBarcode">Scan Ulang</button>
                            <button type="submit" class="btn-primary">Simpan Barang</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        </div>
    <?php endif; ?>

    <form method="POST" action="tambah-barang.php<?= $id_ruangan > 0 ? '?id_ruangan=' . $id_ruangan : '' ?>"
        id="assetMasterPanel"
        class="widget-card" enctype="multipart/form-data"
        style="padding:24px;margin:0 auto;max-width:500px;background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,0.1);">
        <?php if ($id_ruangan > 0): ?>
            <input type="hidden" name="id_ruangan" value="<?= $id_ruangan; ?>">

            <div style="margin-bottom:24px;">
                <h4
                    style="color:#0f172a;margin-bottom:16px;font-size:14px;letter-spacing:.5px;border-left:3px solid #0d9488;padding-left:8px;">
                    TAMBAH UNIT BARANG DI RUANGAN <?= htmlspecialchars($ruangan['nama_ruangan']); ?>
                </h4>

                <div style="display:grid;grid-template-columns:1fr;gap:16px;margin-bottom:16px;">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:6px;">
                            NAMA BARANG <span style="color:red;">*</span></label>
                        <select name="id_barang" required
                            style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;">
                            <option value="">-- Pilih Barang --</option>
                            <?php while ($b = mysqli_fetch_assoc($q_barang_katalog)) { ?>
                                <option value="<?= $b['id_barang']; ?>"><?= htmlspecialchars($b['nama_barang']); ?></option>
                            <?php } ?>
                        </select>
                    </div>

                    <div>
                        <label style="display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:6px;">
                            JUMLAH UNIT <span style="color:red;">*</span></label>
                        <input type="number" name="jumlah" value="1" min="1" required
                            style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;">
                    </div>
                </div>
            </div>

            <div style="margin-bottom:24px;">
                <h4
                    style="color:#0f172a;margin-bottom:16px;font-size:14px;letter-spacing:.5px;border-left:3px solid #0d9488;padding-left:8px;">
                    DATA PEROLEHAN</h4>

                <div style="display:grid;grid-template-columns:1fr;gap:16px;margin-bottom:16px;">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:6px;">TAHUN
                            PEROLEHAN</label>
                        <input type="number" name="tahun_perolehan" value="<?= $current_year; ?>" min="2000" max="2099"
                            style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;">
                    </div>
                </div>
            </div>

        <?php else: ?>

            <div style="margin-bottom:24px;">
                <h4
                    style="color:#0f172a;margin-bottom:16px;font-size:14px;letter-spacing:.5px;border-left:3px solid #0d9488;padding-left:8px;">
                    TAMBAH MASTER BARANG</h4>

                <div style="gap:16px;margin-bottom:16px;">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:6px;">NAMA
                            BARANG <span style="color:red;">*</span></label>
                        <input type="text" name="nama_barang" value="<?= htmlspecialchars($nama_barang ?? ''); ?>" required
                            style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;">
                    </div>

                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:6px;">KATEGORI
                        <span style="color:red;">*</span></label>
                    <select name="id_kategori" required
                        style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;">
                        <option value="">-- Pilih Kategori --</option>
                        <?php while ($k = mysqli_fetch_assoc($q_kategori)) { ?>
                            <option value="<?= $k['id_kategori']; ?>" <?= isset($id_kategori) && $id_kategori == $k['id_kategori'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($k['nama_kategori']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div>
                    <label style="display:block;font-size:12px;font-weight:700;color:#475569;margin-bottom:6px;">FOTO BARANG
                        <span style="font-weight:400;color:#94a3b8;">(Opsional)</span></label>
                    <input type="file" name="gambar" id="gambar" accept="image/jpeg,image/png,image/webp"
                        style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;background:#fff;">
                    <small style="display:block;margin-top:6px;color:#94a3b8;">Format JPG, PNG, atau WEBP. Maksimal 2
                        MB.</small>
                    <div id="preview-container" style="display:none;margin-top:12px;">
                        <img id="preview-gambar" src="" alt="Preview"
                            style="width:120px;height:120px;object-fit:cover;border-radius:10px;border:1px solid #e2e8f0;">
                    </div>
                </div>
            </div>

        <?php endif; ?>

        <div style="display:flex;gap:12px;justify-content:flex-end;border-top:1px solid #f1f5f9;padding-top:16px;">
            <?php if ($id_ruangan > 0): ?>
                <a href="ruangan.php?id=<?= $id_ruangan; ?>"
                    style="padding:10px 20px;border:1px solid #cbd5e1;border-radius:8px;text-decoration:none;color:#475569;font-weight:600;font-size:14px;">Batal</a>
            <?php endif; ?>

            <button type="submit" class="btn-primary" style="padding:10px 24px;font-size:14px;cursor:pointer;">Simpan
                Barang</button>
        </div>
    </form>
</div>

<?php if ($id_ruangan === 0): ?>
    <style>
    .master-type-switcher { display:grid; grid-template-columns:1fr 1fr; gap:14px; max-width:850px; margin:0 auto 20px; }
    .master-type-option { display:flex; align-items:center; gap:12px; padding:16px; border:1px solid #cbd5e1; border-radius:12px; background:#fff; color:#475569; text-align:left; cursor:pointer; }
    .master-type-option i { color:#0f766e; font-size:24px; }
    .master-type-option span { display:flex; flex-direction:column; gap:4px; }
    .master-type-option strong { color:#0f172a; font-size:14px; }
    .master-type-option small { color:#64748b; font-size:12px; }
    .master-type-option.active { border-color:#0f766e; background:#f0fdfa; box-shadow:0 0 0 2px rgba(15,118,110,.12); }
    .bhp-master-panel { display:none; max-width:850px; margin:0 auto 20px; }
    .bhp-panel-heading { margin-bottom:14px; padding:0 4px; }
    .bhp-panel-heading h4 { margin:0; color:#0f172a; font-size:18px; }
    .bhp-panel-heading p { margin:5px 0 0; color:#64748b; font-size:13px; }
        .barcode-entry-banner { display:flex; align-items:center; justify-content:space-between; gap:16px; margin:0 auto 20px; max-width:500px; padding:16px 18px; border:1px solid #99f6e4; border-radius:12px; background:#f0fdfa; color:#134e4a; }
        .barcode-entry-banner span { display:block; margin-top:4px; color:#52706d; font-size:13px; }
        .barcode-scan-trigger { flex-shrink:0; white-space:nowrap; }
        .barcode-modal { display:none; position:fixed; inset:0; z-index:100000; align-items:center; justify-content:center; padding:18px; background:rgba(15,23,42,.68); }
        .barcode-modal.show { display:flex; }
        .barcode-modal-card { width:min(100%, 520px); max-height:calc(100vh - 36px); overflow:auto; border-radius:14px; background:#fff; box-shadow:0 24px 60px rgba(15,23,42,.28); }
        .barcode-modal-header { display:flex; justify-content:space-between; gap:16px; padding:20px 22px 14px; border-bottom:1px solid #e2e8f0; }
        .barcode-modal-header h2 { margin:0; color:#0f172a; font-size:20px; }
        .barcode-modal-header p { margin:5px 0 0; color:#64748b; font-size:13px; }
        .barcode-modal-close { border:0; background:transparent; color:#64748b; cursor:pointer; font-size:28px; line-height:1; }
        .barcode-reader { min-height:260px; margin:18px 22px 12px; overflow:hidden; border-radius:10px; background:#0f172a; }
        .barcode-reader video { width:100% !important; height:260px !important; object-fit:cover; }
        .barcode-reader img, #addBarcodeReader__scan_region img { display:none !important; }
        .barcode-manual-row { display:flex; gap:8px; padding:0 22px; }
        .barcode-manual-row input, .barcode-form-field input, .barcode-form-field select { box-sizing:border-box; width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; }
        .barcode-manual-row input { flex:1; }
        .barcode-scan-status { min-height:20px; margin:10px 22px 18px; color:#64748b; font-size:13px; }
        #barcodeFormStep { padding:20px 22px 22px; }
        .scanned-code-display { margin-bottom:18px; padding:12px 14px; border:1px solid #99f6e4; border-radius:8px; background:#f0fdfa; }
        .scanned-code-display span { display:block; color:#52706d; font-size:12px; }
        .scanned-code-display strong { display:block; margin-top:4px; color:#115e59; font-size:16px; letter-spacing:.3px; word-break:break-all; }
        .barcode-form-field { margin-bottom:15px; }
        .barcode-form-field label { display:block; margin-bottom:6px; color:#475569; font-size:12px; font-weight:700; }
        .barcode-form-field label span { color:#dc2626; }
        .barcode-form-field small { display:block; margin-top:5px; color:#94a3b8; font-size:12px; }
        .barcode-modal-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:20px; }
        .barcode-secondary-btn { padding:10px 18px; border:1px solid #cbd5e1; border-radius:8px; background:#fff; color:#475569; cursor:pointer; font-weight:600; }
        @media (max-width:600px) { .master-type-switcher { grid-template-columns:1fr; } .barcode-entry-banner { align-items:stretch; flex-direction:column; } .barcode-scan-trigger { width:100%; justify-content:center; } .barcode-manual-row { flex-direction:column; } .barcode-modal-actions { flex-direction:column-reverse; } .barcode-modal-actions button { width:100%; justify-content:center; } }
    </style>
<?php endif; ?>

<?php if ($id_ruangan > 0): ?>
    <style>
        .bhp-room-form { display:block; max-width:500px; margin:0 auto 20px; padding:24px; }
        .master-panel-title { margin:0 0 18px; color:#0f172a; font-size:14px; letter-spacing:.4px; border-left:3px solid #0d9488; padding-left:8px; }
        .master-form-grid { display:grid; grid-template-columns:1fr; gap:16px; }
        .master-form-grid label { display:block; margin-bottom:6px; color:#475569; font-size:12px; font-weight:700; }
        .master-form-grid label span { color:#dc2626; }
        .master-form-grid input, .master-form-grid select { box-sizing:border-box; width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; }
        .master-form-actions { display:flex; justify-content:flex-end; margin-top:20px; }
    </style>
<?php endif; ?>

<script>
    const inputGambar = document.getElementById('gambar');
    const previewContainer = document.getElementById('preview-container');
    const previewGambar = document.getElementById('preview-gambar');

    if (inputGambar) {
        inputGambar.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                previewGambar.src = URL.createObjectURL(file);
                previewContainer.style.display = 'block';
            } else {
                previewGambar.src = '';
                previewContainer.style.display = 'none';
            }
        });
    }

    <?php if ($id_ruangan === 0): ?>
        document.querySelectorAll('.master-type-option').forEach(function (button) {
            button.addEventListener('click', function () {
                document.querySelectorAll('.master-type-option').forEach(function (item) { item.classList.remove('active'); });
                this.classList.add('active');
                document.getElementById('assetMasterPanel').style.display = this.dataset.target === 'assetMasterPanel' ? 'block' : 'none';
                document.getElementById('bhpMasterPanel').style.display = this.dataset.target === 'bhpMasterPanel' ? 'block' : 'none';
            });
        });
    <?php endif; ?>

    <?php if ($id_ruangan === 0): ?>
        let addBarcodeScanner = null;
        let scannerRunning = false;
        const scannerModal = document.getElementById('barcodeScannerModal');
        const scanStep = document.getElementById('barcodeScanStep');
        const formStep = document.getElementById('barcodeFormStep');
        const scanStatus = document.getElementById('barcodeScanStatus');

        async function stopAddBarcodeScanner() {
            if (addBarcodeScanner && scannerRunning) {
                await addBarcodeScanner.stop();
                scannerRunning = false;
            }
        }

        async function showScannedBarcode(code) {
            const cleanCode = String(code || '').trim();
            if (!cleanCode) return;
            await stopAddBarcodeScanner();
            document.getElementById('scannedBarcodeValue').value = cleanCode;
            document.getElementById('scannedBarcodeText').textContent = cleanCode;
            scanStep.style.display = 'none';
            formStep.style.display = 'block';
            document.getElementById('scannedNamaBarang').focus();
        }

        async function startAddBarcodeScanner() {
            if (!window.isSecureContext) {
                scanStatus.textContent = 'Kamera membutuhkan HTTPS atau localhost. Gunakan input manual di bawah.';
                return;
            }
            try {
                if (!addBarcodeScanner) addBarcodeScanner = new Html5Qrcode('addBarcodeReader');
                await addBarcodeScanner.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 280, height: 140 } },
                    showScannedBarcode,
                    function () {}
                );
                scannerRunning = true;
                scanStatus.textContent = 'Kamera aktif. Arahkan ke barcode barang.';
            } catch (error) {
                scanStatus.textContent = 'Kamera tidak dapat digunakan. Masukkan kode barcode secara manual.';
            }
        }

        function openBarcodeModal() {
            scannerModal.classList.add('show');
            scannerModal.setAttribute('aria-hidden', 'false');
            scanStep.style.display = 'block';
            formStep.style.display = 'none';
            scanStatus.textContent = 'Memuat kamera...';
            startAddBarcodeScanner();
        }

        async function closeBarcodeModal() {
            await stopAddBarcodeScanner();
            scannerModal.classList.remove('show');
            scannerModal.setAttribute('aria-hidden', 'true');
        }

        document.getElementById('openBarcodeScanner').addEventListener('click', openBarcodeModal);
        document.getElementById('closeBarcodeScanner').addEventListener('click', closeBarcodeModal);
        document.getElementById('rescanBarcode').addEventListener('click', function () {
            scanStep.style.display = 'block';
            formStep.style.display = 'none';
            scanStatus.textContent = 'Memuat kamera...';
            startAddBarcodeScanner();
        });
        document.getElementById('useManualBarcode').addEventListener('click', function () {
            showScannedBarcode(document.getElementById('manualBarcode').value);
        });
        document.getElementById('manualBarcode').addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                showScannedBarcode(this.value);
            }
        });
        scannerModal.addEventListener('click', function (event) {
            if (event.target === scannerModal) closeBarcodeModal();
        });
    <?php endif; ?>
</script>

</main>

<?php include 'includes/footer.php'; ?>