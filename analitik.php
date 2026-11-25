<?php
require "koneksi.php";
require "layout.php";
wajib_user();

$id_user = (int) $_SESSION["id_user"];
$bulan_ini = date("Y-m");
$bulan = (string) ($_GET["bulan"] ?? $bulan_ini);
if (!bulan_valid($bulan) || $bulan > $bulan_ini) {
    $bulan = $bulan_ini;
}
$dt = DateTime::createFromFormat("Y-m-d", $bulan . "-01");
$sebelum = (clone $dt)->modify("-1 month")->format("Y-m");
$sesudah = (clone $dt)->modify("+1 month")->format("Y-m");
$bisa_maju = $bulan < $bulan_ini;

// ---------- 6 bulan sampai bulan terpilih ----------
$daftar_bulan = [];
for ($i = 5; $i >= 0; $i--) {
    $daftar_bulan[] = (clone $dt)->modify("-$i month")->format("Y-m");
}
$baris = ambil_semua($koneksi,
    "SELECT bulan, total_masuk, total_keluar FROM v_ringkasan_bulanan
     WHERE id_user = ? AND bulan >= ? AND bulan <= ?",
    "iss", [$id_user, $daftar_bulan[0], $bulan]);
$data_bulan = [];
foreach ($baris as $r) {
    $data_bulan[$r["bulan"]] = $r;
}

$singkat = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];
$label = [];
$masuk = [];
$keluar = [];
foreach ($daftar_bulan as $b) {
    $label[]  = $singkat[(int) substr($b, 5, 2) - 1];
    $masuk[]  = (float) ($data_bulan[$b]["total_masuk"] ?? 0);
    $keluar[] = (float) ($data_bulan[$b]["total_keluar"] ?? 0);
}

$m_ini = (float) ($data_bulan[$bulan]["total_masuk"] ?? 0);
$k_ini = (float) ($data_bulan[$bulan]["total_keluar"] ?? 0);

