<?php
require "koneksi.php";
require "layout.php";
wajib_admin();

// ---------- Statistik ----------
$u = ambil_semua($koneksi,
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(aktif = 1), 0) AS aktif,
            COALESCE(SUM(role = 'admin'), 0) AS admin,
            COALESCE(SUM(dibuat_pada >= DATE_SUB(NOW(), INTERVAL 7 DAY)), 0) AS baru7,
            COALESCE(SUM(terakhir_login >= DATE_SUB(NOW(), INTERVAL 1 DAY)), 0) AS login24
     FROM users")[0];

$terbaru = ambil_semua($koneksi,
    "SELECT nama_lengkap, username, email, role, aktif, dibuat_pada
     FROM users ORDER BY id_user DESC LIMIT 6");

$log = [];
try {
    $log = ambil_semua($koneksi,
        "SELECT l.waktu, l.aksi, l.target, u.username
         FROM log_aktivitas l LEFT JOIN users u ON u.id_user = l.id_admin
         ORDER BY l.id_log DESC LIMIT 8");
} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());
}

$stat = [
    ["Total pengguna",       (int) $u["total"]],
    ["Akun aktif",           (int) $u["aktif"]],
    ["Akun nonaktif",        (int) $u["total"] - (int) $u["aktif"]],
    ["Administrator",        (int) $u["admin"]],
    ["Daftar 7 hari terakhir", (int) $u["baru7"]],
    ["Login 24 jam terakhir", (int) $u["login24"]],
];

halaman_awal("Admin", "admin");
?>

  <div class="kepala-halaman">
    <div>
      <h1>Panel admin</h1>
      <p>Pantau dan kelola akun pengguna.</p>
    </div>
  </div>


  <section class="stat-grid" aria-label="Statistik">
    <?php foreach ($stat as $s): ?>
      <div class="stat kartu naik">
        <span class="kecil"><?= h($s[0]) ?></span>
        <strong class="angka"><?= number_format($s[1], 0, ",", ".") ?></strong>
      </div>
    <?php endforeach; ?>
  </section>

  <div class="grid-2">
    <section class="kartu" aria-labelledby="w-user">
      <div class="widget-kepala">
        <h2 id="w-user">Pengguna terbaru</h2>
        <a class="tautan-hijau" href="admin_pengguna.php">Kelola semua</a>
      </div>
      <div class="gulir">
        <table>
          <thead><tr><th>Nama</th><th>Peran</th><th>Status</th><th>Daftar</th></tr></thead>
          <tbody>
            <?php foreach ($terbaru as $p): ?>
              <tr>
                <td><?= h($p["nama_lengkap"]) ?><span class="sub-kat">@<?= h($p["username"]) ?></span></td>
                <td><span class="badge <?= $p["role"] === 'admin' ? 'biru' : 'abu' ?>"><?= h($p["role"]) ?></span></td>
                <td><span class="badge <?= (int) $p["aktif"] === 1 ? 'hijau' : 'merah' ?>"><?= (int) $p["aktif"] === 1 ? "aktif" : "nonaktif" ?></span></td>
                <td><?= h(date("d/m/Y", strtotime($p["dibuat_pada"]))) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="kartu" aria-labelledby="w-log">
      <div class="widget-kepala">
        <h2 id="w-log">Aktivitas admin terbaru</h2>
        <a class="tautan-hijau" href="admin_log.php">Lihat semua</a>
      </div>
      <?php if (count($log) === 0): ?>
        <p class="kosong">Belum ada aktivitas tercatat.</p>
      <?php else: ?>
        <ul class="terbaru">
          <?php foreach ($log as $l): ?>
            <li class="baris-log">
              <span class="terbaru-tgl"><?= h(date("d/m", strtotime($l["waktu"]))) ?></span>
              <span class="terbaru-ket">
                <?= h($l["aksi"]) ?>
                <small><?= h($l["target"]) ?> · oleh <?= h($l["username"] ?? "(akun dihapus)") ?></small>
              </span>
              <span class="kecil"><?= h(date("H:i", strtotime($l["waktu"]))) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>

<?php halaman_akhir(); ?>
