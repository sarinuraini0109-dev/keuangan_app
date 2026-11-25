<?php
require "koneksi.php";
require "layout.php";
wajib_user();

$id_user = (int) $_SESSION["id_user"];
$bulan_ini = date("Y-m");
$bulan = (string) ($_GET["bulan"] ?? ($_POST["bulan"] ?? $bulan_ini));
if (!bulan_valid($bulan)) {
    $bulan = $bulan_ini;
}
$pesan = "";

// ---------- Simpan / hapus anggaran ----------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $aksi = $_POST["aksi"] ?? "";

    if (!csrf_cek()) {
        $pesan = pesan_csrf();
    } elseif ($aksi === "simpan") {
        $id_kat = (int) ($_POST["id_kategori"] ?? 0);
        $digit  = preg_replace('/\D/', '', (string) ($_POST["batas"] ?? ""));
        $batas  = ($digit === "" || strlen($digit) > 13) ? 0.0 : (float) $digit;

        $kat_ok = ambil_semua($koneksi,
            "SELECT 1 FROM kategori WHERE id_kategori = ? AND jenis = 'pengeluaran' AND (id_user IS NULL OR id_user = ?)",
            "ii", [$id_kat, $id_user]);
        if (count($kat_ok) === 0) {
            $pesan = "Pilih kategori pengeluaran yang valid.";
        } elseif ($batas <= 0) {
            $pesan = "Batas anggaran harus lebih dari 0.";
        } else {
            // Satu anggaran per kategori per bulan: jika sudah ada, nilainya diperbarui
            $stmt = $koneksi->prepare(
                "INSERT INTO anggaran (id_user, id_kategori, bulan, batas) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE batas = VALUES(batas)"
            );
            $stmt->bind_param("iisd", $id_user, $id_kat, $bulan, $batas);
            $stmt->execute();
            set_flash("Anggaran disimpan.");
            header("Location: anggaran.php?bulan=" . $bulan);
            exit;
        }
    } elseif ($aksi === "hapus") {
        $id = (int) ($_POST["id_anggaran"] ?? 0);
        $stmt = $koneksi->prepare("DELETE FROM anggaran WHERE id_anggaran = ? AND id_user = ?");
        $stmt->bind_param("ii", $id, $id_user);
        $stmt->execute();
        set_flash("Anggaran dihapus.");
        header("Location: anggaran.php?bulan=" . $bulan);
        exit;
    }
}

$tipe_pesan = "gagal";
$flash = ambil_flash();
if ($flash) {
    $pesan = $flash["pesan"];
    $tipe_pesan = $flash["tipe"];
}

// ---------- Data ----------
$awal = $bulan . "-01";
$dt = DateTime::createFromFormat("Y-m-d", $awal);
$sebelum = (clone $dt)->modify("-1 month")->format("Y-m");
$sesudah = (clone $dt)->modify("+1 month")->format("Y-m");

$daftar = ambil_semua($koneksi,
    "SELECT a.id_anggaran, k.nama, a.batas,
            COALESCE((SELECT SUM(t.nominal) FROM transaksi t
                      WHERE t.id_user = a.id_user AND t.id_kategori = a.id_kategori
                        AND t.tanggal >= ? AND t.tanggal < DATE_ADD(?, INTERVAL 1 MONTH)), 0) AS terpakai
     FROM anggaran a JOIN kategori k ON k.id_kategori = a.id_kategori
     WHERE a.id_user = ? AND a.bulan = ? ORDER BY k.nama",
    "ssis", [$awal, $awal, $id_user, $bulan]);

$total_batas = 0.0;
$total_pakai = 0.0;
foreach ($daftar as $a) {
    $total_batas += (float) $a["batas"];
    $total_pakai += (float) $a["terpakai"];
}

