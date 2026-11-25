<?php
// Pembuat admin pertama. Hanya aktif di mode lokal dan hanya selama BELUM ada admin.
// HAPUS file ini setelah admin pertama berhasil dibuat.
require "koneksi.php";
require "layout.php";

if (!$mode_lokal) {
    http_response_code(404);
    exit;
}

$pesan = "";
$sukses = false;
$siap = true;          // false jika database belum dimigrasi
$sudah_ada = false;

try {
    $sudah_ada = (int) ambil_semua($koneksi, "SELECT COUNT(*) AS n FROM users WHERE role = 'admin'")[0]["n"] > 0;
} catch (mysqli_sql_exception $e) {
    $siap = false;
}

if ($siap && !$sudah_ada && $_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim((string) ($_POST["username"] ?? ""));

    if (!csrf_cek()) {
        $pesan = pesan_csrf();
    } elseif ($username === "" || strlen($username) > 100) {
        $pesan = "Isi username atau email akun yang sudah terdaftar.";
    } else {
        $akun = ambil_semua($koneksi,
            "SELECT id_user, username FROM users WHERE username = ? OR email = ? LIMIT 1",
            "ss", [$username, $username]);
        if (count($akun) === 0) {
            $pesan = "Akun itu belum terdaftar. Daftar dulu lewat halaman Daftar.";
        } else {
            $id = (int) $akun[0]["id_user"];
            $stmt = $koneksi->prepare("UPDATE users SET role = 'admin', aktif = 1 WHERE id_user = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            catat_log($koneksi, $id, "Menjadi admin pertama", "@" . $akun[0]["username"]);
            $sukses = true;
            $sudah_ada = true;
        }
    }
}

auth_awal("Buat admin", "Buat admin", "Jadikan satu akun yang sudah terdaftar sebagai admin.");

if (!$siap): ?>
  <?php tampil_pesan("Database belum diperbarui. Jalankan dulu file database/tambah_admin.sql di phpMyAdmin (tab SQL), lalu muat ulang halaman ini.", "gagal"); ?>

<?php elseif ($sukses): ?>
  <?php tampil_pesan("Berhasil! Akun itu sekarang admin. Hapus file buat_admin.php dari folder website, lalu login ulang.", "sukses"); ?>
  <a href="login.php" class="tombol tombol-tautan">Ke halaman masuk</a>

<?php elseif ($sudah_ada): ?>
  <?php tampil_pesan("Admin sudah ada, jadi halaman ini dikunci. Sebaiknya hapus file buat_admin.php dari folder website.", "sukses"); ?>
  <a href="login.php" class="tombol tombol-tautan">Ke halaman masuk</a>

<?php else: ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <?php tampil_pesan($pesan, "gagal"); ?>
    <label class="sr" for="username">Username atau email</label>
    <input type="text" id="username" name="username" maxlength="100" placeholder="Username atau email akunmu" autocomplete="username" required autofocus>
    <button type="submit" class="tombol">Jadikan admin</button>
  </form>
  <p class="alih">Belum punya akun? <a href="register.php">Daftar dulu</a></p>
<?php endif;

auth_akhir();
