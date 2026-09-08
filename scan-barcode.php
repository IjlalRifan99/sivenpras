<?php
session_start();
include 'config/koneksi.php';

if (!isset($_SESSION['login'])) {
    header('Location: login.php');
    exit;
}

$user_role = strtolower($_SESSION['role'] ?? '');
$active_page = 'scan-barcode';
$page_title = 'Scan Barcode';
$breadcrumb = 'Scan Barcode';

$update_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_scanned_item'])) {
    if ($user_role === 'kepala_sekolah') {
        $update_message = 'Akses ditolak: Kepala sekolah tidak memiliki izin mengubah data.';
    } else {
        $id_inventaris = (int) ($_POST['id_inventaris'] ?? 0);
        $keterangan = mysqli_real_escape_string($koneksi, trim($_POST['keterangan'] ?? ''));
        $kondisi = mysqli_real_escape_string($koneksi, strtolower(trim($_POST['kondisi'] ?? '')));

        if ($id_inventaris > 0) {
            mysqli_query($koneksi, "UPDATE inventaris SET keterangan = '$keterangan', kondisi = '$kondisi' WHERE id_inventaris = '$id_inventaris'");
            $update_message = 'Data berhasil diperbarui!';
        }
    }
}

include 'includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>

<div class="dashboard-container scan-page-shell" style="padding: 24px;">
    <h1 class="scan-page-title">Scan Barcode</h1>

    <div class="scan-layout">
        <div class="widget-card scan-panel">
            <h3 class="scan-panel-title">Scanner</h3>

            <div class="scan-form">
                <input type="text" id="barcodeInput" class="search-box-input scan-manual-input"
                    placeholder="Cari Kode Barang Manual" autocomplete="off" autofocus>
                <button type="button" id="btnCariManual" class="btn-primary scan-submit-btn">Cari Barang</button>
            </div>

            <div id="scannerBox" class="scanner-box">
                <video id="cameraPreview" autoplay playsinline muted class="camera-preview"></video>
                <div id="cameraFallback" class="camera-fallback">
                    <i class="bi bi-camera-video camera-fallback-icon"></i>
                </div>
            </div>

            <div class="scan-control-row">
                <button type="button" id="startCamera" class="btn-primary scan-camera-btn scan-camera-start">Aktifkan Kamera</button>
                <button type="button" id="stopCamera" class="btn-primary scan-camera-btn scan-camera-stop">Matikan Kamera</button>
            </div>
        </div>

        <div class="widget-card scan-result-panel">
            <h3 class="scan-result-title">Hasil Scan</h3>

            <?php if (!empty($update_message)): ?>
                <div class="alert-success-box" style="background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;">
                    <?= htmlspecialchars($update_message); ?>
                </div>
            <?php endif; ?>

            <!-- Form Update (Muncul jika barang ditemukan) -->
            <form method="POST" action="scan-barcode.php" id="formUpdate" style="display: none;">
                <input type="hidden" name="update_scanned_item" value="1">
                <input type="hidden" name="id_inventaris" id="res_id_inventaris">

                <div class="scan-detail-grid">
                    <div>
                        <label class="scan-field-label">Barcode</label>
                        <input type="text" id="res_barcode" class="search-box-input" readonly>
                    </div>

                    <div>
                        <label class="scan-field-label">Nama Barang</label>
                        <input type="text" id="res_nama_barang" class="search-box-input" readonly>
                    </div>

                    <div>
                        <label class="scan-field-label">Kategori</label>
                        <input type="text" id="res_kategori" class="search-box-input" readonly>
                    </div>

                    <div class="scan-field-field">
                        <label class="scan-field-label">Keterangan</label>
                        <input type="text" name="keterangan" id="res_keterangan" class="search-box-input" <?= ($user_role === 'kepala_sekolah') ? 'readonly' : ''; ?>>
                    </div>

                    <div>
                        <label class="scan-field-label">Kondisi</label>
                        <?php if ($user_role === 'kepala_sekolah'): ?>
                            <input type="text" id="res_kondisi_text" class="search-box-input" readonly>
                        <?php else: ?>
                            <select name="kondisi" id="res_kondisi_select" class="filter-select scan-select">
                                <option value="baik">Baik</option>
                                <option value="cukup baik">Cukup Baik</option>
                                <option value="rusak">Rusak</option>
                                <option value="rusak parah">Rusak Parah</option>
                                <option value="hilang">Hilang</option>
                            </select>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="scan-field-label">Ruangan</label>
                        <input type="text" id="res_ruangan" class="search-box-input" readonly>
                    </div>
                </div>

                <?php if ($user_role !== 'kepala_sekolah'): ?>
                    <div class="scan-save-row">
                        <button type="submit" class="btn-primary">Simpan Perubahan</button>
                    </div>
                <?php endif; ?>
            </form>

            <!-- State Kosong / Not Found (Muncul di panel hasil kanan) -->
            <div id="emptyState" class="scan-empty-state">
                Belum ada data yang dipindai. Silakan scan atau masukkan barcode.
            </div>
        </div>
    </div>
