<?php
/* =====================================================
   KONEKSI DATABASE + KEAMANAN DASAR
   Semua halaman memanggil file ini paling awal.
   ===================================================== */

// ---------- Pengaturan ----------
$host = "localhost";
$user = "root";
$pass = "";
$db   = "keuangan_app";

// true  = tautan reset password tampil di layar (hanya untuk localhost)
// false = tautan dikirim lewat email (WAJIB false saat di hosting)
$mode_lokal = true;

const BATAS_IDLE = 3600;   // otomatis keluar setelah 60 menit tidak aktif

$https = !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";
// Cookie berlaku untuk seluruh situs ("/"). Jalur folder tidak dipakai karena folder yang
// memuat spasi atau tanda kurung (misal "files (2)") membuat browser menolak mengirim cookie.
$jalur_app = "/";

// ---------- Header keamanan ----------
header("X-Frame-Options: DENY");                       // cegah clickjacking
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: same-origin");
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
     . "style-src 'self'; script-src 'self'; form-action 'self'; "
     . "frame-ancestors 'none'; base-uri 'self'");    // cegah XSS
header("Cache-Control: no-store");
if ($https) {
    header("Strict-Transport-Security: max-age=31536000");
}

// ---------- Sesi yang diperkuat ----------
ini_set("session.use_strict_mode", "1");
ini_set("session.use_only_cookies", "1");
session_name("KEUANGAN_SID");   // nama baru agar tidak bentrok dengan cookie lama
session_set_cookie_params([
    "lifetime" => 0,
    "path"     => $jalur_app,
    "secure"   => $https,
    "httponly" => true,       // tidak bisa dibaca JavaScript
    "samesite" => "Lax",      // ikut menahan CSRF
]);
session_start();

// Keluar otomatis jika terlalu lama tidak aktif
if (isset($_SESSION["id_user"])) {
    if (time() - ($_SESSION["aktif_terakhir"] ?? 0) > BATAS_IDLE) {
        $_SESSION = [];
        session_destroy();
        session_start();
    } else {
        $_SESSION["aktif_terakhir"] = time();
    }
}

// ---------- Koneksi database ----------
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $koneksi = new mysqli($host, $user, $pass, $db);
    $koneksi->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    error_log("Koneksi DB gagal: " . $e->getMessage());   // detail hanya di log server
    http_response_code(500);
    die("Layanan sedang bermasalah. Pastikan MySQL aktif dan database sudah diimpor.");
}

/* =====================================================
   FUNGSI BANTU
   ===================================================== */

// Escape teks sebelum masuk HTML (anti XSS)
function h($teks)
{
    return htmlspecialchars((string) $teks, ENT_QUOTES, "UTF-8");
}

function rupiah($angka)
{
    return "Rp " . number_format((float) $angka, 0, ",", ".");
}

