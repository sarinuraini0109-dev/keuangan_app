<?php
require "koneksi.php";
require "layout.php";
wajib_user();

$id_user = (int) $_SESSION["id_user"];
$pesan = "";
$tipe_pesan = "gagal";

$nilai_form = [
    "id_kategori" => 0, "id_sub" => 0,
    "id_dompet"   => 0, "tanggal" => date("Y-m-d"),
    "nominal"     => "", "keterangan" => "",
];

// ---------- CREATE & DELETE ----------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $aksi = $_POST["aksi"] ?? "";

    if (!csrf_cek()) {
        $pesan = pesan_csrf();
    } elseif ($aksi === "tambah") {
        [$galat, $d] = proses_input_transaksi($koneksi, $id_user, $_POST);
        if ($galat === "") {
            $stmt = $koneksi->prepare(
                "INSERT INTO transaksi (id_user, id_dompet, id_kategori, id_subkategori, tanggal, nominal, keterangan)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("iiiisds", $id_user, $d["id_dompet"], $d["id_kategori"], $d["sub_simpan"],
                              $d["tanggal"], $d["nominal"], $d["keterangan"]);
            $stmt->execute();
            set_flash("Transaksi disimpan.");
            header("Location: transaksi.php");
            exit;
        }
        $pesan = $galat;
    } elseif ($aksi === "hapus") {
        $id = (int) ($_POST["id_transaksi"] ?? 0);
        // id_user ikut dicek: user tidak bisa menghapus data milik orang lain
        $stmt = $koneksi->prepare("DELETE FROM transaksi WHERE id_transaksi = ? AND id_user = ?");
        $stmt->bind_param("ii", $id, $id_user);
        $stmt->execute();
        set_flash("Transaksi dihapus.");
        header("Location: transaksi.php");
        exit;
    }
}

$flash = ambil_flash();
if ($flash) {
    $pesan = $flash["pesan"];
    $tipe_pesan = $flash["tipe"];
}

// ---------- READ ----------
$bulan = (string) ($_GET["bulan"] ?? "");
$ada_filter = bulan_valid($bulan);

$kondisi = "t.id_user = ?";
$tipe = "i";
$param = [$id_user];
if ($ada_filter) {
    $awal = $bulan . "-01";
    $kondisi .= " AND t.tanggal >= ? AND t.tanggal < DATE_ADD(?, INTERVAL 1 MONTH)";
    $tipe .= "ss";
    $param[] = $awal;
    $param[] = $awal;
}

