<?php
require "koneksi.php";
require "layout.php";
wajib_user();

$id_user = (int) $_SESSION["id_user"];
$pesan = "";

// ---------- Tambah target / setor / hapus ----------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $aksi = $_POST["aksi"] ?? "";

    if (!csrf_cek()) {
        $pesan = pesan_csrf();
    } elseif ($aksi === "tambah") {
        $nama   = mb_substr(trim((string) ($_POST["nama"] ?? "")), 0, 80);
        $digit  = preg_replace('/\D/', '', (string) ($_POST["target"] ?? ""));
        $target = ($digit === "" || strlen($digit) > 13) ? 0.0 : (float) $digit;
        $tenggat_in = (string) ($_POST["tenggat"] ?? "");
        $tenggat = null;
        if ($tenggat_in !== "") {
            $t = DateTime::createFromFormat("Y-m-d", $tenggat_in);
            if ($t && $t->format("Y-m-d") === $tenggat_in) {
                $tenggat = $tenggat_in;
            }
        }

        if ($nama === "") {
            $pesan = "Nama target wajib diisi.";
        } elseif ($target <= 0) {
            $pesan = "Nominal target harus lebih dari 0.";
        } elseif ($tenggat_in !== "" && $tenggat === null) {
            $pesan = "Tanggal tenggat tidak valid.";
        } else {
            $stmt = $koneksi->prepare("INSERT INTO target_tabungan (id_user, nama, target_nominal, tenggat) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isds", $id_user, $nama, $target, $tenggat);
            $stmt->execute();
            set_flash("Target \"" . $nama . "\" dibuat.");
            header("Location: tabungan.php");
            exit;
        }
    } elseif ($aksi === "setor") {
        $id = (int) ($_POST["id_target"] ?? 0);
        $digit = preg_replace('/\D/', '', (string) ($_POST["jumlah"] ?? ""));
        $jumlah = ($digit === "" || strlen($digit) > 13) ? 0.0 : (float) $digit;

        $baris = ambil_semua($koneksi,
            "SELECT terkumpul, target_nominal FROM target_tabungan WHERE id_target = ? AND id_user = ?",
            "ii", [$id, $id_user]);
        if (count($baris) === 0) {
            $pesan = "Target tidak ditemukan.";
        } elseif ($jumlah <= 0) {
            $pesan = "Jumlah setoran harus lebih dari 0.";
        } else {
            $baru = (float) $baris[0]["terkumpul"] + $jumlah;
            $status = $baru >= (float) $baris[0]["target_nominal"] ? "tercapai" : "berjalan";
            $stmt = $koneksi->prepare("UPDATE target_tabungan SET terkumpul = ?, status = ? WHERE id_target = ? AND id_user = ?");
            $stmt->bind_param("dsii", $baru, $status, $id, $id_user);
            $stmt->execute();
            set_flash($status === "tercapai" ? "Selamat, target tercapai!" : "Setoran dicatat.");
            header("Location: tabungan.php");
            exit;
        }
    } elseif ($aksi === "hapus") {
        $id = (int) ($_POST["id_target"] ?? 0);
        $stmt = $koneksi->prepare("DELETE FROM target_tabungan WHERE id_target = ? AND id_user = ?");
        $stmt->bind_param("ii", $id, $id_user);
        $stmt->execute();
        set_flash("Target dihapus.");
        header("Location: tabungan.php");
        exit;
    }
}

$tipe_pesan = "gagal";
$flash = ambil_flash();
if ($flash) {
    $pesan = $flash["pesan"];
    $tipe_pesan = $flash["tipe"];
}

