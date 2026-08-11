<?php
session_start();
include 'config/koneksi.php';

if (!isset($_SESSION['login'])) {
    header('Location: login.php');
    exit;
}

$active_page = 'scan-barcode';
$page_title = 'Scan Barcode';
$breadcrumb = 'Scan Barcode';

$scan_result = null;
$scan_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['barcode'])) {
    $barcode = trim($_POST['barcode']);
    if ($barcode === '') {
        $scan_error = 'Kode barcode tidak valid.';
    } else {
        $query = mysqli_query($koneksi, "
            SELECT i.id_inventaris, i.barcode, i.keterangan, i.kondisi, i.ruangan_id,
                   b.id_barang, b.nama_barang, b.kode_barang,
                   k.nama_kategori, r.nama_ruangan
            FROM inventaris i
            JOIN barang b ON i.barang_id = b.id_barang
            LEFT JOIN kategori k ON b.kategori_id = k.id_kategori
            LEFT JOIN ruangan r ON i.ruangan_id = r.id_ruangan
            WHERE i.barcode = '" . mysqli_real_escape_string($koneksi, $barcode) . "' 
            LIMIT 1
        ");

        if ($query && mysqli_num_rows($query) > 0) {
            $scan_result = mysqli_fetch_assoc($query);
        } else {
            $scan_error = 'Barcode tidak ditemukan di database.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_scanned_item'])) {
    $id_inventaris = (int)($_POST['id_inventaris'] ?? 0);
    $keterangan = trim($_POST['keterangan'] ?? '');
    $kondisi = strtolower(trim($_POST['kondisi'] ?? ''));
    $allowed = ['baik', 'cukup baik', 'rusak', 'rusak parah', 'hilang'];

    if ($id_inventaris > 0 && in_array($kondisi, $allowed, true)) {
        $keterangan = mysqli_real_escape_string($koneksi, $keterangan);
        $kondisi = mysqli_real_escape_string($koneksi, $kondisi);
        mysqli_query($koneksi, "UPDATE inventaris SET keterangan = '$keterangan', kondisi = '$kondisi' WHERE id_inventaris = '$id_inventaris'");
        $scan_error = '';
        $scan_result = null;

        $barcode_lookup = mysqli_query($koneksi, "
            SELECT i.id_inventaris, i.barcode, i.keterangan, i.kondisi, i.ruangan_id,
                   b.id_barang, b.nama_barang, b.kode_barang,
                   k.nama_kategori, r.nama_ruangan
            FROM inventaris i
            JOIN barang b ON i.barang_id = b.id_barang
            LEFT JOIN kategori k ON b.kategori_id = k.id_kategori
            LEFT JOIN ruangan r ON i.ruangan_id = r.id_ruangan
            WHERE i.id_inventaris = '$id_inventaris'
            LIMIT 1
        ");

        if ($barcode_lookup && mysqli_num_rows($barcode_lookup) > 0) {
            $scan_result = mysqli_fetch_assoc($barcode_lookup);
        }
    } else {
        $scan_error = 'Data hasil scan tidak valid untuk diperbarui.';
    }
}

include 'includes/header.php';
?>

<div class="dashboard-container" style="padding: 24px;">
    <h1 style="font-size: 28px; color: #0f172a; font-weight: 700; margin-bottom: 20px;">Scan Barcode</h1>

    <div style="display: grid; grid-template-columns: 1.1fr 1.3fr; gap: 24px; align-items: start;">
        <div class="widget-card" style="padding: 20px;">
            <h3 style="margin-bottom: 18px; color: #0f172a;">Scanner</h3>

            <form method="POST" action="scan-barcode.php" id="scanForm">
                <input type="text" name="barcode" id="barcodeInput" class="search-box-input" placeholder="Cari Kode Barang Manual" style="width: 100%; margin-bottom: 12px;" autocomplete="off" autofocus>
                <button type="submit" class="btn-primary" style="width: 100%; justify-content: center;">Cari Barang</button>
            </form>

            <div id="scannerBox" style="margin-top: 18px; border: 1px solid #cbd5e1; border-radius: 12px; min-height: 260px; background: linear-gradient(135deg, #f8fafc, #e2e8f0); display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative;">
                <video id="cameraPreview" autoplay playsinline muted style="width: 100%; height: 100%; object-fit: cover; display: none; background: #0f172a;"></video>
                <div id="cameraFallback" style="text-align: center; color: #475569; padding: 20px; display: none;">
                    <i class="bi bi-camera-video" style="font-size: 40px; display: block; margin-bottom: 8px;"></i>
                </div>
            </div>

            <div style="margin-top: 14px; display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" id="startCamera" class="btn-primary" style="background: #0f766e;">Aktifkan Kamera</button>
                <button type="button" id="stopCamera" class="btn-primary" style="background: #64748b; display: none;">Matikan Kamera</button>
            </div>

            <?php if (!empty($scan_error)): ?>
                <div class="alert-error" style="margin-top: 16px; padding: 10px 14px; border-radius: 8px; background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b;">
                    <?= htmlspecialchars($scan_error); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="widget-card" style="padding: 20px; min-height: 420px;">
            <?php if ($scan_result): ?>
                <h3 style="margin-bottom: 18px; color: #0f172a;">Hasil Scan</h3>

                <form method="POST" action="scan-barcode.php">
                    <input type="hidden" name="update_scanned_item" value="1">
                    <input type="hidden" name="id_inventaris" value="<?= (int) $scan_result['id_inventaris']; ?>">

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px;">
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase;">Kode Barang</label>
                            <input type="text" class="search-box-input" value="<?= htmlspecialchars($scan_result['kode_barang'] ?? ''); ?>" readonly>
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase;">Barcode</label>
                            <input type="text" class="search-box-input" value="<?= htmlspecialchars($scan_result['barcode'] ?? ''); ?>" readonly>
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase;">Nama Barang</label>
                            <input type="text" class="search-box-input" value="<?= htmlspecialchars($scan_result['nama_barang'] ?? ''); ?>" readonly>
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase;">Kategori</label>
                            <input type="text" class="search-box-input" value="<?= htmlspecialchars($scan_result['nama_kategori'] ?? '-'); ?>" readonly>
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label style="display: block; margin-bottom: 8px; font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase;">Keterangan</label>
                            <input type="text" name="keterangan" class="search-box-input" value="<?= htmlspecialchars($scan_result['keterangan'] ?? ''); ?>">
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase;">Kondisi</label>
                            <select name="kondisi" class="filter-select" style="width: 100%;">
                                <option value="baik" <?= strtolower((string)($scan_result['kondisi'] ?? '')) === 'baik' ? 'selected' : ''; ?>>Baik</option>
                                <option value="cukup baik" <?= strtolower((string)($scan_result['kondisi'] ?? '')) === 'cukup baik' ? 'selected' : ''; ?>>Cukup Baik</option>
                                <option value="rusak" <?= strtolower((string)($scan_result['kondisi'] ?? '')) === 'rusak' ? 'selected' : ''; ?>>Rusak</option>
                                <option value="rusak parah" <?= strtolower((string)($scan_result['kondisi'] ?? '')) === 'rusak parah' ? 'selected' : ''; ?>>Rusak Parah</option>
                                <option value="hilang" <?= strtolower((string)($scan_result['kondisi'] ?? '')) === 'hilang' ? 'selected' : ''; ?>>Hilang</option>
                            </select>
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase;">Ruangan</label>
                            <input type="text" class="search-box-input" value="<?= htmlspecialchars($scan_result['nama_ruangan'] ?? '-'); ?>" readonly>
                        </div>
                    </div>

                    <div style="margin-top: 22px;">
                        <label style="display: block; margin-bottom: 8px; font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase;">QR CODE</label>
                        <div style="display:flex; justify-content:center; align-items:center; border:1px solid #dbe4ee; border-radius:12px; background:#fff; min-height:180px; padding:20px;">
                            <div class="qr-code" data-barcode="<?= htmlspecialchars($scan_result['barcode'] ?? ''); ?>" style="width:120px; height:120px;"></div>
                        </div>
                    </div>

                    <div style="margin-top: 20px; display: flex; gap: 12px; justify-content: flex-end;">
                        <button type="submit" class="btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            <?php else: ?>
                <div style="display: flex; align-items: center; justify-content: center; min-height: 320px; color: #64748b; text-align: center;">
                    Belum ada data yang dipindai. Silakan scan atau masukkan barcode.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    const video = document.getElementById('cameraPreview');
    const cameraFallback = document.getElementById('cameraFallback');
    const startCameraBtn = document.getElementById('startCamera');
    const stopCameraBtn = document.getElementById('stopCamera');
    const scanForm = document.getElementById('scanForm');
    const barcodeInput = document.getElementById('barcodeInput');
    const detectionCanvas = document.createElement('canvas');
    const detectionCtx = detectionCanvas.getContext('2d');
    let stream = null;
    let cameraStarted = false;
    let detectionLoop = null;
    let lastDetectedValue = '';

    function renderQrCell() {
        document.querySelectorAll('.qr-code').forEach(function(container) {
            const code = container.dataset.barcode || '';
            if (!code || container.children.length > 0) return;
            if (typeof QRCode !== 'undefined') {
                new QRCode(container, {
                    text: code,
                    width: 120,
                    height: 120,
                    colorDark: '#0f172a',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.H
                });
            } else {
                container.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;width:120px;height:120px;border:1px dashed #cbd5e1;border-radius:10px;font-size:12px;color:#64748b;">QR</div>';
            }
        });
    }

    function handleBarcodeInput(value) {
        const code = value.trim();
        if (!code) return;
        barcodeInput.value = code;
        if (scanForm) {
            scanForm.submit();
        }
    }

    function submitDetectedBarcode(value) {
        if (!value) return;
        const code = String(value).trim();
        if (!code || code === lastDetectedValue) return;
        lastDetectedValue = code;
        barcodeInput.value = code;
        if (scanForm) {
            scanForm.submit();
        }
    }

    function resetDetectedState() {
        lastDetectedValue = '';
    }

    function startDetectionLoop() {
        if (!cameraStarted || !video || !video.videoWidth || !video.videoHeight) {
            detectionLoop = requestAnimationFrame(startDetectionLoop);
            return;
        }

        const width = video.videoWidth;
        const height = video.videoHeight;
        detectionCanvas.width = width;
        detectionCanvas.height = height;
        detectionCtx.drawImage(video, 0, 0, width, height);

        const imageData = detectionCtx.getImageData(0, 0, width, height);
        const code = window.jsQR ? window.jsQR(imageData.data, width, height, {
            inversionAttempts: 'dontInvert'
        }) : null;

        if (code && code.data) {
            submitDetectedBarcode(code.data);
            return;
        }

        detectionLoop = requestAnimationFrame(startDetectionLoop);
    }

    async function startCamera() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            cameraFallback.innerHTML = '<div style="color:#b91c1c;">Browser ini tidak mendukung kamera.</div>';
            return;
        }

        if (stream) {
            return;
        }

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' }
            });

            video.srcObject = stream;
            video.style.display = 'block';
            cameraFallback.style.display = 'none';
            startCameraBtn.style.display = 'none';
            stopCameraBtn.style.display = 'inline-flex';
            cameraStarted = true;
            await video.play();
            detectionLoop = requestAnimationFrame(startDetectionLoop);
        } catch (error) {
            cameraFallback.innerHTML = '';
            cameraFallback.style.display = 'none';
        }
    }

    function stopCamera() {
        cameraStarted = false;
        if (detectionLoop) {
            cancelAnimationFrame(detectionLoop);
            detectionLoop = null;
        }

        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }

        video.srcObject = null;
        video.style.display = 'none';
        cameraFallback.style.display = 'block';
        startCameraBtn.style.display = 'inline-flex';
        stopCameraBtn.style.display = 'none';
        cameraFallback.innerHTML = '';
        cameraFallback.style.display = 'none';
    }

    startCameraBtn.addEventListener('click', startCamera);
    stopCameraBtn.addEventListener('click', stopCamera);
    barcodeInput.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            handleBarcodeInput(this.value);
        }
    });

    window.addEventListener('load', function() {
        startCamera();
    });

    window.addEventListener('pageshow', function() {
        if (!stream) {
            startCamera();
        }
    });

    renderQrCell();
</script>

<?php include 'includes/footer.php'; ?>
