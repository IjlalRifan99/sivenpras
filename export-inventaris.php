<?php
session_start();
include 'config/koneksi.php';

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

// Cek apakah ada ruangan yang dipilih
if (!isset($_POST['ruangan']) || empty($_POST['ruangan'])) {
    header("Location: daftar-inventaris.php?error=Pilih minimal satu ruangan");
    exit;
}

$ruangan_ids = $_POST['ruangan'];
// Sanitize input
$ruangan_ids = array_map(function ($id) {
    return (int)$id;
}, $ruangan_ids);

$ruangan_placeholder = implode(',', $ruangan_ids);

// Query data inventaris berdasarkan ruangan yang dipilih
$query = "
    SELECT 
        i.id_inventaris,
        b.id_barang,
        b.nama_barang,
        k.nama_kategori,
        r.nama_ruangan,
        i.kondisi,
        i.barcode,
        i.keterangan,
        b.deskripsi
    FROM inventaris i
    JOIN barang b ON i.barang_id = b.id_barang
    LEFT JOIN kategori k ON b.kategori_id = k.id_kategori
    LEFT JOIN ruangan r ON i.ruangan_id = r.id_ruangan
    WHERE i.ruangan_id IN ($ruangan_placeholder)
    ORDER BY r.nama_ruangan, b.nama_barang, i.barcode ASC
";

$result = mysqli_query($koneksi, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    header("Location: daftar-inventaris.php?error=Tidak ada data untuk ruangan yang dipilih");
    exit;
}

// Collect data
$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

mysqli_close($koneksi);

// Generate XLSX using Python
generateXLSXFile($data);

function generateXLSXFile($data) {
    $filename = 'Inventaris_' . date('Ymd_His') . '.xlsx';
    $temp_dir = sys_get_temp_dir();
    $csv_file = $temp_dir . '/export_' . uniqid() . '.csv';
    $xlsx_file = $temp_dir . '/' . uniqid() . '.xlsx';
    
    // Generate CSV content
    $csv_content = "NO,BARCODE,NAMA BARANG,KATEGORI,RUANGAN,KONDISI,DESKRIPSI\n";
    foreach ($data as $idx => $row) {
        $csv_row = [
            $idx + 1,
            $row['barcode'],
            $row['nama_barang'],
            $row['nama_kategori'] ?? '-',
            $row['nama_ruangan'] ?? '-',
            ucfirst($row['kondisi']),
            $row['deskripsi'] ?? ''
        ];
        // Properly escape CSV
        $csv_content .= implode(',', array_map(function($val) {
            return '"' . str_replace('"', '""', $val) . '"';
        }, $csv_row)) . "\n";
    }
    
    // Write CSV file
    if (file_put_contents($csv_file, $csv_content) === false) {
        http_response_code(500);
        echo "Error: Failed to create temporary CSV file";
        exit;
    }
    
    // Find Python executable
    $python_paths = [
        'C:\\laragon\\bin\\python\\python-3.13\\python.exe',
        'C:\\laragon\\bin\\python\\python-3.12\\python.exe',
        'C:\\laragon\\bin\\python\\python-3.11\\python.exe',
        'C:\\Python313\\python.exe',
        'C:\\Python312\\python.exe',
        'python'
    ];
    
    $python_exe = null;
    foreach ($python_paths as $path) {
        if (file_exists($path) || $path === 'python') {
            $python_exe = $path;
            break;
        }
    }
    
    if (!$python_exe) {
        unlink($csv_file);
        http_response_code(500);
        echo "Error: Python not found";
        exit;
    }
    
    // Get script path
    $script_path = __DIR__ . '/generate_xlsx.py';
    if (!file_exists($script_path)) {
        unlink($csv_file);
        http_response_code(500);
        echo "Error: Python script not found";
        exit;
    }
    
    // Execute Python script
    $command = '"' . $python_exe . '" "' . $script_path . '" "' . $csv_file . '" "' . $xlsx_file . '"';
    
    // Execute and capture output
    $output = [];
    $return_var = 0;
    exec($command, $output, $return_var);
    
    // Clean up CSV file
    unlink($csv_file);
    
    if ($return_var !== 0 || !file_exists($xlsx_file)) {
        http_response_code(500);
        echo "Error: Failed to generate XLSX file\n";
        if (!empty($output)) {
            echo implode("\n", $output);
        }
        exit;
    }
    
    // Send file to client
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Length: ' . filesize($xlsx_file));
    
    readfile($xlsx_file);
    
    // Clean up XLSX file
    unlink($xlsx_file);
    exit;
}
?>
    $filename = 'Inventaris_' . date('Ymd_His') . '.xlsx';
    
    // Check if ZipArchive is available
    if (!class_exists('ZipArchive')) {
        // Fallback ke CSV jika ZipArchive tidak tersedia
        fallbackToCSV($data, $filename);
        return;
    }
    
    // Create temporary directory
    $temp_dir = sys_get_temp_dir() . '/' . uniqid('xlsx_');
    mkdir($temp_dir);
    mkdir($temp_dir . '/xl');
    mkdir($temp_dir . '/xl/worksheets');
    mkdir($temp_dir . '/_rels');
    mkdir($temp_dir . '/xl/_rels');
    mkdir($temp_dir . '/docProps');  // Tambahkan ini
    
    // Create [Content_Types].xml
    $content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
    <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>';
    file_put_contents($temp_dir . '/[Content_Types].xml', $content_types);
    
    // Create .rels
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>';
    file_put_contents($temp_dir . '/_rels/.rels', $rels);
    
    // Create workbook.xml.rels
    $workbook_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';
    file_put_contents($temp_dir . '/xl/_rels/workbook.xml.rels', $workbook_rels);
    
    // Create styles.xml
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font></fonts>
    <fills><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor theme="3"/></patternFill></fill></fills>
    <borders><border><left/><right/><top/><bottom/><diagonal/></border></borders>
    <cellStyleXfs><xf borderId="0" fillId="0" fontId="0" numFmtId="0"/></cellStyleXfs>
    <cellXfs><xf borderId="0" fillId="0" fontId="0" numFmtId="0" xfId="0"/><xf borderId="0" fillId="2" fontId="0" numFmtId="0" xfId="0" applyFill="1"/></cellXfs>
    <cellStyles><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
