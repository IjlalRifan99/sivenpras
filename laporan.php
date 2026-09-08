<?php
session_start();
include 'config/koneksi.php';

if (!isset($_SESSION['login'])) {
    header('Location: login.php');
    exit;
}

// Dipakai oleh dropdown nama barang setelah kategori dipilih.
if (isset($_GET['action']) && $_GET['action'] === 'barang_by_kategori') {
    header('Content-Type: application/json; charset=utf-8');
    $kategori_id = (int)($_GET['kategori'] ?? 0);
    $barang = [];

    if ($kategori_id > 0) {
        $query_barang = mysqli_query($koneksi, "SELECT id_barang, nama_barang FROM barang WHERE kategori_id = $kategori_id ORDER BY nama_barang ASC");
        if ($query_barang) {
            while ($row = mysqli_fetch_assoc($query_barang)) {
                $barang[] = $row;
            }
        }
    }

    echo json_encode($barang);
    exit;
}

$kategori_id = (int)($_GET['kategori'] ?? 0);
$barang_id = (int)($_GET['barang'] ?? 0);
$ruangan_id = (int)($_GET['ruangan'] ?? 0);
$kondisi = mysqli_real_escape_string($koneksi, trim($_GET['kondisi'] ?? ''));

$where = ['1=1'];
if ($kategori_id > 0) {
    $where[] = "b.kategori_id = $kategori_id";
}
if ($barang_id > 0) {
    $where[] = "b.id_barang = $barang_id";
}
if ($ruangan_id > 0) {
    $where[] = "i.ruangan_id = $ruangan_id";
}
if ($kondisi !== '') {
    $where[] = "i.kondisi = '$kondisi'";
}
$where_sql = implode(' AND ', $where);

$query_data = "
    SELECT
        i.id_inventaris,
        i.barcode,
        i.tahun_perolehan,
        i.kondisi,
        i.keterangan,
        b.nama_barang,
        k.nama_kategori,
        r.nama_ruangan
    FROM inventaris i
    INNER JOIN barang b ON i.barang_id = b.id_barang
    LEFT JOIN kategori k ON b.kategori_id = k.id_kategori
    LEFT JOIN ruangan r ON i.ruangan_id = r.id_ruangan
    WHERE $where_sql
    ORDER BY r.nama_ruangan ASC, b.nama_barang ASC, i.barcode ASC
";

$result_data = mysqli_query($koneksi, $query_data);

if (isset($_GET['action']) && $_GET['action'] === 'export_excel') {
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        exit('Ekstensi ZipArchive PHP belum tersedia.');
    }

    $rows = [];
    while ($row = mysqli_fetch_assoc($result_data)) {
        $rows[] = [
            count($rows) + 1,
            $row['barcode'] ?? '-',
            $row['nama_barang'] ?? '-',
            $row['nama_kategori'] ?? '-',
            $row['nama_ruangan'] ?? '-',
            ucwords($row['kondisi'] ?? '-'),
            $row['tahun_perolehan'] ?? '-',
            $row['keterangan'] ?? '-'
        ];
    }

    $xml = static function ($value): string {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string)$value);
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    };

    $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
    $headers = ['NO', 'BARCODE', 'NAMA BARANG', 'KATEGORI', 'LOKASI', 'KONDISI', 'TAHUN PEROLEHAN', 'KETERANGAN'];
    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $sheet .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $sheet .= '<dimension ref="A1:H' . (count($rows) + 1) . '"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
    $sheet .= '<cols><col min="1" max="1" width="8" customWidth="1"/><col min="2" max="2" width="20" customWidth="1"/><col min="3" max="4" width="28" customWidth="1"/><col min="5" max="6" width="18" customWidth="1"/><col min="7" max="7" width="16" customWidth="1"/><col min="8" max="8" width="35" customWidth="1"/></cols><sheetData><row r="1">';
    foreach ($headers as $index => $header) {
        $sheet .= '<c r="' . $columns[$index] . '1" t="inlineStr" s="1"><is><t>' . $xml($header) . '</t></is></c>';
    }
    $sheet .= '</row>';
    foreach ($rows as $rowIndex => $row) {
        $excelRow = $rowIndex + 2;
        $sheet .= '<row r="' . $excelRow . '">';
        foreach ($row as $columnIndex => $value) {
            $cellType = is_int($value) ? '' : ' t="inlineStr"';
            $cellValue = is_int($value)
                ? '<v>' . $value . '</v>'
                : '<is><t>' . $xml($value) . '</t></is>';
            $sheet .= '<c r="' . $columns[$columnIndex] . $excelRow . '"' . $cellType . '>' . $cellValue . '</c>';
        }
        $sheet .= '</row>';
    }
    $sheet .= '</sheetData><autoFilter ref="A1:H' . (count($rows) + 1) . '"/></worksheet>';

    $content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>';
    $root_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>';
    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Laporan Inventaris" sheetId="1" r:id="rId1"/></sheets></workbook>';
    $workbook_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0D9488"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles><dxfs count="0"/><tableStyles count="0"/></styleSheet>';
    $core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>Laporan Inventaris</dc:title><dc:creator>SIVENPRAS-TB</dc:creator></cp:coreProperties>';

    $file_name = 'Laporan_Inventaris_SIVENPRAS_' . date('Y-m-d') . '.xlsx';
    $temp_file = tempnam(sys_get_temp_dir(), 'sivenpras_xlsx_');
    $zip = new ZipArchive();
    if ($zip->open($temp_file, ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        exit('Gagal membuat file XLSX.');
    }
    $zip->addFromString('[Content_Types].xml', $content_types);
    $zip->addFromString('_rels/.rels', $root_rels);
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbook_rels);
    $zip->addFromString('xl/styles.xml', $styles);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->addFromString('docProps/core.xml', $core);
    $zip->close();

    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Content-Length: ' . filesize($temp_file));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    readfile($temp_file);
    unlink($temp_file);
    exit;
}

