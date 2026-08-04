<?php
include 'config/koneksi.php';

// 1. Ambil ID Ruangan dari URL (contoh: ruangan.php?id=3)
$id_ruangan = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Jika tidak ada ID di URL, redirect ke index/dashboard
if ($id_ruangan == 0) {
    header("Location: index.php");
    exit;
}

// Update kondisi inventaris bila ada form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_kondisi'], $_POST['id_inventaris'])) {
    $id_inventaris = (int)$_POST['id_inventaris'];
    $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $_POST['kondisi'])));
    $kondisi = mysqli_real_escape_string($koneksi, $normalized);
    $allowed = ['baik', 'cukup baik', 'rusak', 'rusak parah', 'hilang'];
    $success = false;

    if (in_array($kondisi, $allowed, true)) {
        $success = (bool) mysqli_query($koneksi, "UPDATE inventaris SET kondisi = '$kondisi' WHERE id_inventaris = '$id_inventaris' AND ruangan_id = '$id_ruangan'");
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'kondisi' => $kondisi,
            'message' => $success ? 'Kondisi berhasil disimpan.' : 'Gagal menyimpan kondisi.'
        ]);
        exit;
    }

    header("Location: ruangan.php?id=$id_ruangan");
    exit;
}

// 2. Ambil Informasi Ruangan Aktif
$q_detail_ruangan = mysqli_query($koneksi, "SELECT * FROM ruangan WHERE id_ruangan = '$id_ruangan'");
$d_ruangan = mysqli_fetch_assoc($q_detail_ruangan);

if (!$d_ruangan) {
    echo "<script>alert('Ruangan tidak ditemukan!'); window.location='index.php';</script>";
    exit;
}

