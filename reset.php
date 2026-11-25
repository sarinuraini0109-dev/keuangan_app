<?php
require "koneksi.php";
require "layout.php";
hanya_tamu();

$token = (string) ($_GET["token"] ?? ($_POST["token"] ?? ""));
$galat = [];
$valid = false;
$id_reset = 0;
$id_user  = 0;

// Token sah: bentuk benar, hash cocok, belum dipakai, belum kedaluwarsa
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $hash = hash("sha256", $token);
    $stmt = $koneksi->prepare(
        "SELECT id_reset, id_user FROM password_reset
         WHERE token_hash = ? AND dipakai = 0 AND kedaluwarsa > NOW() LIMIT 1"
    );
    $stmt->bind_param("s", $hash);
    $stmt->execute();
    $baris = $stmt->get_result()->fetch_assoc();
    if ($baris) {
        $valid    = true;
        $id_reset = (int) $baris["id_reset"];
        $id_user  = (int) $baris["id_user"];
    }
}

if ($valid && $_SERVER["REQUEST_METHOD"] === "POST") {
    $password = (string) ($_POST["password"] ?? "");
    $ulang    = (string) ($_POST["ulang"] ?? "");

    if (!csrf_cek()) {
        $galat[] = pesan_csrf();
    } else {
        $galat = validasi_password($password);
        if ($password !== $ulang) $galat[] = "Konfirmasi password tidak sama.";
    }

    if (count($galat) === 0) {
        $baru = password_hash($password, PASSWORD_DEFAULT);

        $koneksi->begin_transaction();
        $stmt = $koneksi->prepare("UPDATE users SET password = ? WHERE id_user = ?");
        $stmt->bind_param("si", $baru, $id_user);
        $stmt->execute();

        // Semua tautan reset milik user ini hangus
        $stmt = $koneksi->prepare("UPDATE password_reset SET dipakai = 1 WHERE id_user = ?");
        $stmt->bind_param("i", $id_user);
        $stmt->execute();

        // Semua perangkat "ingat saya" harus login ulang
        hapus_semua_token_ingat($koneksi, $id_user);
        $koneksi->commit();

        header("Location: login.php?status=reset");
        exit;
    }
}
?>
<?php if (!$valid): ?>
  <?php auth_awal("Tautan tidak berlaku", "Oops", "Tautan salah, sudah dipakai, atau lewat 30 menit."); ?>
  <a href="lupa.php" class="tombol tombol-tautan">Buat tautan baru</a>
<?php else: ?>
  <?php auth_awal("Password baru", "Password baru", "Minimal 8 karakter, berisi huruf dan angka."); ?>
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= h($token) ?>">

    <?php if (count($galat) > 0): ?>
      <div class="pesan gagal" role="alert">
        <?php foreach ($galat as $g): ?><div><?= h($g) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <label class="sr" for="password">Password baru</label>
    <div class="kolom-sandi">
      <input type="password" id="password" name="password" maxlength="72" placeholder="Password baru" autocomplete="new-password" required autofocus>
      <button type="button" class="lihat" data-target="password" aria-label="Tampilkan password">Lihat</button>
    </div>

    <label class="sr" for="ulang">Ulangi password</label>
    <div class="kolom-sandi">
      <input type="password" id="ulang" name="ulang" maxlength="72" placeholder="Ulangi password" autocomplete="new-password" required>
      <button type="button" class="lihat" data-target="ulang" aria-label="Tampilkan password">Lihat</button>
    </div>

    <button type="submit" class="tombol">Simpan password</button>
  </form>
<?php endif; ?>
<?php auth_akhir(); ?>
