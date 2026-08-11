<?php
session_start();
include 'config/koneksi.php';

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

$active_page = 'dashboard';
$page_title = 'Dashboard';
$breadcrumb = 'Dashboard';
$active_page = 'dashboard';

/* DATA DASHBOARD */
$q_total_barang = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM inventaris");
$data_total_barang = mysqli_fetch_assoc($q_total_barang);
$total_barang = $data_total_barang['total'] ?? 0;

$q_total_jenis = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM barang");
$data_total_jenis = mysqli_fetch_assoc($q_total_jenis);
$total_jenis = $data_total_jenis['total'] ?? 0;

$q_kondisi_baik = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM inventaris WHERE kondisi = 'baik'");
$data_kondisi_baik = mysqli_fetch_assoc($q_kondisi_baik);
$kondisi_baik = $data_kondisi_baik['total'] ?? 0;

$q_rusak_ringan = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM inventaris WHERE kondisi = 'rusak'");
$data_rusak_ringan = mysqli_fetch_assoc($q_rusak_ringan);
$rusak_ringan = $data_rusak_ringan['total'] ?? 0;

$q_rusak_berat = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM inventaris WHERE kondisi = 'hilang'");
$data_rusak_berat = mysqli_fetch_assoc($q_rusak_berat);
$rusak_berat = $data_rusak_berat['total'] ?? 0;

$persen_baik = $total_barang > 0 ? round(($kondisi_baik / $total_barang) * 100) : 0;

$q_top_kategori = mysqli_query($koneksi, "SELECT k.nama_kategori, COUNT(i.id_inventaris) AS total_unit FROM kategori k LEFT JOIN barang b ON k.id_kategori = b.kategori_id LEFT JOIN inventaris i ON b.id_barang = i.barang_id GROUP BY k.id_kategori, k.nama_kategori ORDER BY total_unit DESC LIMIT 5");

include 'includes/header.php';
?>
<div class="dashboard-container">
    <div class="page-header">
        <h1>Dashboard Inventaris</h1>
        <p>Ringkasan data sarana dan prasarana sekolah — diperbarui hari ini</p>
    </div>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <h3>Total Barang</h3>
                <div class="icon-wrapper icon-teal"><i class="ph-bold ph-cube"></i></div>
            </div>
            <div class="stat-value teal"><?= number_format($total_barang); ?></div>
            <div class="stat-desc"><?= $total_jenis; ?> jenis barang</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <h3>Kondisi Baik</h3>
                <div class="icon-wrapper icon-green"><i class="ph-bold ph-check-circle"></i></div>
            </div>
            <div class="stat-value green"><?= number_format($kondisi_baik); ?></div>
            <div class="stat-desc"><?= $persen_baik; ?>% dari total</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <h3>Rusak Ringan</h3>
                <div class="icon-wrapper icon-yellow"><i class="ph-bold ph-warning"></i></div>
            </div>
            <div class="stat-value yellow"><?= number_format($rusak_ringan); ?></div>
            <div class="stat-desc">Perlu perhatian</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <h3>Rusak Berat</h3>
                <div class="icon-wrapper icon-red"><i class="ph-bold ph-x-circle"></i></div>
            </div>
            <div class="stat-value red"><?= number_format($rusak_berat); ?></div>
            <div class="stat-desc">Perlu penggantian</div>
        </div>
    </div>
    <div class="dashboard-widgets">
        <div class="widget-card">
            <h3>Top Kategori Barang</h3>
            <?php if ($q_top_kategori && mysqli_num_rows($q_top_kategori) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($q_top_kategori)): ?>
                    <?php $persen_bar = ($total_barang > 0) ? round(($row['total_unit'] / $total_barang) * 100) : 0; ?>
                    <div class="progress-item">
                        <div class="progress-info">
                            <span><?= htmlspecialchars($row['nama_kategori']); ?></span>
                            <span><?= $row['total_unit']; ?></span>
                        </div>
                        <div class="progress-bg">
                            <div class="progress-fill" style="width:<?= $persen_bar; ?>%;"></div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color:#888;font-size:14px;">Belum ada data barang.</p>
            <?php endif; ?>
        </div>
        <div class="widget-card">
            <h3>Distribusi Kondisi</h3>
            <ul class="kondisi-list">
                <li class="kondisi-item">
                    <div class="kondisi-left">
                        <div class="dot green"></div> Baik
                    </div>
                    <div class="kondisi-value"><?= number_format($kondisi_baik); ?></div>
                </li>
                <li class="kondisi-item">
                    <div class="kondisi-left">
                        <div class="dot yellow"></div> Rusak Ringan
                    </div>
                    <div class="kondisi-value"><?= number_format($rusak_ringan); ?></div>
                </li>
                <li class="kondisi-item">
                    <div class="kondisi-left">
                        <div class="dot red"></div> Rusak Berat
                    </div>
                    <div class="kondisi-value"><?= number_format($rusak_berat); ?></div>
                </li>
            </ul>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>