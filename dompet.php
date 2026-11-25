<?php
require "koneksi.php";
require "layout.php";
wajib_user();

$id_user = (int) $_SESSION["id_user"];
$jenis_sah = ["tunai", "bank", "e-wallet"];
$pesan = "";

// ---------- Tambah / ubah / hapus dompet ----------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $aksi = $_POST["aksi"] ?? "";

    if (!csrf_cek()) {
        $pesan = pesan_csrf();
    } elseif ($aksi === "tambah" || $aksi === "ubah") {
        $nama  = mb_substr(trim((string) ($_POST["nama"] ?? "")), 0, 50);
        $jenis = (string) ($_POST["jenis"] ?? "");
        $digit = preg_replace('/\D/', '', (string) ($_POST["saldo_awal"] ?? ""));

        if ($nama === "") {
            $pesan = "Nama dompet wajib diisi.";
        } elseif (!in_array($jenis, $jenis_sah, true)) {
            $pesan = "Jenis dompet tidak valid.";
        } elseif (strlen($digit) > 13) {
            $pesan = "Saldo awal terlalu besar.";
        } else {
            $saldo_awal = $digit === "" ? 0.0 : (float) $digit;
            try {
                if ($aksi === "tambah") {
                    $stmt = $koneksi->prepare("INSERT INTO dompet (id_user, nama, jenis, saldo_awal) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("issd", $id_user, $nama, $jenis, $saldo_awal);
                    $stmt->execute();
                    set_flash("Dompet \"" . $nama . "\" ditambahkan.");
                } else {
                    $id = (int) ($_POST["id_dompet"] ?? 0);
                    $stmt = $koneksi->prepare("UPDATE dompet SET nama = ?, jenis = ?, saldo_awal = ? WHERE id_dompet = ? AND id_user = ?");
                    $stmt->bind_param("ssdii", $nama, $jenis, $saldo_awal, $id, $id_user);
                    $stmt->execute();
                    set_flash("Dompet diperbarui.");
                }
                header("Location: dompet.php");
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
    } elseif ($aksi === "hapus") {
        $id = (int) ($_POST["id_dompet"] ?? 0);
        $jumlah = (int) ambil_semua($koneksi, "SELECT COUNT(*) AS n FROM dompet WHERE id_user = ?", "i", [$id_user])[0]["n"];
        if ($jumlah <= 1) {
            $pesan = "Minimal harus ada satu dompet.";
        } else {
            try {
                $stmt = $koneksi->prepare("DELETE FROM dompet WHERE id_dompet = ? AND id_user = ?");
                $stmt->bind_param("ii", $id, $id_user);
                $stmt->execute();
                set_flash("Dompet dihapus.");
                header("Location: dompet.php");
                exit;
            } catch (mysqli_sql_exception $e) {
                // Dompet yang masih punya transaksi ditolak oleh foreign key
                $pesan = "Dompet ini masih memiliki transaksi. Hapus atau pindahkan transaksinya dulu.";
            }
        }
    }
}

$tipe_pesan = "gagal";
$flash = ambil_flash();
if ($flash) {
    $pesan = $flash["pesan"];
    $tipe_pesan = $flash["tipe"];
}

$daftar = ambil_semua($koneksi,
    "SELECT d.id_dompet, d.nama, d.jenis, d.saldo_awal, s.saldo,
            (SELECT COUNT(*) FROM transaksi t WHERE t.id_dompet = d.id_dompet) AS jml_transaksi
     FROM dompet d JOIN saldo_dompet s ON s.id_dompet = d.id_dompet
     WHERE d.id_user = ? ORDER BY d.id_dompet", "i", [$id_user]);

$total = 0.0;
foreach ($daftar as $d) {
    $total += (float) $d["saldo"];
}

halaman_awal("Dompet", "dompet");
?>

  <div class="kepala-halaman">
    <div>
      <h1>Dompet</h1>
      <p>Tempat uangmu disimpan. Total saat ini <strong class="angka"><?= rupiah($total) ?></strong>.</p>
    </div>
  </div>

  <?php tampil_pesan($pesan, $tipe_pesan); ?>

  <div class="kolom">

    <section class="panel" aria-labelledby="judul-tambah">
      <h2 id="judul-tambah">Tambah dompet</h2>
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="tambah">
        <label for="nama">Nama dompet</label>
        <input type="text" id="nama" name="nama" maxlength="50" placeholder="contoh: Rekening BRI" required>
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
    </section>

    <div class="grid-2 dompet-grid">
      <?php foreach ($daftar as $d): ?>
        <?php $kelas = str_replace("-", "", $d["jenis"]); ?>
        <article class="kartu naik kartu-dompet garis-<?= h($kelas) ?>">
          <span class="badge biru"><?= h($d["jenis"]) ?></span>
          <h2 class="dompet-nama"><?= h($d["nama"]) ?></h2>
          <strong class="dompet-saldo angka"><?= rupiah($d["saldo"]) ?></strong>
          <span class="kecil">Saldo awal <?= rupiah($d["saldo_awal"]) ?> · <?= (int) $d["jml_transaksi"] ?> transaksi</span>

          <details class="ubah-dompet">
            <summary class="aksi-teks">Ubah</summary>
            <form method="post" novalidate>
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="ubah">
              <input type="hidden" name="id_dompet" value="<?= (int) $d["id_dompet"] ?>">
              <label for="nama-<?= (int) $d["id_dompet"] ?>">Nama</label>
              <input type="text" id="nama-<?= (int) $d["id_dompet"] ?>" name="nama" maxlength="50" value="<?= h($d["nama"]) ?>" required>
              <label for="jenis-<?= (int) $d["id_dompet"] ?>">Jenis</label>
              <select id="jenis-<?= (int) $d["id_dompet"] ?>" name="jenis">
                <?php foreach ($jenis_sah as $j): ?>
                  <option value="<?= h($j) ?>" <?= $j === $d["jenis"] ? "selected" : "" ?>><?= h(ucfirst($j)) ?></option>
                <?php endforeach; ?>
              </select>
              <label for="awal-<?= (int) $d["id_dompet"] ?>">Saldo awal (Rp)</label>
              <input type="text" id="awal-<?= (int) $d["id_dompet"] ?>" name="saldo_awal" class="uang" inputmode="numeric" maxlength="16" value="<?= h(number_format((float) $d["saldo_awal"], 0, ",", ".")) ?>">
              <button type="submit" class="tombol kecil simpan-ubah">Simpan</button>
            </form>
          </details>

          <form method="post" class="hapus-dompet" data-konfirmasi="Hapus dompet ini?">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="id_dompet" value="<?= (int) $d["id_dompet"] ?>">
            <button type="submit" class="hapus">Hapus dompet</button>
          </form>
        </article>
      <?php endforeach; ?>
    </div>

  </div>
<?php halaman_akhir(); ?>
