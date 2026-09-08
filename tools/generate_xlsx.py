#!/usr/bin/env python
# -*- coding: utf-8 -*-
import csv
import zipfile
import os
from datetime import datetime
import sys

def escape_xml(text):
    """Escape XML special characters"""
    if text is None:
        text = ""
    text = str(text)
    text = text.replace('&', '&amp;')
    text = text.replace('<', '&lt;')
    text = text.replace('>', '&gt;')
    text = text.replace('"', '&quot;')
    text = text.replace("'", '&apos;')
    return text

def generate_xlsx(csv_file, output_file):
    """Generate XLSX file from CSV data"""
    
    # Read CSV file
    rows = []
    with open(csv_file, 'r', encoding='utf-8') as f:
        reader = csv.reader(f)
        rows = list(reader)
    
    if not rows:
        return False
    
    # Create XLSX as ZIP archive
    with zipfile.ZipFile(output_file, 'w', zipfile.ZIP_DEFLATED) as zf:
        # 1. [Content_Types].xml
        content_types_xml = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>'''
        zf.writestr('[Content_Types].xml', content_types_xml)
        
        # 2. _rels/.rels
        rels_xml = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>'''
        zf.writestr('_rels/.rels', rels_xml)
        
        # 3. xl/workbook.xml
        workbook_xml = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <fileVersion appName="xl" lastEdited="5" lowestEdited="5" rupBuild="21224"/>
  <workbookPr defaultTheme="1"/>
  <workbookProtection workbookPassword="false"/>
  <sheets>
    <sheet name="Sheet1" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>'''
        zf.writestr('xl/workbook.xml', workbook_xml)
        
        # 4. xl/_rels/workbook.xml.rels
        workbook_rels_xml = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>'''
        zf.writestr('xl/_rels/workbook.xml.rels', workbook_rels_xml)
        
        # 5. xl/styles.xml
        styles_xml = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/><scheme val="minor"/></font>
    <font><b/><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/><scheme val="minor"/></font>
  </fonts>
  <fills count="2">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
  </fills>
  <borders count="1">
    <border><left/><right/><top/><bottom/><diagonal/></border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>
  </cellXfs>
  <cellStyles count="1">
    <cellStyle name="Normal" xfId="0" builtinId="0"/>
  </cellStyles>
  <dxfs count="0"/>
  <tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/>
</styleSheet>'''
        zf.writestr('xl/styles.xml', styles_xml)
        
        # 6. docProps/core.xml
        now = datetime.utcnow().isoformat() + 'Z'
        core_xml = f'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/officeDocument/2006/custom-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <dc:title>LAPORAN INVENTARIS</dc:title>
  <dc:creator>SIVENPRAS-TB</dc:creator>
  <dcterms:created xsi:type="dcterms:W3CDTF">{now}</dcterms:created>
</cp:coreProperties>'''
        zf.writestr('docProps/core.xml', core_xml)
        
        # 7. xl/worksheets/sheet1.xml
        sheet_xml = '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheetData>'''
        
        col_letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G']
        
        # Add rows
        for row_idx, row_data in enumerate(rows, 1):
            sheet_xml += f'\n    <row r="{row_idx}">'
            
            for col_idx, cell_value in enumerate(row_data):
                if col_idx < len(col_letters):
                    col_letter = col_letters[col_idx]
                    cell_ref = f"{col_letter}{row_idx}"
                    cell_value_esc = escape_xml(cell_value)
                    
                    # Use formula for numbers in first column (NO)
                    if row_idx > 1 and col_idx == 0:
                        sheet_xml += f'\n      <c r="{cell_ref}"><v>{cell_value_esc}</v></c>'
                    else:
                        # Determine style index (header row is styled with index 1)
                        style = ' s="1"' if row_idx == 1 else ''
                        sheet_xml += f'\n      <c r="{cell_ref}" t="inlineStr"{style}><is><t>{cell_value_esc}</t></is></c>'
            
            sheet_xml += '\n    </row>'
        
        sheet_xml += '\n  </sheetData>\n</worksheet>'
        zf.writestr('xl/worksheets/sheet1.xml', sheet_xml)
    
    return True

if __name__ == '__main__':
    if len(sys.argv) != 3:
        print("Usage: python generate_xlsx.py <csv_file> <output_xlsx>")
        sys.exit(1)
    
    csv_file = sys.argv[1]
    output_file = sys.argv[2]
    
    if not os.path.exists(csv_file):
        print(f"Error: CSV file not found: {csv_file}")
        sys.exit(1)
    
    try:
        if generate_xlsx(csv_file, output_file):
            print("OK")
            sys.exit(0)
        else:
            print("Error: Failed to generate XLSX")
            sys.exit(1)
    except Exception as e:
        print(f"Error: {e}")
        sys.exit(1)
