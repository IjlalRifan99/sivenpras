<?php
session_start();
include 'config/koneksi.php';

if (!isset($_SESSION['login'])) {
    header('Location: login.php');
    exit;
}

$active_page = 'laporan-bhp';
$page_title = 'Laporan BHP';
$breadcrumb = 'Laporan > Barang Habis Pakai';
$kategori_id = (int) ($_GET['kategori'] ?? 0);
$ruangan_id = (int) ($_GET['ruangan'] ?? 0);
$tahun_masuk = (int) ($_GET['tahun_masuk'] ?? date('Y'));
$bulan_masuk = (int) ($_GET['bulan_masuk'] ?? 0);
$tanggal_masuk = (int) ($_GET['tanggal_masuk'] ?? 0);

$tanggal_column = mysqli_query($koneksi, "SHOW COLUMNS FROM inventaris_bhp LIKE 'tanggal_masuk'");
if (!$tanggal_column || mysqli_num_rows($tanggal_column) === 0) {
    mysqli_query($koneksi, "ALTER TABLE inventaris_bhp ADD COLUMN tanggal_masuk DATE NULL AFTER jumlah");
    mysqli_query($koneksi, "UPDATE inventaris_bhp SET tanggal_masuk = CURRENT_DATE WHERE tanggal_masuk IS NULL");
}

$where = ['1=1'];
if ($kategori_id > 0) $where[] = "h.kategori_id = $kategori_id";
if ($ruangan_id > 0) $where[] = "ib.ruangan_id = $ruangan_id";
if ($tahun_masuk >= 2000 && $tahun_masuk <= 2099) $where[] = "YEAR(ib.tanggal_masuk) = $tahun_masuk";
if ($bulan_masuk >= 1 && $bulan_masuk <= 12) $where[] = "MONTH(ib.tanggal_masuk) = $bulan_masuk";
if ($tanggal_masuk >= 1 && $tanggal_masuk <= 31) $where[] = "DAY(ib.tanggal_masuk) = $tanggal_masuk";
$where_sql = implode(' AND ', $where);

$query = "SELECT ib.id_inventaris_bhp, ib.jumlah, ib.satuan, ib.tanggal_masuk, ib.keterangan,
                 h.nama_barang, k.nama_kategori, r.nama_ruangan
          FROM inventaris_bhp ib
          INNER JOIN bhp h ON h.id_bhp = ib.bhp_id
          LEFT JOIN kategori_bhp k ON k.id_kategori = h.kategori_id
          LEFT JOIN ruangan r ON r.id_ruangan = ib.ruangan_id
          WHERE $where_sql
          ORDER BY ib.tanggal_masuk DESC, h.nama_barang ASC, r.nama_ruangan ASC";
$result = mysqli_query($koneksi, $query);