</div>

<style>
    .scan-page-shell { box-sizing: border-box; }
    .scan-page-title { font-size: 28px; color: #0f172a; font-weight: 700; margin-bottom: 20px; }
    .scan-layout { display: grid; grid-template-columns: 1.1fr 1.3fr; gap: 24px; align-items: start; }
    .scan-panel, .scan-result-panel { padding: 20px; }
    .scan-panel-title, .scan-result-title { margin-bottom: 18px; color: #0f172a; }
    .scan-form { display: flex; flex-direction: column; gap: 12px; }
    .scan-manual-input { width: 100%; margin-bottom: 0 !important; box-sizing: border-box; }
    .scan-submit-btn { width: 100%; justify-content: center; }
    .scanner-box { margin-top: 18px; border: 1px solid #cbd5e1; border-radius: 12px; min-height: 260px; height: 320px; background: linear-gradient(135deg, #f8fafc, #e2e8f0); display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; }
    .camera-preview { width: 100%; height: 100%; object-fit: cover; display: none; background: #0f172a; }
    .camera-fallback { text-align: center; color: #475569; padding: 20px; display: none; }
    .camera-fallback-icon { font-size: 40px; display: block; margin-bottom: 8px; }
    .scan-control-row { margin-top: 14px; display: flex; gap: 10px; flex-wrap: wrap; }
    .scan-camera-btn { flex: 1 1 180px; justify-content: center; }
    .scan-camera-start { background: #0f766e; }
    .scan-camera-stop { background: #64748b; display: none; }
    .scan-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
    .scan-field-label { display: block; margin-bottom: 8px; font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; }
    .scan-field-full { grid-column: 1 / -1; }
    .scan-select { width: 100%; }
    .scan-save-row { margin-top: 20px; display: flex; gap: 12px; justify-content: flex-end; }
    .scan-empty-state { display: flex; align-items: center; justify-content: center; min-height: 320px; color: #64748b; text-align: center; padding: 20px; }
    @media (max-width: 767px) {
        .dashboard-container { padding: 16px !important; }
        .scan-page-title { font-size: 24px; margin-bottom: 16px; }
        .scan-layout { grid-template-columns: 1fr; gap: 16px; }
        .scan-panel, .scan-result-panel { padding: 16px; }
        .scanner-box { min-height: 220px; height: 240px; }
        .scan-control-row { flex-direction: column; }
        .scan-camera-btn, .scan-submit-btn { width: 100%; flex: 1 1 100%; }
        .scan-detail-grid { grid-template-columns: 1fr; gap: 14px; }
        .scan-save-row { justify-content: stretch; }
        .scan-save-row .btn-primary { width: 100%; justify-content: center; }
    }
</style>

<script>
    const video = document.getElementById('cameraPreview');
    const cameraFallback = document.getElementById('cameraFallback');
    const startCameraBtn = document.getElementById('startCamera');
    const stopCameraBtn = document.getElementById('stopCamera');
    const barcodeInput = document.getElementById('barcodeInput');
    const btnCariManual = document.getElementById('btnCariManual');
    const formUpdate = document.getElementById('formUpdate');
    const emptyState = document.getElementById('emptyState');

    const detectionCanvas = document.createElement('canvas');
    const detectionCtx = detectionCanvas.getContext('2d');
    let stream = null;
    let cameraStarted = false;
    let detectionLoop = null;
    let isProcessing = false;

    async function processBarcode(code) {
        const cleanCode = code.trim();
        if (!cleanCode) return;

        if (isProcessing) return;
        isProcessing = true;

        try {
            const formData = new FormData();
            formData.append('barcode', cleanCode);

            const response = await fetch('api-scan.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.status === 'success') {
                const item = result.data;
                document.getElementById('res_id_inventaris').value = item.id_inventaris;
                document.getElementById('res_barcode').value = item.barcode;
                document.getElementById('res_nama_barang').value = item.nama_barang;
                document.getElementById('res_kategori').value = item.nama_kategori || '-';
                document.getElementById('res_ruangan').value = item.nama_ruangan || '-';
                document.getElementById('res_keterangan').value = item.keterangan || '';

                const selectKondisi = document.getElementById('res_kondisi_select');
                if (selectKondisi) {
                    selectKondisi.value = (item.kondisi || 'baik').toLowerCase();
                } else {
                    const textKondisi = document.getElementById('res_kondisi_text');
                    if (textKondisi) textKondisi.value = item.kondisi || '-';
                }

                emptyState.style.display = 'none';
                formUpdate.style.display = 'block';

            } else if (result.status === 'not_found') {
                formUpdate.style.display = 'none';
                emptyState.innerHTML = `
                    <div style="text-align: center; color: #991b1b; background: #fef2f2; border: 1px solid #fecaca; padding: 20px; border-radius: 8px; width: 100%;">
                        <i class="bi bi-shield-exclamation" style="font-size: 36px; display: block; margin-bottom: 8px;"></i>
                        <strong style="font-size: 15px;">Barcode Tidak Ditemukan</strong>
                        <p style="margin: 6px 0 0 0; font-size: 13px; color: #7f1d1d;">
                            Kode <u style="font-weight: 600;">${htmlspecialchars(result.barcode)}</u> tidak terdaftar di database inventaris.
                        </p>
                    </div>
                `;
                emptyState.style.display = 'flex';
            }
        } catch (error) {
            console.error('API Error:', error);
        } finally {
            setTimeout(() => { isProcessing = false; }, 1500);
        }
    }

    function htmlspecialchars(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
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
        const code = window.jsQR ? window.jsQR(imageData.data, width, height, { inversionAttempts: 'dontInvert' }) : null;

        if (code && code.data && !isProcessing) {
            barcodeInput.value = code.data;
            processBarcode(code.data);
        }

        detectionLoop = requestAnimationFrame(startDetectionLoop);
    }

    async function startCamera() {
        if (!window.isSecureContext) return;

        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
            video.srcObject = stream;
            video.style.display = 'block';
            cameraFallback.style.display = 'none';
            startCameraBtn.style.display = 'none';
            stopCameraBtn.style.display = 'inline-flex';
            cameraStarted = true;
            await video.play();
            detectionLoop = requestAnimationFrame(startDetectionLoop);
        } catch (err) {
            console.error('Kamera error:', err);
        }
    }

    function stopCamera() {
        cameraStarted = false;
        if (detectionLoop) cancelAnimationFrame(detectionLoop);
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
        video.style.display = 'none';
        cameraFallback.style.display = 'block';
        startCameraBtn.style.display = 'inline-flex';
        stopCameraBtn.style.display = 'none';
    }

    startCameraBtn.addEventListener('click', startCamera);
    stopCameraBtn.addEventListener('click', stopCamera);

    btnCariManual.addEventListener('click', () => {
        processBarcode(barcodeInput.value.trim());
    });

    barcodeInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            processBarcode(barcodeInput.value.trim());
        }
    });

    window.addEventListener('load', startCamera);
</script>

<?php include 'includes/footer.php'; ?>