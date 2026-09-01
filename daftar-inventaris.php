<?php
session_start();
include 'config/koneksi.php';

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

// --- LOGIKA AJAX KELOLA GAMBAR (UPLOAD, EDIT, HAPUS) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_gambar'])) {
    $id_barang = (int)$_POST['id_barang'];
    $action = $_POST['action_gambar'];

    if ($action === 'upload' || $action === 'edit') {
        if (isset($_FILES['gambar_file']) && $_FILES['gambar_file']['error'] === 0) {
            $fileName = $_FILES['gambar_file']['name'];
            $fileTmp  = $_FILES['gambar_file']['tmp_name'];
            $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowed  = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($ext, $allowed)) {
                // Hapus file lama dari server jika ada
                $q_old = mysqli_query($koneksi, "SELECT gambar FROM barang WHERE id_barang = '$id_barang'");
                $d_old = mysqli_fetch_assoc($q_old);
                if (!empty($d_old['gambar']) && file_exists('uploads/barang/' . $d_old['gambar'])) {
                    unlink('uploads/barang/' . $d_old['gambar']);
                }

                $newFileName = 'img_' . $id_barang . '_' . time() . '.' . $ext;
                $targetDir   = 'uploads/barang/';
                
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }

                if (move_uploaded_file($fileTmp, $targetDir . $newFileName)) {
                    mysqli_query($koneksi, "UPDATE barang SET gambar = '$newFileName' WHERE id_barang = '$id_barang'");
                    echo json_encode(['status' => 'success', 'gambar' => $newFileName]);
                    exit;
                }
            }
        }
        echo json_encode(['status' => 'error', 'message' => 'Gagal mengunggah gambar.']);
        exit;
    }

    if ($action === 'delete') {
        $q_old = mysqli_query($koneksi, "SELECT gambar FROM barang WHERE id_barang = '$id_barang'");
        $d_old = mysqli_fetch_assoc($q_old);
        if (!empty($d_old['gambar']) && file_exists('uploads/barang/' . $d_old['gambar'])) {
            unlink('uploads/barang/' . $d_old['gambar']);
        }
        mysqli_query($koneksi, "UPDATE barang SET gambar = NULL WHERE id_barang = '$id_barang'");
        echo json_encode(['status' => 'success']);
        exit;
    }
}

$active_page = 'inventaris';
$page_title = 'inventaris';
$breadcrumb = 'Inventaris';

// --- LOGIKA FILTER & PENCARIAN ---
$search = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, $_GET['search']) : '';
$kategori = isset($_GET['kategori']) ? mysqli_real_escape_string($koneksi, $_GET['kategori']) : '';
$kondisi = isset($_GET['kondisi']) ? mysqli_real_escape_string($koneksi, $_GET['kondisi']) : '';

$where_clauses = [];

if (!empty($search)) {
    $where_clauses[] = "(b.nama_barang LIKE '%$search%' OR r.nama_ruangan LIKE '%$search%')";
}

if (!empty($kategori)) {
    $where_clauses[] = "b.kategori_id = '$kategori'";
}

if (!empty($kondisi)) {
    $where_clauses[] = "i.kondisi = '$kondisi'";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(' AND ', $where_clauses);
}

// Query Utama Fetch Data Barang
$query = "
    SELECT 
        b.id_barang,
        b.nama_barang,
        b.deskripsi,
        b.gambar,
        k.nama_kategori,
        GROUP_CONCAT(DISTINCT r.nama_ruangan SEPARATOR ', ') AS lokasi,
        COUNT(i.id_inventaris) AS jumlah_unit,
        GROUP_CONCAT(DISTINCT CASE
            WHEN i.kondisi = 'baik' THEN 'Baik'
            WHEN i.kondisi = 'rusak' THEN 'Rusak'
            WHEN i.kondisi = 'hilang' THEN 'Hilang'
            ELSE i.kondisi END SEPARATOR ', ') AS daftar_kondisi
    FROM barang b
    LEFT JOIN kategori k ON b.kategori_id = k.id_kategori
    LEFT JOIN inventaris i ON b.id_barang = i.barang_id
    LEFT JOIN ruangan r ON i.ruangan_id = r.id_ruangan
    $where_sql
    GROUP BY b.id_barang
    ORDER BY b.id_barang DESC
