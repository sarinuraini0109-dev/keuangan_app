<?php
require "koneksi.php";
require "layout.php";
wajib_admin();

$id_admin = (int) $_SESSION["id_user"];
$pesan = "";
$tipe_pesan = "gagal";

// ---------- Tindakan terhadap pengguna ----------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $aksi = (string) ($_POST["aksi"] ?? "");
    $id   = (int) ($_POST["id"] ?? 0);

    if (!csrf_cek()) {
        $pesan = pesan_csrf();
    } elseif ($id === $id_admin) {
        $pesan = "Akunmu sendiri tidak bisa diubah dari sini. Gunakan halaman Profil.";
    } else {
        $target = ambil_semua($koneksi, "SELECT id_user, username, role, aktif FROM users WHERE id_user = ?", "i", [$id]);
        if (count($target) === 0) {
            $pesan = "Pengguna tidak ditemukan.";
        } else {
            $t = $target[0];
            $nama_target = "@" . $t["username"];

            try {
                if ($aksi === "aktif") {
                    $nilai = (int) ($_POST["nilai"] ?? 1) === 1 ? 1 : 0;
                    $stmt = $koneksi->prepare("UPDATE users SET aktif = ? WHERE id_user = ?");
                    $stmt->bind_param("ii", $nilai, $id);
                    $stmt->execute();
                    if ($nilai === 0) {
                        hapus_semua_token_ingat($koneksi, $id);   // putuskan "ingat saya"
                    }
                    catat_log($koneksi, $id_admin, $nilai === 1 ? "Mengaktifkan akun" : "Menonaktifkan akun", $nama_target);
                    set_flash($nilai === 1 ? "Akun $nama_target diaktifkan." : "Akun $nama_target dinonaktifkan.");

                } elseif ($aksi === "role") {
                    $nilai = ($_POST["nilai"] ?? "") === "admin" ? "admin" : "user";
                    $stmt = $koneksi->prepare("UPDATE users SET role = ? WHERE id_user = ?");
                    $stmt->bind_param("si", $nilai, $id);
                    $stmt->execute();
                    catat_log($koneksi, $id_admin, "Mengubah peran menjadi " . $nilai, $nama_target);
                    set_flash("Peran $nama_target sekarang: $nilai.");

                } elseif ($aksi === "reset_link") {
                    $stmt = $koneksi->prepare("DELETE FROM password_reset WHERE id_user = ? AND dipakai = 0");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $token = bin2hex(random_bytes(32));
                    $hash  = hash("sha256", $token);
                    $stmt = $koneksi->prepare(
                        "INSERT INTO password_reset (id_user, token_hash, kedaluwarsa)
                         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))"
                    );
                    $stmt->bind_param("is", $id, $hash);
                    $stmt->execute();
                    catat_log($koneksi, $id_admin, "Membuat tautan reset password", $nama_target);
                    set_flash("Tautan reset untuk $nama_target (berlaku 30 menit, sekali pakai): "
                              . url_dasar() . "/reset.php?token=" . $token);

                } elseif ($aksi === "hapus") {
                    // Hapus transaksi lebih dulu, sisanya ikut terhapus lewat ON DELETE CASCADE
                    $koneksi->begin_transaction();
                    $stmt = $koneksi->prepare("DELETE FROM transaksi WHERE id_user = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $stmt = $koneksi->prepare("DELETE FROM users WHERE id_user = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $koneksi->commit();
                    catat_log($koneksi, $id_admin, "Menghapus akun", $nama_target);
                    set_flash("Akun $nama_target dan seluruh datanya dihapus.");
                } else {
                    $pesan = "Aksi tidak dikenal.";
                }
            } catch (mysqli_sql_exception $e) {
                error_log($e->getMessage());
                $pesan = "Tindakan gagal dijalankan.";
            }

            if ($pesan === "") {
                header("Location: " . "admin_pengguna.php" . (!empty($_POST["kembali"]) ? "?" . http_build_query(["q" => (string) ($_POST["q"] ?? ""), "role" => (string) ($_POST["role_f"] ?? ""), "status" => (string) ($_POST["status_f"] ?? ""), "hal" => (int) ($_POST["hal"] ?? 1)]) : ""));
                exit;
            }
        }
    }
}

$flash = ambil_flash();
if ($flash) {
    $pesan = $flash["pesan"];
    $tipe_pesan = $flash["tipe"];
}

// ---------- Pencarian, filter, halaman ----------
$q      = mb_substr(trim((string) ($_GET["q"] ?? "")), 0, 60);
$role_f = in_array($_GET["role"] ?? "", ["user", "admin"], true) ? $_GET["role"] : "";
$stat_f = in_array($_GET["status"] ?? "", ["aktif", "nonaktif"], true) ? $_GET["status"] : "";
$hal    = max(1, (int) ($_GET["hal"] ?? 1));
$per    = 15;

$kondisi = ["1 = 1"];
$tipe = "";
$param = [];
if ($q !== "") {
    $like = "%" . addcslashes($q, "%_\\") . "%";
    $kondisi[] = "(u.nama_lengkap LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $tipe .= "sss";
    array_push($param, $like, $like, $like);
}
if ($role_f !== "") {
    $kondisi[] = "u.role = ?";
    $tipe .= "s";
    $param[] = $role_f;
}
if ($stat_f !== "") {
    $kondisi[] = "u.aktif = " . ($stat_f === "aktif" ? "1" : "0");
}
$where = implode(" AND ", $kondisi);

$total = (int) ambil_semua($koneksi, "SELECT COUNT(*) AS n FROM users u WHERE $where", $tipe, $param)[0]["n"];
$jml_hal = max(1, (int) ceil($total / $per));
$hal = min($hal, $jml_hal);
$offset = ($hal - 1) * $per;

