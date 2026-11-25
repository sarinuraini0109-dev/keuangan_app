<?php
require "koneksi.php";
require "layout.php";
hanya_tamu();

$galat  = "";
$pesan  = "";
$tautan = "";
$email  = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim((string) ($_POST["email"] ?? ""));
    $kunci = kunci_percobaan($email);

    if (!csrf_cek()) {
        $galat = pesan_csrf();
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $galat = "Masukkan alamat email yang valid.";
    } elseif (terlalu_banyak($koneksi, "lupa", $kunci, 3, 10, 15)) {
        $galat = "Terlalu banyak permintaan. Coba lagi dalam 15 menit.";
    } else {
        catat_percobaan($koneksi, "lupa", $kunci);

        $stmt = $koneksi->prepare("SELECT id_user FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $akun = $stmt->get_result()->fetch_assoc();

        if ($akun) {
            $id_user = (int) $akun["id_user"];

            // Batalkan permintaan lama yang belum dipakai
            $stmt = $koneksi->prepare("DELETE FROM password_reset WHERE id_user = ? AND dipakai = 0");
            $stmt->bind_param("i", $id_user);
            $stmt->execute();

            // Token acak 256 bit; di database hanya hash-nya
            $token = bin2hex(random_bytes(32));
            $hash  = hash("sha256", $token);
            $stmt = $koneksi->prepare(
                "INSERT INTO password_reset (id_user, token_hash, kedaluwarsa)
                 VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))"
            );
            $stmt->bind_param("is", $id_user, $hash);
            $stmt->execute();

            $link = url_dasar() . "/reset.php?token=" . $token;
            if ($mode_lokal) {
                $tautan = $link;
            } else {
                @mail($email, "Reset password Catatan Keuangan",
                      "Buka tautan ini untuk mengganti password (berlaku 30 menit):\r\n" . $link
                      . "\r\n\r\nAbaikan email ini jika Anda tidak memintanya.");
            }
        }

        // Jawaban sama, ada atau tidaknya email, agar akun tidak bisa ditebak
        $pesan = "Jika email terdaftar, tautan untuk mengganti password sudah dibuat. Berlaku 30 menit.";
    }
}
?>
<?php auth_awal("Lupa password", "Lupa password", "Masukkan email akunmu, kami buatkan tautan penggantinya."); ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>

    <?php tampil_pesan($pesan, "sukses"); ?>
    <?php tampil_pesan($galat, "gagal"); ?>

    <?php if ($tautan !== ""): ?>
      <div class="tautan-reset">
        <small>Mode lokal: belum ada server email, jadi tautan ditampilkan di sini.</small>
        <a href="<?= h($tautan) ?>">Buka tautan reset password</a>
      </div>
    <?php endif; ?>

    <label class="sr" for="email">Email</label>
    <input type="text" id="email" name="email" value="<?= h($email) ?>" maxlength="100" placeholder="Email" autocomplete="email" inputmode="email" required autofocus>

    <button type="submit" class="tombol">Buat tautan reset</button>
  </form>
  <p class="alih"><a href="login.php">Kembali ke halaman masuk</a></p>
<?php auth_akhir(); ?>
