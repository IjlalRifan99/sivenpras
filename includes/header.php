<?php
if (!isset($page_title)) $page_title = 'SIVENPRAS';
if (!isset($breadcrumb)) $breadcrumb = 'Dashboard';
$user_role = strtolower($_SESSION['role'] ?? '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIVENPRAS - <?= htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/media.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="main-content">
    <header class="topbar">
        <div style="display:flex; align-items:center; gap:12px;">
            <button id="sidebarToggleBtn" type="button" aria-label="Toggle sidebar" style="border:none; background:#e2e8f0; color:#0f172a; width:38px; height:38px; border-radius:10px; cursor:pointer; display:none; align-items:center; justify-content:center; font-size:18px;">
                <i class="bi bi-list"></i>
            </button>
            <div class="breadcrumb">
                SIVENPRAS-TB &rsaquo; <span><?= htmlspecialchars($breadcrumb); ?></span>
            </div>
        </div>
        
        <?php if ($user_role !== 'kepala_sekolah'): ?>
        <div class="topbar-actions">
            <?php if (($active_page ?? '') !== 'ruangan' && ($active_page ?? '') !== 'tambah' && ($active_page ?? '') !== 'laporan' && ($active_page ?? '') !== 'laporan-bhp' && ($active_page ?? '') !== 'bhp'): ?>
                <a href="tambah-barang.php" class="btn-primary" style="text-decoration: none;">
                    <i class="bi bi-plus-lg"></i>
                    Tambah Barang
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </header>