";

$result_barang = mysqli_query($koneksi, $query);

// Dropdown Kategori
$q_filter_kategori = mysqli_query($koneksi, "SELECT * FROM kategori ORDER BY nama_kategori ASC");

// Query Semua Ruangan untuk Export Modal
$q_ruangan = mysqli_query($koneksi, "SELECT id_ruangan, nama_ruangan FROM ruangan ORDER BY nama_ruangan ASC");
$ruangan_list = [];
if ($q_ruangan) {
    while ($ruang = mysqli_fetch_assoc($q_ruangan)) {
        $ruangan_list[] = $ruang;
    }
}

// Query Detail Unit Inventaris per Barang (termasuk foto & kategori)
$detail_query = "
    SELECT 
        b.id_barang,
        b.nama_barang,
        b.gambar,
        k.nama_kategori,
        i.barcode,
        i.kondisi,
        i.keterangan,
        r.nama_ruangan
    FROM inventaris i
    JOIN barang b ON i.barang_id = b.id_barang
    LEFT JOIN kategori k ON b.kategori_id = k.id_kategori
    LEFT JOIN ruangan r ON i.ruangan_id = r.id_ruangan
    WHERE 1=1
";

if (!empty($search)) {
    $detail_query .= " AND (b.nama_barang LIKE '%$search%' OR r.nama_ruangan LIKE '%$search%')";
}
if (!empty($kategori)) {
    $detail_query .= " AND b.kategori_id = '$kategori'";
}
if (!empty($kondisi)) {
    $detail_query .= " AND i.kondisi = '$kondisi'";
}

$detail_query .= " ORDER BY b.id_barang, i.barcode ASC";

$q_detail_units = mysqli_query($koneksi, $detail_query);

$detail_units_by_barang = [];
if ($q_detail_units) {
    while ($unit = mysqli_fetch_assoc($q_detail_units)) {
        $detail_units_by_barang[$unit['id_barang']][] = $unit;
    }
}
?>

<link rel="stylesheet" href="assets/css/style-inventaris.css">

<?php include 'includes/header.php'; ?>

