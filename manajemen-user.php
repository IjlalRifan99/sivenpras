<?php
session_start();
include 'config/koneksi.php';

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

$active_page = 'user';
$page_title = 'Manajemen User';
$breadcrumb = 'Manajemen User';

// Ambil semua user
$q_users = mysqli_query($koneksi, "SELECT * FROM users ORDER BY username ASC");

// AJAX - Tambah User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $username = mysqli_real_escape_string($koneksi, trim($_POST['username']));
    $password = $_POST['password'];
    $nama_lengkap = mysqli_real_escape_string($koneksi, trim($_POST['nama_lengkap'] ?? ''));
    $email = mysqli_real_escape_string($koneksi, trim($_POST['email'] ?? ''));
    $role = mysqli_real_escape_string($koneksi, $_POST['role'] ?? 'user');

    $response = ['status' => 'error', 'message' => ''];

    if (empty($username) || empty($password)) {
        $response['message'] = 'Username dan password tidak boleh kosong!';
    } else {
        // Cek username sudah ada atau belum
        $check_user = mysqli_query($koneksi, "SELECT id_user FROM users WHERE username = '$username'");
        if (mysqli_num_rows($check_user) > 0) {
            $response['message'] = 'Username sudah terdaftar!';
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            
            $insert = mysqli_query($koneksi, "
                INSERT INTO users (username, password, nama_lengkap, email, role, created_at)
                VALUES ('$username', '$hashed_password', '$nama_lengkap', '$email', '$role', NOW())
            ");

            if ($insert) {
                $response['status'] = 'success';
                $response['message'] = 'User berhasil ditambahkan!';
            } else {
                $response['message'] = 'Gagal menambahkan user: ' . mysqli_error($koneksi);
            }
        }
    }

    echo json_encode($response);
    exit;
}

// AJAX - Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id_user = (int)$_POST['id_user'];
    $username = mysqli_real_escape_string($koneksi, trim($_POST['username']));
    $nama_lengkap = mysqli_real_escape_string($koneksi, trim($_POST['nama_lengkap'] ?? ''));
    $email = mysqli_real_escape_string($koneksi, trim($_POST['email'] ?? ''));
    $role = mysqli_real_escape_string($koneksi, $_POST['role'] ?? 'user');

    $response = ['status' => 'error', 'message' => ''];

    if (empty($username)) {
        $response['message'] = 'Username tidak boleh kosong!';
    } else {
        // Cek username sudah ada atau belum (kecuali user yang sedang diedit)
        $check_user = mysqli_query($koneksi, "SELECT id_user FROM users WHERE username = '$username' AND id_user != $id_user");
        if (mysqli_num_rows($check_user) > 0) {
            $response['message'] = 'Username sudah terdaftar!';
        } else {
            $update = mysqli_query($koneksi, "
                UPDATE users 
                SET username = '$username', nama_lengkap = '$nama_lengkap', email = '$email', role = '$role'
                WHERE id_user = $id_user
            ");

            if ($update) {
                $response['status'] = 'success';
                $response['message'] = 'User berhasil diperbarui!';
            } else {
                $response['message'] = 'Gagal memperbarui user: ' . mysqli_error($koneksi);
            }
        }
    }

    echo json_encode($response);
    exit;
}

// AJAX - Reset Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $id_user = (int)$_POST['id_user'];
    $new_password = $_POST['new_password'];

    $response = ['status' => 'error', 'message' => ''];

    if (empty($new_password)) {
        $response['message'] = 'Password tidak boleh kosong!';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
        
        $update = mysqli_query($koneksi, "
            UPDATE users 
            SET password = '$hashed_password'
            WHERE id_user = $id_user
        ");

        if ($update) {
            $response['status'] = 'success';
            $response['message'] = 'Password berhasil direset!';
        } else {
            $response['message'] = 'Gagal mereset password: ' . mysqli_error($koneksi);
        }
    }

    echo json_encode($response);
    exit;
}

// AJAX - Hapus User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id_user = (int)$_POST['id_user'];

    $response = ['status' => 'error', 'message' => ''];

    if ($id_user == $_SESSION['user_id']) {
        $response['message'] = 'Anda tidak bisa menghapus akun sendiri!';
    } else {
        $delete = mysqli_query($koneksi, "DELETE FROM users WHERE id_user = $id_user");

        if ($delete) {
            $response['status'] = 'success';
            $response['message'] = 'User berhasil dihapus!';
        } else {
            $response['message'] = 'Gagal menghapus user: ' . mysqli_error($koneksi);
        }
    }

    echo json_encode($response);
    exit;
}

include 'includes/header.php';
?>

