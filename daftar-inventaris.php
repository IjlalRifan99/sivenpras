<?php
session_start();
include 'config/koneksi.php';

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
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

// Query Detail Unit Inventaris per Barang
    $detail_query = "
    SELECT 
        b.id_barang,
        b.nama_barang,
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
                    <th>ID</th>
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
                            <td style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($row['id_barang']); ?></td>
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
                                <button type="button" class="btn-view-units" data-barang-id="<?= $row['id_barang']; ?>"
                                    data-barang-nama="<?= htmlspecialchars($row['nama_barang'], ENT_QUOTES, 'UTF-8'); ?>">
                                    Detail
                                </button>
                            </td>
                            <td style="color: #475569;"><?= htmlspecialchars($row['lokasi'] ?? '-'); ?></td>
                        </tr>
                        <?php
                    }
                } else {
                    echo "<tr><td colspan='7' style='text-align: center; padding: 30px; color: #94a3b8;'>Tidak ada data barang.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
</main>

<div id="simpleModal" class="modal-backdrop">
    <div class="modal-content-panel">

        <div class="modal-header-custom">
            <div>
                <h3 id="modalTitle" class="modal-title-custom">Detail Unit Barang</h3>
                <strong id="modalNamaBarang" style="display: block; font-size: 16px; color: #0f172a; margin-top: 2px;"></strong>
                <span id="modalSubtitle" class="modal-subtitle-custom"></span>
            </div>
            <button type="button" onclick="closeModal()" class="btn-close-icon">&times;</button>
        </div>

        <div class="modal-filter-row">
            <label for="modalKondisiFilter">Filter Kondisi</label>
            <select id="modalKondisiFilter" class="filter-select">
                <option value="">Semua Kondisi</option>
                <option value="baik">Baik</option>
                <option value="cukup baik">Cukup Baik</option>
                <option value="rusak">Rusak</option>
                <option value="rusak parah">Rusak Parah</option>
                <option value="hilang">Hilang</option>
            </select>
        </div>

        <div class="modal-body-scroll">
            <table class="modal-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">NO</th>
                        <th>CODE BARANG</th>
                        <th>NAMA BARANG</th>
                        <th>RUANGAN</th>
                        <th>KONDISI</th>
                        <th>KETERANGAN</th>
                    </tr>
                </thead>
                <tbody id="modalBody">
                </tbody>
            </table>
        </div>

        <div class="modal-footer-custom">
            <button type="button" onclick="closeModal()" class="btn-close-modal">Tutup</button>
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
    /* --- ACTION BAR --- */
    .action-bar {
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .btn-export-excel {
        padding: 12px 20px;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 6px rgba(59, 130, 246, 0.25);
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-export-excel:hover {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        box-shadow: 0 6px 12px rgba(59, 130, 246, 0.35);
        transform: translateY(-2px);
    }

    .btn-export-excel:active {
        transform: translateY(0);
    }

    /* --- CHECKBOX LIST --- */
    .checkbox-list {
        display: flex;
        flex-direction: column;
        gap: 4px;
        padding: 0 20px;
    }

    .checkbox-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        border-radius: 8px;
        transition: all 0.2s ease;
        border: 1px solid transparent;
    }

    .checkbox-item:hover {
        background: #f0f9ff;
        border-color: #3b82f6;
    }

    .checkbox-item input[type="checkbox"] {
        cursor: pointer;
        width: 18px;
        height: 18px;
        accent-color: #3b82f6;
    }

    .checkbox-item label {
        cursor: pointer;
        font-size: 14px;
        color: #334155;
        flex: 1;
        font-weight: 500;
    }

    /* --- FITUR FILTER BAR --- */
    .filter-bar {
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .filter-input-wrapper {
        flex: 1;
        min-width: 250px;
    }

    .filter-input {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 14px;
        outline: none;
        transition: border-color 0.2s;
    }

    .filter-input:focus {
        border-color: #0d9488;
    }

    .filter-select {
        padding: 10px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 14px;
        background-color: #fff;
        outline: none;
        cursor: pointer;
    }

    .btn-reset {
        padding: 10px 14px;
        background: #e2e8f0;
        color: #334155;
        text-decoration: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
    }

    /* --- TABEL DATA --- */
    .table-wrapper {
        padding: 0;
        overflow-x: auto;
    }

    .custom-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-size: 14px;
    }

    .custom-table thead tr {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        color: #64748b;
    }

    .custom-table th,
    .custom-table td {
        padding: 14px 18px;
    }

    .custom-table tbody tr {
        border-bottom: 1px solid #f1f5f9;
    }

    .btn-view-units {
        margin-left: 8px;
        padding: 5px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background: #fff;
        font-size: 12px;
        cursor: pointer;
        font-weight: 500;
        color: #0f172a;
        transition: all 0.2s;
    }

    .btn-view-units:hover {
        background: #f1f5f9;
        border-color: #94a3b8;
    }

    .dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 5px;
    }

    .dot.green {
        background-color: #22c55e;
    }

    .dot.yellow {
        background-color: #eab308;
    }

    .dot.red {
        background-color: #ef4444;
    }

    /* --- MODAL POPUP --- */
    .modal-backdrop {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.5);
        backdrop-filter: blur(2px);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }

    /* Tampil saat Javascript menambahkan class 'show' */
    .modal-backdrop.show {
        display: flex !important;
    }

    .modal-content-panel {
        background: #ffffff;
        width: 90%;
        max-width: 650px;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        position: relative;
        max-height: 85vh;
        display: flex;
        flex-direction: column;
    }

    .modal-header-custom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 12px;
        margin-bottom: 16px;
    }

    .modal-title-custom {
        margin: 0;
        font-size: 18px;
        color: #0f172a;
    }

    .modal-subtitle-custom {
        font-size: 13px;
        color: #64748b;
    }

    .btn-close-icon {
        background: transparent;
        border: none;
        font-size: 24px;
        color: #64748b;
        cursor: pointer;
        line-height: 1;
    }

    .modal-body-scroll {
        overflow-y: auto;
        flex: 1;
    }

    .modal-filter-row {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
    }

    .modal-filter-row label {
        font-size: 13px;
        color: #475569;
        font-weight: 600;
        min-width: 110px;
    }

    .modal-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        text-align: left;
    }

    .modal-table thead tr {
        background: #f1f5f9;
        color: #475569;
    }

    .modal-table th,
    .modal-table td {
        padding: 10px;
        border-bottom: 1px solid #e2e8f0;
    }

    .modal-footer-custom {
        margin-top: 20px;
        text-align: right;
        border-top: 1px solid #e2e8f0;
        padding-top: 16px;
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }

    .btn-close-modal {
        padding: 10px 18px;
        background: #e2e8f0;
        color: #334155;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.2s ease;
    }

    .btn-close-modal:hover {
        background: #cbd5e1;
        transform: translateY(-2px);
    }

    .modal-footer-custom button[type="submit"] {
        background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        color: #fff;
        box-shadow: 0 4px 6px rgba(34, 197, 94, 0.25);
    }

    .modal-footer-custom button[type="submit"]:hover {
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        box-shadow: 0 6px 12px rgba(34, 197, 94, 0.35);
    }
