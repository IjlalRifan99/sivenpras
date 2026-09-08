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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($id_ruangan > 0) {
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
                $kode_barang_singkat = buatKodeBarang($d_barang['nama_barang']);
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

            if (mysqli_query($koneksi, $query_barang)) {
                $pesan_sukses = "Master barang berhasil ditambahkan.";
                $nama_barang = '';
                $id_kategori = 0;
                $keterangan = '';
            } else {
                if (!empty($gambar) && file_exists('uploads/barang/' . $gambar)) {
                    unlink('uploads/barang/' . $gambar);
                }
                $pesan_error = "Gagal menyimpan master barang: " . mysqli_error($koneksi);
            }
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

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

    <form method="POST" action="tambah-barang.php<?= $id_ruangan > 0 ? '?id_ruangan=' . $id_ruangan : '' ?>"
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
</script>

</main>

<?php include 'includes/footer.php'; ?>