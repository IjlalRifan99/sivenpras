<?php
session_start();
include 'config/koneksi.php';

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

$active_page = 'kategori';
$page_title = 'Manajemen Kategori';
$breadcrumb = 'Manajemen Kategori';

// Ambil semua kategori
$q_kategori = mysqli_query($koneksi, "SELECT * FROM kategori ORDER BY nama_kategori ASC");

// AJAX - Tambah Kategori
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $nama_kategori = mysqli_real_escape_string($koneksi, trim($_POST['nama_kategori']));
    $keterangan = mysqli_real_escape_string($koneksi, trim($_POST['keterangan'] ?? ''));

    $response = ['status' => 'error', 'message' => ''];

    if (empty($nama_kategori)) {
        $response['message'] = 'Nama kategori tidak boleh kosong!';
    } else {
        // Cek kategori sudah ada atau belum
        $check_kat = mysqli_query($koneksi, "SELECT id_kategori FROM kategori WHERE nama_kategori = '$nama_kategori'");
        if (mysqli_num_rows($check_kat) > 0) {
            $response['message'] = 'Kategori sudah ada!';
        } else {
            $insert = mysqli_query($koneksi, "
                INSERT INTO kategori (nama_kategori, keterangan)
                VALUES ('$nama_kategori', '$keterangan')
            ");

            if ($insert) {
                $response['status'] = 'success';
                $response['message'] = 'Kategori berhasil ditambahkan!';
            } else {
                $response['message'] = 'Gagal menambahkan kategori: ' . mysqli_error($koneksi);
            }
        }
    }

    echo json_encode($response);
    exit;
}

// AJAX - Edit Kategori
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id_kategori = (int)$_POST['id_kategori'];
    $nama_kategori = mysqli_real_escape_string($koneksi, trim($_POST['nama_kategori']));
    $keterangan = mysqli_real_escape_string($koneksi, trim($_POST['keterangan'] ?? ''));

    $response = ['status' => 'error', 'message' => ''];

    if (empty($nama_kategori)) {
        $response['message'] = 'Nama kategori tidak boleh kosong!';
    } else {
        // Cek nama kategori sudah ada atau belum (kecuali kategori yang sedang diedit)
        $check_kat = mysqli_query($koneksi, "SELECT id_kategori FROM kategori WHERE nama_kategori = '$nama_kategori' AND id_kategori != $id_kategori");
        if (mysqli_num_rows($check_kat) > 0) {
            $response['message'] = 'Kategori sudah ada!';
        } else {
            $update = mysqli_query($koneksi, "
                UPDATE kategori 
                SET nama_kategori = '$nama_kategori', keterangan = '$keterangan'
                WHERE id_kategori = $id_kategori
            ");

            if ($update) {
                $response['status'] = 'success';
                $response['message'] = 'Kategori berhasil diperbarui!';
            } else {
                $response['message'] = 'Gagal memperbarui kategori: ' . mysqli_error($koneksi);
            }
        }
    }

    echo json_encode($response);
    exit;
}

// AJAX - Hapus Kategori
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id_kategori = (int)$_POST['id_kategori'];

    $response = ['status' => 'error', 'message' => ''];

    // Cek apakah kategori sudah digunakan
    $check_usage = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM barang WHERE kategori_id = $id_kategori");
    $usage = mysqli_fetch_assoc($check_usage);
    
    if ($usage['total'] > 0) {
        $response['message'] = 'Tidak bisa menghapus kategori yang sudah digunakan (' . $usage['total'] . ' barang)!';
    } else {
        $delete = mysqli_query($koneksi, "DELETE FROM kategori WHERE id_kategori = $id_kategori");

        if ($delete) {
            $response['status'] = 'success';
            $response['message'] = 'Kategori berhasil dihapus!';
        } else {
            $response['message'] = 'Gagal menghapus kategori: ' . mysqli_error($koneksi);
        }
    }

    echo json_encode($response);
    exit;
}

include 'includes/header.php';
?>

