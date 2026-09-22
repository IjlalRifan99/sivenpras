# Dokumentasi SIVENPRAS-TB

**Sistem Inventaris Sarana dan Prasarana Sekolah**  
**Versi dokumentasi:** 1.0  
**Tanggal:** 22 September 2026

## 1. Gambaran Umum

SIVENPRAS-TB adalah aplikasi berbasis PHP dan MySQL untuk mengelola data sarana-prasarana sekolah. Aplikasi menyediakan pencatatan katalog barang, unit inventaris, lokasi ruangan, barang habis pakai (BHP), kondisi barang, pemindaian barcode/QR, laporan, serta manajemen pengguna.

Tujuan utama aplikasi:

- Menyediakan satu sumber data inventaris sekolah.
- Memudahkan pelacakan barang berdasarkan barcode, kategori, dan ruangan.
- Memperbarui kondisi dan keterangan setiap unit inventaris.
- Mengelola stok BHP dan distribusinya ke ruangan.
- Menghasilkan laporan yang dapat dicetak, disimpan sebagai PDF, atau diekspor ke Excel.

## 2. Teknologi dan Struktur Aplikasi

### 2.1 Teknologi

- Backend: PHP dengan `mysqli`.
- Database: MySQL/MariaDB, database `sivenpras_tb`.
- Frontend: HTML, CSS, JavaScript, Bootstrap Icons, dan AJAX/fetch.
- Pemindaian: `html5-qrcode` dari CDN.
- Barcode: `JsBarcode` dari CDN.
- Web server pengembangan: Apache/XAMPP.

### 2.2 Struktur direktori

| Direktori/File | Fungsi |
|---|---|
| `index.php` | Dashboard ringkasan inventaris. |
| `login.php` / `logout.php` | Autentikasi dan pengakhiran sesi. |
| `daftar-inventaris.php` | Daftar aset tetap dan detail unit. |
| `daftar-bhp.php` | Daftar stok barang habis pakai. |
| `daftar-barang.php` | Katalog/master jenis barang. |
| `tambah-barang.php` | Menambah master barang, master BHP, atau unit ke ruangan. |
| `ruangan.php` | Daftar ruangan dan detail inventaris per ruangan. |
| `scan-barcode.php` | Scan QR/barcode dan memperbarui data unit. |
| `laporan.php` | Filter, cetak, dan ekspor laporan inventaris. |
| `manajemen-kategori.php` | CRUD kategori aset tetap. |
| `manajemen-ruangan.php` | CRUD ruangan. |
| `manajemen-user.php` | CRUD pengguna dan reset password. |
| `api-scan.php` | Endpoint pencarian unit berdasarkan barcode. |
| `api/export-inventaris.php` | Endpoint ekspor inventaris. |
| `api/get_kode_otomatis.php` | Endpoint kode otomatis. |
| `config/koneksi.php` | Konfigurasi koneksi database. |
| `includes/header.php` | Kerangka header halaman. |
| `includes/sidebar.php` | Navigasi dan pembatasan menu berdasarkan role. |
| `includes/footer.php` | Footer halaman dan aset JavaScript. |
| `assets/css/` | Stylesheet aplikasi. |
| `assets/js/` | JavaScript antarmuka. |
| `uploads/barang/` | Penyimpanan gambar barang. |

## 3. Prasyarat dan Instalasi

### 3.1 Prasyarat server

- Windows dengan XAMPP atau stack Apache/PHP/MySQL setara.
- PHP dengan ekstensi `mysqli`, `fileinfo`, dan `ZipArchive`.
- MySQL/MariaDB.
- Browser modern dengan izin kamera jika fitur scan digunakan.
- Koneksi internet untuk library CDN pada halaman scan dan ikon tertentu.

### 3.2 Konfigurasi database

File konfigurasi berada di `config/koneksi.php` dengan nilai bawaan:

```text
Host     : localhost
User     : root
Password : kosong
Database : sivenpras_tb
```