// ---------- CSRF ----------
function csrf_token()
{
    if (empty($_SESSION["csrf"])) {
        $_SESSION["csrf"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf"];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_cek()
{
    return isset($_POST["csrf"], $_SESSION["csrf"])
        && hash_equals($_SESSION["csrf"], (string) $_POST["csrf"]);
}

// ---------- Jalankan SELECT dengan prepared statement ----------
function ambil_semua($koneksi, $sql, $tipe = "", $param = [])
{
    $stmt = $koneksi->prepare($sql);
    if ($tipe !== "") {
        $stmt->bind_param($tipe, ...$param);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Pesan galat CSRF yang menjelaskan penyebabnya
function pesan_csrf()
{
    if (empty($_COOKIE[session_name()])) {
        return "Browser tidak mengirim cookie sesi. Pastikan cookie untuk localhost tidak diblokir, "
             . "hapus cookie lama, lalu muat ulang halaman (Ctrl+F5).";
    }
    return "Sesi formulir habis. Muat ulang halaman lalu coba lagi.";
}

// ---------- Pesan sekali tampil ----------
function set_flash($pesan, $tipe = "sukses")
{
    $_SESSION["flash"] = ["pesan" => $pesan, "tipe" => $tipe];
}

function ambil_flash()
{
    $f = $_SESSION["flash"] ?? null;
    unset($_SESSION["flash"]);
    return $f;
}

// ---------- Info permintaan ----------
function ip_klien()
{
    return substr($_SERVER["REMOTE_ADDR"] ?? "0.0.0.0", 0, 45);   // tidak percaya header buatan klien
}

function url_dasar()
{
    global $https;
    return ($https ? "https" : "http") . "://" . $_SERVER["HTTP_HOST"]
         . rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"])), "/");
}

// ---------- Aturan password ----------
function validasi_password($p)
{
    $galat = [];
    if (strlen($p) < 8)                      $galat[] = "Password minimal 8 karakter.";
    if (strlen($p) > 72)                     $galat[] = "Password maksimal 72 karakter.";
    if (!preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p)) {
        $galat[] = "Password harus mengandung huruf dan angka.";
    }
    return $galat;
}

// ---------- Pembatas percobaan (anti brute force) ----------
function kunci_percobaan($teks)
{
    return hash("sha256", mb_strtolower(trim((string) $teks)));
}

function terlalu_banyak($koneksi, $jenis, $kunci, $maks_kunci, $maks_ip, $menit)
{
    $ip = ip_klien();
    $stmt = $koneksi->prepare(
        "SELECT SUM(kunci = ?) AS per_kunci, SUM(ip = ?) AS per_ip
         FROM login_attempt
         WHERE jenis = ? AND waktu > DATE_SUB(NOW(), INTERVAL ? MINUTE)"
    );
    $stmt->bind_param("sssi", $kunci, $ip, $jenis, $menit);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    return ((int) $r["per_kunci"] >= $maks_kunci) || ((int) $r["per_ip"] >= $maks_ip);
}

function catat_percobaan($koneksi, $jenis, $kunci)
{
    $ip = ip_klien();
    $stmt = $koneksi->prepare("INSERT INTO login_attempt (jenis, kunci, ip) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $jenis, $kunci, $ip);
    $stmt->execute();

    if (random_int(1, 100) === 1) {    // sesekali bersihkan data lama
        $koneksi->query("DELETE FROM login_attempt WHERE waktu < DATE_SUB(NOW(), INTERVAL 1 DAY)");
    }
}

function hapus_percobaan($koneksi, $jenis, $kunci)
{
    $stmt = $koneksi->prepare("DELETE FROM login_attempt WHERE jenis = ? AND kunci = ?");
    $stmt->bind_param("ss", $jenis, $kunci);
    $stmt->execute();
}

// ---------- Login, sesi, dan "ingat saya" ----------
function mulai_sesi_login($koneksi, $id_user, $nama)
{
    session_regenerate_id(true);            // cegah session fixation
    $_SESSION["id_user"]        = (int) $id_user;
    $_SESSION["nama"]           = $nama;
    $_SESSION["aktif_terakhir"] = time();
    unset($_SESSION["csrf"]);               // token CSRF baru

    $_SESSION["role"] = "user";
    try {
        $r = ambil_semua($koneksi, "SELECT role FROM users WHERE id_user = ?", "i", [(int) $id_user]);
        if (count($r) > 0) {
            $_SESSION["role"] = $r[0]["role"];
        }
    } catch (mysqli_sql_exception $e) {
        // database belum dimigrasi: tetap user biasa
    }

    $stmt = $koneksi->prepare("UPDATE users SET terakhir_login = NOW() WHERE id_user = ?");
    $stmt->bind_param("i", $id_user);
    $stmt->execute();
}

function atur_cookie_ingat($nilai, $kedaluwarsa)
{
    global $https, $jalur_app;
    setcookie("keuangan_ingat", $nilai, [
        "expires"  => $kedaluwarsa,
        "path"     => $jalur_app,
        "secure"   => $https,
        "httponly" => true,
        "samesite" => "Lax",
    ]);
}

function buat_token_ingat($koneksi, $id_user)
{
    $selector  = bin2hex(random_bytes(12));      // 24 karakter
    $validator = bin2hex(random_bytes(32));      // 64 karakter
    $hash      = hash("sha256", $validator);     // yang disimpan hanya hash

    $stmt = $koneksi->prepare(
        "INSERT INTO remember_token (id_user, selector, validator_hash, kedaluwarsa)
         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))"
    );
    $stmt->bind_param("iss", $id_user, $selector, $hash);
    $stmt->execute();

    atur_cookie_ingat($selector . ":" . $validator, time() + 30 * 86400);
}

function hapus_semua_token_ingat($koneksi, $id_user)
{
    try {
        $stmt = $koneksi->prepare("DELETE FROM remember_token WHERE id_user = ?");
        $stmt->bind_param("i", $id_user);
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        error_log($e->getMessage());
    }
}

function keluar_akun($koneksi)
{
    if (isset($_SESSION["id_user"])) {
        hapus_semua_token_ingat($koneksi, (int) $_SESSION["id_user"]);
    }
    atur_cookie_ingat("", time() - 3600);
    $_SESSION = [];
    session_destroy();
}

// Halaman yang wajib login
function wajib_login()
{
    global $koneksi;
    if (!isset($_SESSION["id_user"])) {
        header("Location: login.php");
        exit;
    }

    // Peran dan status dibaca ulang dari database di setiap permintaan,
    // sehingga akun yang dinonaktifkan admin langsung tidak bisa dipakai.
    $role = "user";
    $aktif = 1;
    try {
        $baris = ambil_semua($koneksi, "SELECT role, aktif FROM users WHERE id_user = ?",
                             "i", [(int) $_SESSION["id_user"]]);
        if (count($baris) === 0) {
            $aktif = 0;                       // akun sudah dihapus
        } else {
            $role  = $baris[0]["role"];
            $aktif = (int) $baris[0]["aktif"];
        }
    } catch (mysqli_sql_exception $e) {
        // Kolom role/aktif belum ada (database belum dimigrasi): anggap user biasa
    }

    if ($aktif !== 1) {
        keluar_akun($koneksi);
        header("Location: login.php?status=nonaktif");
        exit;
    }
    $_SESSION["role"] = $role;
}

// Halaman keuangan pribadi: khusus pengguna biasa. Admin dialihkan ke panel admin.
function wajib_user()
{
    wajib_login();
    if (adalah_admin()) {
        header("Location: admin.php");
        exit;
    }
}

function adalah_admin()
{
    return ($_SESSION["role"] ?? "user") === "admin";
}

// Halaman khusus admin. Dipanggil setelah koneksi.php dan layout.php dimuat.
function wajib_admin()
{
    wajib_login();
    if (!adalah_admin()) {
        http_response_code(403);
        auth_awal("Akses ditolak", "403", "Halaman ini khusus administrator.");
        echo '<a href="dashboard.php" class="tombol tombol-tautan">Kembali ke dashboard</a>';
        auth_akhir();
        exit;
    }
}

// Catat tindakan admin ke tabel log_aktivitas
function catat_log($koneksi, $id_admin, $aksi, $target = "")
{
    try {
        $ip = ip_klien();
        $stmt = $koneksi->prepare("INSERT INTO log_aktivitas (id_admin, aksi, target, ip) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $id_admin, $aksi, $target, $ip);
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        error_log($e->getMessage());
    }
}

// Halaman tamu (login/daftar): jika sudah masuk, arahkan ke index
function hanya_tamu()
{
    if (isset($_SESSION["id_user"])) {
        header("Location: " . (adalah_admin() ? "admin.php" : "dashboard.php"));
        exit;
    }
}

// ---------- Masuk otomatis lewat cookie "ingat saya" ----------
if (!isset($_SESSION["id_user"]) && !empty($_COOKIE["keuangan_ingat"]) && is_string($_COOKIE["keuangan_ingat"])) {
    try {
        if (preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $_COOKIE["keuangan_ingat"], $m)) {
            $selector = $m[1];
            $stmt = $koneksi->prepare(
                "SELECT t.id_token, t.id_user, t.validator_hash, u.nama_lengkap
                 FROM remember_token t JOIN users u ON u.id_user = t.id_user
                 WHERE t.selector = ? AND t.kedaluwarsa > NOW() AND u.aktif = 1 LIMIT 1"
            );
            $stmt->bind_param("s", $selector);
            $stmt->execute();
            $baris = $stmt->get_result()->fetch_assoc();

            if ($baris && hash_equals($baris["validator_hash"], hash("sha256", $m[2]))) {
                $stmt = $koneksi->prepare("DELETE FROM remember_token WHERE id_token = ?");
                $stmt->bind_param("i", $baris["id_token"]);
                $stmt->execute();

                mulai_sesi_login($koneksi, $baris["id_user"], $baris["nama_lengkap"]);
                buat_token_ingat($koneksi, (int) $baris["id_user"]);   // token diganti tiap dipakai
            } else {
                atur_cookie_ingat("", time() - 3600);                  // cookie palsu/usang
            }
        } else {
            atur_cookie_ingat("", time() - 3600);
        }
    } catch (mysqli_sql_exception $e) {
        error_log($e->getMessage());
    }
}