</style>

<script>
    const detailUnits = <?= json_encode($detail_units_by_barang, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    function escapeHtml(text) {
        return String(text ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function getFilterLabel(filter) {
        switch ((filter || '').toLowerCase().trim()) {
            case 'baik':
                return 'Total Baik';
            case 'cukup baik':
                return 'Total Cukup Baik';
            case 'rusak':
                return 'Total Rusak';
            case 'rusak parah':
                return 'Total Rusak Parah';
            case 'hilang':
                return 'Total Hilang';
            default:
                return 'Total';
        }
    }

    function renderModalRows(units, kondisiFilter, barangNama, barangId) {
        const body = document.getElementById('modalBody');
        const subtitle = document.getElementById('modalSubtitle');
        const filter = (kondisiFilter || '').toLowerCase().trim();
        const filtered = units.filter(function (unit) {
            if (!filter) return true;
            return String(unit.kondisi || '').toLowerCase().trim() === filter;
        });

        if (filtered.length > 0) {
            body.innerHTML = filtered.map(function (unit, index) {
                return `
                <tr>
                    <td style="color: #64748b;">${index + 1}</td>
                    <td style="font-weight: 600; color: #0f172a;">${escapeHtml(unit.barcode || '-')}</td>
                    <td style="color: #1e293b; font-weight: 500;">${escapeHtml(unit.nama_barang || barangNama || '-')}</td>
                    <td style="color: #334155;">${escapeHtml(unit.nama_ruangan || '-')}</td>
                    <td style="text-transform: capitalize;">${escapeHtml(unit.kondisi || '-')}</td>
                    <td style="color: #475569;">${escapeHtml(unit.keterangan || '-')}</td>
                </tr>
            `;
            }).join('');
        } else {
            body.innerHTML = `
            <tr>
                <td colspan="6" style="padding: 20px; text-align: center; color: #94a3b8;">
                    Tidak ada unit dengan kondisi terpilih.
                </td>
            </tr>
        `;
        }

        if (subtitle) {
            subtitle.textContent = `ID: ${barangId} | ${getFilterLabel(filter)}: ${filtered.length} unit`;
        }
    }

    function openModal(barangId, barangNama, barangKode) {
        const modal = document.getElementById('simpleModal');
        const title = document.getElementById('modalTitle');
        const namaBarangEl = document.getElementById('modalNamaBarang');
        const kondisiFilterEl = document.getElementById('modalKondisiFilter');
        const units = detailUnits[barangId] || [];

        // Simpan barangId dan barangKode pada modal untuk filter
        if (modal) {
            modal.dataset.barangId = barangId;
            modal.dataset.barangKode = barangKode;
        }

        // Set Judul Modal
        title.textContent = 'Detail Unit Barang';

        // Set Nama Barang di Header Modal
        if (namaBarangEl) {
            namaBarangEl.textContent = barangNama || '-';
        }

        if (kondisiFilterEl) {
            kondisiFilterEl.value = '';
        }

        renderModalRows(units, '', barangNama, barangKode);

        modal.classList.add('show');
    }

    function closeModal() {
        const modal = document.getElementById('simpleModal');
        if (modal) {
            modal.classList.remove('show');
            delete modal.dataset.barangId;
        }
    }

    function openExportModal() {
        const modal = document.getElementById('exportModal');
        if (modal) {
            modal.classList.add('show');
        }
    }

    function closeExportModal() {
        const modal = document.getElementById('exportModal');
        if (modal) {
            modal.classList.remove('show');
        }
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

        // Disable tombol dan ubah text saat proses
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
        const kondisiFilterEl = document.getElementById('modalKondisiFilter');

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const barangId = this.getAttribute('data-barang-id');
                const barangNama = this.getAttribute('data-barang-nama');

                openModal(barangId, barangNama);
            });
        });

        if (kondisiFilterEl) {
            kondisiFilterEl.addEventListener('change', function () {
                const modal = document.getElementById('simpleModal');
                if (!modal.classList.contains('show')) return;

                const barangId = modal.dataset.barangId;
                const barangNama = document.getElementById('modalNamaBarang').textContent;
                const units = detailUnits[barangId] || [];
                renderModalRows(units, this.value, barangNama, barangId);
            });
        }

        const modal = document.getElementById('simpleModal');
        window.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeModal();
            }
        });
    });
</script>

<?php include 'includes/footer.php'; ?>