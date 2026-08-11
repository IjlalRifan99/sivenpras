<?php
session_start();
include 'config/koneksi.php';

// Jika user sudah login, langsung lempar ke index.php
if (isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

$error = '';

// Proses validasi saat form di-submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($koneksi, trim($_POST['username']));
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        // Sesuaikan nama tabel & kolom di database kamu jika berbeda (misal: tabel 'users' / 'user')
        $query = mysqli_query($koneksi, "SELECT * FROM users WHERE username = '$username'");
        
        if (mysqli_num_rows($query) === 1) {
            $user = mysqli_fetch_assoc($query);
            
            // Verifikasi password (menggunakan password_verify jika di-hash, atau perbandingan langsung jika plain text)
            if (password_verify($password, $user['password']) || $password === $user['password']) {
                $_SESSION['login'] = true;
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_id'] = $user['id_user'] ?? $user['id'];

                header("Location: index.php");
                exit;
            } else {
                $error = "Password salah!";
            }
        } else {
            $error = "Username tidak ditemukan!";
        }
    } else {
        $error = "Harap isi username dan password!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Halaman Login - SIVENPRAS</title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #92b1e4; /* Warna background biru pastel sesuai gambar */
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-card {
            background: #ffffff;
            width: 100%;
            max-width: 420px;
            padding: 40px 35px;
            border-radius: 28px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Circle Avatar Atas */
        .avatar-circle {
            width: 90px;
            height: 90px;
            background-color: #4170c4;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 24px;
            color: #ffffff;
            font-size: 50px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h2 {
            font-size: 22px;
            font-weight: 700;
            color: #1a1a1a;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .login-header p {
            font-size: 13px;
            color: #4a4a4a;
        }

        .form-group {
            width: 100%;
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #1a1a1a;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus {
            border-color: #002366;
            box-shadow: 0 0 0 3px rgba(0, 35, 102, 0.1);
        }

        .form-control::placeholder {
            color: #8c8c8c;
        }

        .btn-login {
            width: 100%;
            background-color: #002366; /* Warna biru dongker tombol */
            color: #ffffff;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: background-color 0.2s, transform 0.1s;
        }

        .btn-login:hover {
            background-color: #001845;
        }

        .btn-login:active {
            transform: scale(0.99);
        }

        .alert-error {
            width: 100%;
            background-color: #fee2e2;
            border: 1px solid #f87171;
            color: #991b1b;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="avatar-circle">
            <i class="ph-fill ph-user"></i>
        </div>

        <div class="login-header">
            <h2>Halaman Login</h2>
            <p>Silakan login dengan benar</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-error">
                <?= htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" style="width: 100%;">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" 
                       id="username" 
                       name="username" 
                       class="form-control" 
                       placeholder="Tambahkan username ...." 
                       required 
                       autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       class="form-control" 
                       placeholder="Tambahkan password ...." 
                       required>
            </div>

            <button type="submit" class="btn-login">Login</button>
        </form>
    </div>

</body>
</html>