$tot = ambil_semua($koneksi,
    "SELECT COALESCE(SUM(CASE WHEN k.jenis = 'pemasukan'   THEN t.nominal END), 0) AS masuk,
            COALESCE(SUM(CASE WHEN k.jenis = 'pengeluaran' THEN t.nominal END), 0) AS keluar
     FROM transaksi t JOIN kategori k ON k.id_kategori = t.id_kategori
     WHERE $kondisi", $tipe, $param)[0];
$total_masuk  = (float) $tot["masuk"];
$total_keluar = (float) $tot["keluar"];

$transaksi = ambil_semua($koneksi,
    "SELECT t.id_transaksi, t.tanggal, t.nominal, t.keterangan,
            k.nama AS kategori, k.jenis, s.nama AS subkategori, d.nama AS dompet
     FROM transaksi t
     JOIN kategori k ON k.id_kategori = t.id_kategori
     JOIN dompet d   ON d.id_dompet = t.id_dompet
     LEFT JOIN subkategori s ON s.id_subkategori = t.id_subkategori
     WHERE $kondisi
     ORDER BY t.tanggal DESC, t.id_transaksi DESC LIMIT 100", $tipe, $param);

$saldo_total = (float) ambil_semua($koneksi,
    "SELECT COALESCE(SUM(s.saldo), 0) AS total
     FROM dompet d JOIN saldo_dompet s ON s.id_dompet = d.id_dompet
     WHERE d.id_user = ?", "i", [$id_user])[0]["total"];

$pilihan = muat_pilihan($koneksi, $id_user);
$nilai_form["id_dompet"] = count($pilihan["dompet"]) > 0 ? (int) $pilihan["dompet"][0]["id_dompet"] : 0;

halaman_awal("Transaksi", "transaksi");
?>

  <div class="kepala-halaman">
    <div>
      <h1>Transaksi</h1>
      <p>Catat dan telusuri semua uang masuk dan keluar.</p>
    </div>
    <form method="get" class="bar-filter">
      <label for="bulan" class="sr">Bulan</label>
      <input type="month" id="bulan" name="bulan" value="<?= h($bulan) ?>">
      <button type="submit" class="tombol kecil">Tampilkan</button>
      <?php if ($ada_filter): ?><a href="transaksi.php" class="tautan-hijau">Semua data</a><?php endif; ?>
    </form>
  </div>

  <?php tampil_pesan($pesan, $tipe_pesan); ?>

  <section class="ringkasan" aria-label="Ringkasan">
    <div class="saldo <?= $saldo_total < 0 ? 'minus' : '' ?>">
      <span class="label">Total saldo semua dompet</span>
      <strong><?= rupiah($saldo_total) ?></strong>
    </div>
    <div class="sisi masuk">
      <span class="label">Pemasukan<?= $ada_filter ? " (" . h(format_bulan($bulan)) . ")" : "" ?></span>
      <strong><?= rupiah($total_masuk) ?></strong>
    </div>
    <div class="sisi keluar">
      <span class="label">Pengeluaran<?= $ada_filter ? " (" . h(format_bulan($bulan)) . ")" : "" ?></span>
      <strong><?= rupiah($total_keluar) ?></strong>
    </div>
  </section>

  <div class="kolom">

    <section class="panel" id="catat" aria-labelledby="judul-form">
      <h2 id="judul-form">Tambah transaksi</h2>
      <?php form_transaksi($pilihan, $nilai_form, "tambah", "Simpan transaksi"); ?>
    </section>

    <section class="panel" aria-labelledby="judul-daftar">
      <div class="kepala-daftar">
        <h2 id="judul-daftar">Riwayat transaksi</h2>
        <input type="search" id="cari" placeholder="Cari keterangan atau kategori" aria-label="Cari transaksi">
      </div>

      <?php if (count($transaksi) === 0): ?>
        <p class="kosong">Belum ada transaksi<?= $ada_filter ? " di bulan ini" : "" ?>. Catat yang pertama lewat formulir di samping.</p>
      <?php else: ?>
        <div class="gulir">
          <table id="tabel">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Kategori</th>
                <th>Dompet</th>
                <th>Keterangan</th>
                <th class="kanan">Nominal</th>
                <th><span class="sr">Aksi</span></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($transaksi as $t): ?>
                <tr>
                  <td><?= date("d/m/Y", strtotime($t["tanggal"])) ?></td>
                  <td>
                    <?= h($t["kategori"]) ?>
                    <?php if ($t["subkategori"] !== null): ?><span class="sub-kat"><?= h($t["subkategori"]) ?></span><?php endif; ?>
                  </td>
                  <td><?= h($t["dompet"]) ?></td>
                  <td><?= h($t["keterangan"]) ?></td>
                  <td class="kanan nominal <?= h($t["jenis"]) ?>"><?= $t["jenis"] === "pemasukan" ? "+" : "−" ?> <?= rupiah($t["nominal"]) ?></td>
                  <td class="kanan sel-aksi">
                    <a class="aksi-teks" href="edit.php?id=<?= (int) $t["id_transaksi"] ?>">Edit</a>
                    <form method="post" data-konfirmasi="Hapus transaksi ini?">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="hapus">
                      <input type="hidden" name="id_transaksi" value="<?= (int) $t["id_transaksi"] ?>">
                      <button type="submit" class="hapus">Hapus</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

  </div>
<?php halaman_akhir(["script.js"]); ?>