$daftar = ambil_semua($koneksi,
    "SELECT id_target, nama, target_nominal, terkumpul, tenggat, status
     FROM target_tabungan WHERE id_user = ? ORDER BY status, tenggat IS NULL, tenggat, id_target", "i", [$id_user]);

$total_kumpul = 0.0;
foreach ($daftar as $t) {
    $total_kumpul += (float) $t["terkumpul"];
}

halaman_awal("Tabungan", "tabungan");
?>

  <div class="kepala-halaman">
    <div>
      <h1>Target tabungan</h1>
      <p>Total terkumpul <strong class="angka"><?= rupiah($total_kumpul) ?></strong> dari <?= count($daftar) ?> target.</p>
    </div>
  </div>

  <?php tampil_pesan($pesan, $tipe_pesan); ?>

  <div class="kolom">

    <section class="panel" aria-labelledby="judul-target">
      <h2 id="judul-target">Target baru</h2>
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="tambah">
        <label for="nama">Nama target</label>
        <input type="text" id="nama" name="nama" maxlength="80" placeholder="contoh: Beli laptop" required>
        <label for="target">Nominal target (Rp)</label>
        <input type="text" id="target" name="target" class="uang" inputmode="numeric" maxlength="16" placeholder="contoh: 8.000.000" required>
        <label for="tenggat">Tenggat (opsional)</label>
        <input type="date" id="tenggat" name="tenggat">
        <button type="submit" class="tombol">Buat target</button>
      </form>
    </section>

    <section class="panel" aria-labelledby="judul-daftar-tab">
      <h2 id="judul-daftar-tab">Target saya</h2>
      <?php if (count($daftar) === 0): ?>
        <p class="kosong">Belum ada target. Buat yang pertama lewat formulir di samping.</p>
      <?php else: ?>
        <?php foreach ($daftar as $t): ?>
          <?php
            $target = (float) $t["target_nominal"];
            $kumpul = (float) $t["terkumpul"];
            $persen = $target > 0 ? min(100, $kumpul / $target * 100) : 0;
            $sisa_hari = null;
            if ($t["tenggat"] !== null) {
                $sisa_hari = (int) (new DateTime("today"))->diff(new DateTime($t["tenggat"]))->format("%r%a");
            }
          ?>
          <div class="tab-item">
            <div class="angg-atas">
              <strong><?= h($t["nama"]) ?></strong>
              <span>
                <?php if ($t["status"] === "tercapai"): ?>
                  <span class="badge hijau">Tercapai</span>
                <?php elseif ($sisa_hari !== null): ?>
                  <span class="badge <?= $sisa_hari < 0 ? 'merah' : 'biru' ?>"><?= $sisa_hari < 0 ? "Lewat " . abs($sisa_hari) . " hari" : $sisa_hari . " hari lagi" ?></span>
                <?php endif; ?>
                <form method="post" class="form-inline" data-konfirmasi="Hapus target ini?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="hapus">
                  <input type="hidden" name="id_target" value="<?= (int) $t["id_target"] ?>">
                  <button type="submit" class="hapus">Hapus</button>
                </form>
              </span>
            </div>
            <progress class="<?= $t["status"] === "tercapai" ? "aman" : "" ?>" max="100" value="<?= h(round($persen, 1)) ?>"></progress>
            <div class="angg-angka">
              <span><strong class="angka"><?= rupiah($kumpul) ?></strong> dari <?= rupiah($target) ?> (<?= round($persen) ?>%)</span>
              <span><?= $t["tenggat"] !== null ? "Tenggat " . h(date("d/m/Y", strtotime($t["tenggat"]))) : "Tanpa tenggat" ?></span>
            </div>

            <?php if ($t["status"] !== "tercapai"): ?>
              <form method="post" class="tab-setoran">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="setor">
                <input type="hidden" name="id_target" value="<?= (int) $t["id_target"] ?>">
                <label class="sr" for="jumlah-<?= (int) $t["id_target"] ?>">Jumlah setoran</label>
                <input type="text" id="jumlah-<?= (int) $t["id_target"] ?>" name="jumlah" class="uang" inputmode="numeric" maxlength="16" placeholder="Jumlah setoran (Rp)">
                <button type="submit" class="tombol kecil">Setor</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

  </div>
<?php halaman_akhir(); ?>
