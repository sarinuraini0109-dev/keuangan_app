<?php
// Halaman muka (beranda) yang bisa dibuka siapa saja.

// ---------- Pemeriksaan awal: semua file harus ada di folder yang sama ----------
$wajib = [
    "koneksi.php", "layout.php", "login.php", "register.php", "lupa.php", "reset.php", "logout.php",
    "dashboard.php", "transaksi.php", "edit.php", "dompet.php", "kategori.php", "anggaran.php",
    "tabungan.php", "analitik.php", "profil.php",
    "admin.php", "admin_pengguna.php", "admin_log.php",
    "style.css", "ui.js", "script.js", "grafik.js", "landing.js",
    "fonts/Inter-Regular.woff", "fonts/Inter-Medium.woff", "fonts/Inter-SemiBold.woff",
    "fonts/Inter-Bold.woff", "fonts/Inter-ExtraBold.woff",
];
$hilang = [];
foreach ($wajib as $berkas) {
    if (!is_file(__DIR__ . "/" . $berkas)) {
        $hilang[] = $berkas;
    }
}
if (count($hilang) > 0) {
    $ada = array_merge(scandir(__DIR__), is_dir(__DIR__ . "/fonts") ? array_map(fn($f) => "fonts/" . $f, scandir(__DIR__ . "/fonts")) : []);
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>File belum lengkap</title></head>'
       . '<body style="font-family:Segoe UI,Arial,sans-serif;max-width:680px;margin:3rem auto;padding:0 1rem;line-height:1.6;color:#12304a">'
       . '<h1>File website belum lengkap</h1>'
       . '<p>Folder <code>' . htmlspecialchars(__DIR__) . '</code> belum berisi semua file. Yang hilang:</p><ul>';
    foreach ($hilang as $berkas) {
        echo '<li><strong>' . htmlspecialchars($berkas) . '</strong>';
        $dasar = pathinfo($berkas, PATHINFO_FILENAME);
        foreach ($ada as $kandidat) {
            if ($kandidat !== $berkas && stripos(basename($kandidat), $dasar) === 0) {
                echo ' &mdash; ada file bernama <code>' . htmlspecialchars($kandidat) . '</code>, ubah namanya menjadi <code>' . htmlspecialchars($berkas) . '</code>';
                break;
            }
        }
        echo '</li>';
    }
    echo '</ul><p><strong>Cara termudah:</strong> hapus isi folder itu, lalu ekstrak <code>keuangan.zip</code> '
       . 'seluruhnya ke dalamnya (jangan menyalin file satu per satu).</p></body></html>';
    exit;
}

require "koneksi.php";
require "layout.php";

$sudah_masuk = isset($_SESSION["id_user"]);

$fitur = [
    ["Banyak dompet", "Pisahkan uang tunai, rekening bank, dan e-wallet. Saldo setiap dompet terhitung otomatis.",
     '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18"/><circle cx="16" cy="14.5" r="1"/>'],
    ["Kategori dan subkategori", "Catat dengan rinci, misalnya Kecantikan › Skincare atau Pekerjaan › Freelance. Buat sendiri sesuai kebutuhan.",
     '<path d="M20 12l-8 8-9-9V4h7z"/><circle cx="7.5" cy="8.5" r="1.2"/>'],
    ["Anggaran bulanan", "Tentukan batas pengeluaran per kategori dan pantau lewat progress bar hijau, kuning, atau merah.",
     '<path d="M4 17a8 8 0 1 1 16 0"/><path d="M12 17l4-5"/>'],
    ["Target tabungan", "Tetapkan tujuan seperti laptop baru, setor sedikit demi sedikit, dan lihat persentasenya naik.",
     '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>'],
    ["Dashboard dan analitik", "Gauge ringkasan, tren saldo, struktur pengeluaran, dan perbandingan enam bulan terakhir.",
     '<path d="M5 20V10M12 20V4M19 20v-7"/>'],
    ["Data milikmu sendiri", "Setiap akun hanya bisa melihat dan mengubah datanya sendiri, dilindungi berlapis.",
     '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>'],
];

$langkah = [
    ["Buat akun", "Daftar gratis dalam satu menit. Satu dompet tunai langsung disiapkan untukmu."],
    ["Catat transaksi", "Masukkan pemasukan dan pengeluaran beserta kategori dan subkategorinya."],
    ["Pantau dan atur", "Lihat dashboard, set anggaran, dan kejar target tabunganmu."],
];