</styleSheet>';
    file_put_contents($temp_dir . '/xl/styles.xml', $styles);
    
    // Create core.xml
    $core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/officeDocument/2006/custom-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <dc:title>LAPORAN INVENTARIS</dc:title>
    <dc:creator>SIVENPRAS</dc:creator>
    <dcterms:created xsi:type="dcterms:W3CDTF">' . date('Y-m-d\TH:i:s\Z') . '</dcterms:created>
</cp:coreProperties>';
    file_put_contents($temp_dir . '/docProps/core.xml', $core);
    
    // Create sheet1.xml with data
    $sheet_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheetData>';
    
    // Header row
    $sheet_xml .= '<row r="1">';
    $headers = ['NO', 'BARCODE', 'NAMA BARANG', 'KATEGORI', 'RUANGAN', 'KONDISI', 'DESKRIPSI'];
    $col_letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
    
    foreach ($headers as $idx => $header) {
        $sheet_xml .= '<c r="' . $col_letters[$idx] . '1" t="inlineStr" s="1"><is><t>' . htmlspecialchars($header) . '</t></is></c>';
    }
    $sheet_xml .= '</row>';
    
    // Data rows
    $row_num = 2;
    foreach ($data as $idx => $row) {
        $sheet_xml .= '<row r="' . $row_num . '">';
        $sheet_xml .= '<c r="A' . $row_num . '"><v>' . ($idx + 1) . '</v></c>';
        $sheet_xml .= '<c r="B' . $row_num . '" t="inlineStr"><is><t>' . htmlspecialchars($row['barcode']) . '</t></is></c>';
        $sheet_xml .= '<c r="C' . $row_num . '" t="inlineStr"><is><t>' . htmlspecialchars($row['nama_barang']) . '</t></is></c>';
        $sheet_xml .= '<c r="D' . $row_num . '" t="inlineStr"><is><t>' . htmlspecialchars($row['nama_kategori'] ?? '-') . '</t></is></c>';
        $sheet_xml .= '<c r="E' . $row_num . '" t="inlineStr"><is><t>' . htmlspecialchars($row['nama_ruangan'] ?? '-') . '</t></is></c>';
        $sheet_xml .= '<c r="F' . $row_num . '" t="inlineStr"><is><t>' . htmlspecialchars(ucfirst($row['kondisi'])) . '</t></is></c>';
        $sheet_xml .= '<c r="G' . $row_num . '" t="inlineStr"><is><t>' . htmlspecialchars($row['deskripsi'] ?? '-') . '</t></is></c>';
        $sheet_xml .= '</row>';
        $row_num++;
    }
    
    $sheet_xml .= '</sheetData></worksheet>';
    file_put_contents($temp_dir . '/xl/worksheets/sheet1.xml', $sheet_xml);
    
    // Create workbook.xml
    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <fileVersion appName="xl" lastEdited="4" lowestEdited="4" rupBuild="4505"/>
    <workbookPr defaultTheme="1"/>
    <sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>