if (($_GET['action'] ?? '') === 'export_excel') {
    if (!class_exists('ZipArchive')) exit('Ekstensi ZipArchive PHP belum tersedia.');
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = [count($rows) + 1, $row['nama_barang'], $row['nama_kategori'] ?? '-', $row['nama_ruangan'] ?? 'Gudang/Stok', (int) $row['jumlah'], $row['satuan'] ?? '-', $row['tanggal_masuk'] ?? '-', $row['keterangan'] ?? '-'];
    }
    $xml = static function ($value): string {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $value);
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    };
    $columns = ['A','B','C','D','E','F','G','H'];
    $headers = ['NO','NAMA BARANG','KATEGORI','LOKASI','JUMLAH','SATUAN','TANGGAL MASUK','KETERANGAN'];
    $header_row = 5;
    $last_data_row = $header_row + count($rows);
    $signature_row = $last_data_row + 3;
    $signature_name_row = $signature_row + 6;
    $logo = file_get_contents(__DIR__ . '/assets/img/logotb.png');
    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><dimension ref="A1:H' . $signature_name_row . '"/><sheetData>';
    $sheet .= '<row r="1" ht="30"><c r="A1" t="inlineStr" s="3"><is><t>' . $xml('SMK TARUNA BANGSA') . '</t></is></c></row><row r="2" ht="28"><c r="A2" t="inlineStr" s="4"><is><t>' . $xml('SISTEM INVENTARIS SARPRAS') . '</t></is></c></row><row r="3" ht="18"><c r="A3" t="inlineStr" s="5"><is><t>' . $xml('Jl. Lingkar Utara Bekasi (Kaliabang Tengah) Kec. Bekasi Utara.') . '</t></is></c></row><row r="4" ht="18"><c r="A4" t="inlineStr" s="5"><is><t>' . $xml('Kode Bekasi 17122') . '</t></is></c></row><row r="5">';
    foreach ($headers as $i => $header) $sheet .= '<c r="' . $columns[$i] . $header_row . '" t="inlineStr" s="1"><is><t>' . $xml($header) . '</t></is></c>';
    $sheet .= '</row>';
    foreach ($rows as $row_index => $row) {
        $excel_row = $row_index + $header_row + 1;
        $sheet .= '<row r="' . $excel_row . '">';
        foreach ($row as $i => $value) {
            $is_number = $i === 0 || $i === 4;
            $sheet .= '<c r="' . $columns[$i] . $excel_row . '" s="2"' . ($is_number ? '' : ' t="inlineStr"') . '>' . ($is_number ? '<v>' . (int) $value . '</v>' : '<is><t>' . $xml($value) . '</t></is>') . '</c>';
        }
        $sheet .= '</row>';
    }
    $sheet .= '<row r="' . $signature_row . '"><c r="A' . $signature_row . '" t="inlineStr" s="3"><is><t>Mengetahui,</t></is></c></row><row r="' . ($signature_row + 1) . '"><c r="A' . ($signature_row + 1) . '" t="inlineStr" s="5"><is><t>Wakil Kepala Sekolah</t></is></c><c r="E' . ($signature_row + 1) . '" t="inlineStr" s="5"><is><t>Kepala Sekolah</t></is></c></row><row r="' . $signature_name_row . '"><c r="A' . $signature_name_row . '" t="inlineStr" s="6"><is><t>Koderi, S.T</t></is></c><c r="E' . $signature_name_row . '" t="inlineStr" s="6"><is><t>Dody Suhendar, S.Pd.</t></is></c></row></sheetData><autoFilter ref="A5:H' . $last_data_row . '"/><mergeCells count="9"><mergeCell ref="A1:H1"/><mergeCell ref="A2:H2"/><mergeCell ref="A3:H3"/><mergeCell ref="A4:H4"/><mergeCell ref="A' . $signature_row . ':H' . $signature_row . '"/><mergeCell ref="A' . ($signature_row + 1) . ':D' . ($signature_row + 1) . '"/><mergeCell ref="E' . ($signature_row + 1) . ':H' . ($signature_row + 1) . '"/><mergeCell ref="A' . $signature_name_row . ':D' . $signature_name_row . '"/><mergeCell ref="E' . $signature_name_row . ':H' . $signature_name_row . '"/></mergeCells><drawing r:id="rId1"/></worksheet>';
    $content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/></Types>';
    $root_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Laporan BHP" sheetId="1" r:id="rId1"/></sheets></workbook>';
    $workbook_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    $sheet_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/></Relationships>';
    $drawing = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><xdr:oneCellAnchor><xdr:from><xdr:col>0</xdr:col><xdr:colOff>180000</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>90000</xdr:rowOff></xdr:from><xdr:ext cx="950000" cy="950000"/><xdr:pic><xdr:nvPicPr><xdr:cNvPr id="1" name="Logo"/><xdr:cNvPicPr/></xdr:nvPicPr><xdr:blipFill><a:blip r:embed="rId1" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill><xdr:spPr><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:oneCellAnchor></xdr:wsDr>';
    $drawing_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/logotb.png"/></Relationships>';
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="7"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="24"/><name val="Times New Roman"/></font><font><b/><sz val="20"/><name val="Times New Roman"/></font><font><sz val="10"/><name val="Times New Roman"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF548235"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border/><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="7"><xf/><xf fontId="1" fillId="2" borderId="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf borderId="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf fontId="3"><alignment horizontal="center" vertical="center"/></xf><xf fontId="4"><alignment horizontal="center" vertical="center"/></xf><xf fontId="5"><alignment horizontal="center" vertical="center"/></xf><xf fontId="6"><alignment horizontal="center" vertical="center"/></xf></cellXfs></styleSheet>';
    $temp = tempnam(sys_get_temp_dir(), 'sivenpras_bhp_'); $zip = new ZipArchive(); $zip->open($temp, ZipArchive::OVERWRITE); $zip->addFromString('[Content_Types].xml', $content_types); $zip->addFromString('_rels/.rels', $root_rels); $zip->addFromString('xl/workbook.xml', $workbook); $zip->addFromString('xl/_rels/workbook.xml.rels', $workbook_rels); $zip->addFromString('xl/worksheets/sheet1.xml', $sheet); $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', $sheet_rels); $zip->addFromString('xl/styles.xml', $styles); $zip->addFromString('xl/drawings/drawing1.xml', $drawing); $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels', $drawing_rels); $zip->addFromString('xl/media/logotb.png', $logo); $zip->close();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); header('Content-Disposition: attachment; filename="Laporan_BHP_SIVENPRAS_' . date('Y-m-d') . '.xlsx"'); readfile($temp); unlink($temp); exit;
}