<div class="dashboard-container">
    <div class="page-header">
        <h1>Manajemen Kategori</h1>
        <p>Kelola kategori barang inventaris</p>
    </div>

    <div class="action-bar" style="margin-bottom: 20px;">
        <button type="button" class="btn-primary" onclick="openAddKategoriModal()" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);">
            <i class="bi bi-plus-circle"></i> Tambah Kategori
        </button>
    </div>

    <div class="widget-card table-wrapper">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Nama Kategori</th>
                    <th>Keterangan</th>
                    <th>Total Barang</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($q_kategori && mysqli_num_rows($q_kategori) > 0): ?>
                    <?php while ($kategori = mysqli_fetch_assoc($q_kategori)): 
                        // Hitung total barang per kategori
                        $q_count = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM barang WHERE kategori_id = " . $kategori['id_kategori']);
                        $count_data = mysqli_fetch_assoc($q_count);
                        $total_barang = $count_data['total'] ?? 0;
                    ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($kategori['nama_kategori']); ?></strong>
                            </td>
                            <td><?= htmlspecialchars($kategori['keterangan'] ?? '-'); ?></td>
                            <td>
                                <span class="badge" style="background: rgba(34, 197, 94, 0.12); color: #15803d; padding: 4px 10px; border-radius: 4px; font-size: 12px;">
                                    <?= $total_barang; ?> barang
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <button type="button" class="btn-action btn-edit" onclick="openEditKategoriModal(<?= $kategori['id_kategori']; ?>, '<?= htmlspecialchars($kategori['nama_kategori']); ?>', '<?= htmlspecialchars($kategori['keterangan'] ?? ''); ?>')">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </button>
                                    <button type="button" class="btn-action btn-delete" onclick="confirmDeleteKategori(<?= $kategori['id_kategori']; ?>, '<?= htmlspecialchars($kategori['nama_kategori']); ?>')">
                                        <i class="bi bi-trash"></i> Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: #999; padding: 30px;">
                            Belum ada data kategori
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL TAMBAH KATEGORI -->
<div id="addKategoriModal" class="modal-backdrop">
    <div class="modal-card">
        <div class="modal-header">
            <span class="modal-title">Tambah Kategori</span>
            <span class="close-btn" onclick="closeAddKategoriModal()">&times;</span>
        </div>
        <form id="addKategoriForm" onsubmit="submitAddKategori(event)">
            <div class="modal-body" style="padding: 24px;">
                <div class="form-group">
                    <label for="addNamaKategori">Nama Kategori</label>
                    <input type="text" id="addNamaKategori" name="nama_kategori" class="form-input" required placeholder="Misal: Elektronik, Furniture">
                </div>
                <div class="form-group">
                    <label for="addKeterangan">Keterangan</label>
                    <textarea id="addKeterangan" name="keterangan" class="form-input" style="resize: vertical; min-height: 80px;" placeholder="Deskripsi kategori (opsional)"></textarea>
                </div>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end; padding: 16px 24px; border-top: 1px solid #e2e8f0;">
                <button type="button" class="btn-modal-close" onclick="closeAddKategoriModal()">Batal</button>
                <button type="submit" class="btn-modal-submit">Tambah Kategori</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT KATEGORI -->
