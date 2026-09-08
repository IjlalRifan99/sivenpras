<?php
session_start();
include 'config/koneksi.php';

if (!isset($_SESSION['login'])) {
    header('Location: login.php');
    exit;
}

$user_role = strtolower($_SESSION['role'] ?? '');
$active_page = 'scan-barcode';
$page_title = 'Scan Barcode & QR';
$breadcrumb = 'Scan Barcode & QR';

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

<!-- Library Html5-Qrcode -->
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

<div class="dashboard-container scan-page-shell" style="padding: 24px;">
    <h1 class="scan-page-title">Scan Barcode & QR Code</h1>

    <div class="scan-layout">
        <div class="widget-card scan-panel">
            <h3 class="scan-panel-title">Scanner</h3>

            <div class="scan-form">
                <input type="text" id="barcodeInput" class="search-box-input scan-manual-input"
                    placeholder="Cari Kode Barang Manual" autocomplete="off" autofocus>
                <button type="button" id="btnCariManual" class="btn-primary scan-submit-btn">Cari Barang</button>
            </div>

            <!-- Tombol Pilihan Mode Scan -->
            <div class="scan-mode-row" style="margin-top: 16px; display: flex; gap: 8px;">
                <button type="button" id="btnModeQR" class="btn-primary scan-mode-btn active-mode" style="flex: 1; justify-content: center; background: #0f766e;">Mode QR Code</button>
                <button type="button" id="btnModeBarcode" class="btn-primary scan-mode-btn" style="flex: 1; justify-content: center; background: #475569;">Mode Barcode</button>
            </div>

            <div id="scannerBox" class="scanner-box">
                <div id="reader"></div>
                <div id="cameraFallback" class="camera-fallback" style="display: none;">
                    <i class="bi bi-camera-video camera-fallback-icon"></i>
                </div>
            </div>

            <div class="scan-control-row">
                <button type="button" id="startCamera" class="btn-primary scan-camera-btn scan-camera-start" style="display: none;">Aktifkan Kamera</button>
                <button type="button" id="stopCamera" class="btn-primary scan-camera-btn scan-camera-stop" style="background: #64748b;">Matikan Kamera</button>
            </div>
        </div>

        <div class="widget-card scan-result-panel">
            <h3 class="scan-result-title">Hasil Scan</h3>

            <?php if (!empty($update_message)): ?>
                <div class="alert-success-box" style="background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;">
                    <?= htmlspecialchars($update_message); ?>
                </div>
            <?php endif; ?>

            <!-- Form Update -->
            <form method="POST" action="scan-barcode.php" id="formUpdate" style="display: none;">
                <input type="hidden" name="update_scanned_item" value="1">
                <input type="hidden" name="id_inventaris" id="res_id_inventaris">

                <div class="scan-detail-grid">
                    <div>
                        <label class="scan-field-label">Barcode / QR</label>
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

            <!-- State Kosong -->
            <div id="emptyState" class="scan-empty-state">
                Belum ada data yang dipindai. Silakan pilih mode scan atau masukkan kode manual.
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
    
    .scanner-box { 
        margin-top: 14px; 
        border: 1px solid #cbd5e1; 
        border-radius: 12px; 
        height: 300px; 
        background: #000; 
        overflow: hidden; 
        position: relative; 
    }
    #reader { 
        width: 100% !important; 
        height: 100% !important; 
        border: none !important; 
        background: transparent !important; 
    }
    #reader video { 
        width: 100% !important; 
        height: 100% !important; 
        object-fit: cover !important; 
        border-radius: 10px; 
    }
    #reader__dashboard_section_csr { display: none !important; }
    #reader__scan_region img { display: none; }

    .camera-fallback { 
        position: absolute;
        top: 0; left: 0; width: 100%; height: 100%;
        display: flex; align-items: center; justify-content: center; flex-direction: column;
        text-align: center; color: #94a3b8; background: #0f172a; 
    }
    .camera-fallback-icon { font-size: 40px; display: block; margin-bottom: 8px; }

    .scan-control-row { margin-top: 14px; display: flex; gap: 10px; flex-wrap: wrap; }
    .scan-camera-btn { flex: 1 1 180px; justify-content: center; }
    .scan-camera-start { background: #0f766e; }
    .scan-camera-stop { background: #64748b; }
    .scan-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
    .scan-field-label { display: block; margin-bottom: 8px; font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; }
    .scan-select { width: 100%; }
    .scan-save-row { margin-top: 20px; display: flex; gap: 12px; justify-content: flex-end; }
    .scan-empty-state { display: flex; align-items: center; justify-content: center; min-height: 320px; color: #64748b; text-align: center; padding: 20px; }

    @media (max-width: 767px) {
        .dashboard-container { padding: 16px !important; }
        .scan-page-title { font-size: 24px; margin-bottom: 16px; }
        .scan-layout { grid-template-columns: 1fr; gap: 16px; }
        .scan-panel, .scan-result-panel { padding: 16px; }
        .scanner-box { height: 220px !important; }
        .scan-control-row { flex-direction: column; }
        .scan-camera-btn, .scan-submit-btn { width: 100%; flex: 1 1 100%; }
        .scan-detail-grid { grid-template-columns: 1fr; gap: 14px; }
        .scan-save-row { justify-content: stretch; }
        .scan-save-row .btn-primary { width: 100%; justify-content: center; }
    }
</style>

<script>
    let html5QrCode = null;
    let currentMode = 'qr'; // Default mode 'qr' atau 'barcode'
    const barcodeInput = document.getElementById('barcodeInput');
    const btnCariManual = document.getElementById('btnCariManual');
    const formUpdate = document.getElementById('formUpdate');
    const emptyState = document.getElementById('emptyState');
    const startCameraBtn = document.getElementById('startCamera');
    const stopCameraBtn = document.getElementById('stopCamera');
    const cameraFallback = document.getElementById('cameraFallback');
    
    const btnModeQR = document.getElementById('btnModeQR');
    const btnModeBarcode = document.getElementById('btnModeBarcode');
    let isProcessing = false;

    async function processBarcode(code) {
        const cleanCode = code.trim();
        if (!cleanCode || isProcessing) return;
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
                        <strong style="font-size: 15px;">Kode Tidak Ditemukan</strong>
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

    async function startCamera() {
        if (!window.isSecureContext) return;

        try {
            if (!html5QrCode) {
                html5QrCode = new Html5Qrcode("reader");
            }

            // Jika kamera sedang aktif, matikan dulu sebelum mengganti format/mode
            if (html5QrCode.isScanning) {
                await html5QrCode.stop();
            }

            // Tentukan format berdasarkan mode yang dipilih
            let formatsToSupport = [];
            let qrboxConfig = { width: 250, height: 150 };

            if (currentMode === 'qr') {
                formatsToSupport = [Html5QrcodeSupportedFormats.QR_CODE];
                qrboxConfig = { width: 200, height: 200 };
            } else {
                // Barcode garis (Code 128, EAN, Code 39, dll)
                formatsToSupport = [
                    Html5QrcodeSupportedFormats.CODE_128,
                    Html5QrcodeSupportedFormats.CODE_39,
                    Html5QrcodeSupportedFormats.EAN_13,
                    Html5QrcodeSupportedFormats.EAN_8,
                    Html5QrcodeSupportedFormats.UPC_A,
                    Html5QrcodeSupportedFormats.UPC_E
                ];
                qrboxConfig = { width: 260, height: 120 }; // Kotak agak melebar pas untuk barcode garis
            }

            const config = { 
                fps: 15, 
                qrbox: qrboxConfig,
                formatsToSupport: formatsToSupport
            };

            await html5QrCode.start(
                { facingMode: "environment" },
                config,
                (decodedText, decodedResult) => {
                    barcodeInput.value = decodedText;
                    processBarcode(decodedText);
                },
                (errorMessage) => {}
            );

            cameraFallback.style.display = 'none';
            startCameraBtn.style.display = 'none';
            stopCameraBtn.style.display = 'inline-flex';
        } catch (err) {
            console.error('Kamera error:', err);
            cameraFallback.style.display = 'flex';
        }
    }

    async function stopCamera() {
        if (html5QrCode && html5QrCode.isScanning) {
            try {
                await html5QrCode.stop();
            } catch (err) {
                console.error('Gagal mematikan kamera:', err);
            }
        }
        startCameraBtn.style.display = 'inline-flex';
        stopCameraBtn.style.display = 'none';
        cameraFallback.style.display = 'flex';
    }

    // Event Listener Ganti Mode
    btnModeQR.addEventListener('click', () => {
        if (currentMode === 'qr') return;
        currentMode = 'qr';
        btnModeQR.style.background = '#0f766e';
        btnModeBarcode.style.background = '#475569';
        startCamera(); // Restart kamera dengan mode QR
    });

    btnModeBarcode.addEventListener('click', () => {
        if (currentMode === 'barcode') return;
        currentMode = 'barcode';
        btnModeBarcode.style.background = '#0f766e';
        btnModeQR.style.background = '#475569';
        startCamera(); // Restart kamera dengan mode Barcode garis
    });

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

    // Otomatis nyalakan kamera saat halaman dimuat
    window.addEventListener('load', startCamera);
</script>

<?php include 'includes/footer.php'; ?>