<?php
session_start();
include 'config/koneksi.php';

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

$active_page = 'manajemen-ruangan';
$page_title = 'Manajemen Ruangan';
$breadcrumb = 'Manajemen Ruangan';

// Ambil semua ruangan
$q_ruangan = mysqli_query($koneksi, "
    SELECT r.id_ruangan, r.nama_ruangan, COUNT(i.id_inventaris) AS total_barang 
    FROM ruangan r 
    LEFT JOIN inventaris i ON r.id_ruangan = i.ruangan_id 
    GROUP BY r.id_ruangan, r.nama_ruangan 
    ORDER BY r.nama_ruangan ASC
");

// AJAX - Tambah Ruangan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $nama_ruangan = mysqli_real_escape_string($koneksi, trim($_POST['nama_ruangan']));

    $response = ['status' => 'error', 'message' => ''];

    if (empty($nama_ruangan)) {
        $response['message'] = 'Nama ruangan tidak boleh kosong!';
    } else {
        // Cek ruangan sudah ada atau belum
        $check_ruangan = mysqli_query($koneksi, "SELECT id_ruangan FROM ruangan WHERE nama_ruangan = '$nama_ruangan'");
        if (mysqli_num_rows($check_ruangan) > 0) {
            $response['message'] = 'Ruangan sudah ada!';
        } else {
            $insert = mysqli_query($koneksi, "INSERT INTO ruangan (nama_ruangan) VALUES ('$nama_ruangan')");

            if ($insert) {
                $response['status'] = 'success';
                $response['message'] = 'Ruangan berhasil ditambahkan!';
            } else {
                $response['message'] = 'Gagal menambahkan ruangan: ' . mysqli_error($koneksi);
            }
        }
    }

    echo json_encode($response);
    exit;
}

// AJAX - Edit Ruangan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id_ruangan = (int)$_POST['id_ruangan'];
    $nama_ruangan = mysqli_real_escape_string($koneksi, trim($_POST['nama_ruangan']));

    $response = ['status' => 'error', 'message' => ''];

    if (empty($nama_ruangan)) {
        $response['message'] = 'Nama ruangan tidak boleh kosong!';
    } else {
        // Cek nama ruangan sudah ada atau belum (kecuali ruangan yang sedang diedit)
        $check_ruangan = mysqli_query($koneksi, "SELECT id_ruangan FROM ruangan WHERE nama_ruangan = '$nama_ruangan' AND id_ruangan != $id_ruangan");
        if (mysqli_num_rows($check_ruangan) > 0) {
            $response['message'] = 'Ruangan sudah ada!';
        } else {
            $update = mysqli_query($koneksi, "UPDATE ruangan SET nama_ruangan = '$nama_ruangan' WHERE id_ruangan = $id_ruangan");

            if ($update) {
                $response['status'] = 'success';
                $response['message'] = 'Ruangan berhasil diperbarui!';
            } else {
                $response['message'] = 'Gagal memperbarui ruangan: ' . mysqli_error($koneksi);
            }
        }
    }

    echo json_encode($response);
    exit;
}