Buat database bernama `sivenpras_tb`, lalu impor skema dan data awal yang disediakan proyek. Pastikan tabel minimal berikut tersedia: `users`, `kategori`, `barang`, `inventaris`, `ruangan`, `kategori_bhp`, `bhp`, dan `inventaris_bhp`.

### 3.3 Menjalankan aplikasi

1. Simpan proyek pada `C:\xampp\htdocs\sivenpras_tb`.
2. Jalankan Apache dan MySQL dari XAMPP Control Panel.
3. Pastikan database `sivenpras_tb` telah dibuat dan dapat diakses.
4. Buka `http://localhost/sivenpras_tb/login.php`.
5. Masuk menggunakan akun yang tersedia pada tabel `users`.

## 4. Autentikasi dan Role

Semua halaman internal memulai sesi PHP dan mengarahkan pengguna yang belum login ke halaman login. Setelah berhasil login, sesi menyimpan `login`, `username`, `user_id`, dan `role`.

### 4.1 Proses login

1. Pengguna mengisi username dan password.
2. Sistem mencari username pada tabel `users`.
3. Password diverifikasi menggunakan bcrypt. Untuk kompatibilitas data lama, kode juga masih menerima password plaintext yang sama persis dengan nilai database.
4. Sistem menyimpan sesi dan mengarahkan pengguna ke dashboard.
5. Username tidak ditemukan, password salah, atau field kosong menghasilkan pesan kesalahan.

### 4.2 Role dan hak akses

| Role | Hak akses utama |
|---|---|
| `admin` | Seluruh menu, termasuk manajemen user, kategori, dan ruangan. |
| `kepala_sekolah` | Melihat dashboard, inventaris, BHP, scan, ruangan, dan laporan; tidak dapat mengubah data melalui menu tambah/scan. |
| `guru` / `staff` / `user` | Akses operasional sesuai konfigurasi menu dan halaman yang tersedia. |

Catatan: nilai role yang digunakan perlu konsisten antara data database, sidebar, dan form manajemen user. Sidebar secara eksplisit memakai `admin` dan `kepala_sekolah`, sedangkan form user pada kode saat ini juga menampilkan `staff`.

## 5. Dokumentasi Menu dan Fitur

### 5.1 Dashboard

Alamat: `index.php`

Dashboard menampilkan:

- Total unit inventaris.
- Total jenis barang.
- Jumlah unit dengan kondisi baik.
- Jumlah unit rusak.
- Jumlah unit hilang.
- Persentase kondisi baik.
- Lima kategori dengan jumlah unit terbanyak.
- Distribusi kondisi barang.

Data dashboard dihitung langsung dari tabel `inventaris`, `barang`, dan `kategori` setiap kali halaman dibuka.

### 5.2 Daftar Inventaris Aset Tetap

Alamat: `daftar-inventaris.php`

Fitur:

- Menampilkan jenis barang, kategori, jumlah unit, kondisi, dan lokasi.
- Pencarian berdasarkan nama barang atau lokasi.
- Filter berdasarkan kategori.
- Membuka detail unit untuk melihat barcode, kondisi, keterangan, lokasi, dan gambar.
- Upload, edit, dan hapus gambar barang dengan format JPG, JPEG, PNG, atau WEBP.
- Menyediakan data ruangan yang dapat dipakai untuk ekspor.

Role `kepala_sekolah` tidak diizinkan melakukan perubahan gambar.

### 5.3 Daftar Barang / Katalog

Alamat: `daftar-barang.php`

Fitur admin/operasional:

- Melihat semua jenis barang dan kategori.
- Melihat jumlah unit inventaris per jenis barang.
- Menambah barang dengan nama dan kategori.
- Mengubah nama dan kategori barang.
- Menghapus barang.

Penghapusan barang menggunakan transaksi dan menghapus seluruh unit inventaris yang terkait terlebih dahulu. Tindakan ini bersifat destruktif dan harus dikonfirmasi.

### 5.4 Tambah Barang

Alamat: `tambah-barang.php`

Halaman ini memiliki beberapa konteks kerja:

#### Menambah master barang

- Isi nama barang.
- Pilih kategori.
- Isi keterangan jika diperlukan.
- Upload gambar opsional maksimal 2 MB.
- Format gambar yang diterima: JPG, PNG, atau WEBP.

#### Menambah master BHP

- Isi barcode BHP.
- Isi nama barang BHP.
- Pilih kategori BHP.
- Data awal stok adalah 0.

#### Menambah unit barang ke ruangan

- Pilih jenis barang.
- Masukkan jumlah unit.
- Masukkan tahun perolehan.
- Masukkan keterangan.
- Sistem membuat barcode otomatis untuk setiap unit.
- Kondisi awal unit adalah `baik`.

Format barcode unit mengikuti pola:

```text
KODE-BARANG-KODE-RUANGAN-NOMOR-URUT
Contoh: MKGU-2-001
```

Jika nama barang terdiri dari beberapa kata, kode diambil dari konsonan awal kata. Jika katalog barang memiliki barcode produk, nilai tersebut dipakai sebagai dasar kode.

#### Distribusi BHP ke ruangan

- Pilih BHP.
- Masukkan jumlah dan satuan.
- Tambahkan keterangan.
- Sistem memeriksa kecukupan stok.
- Jika berhasil, stok BHP berkurang dan catatan distribusi disimpan.

### 5.5 Barang Habis Pakai

Alamat: `daftar-bhp.php`

Fitur:

- Pencarian berdasarkan nama atau kategori BHP.
- Melihat stok tersedia.
- Melihat satuan.
- Melihat jumlah terpakai per ruangan.
- Melihat keterangan BHP.
- Menambah stok melalui modal.

Satuan yang didukung: PCS, Box, Pack, dan Lusin. Penambahan stok dicatat sebagai transaksi dan menambah nilai stok master BHP.

### 5.6 Ruangan

Alamat: `ruangan.php`

#### Daftar ruangan

Tanpa parameter `id`, halaman menampilkan seluruh ruangan sebagai kartu beserta jumlah barang. Pilih kartu ruangan untuk membuka detail.

#### Detail ruangan

Dengan parameter `id`, halaman menampilkan:

- Total barang dan jumlah berdasarkan kondisi.
- Daftar unit inventaris di ruangan.
- Daftar distribusi BHP di ruangan.
- Pencarian barcode, nama barang, atau kategori.
- Filter jenis barang dan kondisi.
- Perubahan kondisi unit.
- Perubahan keterangan unit.
- Penghapusan beberapa unit sekaligus.
- Tautan untuk menambah barang ke ruangan.

Kondisi yang didukung: `baik`, `cukup baik`, `rusak`, `rusak parah`, dan `hilang`.

Penghapusan massal hanya berlaku untuk unit yang berada pada ruangan aktif.

### 5.7 Scan Barcode dan QR Code

Alamat: `scan-barcode.php`

Fitur:

- Scan menggunakan kamera perangkat.
- Mode QR Code dan Barcode.
- Pencarian kode secara manual.
- Menampilkan detail unit hasil scan.
- Mengubah keterangan dan kondisi unit.
- Mematikan kamera setelah selesai.

Alur penggunaan:

1. Pilih mode scan.
2. Izinkan akses kamera pada browser atau masukkan kode secara manual.
3. Sistem mengirim kode ke `api-scan.php`.
4. Jika ditemukan, detail unit ditampilkan.
5. Ubah kondisi atau keterangan lalu simpan.

Role `kepala_sekolah` hanya dapat melihat hasil scan; field perubahan dibuat readonly dan tombol simpan disembunyikan.

### 5.8 Laporan Inventaris

Alamat: `laporan.php`

Filter yang tersedia:

- Kategori.
- Nama barang, bergantung pada kategori yang dipilih.
- Kondisi.
- Lokasi/ruangan.

Keluaran laporan:

- Tabel barcode, nama barang, kategori, lokasi, kondisi, tahun perolehan, dan keterangan.
- Cetak langsung melalui dialog print browser.
- Simpan sebagai PDF dari dialog print.
- Export Excel dengan format `.xlsx`.