$aman = [
    ["Password di-hash", "Password tidak pernah disimpan sebagai teks asli."],
    ["Anti pembajakan formulir", "Setiap formulir memakai token CSRF, dan semua teks di-escape dari XSS."],
    ["Batas percobaan login", "Percobaan masuk berulang dibatasi untuk menahan tebak-tebakan password."],
    ["Reset password aman", "Tautan reset berlaku 30 menit, sekali pakai, dan tokennya disimpan dalam bentuk hash."],
];

$faq = [
    ["Apakah aplikasi ini gratis?",
     "Ya, kamu bisa membuat akun dan memakai semua fiturnya tanpa biaya."],
    ["Apakah datanya aman?",
     "Password disimpan dalam bentuk hash, setiap formulir dilindungi token CSRF, dan percobaan login berulang dibatasi. Setiap akun hanya bisa melihat datanya sendiri. Tidak ada sistem yang kebal sepenuhnya, jadi tetap gunakan password yang kuat dan unik."],
    ["Apakah terhubung otomatis ke rekening bank?",
     "Tidak. Pencatatan dilakukan manual dan aplikasi tidak pernah meminta akses ke rekening atau e-wallet kamu."],
    ["Bisakah punya lebih dari satu dompet?",
     "Bisa. Buat dompet tunai, rekening bank, atau e-wallet sebanyak yang kamu mau. Saldo tiap dompet dihitung otomatis dari transaksinya."],
    ["Apa bedanya kategori dan subkategori?",
     "Kategori adalah kelompok besar, subkategori adalah rinciannya. Contohnya kategori Kecantikan berisi subkategori Makeup dan Skincare. Kamu juga bisa menambah milikmu sendiri."],
    ["Saya lupa password, bagaimana?",
     "Klik \"Lupa password ?\" di halaman masuk dan isi email akunmu. Tautan penggantinya berlaku 30 menit dan hanya bisa dipakai sekali."],
    ["Apa fungsi \"Ingat saya\"?",
     "Kamu tetap masuk di perangkat itu selama 30 hari tanpa perlu login ulang. Kamu bisa melupakan semua perangkat kapan saja lewat halaman Profil."],
    ["Bisakah dibuka lewat HP?",
     "Bisa. Tampilannya menyesuaikan ukuran layar, jadi nyaman dipakai di HP, tablet, maupun laptop."],
];

halaman_publik_awal("Beranda");
?>

<!-- ===== Hero ===== -->
<section class="hero" id="beranda">
  <div class="wadah hero-isi">
    <div class="hero-teks">
      <span class="lencana-hero">Pencatat keuangan pribadi</span>
      <h1>Atur uangmu, <span class="teks-grad">tanpa ribet.</span></h1>
      <p>Catat pemasukan dan pengeluaran, pantau saldo semua dompet, dan lihat ke mana uangmu pergi setiap bulan, semuanya di satu tempat.</p>
      <div class="hero-aksi">
        <?php if ($sudah_masuk): ?>
          <a href="dashboard.php" class="tombol">Buka dashboard</a>
        <?php else: ?>
          <a href="register.php" class="tombol">Daftar gratis</a>
          <a href="login.php" class="tombol garis">Saya sudah punya akun</a>
        <?php endif; ?>
      </div>
      <ul class="hero-poin">
        <li>Gratis</li>
        <li>Tanpa iklan</li>
        <li>Data aman</li>
      </ul>
    </div>

    <div class="hero-visual-bungkus">
      <div class="hero-visual" aria-hidden="true">
        <div class="mock mock-saldo">
          <span class="mock-label">Total saldo</span>
          <strong>Rp 12.450.000</strong>
          <span class="mock-naik">▲ 8,2% dibanding awal bulan</span>
        </div>

        <div class="mock mock-batang">
          <span class="mock-label">Pemasukan vs pengeluaran</span>
          <div class="batang-mini">
            <span class="bm h45"></span><span class="bm h70"></span><span class="bm h55"></span>
            <span class="bm h85"></span><span class="bm h60"></span><span class="bm h75"></span>
          </div>
        </div>

        <div class="mock mock-donut">
          <svg viewBox="0 0 100 100" width="84" height="84">
            <circle cx="50" cy="50" r="36" fill="none" stroke="#0e8bd6" stroke-width="14" stroke-dasharray="102 124.2" transform="rotate(-90 50 50)"/>
            <circle cx="50" cy="50" r="36" fill="none" stroke="#12b3cf" stroke-width="14" stroke-dasharray="67 159.2" stroke-dashoffset="-102" transform="rotate(-90 50 50)"/>
            <circle cx="50" cy="50" r="36" fill="none" stroke="#12a574" stroke-width="14" stroke-dasharray="57 169.2" stroke-dashoffset="-169" transform="rotate(-90 50 50)"/>
          </svg>
          <span class="mock-label">Pengeluaran</span>
        </div>

        <div class="mock mock-chip chip-1"><span class="titik-hijau"></span> Gaji <strong>+ Rp 3.500.000</strong></div>
        <div class="mock mock-chip chip-2"><span class="titik-merah"></span> Skincare <strong>− Rp 89.000</strong></div>
      </div>
      <p class="catatan-contoh">Contoh tampilan dengan data fiktif</p>
    </div>
  </div>