$active_page = 'laporan';
$page_title = 'Laporan Inventaris';
$breadcrumb = 'Laporan';
$q_kategori = mysqli_query($koneksi, 'SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori ASC');
$q_ruangan = mysqli_query($koneksi, 'SELECT id_ruangan, nama_ruangan FROM ruangan ORDER BY nama_ruangan ASC');
$selected_barang = null;
if ($barang_id > 0) {
    $q_selected_barang = mysqli_query($koneksi, "SELECT id_barang, nama_barang FROM barang WHERE id_barang = $barang_id LIMIT 1");
    $selected_barang = $q_selected_barang ? mysqli_fetch_assoc($q_selected_barang) : null;
}
?>

<?php include 'includes/header.php'; ?>

<div class="dashboard-container">
    <div class="page-header no-print">
        <h1>Laporan Inventaris</h1>
        <p>Seluruh data unit inventaris dengan filter kategori, nama barang, kondisi, dan lokasi</p>
    </div>

    <div class="widget-card no-print report-filter-card">
        <form method="GET" action="laporan.php" class="filter-grid">
            <div class="filter-item">
                <label for="kategori">Kategori</label>
                <select name="kategori" id="kategori" class="filter-select">
                    <option value="">Semua Kategori</option>
                    <?php while ($kat = mysqli_fetch_assoc($q_kategori)): ?>
                        <option value="<?= $kat['id_kategori']; ?>" <?= $kategori_id === (int)$kat['id_kategori'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($kat['nama_kategori']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="filter-item">
                <label for="barang">Nama Barang</label>
                <select name="barang" id="barang" class="filter-select" <?= $kategori_id > 0 ? '' : 'disabled'; ?> data-selected="<?= $barang_id; ?>">
                    <option value=""><?= $kategori_id > 0 ? 'Semua Nama Barang' : 'Pilih kategori dahulu'; ?></option>
                    <?php if ($selected_barang && $kategori_id > 0): ?>
                        <option value="<?= $selected_barang['id_barang']; ?>" selected><?= htmlspecialchars($selected_barang['nama_barang']); ?></option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="filter-item">
                <label for="kondisi">Kondisi</label>
                <select name="kondisi" id="kondisi" class="filter-select">
                    <option value="">Semua Kondisi</option>
                    <?php foreach (['baik' => 'Baik', 'cukup baik' => 'Cukup Baik', 'rusak' => 'Rusak', 'rusak parah' => 'Rusak Parah', 'hilang' => 'Hilang'] as $value => $label): ?>
                        <option value="<?= $value; ?>" <?= $kondisi === $value ? 'selected' : ''; ?>><?= $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-item">
                <label for="ruangan">Lokasi</label>
                <select name="ruangan" id="ruangan" class="filter-select">
                    <option value="">Semua Lokasi</option>
                    <?php while ($ruang = mysqli_fetch_assoc($q_ruangan)): ?>
                        <option value="<?= $ruang['id_ruangan']; ?>" <?= $ruangan_id === (int)$ruang['id_ruangan'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($ruang['nama_ruangan']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="filter-actions">
                <a href="laporan.php" class="btn-reset">Reset</a>
            </div>
        </form>

        <div class="report-actions">
            <a href="laporan.php?action=export_excel&kategori=<?= $kategori_id; ?>&barang=<?= $barang_id; ?>&ruangan=<?= $ruangan_id; ?>&kondisi=<?= urlencode($kondisi); ?>" class="btn-export-excel">
                <i class="bi bi-file-earmark-excel"></i> Export Excel
            </a>
            <button type="button" onclick="window.print()" class="btn-print"><i class="bi bi-printer"></i> Cetak / PDF</button>
        </div>
    </div>

    <div class="print-only print-header">
        <h2>SMK TARUNA BANGSA</h2>
        <p>LAPORAN DATA INVENTARIS SARANA DAN PRASARANA</p>
        <p>Tanggal Cetak: <?= date('d F Y'); ?></p>
    </div>

    <div class="widget-card table-wrapper">
        <table class="custom-table print-table">
            <thead>
                <tr>
                    <th>NO</th><th>BARCODE</th><th>NAMA BARANG</th><th>KATEGORI</th>
                    <th>LOKASI</th><th>KONDISI</th><th>TAHUN</th><th>KETERANGAN</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result_data && mysqli_num_rows($result_data) > 0): ?>
                    <?php $no = 1; while ($row = mysqli_fetch_assoc($result_data)): ?>
                        <tr>
                            <td><?= $no++; ?></td>
                            <td><?= htmlspecialchars($row['barcode'] ?? '-'); ?></td>
                            <td><strong><?= htmlspecialchars($row['nama_barang']); ?></strong></td>
                            <td><?= htmlspecialchars($row['nama_kategori'] ?? '-'); ?></td>
                            <td><?= htmlspecialchars($row['nama_ruangan'] ?? '-'); ?></td>
                            <td><?= htmlspecialchars(ucwords($row['kondisi'] ?? '-')); ?></td>
                            <td><?= htmlspecialchars($row['tahun_perolehan'] ?? '-'); ?></td>
                            <td><?= htmlspecialchars($row['keterangan'] ?? '-'); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align: center; padding: 30px; color: #94a3b8;">Data inventaris tidak ditemukan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
    .report-filter-card { margin-bottom: 20px; padding: 20px; }
    .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; align-items: end; }
    .filter-item { display: flex; flex-direction: column; gap: 4px; }
    .filter-item label { font-size: 12px; font-weight: 600; color: #475569; }
    .filter-select { width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; outline: none; background: #fff; }
    .filter-select:disabled { background: #f1f5f9; color: #94a3b8; cursor: not-allowed; }
    .filter-actions, .report-actions { display: flex; gap: 8px; align-items: center; }
    .report-actions { justify-content: flex-end; margin-top: 15px; padding-top: 15px; border-top: 1px solid #f1f5f9; }
    .btn-filter, .btn-reset, .btn-export-excel, .btn-print { padding: 8px 14px; border: 0; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; }
    .btn-filter { background: #0d9488; color: white; }
    .btn-reset { background: #e2e8f0; color: #334155; }
    .btn-export-excel { background: #16a34a; color: white; }
    .btn-print { background: #0284c7; color: white; }
    .print-only { display: none; }
    @media print {
        .no-print, .sidebar, header, nav { display: none !important; }
        .print-only { display: block !important; }
        body { background: #fff !important; color: #000; }
        .dashboard-container { padding: 0 !important; margin: 0 !important; }
        .widget-card { box-shadow: none !important; border: none !important; padding: 0 !important; }
        .print-table { width: 100%; border-collapse: collapse !important; }
        .print-table th, .print-table td { border: 1px solid #000 !important; padding: 6px 8px !important; font-size: 11px !important; }
        .print-table th { background: #f1f5f9 !important; -webkit-print-color-adjust: exact; }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const filterForm = document.querySelector('.report-filter-card form');
    const kategori = document.getElementById('kategori');
    const barang = document.getElementById('barang');

    function refreshReport() {
        filterForm.submit();
    }

    function loadBarang(kategoriId, selectedId) {
        barang.innerHTML = '<option value="">Memuat nama barang...</option>';
        barang.disabled = !kategoriId;
        if (!kategoriId) {
            barang.innerHTML = '<option value="">Pilih kategori dahulu</option>';
            return;
        }

        fetch('laporan.php?action=barang_by_kategori&kategori=' + encodeURIComponent(kategoriId))
            .then(response => response.json())
            .then(items => {
                barang.innerHTML = '<option value="">Semua Nama Barang</option>';
                items.forEach(item => {
                    const option = new Option(item.nama_barang, item.id_barang);
                    if (String(item.id_barang) === String(selectedId)) option.selected = true;
                    barang.add(option);
                });
            })
            .catch(() => {
                barang.innerHTML = '<option value="">Gagal memuat nama barang</option>';
            });
    }

    kategori.addEventListener('change', function () {
        if (!this.value) {
            refreshReport();
            return;
        }

        loadBarang(this.value, '');
        refreshReport();
    });

    barang.addEventListener('change', refreshReport);
    document.getElementById('kondisi').addEventListener('change', refreshReport);
    document.getElementById('ruangan').addEventListener('change', refreshReport);

    if (kategori.value) {
        loadBarang(kategori.value, barang.dataset.selected);
    }
});
</script>

<?php include 'includes/footer.php'; ?>