</workbook>';
    file_put_contents($temp_dir . '/xl/workbook.xml', $workbook);
    
    // Create ZIP file
    $zip = new ZipArchive();
    $zip_path = sys_get_temp_dir() . '/' . $filename;
    
    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        removeDir($temp_dir);
        exit('Failed to create zip');
    }
    
    // Add files to ZIP
    addDirToZip($temp_dir, $zip);
    $zip->close();
    
    // Send file to client
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($zip_path));
    readfile($xlsx_file);
    
    // Clean up XLSX file
    unlink($xlsx_file);
    exit;
}
?>
    }
}

function removeDir($dir) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') continue;
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            removeDir($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

function generateXLSXPython($data) {
    $filename = 'Inventaris_' . date('Ymd_His') . '.xlsx';
    $temp_dir = sys_get_temp_dir() . '/' . uniqid('xlsx_');
    mkdir($temp_dir);
    
    // Create data directory
    mkdir($temp_dir . '/data');
    
    // Generate header + data in CSV format first
    $csv_content = "NO,KODE BARANG,NAMA BARANG,KATEGORI,RUANGAN,KONDISI,DESKRIPSI\n";
    foreach ($data as $idx => $row) {
        $csv_row = [
            $idx + 1,
            $row['barcode'],
            $row['nama_barang'],
            $row['nama_kategori'] ?? '-',
            $row['nama_ruangan'] ?? '-',
            ucfirst($row['kondisi']),
            $row['deskripsi'] ?? '-'
        ];
        $csv_content .= implode(',', array_map(function($val) {
            return '"' . str_replace('"', '""', $val) . '"';
        }, $csv_row)) . "\n";
    }
    
    // Save CSV temporarily
    file_put_contents($temp_dir . '/data.csv', $csv_content);
    
    // Create Python script to generate XLSX
    $python_script = <<<'PYTHON'
import csv
import zipfile
import os
from datetime import datetime
import sys

csv_file = sys.argv[1]
output_file = sys.argv[2]

# Read CSV data
with open(csv_file, 'r', encoding='utf-8') as f:
    reader = csv.reader(f)
    rows = list(reader)

# Create XLSX structure
with zipfile.ZipFile(output_file, 'w', zipfile.ZIP_DEFLATED) as zf:
    # [Content_Types].xml
    content_types = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
    <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>'''
    zf.writestr('[Content_Types].xml', content_types)
    
    # _rels/.rels
    rels = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>'''
    zf.writestr('_rels/.rels', rels)
    
    # xl/_rels/workbook.xml.rels
    workbook_rels = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>'''
    zf.writestr('xl/_rels/workbook.xml.rels', workbook_rels)
    
    # xl/styles.xml
    styles = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font></fonts>
    <fills><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>
    <borders><border><left/><right/><top/><bottom/><diagonal/></border></borders>
    <cellStyleXfs><xf borderId="0" fillId="0" fontId="0" numFmtId="0"/></cellStyleXfs>
    <cellXfs><xf borderId="0" fillId="0" fontId="0" numFmtId="0" xfId="0"/></cellXfs>
    <cellStyles><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
</styleSheet>'''
    zf.writestr('xl/styles.xml', styles)
    
    # docProps/core.xml
    core = f'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/officeDocument/2006/custom-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <dc:title>LAPORAN INVENTARIS</dc:title>
    <dc:creator>SIVENPRAS</dc:creator>
    <dcterms:created xsi:type="dcterms:W3CDTF">{datetime.utcnow().isoformat()}Z</dcterms:created>
</cp:coreProperties>'''
    zf.writestr('docProps/core.xml', core)
    
    # xl/workbook.xml
    workbook = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>
</workbook>'''
    zf.writestr('xl/workbook.xml', workbook)
    
    # xl/worksheets/sheet1.xml with data
    sheet = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <sheetData>'''
    
    cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G']
    for row_idx, row in enumerate(rows, 1):
        sheet += f'<row r="{row_idx}">'
        for col_idx, cell in enumerate(row):
            col = cols[col_idx] if col_idx < len(cols) else 'G'
            cell_esc = cell.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;').replace('"', '&quot;')
            if row_idx == 1:
                sheet += f'<c r="{col}{row_idx}" t="inlineStr"><is><t>{cell_esc}</t></is></c>'
            else:
                sheet += f'<c r="{col}{row_idx}" t="inlineStr"><is><t>{cell_esc}</t></is></c>'
        sheet += '</row>'
    
    sheet += '</sheetData></worksheet>'
    zf.writestr('xl/worksheets/sheet1.xml', sheet)

print("OK")
PYTHON;
    
    $script_path = $temp_dir . '/generate.py';
    file_put_contents($script_path, $python_script);
    
    // Run Python script
    $csv_path = str_replace('/', '\\', $temp_dir . '/data.csv');
    $output_path = str_replace('/', '\\', sys_get_temp_dir() . '/' . $filename);
    $script_path = str_replace('/', '\\', $script_path);
    
    // Execute Python with proper error handling
    // Try multiple Python paths (Laragon > System > Python 3.13)
    $python_paths = [
        'C:\\laragon\\bin\\python\\python-3.13\\python.exe',
        'C:\\Users\\Ijlal\\AppData\\Local\\Programs\\Python\\Python313\\python.exe',
        'C:\\Python313\\python.exe',
        'python3.13',
        'python'
    ];
    
    $python_exe = null;
    foreach ($python_paths as $path) {
        if (file_exists($path) || strpos($path, '\\') === false) {
            $python_exe = $path;
            break;
        }
    }
    
    if (!$python_exe) {
        error_log("Python not found in any expected location");
        fallbackToCSV($data, $filename);
        removeDir($temp_dir);
        return;
    }
    
    $command = "\"$python_exe\" \"$script_path\" \"$csv_path\" \"$output_path\" 2>&1";
    exec($command, $output, $return_code);
    
    // Log debug info (remove in production)
    error_log("Python exec return code: $return_code");
    error_log("Output path exists: " . (file_exists($output_path) ? 'yes' : 'no'));
    if (!empty($output)) {
        error_log("Python output: " . implode("\n", $output));
    }
    
    if ($return_code !== 0 || !file_exists($output_path)) {
        // Fallback ke CSV jika Python gagal
        error_log("Falling back to CSV generation");
        fallbackToCSV($data, $filename);
        removeDir($temp_dir);
        return;
    }
    
    // Send file
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($output_path));
    readfile($output_path);
    
    // Cleanup
    unlink($output_path);
    removeDir($temp_dir);
}

function fallbackToCSV($data, $filename) {
    // Gunakan .xlsx extension meskipun content CSV
    // Excel tetap bisa membuka CSV dengan extension .xlsx
    $csv_filename = str_replace('.xlsx', '.xlsx', $filename);
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $csv_filename . '"');
    header('Cache-Control: max-age=0');
    
    // BOM untuk UTF-8
    echo chr(0xEF) . chr(0xBB) . chr(0xBF);
    
    // Headers
    $headers = ['NO', 'KODE BARANG', 'NAMA BARANG', 'KATEGORI', 'RUANGAN', 'KONDISI', 'DESKRIPSI'];
    echo implode(',', $headers) . "\n";
    
    // Data
    foreach ($data as $idx => $row) {
        $csv_row = [
            $idx + 1,
            $row['barcode'],
            $row['nama_barang'],
            $row['nama_kategori'] ?? '-',
            $row['nama_ruangan'] ?? '-',
            ucfirst($row['kondisi']),
            $row['deskripsi'] ?? '-'
        ];
        echo implode(',', array_map(function($val) {
            return '"' . str_replace('"', '""', $val) . '"';
        }, $csv_row)) . "\n";
    }
    exit;
}
?>