// ---------- Rincian per kategori dan subkategori bulan terpilih ----------
$rincian = ambil_semua($koneksi,
    "SELECT jenis, kategori, subkategori, total FROM v_pengeluaran_subkategori
     WHERE id_user = ? AND bulan = ? ORDER BY jenis, kategori, total DESC", "is", [$id_user, $bulan]);

// Kelompokkan: [jenis][kategori] = ["total" => .., "sub" => [...]]
$kelompok = ["pemasukan" => [], "pengeluaran" => []];
foreach ($rincian as $r) {
    $j = $r["jenis"];
    $k = $r["kategori"];
    if (!isset($kelompok[$j][$k])) {
        $kelompok[$j][$k] = ["total" => 0.0, "sub" => []];
    }
    $kelompok[$j][$k]["total"] += (float) $r["total"];
    $kelompok[$j][$k]["sub"][] = ["nama" => $r["subkategori"], "total" => (float) $r["total"]];
}
foreach ($kelompok as $j => $isi) {
    uasort($kelompok[$j], function ($a, $b) { return $b["total"] <=> $a["total"]; });
}

halaman_awal("Analitik", "analitik");
?>

  <div class="kepala-halaman">
    <div>
      <h1>Analitik</h1>
      <p>Lihat ke mana uangmu mengalir dari bulan ke bulan.</p>
    </div>
    <div class="periode periode-kecil">
      <a class="panah" href="?bulan=<?= h($sebelum) ?>" aria-label="Bulan sebelumnya">&lsaquo;</a>
      <div class="periode-label"><?= h(format_bulan($bulan)) ?></div>
      <?php if ($bisa_maju): ?>
        <a class="panah" href="?bulan=<?= h($sesudah) ?>" aria-label="Bulan berikutnya">&rsaquo;</a>
      <?php else: ?>
        <span class="panah mati" aria-hidden="true">&rsaquo;</span>
      <?php endif; ?>
    </div>
  </div>

  <section class="ringkasan ringkasan-angg" aria-label="Ringkasan bulan">
    <div class="saldo <?= $m_ini - $k_ini < 0 ? 'minus' : '' ?>">
      <span class="label">Selisih <?= h(format_bulan($bulan)) ?></span>
      <strong><?= ($m_ini - $k_ini < 0 ? "−" : "") . rupiah(abs($m_ini - $k_ini)) ?></strong>
    </div>
    <div class="sisi masuk">
      <span class="label">Pemasukan</span>
      <strong><?= rupiah($m_ini) ?></strong>
    </div>
    <div class="sisi keluar">
      <span class="label">Pengeluaran</span>
      <strong><?= rupiah($k_ini) ?></strong>
    </div>
  </section>

  <div class="grid-2 analitik-atas">
    <section class="kartu" aria-labelledby="w-batang">
      <h2 id="w-batang">Pemasukan vs pengeluaran (6 bulan)</h2>
      <div data-batang="<?= h(json_encode(["label" => $label, "masuk" => $masuk, "keluar" => $keluar], JSON_UNESCAPED_UNICODE)) ?>"></div>
      <div class="legenda-baris">
        <span><span class="titik titik-masuk"></span>Pemasukan</span>
        <span><span class="titik titik-keluar"></span>Pengeluaran</span>
      </div>
    </section>

    <section class="kartu" aria-labelledby="w-tabel">
      <h2 id="w-tabel">Rekap per bulan</h2>
      <div class="gulir">
        <table>
          <thead>
            <tr><th>Bulan</th><th class="kanan">Masuk</th><th class="kanan">Keluar</th><th class="kanan">Selisih</th></tr>
          </thead>
          <tbody>
            <?php foreach (array_reverse($daftar_bulan) as $b): ?>
              <?php
                $mm = (float) ($data_bulan[$b]["total_masuk"] ?? 0);
                $kk = (float) ($data_bulan[$b]["total_keluar"] ?? 0);
                $sel = $mm - $kk;
              ?>
              <tr>
                <td><?= h(format_bulan($b)) ?></td>
                <td class="kanan angka"><?= rupiah($mm) ?></td>
                <td class="kanan angka"><?= rupiah($kk) ?></td>
                <td class="kanan nominal <?= $sel >= 0 ? 'pemasukan' : 'pengeluaran' ?>"><?= ($sel < 0 ? "−" : "") . rupiah(abs($sel)) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>

  <div class="grid-2">
    <?php foreach (["pengeluaran" => "Pengeluaran", "pemasukan" => "Pemasukan"] as $j => $judul): ?>
      <?php $total_jenis = $j === "pengeluaran" ? $k_ini : $m_ini; ?>
      <section class="kartu kat-blok" aria-label="<?= h($judul) ?> per kategori">
        <h2><span class="badge <?= $j === 'pemasukan' ? 'hijau' : 'merah' ?>"><?= h($judul) ?></span> per kategori</h2>
        <?php if (count($kelompok[$j]) === 0): ?>
          <p class="kosong">Belum ada data di bulan ini.</p>
        <?php else: ?>
          <?php foreach ($kelompok[$j] as $nama_kat => $isi): ?>
            <div class="kat-item">
              <div class="kat-kepala">
                <strong><?= h($nama_kat) ?></strong>
                <span class="angka"><?= rupiah($isi["total"]) ?>
                  <small class="kecil-inline">(<?= $total_jenis > 0 ? round($isi["total"] / $total_jenis * 100) : 0 ?>%)</small>
                </span>
              </div>
              <progress class="<?= h($j) ?>" max="<?= h($total_jenis) ?>" value="<?= h($isi["total"]) ?>"></progress>
              <div class="chips">
                <?php foreach ($isi["sub"] as $s): ?>
                  <span class="chip bawaan"><?= h($s["nama"]) ?> · <?= rupiah($s["total"]) ?></span>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  </div>
<?php halaman_akhir(["grafik.js"]); ?>
