import csv
import zipfile
import os
from datetime import datetime, timezone
import sys
import html

# Test data
csv_file = "test_data.csv"
output_file = "test_output.xlsx"

# Create test CSV
with open(csv_file, 'w', encoding='utf-8') as f:
    f.write("NO,KODE BARANG,NAMA BARANG,KATEGORI,RUANGAN,KONDISI,DESKRIPSI\n")
    f.write('"1","KRSRPL","Kursi RPL","Furnitur","101","Baik",""\n')

# Read CSV data
with open(csv_file, 'r', encoding='utf-8') as f:
    reader = csv.reader(f)
    rows = list(reader)

print(f"Rows loaded: {len(rows)}")

# Create XLSX structure
try:
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
                sheet += f'<c r="{col}{row_idx}" t="inlineStr"><is><t>{cell_esc}</t></is></c>'
            sheet += '</row>'
        
        sheet += '</sheetData></worksheet>'
        zf.writestr('xl/worksheets/sheet1.xml', sheet)
    
    print("OK")
except Exception as e:
    print(f"ERROR: {e}")
    sys.exit(1)
