<?php
session_start();
include 'config/koneksi.php';

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

$user_role = strtolower($_SESSION['role'] ?? '');

// --- LOGIKA AJAX KELOLA GAMBAR (UPLOAD, EDIT, HAPUS) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_gambar'])) {
    // Keamanan tambahan: Batasi eksekusi aksi ubah gambar jika role adalah kepala_sekolah
    if ($user_role === 'kepala_sekolah') {
        echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
        exit;
    }

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
            <?php if ($user_role !== 'kepala_sekolah'): ?>
                <input type="file" id="imageFileInput" accept="image/*" style="display: none;">
            <?php endif; ?>

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

<style>
    .modal-backdrop {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(4px);
    }

    .modal-backdrop.show {
        display: flex !important;
        animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes slideUp {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .modal-card {
        width: 420px;
        max-width: 90%;
        background-color: #ffffff;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        overflow: hidden;
        animation: slideUp 0.3s ease-out;
        border: 1px solid #f0f4f8;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
        background: linear-gradient(135deg, #164e5a 0%, #256b7a 100%);
        border-bottom: none;
    }

    .modal-title {
        font-weight: 700;
        font-size: 18px;
        color: #ffffff;
        letter-spacing: 0.5px;
    }

    .close-btn {
        cursor: pointer;
        font-size: 28px;
        font-weight: 300;
        color: #ffffff;
        line-height: 1;
        transition: transform 0.2s ease;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        background-color: rgba(255, 255, 255, 0.1);
    }

    .close-btn:hover {
        background-color: rgba(255, 255, 255, 0.2);
        transform: rotate(90deg);
    }

    .modal-body {
        padding: 24px;
    }

    .btn-add-photo {
        width: 70%;
        height: 140px;
        border: 2px dashed #cbd5e1;
        display: flex;
        justify-content: center;
        align-items: center;
        margin: 0 auto 16px;
        color: #64748b;
        font-size: 14px;
        background-color: #f8fafc;
        cursor: pointer;
        border-radius: 12px;
        transition: all 0.3s ease;
    }
    .btn-add-photo:hover {
        background-color: #f1f5f9;
        border-color: #94a3b8;
        color: #334155;
    }

    .photo-preview-wrapper {
        position: relative;
        width: 70%;
        height: 140px;
        border: none;
        border-radius: 12px;
        margin: 0 auto 16px;
        background-color: #000;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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
        background: rgba(0, 0, 0, 0.7);
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        opacity: 0;
        transition: opacity 0.3s ease-in-out;
    }

    .photo-preview-wrapper:hover .photo-overlay {
        opacity: 1;
    }

    .btn-overlay {
        padding: 6px 12px;
        border: none;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-overlay-edit {
        background: #3b82f6;
        color: #fff;
    }

    .btn-overlay-edit:hover {
        background: #2563eb;
        transform: scale(1.05);
    }

    .btn-overlay-delete {
        background: #ef4444;
        color: #fff;
    }

    .btn-overlay-delete:hover {
        background: #dc2626;
        transform: scale(1.05);
    }

    .info-row {
        display: flex;
        margin-bottom: 12px;
        font-size: 14px;
        color: #334155;
        padding: 10px 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .info-row:last-of-type {
        border-bottom: none;
    }

    .info-label {
        width: 110px;
        font-weight: 600;
        color: #475569;
    }

    .info-separator {
        margin-right: 12px;
        color: #cbd5e1;
    }

    .info-value {
        font-weight: 500;
        color: #0f172a;
    }

    .modal-table-scroll {
        max-height: 200px;
        overflow-y: auto;
        margin-top: 16px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }

    .detail-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        text-align: left;
        color: #475569;
    }

    .detail-table th {
        font-weight: 700;
        padding: 12px;
        border-bottom: 2px solid #e2e8f0;
        background-color: #f8fafc;
        color: #334155;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .detail-table td {
        padding: 10px 12px;
        border-bottom: 1px solid #f1f5f9;
    }

    .detail-table tbody tr:hover {
        background-color: #f8fafc;
    }

    /* --- OTHER STYLES --- */
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
                    <?php if ($user_role !== 'kepala_sekolah'): ?>
                    <div class="photo-overlay">
                        <button class="btn-overlay btn-overlay-edit" onclick="triggerFileInput()">Edit</button>
                        <button class="btn-overlay btn-overlay-delete" onclick="handleDeleteGambar()">Hapus</button>
                    </div>
                    <?php endif; ?>
                </div>
            `;
        } else {
            <?php if ($user_role !== 'kepala_sekolah'): ?>
                container.innerHTML = `
                    <button class="btn-add-photo" onclick="triggerFileInput()">
                        <span>➕ Tambah Gambar</span>
                    </button>
                `;
            <?php else: ?>
                container.innerHTML = `
                    <div style="width: 70%; height: 140px; margin: 0 auto 16px; display: flex; align-items: center; justify-content: center; background-color: #f8fafc; border-radius: 12px; border: 1px dashed #cbd5e1; color: #94a3b8; font-size: 14px; font-weight: 500; box-sizing: border-box;">
                        📷 Belum ada gambar
                    </div>
                `;
            <?php endif; ?>
        }
    }

    function triggerFileInput() {
        const input = document.getElementById('imageFileInput');
        if (input) input.click();
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

        // Render Photo Area
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

        const imageFileInput = document.getElementById('imageFileInput');
        if (imageFileInput) {
            imageFileInput.addEventListener('change', function () {
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
        }

        const modal = document.getElementById('simpleModal');
        window.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });
    });
</script>

<?php include 'includes/footer.php'; ?>