<?php

$source = __DIR__ . '/../DOKUMENTASI_SIVENPRAS_TB.md';
$target = __DIR__ . '/../DOKUMENTASI_SIVENPRAS_TB.doc';
$markdown = file_get_contents($source);

if ($markdown === false) {
    fwrite(STDERR, "Gagal membaca naskah dokumentasi.\n");
    exit(1);
}

$lines = preg_split('/\R/', $markdown);
$html = [];
$html[] = '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>Dokumentasi SIVENPRAS-TB</title>';
$html[] = '<style>body{font-family:Arial,sans-serif;color:#172033;line-height:1.45;margin:40px}h1{color:#0f766e;font-size:24pt;border-bottom:2px solid #0f766e;padding-bottom:8px}h2{color:#0f766e;font-size:16pt;margin-top:24px}h3{color:#334155;font-size:13pt;margin-top:18px}table{border-collapse:collapse;width:100%;margin:10px 0 18px}th,td{border:1px solid #cbd5e1;padding:7px;vertical-align:top}th{background:#e2e8f0}code,pre{font-family:Consolas,monospace}pre{background:#f1f5f9;padding:10px;white-space:pre-wrap}blockquote{border-left:4px solid #94a3b8;padding-left:12px;color:#475569}</style></head><body>';

$in_code = false;
$in_table = false;
$list_type = null;

$close_list = function () use (&$html, &$list_type): void {
    if ($list_type !== null) {
        $html[] = '</' . $list_type . '>';
        $list_type = null;
    }
};

$close_table = function () use (&$html, &$in_table): void {
    if ($in_table) {
        $html[] = '</tbody></table>';
        $in_table = false;
    }
};

foreach ($lines as $line) {
    if (trim($line) === '```') {
        $close_list();
        $close_table();
        $in_code = !$in_code;
        $html[] = $in_code ? '<pre>' : '</pre>';
        continue;
    }

    if ($in_code) {
        $html[] = htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . "\n";
        continue;
    }

    if (trim($line) === '') {
        $close_list();
        $close_table();
        continue;
    }

    if (preg_match('/^\|(.+)\|$/', $line)) {
        $cells = array_map('trim', explode('|', trim($line, '|')));
        if (count($cells) > 0 && preg_match('/^-+$/', str_replace([' ', ':'], '', $cells[0]))) {
            continue;
        }
        $close_list();
        if (!$in_table) {
            $html[] = '<table><thead><tr>';
            foreach ($cells as $cell) {
                $html[] = '<th>' . htmlspecialchars($cell, ENT_QUOTES, 'UTF-8') . '</th>';
            }
            $html[] = '</tr></thead><tbody>';
            $in_table = true;
        } else {
            $html[] = '<tr>';
            foreach ($cells as $cell) {
                $html[] = '<td>' . htmlspecialchars($cell, ENT_QUOTES, 'UTF-8') . '</td>';
            }
            $html[] = '</tr>';
        }
        continue;
    }

    $close_table();
    if (preg_match('/^(#{1,3})\s+(.+)$/', $line, $match)) {
        $close_list();
        $level = strlen($match[1]);
        $html[] = '<h' . $level . '>' . htmlspecialchars($match[2], ENT_QUOTES, 'UTF-8') . '</h' . $level . '>';
        continue;
    }

    if (preg_match('/^[-*]\s+(.+)$/', $line, $match)) {
        if ($list_type !== 'ul') {
            $close_list();
            $html[] = '<ul>';
            $list_type = 'ul';
        }
        $html[] = '<li>' . htmlspecialchars($match[1], ENT_QUOTES, 'UTF-8') . '</li>';
        continue;
    }

    if (preg_match('/^\d+\.\s+(.+)$/', $line, $match)) {
        if ($list_type !== 'ol') {
            $close_list();
            $html[] = '<ol>';
            $list_type = 'ol';
        }
        $html[] = '<li>' . htmlspecialchars($match[1], ENT_QUOTES, 'UTF-8') . '</li>';
        continue;
    }

    $close_list();
    if (preg_match('/^>\s+(.+)$/', $line, $match)) {
        $html[] = '<blockquote>' . htmlspecialchars($match[1], ENT_QUOTES, 'UTF-8') . '</blockquote>';
    } else {
        $html[] = '<p>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</p>';
    }
}

$close_list();
$close_table();
$html[] = '</body></html>';

if (file_put_contents($target, implode("\n", $html)) === false) {
    fwrite(STDERR, "Gagal menulis dokumen output.\n");
    exit(1);
}

echo "Dokumen dibuat: {$target}\n";