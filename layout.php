<?php
/* =====================================================
   KERANGKA HALAMAN BERSAMA
   Semua halaman memakai fungsi di sini supaya tampilan,
   navigasi, font, dan warna selalu konsisten.
   ===================================================== */

function kepala_html($judul, $kelas_body = "")
{
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0e8bd6">
  <title><?= h($judul) ?> | Catatan Keuangan</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="<?= h($kelas_body) ?>">
<?php
}

function ikon_logo($ukuran = 24)
{
    ?>
<svg viewBox="0 0 24 24" width="<?= (int) $ukuran ?>" height="<?= (int) $ukuran ?>" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
  <path d="M3 7a2 2 0 0 1 2-2h12v4"/>
  <rect x="3" y="7" width="18" height="13" rx="2"/>
  <circle cx="16.5" cy="13.5" r="1.2" fill="#fff"/>
</svg>
<?php
}

// ---------- Halaman setelah login (dengan navigasi) ----------
// Admin selalu memakai navbar gelap khusus admin (hanya mengelola akun pengguna).
function halaman_awal($judul, $aktif)
{
    $nama    = $_SESSION["nama"] ?? "Pengguna";
    $inisial = mb_strtoupper(mb_substr($nama, 0, 1));
    $admin   = adalah_admin();

    if ($admin) {
        $menu = [
            "admin"    => ["admin.php",          "Ringkasan"],
            "pengguna" => ["admin_pengguna.php", "Pengguna"],
            "log"      => ["admin_log.php",      "Log aktivitas"],
        ];
        $beranda = "admin.php";
    } else {
        $menu = [
            "dashboard" => ["dashboard.php", "Dashboard"],
            "transaksi" => ["transaksi.php", "Transaksi"],
            "dompet"    => ["dompet.php",    "Dompet"],
            "anggaran"  => ["anggaran.php",  "Anggaran"],
            "tabungan"  => ["tabungan.php",  "Tabungan"],
            "analitik"  => ["analitik.php",  "Analitik"],
        ];
        $beranda = "dashboard.php";
    }
    kepala_html($judul);
    ?>
<header class="nav<?= $admin ? " nav-admin" : "" ?>">
  <div class="wadah nav-isi">
    <a href="<?= h($beranda) ?>" class="nav-merek" aria-label="Catatan Keuangan">
      <span class="logo"><?php ikon_logo(24); ?></span>
      <span class="nama-merek">Catatan Keuangan</span>
      <?php if ($admin): ?><span class="lencana-admin">ADMIN</span><?php endif; ?>
    </a>

    <nav class="nav-menu" id="menu-utama" aria-label="Menu utama">
      <?php foreach ($menu as $kunci => $m): ?>
        <a href="<?= h($m[0]) ?>" class="<?= $aktif === $kunci ? 'aktif' : '' ?>"<?= $aktif === $kunci ? ' aria-current="page"' : '' ?>><?= h($m[1]) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="nav-kanan">
      <?php if (!$admin): ?>
        <a href="transaksi.php#catat" class="tombol-catat"><span aria-hidden="true">+</span><span class="teks">Catat</span></a>
      <?php endif; ?>

      <details class="menu-pengguna">
        <summary aria-label="Menu akun">
          <span class="avatar"><?= h($inisial) ?></span>
          <span class="pengguna-nama"><?= h($nama) ?></span>
          <span class="caret" aria-hidden="true"></span>
        </summary>
        <div class="menu-isi">
          <div class="info"><strong><?= h($nama) ?></strong><span class="kecil"><?= $admin ? "Administrator" : "Akun saya" ?></span></div>
          <a href="profil.php">Profil</a>
          <?php if (!$admin): ?><a href="kategori.php">Kategori</a><?php endif; ?>
          <form method="post" action="logout.php" class="form-keluar">
            <?= csrf_field() ?>
            <button type="submit" class="bahaya">Keluar</button>
          </form>
        </div>
      </details>

      <button type="button" class="nav-toggle" aria-label="Buka menu" aria-expanded="false" aria-controls="menu-utama"><span></span></button>
    </div>
  </div>
</header>

<main class="wadah" id="isi">
<?php
}