<div id="editKategoriModal" class="modal-backdrop">
    <div class="modal-card">
        <div class="modal-header">
            <span class="modal-title">Edit Kategori</span>
            <span class="close-btn" onclick="closeEditKategoriModal()">&times;</span>
        </div>
        <form id="editKategoriForm" onsubmit="submitEditKategori(event)">
            <input type="hidden" id="editKategoriId" name="id_kategori">
            <div class="modal-body" style="padding: 24px;">
                <div class="form-group">
                    <label for="editNamaKategori">Nama Kategori</label>
                    <input type="text" id="editNamaKategori" name="nama_kategori" class="form-input" required>
                </div>
                <div class="form-group">
                    <label for="editKeterangan">Keterangan</label>
                    <textarea id="editKeterangan" name="keterangan" class="form-input" style="resize: vertical; min-height: 80px;"></textarea>
                </div>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end; padding: 16px 24px; border-top: 1px solid #e2e8f0;">
                <button type="button" class="btn-modal-close" onclick="closeEditKategoriModal()">Batal</button>
                <button type="submit" class="btn-modal-submit">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<style>
    /* --- STYLE MODAL --- */
    .modal-backdrop {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(4px);
    }

    .modal-backdrop.show {
        display: flex !important;
        animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    @keyframes slideUp {
        from {
            transform: translateY(20px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .modal-card {
        width: 420px;
        max-width: 90%;
        background-color: #ffffff;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        overflow: hidden;
        animation: slideUp 0.3s ease-out;
        border: 1px solid #f0f4f8;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
        background: linear-gradient(135deg, #164e5a 0%, #256b7a 100%);
        border-bottom: none;
    }

    .modal-title {
        font-weight: 700;
        font-size: 18px;
        color: #ffffff;
        letter-spacing: 0.5px;
    }

    .close-btn {
        cursor: pointer;
        font-size: 28px;
        font-weight: 300;
        color: #ffffff;
        line-height: 1;
        transition: transform 0.2s ease;
        width: 32px;
        text-align: center;
    }

    .close-btn:hover {
        transform: scale(1.1);
    }

    /* --- FORM STYLES --- */
    .form-group {
        margin-bottom: 16px;
        display: flex;
        flex-direction: column;
    }

    .form-group label {
        font-weight: 600;
        color: #334155;
        margin-bottom: 6px;
        font-size: 14px;
    }

    .form-input {
        padding: 10px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 14px;
        font-family: inherit;
        transition: all 0.2s ease;
    }

    .form-input:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .btn-modal-close {
        padding: 10px 16px;
        background: #e2e8f0;
        color: #334155;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.2s ease;
    }

    .btn-modal-close:hover {
        background: #cbd5e1;
    }

    .btn-modal-submit {
        padding: 10px 20px;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: #fff;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.2s ease;
    }

    .btn-modal-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    .btn-action {
        padding: 6px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.2s ease;
        background: #fff;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .btn-action:hover {
        transform: translateY(-2px);
    }

    .btn-edit {
        color: #3b82f6;
        border-color: #3b82f6;
    }

    .btn-edit:hover {
        background: #eff6ff;
    }

    .btn-delete {
        color: #ef4444;
        border-color: #ef4444;
    }

    .btn-delete:hover {
        background: #fef2f2;
    }

    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 12px;
    }
</style>

<script>
    // Tutup semua modal
    function closeAllModals() {
        document.getElementById('addKategoriModal').classList.remove('show');
        document.getElementById('editKategoriModal').classList.remove('show');
    }

    function openAddKategoriModal() {
        closeAllModals();
        document.getElementById('addKategoriForm').reset();
        document.getElementById('addKategoriModal').classList.add('show');
    }

    function closeAddKategoriModal() {
        document.getElementById('addKategoriModal').classList.remove('show');
        document.getElementById('addKategoriForm').reset();
    }

    function openEditKategoriModal(id, nama, keterangan) {
        closeAllModals();
        document.getElementById('editKategoriId').value = id;
        document.getElementById('editNamaKategori').value = nama;
        document.getElementById('editKeterangan').value = keterangan;
        document.getElementById('editKategoriModal').classList.add('show');
    }

    function closeEditKategoriModal() {
        document.getElementById('editKategoriModal').classList.remove('show');
    }

    function submitAddKategori(event) {
        event.preventDefault();
        
        const formData = new FormData(document.getElementById('addKategoriForm'));
        formData.append('action', 'add');

        fetch('manajemen-kategori.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function submitEditKategori(event) {
        event.preventDefault();
        
        const formData = new FormData(document.getElementById('editKategoriForm'));
        formData.append('action', 'edit');

        fetch('manajemen-kategori.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function confirmDeleteKategori(id, nama) {
        if (confirm(`Apakah Anda yakin ingin menghapus kategori "${nama}"? Tindakan ini tidak bisa dibatalkan!`)) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id_kategori', id);

            fetch('manajemen-kategori.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        }
    }

    // Close modal ketika klik di luar modal
    window.addEventListener('click', function(event) {
        const addModal = document.getElementById('addKategoriModal');
        const editModal = document.getElementById('editKategoriModal');
        
        if (event.target === addModal) closeAddKategoriModal();
        if (event.target === editModal) closeEditKategoriModal();
    });
</script>

<?php include 'includes/footer.php'; ?>