Export Excel mempertahankan filter yang sedang aktif dan menyertakan judul sekolah, tanggal cetak, header tabel, serta area tanda tangan.

### 5.9 Manajemen Kategori

Alamat: `manajemen-kategori.php`

Fitur:

- Tambah kategori dan keterangan.
- Edit kategori dan keterangan.
- Hapus kategori.
- Melihat jumlah barang per kategori.

Kategori yang masih digunakan oleh barang tidak dapat dihapus.

### 5.10 Manajemen Ruangan

Alamat: `manajemen-ruangan.php`

Fitur:

- Tambah ruangan.
- Edit nama ruangan.
- Hapus ruangan.
- Melihat jumlah barang per ruangan.

Penghapusan ruangan menghapus seluruh unit inventaris yang terkait melalui transaksi database. Pastikan data sudah dipindahkan atau dicadangkan sebelum menghapus.

### 5.11 Manajemen User

Alamat: `manajemen-user.php`

Fitur:

- Melihat username, nama lengkap, email, role, dan status.
- Menambah user.
- Mengedit profil dan role.
- Reset password.
- Menghapus user.

Password user baru dan password hasil reset disimpan menggunakan bcrypt. Username harus unik. User yang sedang login tidak dapat menghapus akunnya sendiri.

## 6. Model Data

Relasi utama yang tampak digunakan aplikasi:

```text
users

kategori 1 --- banyak barang 1 --- banyak inventaris banyak --- 1 ruangan

kategori_bhp 1 --- banyak bhp 1 --- banyak inventaris_bhp banyak --- 1 ruangan
```

Kolom penting yang digunakan:

| Tabel | Kolom yang dipakai aplikasi |
|---|---|
| `users` | `id_user`, `username`, `password`, `nama_lengkap`, `email`, `role`, `created_at` |
| `kategori` | `id_kategori`, `nama_kategori`, `keterangan` |
| `barang` | `id_barang`, `nama_barang`, `kategori_id`, `deskripsi`, `gambar`, `barcode` |
| `inventaris` | `id_inventaris`, `barang_id`, `ruangan_id`, `tahun_perolehan`, `kondisi`, `keterangan`, `barcode`, `update_at` |
| `ruangan` | `id_ruangan`, `nama_ruangan` |
| `kategori_bhp` | `id_kategori`, `nama_kategori` |
| `bhp` | `id_bhp`, `nama_barang`, `kategori_id`, `deskripsi`, `barcode`, `stok` |
| `inventaris_bhp` | `id_inventaris_bhp`, `bhp_id`, `ruangan_id`, `jumlah`, `satuan`, `keterangan`, `barcode` |

## 7. Endpoint dan Integrasi

### `api-scan.php`

- Metode: POST.
- Parameter: `barcode`.
- Hasil sukses: JSON dengan status dan detail inventaris.
- Hasil tidak ditemukan: status `not_found`.

### `api/export-inventaris.php`

Endpoint pendukung untuk ekspor data inventaris. Filter mengikuti parameter laporan jika dipanggil dari halaman laporan.

### `api/get_kode_otomatis.php`

Endpoint pendukung pembuatan atau pengambilan kode otomatis barang.

## 8. Keamanan dan Validasi

Implementasi saat ini mencakup:

- Pemeriksaan sesi pada halaman internal.
- Escape input menggunakan `mysqli_real_escape_string` pada banyak query.
- Escape output HTML dengan `htmlspecialchars`.
- Hash password bcrypt untuk user baru dan reset password.
- Validasi ekstensi dan MIME file gambar.
- Batas ukuran gambar 2 MB pada form tambah barang.
- Transaksi dan rollback pada penghapusan barang/ruangan serta mutasi stok BHP.
- Pencegahan penghapusan akun sendiri.
- Pencegahan penghapusan kategori yang sedang digunakan.

Rekomendasi penguatan sebelum produksi:

- Hapus fallback password plaintext setelah seluruh akun lama dimigrasikan ke bcrypt.
- Gunakan prepared statements untuk seluruh query, bukan hanya escape string.
- Tambahkan token CSRF pada seluruh operasi POST/AJAX.
- Validasi role di server pada setiap endpoint, bukan hanya menyembunyikan menu.
- Batasi tipe, ukuran, dan nama file upload secara lebih ketat serta nonaktifkan eksekusi script pada folder upload.
- Simpan kredensial database di environment variable atau konfigurasi di luar web root.
- Tambahkan audit log untuk perubahan kondisi, penghapusan, mutasi stok, dan perubahan user.
- Terapkan HTTPS jika kamera, login, atau aplikasi digunakan melalui jaringan.

## 9. Prosedur Operasional yang Disarankan

### Setup awal

1. Buat kategori aset tetap.
2. Buat kategori BHP.
3. Buat ruangan.
4. Buat akun pengguna dan tetapkan role.
5. Tambahkan master barang dan master BHP.

### Pencatatan aset tetap

1. Buka Tambah Barang.
2. Tambahkan master barang.
3. Pilih ruangan.
4. Tambahkan jumlah unit dan tahun perolehan.
5. Cetak atau tempel barcode yang dihasilkan.
6. Verifikasi data melalui detail ruangan atau Scan Barang.

### Pemeliharaan data

1. Lakukan pemeriksaan kondisi berkala.
2. Perbarui kondisi melalui detail ruangan atau scan.
3. Isi keterangan untuk kerusakan, pemindahan, atau kehilangan.
4. Gunakan laporan dengan filter lokasi dan kondisi sebagai bahan pemeriksaan.

### Pengelolaan BHP

1. Buat master BHP beserta kategori dan barcode.
2. Tambahkan stok masuk.
3. Distribusikan BHP ke ruangan melalui halaman ruangan.
4. Periksa saldo stok dan jumlah terpakai.

## 10. Backup dan Pemulihan

Backup minimal mencakup:

- Database `sivenpras_tb`.
- Folder `uploads/barang/`.
- File konfigurasi dan source code.

Contoh backup database menggunakan XAMPP:

```text
phpMyAdmin -> pilih sivenpras_tb -> Export -> SQL -> Go
```

Untuk pemulihan, buat database kosong, impor file SQL, kembalikan folder upload, lalu periksa koneksi pada `config/koneksi.php`.

## 11. Troubleshooting

| Masalah | Pemeriksaan |
|---|---|
| Tidak dapat login | Pastikan tabel `users`, username, password, dan role tersedia. |
| Koneksi database gagal | Periksa MySQL aktif, nama database, user, dan password. |
| Kamera tidak aktif | Gunakan HTTPS atau localhost, izinkan kamera, dan cek browser. |
| Export Excel gagal | Pastikan ekstensi PHP `ZipArchive` aktif. |
| Gambar tidak tampil | Periksa folder `uploads/barang/`, permission, dan nama file pada kolom `gambar`. |
| BHP gagal digunakan | Pastikan kolom `stok` dan `ruangan_id` sudah tersedia serta stok cukup. |
| Menu tidak sesuai role | Periksa nilai `$_SESSION['role']` dan nilai role pada tabel `users`. |
| Data tidak tersimpan | Periksa pesan error PHP/MySQL dan validasi field wajib. |

## 12. Daftar File Pemeriksaan Teknis

File utilitas pada proyek:

- `tools/test-env.php`: pemeriksaan lingkungan aplikasi.
- `tools/check-zip.php`: pemeriksaan dukungan ZIP/ZipArchive.
- `tests/test_data.csv`: data uji.
- `tests/test_xlsx_gen.py`: utilitas pengujian generator XLSX.
- `tools/generate_xlsx.py`: utilitas pembuatan XLSX.

Dokumentasi ini disusun berdasarkan kode sumber yang tersedia pada proyek SIVENPRAS-TB dan sebaiknya diperbarui setiap kali struktur database, role, atau alur operasional berubah.