</section>

<!-- ===== Fitur ===== -->
<section class="bagian" id="fitur">
  <div class="wadah">
    <div class="bagian-kepala" data-muncul>
      <span class="lencana-hero">Fitur</span>
      <h2>Semua yang kamu butuhkan untuk mengatur uang</h2>
      <p>Sederhana dipakai setiap hari, lengkap saat kamu ingin melihat gambaran besarnya.</p>
    </div>

    <div class="fitur-grid">
      <?php foreach ($fitur as $f): ?>
        <article class="kartu naik fitur-kartu" data-muncul>
          <span class="fitur-ikon">
            <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $f[2] ?></svg>
          </span>
          <h3><?= h($f[0]) ?></h3>
          <p><?= h($f[1]) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ===== Cara kerja ===== -->
<section class="bagian bagian-warna" id="cara">
  <div class="wadah">
    <div class="bagian-kepala" data-muncul>
      <span class="lencana-hero">Cara kerja</span>
      <h2>Mulai dalam tiga langkah</h2>
    </div>
    <ol class="langkah">
      <?php foreach ($langkah as $i => $l): ?>
        <li class="kartu naik" data-muncul>
          <span class="nomor"><?= $i + 1 ?></span>
          <h3><?= h($l[0]) ?></h3>
          <p><?= h($l[1]) ?></p>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<!-- ===== Keamanan ===== -->
<section class="bagian" id="keamanan">
  <div class="wadah keamanan-isi">
    <div class="bagian-kepala rata-kiri" data-muncul>
      <span class="lencana-hero">Keamanan</span>
      <h2>Datamu dijaga, bukan dipajang</h2>
      <p>Keuangan itu urusan pribadi. Karena itu keamanan dipasang sejak awal, bukan belakangan.</p>
    </div>
    <ul class="aman-daftar">
      <?php foreach ($aman as $a): ?>
        <li data-muncul>
          <span class="centang" aria-hidden="true">✓</span>
          <div><strong><?= h($a[0]) ?></strong><span><?= h($a[1]) ?></span></div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<!-- ===== FAQ ===== -->
<section class="bagian bagian-warna" id="faq">
  <div class="wadah faq-isi">
    <div class="bagian-kepala" data-muncul>
      <span class="lencana-hero">FAQ</span>
      <h2>Pertanyaan yang sering diajukan</h2>
      <p>Belum menemukan jawabannya? Coba daftar dan lihat sendiri, gratis.</p>
    </div>
    <div class="faq-daftar">
      <?php foreach ($faq as $q): ?>
        <details class="faq-item" data-muncul>
          <summary><?= h($q[0]) ?></summary>
          <p><?= h($q[1]) ?></p>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ===== Ajakan ===== -->
<section class="bagian">
  <div class="wadah">
    <div class="cta" data-muncul>
      <h2>Siap merapikan keuanganmu?</h2>
      <p>Buat akun gratis dan catat transaksi pertamamu hari ini.</p>
      <?php if ($sudah_masuk): ?>
        <a href="dashboard.php" class="cta-tombol">Buka dashboard</a>
      <?php else: ?>
        <div class="cta-aksi">
          <a href="register.php" class="cta-tombol">Daftar sekarang</a>
          <a href="login.php" class="cta-tautan">Atau masuk</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<footer class="footer-publik">
  <div class="wadah footer-isi">
    <span class="footer-merek"><span class="logo"><?php ikon_logo(20); ?></span> Catatan Keuangan</span>
    <nav aria-label="Tautan footer">
      <a href="#fitur">Fitur</a>
      <a href="#cara">Cara kerja</a>
      <a href="#keamanan">Keamanan</a>
      <a href="#faq">FAQ</a>
      <?php if (!$sudah_masuk): ?><a href="login.php">Masuk</a><a href="register.php">Daftar</a><?php endif; ?>
    </nav>
    <span class="kecil">© <?= date("Y") ?> Catatan Keuangan</span>
  </div>
</footer>

<?php halaman_publik_akhir(["landing.js"]); ?>