<div class="dashboard-container">
    <div class="page-header">
        <h1>Manajemen User</h1>
        <p>Kelola pengguna sistem inventaris</p>
    </div>

    <div class="action-bar" style="margin-bottom: 20px;">
        <button type="button" class="btn-primary" onclick="openAddUserModal()" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);">
            <i class="bi bi-plus-circle"></i> Tambah User
        </button>
    </div>

    <div class="widget-card table-wrapper">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Nama Lengkap</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($q_users && mysqli_num_rows($q_users) > 0): ?>
                    <?php while ($user = mysqli_fetch_assoc($q_users)): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($user['username']); ?></strong>
                            </td>
                            <td><?= htmlspecialchars($user['nama_lengkap'] ?? '-'); ?></td>
                            <td><?= htmlspecialchars($user['email'] ?? '-'); ?></td>
                            <td>
                                <span class="badge" style="background: rgba(59, 130, 246, 0.12); color: #1e40af; padding: 4px 10px; border-radius: 4px; font-size: 12px;">
                                    <?= htmlspecialchars($user['role'] ?? 'user'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="dot green"></span> Aktif
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <button type="button" class="btn-action btn-edit" onclick="openEditUserModal(<?= $user['id_user']; ?>, '<?= htmlspecialchars($user['username']); ?>', '<?= htmlspecialchars($user['nama_lengkap'] ?? ''); ?>', '<?= htmlspecialchars($user['email'] ?? ''); ?>', '<?= htmlspecialchars($user['role'] ?? 'user'); ?>')">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </button>
                                    <button type="button" class="btn-action btn-reset" onclick="openResetPasswordModal(<?= $user['id_user']; ?>, '<?= htmlspecialchars($user['username']); ?>')">
                                        <i class="bi bi-key"></i> Reset Pass
                                    </button>
                                    <button type="button" class="btn-action btn-delete" onclick="confirmDeleteUser(<?= $user['id_user']; ?>, '<?= htmlspecialchars($user['username']); ?>')">
                                        <i class="bi bi-trash"></i> Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #999; padding: 30px;">
                            Belum ada data user
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL TAMBAH USER -->
<div id="addUserModal" class="modal-backdrop">
    <div class="modal-card">
        <div class="modal-header">
            <span class="modal-title">Tambah User</span>
            <span class="close-btn" onclick="closeAddUserModal()">&times;</span>
        </div>
        <form id="addUserForm" onsubmit="submitAddUser(event)">
            <div class="modal-body" style="padding: 24px;">
                <div class="form-group">
                    <label for="addUsername">Username</label>
                    <input type="text" id="addUsername" name="username" class="form-input" required placeholder="Masukkan username">
                </div>
                <div class="form-group">
                    <label for="addPassword">Password</label>
                    <input type="password" id="addPassword" name="password" class="form-input" required placeholder="Masukkan password">
                </div>
                <div class="form-group">
                    <label for="addNamaLengkap">Nama Lengkap</label>
                    <input type="text" id="addNamaLengkap" name="nama_lengkap" class="form-input" placeholder="Masukkan nama lengkap">
                </div>
                <div class="form-group">
                    <label for="addEmail">Email</label>
                    <input type="email" id="addEmail" name="email" class="form-input" placeholder="Masukkan email">
                </div>
                <div class="form-group">
                    <label for="addRole">Role</label>
                    <select id="addRole" name="role" class="form-input" required>
                        <option value="admin">Admin</option>
                        <option value="kepala_sekolah">Kepala Sekolah</option>
                        <option value="guru">Guru</option>
                        <option value="user">User Biasa</option>
                    </select>
                </div>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end; padding: 16px 24px; border-top: 1px solid #e2e8f0;">
                <button type="button" class="btn-modal-close" onclick="closeAddUserModal()">Batal</button>
                <button type="submit" class="btn-modal-submit">Tambah User</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT USER -->
<div id="editUserModal" class="modal-backdrop">
    <div class="modal-card">
        <div class="modal-header">
            <span class="modal-title">Edit User</span>
            <span class="close-btn" onclick="closeEditUserModal()">&times;</span>
        </div>
        <form id="editUserForm" onsubmit="submitEditUser(event)">
            <input type="hidden" id="editUserId" name="id_user">
            <div class="modal-body" style="padding: 24px;">
                <div class="form-group">
                    <label for="editUsername">Username</label>
                    <input type="text" id="editUsername" name="username" class="form-input" required>
                </div>
                <div class="form-group">
                    <label for="editNamaLengkap">Nama Lengkap</label>
                    <input type="text" id="editNamaLengkap" name="nama_lengkap" class="form-input">
                </div>
                <div class="form-group">
                    <label for="editEmail">Email</label>
                    <input type="email" id="editEmail" name="email" class="form-input">
                </div>
                <div class="form-group">
                    <label for="editRole">Role</label>
                    <select id="editRole" name="role" class="form-input" required>
                        <option value="admin">Admin</option>
                        <option value="kepala_sekolah">Kepala Sekolah</option>
                        <option value="guru">Guru</option>
                        <option value="user">User Biasa</option>
                    </select>
                </div>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end; padding: 16px 24px; border-top: 1px solid #e2e8f0;">
                <button type="button" class="btn-modal-close" onclick="closeEditUserModal()">Batal</button>
                <button type="submit" class="btn-modal-submit">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL RESET PASSWORD -->
<div id="resetPasswordModal" class="modal-backdrop">
    <div class="modal-card">
        <div class="modal-header">
            <span class="modal-title">Reset Password</span>
            <span class="close-btn" onclick="closeResetPasswordModal()">&times;</span>
        </div>
        <form id="resetPasswordForm" onsubmit="submitResetPassword(event)">
            <input type="hidden" id="resetUserId" name="id_user">
            <div class="modal-body" style="padding: 24px;">
                <p style="color: #64748b; margin-bottom: 16px;" id="resetUserInfo"></p>
                <div class="form-group">
                    <label for="resetPassword">Password Baru</label>
                    <input type="password" id="resetPassword" name="new_password" class="form-input" required placeholder="Masukkan password baru">
                </div>
                <div class="form-group">
                    <label for="resetPasswordConfirm">Konfirmasi Password</label>
                    <input type="password" id="resetPasswordConfirm" name="confirm_password" class="form-input" required placeholder="Konfirmasi password">
                </div>
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end; padding: 16px 24px; border-top: 1px solid #e2e8f0;">
                <button type="button" class="btn-modal-close" onclick="closeResetPasswordModal()">Batal</button>
                <button type="submit" class="btn-modal-submit">Reset Password</button>
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

    .btn-reset {
        color: #f59e0b;
        border-color: #f59e0b;
    }

    .btn-reset:hover {
        background: #fffbeb;
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
        document.getElementById('addUserModal').classList.remove('show');
        document.getElementById('editUserModal').classList.remove('show');
        document.getElementById('resetPasswordModal').classList.remove('show');
    }

    function openAddUserModal() {
        closeAllModals();
        document.getElementById('addUserForm').reset();
        document.getElementById('addUserModal').classList.add('show');
    }

    function closeAddUserModal() {
        document.getElementById('addUserModal').classList.remove('show');
        document.getElementById('addUserForm').reset();
    }

    function openEditUserModal(id, username, nama, email, role) {
        closeAllModals();
        document.getElementById('editUserId').value = id;
        document.getElementById('editUsername').value = username;
        document.getElementById('editNamaLengkap').value = nama;
        document.getElementById('editEmail').value = email;
        document.getElementById('editRole').value = role;
        document.getElementById('editUserModal').classList.add('show');
    }

    function closeEditUserModal() {
        document.getElementById('editUserModal').classList.remove('show');
    }

    function openResetPasswordModal(id, username) {
        closeAllModals();
        document.getElementById('resetUserId').value = id;
        document.getElementById('resetUserInfo').textContent = `Reset password untuk user: ${username}`;
        document.getElementById('resetPasswordModal').classList.add('show');
    }

    function closeResetPasswordModal() {
        document.getElementById('resetPasswordModal').classList.remove('show');
        document.getElementById('resetPasswordForm').reset();
    }

    function submitAddUser(event) {
        event.preventDefault();
        
        const formData = new FormData(document.getElementById('addUserForm'));
        formData.append('action', 'add');

        fetch('manajemen-user.php', {
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

    function submitEditUser(event) {
        event.preventDefault();
        
        const formData = new FormData(document.getElementById('editUserForm'));
        formData.append('action', 'edit');

        fetch('manajemen-user.php', {
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

    function submitResetPassword(event) {
        event.preventDefault();
        
        const password = document.getElementById('resetPassword').value;
        const confirmPassword = document.getElementById('resetPasswordConfirm').value;

        if (password !== confirmPassword) {
            alert('Password dan konfirmasi password tidak cocok!');
            return;
        }

        const formData = new FormData(document.getElementById('resetPasswordForm'));
        formData.append('action', 'reset_password');

        fetch('manajemen-user.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert(data.message);
                closeResetPasswordModal();
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }

    function confirmDeleteUser(id, username) {
        if (confirm(`Apakah Anda yakin ingin menghapus user "${username}"? Tindakan ini tidak bisa dibatalkan!`)) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id_user', id);

            fetch('manajemen-user.php', {
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
        const addModal = document.getElementById('addUserModal');
        const editModal = document.getElementById('editUserModal');
        const resetModal = document.getElementById('resetPasswordModal');
        
        if (event.target === addModal) closeAddUserModal();
        if (event.target === editModal) closeEditUserModal();
        if (event.target === resetModal) closeResetPasswordModal();
    });
</script>

<?php include 'includes/footer.php'; ?>