// 3. Query untuk Sidebar Ruangan (Hitung total unit fisik per ruangan)
$q_ruangan_sidebar = mysqli_query($koneksi, "
    SELECT r.id_ruangan, r.nama_ruangan, COUNT(i.id_inventaris) AS total_barang 
    FROM ruangan r 
    LEFT JOIN inventaris i ON r.id_ruangan = i.ruangan_id 
    GROUP BY r.id_ruangan, r.nama_ruangan
");

// 4. Query Menghitung Statistik Card Atas untuk Ruangan Ini
// - Jenis Barang (Jumlah Jenis Master Barang berada di ruangan ini)
$q_stat_jenis = mysqli_query($koneksi, "SELECT COUNT(DISTINCT barang_id) AS total_jenis FROM inventaris WHERE ruangan_id = '$id_ruangan'");
$d_stat_jenis = mysqli_fetch_assoc($q_stat_jenis);
$total_jenis = $d_stat_jenis['total_jenis'] ?? 0;

// - Total Satuan (Jumlah Unit Fisik di inventaris)
$q_stat_satuan = mysqli_query($koneksi, "
    SELECT COUNT(id_inventaris) AS total_satuan 
    FROM inventaris 
    WHERE ruangan_id = '$id_ruangan'
");
$d_stat_satuan = mysqli_fetch_assoc($q_stat_satuan);
$total_satuan = $d_stat_satuan['total_satuan'] ?? 0;

// - Kondisi Baik
$q_stat_baik = mysqli_query($koneksi, "
    SELECT COUNT(id_inventaris) AS total_baik 
    FROM inventaris 
    WHERE ruangan_id = '$id_ruangan' AND kondisi = 'baik'
");
$d_stat_baik = mysqli_fetch_assoc($q_stat_baik);
$total_baik = $d_stat_baik['total_baik'] ?? 0;

// - Perlu Perhatian (Kondisi rusak atau hilang)
$q_stat_perhatian = mysqli_query($koneksi, "
    SELECT COUNT(id_inventaris) AS total_perhatian 
    FROM inventaris 
    WHERE ruangan_id = '$id_ruangan' AND kondisi != 'baik'
");
$d_stat_perhatian = mysqli_fetch_assoc($q_stat_perhatian);
$total_perhatian = $d_stat_perhatian['total_perhatian'] ?? 0;


// 5. Query Menampilkan Daftar Unit Inventaris di Ruangan
// Menerima pencarian dari form jika ada
$keyword = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, $_GET['search']) : '';

$sql_list = "
    SELECT 
        i.id_inventaris,
        i.barcode,
        i.kondisi,
        i.keterangan AS keterangan_inventaris,
        b.nama_barang,
        b.kode_barang,
        b.deskripsi,
        k.nama_kategori
    FROM inventaris i
    JOIN barang b ON i.barang_id = b.id_barang
    LEFT JOIN kategori k ON b.kategori_id = k.id_kategori
    WHERE i.ruangan_id = '$id_ruangan'
";

if (!empty($keyword)) {
    $sql_list .= " AND (i.barcode LIKE '%$keyword%' OR b.nama_barang LIKE '%$keyword%' OR k.nama_kategori LIKE '%$keyword%')";
}

$sql_list .= " ORDER BY i.barcode ASC";
$q_list = mysqli_query($koneksi, $sql_list);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIVENPRAS - Ruangan <?= htmlspecialchars($d_ruangan['nama_ruangan']); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        .card-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }
        .stat-card p {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 8px;
            font-weight: 500;
        }
        .stat-card h2 {
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
        }
        .stat-card.good h2 { color: #16a34a; }
        .stat-card.warning h2 { color: #dc2626; }
        
        .badge-kondisi {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .badge-baik { background: #dcfce7; color: #15803d; }
        .badge-cukup-baik { background: #fde047; color: #92400e; }
        .badge-rusak { background: #fb923c; color: #7c2d12; }
        .badge-rusak-parah { background: #f87171; color: #7f1d1d; }
        .badge-hilang { background: #111827; color: #ffffff; }
        
        .select-kondisi {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 10px 12px;
            background: #fff;
            color: #0f172a;
            font-size: 13px;
            min-width: 160px;
            max-width: 220px;
            cursor: pointer;
        }
        .select-baik { background: #dcfce7; color: #15803d; }
        .select-cukup-baik { background: #fde047; color: #92400e; }
        .select-rusak { background: #fb923c; color: #7c2d12; }
        .select-rusak-parah { background: #f87171; color: #7f1d1d; }
        .select-hilang { background: #111827; color: #ffffff; }
        .table-container {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 20px;
        }
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .search-box {
            display: flex;
            align-items: center;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 8px 14px;
            width: 300px;
        }
        .search-box input {
            border: none;
            background: transparent;
            outline: none;
            margin-left: 8px;
            width: 100%;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            text-align: left;
            padding: 12px;
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
        }
        td {
            padding: 14px 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: #334155;
        }
        .btn-action {
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
        }
        .btn-edit { background: #f1f5f9; color: #334155; margin-right: 4px; }
        .btn-hapus { background: #fef2f2; color: #dc2626; }
    </style>
</head>

<body>

    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <i class="ph-bold ph-archive-box"></i>
            </div>
            <div class="brand-text">
                <h2>SIVENPRAS</h2>
                <p>Sistem Inventaris Sarpras</p>
            </div>
        </div>

        <nav class="sidebar-menu">
            <div class="menu-label">Menu Utama</div>
            <a href="index.php" class="menu-item">
                <div class="menu-left"><i class="ph ph-squares-four"></i> Dashboard</div>
            </a>
            <a href="daftar-inventaris.php" class="menu-item">
                <div class="menu-left"><i class="ph ph-clipboard-text"></i> Daftar Inventaris</div>
            </a>
            <a href="tambah-barang.php" class="menu-item">
                <div class="menu-left"><i class="ph ph-plus"></i> Tambah Barang</div>
            </a>
            <a href="laporan.php" class="menu-item">
                <div class="menu-left"><i class="ph ph-chart-bar"></i> Laporan</div>
            </a>

            <div class="menu-label" style="margin-top: 30px;">
                Ruangan <i class="ph ph-caret-up" style="float: right;"></i>
            </div>

            <?php while ($r = mysqli_fetch_assoc($q_ruangan_sidebar)) { 
                $activeClass = ($r['id_ruangan'] == $id_ruangan) ? 'active' : '';
            ?>
                <a href="ruangan.php?id=<?= $r['id_ruangan']; ?>" class="menu-item <?= $activeClass; ?>">
                    <div class="menu-left"><i class="ph ph-house"></i> <?= htmlspecialchars($r['nama_ruangan']); ?></div>
                    <span class="badge"><?= $r['total_barang']; ?></span>
                </a>
            <?php } ?>
        </nav>

        <div class="sidebar-footer">
            <div class="menu-label" style="margin: 0 0 10px 0;">Sekolah</div>
            <h4>SMK TARUNA BANGSA</h4>
            <p>Tahun Ajaran 2024/2025</p>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar" style="display: flex; justify-content: space-between; align-items: center;">
            <div class="breadcrumb">
                SIVENPRAS-TB &rsaquo; Ruangan &rsaquo; <span><?= htmlspecialchars($d_ruangan['nama_ruangan']); ?></span>
            </div>
            <div>
                <a href="tambah-barang.php" class="btn-primary" style="text-decoration: none; padding: 10px 18px; border-radius: 8px; font-weight: 600; font-size: 14px; background: #0d9488; color: white;">
                    + Tambah Barang
                </a>
            </div>
        </header>

        <div class="dashboard-container" style="padding: 24px;">

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h1 style="font-size: 26px; color: #0f172a; font-weight: 700;"><?= htmlspecialchars($d_ruangan['nama_ruangan']); ?></h1>
                <a href="tambah-barang.php?id_ruangan=<?= $id_ruangan; ?>" style="background: #004d40; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600;">
                    + Tambah Barang ke Ruangan Ini
                </a>
            </div>

            <div class="card-stats-grid">
                <div class="stat-card">
                    <p>Jenis Barang</p>
                    <h2><?= $total_jenis; ?></h2>
                </div>
                <div class="stat-card">
                    <p>Total Satuan</p>
                    <h2><?= $total_satuan; ?></h2>
                </div>
                <div class="stat-card good">
                    <p>Kondisi Baik</p>
                    <h2><?= $total_baik; ?></h2>
                </div>
                <div class="stat-card warning">
                    <p>Perlu Perhatian</p>
                    <h2><?= $total_perhatian; ?></h2>
                </div>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <form method="GET" action="ruangan.php" class="search-box">
                        <input type="hidden" name="id" value="<?= $id_ruangan; ?>">
                        <i class="ph ph-magnifying-glass" style="color: #94a3b8;"></i>
                        <input type="text" name="search" placeholder="Cari barang di ruangan ini..." value="<?= htmlspecialchars($keyword); ?>">
                    </form>
                    <span style="font-size: 13px; color: #64748b;"><?= mysqli_num_rows($q_list); ?> dari <?= $total_jenis; ?> barang</span>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th style="width: 50px;">NO</th>
                            <th style="width: 160px;">BARCODE</th>
                            <th>NAMA BARANG</th>
                            <th>KATEGORI</th>
                            <th>KONDISI</th>
                            <th>KETERANGAN</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (mysqli_num_rows($q_list) > 0) {
                            $no = 1;
                            while ($row = mysqli_fetch_assoc($q_list)) { 
                                $kondisiRaw = $row['kondisi'] ?? 'baik';
                                $kondisiLower = strtolower(trim(preg_replace('/\s+/', ' ', $kondisiRaw)));
                                $badgeClass = 'badge-baik';
                                if ($kondisiLower === 'cukup baik') {
                                    $badgeClass = 'badge-cukup-baik';
                                } elseif ($kondisiLower === 'rusak') {
                                    $badgeClass = 'badge-rusak';
                                } elseif ($kondisiLower === 'rusak parah') {
                                    $badgeClass = 'badge-rusak-parah';
                                } elseif ($kondisiLower === 'hilang') {
                                    $badgeClass = 'badge-hilang';
                                }
                                $displayKondisi = ucwords($kondisiLower);
                        ?>
                            <tr>
                                <td><?= $no++; ?></td>
                                <td style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($row['barcode']); ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($row['nama_barang']); ?></strong>
                                    <div style="font-size: 12px; color: #94a3b8;"><?= htmlspecialchars($row['deskripsi']); ?></div>
                                </td>
                                <td><?= htmlspecialchars($row['nama_kategori'] ?? '-'); ?></td>
                                <td>
                                    <form method="POST" action="ruangan.php?id=<?= $id_ruangan; ?>">
                                        <input type="hidden" name="id_inventaris" value="<?= $row['id_inventaris']; ?>">
                                        <select name="kondisi" class="select-kondisi select-<?= str_replace(' ', '-', $kondisiLower); ?>" onchange="saveCondition(this)">
                                            <option value="baik" <?= $kondisiLower === 'baik' ? 'selected' : ''; ?>>Baik</option>
                                            <option value="cukup baik" <?= $kondisiLower === 'cukup baik' ? 'selected' : ''; ?>>Cukup Baik</option>
                                            <option value="rusak" <?= $kondisiLower === 'rusak' ? 'selected' : ''; ?>>Rusak</option>
                                            <option value="rusak parah" <?= $kondisiLower === 'rusak parah' ? 'selected' : ''; ?>>Rusak Parah</option>
                                            <option value="hilang" <?= $kondisiLower === 'hilang' ? 'selected' : ''; ?>>Hilang</option>
                                        </select>
                                        <input type="hidden" name="update_kondisi" value="1">
                                    </form>
                                </td>
                                <td><?= htmlspecialchars($row['keterangan_inventaris'] ?? '-'); ?></td>
                            </tr>
                        <?php 
                            }
                        } else { 
                        ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 30px; color: #94a3b8;">
                                    Belum ada data barang di ruangan ini.
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

        </div>
    </main>

    <script>
        function toggleEditMode(formId, hideOnly = false) {
            var form = document.getElementById(formId);
            if (!form) return;

            if (hideOnly) {
                form.classList.remove('active');
                return;
            }

            var isActive = form.classList.contains('active');
            form.classList.toggle('active', !isActive);
        }

        function saveCondition(select) {
            var form = select.form;
            if (!form) return;

            var formData = new FormData(form);
            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Gagal menyimpan kondisi.');
                }
                return response.json();
            })
            .then(function(data) {
                if (!data.success) {
                    throw new Error(data.message || 'Gagal menyimpan kondisi.');
                }
                var value = select.value.toLowerCase().replace(/\s+/g, '-');
                select.className = 'select-kondisi select-' + value;
            })
            .catch(function(error) {
                alert(error.message);
            });
        }
    </script>
</body>

</html>