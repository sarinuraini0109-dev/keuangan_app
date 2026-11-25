<?php
require "koneksi.php";
require "layout.php";
hanya_tamu();

$galat    = [];
$nama     = "";
$username = "";
$email    = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nama     = trim((string) ($_POST["nama"] ?? ""));
    $username = trim((string) ($_POST["username"] ?? ""));
    $email    = trim((string) ($_POST["email"] ?? ""));
    $password = (string) ($_POST["password"] ?? "");
    $ulang    = (string) ($_POST["ulang"] ?? "");
    $kunci    = kunci_percobaan($email);

    if (!csrf_cek()) {
        $galat[] = pesan_csrf();
    } elseif (terlalu_banyak($koneksi, "daftar", $kunci, 5, 5, 60)) {
        $galat[] = "Terlalu banyak percobaan mendaftar. Coba lagi nanti.";
    } else {
        catat_percobaan($koneksi, "daftar", $kunci);

        if ($nama === "" || mb_strlen($nama) > 100)             $galat[] = "Nama lengkap wajib diisi (maksimal 100 karakter).";
        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username))   $galat[] = "Username 3-30 karakter: huruf, angka, atau garis bawah.";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) $galat[] = "Format email tidak valid.";
        $galat = array_merge($galat, validasi_password($password));
        if ($password !== $ulang)                               $galat[] = "Konfirmasi password tidak sama.";

        if (count($galat) === 0) {
            $stmt = $koneksi->prepare("SELECT id_user FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $galat[] = "Username atau email sudah terdaftar.";
            }
        }

        if (count($galat) === 0) {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $koneksi->begin_transaction();
            try {
                $stmt = $koneksi->prepare(
                    "INSERT INTO users (nama_lengkap, username, email, password) VALUES (?, ?, ?, ?)"
                );
                $stmt->bind_param("ssss", $nama, $username, $email, $hash);
                $stmt->execute();
                $id_baru = $koneksi->insert_id;

                // Dompet awal supaya langsung bisa mencatat transaksi
                $stmt = $koneksi->prepare(
                    "INSERT INTO dompet (id_user, nama, jenis, saldo_awal) VALUES (?, 'Dompet Tunai', 'tunai', 0)"
                );
                $stmt->bind_param("i", $id_baru);
                $stmt->execute();
                $koneksi->commit();
            } catch (mysqli_sql_exception $e) {
                $koneksi->rollback();
                error_log($e->getMessage());
                $galat[] = "Pendaftaran gagal. Coba lagi.";
            }

            if (count($galat) === 0) {
                header("Location: login.php?status=daftar");
                exit;
            }
        }
    }
}
?>
<?php auth_awal("Daftar", "Daftar", "Buat akun gratis, mulai catat hari ini."); ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>

    <?php if (count($galat) > 0): ?>
      <div class="pesan gagal" role="alert">
        <?php foreach ($galat as $g): ?><div><?= h($g) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <label class="sr" for="nama">Nama lengkap</label>
    <input type="text" id="nama" name="nama" value="<?= h($nama) ?>" maxlength="100" placeholder="Nama lengkap" autocomplete="name" required autofocus>

    <label class="sr" for="username">Username</label>
    <input type="text" id="username" name="username" value="<?= h($username) ?>" maxlength="30" placeholder="Username (huruf, angka, _)" autocomplete="username" required>

    <label class="sr" for="email">Email</label>
    <input type="text" id="email" name="email" value="<?= h($email) ?>" maxlength="100" placeholder="Email" autocomplete="email" inputmode="email" required>

    <label class="sr" for="password">Password</label>
    <div class="kolom-sandi">
      <input type="password" id="password" name="password" maxlength="72" placeholder="Password" autocomplete="new-password" required>
      <button type="button" class="lihat" data-target="password" aria-label="Tampilkan password">Lihat</button>
    </div>
    <p class="petunjuk">Minimal 8 karakter, berisi huruf dan angka.</p>

    <label class="sr" for="ulang">Ulangi password</label>
    <div class="kolom-sandi">
      <input type="password" id="ulang" name="ulang" maxlength="72" placeholder="Ulangi password" autocomplete="new-password" required>
      <button type="button" class="lihat" data-target="ulang" aria-label="Tampilkan password">Lihat</button>
    </div>

    <button type="submit" class="tombol">Daftar</button>
  </form>
  <p class="alih">Sudah punya akun? <a href="login.php">Masuk</a></p>
<?php auth_akhir(); ?>