$daftar = ambil_semua($koneksi,
    "SELECT u.id_user, u.nama_lengkap, u.username, u.email, u.role, u.aktif, u.dibuat_pada, u.terakhir_login
     FROM users u WHERE $where ORDER BY u.id_user DESC LIMIT $per OFFSET $offset", $tipe, $param);

function tautan_hal($n)
{
    $p = $_GET;
    $p["hal"] = $n;
    return "?" . http_build_query($p);
}

halaman_awal("Kelola pengguna", "pengguna");
?>

  <div class="kepala-halaman">
    <div>
      <h1>Pengguna</h1>
      <p><?= number_format($total, 0, ",", ".") ?> akun ditemukan.</p>
    </div>
  </div>

  <?php tampil_pesan($pesan, $tipe_pesan); ?>

  <section class="panel">
    <form method="get" class="filter-baris">
      <label class="sr" for="q">Cari pengguna</label>
      <input type="search" id="q" name="q" value="<?= h($q) ?>" placeholder="Cari nama, username, atau email" maxlength="60">
      <label class="sr" for="role">Peran</label>
      <select id="role" name="role">
        <option value="">Semua peran</option>
        <option value="user" <?= $role_f === "user" ? "selected" : "" ?>>User</option>
        <option value="admin" <?= $role_f === "admin" ? "selected" : "" ?>>Admin</option>
      </select>
      <label class="sr" for="status">Status</label>
      <select id="status" name="status">
        <option value="">Semua status</option>
        <option value="aktif" <?= $stat_f === "aktif" ? "selected" : "" ?>>Aktif</option>
        <option value="nonaktif" <?= $stat_f === "nonaktif" ? "selected" : "" ?>>Nonaktif</option>
      </select>
      <button type="submit" class="tombol kecil">Terapkan</button>
      <?php if ($q !== "" || $role_f !== "" || $stat_f !== ""): ?><a href="admin_pengguna.php" class="tautan-hijau">Reset filter</a><?php endif; ?>
    </form>

    <?php if (count($daftar) === 0): ?>
      <p class="kosong">Tidak ada pengguna yang cocok.</p>
    <?php else: ?>
      <div class="gulir">
        <table>
          <thead>
            <tr><th>Pengguna</th><th>Peran</th><th>Status</th><th>Login terakhir</th><th>Tindakan</th></tr>
          </thead>
          <tbody>
            <?php foreach ($daftar as $p): ?>
              <?php $saya = (int) $p["id_user"] === $id_admin; $aktif = (int) $p["aktif"] === 1; ?>
              <tr>
                <td>
                  <strong><?= h($p["nama_lengkap"]) ?></strong>
                  <span class="sub-kat">@<?= h($p["username"]) ?> · <?= h($p["email"]) ?></span>
                </td>
                <td><span class="badge <?= $p["role"] === 'admin' ? 'biru' : 'abu' ?>"><?= h($p["role"]) ?></span></td>
                <td><span class="badge <?= $aktif ? 'hijau' : 'merah' ?>"><?= $aktif ? "aktif" : "nonaktif" ?></span></td>
                <td><?= $p["terakhir_login"] ? h(date("d/m/Y H:i", strtotime($p["terakhir_login"]))) : "—" ?></td>
                <td>
                  <?php if ($saya): ?>
                    <span class="badge abu">Akunmu</span>
                  <?php else: ?>
                    <div class="aksi-baris">
                      <?php
                        $tindakan = [
                          ["aktif", $aktif ? "0" : "1", $aktif ? "Nonaktifkan" : "Aktifkan", false,
                           $aktif ? "Nonaktifkan akun ini? Pengguna tidak bisa masuk lagi." : ""],
                          ["role", $p["role"] === "admin" ? "user" : "admin", $p["role"] === "admin" ? "Jadikan user" : "Jadikan admin", false,
                           $p["role"] === "admin" ? "Cabut hak admin akun ini?" : "Berikan hak admin penuh kepada akun ini?"],
                          ["reset_link", "", "Tautan reset", false, ""],
                          ["hapus", "", "Hapus", true, "Hapus akun ini beserta SELURUH datanya? Tindakan ini tidak bisa dibatalkan."],
                        ];
                      ?>
                      <?php foreach ($tindakan as $t): ?>
                        <form method="post" <?= $t[4] !== "" ? 'data-konfirmasi="' . h($t[4]) . '"' : "" ?>>
                          <?= csrf_field() ?>
                          <input type="hidden" name="aksi" value="<?= h($t[0]) ?>">
                          <input type="hidden" name="id" value="<?= (int) $p["id_user"] ?>">
                          <input type="hidden" name="nilai" value="<?= h($t[1]) ?>">
                          <input type="hidden" name="kembali" value="1">
                          <input type="hidden" name="q" value="<?= h($q) ?>">
                          <input type="hidden" name="role_f" value="<?= h($role_f) ?>">
                          <input type="hidden" name="status_f" value="<?= h($stat_f) ?>">
                          <input type="hidden" name="hal" value="<?= (int) $hal ?>">
                          <button type="submit" class="<?= $t[3] ? 'hapus' : 'aksi-teks' ?>"><?= h($t[2]) ?></button>
                        </form>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($jml_hal > 1): ?>
        <nav class="paging" aria-label="Halaman">
          <?php if ($hal > 1): ?><a href="<?= h(tautan_hal($hal - 1)) ?>">&lsaquo; Sebelumnya</a><?php endif; ?>
          <span>Halaman <?= $hal ?> dari <?= $jml_hal ?></span>
          <?php if ($hal < $jml_hal): ?><a href="<?= h(tautan_hal($hal + 1)) ?>">Berikutnya &rsaquo;</a><?php endif; ?>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </section>
<?php halaman_akhir(); ?>
