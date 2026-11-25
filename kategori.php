<?php
require "koneksi.php";
require "layout.php";
wajib_user();

$id_user = (int) $_SESSION["id_user"];
$pesan = "";

// ---------- Tambah / hapus kategori dan subkategori ----------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $aksi = $_POST["aksi"] ?? "";

    if (!csrf_cek()) {
        $pesan = pesan_csrf();
    } else {
        try {
            if ($aksi === "tambah_kategori") {
                $nama  = mb_substr(trim((string) ($_POST["nama"] ?? "")), 0, 50);
                $jenis = (string) ($_POST["jenis"] ?? "");
                if ($nama === "") {
                    $pesan = "Nama kategori wajib diisi.";
                } elseif (!in_array($jenis, ["pemasukan", "pengeluaran"], true)) {
                    $pesan = "Jenis kategori tidak valid.";
                } elseif (count(ambil_semua($koneksi,
                    "SELECT 1 FROM kategori WHERE nama = ? AND jenis = ? AND (id_user IS NULL OR id_user = ?)",
                    "ssi", [$nama, $jenis, $id_user])) > 0) {
                    $pesan = "Kategori itu sudah ada.";
                } else {
                    $stmt = $koneksi->prepare("INSERT INTO kategori (id_user, nama, jenis) VALUES (?, ?, ?)");
                    $stmt->bind_param("iss", $id_user, $nama, $jenis);
                    $stmt->execute();
                    set_flash("Kategori \"" . $nama . "\" ditambahkan.");
                }
            } elseif ($aksi === "tambah_sub") {
                $id_kat = (int) ($_POST["id_kategori"] ?? 0);
                $nama   = mb_substr(trim((string) ($_POST["nama"] ?? "")), 0, 50);
                $kat_ok = ambil_semua($koneksi,
                    "SELECT 1 FROM kategori WHERE id_kategori = ? AND (id_user IS NULL OR id_user = ?)",
                    "ii", [$id_kat, $id_user]);
                if ($nama === "") {
                    $pesan = "Nama subkategori wajib diisi.";
                } elseif (count($kat_ok) === 0) {
                    $pesan = "Kategori tidak valid.";
                } elseif (count(ambil_semua($koneksi,
                    "SELECT 1 FROM subkategori WHERE id_kategori = ? AND nama = ? AND (id_user IS NULL OR id_user = ?)",
                    "isi", [$id_kat, $nama, $id_user])) > 0) {
                    $pesan = "Subkategori itu sudah ada di kategori tersebut.";
                } else {
                    $stmt = $koneksi->prepare("INSERT INTO subkategori (id_kategori, id_user, nama) VALUES (?, ?, ?)");
                    $stmt->bind_param("iis", $id_kat, $id_user, $nama);
                    $stmt->execute();
                    set_flash("Subkategori \"" . $nama . "\" ditambahkan.");
                }
            } elseif ($aksi === "hapus_kategori") {
                $id = (int) ($_POST["id"] ?? 0);
                $stmt = $koneksi->prepare("DELETE FROM kategori WHERE id_kategori = ? AND id_user = ?");
                $stmt->bind_param("ii", $id, $id_user);
                $stmt->execute();
                set_flash("Kategori dihapus.");
            } elseif ($aksi === "hapus_sub") {
                $id = (int) ($_POST["id"] ?? 0);
                $stmt = $koneksi->prepare("DELETE FROM subkategori WHERE id_subkategori = ? AND id_user = ?");
                $stmt->bind_param("ii", $id, $id_user);
                $stmt->execute();
                set_flash("Subkategori dihapus.");
            }
        } catch (mysqli_sql_exception $e) {
            // Data yang masih dipakai transaksi ditolak oleh foreign key
            $pesan = "Tidak bisa dihapus karena masih dipakai oleh transaksi.";
        }

        if ($pesan === "") {
            header("Location: kategori.php");
            exit;
        }
    }
}

$tipe_pesan = "gagal";
$flash = ambil_flash();
if ($flash) {
    $pesan = $flash["pesan"];
    $tipe_pesan = $flash["tipe"];
}

