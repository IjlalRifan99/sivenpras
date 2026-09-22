<?php
session_start();
include 'config/koneksi.php';

if (!isset($_SESSION['login'])) {
    header('Location: login.php');
    exit;
}

$active_page = 'bhp';
$page_title = 'Barang Habis Pakai';
$breadcrumb = 'Daftar Inventaris > Barang Habis Pakai';
$search = mysqli_real_escape_string($koneksi, trim($_GET['search'] ?? ''));
$message = '';
$message_type = 'success';
$user_role = strtolower($_SESSION['role'] ?? '');

$stok_column = mysqli_query($koneksi, "SHOW COLUMNS FROM bhp LIKE 'stok'");
if (!$stok_column || mysqli_num_rows($stok_column) === 0) {
    mysqli_query($koneksi, "ALTER TABLE bhp ADD COLUMN stok INT NOT NULL DEFAULT 0");
}
$ruangan_column = mysqli_query($koneksi, "SHOW COLUMNS FROM inventaris_bhp LIKE 'ruangan_id'");
if (!$ruangan_column || mysqli_num_rows($ruangan_column) === 0) {
    mysqli_query($koneksi, "ALTER TABLE inventaris_bhp ADD COLUMN ruangan_id INT NULL AFTER bhp_id");
}
$tanggal_bhp_column = mysqli_query($koneksi, "SHOW COLUMNS FROM inventaris_bhp LIKE 'tanggal_masuk'");
if (!$tanggal_bhp_column || mysqli_num_rows($tanggal_bhp_column) === 0) {
    mysqli_query($koneksi, "ALTER TABLE inventaris_bhp ADD COLUMN tanggal_masuk DATE NULL AFTER jumlah");
    mysqli_query($koneksi, "UPDATE inventaris_bhp SET tanggal_masuk = CURRENT_DATE WHERE tanggal_masuk IS NULL");
}
$satuan_column = mysqli_query($koneksi, "SHOW COLUMNS FROM bhp LIKE 'satuan'");
if (!$satuan_column || mysqli_num_rows($satuan_column) === 0) {
    mysqli_query($koneksi, "ALTER TABLE bhp ADD COLUMN satuan VARCHAR(20) NULL AFTER stok");
    mysqli_query($koneksi, "UPDATE bhp h INNER JOIN (SELECT bhp_id, MAX(satuan) AS satuan FROM inventaris_bhp WHERE satuan IS NOT NULL AND satuan <> '' GROUP BY bhp_id) ib ON ib.bhp_id = h.id_bhp SET h.satuan = ib.satuan WHERE h.satuan IS NULL OR h.satuan = ''");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_bhp') {
    $id_bhp = (int) ($_POST['id_bhp'] ?? 0);
    $nama_bhp = trim(mysqli_real_escape_string($koneksi, $_POST['nama_barang'] ?? ''));
    $kategori_bhp = (int) ($_POST['kategori_id'] ?? 0);
    $barcode_bhp = trim(mysqli_real_escape_string($koneksi, $_POST['barcode'] ?? ''));
    $satuan_bhp = strtoupper(trim($_POST['satuan'] ?? ''));
    $satuan_map = ['PCS' => 'PCS', 'BOX' => 'Box', 'PACK' => 'Pack', 'LUSIN' => 'Lusin'];

    if ($user_role === 'kepala_sekolah') {
        $message = 'Akses ditolak.';
        $message_type = 'error';
    } elseif ($id_bhp <= 0 || $nama_bhp === '' || $kategori_bhp <= 0 || $barcode_bhp === '' || !isset($satuan_map[$satuan_bhp])) {
        $message = 'Data BHP belum lengkap atau satuan tidak valid.';
        $message_type = 'error';
    } else {
        $satuan_db = mysqli_real_escape_string($koneksi, $satuan_map[$satuan_bhp]);
        $q_existing_bhp = mysqli_query($koneksi, "SELECT satuan FROM bhp WHERE id_bhp = '$id_bhp' LIMIT 1");
        $existing_bhp = $q_existing_bhp ? mysqli_fetch_assoc($q_existing_bhp) : null;
        $q_existing_records = mysqli_query($koneksi, "SELECT id_inventaris_bhp FROM inventaris_bhp WHERE bhp_id = '$id_bhp' LIMIT 1");
        if (!$existing_bhp) {
            $message = 'BHP tidak ditemukan.';
            $message_type = 'error';
        } elseif (strcasecmp(trim($existing_bhp['satuan'] ?? ''), $satuan_db) !== 0 && $q_existing_records && mysqli_num_rows($q_existing_records) > 0) {
            $message = 'Satuan tidak dapat diubah karena BHP sudah memiliki catatan stok atau distribusi.';
            $message_type = 'error';
        } else {
        $duplicate = mysqli_query($koneksi, "SELECT id_bhp FROM bhp WHERE (nama_barang = '$nama_bhp' OR barcode = '$barcode_bhp') AND id_bhp != '$id_bhp' LIMIT 1");
        if ($duplicate && mysqli_num_rows($duplicate) > 0) {
            $message = 'Nama atau barcode BHP sudah digunakan.';
            $message_type = 'error';
        } else {
            $updated = mysqli_query($koneksi, "UPDATE bhp SET nama_barang = '$nama_bhp', kategori_id = '$kategori_bhp', barcode = '$barcode_bhp', satuan = '$satuan_db' WHERE id_bhp = '$id_bhp'");
            $message = $updated ? 'Data BHP berhasil diperbarui.' : 'Data BHP gagal diperbarui.';
            $message_type = $updated ? 'success' : 'error';
        }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_bhp') {
    $id_bhp = (int) ($_POST['id_bhp'] ?? 0);
    if ($user_role === 'kepala_sekolah') {
        $message = 'Akses ditolak.';
        $message_type = 'error';
    } elseif ($id_bhp <= 0) {
        $message = 'BHP yang akan dihapus tidak valid.';
        $message_type = 'error';
    } else {
        mysqli_begin_transaction($koneksi);
        $delete_records = mysqli_query($koneksi, "DELETE FROM inventaris_bhp WHERE bhp_id = '$id_bhp'");
        $delete_master = $delete_records ? mysqli_query($koneksi, "DELETE FROM bhp WHERE id_bhp = '$id_bhp'") : false;
        if ($delete_records && $delete_master) {
            mysqli_commit($koneksi);
            $message = 'BHP dan seluruh catatan stok/distribusinya berhasil dihapus.';
        } else {
            mysqli_rollback($koneksi);
            $message = 'BHP gagal dihapus.';
            $message_type = 'error';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_stock') {
    $id_bhp_stock = (int) ($_POST['id_bhp'] ?? 0);
    $jumlah_stock = (int) ($_POST['jumlah'] ?? 0);
    $tanggal_masuk = trim($_POST['tanggal_masuk'] ?? date('Y-m-d'));
    $tanggal_valid = DateTime::createFromFormat('Y-m-d', $tanggal_masuk);

    if ($id_bhp_stock <= 0 || $jumlah_stock <= 0 || !$tanggal_valid || $tanggal_valid->format('Y-m-d') !== $tanggal_masuk) {
        $message = 'Pilih barang, jumlah, dan tanggal masuk yang valid.';
        $message_type = 'error';
    } else {
        $q_bhp_stock = mysqli_query($koneksi, "SELECT satuan FROM bhp WHERE id_bhp = '$id_bhp_stock' LIMIT 1");
        $bhp_stock = $q_bhp_stock ? mysqli_fetch_assoc($q_bhp_stock) : null;
        $satuan_db = mysqli_real_escape_string($koneksi, trim($bhp_stock['satuan'] ?? ''));
        if (!$bhp_stock || $satuan_db === '') {
            $message = 'Satuan BHP belum ditentukan pada master barang.';
            $message_type = 'error';
        } else {
        mysqli_begin_transaction($koneksi);
        $tanggal_db = mysqli_real_escape_string($koneksi, $tanggal_masuk);
        $insert_stock = mysqli_query($koneksi, "INSERT INTO inventaris_bhp (bhp_id, ruangan_id, jumlah, tanggal_masuk, satuan, keterangan, barcode) VALUES ('$id_bhp_stock', NULL, '$jumlah_stock', '$tanggal_db', '$satuan_db', '', '')");
        $update_stock = mysqli_query($koneksi, "UPDATE bhp SET stok = stok + '$jumlah_stock' WHERE id_bhp = '$id_bhp_stock'");
        if ($insert_stock && $update_stock && mysqli_affected_rows($koneksi) > 0) {
            mysqli_commit($koneksi);
            $message = 'Stok BHP berhasil ditambahkan.';
        } else {
            mysqli_rollback($koneksi);
            $message = 'Stok BHP gagal ditambahkan.';
            $message_type = 'error';
        }
        }
    }
}

$where = $search !== '' ? "WHERE h.nama_barang LIKE '%$search%' OR k.nama_kategori LIKE '%$search%'" : '';
$query = "
        SELECT h.id_bhp, h.nama_barang, h.kategori_id, h.barcode, h.stok, h.satuan, h.deskripsi,
           k.nama_kategori,
            MAX(CASE WHEN ib.ruangan_id IS NULL THEN ib.tanggal_masuk END) AS tanggal_masuk,
           COALESCE(SUM(CASE WHEN ib.ruangan_id IS NOT NULL THEN ib.jumlah ELSE 0 END), 0) AS terpakai
    FROM bhp h
    LEFT JOIN kategori_bhp k ON h.kategori_id = k.id_kategori
    LEFT JOIN inventaris_bhp ib ON h.id_bhp = ib.bhp_id
    $where
    GROUP BY h.id_bhp, h.nama_barang, h.stok, h.satuan, h.deskripsi, k.nama_kategori
    ORDER BY h.nama_barang ASC
";
$result = mysqli_query($koneksi, $query);
$q_bhp_options = mysqli_query($koneksi, "SELECT id_bhp, nama_barang, satuan FROM bhp ORDER BY nama_barang ASC");
$q_bhp_categories = mysqli_query($koneksi, "SELECT id_kategori, nama_kategori FROM kategori_bhp ORDER BY nama_kategori ASC");
?>

<?php include 'includes/header.php'; ?>

<div class="dashboard-container bhp-page">
    <div class="bhp-page-header">
        <div>
            <h1>Barang Habis Pakai</h1>
            <p>Kelola stok dan pemakaian barang habis pakai per ruangan.</p>
        </div>
        <?php if (strtolower($_SESSION['role'] ?? '') !== 'kepala_sekolah'): ?>
            <button type="button" class="btn-primary" id="openAddStock"><i class="bi bi-plus-lg"></i> Tambah Stok BHP</button>
        <?php endif; ?>
    </div>

    <?php if ($message !== ''): ?>
        <div class="bhp-alert <?= $message_type === 'error' ? 'error' : ''; ?>"><?= htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="GET" class="bhp-filter">
        <input type="search" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Cari nama atau kategori BHP...">
        <button type="submit" class="btn-primary">Cari</button>
    </form>

    <div class="widget-card bhp-table-wrap">
        <table class="bhp-table">
            <thead>
                <tr><th>No</th><th>Nama Barang</th><th>Kategori</th><th>Stok</th><th>Satuan</th><th>Tanggal Masuk</th><th>Terpakai</th><th>Keterangan</th><?php if ($user_role !== 'kepala_sekolah'): ?><th>Aksi</th><?php endif; ?></tr>
            </thead>
            <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): $no = 1; while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td><strong><?= htmlspecialchars($row['nama_barang']); ?></strong></td>
                        <td><?= htmlspecialchars($row['nama_kategori'] ?? '-'); ?></td>
                        <td><span class="bhp-number stock"><?= (int) $row['stok']; ?></span></td>
                        <td><?= htmlspecialchars($row['satuan'] ?: '-'); ?></td>
                        <td><?= htmlspecialchars($row['tanggal_masuk'] ?: '-'); ?></td>
                        <td><span class="bhp-number used"><?= (int) $row['terpakai']; ?></span></td>
                        <td><?= htmlspecialchars($row['deskripsi'] ?: '-'); ?></td>
                        <?php if ($user_role !== 'kepala_sekolah'): ?>
                            <td class="bhp-actions">
                                <button type="button" class="btn-action btn-edit" onclick="openEditBhpModal(<?= (int) $row['id_bhp']; ?>, '<?= htmlspecialchars($row['nama_barang'], ENT_QUOTES, 'UTF-8'); ?>', <?= (int) $row['kategori_id']; ?>, '<?= htmlspecialchars($row['barcode'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', '<?= htmlspecialchars(strtoupper($row['satuan'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>')">
                                    <i class="bi bi-pencil-square"></i> Edit
                                </button>
                                <form method="POST" onsubmit="return confirmDeleteBhp('<?= htmlspecialchars($row['nama_barang'], ENT_QUOTES, 'UTF-8'); ?>');">
                                    <input type="hidden" name="action" value="delete_bhp">
                                    <input type="hidden" name="id_bhp" value="<?= (int) $row['id_bhp']; ?>">
                                    <button type="submit" class="btn-action btn-delete"><i class="bi bi-trash"></i> Hapus</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="<?= $user_role !== 'kepala_sekolah' ? '9' : '8'; ?>" class="bhp-empty">Belum ada master barang habis pakai.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="bhp-modal" id="editBhpModal" aria-hidden="true">
    <div class="bhp-modal-card" role="dialog" aria-modal="true" aria-labelledby="editBhpTitle">
        <div class="bhp-modal-header">
            <h2 id="editBhpTitle">Edit Barang Habis Pakai</h2>
            <button type="button" class="bhp-modal-close" id="closeEditBhp" aria-label="Tutup">&times;</button>
        </div>
        <form method="POST" class="bhp-modal-body">
            <input type="hidden" name="action" value="edit_bhp">
            <input type="hidden" name="id_bhp" id="editBhpId">
            <label>Nama Barang <span>*</span></label>
            <input type="text" name="nama_barang" id="editBhpName" required>
            <label>Kategori <span>*</span></label>
            <select name="kategori_id" id="editBhpCategory" required>
                <option value="">-- Pilih kategori --</option>
                <?php while ($category = mysqli_fetch_assoc($q_bhp_categories)): ?>
                    <option value="<?= (int) $category['id_kategori']; ?>"><?= htmlspecialchars($category['nama_kategori']); ?></option>
                <?php endwhile; ?>
            </select>
            <label>Barcode <span>*</span></label>
            <input type="text" name="barcode" id="editBhpBarcode" required>
            <label>Satuan <span>*</span></label>
            <select name="satuan" id="editBhpUnit" required>
                <option value="">-- Pilih satuan --</option>
                <option value="PCS">PCS</option>
                <option value="BOX">Box</option>
                <option value="PACK">Pack</option>
                <option value="LUSIN">Lusin</option>
            </select>
            <div class="bhp-modal-actions">
                <button type="button" class="bhp-cancel" id="cancelEditBhp">Batal</button>
                <button type="submit" class="btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<div class="bhp-modal" id="addStockModal" aria-hidden="true">
    <div class="bhp-modal-card" role="dialog" aria-modal="true" aria-labelledby="addStockTitle">
        <div class="bhp-modal-header">
            <h2 id="addStockTitle">Tambah Stok Barang Habis Pakai</h2>
            <button type="button" class="bhp-modal-close" id="closeAddStock" aria-label="Tutup">&times;</button>
        </div>
        <form method="POST" class="bhp-modal-body">
            <input type="hidden" name="action" value="add_stock">
            <label>Barang BHP <span>*</span></label>
            <select name="id_bhp" required>
                <option value="">-- Pilih barang yang sudah terdaftar --</option>
                <?php while ($option = mysqli_fetch_assoc($q_bhp_options)): ?>
                    <option value="<?= $option['id_bhp']; ?>" data-satuan="<?= htmlspecialchars($option['satuan'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($option['nama_barang']); ?></option>
                <?php endwhile; ?>
            </select>
            <label>Jumlah Stok <span>*</span></label>
            <input type="number" name="jumlah" min="1" value="1" required>
            <label>Tanggal Masuk</label>
            <input type="date" name="tanggal_masuk" value="<?= date('Y-m-d'); ?>" required>
            <label>Satuan</label>
            <input type="text" id="stockUnit" readonly placeholder="Otomatis mengikuti master BHP">
            <div class="bhp-modal-actions">
                <button type="button" class="bhp-cancel" id="cancelAddStock">Batal</button>
                <button type="submit" class="btn-primary">Tambah Stok</button>
            </div>
        </form>
    </div>
</div>

<style>
    .bhp-page { padding:24px; }
    .bhp-page-header { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:20px; }
    .bhp-page-header h1 { margin:0; color:#0f172a; font-size:26px; }
    .bhp-page-header p { margin:6px 0 0; color:#64748b; font-size:13px; }
    .bhp-filter { display:flex; gap:10px; margin-bottom:18px; }
    .bhp-filter input { flex:1; padding:11px 14px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; }
    .bhp-table-wrap { overflow-x:auto; padding:0; }
    .bhp-table { width:100%; border-collapse:collapse; min-width:760px; }
    .bhp-table th, .bhp-table td { padding:14px 16px; border-bottom:1px solid #e2e8f0; text-align:left; font-size:14px; }
    .bhp-table th { background:#f8fafc; color:#64748b; font-size:12px; text-transform:uppercase; }
    .bhp-actions { display:flex; gap:8px; align-items:center; white-space:nowrap; }
    .bhp-actions form { margin:0; }
    .btn-action { padding:8px 10px; border:0; border-radius:6px; cursor:pointer; font:inherit; font-size:12px; font-weight:600; }
    .btn-edit { background:#dbeafe; color:#1d4ed8; }
    .btn-delete { background:#fee2e2; color:#b91c1c; }
    .btn-action:hover { filter:brightness(.96); }
    .bhp-number { display:inline-flex; min-width:38px; justify-content:center; padding:5px 9px; border-radius:6px; font-weight:700; }
    .bhp-number.stock { background:#dcfce7; color:#166534; }
    .bhp-number.used { background:#fef3c7; color:#92400e; }
    .bhp-empty { padding:32px !important; color:#64748b; text-align:center !important; }
    .bhp-alert { margin-bottom:16px; padding:12px 16px; border-radius:8px; background:#dcfce7; color:#166534; }
    .bhp-alert.error { background:#fee2e2; color:#991b1b; }
    .bhp-modal { display:none; position:fixed; inset:0; z-index:100000; align-items:center; justify-content:center; padding:18px; background:rgba(15,23,42,.6); }
    .bhp-modal.show { display:flex; }
    .bhp-modal-card { width:min(100%, 480px); background:#fff; border-radius:12px; box-shadow:0 24px 60px rgba(15,23,42,.25); }
    .bhp-modal-header { display:flex; align-items:center; justify-content:space-between; padding:18px 20px; border-bottom:1px solid #e2e8f0; }
    .bhp-modal-header h2 { margin:0; color:#0f172a; font-size:18px; }
    .bhp-modal-close { border:0; background:transparent; color:#64748b; cursor:pointer; font-size:28px; line-height:1; }
    .bhp-modal-body { display:flex; flex-direction:column; gap:8px; padding:20px; }
    .bhp-modal-body label { margin-top:5px; color:#475569; font-size:12px; font-weight:700; }
    .bhp-modal-body label span { color:#dc2626; }
    .bhp-modal-body select, .bhp-modal-body input { box-sizing:border-box; width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; font:inherit; }
    .bhp-modal-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:14px; }
    .bhp-cancel { padding:10px 18px; border:1px solid #cbd5e1; border-radius:8px; background:#fff; color:#475569; cursor:pointer; font-weight:600; }
    @media (max-width:600px) { .bhp-page-header { align-items:stretch; flex-direction:column; } .bhp-page-header .btn-primary { justify-content:center; } .bhp-filter { flex-direction:column; } .bhp-filter .btn-primary { justify-content:center; } }
</style>

<script>
    const editBhpModal = document.getElementById('editBhpModal');
    const editBhpId = document.getElementById('editBhpId');
    const editBhpName = document.getElementById('editBhpName');
    const editBhpCategory = document.getElementById('editBhpCategory');
    const editBhpBarcode = document.getElementById('editBhpBarcode');
    const editBhpUnit = document.getElementById('editBhpUnit');
    function openEditBhpModal(id, name, category, barcode, unit) {
        editBhpId.value = id;
        editBhpName.value = name;
        editBhpCategory.value = category;
        editBhpBarcode.value = barcode;
        editBhpUnit.value = unit;
        editBhpModal.classList.add('show');
        editBhpModal.setAttribute('aria-hidden', 'false');
    }
    function closeEditBhpModal() {
        editBhpModal.classList.remove('show');
        editBhpModal.setAttribute('aria-hidden', 'true');
    }
    function confirmDeleteBhp(name) {
        return confirm('Hapus BHP "' + name + '" beserta seluruh catatan stok dan distribusinya?');
    }
    document.getElementById('closeEditBhp').addEventListener('click', closeEditBhpModal);
    document.getElementById('cancelEditBhp').addEventListener('click', closeEditBhpModal);
    editBhpModal.addEventListener('click', function (event) {
        if (event.target === editBhpModal) closeEditBhpModal();
    });

    const addStockModal = document.getElementById('addStockModal');
    const openAddStock = document.getElementById('openAddStock');
    const closeAddStock = document.getElementById('closeAddStock');
    const cancelAddStock = document.getElementById('cancelAddStock');
    const stockItem = document.querySelector('select[name="id_bhp"]');
    const stockUnit = document.getElementById('stockUnit');
    if (stockItem && stockUnit) {
        stockItem.addEventListener('change', function () {
            const selected = this.options[this.selectedIndex];
            stockUnit.value = selected ? (selected.dataset.satuan || 'Belum ditentukan') : '';
        });
    }
    function closeStockModal() { addStockModal.classList.remove('show'); addStockModal.setAttribute('aria-hidden', 'true'); }
    if (openAddStock) openAddStock.addEventListener('click', function () { addStockModal.classList.add('show'); addStockModal.setAttribute('aria-hidden', 'false'); });
    if (closeAddStock) closeAddStock.addEventListener('click', closeStockModal);
    if (cancelAddStock) cancelAddStock.addEventListener('click', closeStockModal);
    if (addStockModal) addStockModal.addEventListener('click', function (event) { if (event.target === addStockModal) closeStockModal(); });
</script>

<?php include 'includes/footer.php'; ?>
