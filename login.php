<?php
require "koneksi.php";
require "layout.php";
hanya_tamu();

// Hash bohongan: waktu pengecekan tetap sama walau username tidak ada
const HASH_PALSU = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

$galat = "";
$ident = "";
$info  = "";
$status = $_GET["status"] ?? "";
if ($status === "daftar") {
    $info = "Akun berhasil dibuat. Silakan masuk.";
} elseif ($status === "reset") {
    $info = "Password berhasil diganti. Silakan masuk dengan password baru.";
} elseif ($status === "nonaktif") {
    $galat = "Akun ini dinonaktifkan atau sudah dihapus. Hubungi administrator.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $ident    = trim((string) ($_POST["ident"] ?? ""));
    $password = (string) ($_POST["password"] ?? "");
    $kunci    = kunci_percobaan($ident);

    if (!csrf_cek()) {
        $galat = pesan_csrf();
    } elseif ($ident === "" || $password === "" || strlen($ident) > 100 || strlen($password) > 200) {
        $galat = "Isi username/email dan password.";
    } elseif (terlalu_banyak($koneksi, "login", $kunci, 5, 20, 15)) {
        $galat = "Terlalu banyak percobaan. Coba lagi dalam 15 menit.";
    } else {
        try {
            $stmt = $koneksi->prepare(
                "SELECT id_user, nama_lengkap, password, aktif FROM users WHERE username = ? OR email = ? LIMIT 1"
            );
            $stmt->bind_param("ss", $ident, $ident);
            $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            // Database belum dimigrasi (kolom aktif belum ada): cari tanpa kolom itu
            $stmt = $koneksi->prepare(
                "SELECT id_user, nama_lengkap, password, 1 AS aktif FROM users WHERE username = ? OR email = ? LIMIT 1"
            );
            $stmt->bind_param("ss", $ident, $ident);
            $stmt->execute();
        }
        $akun = $stmt->get_result()->fetch_assoc();

        $cocok = password_verify($password, $akun["password"] ?? HASH_PALSU);

        if ($akun && $cocok && (int) $akun["aktif"] !== 1) {
            $galat = "Akun ini dinonaktifkan. Hubungi administrator.";
        } elseif ($akun && $cocok) {
            hapus_percobaan($koneksi, "login", $kunci);

            // Perbarui hash jika algoritma/biaya default sudah naik
            if (password_needs_rehash($akun["password"], PASSWORD_DEFAULT)) {
                $baru = password_hash($password, PASSWORD_DEFAULT);
                $up = $koneksi->prepare("UPDATE users SET password = ? WHERE id_user = ?");
                $up->bind_param("si", $baru, $akun["id_user"]);
                $up->execute();
            }

            mulai_sesi_login($koneksi, $akun["id_user"], $akun["nama_lengkap"]);

            if (!empty($_POST["ingat"])) {
                try {
                    buat_token_ingat($koneksi, (int) $akun["id_user"]);
                } catch (mysqli_sql_exception $e) {
                    error_log($e->getMessage());
                }
            }
            header("Location: " . (adalah_admin() ? "admin.php" : "dashboard.php"));
            exit;
        } else {
            catat_percobaan($koneksi, "login", $kunci);
            $galat = "Username/email atau password salah.";   // sengaja tidak spesifik
        }
    }
}
?>
<?php auth_awal("Masuk", "Masuk", "Lanjutkan ke catatan keuanganmu."); ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>

    <?php tampil_pesan($info, "sukses"); ?>
    <?php tampil_pesan($galat, "gagal"); ?>

    <label class="sr" for="ident">Username atau email</label>
    <input type="text" id="ident" name="ident" value="<?= h($ident) ?>" maxlength="100" placeholder="Username atau email" autocomplete="username" required autofocus>

    <label class="sr" for="password">Password</label>
    <div class="kolom-sandi">
      <input type="password" id="password" name="password" maxlength="200" placeholder="Password" autocomplete="current-password" required>
      <button type="button" class="lihat" data-target="password" aria-label="Tampilkan password">Lihat</button>
    </div>

    <div class="baris-opsi">
      <label class="ingat"><input type="checkbox" name="ingat" value="1"> Ingat saya</label>
      <a href="lupa.php">Lupa password ?</a>
    </div>

    <button type="submit" class="tombol">Masuk</button>
  </form>
  <p class="alih">Belum punya akun? <a href="register.php">Daftar</a></p>
<?php auth_akhir(); ?>
