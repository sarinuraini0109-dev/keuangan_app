<?php
// Halaman pemeriksa sesi (hanya aktif di mode lokal). HAPUS file ini sebelum dipasang di hosting.
require "koneksi.php";
require "layout.php";

if (!$mode_lokal) {
    http_response_code(404);
    exit;
}

$_SESSION["hitung"] = ($_SESSION["hitung"] ?? 0) + 1;
$simpan = session_save_path() !== "" ? session_save_path() : (string) ini_get("session.save_path");
$params = session_get_cookie_params();

$login_sebagai = "Belum login";
$peran_sesi = "-";
$peran_db = "-";
if (isset($_SESSION["id_user"])) {
    $login_sebagai = ($_SESSION["nama"] ?? "?") . " (id " . (int) $_SESSION["id_user"] . ")";
    $peran_sesi = (string) ($_SESSION["role"] ?? "(belum terisi)");
    try {
        $r = ambil_semua($koneksi, "SELECT role, aktif FROM users WHERE id_user = ?", "i", [(int) $_SESSION["id_user"]]);
        $peran_db = count($r) > 0 ? $r[0]["role"] . ", aktif=" . $r[0]["aktif"] : "akun tidak ada di database";
    } catch (mysqli_sql_exception $e) {
        $peran_db = "kolom role belum ada: jalankan database/tambah_admin.sql";
    }
}

$baris = [
    "Login sebagai"                => $login_sebagai,
    "Peran di sesi"                => $peran_sesi,
    "Peran di database"            => $peran_db,
    "Cookie sesi diterima browser" => isset($_COOKIE[session_name()]) ? "Ya" : "Tidak (wajar pada kunjungan pertama)",
    "Nama cookie sesi"             => session_name(),
    "Jumlah kunjungan sesi ini"    => (string) $_SESSION["hitung"],
    "Folder penyimpanan sesi"      => $simpan,
    "Folder bisa ditulis"          => is_writable($simpan) ? "Ya" : "TIDAK",
    "Path cookie"                  => $params["path"],
    "Versi PHP"                    => PHP_VERSION,
];

kepala_html("Cek sesi");
?>
<main class="auth-kartu cek-sesi">
  <h1 class="auth-judul">Cek sesi</h1>
  <p class="auth-sub">Muat ulang halaman ini 3 kali (Ctrl+R).</p>
  <dl class="info-daftar">
    <?php foreach ($baris as $k => $v): ?>
      <dt><?= h($k) ?></dt><dd><?= h($v) ?></dd>
    <?php endforeach; ?>
  </dl>
  <p class="petunjuk">
    Sehat: "Jumlah kunjungan" naik 1 setiap muat ulang dan "Cookie sesi diterima" menjadi Ya.
    Jika selalu 1, browser tidak menyimpan cookie atau folder sesi tidak bisa ditulis.
  </p>
</main>
</body>
</html>