$kategori_keluar = ambil_semua($koneksi,
    "SELECT id_kategori, nama FROM kategori
     WHERE jenis = 'pengeluaran' AND (id_user IS NULL OR id_user = ?) ORDER BY nama", "i", [$id_user]);

halaman_awal("Anggaran", "anggaran");
?>

  <div class="kepala-halaman">
    <div>
      <h1>Anggaran</h1>
      <p>Batasi pengeluaran per kategori supaya tidak bocor.</p>
    </div>
    <div class="periode periode-kecil">
      <a class="panah" href="?bulan=<?= h($sebelum) ?>" aria-label="Bulan sebelumnya">&lsaquo;</a>
      <div class="periode-label"><?= h(format_bulan($bulan)) ?></div>
      <a class="panah" href="?bulan=<?= h($sesudah) ?>" aria-label="Bulan berikutnya">&rsaquo;</a>
    </div>
  </div>

  <?php tampil_pesan($pesan, $tipe_pesan); ?>

  <section class="ringkasan ringkasan-angg" aria-label="Ringkasan anggaran">
    <div class="saldo">
      <span class="label">Total anggaran <?= h(format_bulan($bulan)) ?></span>
      <strong><?= rupiah($total_batas) ?></strong>
    </div>
    <div class="sisi keluar">
      <span class="label">Terpakai</span>
      <strong><?= rupiah($total_pakai) ?></strong>
    </div>
    <div class="sisi masuk">
      <span class="label">Sisa</span>
      <strong><?= rupiah($total_batas - $total_pakai) ?></strong>
    </div>
  </section>

  <div class="kolom">

    <section class="panel" aria-labelledby="judul-angg">
      <h2 id="judul-angg">Atur anggaran</h2>
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="bulan" value="<?= h($bulan) ?>">
        <label for="id_kategori">Kategori pengeluaran</label>
        <select id="id_kategori" name="id_kategori">
          <?php foreach ($kategori_keluar as $k): ?>
            <option value="<?= (int) $k["id_kategori"] ?>"><?= h($k["nama"]) ?></option>
          <?php endforeach; ?>
        </select>
        <label for="batas">Batas bulan ini (Rp)</label>
        <input type="text" id="batas" name="batas" class="uang" inputmode="numeric" maxlength="16" placeholder="contoh: 500.000" required>
        <p class="petunjuk">Jika kategori sudah punya anggaran, nilainya diganti.</p>
        <button type="submit" class="tombol">Simpan anggaran</button>
      </form>
    </section>

    <section class="panel" aria-labelledby="judul-daftar-angg">
      <h2 id="judul-daftar-angg">Pemakaian anggaran</h2>
      <?php if (count($daftar) === 0): ?>
        <p class="kosong">Belum ada anggaran untuk <?= h(format_bulan($bulan)) ?>. Atur yang pertama lewat formulir di samping.</p>
      <?php else: ?>
        <?php foreach ($daftar as $a): ?>
          <?php
            $batas   = (float) $a["batas"];
            $pakai   = (float) $a["terpakai"];
            $persen  = $batas > 0 ? $pakai / $batas * 100 : 0;
            $kelas   = $persen > 100 ? "lewat" : ($persen >= 80 ? "waspada" : "aman");
            $sisa    = $batas - $pakai;
          ?>
          <div class="angg-item">
            <div class="angg-atas">
              <strong><?= h($a["nama"]) ?></strong>
              <span>
                <span class="badge <?= $kelas === 'lewat' ? 'merah' : ($kelas === 'waspada' ? 'abu' : 'hijau') ?>"><?= round($persen) ?>%</span>
                <form method="post" class="form-inline" data-konfirmasi="Hapus anggaran ini?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="hapus">
                  <input type="hidden" name="bulan" value="<?= h($bulan) ?>">
                  <input type="hidden" name="id_anggaran" value="<?= (int) $a["id_anggaran"] ?>">
                  <button type="submit" class="hapus">Hapus</button>
                </form>
              </span>
            </div>
            <progress class="<?= h($kelas) ?>" max="<?= h($batas) ?>" value="<?= h(min($pakai, $batas)) ?>"></progress>
            <div class="angg-angka">
              <span>Terpakai <strong class="angka"><?= rupiah($pakai) ?></strong> dari <?= rupiah($batas) ?></span>
              <span class="<?= $sisa < 0 ? 'teks-bahaya' : '' ?>">
                <?= $sisa >= 0 ? "Sisa " . rupiah($sisa) : "Lewat " . rupiah(abs($sisa)) ?>
              </span>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

  </div>
<?php halaman_akhir(); ?>
