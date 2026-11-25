<?php
require "koneksi.php";
require "layout.php";
wajib_user();

$id_user = (int) $_SESSION["id_user"];
$id = (int) ($_GET["id"] ?? ($_POST["id_transaksi"] ?? 0));

// Ambil transaksi milik user ini saja
$baris = ambil_semua($koneksi,
    "SELECT id_dompet, id_kategori, id_subkategori, tanggal, nominal, keterangan
     FROM transaksi WHERE id_transaksi = ? AND id_user = ?", "ii", [$id, $id_user]);
if (count($baris) === 0) {
    set_flash("Transaksi tidak ditemukan.", "gagal");
    header("Location: transaksi.php");
    exit;
}
$lama = $baris[0];

$pesan = "";
$nilai = [
    "id_kategori" => (int) $lama["id_kategori"],
    "id_sub"      => (int) $lama["id_subkategori"],
    "id_dompet"   => (int) $lama["id_dompet"],
    "tanggal"     => $lama["tanggal"],
    "nominal"     => number_format((float) $lama["nominal"], 0, ",", "."),
    "keterangan"  => (string) $lama["keterangan"],
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_cek()) {
        $pesan = pesan_csrf();
    } else {
        [$galat, $d] = proses_input_transaksi($koneksi, $id_user, $_POST);
        if ($galat === "") {
            $stmt = $koneksi->prepare(
                "UPDATE transaksi SET id_dompet = ?, id_kategori = ?, id_subkategori = ?,
                        tanggal = ?, nominal = ?, keterangan = ?
                 WHERE id_transaksi = ? AND id_user = ?"
            );
            $stmt->bind_param("iiisdsii", $d["id_dompet"], $d["id_kategori"], $d["sub_simpan"],
                              $d["tanggal"], $d["nominal"], $d["keterangan"], $id, $id_user);
            $stmt->execute();
            set_flash("Transaksi diperbarui.");
            header("Location: transaksi.php");
            exit;
        }
        $pesan = $galat;
        // Pertahankan isian pengguna saat ada galat
        $nilai = [
            "id_kategori" => $d["id_kategori"], "id_sub" => $d["id_sub"], "id_dompet" => $d["id_dompet"],
            "tanggal" => $d["tanggal"], "nominal" => (string) ($_POST["nominal"] ?? ""), "keterangan" => $d["keterangan"],
        ];
    }
}

$pilihan = muat_pilihan($koneksi, $id_user);
halaman_awal("Edit transaksi", "transaksi");
?>

  <div class="kepala-halaman">
    <div>
      <h1>Edit transaksi</h1>
      <p>Ubah data transaksi. Saldo dompet ikut dihitung ulang otomatis.</p>
    </div>
    <a href="transaksi.php" class="tombol garis kecil">Kembali</a>
  </div>

  <?php tampil_pesan($pesan, "gagal"); ?>

  <section class="panel edit-kartu" aria-label="Formulir edit transaksi">
    <?php form_transaksi($pilihan, $nilai, "ubah", "Simpan perubahan", $id); ?>
  </section>
<?php halaman_akhir(["script.js"]); ?>
