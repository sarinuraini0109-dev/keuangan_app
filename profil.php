<?php
require "koneksi.php";
require "layout.php";
wajib_login();

$id_user = (int) $_SESSION["id_user"];
$pesan = "";
$tipe_pesan = "gagal";
$galat_pw = [];

$user = ambil_semua($koneksi,
    "SELECT nama_lengkap, username, email, password, terakhir_login, dibuat_pada FROM users WHERE id_user = ?",
    "i", [$id_user])[0];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $aksi = $_POST["aksi"] ?? "";

    if (!csrf_cek()) {
        $pesan = pesan_csrf();

    } elseif ($aksi === "profil") {
        $nama  = trim((string) ($_POST["nama"] ?? ""));
        $email = trim((string) ($_POST["email"] ?? ""));
        if ($nama === "" || mb_strlen($nama) > 100) {
            $pesan = "Nama lengkap wajib diisi (maksimal 100 karakter).";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            $pesan = "Format email tidak valid.";
        } else {
            try {
                $stmt = $koneksi->prepare("UPDATE users SET nama_lengkap = ?, email = ? WHERE id_user = ?");
                $stmt->bind_param("ssi", $nama, $email, $id_user);
                $stmt->execute();
                $_SESSION["nama"] = $nama;
                set_flash("Profil diperbarui.");
                header("Location: profil.php");
                exit;
            } catch (mysqli_sql_exception $e) {
                $pesan = (int) $e->getCode() === 1062 ? "Email itu sudah dipakai akun lain." : "Profil gagal disimpan.";
            }
        }

    } elseif ($aksi === "password") {
        $lama  = (string) ($_POST["lama"] ?? "");
        $baru  = (string) ($_POST["baru"] ?? "");
        $ulang = (string) ($_POST["ulang"] ?? "");
        $kunci = kunci_percobaan("pw:" . $id_user);

        if (terlalu_banyak($koneksi, "login", $kunci, 5, 20, 15)) {
            $galat_pw[] = "Terlalu banyak percobaan. Coba lagi dalam 15 menit.";
        } elseif (!password_verify($lama, $user["password"])) {
            catat_percobaan($koneksi, "login", $kunci);
            $galat_pw[] = "Password saat ini salah.";
        } else {
            $galat_pw = validasi_password($baru);
            if ($baru !== $ulang) $galat_pw[] = "Konfirmasi password tidak sama.";
        }

        if (count($galat_pw) === 0) {
            $hash = password_hash($baru, PASSWORD_DEFAULT);
            $stmt = $koneksi->prepare("UPDATE users SET password = ? WHERE id_user = ?");
            $stmt->bind_param("si", $hash, $id_user);
            $stmt->execute();
            hapus_percobaan($koneksi, "login", $kunci);
            hapus_semua_token_ingat($koneksi, $id_user);   // perangkat lain harus login ulang
            session_regenerate_id(true);
            set_flash("Password berhasil diganti.");
            header("Location: profil.php");
            exit;
        }

    } elseif ($aksi === "lupakan_perangkat") {
        hapus_semua_token_ingat($koneksi, $id_user);
        atur_cookie_ingat("", time() - 3600);
        set_flash("Semua perangkat \"ingat saya\" dilupakan.");
        header("Location: profil.php");
        exit;
    }
}

$flash = ambil_flash();
if ($flash) {
    $pesan = $flash["pesan"];
    $tipe_pesan = $flash["tipe"];
}

$jml_perangkat = 0;
try {
    $jml_perangkat = (int) ambil_semua($koneksi,
        "SELECT COUNT(*) AS n FROM remember_token WHERE id_user = ? AND kedaluwarsa > NOW()",
        "i", [$id_user])[0]["n"];
} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());
}

halaman_awal("Profil", "profil");
?>

  <div class="kepala-halaman">
    <div>
      <h1>Profil</h1>
      <p>Kelola data akun dan keamananmu.</p>
    </div>
  </div>

  <?php tampil_pesan($pesan, $tipe_pesan); ?>

  <div class="grid-2 profil-grid">

    <section class="panel" aria-labelledby="judul-profil">
      <h2 id="judul-profil">Data akun</h2>
      <dl class="info-daftar">
        <dt>Username</dt><dd><?= h($user["username"]) ?></dd>
        <dt>Bergabung</dt><dd><?= h(date("d/m/Y", strtotime($user["dibuat_pada"]))) ?></dd>
        <dt>Login terakhir</dt><dd><?= $user["terakhir_login"] ? h(date("d/m/Y H:i", strtotime($user["terakhir_login"]))) : "—" ?></dd>
      </dl>
      <hr class="pemisah">
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="profil">
        <label for="nama">Nama lengkap</label>
        <input type="text" id="nama" name="nama" maxlength="100" value="<?= h($user["nama_lengkap"]) ?>" required>
        <label for="email">Email</label>
        <input type="text" id="email" name="email" maxlength="100" inputmode="email" value="<?= h($user["email"]) ?>" required>
        <button type="submit" class="tombol">Simpan profil</button>
      </form>
    </section>

    <div class="profil-kanan">
      <section class="panel" aria-labelledby="judul-pw">
        <h2 id="judul-pw">Ganti password</h2>
        <?php if (count($galat_pw) > 0): ?>
          <div class="pesan gagal" role="alert">
            <?php foreach ($galat_pw as $g): ?><div><?= h($g) ?></div><?php endforeach; ?>
          </div>
        <?php endif; ?>
        <form method="post" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="password">
          <label for="lama">Password saat ini</label>
          <input type="password" id="lama" name="lama" maxlength="200" autocomplete="current-password" required>
          <label for="baru">Password baru</label>
          <input type="password" id="baru" name="baru" maxlength="72" autocomplete="new-password" required>
          <p class="petunjuk">Minimal 8 karakter, berisi huruf dan angka.</p>
          <label for="ulang">Ulangi password baru</label>
          <input type="password" id="ulang" name="ulang" maxlength="72" autocomplete="new-password" required>
          <button type="submit" class="tombol">Ganti password</button>
        </form>
      </section>

      <section class="panel" aria-labelledby="judul-perangkat">
        <h2 id="judul-perangkat">Perangkat yang diingat</h2>
        <p class="kosong">Ada <strong><?= $jml_perangkat ?></strong> perangkat yang tetap masuk lewat "Ingat saya".</p>
        <form method="post" data-konfirmasi="Lupakan semua perangkat?">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="lupakan_perangkat">
          <button type="submit" class="tombol garis kecil">Lupakan semua perangkat</button>
        </form>
      </section>
    </div>

  </div>
<?php halaman_akhir(); ?>