// AJAX - Hapus Ruangan (dengan cascade delete ke inventaris)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id_ruangan = (int)$_POST['id_ruangan'];

    $response = ['status' => 'error', 'message' => ''];

    // Mulai transaction
    mysqli_begin_transaction($koneksi);

    try {
        // Hapus semua inventaris di ruangan ini terlebih dahulu
        $delete_inventaris = mysqli_query($koneksi, "DELETE FROM inventaris WHERE ruangan_id = $id_ruangan");
        
        if (!$delete_inventaris) {
            throw new Exception('Gagal menghapus inventaris: ' . mysqli_error($koneksi));
        }

        // Hapus ruangan
        $delete_ruangan = mysqli_query($koneksi, "DELETE FROM ruangan WHERE id_ruangan = $id_ruangan");
        
        if (!$delete_ruangan) {
            throw new Exception('Gagal menghapus ruangan: ' . mysqli_error($koneksi));
        }

        // Commit transaction
        mysqli_commit($koneksi);
        
        $response['status'] = 'success';
        $response['message'] = 'Ruangan dan semua inventaris berhasil dihapus!';
    } catch (Exception $e) {
        // Rollback transaction
        mysqli_rollback($koneksi);
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

include 'includes/header.php';
?>

<div class="dashboard-container">
    <div class="page-header">
        <h1>Manajemen Ruangan</h1>
        <p>Kelola ruangan tempat penyimpanan barang inventaris</p>
    </div>

    <div class="action-bar" style="margin-bottom: 20px;">
        <button type="button" class="btn-primary" onclick="openAddRuanganModal()" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);">
            <i class="bi bi-plus-circle"></i> Tambah Ruangan
        </button>
    </div>

    <div class="widget-card table-wrapper">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Nama Ruangan</th>
                    <th>Total Barang</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($q_ruangan && mysqli_num_rows($q_ruangan) > 0): ?>
                    <?php while ($ruangan = mysqli_fetch_assoc($q_ruangan)): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($ruangan['nama_ruangan']); ?></strong>
                            </td>
                            <td>
                                <span class="badge" style="background: rgba(59, 130, 246, 0.12); color: #1e40af; padding: 4px 10px; border-radius: 4px; font-size: 12px;">
                                    <?= (int)$ruangan['total_barang']; ?> barang
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <button type="button" class="btn-action btn-edit" onclick="openEditRuanganModal(<?= $ruangan['id_ruangan']; ?>, '<?= htmlspecialchars($ruangan['nama_ruangan']); ?>')">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </button>
                                    <button type="button" class="btn-action btn-delete" onclick="confirmDeleteRuangan(<?= $ruangan['id_ruangan']; ?>, '<?= htmlspecialchars($ruangan['nama_ruangan']); ?>')">
                                        <i class="bi bi-trash"></i> Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align: center; color: #999; padding: 30px;">
                            Belum ada data ruangan
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL TAMBAH RUANGAN -->
<div id="addRuanganModal" class="modal-backdrop">
    <div class="modal-card">
        <div class="modal-header">
            <span class="modal-title">Tambah Ruangan</span>
            <span class="close-btn" onclick="closeAddRuanganModal()">&times;</span>
        </div>
        <form id="addRuanganForm" onsubmit="submitAddRuangan(event)">
            <div class="modal-body" style="padding: 24px;">
                <div class="form-group">
                    <label for="addNamaRuangan">Nama Ruangan</label>
                    <input type="text" id="addNamaRuangan" name="nama_ruangan" class="form-input" required placeholder="Misal: Ruang Guru, Lab Komputer, Perpustakaan">
                </div>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end; padding: 16px 24px; border-top: 1px solid #e2e8f0;">
                <button type="button" class="btn-modal-close" onclick="closeAddRuanganModal()">Batal</button>
                <button type="submit" class="btn-modal-submit">Tambah Ruangan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT RUANGAN -->
<div id="editRuanganModal" class="modal-backdrop">
    <div class="modal-card">
        <div class="modal-header">
            <span class="modal-title">Edit Ruangan</span>
            <span class="close-btn" onclick="closeEditRuanganModal()">&times;</span>
        </div>
        <form id="editRuanganForm" onsubmit="submitEditRuangan(event)">
            <input type="hidden" id="editRuanganId" name="id_ruangan">
            <div class="modal-body" style="padding: 24px;">
                <div class="form-group">
                    <label for="editNamaRuangan">Nama Ruangan</label>
                    <input type="text" id="editNamaRuangan" name="nama_ruangan" class="form-input" required>
                </div>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end; padding: 16px 24px; border-top: 1px solid #e2e8f0;">
                <button type="button" class="btn-modal-close" onclick="closeEditRuanganModal()">Batal</button>
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
        document.getElementById('addRuanganModal').classList.remove('show');
        document.getElementById('editRuanganModal').classList.remove('show');
    }

    function openAddRuanganModal() {
        closeAllModals();
        document.getElementById('addRuanganForm').reset();
        document.getElementById('addRuanganModal').classList.add('show');
    }

    function closeAddRuanganModal() {
        document.getElementById('addRuanganModal').classList.remove('show');
        document.getElementById('addRuanganForm').reset();
    }

    function openEditRuanganModal(id, nama) {
        closeAllModals();
        document.getElementById('editRuanganId').value = id;
        document.getElementById('editNamaRuangan').value = nama;
        document.getElementById('editRuanganModal').classList.add('show');
    }

    function closeEditRuanganModal() {
        document.getElementById('editRuanganModal').classList.remove('show');
    }

    function submitAddRuangan(event) {
        event.preventDefault();
        
        const formData = new FormData(document.getElementById('addRuanganForm'));
        formData.append('action', 'add');

        fetch('manajemen-ruangan.php', {
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

    function submitEditRuangan(event) {
        event.preventDefault();
        
        const formData = new FormData(document.getElementById('editRuanganForm'));
        formData.append('action', 'edit');

        fetch('manajemen-ruangan.php', {
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

    function confirmDeleteRuangan(id, nama) {
        if (confirm(`Apakah Anda yakin ingin menghapus ruangan "${nama}" dan semua inventaris di dalamnya? Tindakan ini tidak bisa dibatalkan!`)) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id_ruangan', id);

            fetch('manajemen-ruangan.php', {
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
        const addModal = document.getElementById('addRuanganModal');
        const editModal = document.getElementById('editRuanganModal');
        
        if (event.target === addModal) closeAddRuanganModal();
        if (event.target === editModal) closeEditRuanganModal();
    });
</script>

<?php include 'includes/footer.php'; ?>
