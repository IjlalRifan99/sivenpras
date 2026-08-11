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
    $where_clauses[] = "(b.nama_barang LIKE '%$search%' OR b.kode_barang LIKE '%$search%' OR r.nama_ruangan LIKE '%$search%')";
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
        b.kode_barang,
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

// Query untuk Dropdown Kategori di Filter
$q_filter_kategori = mysqli_query($koneksi, "SELECT * FROM kategori ORDER BY nama_kategori ASC");

// Query detail unit inventaris per barang untuk popup
$detail_query = "
    SELECT 
        b.id_barang,
        i.barcode,
        i.kondisi,
        r.nama_ruangan
    FROM inventaris i
    JOIN barang b ON i.barang_id = b.id_barang
    LEFT JOIN ruangan r ON i.ruangan_id = r.id_ruangan
    WHERE 1=1
";

if (!empty($search)) {
    $detail_query .= " AND (b.nama_barang LIKE '%$search%' OR b.kode_barang LIKE '%$search%' OR r.nama_ruangan LIKE '%$search%')";
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

<?php include 'includes/header.php'; ?>

    <div class="dashboard-container">
        <div class="page-header">
            <h1>Daftar Inventaris</h1>
            <p>Total <?= $result_barang ? mysqli_num_rows($result_barang) : 0; ?> jenis barang tercatat dalam sistem</p>
        </div>

        <form method="GET" action="daftar-inventaris.php" class="filter-bar" style="display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 250px; position: relative;">
                <input type="text" name="search" placeholder="Cari nama, kode, atau lokasi..." value="<?= htmlspecialchars($search); ?>" style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">
            </div>

            <select name="kategori" onchange="this.form.submit()" style="padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">
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

            <select name="kondisi" onchange="this.form.submit()" style="padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">
                <option value="">Semua Kondisi</option>
                <option value="baik" <?= ($kondisi == 'baik') ? 'selected' : ''; ?>>Baik</option>
                <option value="rusak" <?= ($kondisi == 'rusak') ? 'selected' : ''; ?>>Rusak</option>
                <option value="hilang" <?= ($kondisi == 'hilang') ? 'selected' : ''; ?>>Hilang</option>
            </select>

            <?php if (!empty($search) || !empty($kategori) || !empty($kondisi)) { ?>
                <a href="daftar-inventaris.php" style="padding: 10px 14px; background: #eee; color: #333; text-decoration: none; border-radius: 8px; font-size: 14px;">Reset</a>
            <?php } ?>
        </form>

        <div class="widget-card" style="padding: 0; overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b;">
                        <th style="padding: 14px 18px;">NO</th>
                        <th style="padding: 14px 18px;">KODE</th>
                        <th style="padding: 14px 18px;">NAMA BARANG</th>
                        <th style="padding: 14px 18px;">KATEGORI</th>
                        <th style="padding: 14px 18px;">KONDISI</th>
                        <th style="padding: 14px 18px;">JUMLAH</th>
                        <th style="padding: 14px 18px;">LOKASI</th>
                        <th style="padding: 14px 18px; text-align: center;">AKSI</th>
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
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 14px 18px; font-weight: 500; color: #64748b;">
                                <?= $no++; ?>
                            </td>

                            <td style="padding: 14px 18px; font-weight: 700; color: #0f172a;">
                                <?= htmlspecialchars($row['kode_barang']); ?>
                            </td>

                            <td style="padding: 14px 18px;">
                                <strong style="display: block; color: #1e293b;">
                                    <?= htmlspecialchars($row['nama_barang']); ?>
                                </strong>
                                <small style="color: #94a3b8;">
                                    <?= htmlspecialchars($row['deskripsi']); ?>
                                </small>
                            </td>

                            <td style="padding: 14px 18px; color: #475569;">
                                <?= htmlspecialchars($row['nama_kategori'] ?? '-'); ?>
                            </td>

                            <td style="padding: 14px 18px;">
                                <span class="dot <?= $badge_class; ?>" style="display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:5px;"></span>
                                <?= htmlspecialchars($kondisi_text); ?>
                            </td>

                            <td style="padding: 14px 18px; font-weight: 600; color: #0f172a;">
                                <?= number_format($row['jumlah_unit']); ?>

                                <button type="button" class="btn-view-units" data-barang-id="<?= $row['id_barang']; ?>" data-barang-nama="<?= htmlspecialchars($row['nama_barang']); ?>" data-barang-kode="<?= htmlspecialchars($row['kode_barang']); ?>" style="margin-left: 8px; padding: 6px 10px; border: 1px solid #94a3b8; border-radius: 6px; background: #fff; font-size: 12px; cursor: pointer;">
                                    Lihat
                                </button>
                            </td>

                            <td style="padding: 14px 18px; color: #475569;">
                                <?= htmlspecialchars($row['lokasi'] ?? '-'); ?>
                            </td>

                            <td style="padding: 14px 18px; text-align: center;">
                                <span style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; color: #334155; font-size: 12px; font-weight: 500;">-</span>
                            </td>
                        </tr>
                    <?php
                        }
                    } else {
                        echo "<tr><td colspan='8' style='text-align: center; padding: 30px; color: #94a3b8;'>Tidak ada data barang yang ditemukan.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<div id="detailModal" class="modal-backdrop" aria-hidden="true">
    <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-header">
            <div>
                <h2 id="modalTitle">Detail Unit</h2>
                <p id="modalSubtitle" style="margin: 6px 0 0; color: #64748b; font-size: 13px;"></p>
            </div>
            <button id="modalClose" class="modal-close" aria-label="Tutup">&times;</button>
        </div>

        <div class="modal-content">
            <div style="margin-bottom: 14px; color: #334155;">
                Daftar semua barcode unit, kondisi, dan ruangan untuk jenis barang ini.
            </div>

            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>BARCODE</th>
                            <th>KONDISI</th>
                            <th>RUANGAN</th>
                        </tr>
                    </thead>
                    <tbody id="modalBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const detailUnits = <?= json_encode($detail_units_by_barang, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

function escapeHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function openModal(barangId, barangNama, barangKode) {
    const modal = document.getElementById('detailModal');
    const title = document.getElementById('modalTitle');
    const subtitle = document.getElementById('modalSubtitle');
    const body = document.getElementById('modalBody');
    const units = detailUnits[barangId] || [];

    title.textContent = `Detail ${barangKode}`;
    subtitle.textContent = `${barangNama} — ${units.length} unit ditemukan`;

    body.innerHTML = units.length > 0
        ? units.map(unit => `
            <tr>
                <td>${escapeHtml(unit.barcode)}</td>
                <td>${escapeHtml(unit.kondisi)}</td>
                <td>${escapeHtml(unit.nama_ruangan || '-')}</td>
            </tr>
        `).join('')
        : '<tr><td colspan="3" style="padding: 16px; text-align: center; color: #64748b;">Tidak ada unit inventaris tersedia.</td></tr>';

    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
}

function closeModal() {
    const modal = document.getElementById('detailModal');
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-view-units').forEach(button => {
        button.addEventListener('click', function () {
            openModal(this.dataset.barangId, this.dataset.barangNama, this.dataset.barangKode);
        });
    });

    document.getElementById('modalClose').addEventListener('click', closeModal);

    document.getElementById('detailModal').addEventListener('click', function (event) {
        if (event.target === this) {
            closeModal();
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>