<div class="dashboard-container">
    <div class="page-header">
        <h1>Daftar Inventaris</h1>
        <p>Total <?= $result_barang ? mysqli_num_rows($result_barang) : 0; ?> jenis barang tercatat dalam sistem</p>
    </div>

    <div class="action-bar">
        <button type="button" class="btn-export-excel" onclick="openExportModal()">
            📊 Export Excel
        </button>
    </div>

    <form method="GET" action="daftar-inventaris.php" class="filter-bar">
        <div class="filter-input-wrapper">
            <input type="text" name="search" class="filter-input" placeholder="Cari nama, kode, atau lokasi..."
                value="<?= htmlspecialchars($search); ?>">
        </div>

        <select name="kategori" onchange="this.form.submit()" class="filter-select">
            <option value="">Semua Kategori</option>
            <?php
            if ($q_filter_kategori) {
                while ($kat = mysqli_fetch_assoc($q_filter_kategori)) {
                    ?>
                    <option value="<?= $kat['id_kategori']; ?>" <?= ($kategori == $kat['id_kategori']) ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($kat['nama_kategori']); ?>
                    </option>
                    <?php
                }
            }
            ?>
        </select>

        <?php if (!empty($search) || !empty($kategori) || !empty($kondisi)) { ?>
            <a href="daftar-inventaris.php" class="btn-reset">Reset</a>
        <?php } ?>
    </form>

    <div class="widget-card table-wrapper">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>NO</th>
                    <th>NAMA BARANG</th>
                    <th>KATEGORI</th>
                    <th>KONDISI</th>
                    <th>JUMLAH</th>
                    <th>LOKASI</th>
                </tr>
            </thead>

            <tbody>
                <?php
                if ($result_barang && mysqli_num_rows($result_barang) > 0) {
                    $no = 1;
                    while ($row = mysqli_fetch_assoc($result_barang)) {
                        $badge_class = 'green';
                        $kondisi_text = $row['daftar_kondisi'] ?? 'Baik';

                        if (strpos($kondisi_text, 'Hilang') !== false) {
                            $badge_class = 'red';
                        } elseif (strpos($kondisi_text, 'Rusak') !== false) {
                            $badge_class = 'yellow';
                        }
                        ?>
                        <tr>
                            <td style="font-weight: 500; color: #64748b;"><?= $no++; ?></td>
                            <td>
                                <strong style="display: block; color: #1e293b;"><?= htmlspecialchars($row['nama_barang']); ?></strong>
                                <small style="color: #94a3b8;"><?= htmlspecialchars($row['deskripsi']); ?></small>
                            </td>
                            <td style="color: #475569;"><?= htmlspecialchars($row['nama_kategori'] ?? '-'); ?></td>
                            <td>
                                <span class="dot <?= $badge_class; ?>"></span>
                                <?= htmlspecialchars($kondisi_text); ?>
                            </td>
                            <td style="font-weight: 600; color: #0f172a;">
                                <?= number_format($row['jumlah_unit']); ?>
                                <button type="button" class="btn-view-units" 
                                    data-barang-id="<?= $row['id_barang']; ?>"
                                    data-barang-nama="<?= htmlspecialchars($row['nama_barang'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-barang-kategori="<?= htmlspecialchars($row['nama_kategori'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-barang-gambar="<?= htmlspecialchars($row['gambar'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    Detail
                                </button>
                            </td>
                            <td style="color: #475569;"><?= htmlspecialchars($row['lokasi'] ?? '-'); ?></td>
                        </tr>
                        <?php
                    }
                } else {
                    echo "<tr><td colspan='6' style='text-align: center; padding: 30px; color: #94a3b8;'>Tidak ada data barang.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
</main>

<!-- MODAL DETAIL BARANG -->
<div id="simpleModal" class="modal-backdrop">
    <div class="modal-card">
        <!-- Header -->
        <div class="modal-header">
            <span class="modal-title">Detail Barang</span>
            <span class="close-btn" onclick="closeModal()">&times;</span>
        </div>

        <!-- Body -->
        <div class="modal-body">
            <!-- Dynamic Photo Container -->
            <div id="photoAreaContainer" style="margin-bottom: 20px;"></div>
            
            <!-- Hidden File Input for Dynamic Upload -->
            <input type="file" id="imageFileInput" accept="image/*" style="display: none;">

            <!-- Info Utama -->
            <div class="info-row">
                <span class="info-label">Nama Barang</span>
                <span class="info-separator">:</span>
                <span id="modalInfoNama" class="info-value">-</span>
            </div>
            <div class="info-row">
                <span class="info-label">Kategori</span>
                <span class="info-separator">:</span>
                <span id="modalInfoKategori" class="info-value">-</span>
            </div>

            <!-- Tabel Unit -->
            <div class="modal-table-scroll">
                <table class="detail-table">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Kondisi</th>
                            <th>Ruangan</th>
                        </tr>
                    </thead>
                    <tbody id="modalBody">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL EXPORT EXCEL -->
<div id="exportModal" class="modal-backdrop">
    <div class="modal-content-panel">
        <div class="modal-header-custom">
            <div>
                <h3 class="modal-title-custom">Export Excel Inventaris</h3>
                <span class="modal-subtitle-custom">Pilih ruangan yang ingin diekspor</span>
            </div>
            <button type="button" onclick="closeExportModal()" class="btn-close-icon">&times;</button>
        </div>

        <form id="exportForm" method="POST" action="export-inventaris.php" onsubmit="return validateRuanganSelection()">
            <div class="modal-body-scroll" style="padding: 20px 0;">
                <div class="checkbox-list">
                    <div class="checkbox-item">
                        <input type="checkbox" id="selectAll" onchange="toggleAllRuangan(this)">
                        <label for="selectAll" style="font-weight: 600;">Pilih Semua Ruangan</label>
                    </div>
                    <hr style="margin: 12px 0; border: none; border-top: 1px solid #e2e8f0;">
                    
                    <?php foreach ($ruangan_list as $ruang): ?>
                        <div class="checkbox-item">
                            <input type="checkbox" name="ruangan[]" value="<?= $ruang['id_ruangan']; ?>" 
                                   class="ruangan-checkbox">
                            <label><?= htmlspecialchars($ruang['nama_ruangan']); ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="modal-footer-custom">
                <button type="button" onclick="closeExportModal()" class="btn-close-modal" style="background: #94a3b8;">Batal</button>
                <button type="submit" class="btn-close-modal" style="background: #22c55e; margin-left: 8px;">Export</button>
            </div>
        </form>
    </div>
</div>

<style>
    /* --- STYLE MODAL SESUAI WIREFRAME --- */
    .modal-backdrop {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.4);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }

    .modal-backdrop.show {
        display: flex !important;
    }

    .modal-card {
        width: 380px;
        max-width: 90%;
        background-color: #f8f8f8;
        border: 1px solid #333;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        border-bottom: 2px solid #333;
        background-color: #f8f8f8;
    }

    .modal-title {
        font-weight: bold;
        font-size: 16px;
        color: #000;
    }

    .close-btn {
        cursor: pointer;
        font-size: 18px;
        font-weight: bold;
        color: #000;
        line-height: 1;
    }

    .modal-body {
        padding: 20px 16px;
    }

    /* CSS Foto, Tambah Gambar, Hover, Edit & Hapus */
    .btn-add-photo {
        width: 60%;
        height: 120px;
        border: 1px dashed #666;
        display: flex;
        justify-content: center;
        align-items: center;
        margin: 0 auto;
        color: #333;
        font-size: 14px;
        background-color: #fff;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .btn-add-photo:hover {
        background-color: #f0f0f0;
    }

    .photo-preview-wrapper {
        position: relative;
        width: 60%;
        height: 120px;
        border: 1px dashed #666;
        margin: 0 auto;
        background-color: #000;
        overflow: hidden;
    }

    .photo-preview-wrapper img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .photo-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.65);
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        opacity: 0;
        transition: opacity 0.2s ease-in-out;
    }

    .photo-preview-wrapper:hover .photo-overlay {
        opacity: 1;
    }

    .btn-overlay {
        padding: 4px 10px;
        border: none;
        border-radius: 4px;
        font-size: 12px;
        font-weight: bold;
        cursor: pointer;
    }

    .btn-overlay-edit {
        background: #3b82f6;
        color: #fff;
    }

    .btn-overlay-delete {
        background: #ef4444;
        color: #fff;
    }

    .info-row {
        display: flex;
        margin-bottom: 8px;
        font-size: 14px;
        color: #000;
    }

    .info-label {
        width: 110px;
    }

    .info-separator {
        margin-right: 8px;
    }

    .info-value {
        font-weight: 500;
    }

    .modal-table-scroll {
        max-height: 180px;
        overflow-y: auto;
        margin-top: 16px;
    }

    .detail-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
        text-align: left;
        color: #000;
    }

    .detail-table th {
        font-weight: bold;
        padding-bottom: 8px;
        border-bottom: 1px solid #ccc;
    }

    .detail-table td {
        padding: 6px 0;
    }

    /* --- OTHER STYLES --- */
    .action-bar { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
    .btn-export-excel { padding: 12px 20px; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: #fff; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
    .checkbox-list { display: flex; flex-direction: column; gap: 4px; padding: 0 20px; }
    .checkbox-item { display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-radius: 8px; }
    .filter-bar { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
    .filter-input-wrapper { flex: 1; min-width: 250px; }
    .filter-input { width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; }
    .filter-select { padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; background-color: #fff; }
    .btn-reset { padding: 10px 14px; background: #e2e8f0; color: #334155; text-decoration: none; border-radius: 8px; font-size: 14px; font-weight: 500; }
    .table-wrapper { padding: 0; overflow-x: auto; }
    .custom-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
    .custom-table thead tr { background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b; }
    .custom-table th, .custom-table td { padding: 14px 18px; }
    .custom-table tbody tr { border-bottom: 1px solid #f1f5f9; }
    .btn-view-units { margin-left: 8px; padding: 5px 12px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; font-size: 12px; cursor: pointer; color: #0f172a; }
    .dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 5px; }
    .dot.green { background-color: #22c55e; }
    .dot.yellow { background-color: #eab308; }
    .dot.red { background-color: #ef4444; }

    .modal-content-panel { background: #ffffff; width: 90%; max-width: 650px; border-radius: 12px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); position: relative; max-height: 85vh; display: flex; flex-direction: column; }
    .modal-header-custom { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 16px; }
    .modal-title-custom { margin: 0; font-size: 18px; color: #0f172a; }
    .modal-subtitle-custom { font-size: 13px; color: #64748b; }
    .btn-close-icon { background: transparent; border: none; font-size: 24px; color: #64748b; cursor: pointer; }
    .modal-body-scroll { overflow-y: auto; flex: 1; }
    .modal-footer-custom { margin-top: 20px; text-align: right; border-top: 1px solid #e2e8f0; padding-top: 16px; display: flex; gap: 10px; justify-content: flex-end; }
    .btn-close-modal { padding: 10px 18px; background: #e2e8f0; color: #334155; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; }
</style>

<script>
    const detailUnits = <?= json_encode($detail_units_by_barang, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    let currentBarangId = null;
    let currentGambar = '';

    function escapeHtml(text) {
        return String(text ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function capitalizeFirstLetter(string) {
        if (!string) return '-';
        return string.charAt(0).toUpperCase() + string.slice(1);
    }

    function renderPhotoArea() {
        const container = document.getElementById('photoAreaContainer');
        if (currentGambar && currentGambar.trim() !== '') {
            container.innerHTML = `
                <div class="photo-preview-wrapper">
                    <img src="uploads/barang/${escapeHtml(currentGambar)}" alt="Foto Barang">
                    <div class="photo-overlay">
                        <button class="btn-overlay btn-overlay-edit" onclick="triggerFileInput()">Edit</button>
                        <button class="btn-overlay btn-overlay-delete" onclick="handleDeleteGambar()">Hapus</button>
                    </div>
                </div>
            `;
        } else {
            container.innerHTML = `
                <button class="btn-add-photo" onclick="triggerFileInput()">
                    <span>➕ Tambah Gambar</span>
                </button>
            `;
        }
    }

    function triggerFileInput() {
        document.getElementById('imageFileInput').click();
    }

    function handleDeleteGambar() {
        if (confirm('Apakah Anda yakin ingin menghapus gambar ini?')) {
            const formData = new FormData();
            formData.append('id_barang', currentBarangId);
            formData.append('action_gambar', 'delete');

            fetch('daftar-inventaris.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    currentGambar = '';
                    renderPhotoArea();
                    updateButtonDataAttribute(currentBarangId, '');
                } else {
                    alert('Gagal menghapus gambar.');
                }
            })
            .catch(() => alert('Terjadi kesalahan koneksi.'));
        }
    }

    function updateButtonDataAttribute(barangId, newGambar) {
        const btn = document.querySelector(`.btn-view-units[data-barang-id="${barangId}"]`);
        if (btn) {
            btn.setAttribute('data-barang-gambar', newGambar);
        }
    }

    function openModal(barangId, barangNama, barangKategori, barangGambar) {
        const modal = document.getElementById('simpleModal');
        const infoNama = document.getElementById('modalInfoNama');
        const infoKategori = document.getElementById('modalInfoKategori');
        const modalBody = document.getElementById('modalBody');

        currentBarangId = barangId;
        currentGambar = barangGambar || '';

        // Render Photo Area (Tambah Gambar vs Hover Overlay)
        renderPhotoArea();

        // Render Meta Info
        infoNama.textContent = barangNama || '-';
        infoKategori.textContent = barangKategori || '-';

        // Render Tabel Unit
        const units = detailUnits[barangId] || [];
        if (units.length > 0) {
            modalBody.innerHTML = units.map(function (unit) {
                return `
                    <tr>
                        <td>${escapeHtml(unit.barcode || '-')}</td>
                        <td>${escapeHtml(capitalizeFirstLetter(unit.kondisi))}</td>
                        <td>${escapeHtml(unit.nama_ruangan || '-')}</td>
                    </tr>
                `;
            }).join('');
        } else {
            modalBody.innerHTML = `
                <tr>
                    <td colspan="3" style="text-align: center; padding: 10px; color: #888;">
                        Belum ada unit tercatat.
                    </td>
                </tr>
            `;
        }

        if (modal) modal.classList.add('show');
    }

    function closeModal() {
        const modal = document.getElementById('simpleModal');
        if (modal) modal.classList.remove('show');
    }

    function openExportModal() {
        const modal = document.getElementById('exportModal');
        if (modal) modal.classList.add('show');
    }

    function closeExportModal() {
        const modal = document.getElementById('exportModal');
        if (modal) modal.classList.remove('show');
    }

    function toggleAllRuangan(checkbox) {
        const checkboxes = document.querySelectorAll('.ruangan-checkbox');
        checkboxes.forEach(function (cb) {
            cb.checked = checkbox.checked;
        });
    }

    function validateRuanganSelection() {
        const checkboxes = document.querySelectorAll('.ruangan-checkbox');
        const isAnyChecked = Array.from(checkboxes).some(function (cb) {
            return cb.checked;
        });

        if (!isAnyChecked) {
            alert('Silakan pilih minimal satu ruangan untuk diekspor');
            return false;
        }

        const submitBtn = document.querySelector('#exportForm button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Sedang mengekspor...';
            submitBtn.style.opacity = '0.6';
        }

        return true;
    }

    document.addEventListener('DOMContentLoaded', function () {
        const buttons = document.querySelectorAll('.btn-view-units');

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const barangId = this.getAttribute('data-barang-id');
                const barangNama = this.getAttribute('data-barang-nama');
                const barangKategori = this.getAttribute('data-barang-kategori');
                const barangGambar = this.getAttribute('data-barang-gambar');

                openModal(barangId, barangNama, barangKategori, barangGambar);
            });
        });

        // Event listener Upload / Edit Gambar via input file
        document.getElementById('imageFileInput').addEventListener('change', function () {
            if (this.files && this.files[0]) {
                const formData = new FormData();
                formData.append('id_barang', currentBarangId);
                formData.append('action_gambar', currentGambar ? 'edit' : 'upload');
                formData.append('gambar_file', this.files[0]);

                fetch('daftar-inventaris.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        currentGambar = data.gambar;
                        renderPhotoArea();
                        updateButtonDataAttribute(currentBarangId, data.gambar);
                    } else {
                        alert(data.message || 'Gagal mengunggah gambar.');
                    }
                })
                .catch(() => alert('Terjadi kesalahan koneksi.'));
                
                this.value = '';
            }
        });

        const modal = document.getElementById('simpleModal');
        const exportModal = document.getElementById('exportModal');

        window.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
            if (e.target === exportModal) closeExportModal();
        });
    });
</script>

<?php include 'includes/footer.php'; ?>