$q_kategori = mysqli_query($koneksi, 'SELECT id_kategori, nama_kategori FROM kategori_bhp ORDER BY nama_kategori ASC');
$q_ruangan = mysqli_query($koneksi, 'SELECT id_ruangan, nama_ruangan FROM ruangan ORDER BY nama_ruangan ASC');
include 'includes/header.php';
?>
<div class="dashboard-container">
    <div class="page-header no-print"><h1>Laporan Barang Habis Pakai</h1><p>Data stok dan distribusi BHP berdasarkan kategori, lokasi, dan tanggal masuk.</p></div>
    <div class="widget-card no-print report-filter-card">
        <form method="GET" action="laporan-bhp.php" class="filter-grid">
            <div class="filter-item"><label>Kategori BHP</label><select name="kategori" class="filter-select"><option value="0">Semua Kategori</option><?php while ($item = mysqli_fetch_assoc($q_kategori)): ?><option value="<?= (int) $item['id_kategori']; ?>" <?= $kategori_id === (int) $item['id_kategori'] ? 'selected' : ''; ?>><?= htmlspecialchars($item['nama_kategori']); ?></option><?php endwhile; ?></select></div>
            <div class="filter-item"><label>Lokasi</label><select name="ruangan" class="filter-select"><option value="0">Semua Lokasi</option><?php while ($item = mysqli_fetch_assoc($q_ruangan)): ?><option value="<?= (int) $item['id_ruangan']; ?>" <?= $ruangan_id === (int) $item['id_ruangan'] ? 'selected' : ''; ?>><?= htmlspecialchars($item['nama_ruangan']); ?></option><?php endwhile; ?></select></div>
            <div class="filter-item"><label>Tahun Masuk</label><select name="tahun_masuk" class="filter-select"><option value="0">Semua Tahun</option><?php for ($year = date('Y'); $year >= 2000; $year--): ?><option value="<?= $year; ?>" <?= $tahun_masuk === $year ? 'selected' : ''; ?>><?= $year; ?></option><?php endfor; ?></select></div>
            <div class="filter-item"><label>Bulan Masuk</label><select name="bulan_masuk" class="filter-select"><option value="0">Semua Bulan</option><?php foreach ([1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'] as $number => $name): ?><option value="<?= $number; ?>" <?= $bulan_masuk === $number ? 'selected' : ''; ?>><?= $name; ?></option><?php endforeach; ?></select></div>
            <div class="filter-item"><label>Tanggal Masuk</label><select name="tanggal_masuk" class="filter-select"><option value="0">Semua Tanggal</option><?php for ($day = 1; $day <= 31; $day++): ?><option value="<?= $day; ?>" <?= $tanggal_masuk === $day ? 'selected' : ''; ?>><?= $day; ?></option><?php endfor; ?></select></div>
            <div class="filter-actions"><a href="laporan-bhp.php?tahun_masuk=0" class="btn-reset">Reset</a></div>
        </form>
        <div class="report-actions"><a class="btn-export-excel" href="laporan-bhp.php?action=export_excel&kategori=<?= $kategori_id; ?>&ruangan=<?= $ruangan_id; ?>&tahun_masuk=<?= $tahun_masuk; ?>&bulan_masuk=<?= $bulan_masuk; ?>&tanggal_masuk=<?= $tanggal_masuk; ?>"><i class="bi bi-file-earmark-excel"></i> Export Excel</a><button type="button" onclick="window.print()" class="btn-print"><i class="bi bi-printer"></i> Cetak / PDF</button></div>
    </div>
    <div class="widget-card table-wrapper"><table class="custom-table print-table"><thead><tr><th>NO</th><th>NAMA BARANG</th><th>KATEGORI</th><th>LOKASI</th><th>JUMLAH</th><th>SATUAN</th><th>TANGGAL MASUK</th><th>KETERANGAN</th></tr></thead><tbody><?php if ($result && mysqli_num_rows($result) > 0): $no=1; while ($row=mysqli_fetch_assoc($result)): ?><tr><td><?= $no++; ?></td><td><strong><?= htmlspecialchars($row['nama_barang']); ?></strong></td><td><?= htmlspecialchars($row['nama_kategori'] ?? '-'); ?></td><td><?= htmlspecialchars($row['nama_ruangan'] ?? 'Gudang/Stok'); ?></td><td><?= (int) $row['jumlah']; ?></td><td><?= htmlspecialchars($row['satuan'] ?? '-'); ?></td><td><?= htmlspecialchars($row['tanggal_masuk'] ?? '-'); ?></td><td><?= htmlspecialchars($row['keterangan'] ?: '-'); ?></td></tr><?php endwhile; else: ?><tr><td colspan="8">Data BHP tidak ditemukan.</td></tr><?php endif; ?></tbody></table></div>
</div>
<style>
    .report-filter-card { margin-bottom:20px; padding:20px; }
    .filter-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; align-items:end; }
    .filter-item { display:flex; flex-direction:column; gap:5px; }
    .filter-item label { color:#475569; font-size:12px; font-weight:700; }
    .filter-select { width:100%; box-sizing:border-box; padding:10px 12px; border:1px solid #cbd5e1; border-radius:8px; background:#fff; color:#0f172a; font:inherit; }
    .filter-actions { display:flex; align-items:end; }
    .btn-reset, .btn-export-excel, .btn-print { display:inline-flex; align-items:center; justify-content:center; gap:7px; min-height:40px; box-sizing:border-box; padding:10px 15px; border-radius:8px; text-decoration:none; font:inherit; font-size:13px; font-weight:600; cursor:pointer; }
    .btn-reset { color:#475569; border:1px solid #cbd5e1; background:#fff; }
    .report-actions { width:100%; display:flex; flex-wrap:wrap; justify-content:flex-end; gap:10px; margin-top:16px; padding-top:16px; border-top:1px solid #f1f5f9; }
    .btn-export-excel { color:#fff; background:#15803d; border:1px solid #15803d; }
    .btn-print { color:#fff; background:#334155; border:1px solid #334155; }
    .btn-reset:hover, .btn-export-excel:hover, .btn-print:hover { filter:brightness(.96); }
    .custom-table { width:100%; border-collapse:collapse; }
    .custom-table th, .custom-table td { padding:13px 16px; border-bottom:1px solid #e2e8f0; text-align:left; font-size:14px; }
    .custom-table th { background:#f8fafc; color:#64748b; font-size:12px; text-transform:uppercase; }
    @media print {
        .no-print, .sidebar, header, nav { display:none !important; }
        .dashboard-container { padding:0 !important; margin:0 !important; }
        .widget-card { box-shadow:none !important; border:none !important; padding:0 !important; }
        .print-table th, .print-table td { border:1px solid #000 !important; padding:6px 8px !important; font-size:11px !important; }
    }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const filterForm = document.querySelector('.report-filter-card form');
    if (!filterForm) return;
    filterForm.querySelectorAll('select').forEach(function (select) {
        select.addEventListener('change', function () {
            filterForm.submit();
        });
    });
});
</script>
<?php include 'includes/footer.php'; ?>