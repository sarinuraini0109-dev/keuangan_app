<?php
require "koneksi.php";
require "layout.php";
wajib_user();

$id_user   = (int) $_SESSION["id_user"];
$nama_user = $_SESSION["nama"] ?? "Pengguna";

$pesan = "";
$tipe_pesan = "";

// ---------- Tambah dompet ----------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_cek()) {
        $pesan = pesan_csrf();
    } elseif (($_POST["aksi"] ?? "") === "tambah_dompet") {
        $nama_dompet = mb_substr(trim((string) ($_POST["nama_dompet"] ?? "")), 0, 50);
        $jenis       = (string) ($_POST["jenis"] ?? "");
        $digit       = preg_replace('/\D/', '', (string) ($_POST["saldo_awal"] ?? ""));

        if ($nama_dompet === "") {
            $pesan = "Nama dompet wajib diisi.";
        } elseif (!in_array($jenis, ["tunai", "bank", "e-wallet"], true)) {
            $pesan = "Jenis dompet tidak valid.";
        } elseif (strlen($digit) > 13) {
            $pesan = "Saldo awal terlalu besar.";
        } else {
            $saldo_awal = $digit === "" ? 0.0 : (float) $digit;
            try {
                $stmt = $koneksi->prepare("INSERT INTO dompet (id_user, nama, jenis, saldo_awal) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("issd", $id_user, $nama_dompet, $jenis, $saldo_awal);
                $stmt->execute();
                set_flash("Dompet \"" . $nama_dompet . "\" ditambahkan.");
                header("Location: dashboard.php");
                exit;
            } catch (mysqli_sql_exception $e) {
                if ((int) $e->getCode() === 1062) {
                    $pesan = "Nama dompet itu sudah dipakai.";
                } else {
                    error_log($e->getMessage());
                    $pesan = "Dompet gagal disimpan. Coba lagi.";
                }
            }
        }
    }
    $tipe_pesan = "gagal";
}

$flash = ambil_flash();
if ($flash) {
    $pesan = $flash["pesan"];
    $tipe_pesan = $flash["tipe"];
}

// ---------- Periode (bulan) ----------
$nama_bulan = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli",
               "Agustus", "September", "Oktober", "November", "Desember"];
$bulan_ini = date("Y-m");
$bulan = (string) ($_GET["bulan"] ?? $bulan_ini);
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan) || $bulan > $bulan_ini) {
    $bulan = $bulan_ini;
}
$awal = $bulan . "-01";
$dt   = DateTime::createFromFormat("Y-m-d", $awal);
$bulan_sebelum  = (clone $dt)->modify("-1 month")->format("Y-m");
$bulan_sesudah  = (clone $dt)->modify("+1 month")->format("Y-m");
$bisa_maju      = $bulan < $bulan_ini;
$label_periode  = $nama_bulan[(int) $dt->format("n") - 1] . " " . $dt->format("Y");
$jumlah_hari    = (int) $dt->format("t");
$batas_hari     = ($bulan === $bulan_ini) ? (int) date("j") : $jumlah_hari;

