<?php
session_start();
include 'config/koneksi.php';

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

$active_page = 'ruangan';

// 1. Ambil ID Ruangan dari URL
$id_ruangan = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_ruangan == 0) {
    header("Location: index.php");
    exit;
}

$q_ruangan_detail = mysqli_query($koneksi, "SELECT * FROM ruangan WHERE id_ruangan = $id_ruangan");

if (!$q_ruangan_detail || mysqli_num_rows($q_ruangan_detail) == 0) {
    header("Location: index.php");
    exit;
}

$ruangan = mysqli_fetch_assoc($q_ruangan_detail);
$nama_ruangan = $ruangan['nama_ruangan'];

$page_title = $nama_ruangan;
$breadcrumb = "Ruangan > " . $nama_ruangan;
$page_title = 'Ruangan';

// Handler Update Kondisi via AJAX / POST
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
        
        $q_b = mysqli_query($koneksi, "SELECT COUNT(id_inventaris) AS total FROM inventaris WHERE ruangan_id = '$id_ruangan' AND kondisi = 'baik'");
        $d_b = mysqli_fetch_assoc($q_b);
        $stat_baik = $d_b['total'] ?? 0;

        $q_p = mysqli_query($koneksi, "SELECT COUNT(id_inventaris) AS total FROM inventaris WHERE ruangan_id = '$id_ruangan' AND kondisi != 'baik'");
        $d_p = mysqli_fetch_assoc($q_p);
        $stat_perhatian = $d_p['total'] ?? 0;

        echo json_encode([
            'success'        => $success,
            'kondisi'        => $kondisi,
            'total_baik'     => $stat_baik,
            'total_perhatian'=> $stat_perhatian,
            'message'        => $success ? 'Kondisi berhasil disimpan.' : 'Gagal menyimpan kondisi.'
        ]);
        exit;
    }

    header("Location: ruangan.php?id=$id_ruangan");
    exit;
}

// Handler Update Keterangan via AJAX / POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_keterangan'], $_POST['id_inventaris'])) {
    $id_inventaris = (int)$_POST['id_inventaris'];
    $keterangan = mysqli_real_escape_string($koneksi, trim($_POST['keterangan']));

    $success = (bool) mysqli_query($koneksi, "UPDATE inventaris SET keterangan = '$keterangan' WHERE id_inventaris = '$id_inventaris' AND ruangan_id = '$id_ruangan'");

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Keterangan berhasil diperbarui.' : 'Gagal memperbarui keterangan.'
        ]);
        exit;
    }

    header("Location: ruangan.php?id=$id_ruangan");
    exit;
}

