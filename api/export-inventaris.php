<?php
session_start();
include __DIR__ . '/../config/koneksi.php';

if (!isset($_SESSION['login'])) {
    header('Location: ../login.php');
    exit;
}

$ruangan_ids = $_POST['ruangan'] ?? [];
$ruangan_ids = array_values(array_filter(array_map('intval', (array)$ruangan_ids), static function ($id) {
    return $id > 0;
}));

if (!$ruangan_ids) {
    header('Location: ../daftar-inventaris.php?error=Pilih minimal satu ruangan');
    exit;
}

$ruangan_placeholder = implode(',', $ruangan_ids);
$query = "
    SELECT
        i.barcode,
        b.nama_barang,
        k.nama_kategori,
        r.nama_ruangan,
        i.kondisi,
        i.keterangan,
        b.deskripsi
    FROM inventaris i
    INNER JOIN barang b ON i.barang_id = b.id_barang
    LEFT JOIN kategori k ON b.kategori_id = k.id_kategori
    LEFT JOIN ruangan r ON i.ruangan_id = r.id_ruangan
    WHERE i.ruangan_id IN ($ruangan_placeholder)
    ORDER BY r.nama_ruangan ASC, b.nama_barang ASC, i.barcode ASC
";
$result = mysqli_query($koneksi, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    header('Location: ../daftar-inventaris.php?error=Tidak ada data untuk ruangan yang dipilih');
    exit;
}

$rows = [];
while ($row = mysqli_fetch_assoc($result)) {
    $rows[] = [
        count($rows) + 1,
        $row['barcode'] ?? '-',
        $row['nama_barang'] ?? '-',
        $row['nama_kategori'] ?? '-',
        $row['nama_ruangan'] ?? '-',
        ucwords($row['kondisi'] ?? '-'),
        $row['keterangan'] ?? $row['deskripsi'] ?? '-'
    ];
}

$xml = static function ($value): string {
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string)$value);
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
};
$columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
$headers = ['NO', 'BARCODE', 'NAMA BARANG', 'KATEGORI', 'RUANGAN', 'KONDISI', 'KETERANGAN'];
$last_row = count($rows) + 1;

$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:G' . $last_row . '"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="1" width="8" customWidth="1"/><col min="2" max="2" width="20" customWidth="1"/><col min="3" max="4" width="28" customWidth="1"/><col min="5" max="6" width="18" customWidth="1"/><col min="7" max="7" width="35" customWidth="1"/></cols><sheetData><row r="1">';
foreach ($headers as $index => $header) {
    $sheet .= '<c r="' . $columns[$index] . '1" t="inlineStr" s="1"><is><t>' . $xml($header) . '</t></is></c>';
}
$sheet .= '</row>';
foreach ($rows as $row_index => $row) {
    $excel_row = $row_index + 2;
    $sheet .= '<row r="' . $excel_row . '">';
    foreach ($row as $column_index => $value) {
        if (is_int($value)) {
            $sheet .= '<c r="' . $columns[$column_index] . $excel_row . '"><v>' . $value . '</v></c>';
        } else {
            $sheet .= '<c r="' . $columns[$column_index] . $excel_row . '" t="inlineStr"><is><t>' . $xml($value) . '</t></is></c>';
        }
    }
    $sheet .= '</row>';
}
$sheet .= '</sheetData><autoFilter ref="A1:G' . $last_row . '"/></worksheet>';

$content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>';
$root_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>';
$workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Inventaris" sheetId="1" r:id="rId1"/></sheets></workbook>';
$workbook_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0D9488"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles><dxfs count="0"/><tableStyles count="0"/></styleSheet>';
$core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>Daftar Inventaris</dc:title><dc:creator>SIVENPRAS-TB</dc:creator></cp:coreProperties>';

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
$filename = 'Inventaris_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($temp_file));
header('Cache-Control: no-cache, no-store, must-revalidate');
readfile($temp_file);
unlink($temp_file);
exit;