// ---------- Data dompet ----------
$daftar_dompet = ambil_semua($koneksi,
    "SELECT d.id_dompet, d.nama, d.jenis, d.saldo_awal, s.saldo
     FROM dompet d JOIN saldo_dompet s ON s.id_dompet = d.id_dompet
     WHERE d.id_user = ? ORDER BY d.id_dompet", "i", [$id_user]);

$saldo_total = 0.0;
$saldo_awal_total = 0.0;
foreach ($daftar_dompet as $d) {
    $saldo_total      += (float) $d["saldo"];
    $saldo_awal_total += (float) $d["saldo_awal"];
}

// ---------- Total periode ----------
$tot = ambil_semua($koneksi,
    "SELECT COALESCE(SUM(CASE WHEN k.jenis = 'pemasukan'   THEN t.nominal END), 0) AS masuk,
            COALESCE(SUM(CASE WHEN k.jenis = 'pengeluaran' THEN t.nominal END), 0) AS keluar
     FROM transaksi t JOIN kategori k ON k.id_kategori = t.id_kategori
     WHERE t.id_user = ? AND t.tanggal >= ? AND t.tanggal < DATE_ADD(?, INTERVAL 1 MONTH)",
    "iss", [$id_user, $awal, $awal])[0];
$masuk  = (float) $tot["masuk"];
$keluar = (float) $tot["keluar"];
$arus   = $masuk - $keluar;

// ---------- Tren saldo harian ----------
$sebelum = ambil_semua($koneksi,
    "SELECT COALESCE(SUM(CASE WHEN k.jenis = 'pemasukan' THEN t.nominal ELSE -t.nominal END), 0) AS net
     FROM transaksi t JOIN kategori k ON k.id_kategori = t.id_kategori
     WHERE t.id_user = ? AND t.tanggal < ?", "is", [$id_user, $awal])[0];
$saldo_mulai = $saldo_awal_total + (float) $sebelum["net"];

$baris_harian = ambil_semua($koneksi,
    "SELECT t.tanggal,
            SUM(CASE WHEN k.jenis = 'pemasukan' THEN t.nominal ELSE -t.nominal END) AS net
     FROM transaksi t JOIN kategori k ON k.id_kategori = t.id_kategori
     WHERE t.id_user = ? AND t.tanggal >= ? AND t.tanggal < DATE_ADD(?, INTERVAL 1 MONTH)
     GROUP BY t.tanggal", "iss", [$id_user, $awal, $awal]);
$harian = [];
foreach ($baris_harian as $r) {
    $harian[$r["tanggal"]] = (float) $r["net"];
}

$tren_label = [];
$tren_nilai = [];
$saldo_jalan = $saldo_mulai;
$saldo_akhir = $saldo_mulai;
for ($hari = 1; $hari <= $jumlah_hari; $hari++) {
    $tgl = sprintf("%s-%02d", $bulan, $hari);
    $saldo_jalan += $harian[$tgl] ?? 0;
    $tren_label[] = $hari;
    if ($hari <= $batas_hari) {
        $tren_nilai[] = round($saldo_jalan, 2);
        $saldo_akhir = $saldo_jalan;
    } else {
        $tren_nilai[] = null;
    }
}
$perubahan = $saldo_mulai != 0 ? ($saldo_akhir - $saldo_mulai) / abs($saldo_mulai) * 100 : null;

// ---------- Struktur pengeluaran ----------
$per_kategori = ambil_semua($koneksi,
    "SELECT k.nama, SUM(t.nominal) AS total
     FROM transaksi t JOIN kategori k ON k.id_kategori = t.id_kategori
     WHERE t.id_user = ? AND k.jenis = 'pengeluaran'
       AND t.tanggal >= ? AND t.tanggal < DATE_ADD(?, INTERVAL 1 MONTH)
     GROUP BY k.id_kategori, k.nama ORDER BY total DESC", "iss", [$id_user, $awal, $awal]);

$donut_item = [];
$lainnya = 0.0;
foreach ($per_kategori as $i => $r) {
    if ($i < 5) {
        $donut_item[] = ["nama" => $r["nama"], "total" => (float) $r["total"]];
    } else {
        $lainnya += (float) $r["total"];
    }
}
if ($lainnya > 0) {
    $donut_item[] = ["nama" => "Lainnya", "total" => $lainnya];
}

// ---------- Subkategori terbesar ----------
$sub_teratas = ambil_semua($koneksi,
    "SELECT COALESCE(s.nama, 'Tanpa subkategori') AS nama, k.nama AS kategori, SUM(t.nominal) AS total
     FROM transaksi t
     JOIN kategori k ON k.id_kategori = t.id_kategori
     LEFT JOIN subkategori s ON s.id_subkategori = t.id_subkategori
     WHERE t.id_user = ? AND k.jenis = 'pengeluaran'
       AND t.tanggal >= ? AND t.tanggal < DATE_ADD(?, INTERVAL 1 MONTH)
     GROUP BY s.id_subkategori, s.nama, k.id_kategori, k.nama
     ORDER BY total DESC LIMIT 5", "iss", [$id_user, $awal, $awal]);
$sub_maks = count($sub_teratas) > 0 ? (float) $sub_teratas[0]["total"] : 0;

// ---------- Transaksi terbaru ----------
$terbaru = ambil_semua($koneksi,
    "SELECT t.tanggal, t.nominal, t.keterangan, k.nama AS kategori, k.jenis, s.nama AS subkategori, d.nama AS dompet
     FROM transaksi t
     JOIN kategori k ON k.id_kategori = t.id_kategori
     JOIN dompet d ON d.id_dompet = t.id_dompet
     LEFT JOIN subkategori s ON s.id_subkategori = t.id_subkategori
     WHERE t.id_user = ?
     ORDER BY t.tanggal DESC, t.id_transaksi DESC LIMIT 6", "i", [$id_user]);

// ---------- Gauge ----------
// Posisi jarum 0..1. Titik p = [a, b, c, d]: nilai a -> 0, b -> 1/3, c -> 2/3, d -> 1
function skala($v, $p)
{
    if ($v <= $p[0]) return 0.0;
    if ($v >= $p[3]) return 1.0;
    for ($i = 0; $i < 3; $i++) {
        if ($v <= $p[$i + 1]) {
            return ($i + ($v - $p[$i]) / ($p[$i + 1] - $p[$i])) / 3;
        }
    }
    return 1.0;
}

// Rata-rata pengeluaran per bulan dari 90 hari terakhir
$rata = ambil_semua($koneksi,
    "SELECT COALESCE(SUM(t.nominal), 0) AS total
     FROM transaksi t JOIN kategori k ON k.id_kategori = t.id_kategori
     WHERE t.id_user = ? AND k.jenis = 'pengeluaran' AND t.tanggal >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)",
    "i", [$id_user])[0];
$rata_bulanan = (float) $rata["total"] / 3;

// 1) Saldo: cukup untuk berapa bulan pengeluaran
if ($rata_bulanan > 0) {
    $bulan_cukup = $saldo_total / $rata_bulanan;
    $f_saldo = skala($bulan_cukup, [0, 1, 3, 6]);
    $ket_saldo = $bulan_cukup > 0
        ? "Cukup ± " . number_format($bulan_cukup, 1, ",", ".") . " bulan pengeluaran"
        : "Saldo habis atau minus";
} else {
    $f_saldo = $saldo_total > 0 ? 1.0 : 0.0;
    $ket_saldo = "Belum ada riwayat pengeluaran";
}

// 2) Arus kas: berapa persen pemasukan yang tersisa
if ($masuk > 0) {
    $rasio_tabung = $arus / $masuk;
    $f_arus = skala($rasio_tabung, [-0.2, 0, 0.2, 0.5]);
    $ket_arus = $rasio_tabung >= 0
        ? round($rasio_tabung * 100) . "% pemasukan tersisa"
        : "Defisit " . abs(round($rasio_tabung * 100)) . "% dari pemasukan";
} else {
    $f_arus = $keluar > 0 ? 0.0 : 0.5;
    $ket_arus = $keluar > 0 ? "Belum ada pemasukan" : "Belum ada transaksi";
}

// 3) Pengeluaran: berapa persen dari pemasukan
if ($masuk > 0) {
    $rasio_keluar = $keluar / $masuk;
    $f_keluar = skala($rasio_keluar, [0, 0.6, 0.9, 1.2]);
    $ket_keluar = round($rasio_keluar * 100) . "% dari pemasukan";
} else {
    $f_keluar = $keluar > 0 ? 1.0 : 0.0;
    $ket_keluar = $keluar > 0 ? "Belum ada pemasukan" : "Belum ada pengeluaran";
}

$gauge = [
    ["judul" => "SALDO",       "f" => $f_saldo,  "arah" => "naik",  "teks" => rupiah($saldo_total), "ket" => $ket_saldo],
    ["judul" => "ARUS KAS",    "f" => $f_arus,   "arah" => "naik",  "teks" => ($arus < 0 ? "−" : "") . rupiah(abs($arus)), "ket" => $ket_arus],
    ["judul" => "PENGELUARAN", "f" => $f_keluar, "arah" => "turun", "teks" => rupiah($keluar), "ket" => $ket_keluar],
];

function json_attr($data)
{
    return h(json_encode($data, JSON_UNESCAPED_UNICODE));
}
?>
<?php halaman_awal("Dashboard", "dashboard"); ?>

  <div class="kepala-halaman">
    <div>
      <h1>Halo, <span class="teks-grad"><?= h($nama_user) ?></span></h1>
      <p>Ringkasan keuanganmu, dari dompet sampai tren saldo.</p>
    </div>
    <a href="dompet.php" class="tombol garis kecil">Kelola dompet</a>
  </div>

  <?php if ($pesan !== ""): ?>
    <p class="pesan <?= h($tipe_pesan) ?>" role="status"><?= h($pesan) ?></p>
  <?php endif; ?>

  <!-- Kartu dompet -->
  <ul class="akun-daftar" aria-label="Dompet">
    <?php foreach ($daftar_dompet as $d): ?>
      <li class="akun-kartu tilt warna-<?= h(str_replace('-', '', $d["jenis"])) ?>">
        <span class="akun-jenis"><?= h($d["jenis"]) ?></span>
        <span class="akun-nama"><?= h($d["nama"]) ?></span>
        <strong class="akun-saldo"><?= rupiah($d["saldo"]) ?></strong>
      </li>
    <?php endforeach; ?>

    <li class="akun-tambah">
      <details>
        <summary>+ Tambah dompet</summary>
        <form method="post" class="form-dompet" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="tambah_dompet">
          <label for="nama_dompet">Nama dompet</label>
          <input type="text" id="nama_dompet" name="nama_dompet" maxlength="50" placeholder="contoh: Rekening BRI" required>
          <label for="jenis">Jenis</label>
          <select id="jenis" name="jenis">
            <option value="tunai">Tunai</option>
            <option value="bank">Bank</option>
            <option value="e-wallet">E-wallet</option>
          </select>
          <label for="saldo_awal">Saldo awal (Rp)</label>
          <input type="text" id="saldo_awal" name="saldo_awal" class="uang" inputmode="numeric" maxlength="16" placeholder="0">
          <button type="submit" class="tombol">Simpan dompet</button>
        </form>
      </details>
    </li>
  </ul>

  <!-- Pemilih periode -->
  <div class="periode">
    <a class="panah" href="?bulan=<?= h($bulan_sebelum) ?>" aria-label="Bulan sebelumnya">&lsaquo;</a>
    <div class="periode-label"><?= h($label_periode) ?></div>
    <?php if ($bisa_maju): ?>
      <a class="panah" href="?bulan=<?= h($bulan_sesudah) ?>" aria-label="Bulan berikutnya">&rsaquo;</a>
    <?php else: ?>
      <span class="panah mati" aria-hidden="true">&rsaquo;</span>
    <?php endif; ?>
    <?php if ($bulan !== $bulan_ini): ?>
      <a class="tautan-hijau" href="dashboard.php">Ke bulan ini</a>
    <?php endif; ?>
  </div>

  <div class="widget-grid">

    <!-- Ringkasan: tiga gauge -->
    <section class="widget" aria-labelledby="w-ringkasan">
      <h2 id="w-ringkasan">Ringkasan</h2>
      <div class="gauges">
        <?php foreach ($gauge as $g): ?>
          <div class="gauge-item">
            <div class="gauge" data-gauge="<?= json_attr(["f" => $g["f"], "arah" => $g["arah"], "judul" => $g["judul"]]) ?>"></div>
            <span class="gauge-judul"><?= h($g["judul"]) ?></span>
            <strong class="gauge-nilai"><?= h($g["teks"]) ?></strong>
            <small class="gauge-ket"><?= h($g["ket"]) ?></small>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Tren saldo -->
    <section class="widget" aria-labelledby="w-tren">
      <h2 id="w-tren">Tren saldo</h2>
      <div class="tren-atas">
        <div>
          <span class="kecil"><?= h($label_periode) ?></span>
          <div class="tren-angka"><?= rupiah($saldo_akhir) ?></div>
        </div>
        <div class="tren-banding">
          <span class="kecil">Dibanding awal bulan</span>
          <?php if ($perubahan === null): ?>
            <span class="lencana datar">—</span>
          <?php else: ?>
            <span class="lencana <?= $perubahan > 0.05 ? 'naik' : ($perubahan < -0.05 ? 'turun' : 'datar') ?>">
              <?= $perubahan > 0.05 ? "▲" : ($perubahan < -0.05 ? "▼" : "●") ?>
              <?= number_format(abs($perubahan), 1, ",", ".") ?>%
            </span>
          <?php endif; ?>
        </div>
      </div>
      <div class="tren" data-tren="<?= json_attr(["label" => $tren_label, "nilai" => $tren_nilai]) ?>"></div>
    </section>

    <!-- Struktur pengeluaran -->
    <section class="widget" aria-labelledby="w-struktur">
      <h2 id="w-struktur">Struktur pengeluaran</h2>
      <div class="donut" data-donut="<?= json_attr(["item" => $donut_item, "total" => $keluar]) ?>"></div>
    </section>

    <!-- Subkategori terbesar -->
    <section class="widget" aria-labelledby="w-sub">
      <h2 id="w-sub">Pengeluaran terbesar</h2>
      <?php if (count($sub_teratas) === 0): ?>
        <p class="kosong">Belum ada pengeluaran pada periode ini.</p>
      <?php else: ?>
        <ul class="batang-daftar">
          <?php foreach ($sub_teratas as $s): ?>
            <li>
              <div class="batang-atas">
                <span><?= h($s["nama"]) ?> <small><?= h($s["kategori"]) ?></small></span>
                <strong><?= rupiah($s["total"]) ?></strong>
              </div>
              <progress max="<?= h($sub_maks) ?>" value="<?= h($s["total"]) ?>"></progress>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <!-- Transaksi terbaru -->
    <section class="widget span-2" aria-labelledby="w-terbaru">
      <div class="widget-kepala">
        <h2 id="w-terbaru">Transaksi terbaru</h2>
        <a class="tautan-hijau" href="transaksi.php">Lihat semua</a>
      </div>
      <?php if (count($terbaru) === 0): ?>
        <p class="kosong">Belum ada transaksi. Klik <strong>+ Catat</strong> untuk mulai.</p>
      <?php else: ?>
        <ul class="terbaru">
          <?php foreach ($terbaru as $t): ?>
            <li>
              <span class="terbaru-tgl"><?= date("d/m", strtotime($t["tanggal"])) ?></span>
              <span class="terbaru-ket">
                <?= h($t["keterangan"] !== null && $t["keterangan"] !== "" ? $t["keterangan"] : $t["kategori"]) ?>
                <small><?= h($t["kategori"]) ?><?= $t["subkategori"] !== null ? " › " . h($t["subkategori"]) : "" ?> · <?= h($t["dompet"]) ?></small>
              </span>
              <span class="nominal <?= h($t["jenis"]) ?>">
                <?= $t["jenis"] === "pemasukan" ? "+" : "−" ?> <?= rupiah($t["nominal"]) ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

  </div>
<?php halaman_akhir(["grafik.js"]); ?>
