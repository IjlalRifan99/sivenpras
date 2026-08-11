<?php
if (!isset($page_title)) $page_title = 'SIVENPRAS';
if (!isset($breadcrumb)) $breadcrumb = 'Dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIVENPRAS - <?= htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="main-content">
    <header class="topbar">
        <div class="breadcrumb">
            SIVENPRAS-TB &rsaquo; <span><?= htmlspecialchars($breadcrumb); ?></span>
        </div>

        <div class="topbar-actions">
            <?php if (($active_page ?? '') !== 'ruangan' && ($active_page ?? '') !== 'tambah'): ?>
                <a href="tambah-barang.php" class="btn-primary" style="text-decoration: none;">
                    <i class="ph-bold ph-plus"></i>
                    Tambah Barang
                </a>
            <?php endif; ?>

            <div class="avatar">AD</div>
        </div>
    </header>