$kategori = ambil_semua($koneksi,
    "SELECT id_kategori, id_user, nama, jenis FROM kategori
     WHERE id_user IS NULL OR id_user = ? ORDER BY jenis, nama", "i", [$id_user]);
$semua_sub = ambil_semua($koneksi,
    "SELECT id_subkategori, id_kategori, id_user, nama FROM subkategori
     WHERE id_user IS NULL OR id_user = ? ORDER BY nama", "i", [$id_user]);

// Kelompokkan subkategori per kategori (looping)
$sub_per_kat = [];
foreach ($semua_sub as $s) {
    $sub_per_kat[(int) $s["id_kategori"]][] = $s;
}

halaman_awal("Kategori", "kategori");
?>

  <div class="kepala-halaman">
    <div>
      <h1>Kategori</h1>
      <p>Atur kategori dan subkategori. Yang bertanda "Bawaan" tidak bisa dihapus.</p>
    </div>
  </div>

  <?php tampil_pesan($pesan, $tipe_pesan); ?>

  <div class="grid-2 form-kategori">
    <section class="panel" aria-labelledby="judul-kat">
      <h2 id="judul-kat">Tambah kategori</h2>
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="tambah_kategori">
        <label for="nama-kat">Nama kategori</label>
        <input type="text" id="nama-kat" name="nama" maxlength="50" placeholder="contoh: Hobi" required>
        <label for="jenis-kat">Jenis</label>
        <select id="jenis-kat" name="jenis">
          <option value="pengeluaran">Pengeluaran</option>
          <option value="pemasukan">Pemasukan</option>
        </select>
        <button type="submit" class="tombol">Simpan kategori</button>
      </form>
    </section>

    <section class="panel" aria-labelledby="judul-sub">
      <h2 id="judul-sub">Tambah subkategori</h2>
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="tambah_sub">
        <label for="induk">Masuk ke kategori</label>
        <select id="induk" name="id_kategori">
          <?php foreach (["pemasukan" => "Pemasukan", "pengeluaran" => "Pengeluaran"] as $j => $label): ?>
            <optgroup label="<?= h($label) ?>">
              <?php foreach ($kategori as $k): ?>
                <?php if ($k["jenis"] === $j): ?>
                  <option value="<?= (int) $k["id_kategori"] ?>"><?= h($k["nama"]) ?></option>
                <?php endif; ?>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
        <label for="nama-sub">Nama subkategori</label>
        <input type="text" id="nama-sub" name="nama" maxlength="50" placeholder="contoh: Skincare" required>
        <button type="submit" class="tombol">Simpan subkategori</button>
      </form>
    </section>
  </div>

  <div class="grid-2 daftar-kategori">
    <?php foreach (["pemasukan" => "Pemasukan", "pengeluaran" => "Pengeluaran"] as $j => $label): ?>
      <section class="kartu kat-blok" aria-label="Kategori <?= h($label) ?>">
        <h2><span class="badge <?= $j === 'pemasukan' ? 'hijau' : 'merah' ?>"><?= h($label) ?></span></h2>
        <?php foreach ($kategori as $k): ?>
          <?php if ($k["jenis"] !== $j) continue; ?>
          <div class="kat-item">
            <div class="kat-kepala">
              <strong><?= h($k["nama"]) ?></strong>
              <?php if ($k["id_user"] === null): ?>
                <span class="badge abu">Bawaan</span>
              <?php else: ?>
                <form method="post" data-konfirmasi="Hapus kategori ini beserta subkategorinya?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="hapus_kategori">
                  <input type="hidden" name="id" value="<?= (int) $k["id_kategori"] ?>">
                  <button type="submit" class="hapus">Hapus</button>
                </form>
              <?php endif; ?>
            </div>
            <?php if (!empty($sub_per_kat[(int) $k["id_kategori"]])): ?>
              <div class="chips">
                <?php foreach ($sub_per_kat[(int) $k["id_kategori"]] as $s): ?>
                  <?php if ($s["id_user"] === null): ?>
                    <span class="chip bawaan"><?= h($s["nama"]) ?></span>
                  <?php else: ?>
                    <span class="chip"><?= h($s["nama"]) ?>
                      <form method="post" data-konfirmasi="Hapus subkategori ini?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="aksi" value="hapus_sub">
                        <input type="hidden" name="id" value="<?= (int) $s["id_subkategori"] ?>">
                        <button type="submit" class="hapus" aria-label="Hapus <?= h($s["nama"]) ?>">×</button>
                      </form>
                    </span>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>
  </div>
<?php halaman_akhir(); ?>