function halaman_akhir($skrip = [])
{
    echo "</main>\n";
    echo '<script src="ui.js"></script>' . "\n";
    foreach ($skrip as $s) {
        echo '<script src="' . h($s) . '"></script>' . "\n";
    }
    echo "</body>\n</html>\n";
}

// ---------- Halaman publik / beranda (navigasi Masuk & Daftar) ----------
function halaman_publik_awal($judul)
{
    global $koneksi;
    $masuk = isset($_SESSION["id_user"]);

    // Baca ulang peran dari database agar menu Admin akurat di beranda juga
    if ($masuk) {
        try {
            $r = ambil_semua($koneksi, "SELECT role FROM users WHERE id_user = ?", "i", [(int) $_SESSION["id_user"]]);
            $_SESSION["role"] = count($r) > 0 ? $r[0]["role"] : "user";
        } catch (mysqli_sql_exception $e) {
            error_log($e->getMessage());
        }
    }
    kepala_html($judul, "publik");
    ?>
<header class="nav">
  <div class="wadah nav-isi">
    <a href="index.php" class="nav-merek" aria-label="Catatan Keuangan - beranda">
      <span class="logo"><?php ikon_logo(24); ?></span>
      <span class="nama-merek">Catatan Keuangan</span>
    </a>

    <nav class="nav-menu" id="menu-utama" aria-label="Menu utama">
      <a href="#beranda">Beranda</a>
      <a href="#fitur">Fitur</a>
      <a href="#cara">Cara kerja</a>
      <a href="#keamanan">Keamanan</a>
      <a href="#faq">FAQ</a>
      <?php if ($masuk && adalah_admin()): ?>
        <a href="admin.php" class="menu-admin">Admin</a>
      <?php endif; ?>
    </nav>

    <div class="nav-kanan">
      <?php if ($masuk): ?>
        <a href="<?= adalah_admin() ? 'admin.php' : 'dashboard.php' ?>" class="tombol-catat"><?= adalah_admin() ? "Buka panel admin" : "Buka dashboard" ?></a>
      <?php else: ?>
        <a href="login.php" class="btn-masuk">Masuk</a>
        <a href="register.php" class="tombol-catat">Daftar</a>
      <?php endif; ?>
      <button type="button" class="nav-toggle" aria-label="Buka menu" aria-expanded="false" aria-controls="menu-utama"><span></span></button>
    </div>
  </div>
</header>

<main id="isi">
<?php
}

function halaman_publik_akhir($skrip = [])
{
    echo "</main>\n";
    echo '<script src="ui.js"></script>' . "\n";
    foreach ($skrip as $sk) {
        echo '<script src="' . h($sk) . '"></script>' . "\n";
    }
    echo "</body>\n</html>\n";
}

// ---------- Halaman masuk/daftar (dua kolom: panel merek + kartu form) ----------
function auth_awal($judul, $heading, $sub)
{
    $teks = [
        "Masuk"         => ["Tahu ke mana uangmu pergi.", "Catat pemasukan dan pengeluaran harian, pantau saldo tiap dompet, dan lihat pengeluaran per kategori."],
        "Daftar"        => ["Mulai rapikan uangmu hari ini.", "Buat akun gratis. Satu dompet tunai disiapkan otomatis, kamu tinggal mencatat."],
        "Lupa password" => ["Lupa password? Tenang.", "Masukkan email akunmu, lalu kami buatkan tautan untuk masuk kembali."],
        "Password baru" => ["Buat password baru.", "Pilih password yang belum pernah kamu pakai di tempat lain."],
    ];
    $kiri = $teks[$judul] ?? ["Catatan keuangan yang rapi.", "Pantau uang masuk dan keluar dengan mudah."];

    kepala_html($judul, "auth");
    ?>
<div class="auth-split">

  <aside class="auth-merek">
    <a href="index.php" class="auth-merek-logo">
      <span class="logo"><?php ikon_logo(24); ?></span>
      <span>Catatan Keuangan</span>
    </a>

    <div class="auth-merek-isi">
      <h2><?= h($kiri[0]) ?></h2>
      <p><?= h($kiri[1]) ?></p>
      <ul class="poin">
        <li>Banyak dompet: tunai, rekening, e-wallet</li>
        <li>Kategori dan subkategori sesuai kebutuhanmu</li>
        <li>Anggaran, target tabungan, dan analitik bulanan</li>
      </ul>
    </div>

    <span class="bulat bulat-1" aria-hidden="true"></span>
    <span class="bulat bulat-2" aria-hidden="true"></span>
    <span class="bulat bulat-3" aria-hidden="true"></span>
  </aside>

  <div class="auth-sisi">
    <main class="auth-kartu">
      <div class="auth-logo"><?php ikon_logo(28); ?></div>
      <h1 class="auth-judul"><?= h($heading) ?></h1>
      <p class="auth-sub"><?= h($sub) ?></p>
<?php
}

