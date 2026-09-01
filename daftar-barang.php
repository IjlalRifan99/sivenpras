<?php
session_start();
include 'config/koneksi.php';

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

$active_page = 'barang';
$page_title = 'Daftar Barang';
$breadcrumb = 'Daftar Barang';

// Ambil semua barang dengan total inventaris
$q_barang = mysqli_query($koneksi, "
    SELECT b.id_barang, b.nama_barang, k.nama_kategori, 
           COUNT(i.id_inventaris) AS total_inventaris
    FROM barang b
    LEFT JOIN kategori k ON b.kategori_id = k.id_kategori
    LEFT JOIN inventaris i ON b.id_barang = i.barang_id
    GROUP BY b.id_barang, b.nama_barang, k.nama_kategori
    ORDER BY b.nama_barang ASC
");

// Ambil kategori untuk form
$q_kategori = mysqli_query($koneksi, "SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori ASC");

// AJAX - Tambah Barang
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $nama_barang = mysqli_real_escape_string($koneksi, trim($_POST['nama_barang']));
    $kategori_id = (int)$_POST['kategori_id'];

    $response = ['status' => 'error', 'message' => ''];

    if (empty($nama_barang) || $kategori_id <= 0) {
        $response['message'] = 'Nama barang dan kategori tidak boleh kosong!';
    } else {
        // Cek barang sudah ada atau belum
        $check_barang = mysqli_query($koneksi, "SELECT id_barang FROM barang WHERE nama_barang = '$nama_barang'");
        if (mysqli_num_rows($check_barang) > 0) {
            $response['message'] = 'Barang sudah ada!';
        } else {
            $insert = mysqli_query($koneksi, "
                INSERT INTO barang (nama_barang, kategori_id)
                VALUES ('$nama_barang', $kategori_id)
            ");

            if ($insert) {
                $response['status'] = 'success';
                $response['message'] = 'Barang berhasil ditambahkan!';
            } else {
                $response['message'] = 'Gagal menambahkan barang: ' . mysqli_error($koneksi);
            }
        }
    }

    echo json_encode($response);
    exit;
}

// AJAX - Edit Barang
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id_barang = (int)$_POST['id_barang'];
    $nama_barang = mysqli_real_escape_string($koneksi, trim($_POST['nama_barang']));
    $kategori_id = (int)$_POST['kategori_id'];

    $response = ['status' => 'error', 'message' => ''];

    if (empty($nama_barang) || $kategori_id <= 0) {
        $response['message'] = 'Nama barang dan kategori tidak boleh kosong!';
    } else {
        // Cek nama barang sudah ada atau belum (kecuali barang yang sedang diedit)
        $check_barang = mysqli_query($koneksi, "SELECT id_barang FROM barang WHERE nama_barang = '$nama_barang' AND id_barang != $id_barang");
        if (mysqli_num_rows($check_barang) > 0) {
            $response['message'] = 'Barang sudah ada!';
        } else {
            $update = mysqli_query($koneksi, "
                UPDATE barang 
                SET nama_barang = '$nama_barang', kategori_id = $kategori_id
                WHERE id_barang = $id_barang
            ");

            if ($update) {
                $response['status'] = 'success';
                $response['message'] = 'Barang berhasil diperbarui!';
            } else {
                $response['message'] = 'Gagal memperbarui barang: ' . mysqli_error($koneksi);
            }
        }
    }

    echo json_encode($response);
    exit;
}

// AJAX - Hapus Barang (dengan cascade delete ke inventaris)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id_barang = (int)$_POST['id_barang'];

    $response = ['status' => 'error', 'message' => ''];

    // Mulai transaction
    mysqli_begin_transaction($koneksi);

    try {
        // Hapus semua inventaris barang ini terlebih dahulu
        $delete_inventaris = mysqli_query($koneksi, "DELETE FROM inventaris WHERE barang_id = $id_barang");
        
        if (!$delete_inventaris) {
            throw new Exception('Gagal menghapus inventaris: ' . mysqli_error($koneksi));
        }

        // Hapus barang
        $delete_barang = mysqli_query($koneksi, "DELETE FROM barang WHERE id_barang = $id_barang");
        
        if (!$delete_barang) {
            throw new Exception('Gagal menghapus barang: ' . mysqli_error($koneksi));
        }

        // Commit transaction
        mysqli_commit($koneksi);
        
        $response['status'] = 'success';
        $response['message'] = 'Barang dan semua inventaris berhasil dihapus!';
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
        <h1>Daftar Barang</h1>
        <p>Kelola katalog barang inventaris</p>
    </div>

    <div class="action-bar" style="margin-bottom: 20px;">
        <button type="button" class="btn-primary" onclick="openAddBarangModal()" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);">
            <i class="bi bi-plus-circle"></i> Tambah Barang
        </button>
    </div>

    <div class="widget-card table-wrapper">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Nama Barang</th>
                    <th>Kategori</th>
                    <th>Total Inventaris</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($q_barang && mysqli_num_rows($q_barang) > 0): ?>
                    <?php while ($barang = mysqli_fetch_assoc($q_barang)): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($barang['nama_barang']); ?></strong>
                            </td>
                            <td>
                                <?= htmlspecialchars($barang['nama_kategori'] ?? '-'); ?>
                            </td>
                            <td>
                                <span class="badge" style="background: rgba(59, 130, 246, 0.12); color: #1e40af; padding: 4px 10px; border-radius: 4px; font-size: 12px;">
                                    <?= (int)$barang['total_inventaris']; ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <button type="button" class="btn-action btn-edit" onclick="openEditBarangModal(<?= $barang['id_barang']; ?>, '<?= htmlspecialchars($barang['nama_barang']); ?>')">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </button>
                                    <button type="button" class="btn-action btn-delete" onclick="confirmDeleteBarang(<?= $barang['id_barang']; ?>, '<?= htmlspecialchars($barang['nama_barang']); ?>')">
                                        <i class="bi bi-trash"></i> Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: #999; padding: 30px;">
                            Belum ada data barang
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL TAMBAH BARANG -->
<div id="addBarangModal" class="modal-backdrop">
    <div class="modal-card">
        <div class="modal-header">
            <span class="modal-title">Tambah Barang</span>
            <span class="close-btn" onclick="closeAddBarangModal()">&times;</span>
        </div>
        <form id="addBarangForm" onsubmit="submitAddBarang(event)">
            <div class="modal-body" style="padding: 24px;">
                <div class="form-group">
                    <label for="addNamaBarang">Nama Barang</label>
                    <input type="text" id="addNamaBarang" name="nama_barang" class="form-input" required placeholder="Misal: Meja Guru, Kursi Siswa">
                </div>
                <div class="form-group">
                    <label for="addKategori">Kategori</label>
                    <select id="addKategori" name="kategori_id" class="form-input" required>
                        <option value="">Pilih Kategori</option>
                        <?php 
                        $q_kat = mysqli_query($koneksi, "SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori ASC");
                        while ($kat = mysqli_fetch_assoc($q_kat)): 
                        ?>
                            <option value="<?= $kat['id_kategori']; ?>"><?= htmlspecialchars($kat['nama_kategori']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end; padding: 16px 24px; border-top: 1px solid #e2e8f0;">
                <button type="button" class="btn-modal-close" onclick="closeAddBarangModal()">Batal</button>
                <button type="submit" class="btn-modal-submit">Tambah Barang</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT BARANG -->
<div id="editBarangModal" class="modal-backdrop">
    <div class="modal-card">
        <div class="modal-header">
            <span class="modal-title">Edit Barang</span>
            <span class="close-btn" onclick="closeEditBarangModal()">&times;</span>
        </div>
        <form id="editBarangForm" onsubmit="submitEditBarang(event)">
            <input type="hidden" id="editBarangId" name="id_barang">
            <div class="modal-body" style="padding: 24px;">
                <div class="form-group">
                    <label for="editNamaBarang">Nama Barang</label>
                    <input type="text" id="editNamaBarang" name="nama_barang" class="form-input" required>
                </div>
                <div class="form-group">
                    <label for="editKategori">Kategori</label>
                    <select id="editKategori" name="kategori_id" class="form-input" required>
                        <option value="">Pilih Kategori</option>
                        <?php 
                        $q_kat = mysqli_query($koneksi, "SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori ASC");
                        while ($kat = mysqli_fetch_assoc($q_kat)): 
                        ?>
                            <option value="<?= $kat['id_kategori']; ?>"><?= htmlspecialchars($kat['nama_kategori']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end; padding: 16px 24px; border-top: 1px solid #e2e8f0;">
                <button type="button" class="btn-modal-close" onclick="closeEditBarangModal()">Batal</button>
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
        document.getElementById('addBarangModal').classList.remove('show');
        document.getElementById('editBarangModal').classList.remove('show');
    }

    function openAddBarangModal() {
        closeAllModals();
        document.getElementById('addBarangForm').reset();
        document.getElementById('addBarangModal').classList.add('show');
    }

    function closeAddBarangModal() {
        document.getElementById('addBarangModal').classList.remove('show');
        document.getElementById('addBarangForm').reset();
    }

    function openEditBarangModal(id, nama) {
        closeAllModals();
        document.getElementById('editBarangId').value = id;
        document.getElementById('editNamaBarang').value = nama;
        
        // Fetch barang data untuk get kategori
        fetch('daftar-barang.php?get_barang=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.kategori_id) {
                    document.getElementById('editKategori').value = data.kategori_id;
                }
                document.getElementById('editBarangModal').classList.add('show');
            });
    }

    function closeEditBarangModal() {
        document.getElementById('editBarangModal').classList.remove('show');
    }

    function submitAddBarang(event) {
        event.preventDefault();
        
        const formData = new FormData(document.getElementById('addBarangForm'));
        formData.append('action', 'add');

        fetch('daftar-barang.php', {
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

    function submitEditBarang(event) {
        event.preventDefault();
        
        const formData = new FormData(document.getElementById('editBarangForm'));
        formData.append('action', 'edit');

        fetch('daftar-barang.php', {
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

    function confirmDeleteBarang(id, nama) {
        if (confirm(`Apakah Anda yakin ingin menghapus barang "${nama}" dan semua inventaris barang ini? Tindakan ini tidak bisa dibatalkan!`)) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id_barang', id);

            fetch('daftar-barang.php', {
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
        const addModal = document.getElementById('addBarangModal');
        const editModal = document.getElementById('editBarangModal');
        
        if (event.target === addModal) closeAddBarangModal();
        if (event.target === editModal) closeEditBarangModal();
    });
</script>

<?php include 'includes/footer.php'; ?>
