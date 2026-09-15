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

$stok_column = mysqli_query($koneksi, "SHOW COLUMNS FROM bhp LIKE 'stok'");
if (!$stok_column || mysqli_num_rows($stok_column) === 0) {
    mysqli_query($koneksi, "ALTER TABLE bhp ADD COLUMN stok INT NOT NULL DEFAULT 0");
}
$ruangan_column = mysqli_query($koneksi, "SHOW COLUMNS FROM inventaris_bhp LIKE 'ruangan_id'");
if (!$ruangan_column || mysqli_num_rows($ruangan_column) === 0) {
    mysqli_query($koneksi, "ALTER TABLE inventaris_bhp ADD COLUMN ruangan_id INT NULL AFTER bhp_id");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_stock') {
    $id_bhp_stock = (int) ($_POST['id_bhp'] ?? 0);
    $jumlah_stock = (int) ($_POST['jumlah'] ?? 0);
    $satuan_stock = strtoupper(trim($_POST['satuan'] ?? ''));
    $satuan_map = ['PCS' => 'PCS', 'BOX' => 'Box', 'PACK' => 'Pack', 'LUSIN' => 'Lusin'];

    if ($id_bhp_stock <= 0 || $jumlah_stock <= 0 || !isset($satuan_map[$satuan_stock])) {
        $message = 'Pilih barang, jumlah stok, dan satuan yang valid.';
        $message_type = 'error';
    } else {
        $satuan_db = mysqli_real_escape_string($koneksi, $satuan_map[$satuan_stock]);
        mysqli_begin_transaction($koneksi);
        $insert_stock = mysqli_query($koneksi, "INSERT INTO inventaris_bhp (bhp_id, ruangan_id, jumlah, satuan, keterangan, barcode) VALUES ('$id_bhp_stock', NULL, '$jumlah_stock', '$satuan_db', '', '')");
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

$where = $search !== '' ? "WHERE h.nama_barang LIKE '%$search%' OR k.nama_kategori LIKE '%$search%'" : '';
$query = "
    SELECT h.id_bhp, h.nama_barang, h.stok, h.deskripsi,
           k.nama_kategori,
            GROUP_CONCAT(DISTINCT CASE WHEN ib.ruangan_id IS NULL THEN ib.satuan END SEPARATOR ', ') AS satuan,
           COALESCE(SUM(CASE WHEN ib.ruangan_id IS NOT NULL THEN ib.jumlah ELSE 0 END), 0) AS terpakai
    FROM bhp h
    LEFT JOIN kategori_bhp k ON h.kategori_id = k.id_kategori
    LEFT JOIN inventaris_bhp ib ON h.id_bhp = ib.bhp_id
    $where
    GROUP BY h.id_bhp, h.nama_barang, h.stok, h.deskripsi, k.nama_kategori
    ORDER BY h.nama_barang ASC
";
$result = mysqli_query($koneksi, $query);
$q_bhp_options = mysqli_query($koneksi, "SELECT id_bhp, nama_barang FROM bhp ORDER BY nama_barang ASC");
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
                <tr><th>No</th><th>Nama Barang</th><th>Kategori</th><th>Stok</th><th>Satuan</th><th>Terpakai</th><th>Keterangan</th></tr>
            </thead>
            <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): $no = 1; while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td><strong><?= htmlspecialchars($row['nama_barang']); ?></strong></td>
                        <td><?= htmlspecialchars($row['nama_kategori'] ?? '-'); ?></td>
                        <td><span class="bhp-number stock"><?= (int) $row['stok']; ?></span></td>
                        <td><?= htmlspecialchars($row['satuan'] ?: '-'); ?></td>
                        <td><span class="bhp-number used"><?= (int) $row['terpakai']; ?></span></td>
                        <td><?= htmlspecialchars($row['deskripsi'] ?: '-'); ?></td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="7" class="bhp-empty">Belum ada master barang habis pakai.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
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
                    <option value="<?= $option['id_bhp']; ?>"><?= htmlspecialchars($option['nama_barang']); ?></option>
                <?php endwhile; ?>
            </select>
            <label>Jumlah Stok <span>*</span></label>
            <input type="number" name="jumlah" min="1" value="1" required>
            <label>Satuan <span>*</span></label>
            <select name="satuan" required>
                <option value="">-- Pilih satuan --</option>
                <option value="PCS">PCS</option>
                <option value="BOX">Box</option>
                <option value="PACK">Pack</option>
                <option value="LUSIN">Lusin</option>
            </select>
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
    const addStockModal = document.getElementById('addStockModal');
    const openAddStock = document.getElementById('openAddStock');
    const closeAddStock = document.getElementById('closeAddStock');
    const cancelAddStock = document.getElementById('cancelAddStock');
    function closeStockModal() { addStockModal.classList.remove('show'); addStockModal.setAttribute('aria-hidden', 'true'); }
    if (openAddStock) openAddStock.addEventListener('click', function () { addStockModal.classList.add('show'); addStockModal.setAttribute('aria-hidden', 'false'); });
    if (closeAddStock) closeAddStock.addEventListener('click', closeStockModal);
    if (cancelAddStock) cancelAddStock.addEventListener('click', closeStockModal);
    if (addStockModal) addStockModal.addEventListener('click', function (event) { if (event.target === addStockModal) closeStockModal(); });
</script>

<?php include 'includes/footer.php'; ?>