function auth_akhir()
{
    echo "    </main>\n  </div>\n</div>\n";
    echo '<script src="ui.js"></script>' . "\n</body>\n</html>\n";
}

function tampil_pesan($pesan, $tipe)
{
    if ($pesan !== "") {
        echo '<p class="pesan ' . h($tipe) . '" role="status">' . h($pesan) . "</p>\n";
    }
}

function format_bulan($bulan)   // "2026-11" -> "November 2026"
{
    $nama = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli",
             "Agustus", "September", "Oktober", "November", "Desember"];
    return $nama[(int) substr($bulan, 5, 2) - 1] . " " . substr($bulan, 0, 4);
}

function bulan_valid($b)
{
    return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $b) === 1;
}

// ---------- Fungsi transaksi (dipakai transaksi.php dan edit.php) ----------
function muat_pilihan($koneksi, $id_user)
{
    return [
        "dompet" => ambil_semua($koneksi,
            "SELECT id_dompet, nama FROM dompet WHERE id_user = ? ORDER BY nama", "i", [$id_user]),
        "kategori" => ambil_semua($koneksi,
            "SELECT id_kategori, nama, jenis FROM kategori
             WHERE id_user IS NULL OR id_user = ? ORDER BY jenis, nama", "i", [$id_user]),
        "sub" => ambil_semua($koneksi,
            "SELECT id_subkategori, id_kategori, nama FROM subkategori
             WHERE id_user IS NULL OR id_user = ? ORDER BY nama", "i", [$id_user]),
    ];
}

// Validasi input transaksi. Mengembalikan [pesan_galat, data]. Galat kosong = sah.
function proses_input_transaksi($koneksi, $id_user, $post)
{
    $d = [
        "id_kategori" => (int) ($post["id_kategori"] ?? 0),
        "id_sub"      => (int) ($post["id_subkategori"] ?? 0),
        "id_dompet"   => (int) ($post["id_dompet"] ?? 0),
        "tanggal"     => (string) ($post["tanggal"] ?? ""),
        "keterangan"  => mb_substr(trim((string) ($post["keterangan"] ?? "")), 0, 150),
    ];
    $digit = preg_replace('/\D/', '', (string) ($post["nominal"] ?? ""));
    $d["nominal"]   = ($digit === "" || strlen($digit) > 13) ? 0.0 : (float) $digit;
    $d["sub_simpan"] = null;

    $tgl = DateTime::createFromFormat("Y-m-d", $d["tanggal"]);
    if (!$tgl || $tgl->format("Y-m-d") !== $d["tanggal"]) return ["Tanggal tidak valid.", $d];
    if ($d["nominal"] <= 0)                                return ["Nominal harus lebih dari 0.", $d];

    // Dompet, kategori, subkategori harus milik/boleh dipakai user ini
    $ok = ambil_semua($koneksi, "SELECT 1 FROM dompet WHERE id_dompet = ? AND id_user = ?",
                      "ii", [$d["id_dompet"], $id_user]);
    if (count($ok) === 0) return ["Dompet tidak valid.", $d];

    $ok = ambil_semua($koneksi,
        "SELECT 1 FROM kategori WHERE id_kategori = ? AND (id_user IS NULL OR id_user = ?)",
        "ii", [$d["id_kategori"], $id_user]);
    if (count($ok) === 0) return ["Kategori tidak valid.", $d];

    if ($d["id_sub"] > 0) {
        $ok = ambil_semua($koneksi,
            "SELECT 1 FROM subkategori WHERE id_subkategori = ? AND id_kategori = ?
             AND (id_user IS NULL OR id_user = ?)", "iii", [$d["id_sub"], $d["id_kategori"], $id_user]);
        if (count($ok) === 0) return ["Subkategori tidak sesuai dengan kategori.", $d];
        $d["sub_simpan"] = $d["id_sub"];
    }
    return ["", $d];
}

// Formulir transaksi. $n = nilai awal, $aksi = "tambah" | "ubah"
function form_transaksi($p, $n, $aksi, $teks_tombol, $id_transaksi = null)
{
    $jenis_awal = "pengeluaran";
    foreach ($p["kategori"] as $k) {
        if ((int) $k["id_kategori"] === (int) $n["id_kategori"]) {
            $jenis_awal = $k["jenis"];
        }
    }
    ?>
<form method="post" id="form-transaksi" data-kategori="<?= (int) $n["id_kategori"] ?>" data-sub="<?= (int) $n["id_sub"] ?>" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="aksi" value="<?= h($aksi) ?>">
  <?php if ($id_transaksi !== null): ?>
    <input type="hidden" name="id_transaksi" value="<?= (int) $id_transaksi ?>">
  <?php endif; ?>

  <fieldset class="jenis">
    <legend>Jenis</legend>
    <label class="pilihan"><input type="radio" name="jenis" value="pengeluaran" <?= $jenis_awal === "pengeluaran" ? "checked" : "" ?>> Pengeluaran</label>
    <label class="pilihan"><input type="radio" name="jenis" value="pemasukan" <?= $jenis_awal === "pemasukan" ? "checked" : "" ?>> Pemasukan</label>
  </fieldset>

  <label for="id_kategori">Kategori</label>
  <select name="id_kategori" id="id_kategori" required>
    <?php foreach ($p["kategori"] as $k): ?>
      <option value="<?= (int) $k["id_kategori"] ?>" data-jenis="<?= h($k["jenis"]) ?>"><?= h($k["nama"]) ?></option>
    <?php endforeach; ?>
  </select>

  <label for="id_subkategori">Subkategori</label>
  <select name="id_subkategori" id="id_subkategori">
    <option value="">Tanpa subkategori</option>
    <?php foreach ($p["sub"] as $s): ?>
      <option value="<?= (int) $s["id_subkategori"] ?>" data-kategori="<?= (int) $s["id_kategori"] ?>"><?= h($s["nama"]) ?></option>
    <?php endforeach; ?>
  </select>

  <label for="id_dompet">Dompet</label>
  <select name="id_dompet" id="id_dompet" required>
    <?php foreach ($p["dompet"] as $d): ?>
      <option value="<?= (int) $d["id_dompet"] ?>" <?= (int) $d["id_dompet"] === (int) $n["id_dompet"] ? "selected" : "" ?>><?= h($d["nama"]) ?></option>
    <?php endforeach; ?>
  </select>

  <label for="tanggal">Tanggal</label>
  <input type="date" name="tanggal" id="tanggal" value="<?= h($n["tanggal"]) ?>" required>

  <label for="nominal">Nominal (Rp)</label>
  <input type="text" name="nominal" id="nominal" class="uang" inputmode="numeric" maxlength="16" placeholder="contoh: 25.000" value="<?= h($n["nominal"]) ?>" required>

  <label for="keterangan">Keterangan</label>
  <input type="text" name="keterangan" id="keterangan" maxlength="150" placeholder="contoh: makan siang" value="<?= h($n["keterangan"]) ?>">

  <p class="galat" id="galat" role="alert" hidden></p>
  <button type="submit" class="tombol"><?= h($teks_tombol) ?></button>
</form>
<?php
}
