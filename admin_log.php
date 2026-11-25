<?php
require "koneksi.php";
require "layout.php";
wajib_admin();

$hal = max(1, (int) ($_GET["hal"] ?? 1));
$per = 30;
$log = [];
$total = 0;

try {
    $total = (int) ambil_semua($koneksi, "SELECT COUNT(*) AS n FROM log_aktivitas")[0]["n"];
    $jml_hal = max(1, (int) ceil($total / $per));
    $hal = min($hal, $jml_hal);
    $offset = ($hal - 1) * $per;
    $log = ambil_semua($koneksi,
        "SELECT l.waktu, l.aksi, l.target, l.ip, u.username
         FROM log_aktivitas l LEFT JOIN users u ON u.id_user = l.id_admin
         ORDER BY l.id_log DESC LIMIT $per OFFSET $offset");
} catch (mysqli_sql_exception $e) {
    error_log($e->getMessage());
    $jml_hal = 1;
}

halaman_awal("Log aktivitas", "log");
?>

  <div class="kepala-halaman">
    <div>
      <h1>Log aktivitas</h1>
      <p>Jejak tindakan admin: <?= number_format($total, 0, ",", ".") ?> catatan.</p>
    </div>
  </div>


  <section class="panel">
    <?php if (count($log) === 0): ?>
      <p class="kosong">Belum ada aktivitas tercatat.</p>
    <?php else: ?>
      <div class="gulir">
        <table>
          <thead><tr><th>Waktu</th><th>Admin</th><th>Tindakan</th><th>Target</th><th>IP</th></tr></thead>
          <tbody>
            <?php foreach ($log as $l): ?>
              <tr>
                <td class="angka"><?= h(date("d/m/Y H:i", strtotime($l["waktu"]))) ?></td>
                <td><?= h($l["username"] ?? "(akun dihapus)") ?></td>
                <td><?= h($l["aksi"]) ?></td>
                <td><?= h($l["target"]) ?></td>
                <td class="kecil"><?= h($l["ip"]) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($jml_hal > 1): ?>
        <nav class="paging" aria-label="Halaman">
          <?php if ($hal > 1): ?><a href="?hal=<?= $hal - 1 ?>">&lsaquo; Sebelumnya</a><?php endif; ?>
          <span>Halaman <?= $hal ?> dari <?= $jml_hal ?></span>
          <?php if ($hal < $jml_hal): ?><a href="?hal=<?= $hal + 1 ?>">Berikutnya &rsaquo;</a><?php endif; ?>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </section>
<?php halaman_akhir(); ?>