// Handler Hapus Massal (Multiple Delete via Checkbox)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_massal'], $_POST['ids_inventaris'])) {
    $ids = array_map('intval', $_POST['ids_inventaris']);
    
    if (!empty($ids)) {
        $id_list = implode(',', $ids);
        mysqli_query($koneksi, "DELETE FROM inventaris WHERE id_inventaris IN ($id_list) AND ruangan_id = '$id_ruangan'");
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

// 3. Query untuk Sidebar Ruangan
$q_ruangan_sidebar = mysqli_query($koneksi, "
    SELECT r.id_ruangan, r.nama_ruangan, COUNT(i.id_inventaris) AS total_barang 
    FROM ruangan r 
    LEFT JOIN inventaris i ON r.id_ruangan = i.ruangan_id 
    GROUP BY r.id_ruangan, r.nama_ruangan
");

// 4. Query Menghitung Statistik Card Atas
$q_stat_jenis = mysqli_query($koneksi, "SELECT COUNT(DISTINCT barang_id) AS total_jenis FROM inventaris WHERE ruangan_id = '$id_ruangan'");
$d_stat_jenis = mysqli_fetch_assoc($q_stat_jenis);
$total_jenis = $d_stat_jenis['total_jenis'] ?? 0;

$q_stat_satuan = mysqli_query($koneksi, "SELECT COUNT(id_inventaris) AS total_satuan FROM inventaris WHERE ruangan_id = '$id_ruangan'");
$d_stat_satuan = mysqli_fetch_assoc($q_stat_satuan);
$total_satuan = $d_stat_satuan['total_satuan'] ?? 0;

$q_stat_baik = mysqli_query($koneksi, "SELECT COUNT(id_inventaris) AS total_baik FROM inventaris WHERE ruangan_id = '$id_ruangan' AND kondisi = 'baik'");
$d_stat_baik = mysqli_fetch_assoc($q_stat_baik);
$total_baik = $d_stat_baik['total_baik'] ?? 0;

$q_stat_perhatian = mysqli_query($koneksi, "SELECT COUNT(id_inventaris) AS total_perhatian FROM inventaris WHERE ruangan_id = '$id_ruangan' AND kondisi != 'baik'");
$d_stat_perhatian = mysqli_fetch_assoc($q_stat_perhatian);
$total_perhatian = $d_stat_perhatian['total_perhatian'] ?? 0;

// 5. Data Pilihan Nama Barang untuk Dropdown Filter
$q_filter_barang = mysqli_query($koneksi, "
    SELECT DISTINCT b.id_barang, b.nama_barang 
    FROM inventaris i 
    JOIN barang b ON i.barang_id = b.id_barang 
    WHERE i.ruangan_id = '$id_ruangan' 
    ORDER BY b.nama_barang ASC
");

// 6. Tangkap Param Filter & Search dari URL
$keyword       = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, $_GET['search']) : '';
$filter_barang  = isset($_GET['filter_barang']) ? (int)$_GET['filter_barang'] : 0;
$filter_kondisi = isset($_GET['filter_kondisi']) ? mysqli_real_escape_string($koneksi, $_GET['filter_kondisi']) : '';

// 7. Query Menampilkan Daftar Unit Inventaris di Ruangan
$sql_list = "
    SELECT 
        i.id_inventaris,
        i.barcode,
        i.kondisi,
        i.keterangan AS keterangan_inventaris,
        i.update_at,
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
if ($filter_barang > 0) {
    $sql_list .= " AND b.id_barang = '$filter_barang'";
}
if (!empty($filter_kondisi)) {
    $sql_list .= " AND i.kondisi = '$filter_kondisi'";
}

$sql_list .= " ORDER BY i.barcode ASC";
$q_list = mysqli_query($koneksi, $sql_list);

?>

<?php include 'includes/header.php'; ?>
        
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
                    <h2 id="stat-jenis"><?= $total_jenis; ?></h2>
                </div>
                <div class="stat-card">
                    <p>Total Satuan</p>
                    <h2 id="stat-satuan"><?= $total_satuan; ?></h2>
                </div>
                <div class="stat-card good">
                    <p>Kondisi Baik</p>
                    <h2 id="stat-baik"><?= $total_baik; ?></h2>
                </div>
                <div class="stat-card warning">
                    <p>Perlu Perhatian</p>
                    <h2 id="stat-perhatian"><?= $total_perhatian; ?></h2>
                </div>
            </div>

            <div class="table-container">
                
                <form method="GET" action="ruangan.php" class="filter-container" id="filterForm">
                    <input type="hidden" name="id" value="<?= $id_ruangan; ?>">

                    <div class="filter-item" style="flex: 1; min-width: 180px;">
                        <label>Cari Kata Kunci</label>
                        <input type="text" name="search" id="searchInput" class="search-box-input" placeholder="Kode barang, Barang..." value="<?= htmlspecialchars($keyword); ?>" oninput="autoSearch(this)">
                    </div>

                    <div class="filter-item">
                        <label>Nama Barang</label>
                        <select name="filter_barang" class="filter-select" onchange="this.form.submit()">
                            <option value=""> Semua Barang </option>
                            <?php while ($fb = mysqli_fetch_assoc($q_filter_barang)) { ?>
                                <option value="<?= $fb['id_barang']; ?>" <?= $filter_barang == $fb['id_barang'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($fb['nama_barang']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="filter-item">
                        <label>Kondisi Barang</label>
                        <select name="filter_kondisi" class="filter-select" onchange="this.form.submit()">
                            <option value=""> Semua Kondisi </option>
                            <option value="baik" <?= $filter_kondisi === 'baik' ? 'selected' : ''; ?>>Baik</option>
                            <option value="cukup baik" <?= $filter_kondisi === 'cukup baik' ? 'selected' : ''; ?>>Cukup Baik</option>
                            <option value="rusak" <?= $filter_kondisi === 'rusak' ? 'selected' : ''; ?>>Rusak</option>
                            <option value="rusak parah" <?= $filter_kondisi === 'rusak parah' ? 'selected' : ''; ?>>Rusak Parah</option>
                            <option value="hilang" <?= $filter_kondisi === 'hilang' ? 'selected' : ''; ?>>Hilang</option>
                        </select>
                    </div>

                    <button type="submit" style="display:none;"></button>

                    <?php if (!empty($keyword) || $filter_barang > 0 || !empty($filter_kondisi)) { ?>
                        <a href="ruangan.php?id=<?= $id_ruangan; ?>" class="btn-reset-filter">
                            <i class="ph ph-x-circle"></i> Reset Filter
                        </a>
                    <?php } ?>
                </form>

                <form method="POST" action="ruangan.php?id=<?= $id_ruangan; ?>" id="formHapusMassal">
                    <input type="hidden" name="hapus_massal" value="1">

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <span style="font-size: 13px; color: #64748b;">
                            Menampilkan <strong><?= mysqli_num_rows($q_list); ?></strong> barang
                        </span>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th style="width: 40px; text-align: center;">
                                    <input type="checkbox" id="checkAll" onchange="toggleSelectAll(this)">
                                </th>
                                <th style="width: 150px;">KODE BARANG</th>
                                <th>NAMA BARANG</th>
                                <th>KATEGORI</th>
                                <th style="width: 200px;">KETERANGAN</th>
                                <th style="width: 170px;">KONDISI</th>
                                <th style="width: 180px;">TERAKHIR DIUPDATE</th>
                                <th style="width: 220px;">QR CODE</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (mysqli_num_rows($q_list) > 0) {
                                $no = 1;
                                while ($row = mysqli_fetch_assoc($q_list)) { 
                                    $kondisiRaw = $row['kondisi'] ?? 'baik';
                                    $kondisiLower = strtolower(trim(preg_replace('/\s+/', ' ', $kondisiRaw)));
                            ?>
                                <tr>
                                    <td style="text-align: center;">
                                        <input type="checkbox" name="ids_inventaris[]" value="<?= $row['id_inventaris']; ?>" class="item-checkbox" data-barcode="<?= htmlspecialchars($row['barcode']); ?>" onchange="updateBatchDeleteButton()">
                                    </td>
                                    <td style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($row['barcode']); ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($row['nama_barang']); ?></strong>
                                        <div style="font-size: 12px; color: #94a3b8;"><?= htmlspecialchars($row['deskripsi']); ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($row['nama_kategori'] ?? '-'); ?></td>
                                    <td>
                                        <input type="text" 
                                               class="search-box-input" 
                                               style="width: 100%;" 
                                               value="<?= htmlspecialchars($row['keterangan_inventaris'] ?? ''); ?>" 
                                               placeholder="Tambah keterangan..." 
                                               onchange="saveKeteranganInline(<?= $row['id_inventaris']; ?>, this)">
                                    </td>
                                    <td>
                                        <select class="select-kondisi select-<?= str_replace(' ', '-', $kondisiLower); ?>" onchange="saveConditionInline(<?= $row['id_inventaris']; ?>, this)">
                                            <option value="baik" <?= $kondisiLower === 'baik' ? 'selected' : ''; ?>>Baik</option>
                                            <option value="cukup baik" <?= $kondisiLower === 'cukup baik' ? 'selected' : ''; ?>>Cukup Baik</option>
                                            <option value="rusak" <?= $kondisiLower === 'rusak' ? 'selected' : ''; ?>>Rusak</option>
                                            <option value="rusak parah" <?= $kondisiLower === 'rusak parah' ? 'selected' : ''; ?>>Rusak Parah</option>
                                            <option value="hilang" <?= $kondisiLower === 'hilang' ? 'selected' : ''; ?>>Hilang</option>
                                        </select>
                                    </td>
                                    <td><?= htmlspecialchars(date('d M Y H:i', strtotime($row['update_at'] ?? $row['created_at']))); ?></td>
                                    <td class="qr-cell">
                                        <div class="qr-code" data-barcode="<?= htmlspecialchars($row['barcode']); ?>"></div>
                                        <div class="barcode-label"><?= htmlspecialchars($row['barcode']); ?></div>
                                    </td>
                                </tr>
                            <?php 
                                }
                            } else { 
                            ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 30px; color: #94a3b8;">
                                        Data barang tidak ditemukan sesuai filter yang dipilih.
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>

                    <div id="selectedActions" class="selected-actions" style="display: none; justify-content: flex-end; align-items: center; gap: 10px; margin-top: 18px;">
                        <span id="selectedCountText" style="font-size: 13px; color: #475569;">0 item terpilih</span>
                        <button type="button" class="btn-action btn-print" onclick="printSelectedItems()">Print</button>
                        <button type="submit" id="btnHapusMassal" onclick="return confirm('Apakah Anda yakin ingin menghapus barang yang dicentang?')" class="btn-action btn-delete">
                            <i class="ph ph-trash"></i> Hapus
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </main>

    <script>
        // 1. Toggle Dropdown Sidebar Ruangan
        function toggleRuanganDropdown() {
            var dropdown = document.getElementById('ruanganDropdown');
            var caret = document.getElementById('ruanganCaret');
            
            if (dropdown.classList.contains('open')) {
                dropdown.classList.remove('open');
                caret.classList.add('rotate');
            } else {
                dropdown.classList.add('open');
                caret.classList.remove('rotate');
            }
        }

        // 2. Auto Search dengan Debounce
        var searchTimer;
        function autoSearch(input) {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function() {
                input.form.submit();
            }, 500);
        }

        // 3. Save Kondisi Inline via AJAX
        function saveConditionInline(id_inventaris, selectElement) {
            var formData = new FormData();
            formData.append('id_inventaris', id_inventaris);
            formData.append('kondisi', selectElement.value);
            formData.append('update_kondisi', '1');

            fetch('ruangan.php?id=<?= $id_ruangan; ?>', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    var value = selectElement.value.toLowerCase().replace(/\s+/g, '-');
                    selectElement.className = 'select-kondisi select-' + value;

                    if (data.total_baik !== undefined) {
                        document.getElementById('stat-baik').textContent = data.total_baik;
                    }
                    if (data.total_perhatian !== undefined) {
                        document.getElementById('stat-perhatian').textContent = data.total_perhatian;
                    }
                } else {
                    alert(data.message || 'Gagal menyimpan kondisi.');
                }
            })
            .catch(function(err) { alert(err.message); });
        }

        // 4. Save Keterangan Inline via AJAX
        function saveKeteranganInline(id_inventaris, inputElement) {
            var formData = new FormData();
            formData.append('id_inventaris', id_inventaris);
            formData.append('keterangan', inputElement.value);
            formData.append('update_keterangan', '1');

            fetch('ruangan.php?id=<?= $id_ruangan; ?>', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    inputElement.style.borderColor = '#16a34a';
                    setTimeout(function() {
                        inputElement.style.borderColor = '#cbd5e1';
                    }, 1200);
                } else {
                    alert(data.message || 'Gagal menyimpan keterangan.');
                }
            })
            .catch(function(err) { alert(err.message); });
        }

        // 5. Checkbox Select All & Batch Delete Toggle
        function toggleSelectAll(mainCheckbox) {
            var checkboxes = document.querySelectorAll('.item-checkbox');
            checkboxes.forEach(function(cb) {
                cb.checked = mainCheckbox.checked;
            });
            updateBatchDeleteButton();
        }

        function updateBatchDeleteButton() {
            var checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
            var actionBar = document.getElementById('selectedActions');
            var selectedCountText = document.getElementById('selectedCountText');
            var checkAll = document.getElementById('checkAll');
            var totalBoxes = document.querySelectorAll('.item-checkbox');

            if (checkedBoxes.length > 0) {
                actionBar.style.display = 'flex';
                selectedCountText.textContent = checkedBoxes.length + ' item terpilih';
            } else {
                actionBar.style.display = 'none';
            }

            if (totalBoxes.length > 0) {
                checkAll.checked = (checkedBoxes.length === totalBoxes.length);
            }
        }

        function getSelectedItems() {
            return Array.from(document.querySelectorAll('.item-checkbox:checked')).map(function(cb) {
                var row = cb.closest('tr');
                return {
                    barcode: cb.dataset.barcode || '',
                    name: row ? (row.querySelector('td:nth-child(3) strong') || {}).textContent || '' : ''
                };
            });
        }

        function getQrDataUrl(code) {
            var tempContainer = document.createElement('div');
            tempContainer.style.position = 'absolute';
            tempContainer.style.left = '-9999px';
            tempContainer.style.visibility = 'hidden';
            document.body.appendChild(tempContainer);

            new QRCode(tempContainer, {
                text: code,
                width: 120,
                height: 120,
                colorDark: '#0f172a',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.H,
                render: 'canvas'
            });

            var canvas = tempContainer.querySelector('canvas');
            var dataUrl = canvas ? canvas.toDataURL('image/png') : '';
            document.body.removeChild(tempContainer);
            return dataUrl;
        }

        function printSelectedItems() {
            var items = getSelectedItems();
            if (items.length === 0) {
                alert('Pilih minimal satu barang untuk dicetak.');
                return;
            }

            var itemHtml = items.map(function(item, index) {
                var imageData = getQrDataUrl(item.barcode);
                return {
                    barcode: item.barcode,
                    name: item.name,
                    imageData: imageData
                };
            });

            var html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">'
                + '<head><meta charset="utf-8"><title>Barcode Export</title>'
                + '<style>body{font-family:Arial,sans-serif;margin:24px;}table{width:100%;border-collapse:collapse;table-layout:fixed;}td{width:33.33%;padding:4px;vertical-align:top;text-align:center;} .barcode-card{width:100%;padding-bottom:100%;position:relative;box-sizing:border-box;border:1px dotted #64748b;overflow:hidden;margin:0 auto;} .barcode-card-inner{position:absolute;top:10px;left:10px;right:10px;bottom:10px;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;gap:8px;} img{width:130px;height:130px;} .barcode-label{font-size:12px;color:#334155;word-break:break-word;} .cut-line{position:absolute;bottom:8px;left:8px;right:8px;height:0;border:none;border-bottom:1px dotted #64748b;}</style></head><body>'
                + '<table>';

            itemHtml.forEach(function(item, idx) {
                if (idx % 3 === 0) {
                    html += '<tr>';
                }
                html += '<td>';
                html += '<div class="barcode-card">';
                html += '<div class="barcode-card-inner">';
                html += '<img src="' + item.imageData + '" alt="QR Code" />';
                html += '<div class="barcode-label">' + item.barcode + '</div>';
                html += '<div class="cut-line"></div>';
                html += '</div>';
                html += '</div>';
                html += '</td>';
                if (idx % 3 === 2) {
                    html += '</tr>';
                }
            });

            if (itemHtml.length % 3 !== 0) {
                for (var fill = itemHtml.length % 3; fill < 3; fill++) {
                    html += '<td></td>';
                }
                html += '</tr>';
            }

            html += '</table></body></html>';

            var blob = new Blob(['\ufeff', html], { type: 'application/msword' });
            var url = URL.createObjectURL(blob);
            var link = document.createElement('a');
            link.href = url;
            link.download = 'export-barcode.doc';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }

        function renderBarcodeCells() {
            document.querySelectorAll('.qr-code').forEach(function(container) {
                var code = container.dataset.barcode || '';
                if (code && container.children.length === 0) {
                    new QRCode(container, {
                        text: code,
                        width: 64,
                        height: 64,
                        colorDark: '#0f172a',
                        colorLight: '#ffffff',
                        correctLevel: QRCode.CorrectLevel.H
                    });
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            renderBarcodeCells();
            var searchInput = document.getElementById('searchInput');
            if (searchInput && searchInput.value !== '') {
                searchInput.focus();
                var val = searchInput.value;
                searchInput.value = '';
                searchInput.value = val;
            }
        });
    </script>
<?php  include 'includes/footer.php'; ?>