-- =====================================================
--  DATABASE: WEBSITE KEUANGAN  (keuangan_app)
--  Import lewat phpMyAdmin > tab Import. Aman diulang
--  (semua tabel dibuat ulang, data lama ikut terhapus).
--
--  RANCANGAN (relasi):
--  users 1--N dompet 1--1 saldo_dompet
--  users 1--N kategori(opsional) 1--N subkategori
--  users 1--N transaksi N--1 dompet / kategori / subkategori
--  users 1--N anggaran, target_tabungan
--  users 1--N remember_token, password_reset
--  login_attempt: pembatas percobaan (anti brute force)
-- =====================================================

CREATE DATABASE IF NOT EXISTS keuangan_app
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE keuangan_app;

SET FOREIGN_KEY_CHECKS = 0;
DROP VIEW  IF EXISTS v_ringkasan_bulanan;
DROP VIEW  IF EXISTS v_pengeluaran_subkategori;
DROP TABLE IF EXISTS log_aktivitas;
DROP TABLE IF EXISTS login_attempt;
DROP TABLE IF EXISTS password_reset;
DROP TABLE IF EXISTS remember_token;
DROP TABLE IF EXISTS target_tabungan;
DROP TABLE IF EXISTS anggaran;
DROP TABLE IF EXISTS transaksi;
DROP TABLE IF EXISTS saldo_dompet;
DROP TABLE IF EXISTS subkategori;
DROP TABLE IF EXISTS kategori;
DROP TABLE IF EXISTS dompet;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------- 1. users ----------
CREATE TABLE users (
  id_user        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama_lengkap   VARCHAR(100) NOT NULL,
  username       VARCHAR(30)  NOT NULL,
  email          VARCHAR(100) NOT NULL,
  password       VARCHAR(255) NOT NULL,          -- hasil password_hash(), bukan teks asli
  role           ENUM('user','admin') NOT NULL DEFAULT 'user',
  aktif          TINYINT(1)   NOT NULL DEFAULT 1,   -- 0 = akun dinonaktifkan admin
  terakhir_login DATETIME     DEFAULT NULL,
  dibuat_pada    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_user),
  UNIQUE KEY uq_username (username),
  UNIQUE KEY uq_email (email)
) ENGINE=InnoDB;

-- ---------- 2. dompet ----------
CREATE TABLE dompet (
  id_dompet  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_user    INT UNSIGNED NOT NULL,
  nama       VARCHAR(50)  NOT NULL,
  jenis      ENUM('tunai','bank','e-wallet') NOT NULL DEFAULT 'tunai',
  saldo_awal DECIMAL(15,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (id_dompet),
  UNIQUE KEY uq_dompet (id_user, nama),
  CONSTRAINT fk_dompet_user FOREIGN KEY (id_user)
    REFERENCES users (id_user) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- 3. saldo_dompet (diisi otomatis oleh trigger) ----------
CREATE TABLE saldo_dompet (
  id_saldo        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_dompet       INT UNSIGNED NOT NULL,
  saldo           DECIMAL(15,2) NOT NULL DEFAULT 0,
  diperbarui_pada TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_saldo),
  UNIQUE KEY uq_saldo_dompet (id_dompet),
  CONSTRAINT fk_saldo_dompet FOREIGN KEY (id_dompet)
    REFERENCES dompet (id_dompet) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- 4. kategori (id_user NULL = bawaan untuk semua user) ----------
CREATE TABLE kategori (
  id_kategori INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_user     INT UNSIGNED DEFAULT NULL,
  nama        VARCHAR(50)  NOT NULL,
  jenis       ENUM('pemasukan','pengeluaran') NOT NULL,
  PRIMARY KEY (id_kategori),
  CONSTRAINT fk_kategori_user FOREIGN KEY (id_user)
    REFERENCES users (id_user) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- 5. subkategori ----------
CREATE TABLE subkategori (
  id_subkategori INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_kategori    INT UNSIGNED NOT NULL,
  id_user        INT UNSIGNED DEFAULT NULL,
  nama           VARCHAR(50)  NOT NULL,
  PRIMARY KEY (id_subkategori),
  UNIQUE KEY uq_sub_kat (id_subkategori, id_kategori),
  KEY idx_sub_kategori (id_kategori),
  CONSTRAINT fk_sub_kategori FOREIGN KEY (id_kategori)
    REFERENCES kategori (id_kategori) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_sub_user FOREIGN KEY (id_user)
    REFERENCES users (id_user) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- 6. transaksi ----------
CREATE TABLE transaksi (
  id_transaksi   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_user        INT UNSIGNED NOT NULL,
  id_dompet      INT UNSIGNED NOT NULL,
  id_kategori    INT UNSIGNED NOT NULL,
  id_subkategori INT UNSIGNED DEFAULT NULL,
  tanggal        DATE          NOT NULL,
  nominal        DECIMAL(15,2) NOT NULL,
  keterangan     VARCHAR(150)  DEFAULT NULL,
  dibuat_pada    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diubah_pada    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_transaksi),
  KEY idx_user_tanggal (id_user, tanggal),
  CONSTRAINT chk_nominal CHECK (nominal > 0),
  CONSTRAINT fk_trx_user FOREIGN KEY (id_user)
    REFERENCES users (id_user) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_trx_dompet FOREIGN KEY (id_dompet)
    REFERENCES dompet (id_dompet) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_trx_kategori FOREIGN KEY (id_kategori)
    REFERENCES kategori (id_kategori) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_trx_subkategori FOREIGN KEY (id_subkategori, id_kategori)
    REFERENCES subkategori (id_subkategori, id_kategori) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------- 7. anggaran ----------
CREATE TABLE anggaran (
  id_anggaran INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_user     INT UNSIGNED NOT NULL,
  id_kategori INT UNSIGNED NOT NULL,
  bulan       CHAR(7)       NOT NULL,           -- YYYY-MM
  batas       DECIMAL(15,2) NOT NULL,
  PRIMARY KEY (id_anggaran),
  UNIQUE KEY uq_anggaran (id_user, id_kategori, bulan),
  CONSTRAINT fk_angg_user FOREIGN KEY (id_user)
    REFERENCES users (id_user) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_angg_kategori FOREIGN KEY (id_kategori)
    REFERENCES kategori (id_kategori) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- 8. target_tabungan ----------
CREATE TABLE target_tabungan (
  id_target      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_user        INT UNSIGNED NOT NULL,
  nama           VARCHAR(80)   NOT NULL,
  target_nominal DECIMAL(15,2) NOT NULL,
  terkumpul      DECIMAL(15,2) NOT NULL DEFAULT 0,
  tenggat        DATE          DEFAULT NULL,
  status         ENUM('berjalan','tercapai') NOT NULL DEFAULT 'berjalan',
  PRIMARY KEY (id_target),
  CONSTRAINT fk_target_user FOREIGN KEY (id_user)
    REFERENCES users (id_user) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- 9. remember_token: "Ingat saya" ----------
-- Yang disimpan hanya HASH validator, bukan nilai di cookie.
CREATE TABLE remember_token (
  id_token       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_user        INT UNSIGNED NOT NULL,
  selector       CHAR(24)     NOT NULL,
  validator_hash CHAR(64)     NOT NULL,
  kedaluwarsa    DATETIME     NOT NULL,
  PRIMARY KEY (id_token),
  UNIQUE KEY uq_selector (selector),
  CONSTRAINT fk_remember_user FOREIGN KEY (id_user)
    REFERENCES users (id_user) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- 10. password_reset: "Lupa password" ----------
-- Token berlaku singkat, sekali pakai, hanya hash yang disimpan.
CREATE TABLE password_reset (
  id_reset    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_user     INT UNSIGNED NOT NULL,
  token_hash  CHAR(64)     NOT NULL,
  kedaluwarsa DATETIME     NOT NULL,
  dipakai     TINYINT(1)   NOT NULL DEFAULT 0,
  dibuat_pada TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_reset),
  UNIQUE KEY uq_reset_token (token_hash),
  CONSTRAINT fk_reset_user FOREIGN KEY (id_user)
    REFERENCES users (id_user) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- 11. login_attempt: anti brute force ----------
CREATE TABLE login_attempt (
  id_attempt INT UNSIGNED NOT NULL AUTO_INCREMENT,
  jenis      ENUM('login','lupa','daftar') NOT NULL,
  kunci      CHAR(64)    NOT NULL,              -- hash dari username/email
  ip         VARCHAR(45) NOT NULL,
  waktu      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_attempt),
  KEY idx_attempt (jenis, waktu),
  KEY idx_attempt_kunci (jenis, kunci),
  KEY idx_attempt_ip (jenis, ip)
) ENGINE=InnoDB;

-- ---------- 12. log_aktivitas: jejak tindakan admin ----------
CREATE TABLE log_aktivitas (
  id_log   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_admin INT UNSIGNED DEFAULT NULL,
  aksi     VARCHAR(50)  NOT NULL,
  target   VARCHAR(150) DEFAULT NULL,
  ip       VARCHAR(45)  NOT NULL,
  waktu    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_log),
  KEY idx_log_waktu (waktu),
  CONSTRAINT fk_log_admin FOREIGN KEY (id_admin)
    REFERENCES users (id_user) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================
--  TRIGGER: menjaga saldo_dompet selalu cocok
-- =====================================================
DELIMITER $$

CREATE TRIGGER trg_dompet_ai AFTER INSERT ON dompet
FOR EACH ROW
BEGIN
  INSERT INTO saldo_dompet (id_dompet, saldo) VALUES (NEW.id_dompet, NEW.saldo_awal);
END$$

CREATE TRIGGER trg_dompet_au AFTER UPDATE ON dompet
FOR EACH ROW
BEGIN
  IF NEW.saldo_awal <> OLD.saldo_awal THEN
    UPDATE saldo_dompet
       SET saldo = saldo + (NEW.saldo_awal - OLD.saldo_awal)
     WHERE id_dompet = NEW.id_dompet;
  END IF;
END$$

CREATE TRIGGER trg_transaksi_ai AFTER INSERT ON transaksi
FOR EACH ROW
BEGIN
  DECLARE v_jenis VARCHAR(12);
  SELECT jenis INTO v_jenis FROM kategori WHERE id_kategori = NEW.id_kategori;
  UPDATE saldo_dompet
     SET saldo = saldo + IF(v_jenis = 'pemasukan', NEW.nominal, -NEW.nominal)
   WHERE id_dompet = NEW.id_dompet;
END$$

CREATE TRIGGER trg_transaksi_au AFTER UPDATE ON transaksi
FOR EACH ROW
BEGIN
  DECLARE v_lama VARCHAR(12);
  DECLARE v_baru VARCHAR(12);
  SELECT jenis INTO v_lama FROM kategori WHERE id_kategori = OLD.id_kategori;
  SELECT jenis INTO v_baru FROM kategori WHERE id_kategori = NEW.id_kategori;
  UPDATE saldo_dompet
     SET saldo = saldo - IF(v_lama = 'pemasukan', OLD.nominal, -OLD.nominal)
   WHERE id_dompet = OLD.id_dompet;
  UPDATE saldo_dompet
     SET saldo = saldo + IF(v_baru = 'pemasukan', NEW.nominal, -NEW.nominal)
   WHERE id_dompet = NEW.id_dompet;
END$$

CREATE TRIGGER trg_transaksi_ad AFTER DELETE ON transaksi
FOR EACH ROW
BEGIN
  DECLARE v_jenis VARCHAR(12);
  SELECT jenis INTO v_jenis FROM kategori WHERE id_kategori = OLD.id_kategori;
  UPDATE saldo_dompet
     SET saldo = saldo - IF(v_jenis = 'pemasukan', OLD.nominal, -OLD.nominal)
   WHERE id_dompet = OLD.id_dompet;
END$$

DELIMITER ;

-- =====================================================
--  VIEW laporan
-- =====================================================
CREATE VIEW v_ringkasan_bulanan AS
SELECT t.id_user,
       DATE_FORMAT(t.tanggal, '%Y-%m') AS bulan,
       SUM(CASE WHEN k.jenis = 'pemasukan'   THEN t.nominal ELSE 0 END) AS total_masuk,
       SUM(CASE WHEN k.jenis = 'pengeluaran' THEN t.nominal ELSE 0 END) AS total_keluar
FROM transaksi t
JOIN kategori k ON k.id_kategori = t.id_kategori
GROUP BY t.id_user, DATE_FORMAT(t.tanggal, '%Y-%m');

CREATE VIEW v_pengeluaran_subkategori AS
SELECT t.id_user,
       DATE_FORMAT(t.tanggal, '%Y-%m') AS bulan,
       k.nama AS kategori,
       COALESCE(s.nama, '(tanpa subkategori)') AS subkategori,
       k.jenis,
       SUM(t.nominal) AS total
FROM transaksi t
JOIN kategori k ON k.id_kategori = t.id_kategori
LEFT JOIN subkategori s ON s.id_subkategori = t.id_subkategori
GROUP BY t.id_user, DATE_FORMAT(t.tanggal, '%Y-%m'), k.nama, s.nama, k.jenis;

-- =====================================================
--  DATA AWAL (tanpa akun bawaan: daftar lewat halaman Daftar)
-- =====================================================
INSERT INTO kategori (id_user, nama, jenis) VALUES
  (NULL, 'Pekerjaan',         'pemasukan'),    -- 1
  (NULL, 'Uang Saku',         'pemasukan'),    -- 2
  (NULL, 'Lain-lain',         'pemasukan'),    -- 3
  (NULL, 'Tempat Tinggal',    'pengeluaran'),  -- 4
  (NULL, 'Makanan & Minuman', 'pengeluaran'),  -- 5
  (NULL, 'Kecantikan',        'pengeluaran'),  -- 6
  (NULL, 'Transportasi',      'pengeluaran'),  -- 7
  (NULL, 'Pendidikan',        'pengeluaran'),  -- 8
  (NULL, 'Tagihan & Pulsa',   'pengeluaran'),  -- 9
  (NULL, 'Hiburan',           'pengeluaran'),  -- 10
  (NULL, 'Belanja',           'pengeluaran'),  -- 11
  (NULL, 'Kesehatan',         'pengeluaran'),  -- 12
  (NULL, 'Lain-lain',         'pengeluaran');  -- 13

INSERT INTO subkategori (id_kategori, id_user, nama) VALUES
  (1, NULL, 'Gaji'), (1, NULL, 'Freelance'), (1, NULL, 'Bonus'), (1, NULL, 'Hasil Jualan'),
  (2, NULL, 'Uang Saku'), (2, NULL, 'Kiriman Keluarga'),
  (3, NULL, 'Nemu Uang'), (3, NULL, 'Hadiah'), (3, NULL, 'Cashback'), (3, NULL, 'Lainnya'),
  (4, NULL, 'Sewa'), (4, NULL, 'Listrik & Air'), (4, NULL, 'Perabot'),
  (5, NULL, 'Makan'), (5, NULL, 'Kopi & Minuman'), (5, NULL, 'Belanja Dapur'),
  (6, NULL, 'Makeup'), (6, NULL, 'Skincare'), (6, NULL, 'Perawatan Rambut'), (6, NULL, 'Salon'),
  (7, NULL, 'Bensin'), (7, NULL, 'Ojek Online'), (7, NULL, 'Angkutan Umum'), (7, NULL, 'Parkir'),
  (8, NULL, 'UKT / SPP'), (8, NULL, 'Buku & Alat Tulis'), (8, NULL, 'Print & Fotokopi'),
  (9, NULL, 'Pulsa'), (9, NULL, 'Paket Data'), (9, NULL, 'Langganan'),
  (10, NULL, 'Nonton & Game'), (10, NULL, 'Jalan-jalan'),
  (11, NULL, 'Pakaian'), (11, NULL, 'Elektronik'),
  (12, NULL, 'Obat'), (12, NULL, 'Dokter'),
  (13, NULL, 'Lainnya');

-- =====================================================
--  MEMBUAT ADMIN PERTAMA
--  Daftar dulu lewat halaman Daftar, lalu jalankan (ganti USERNAME):
--    UPDATE users SET role = 'admin' WHERE username = 'USERNAME';
-- =====================================================

-- =====================================================
--  SARAN KEAMANAN: user MySQL khusus (hapus tanda -- untuk memakai)
--  lalu isi $user dan $pass di koneksi.php dengan akun ini.
-- =====================================================
-- CREATE USER 'keuangan_app'@'localhost' IDENTIFIED BY 'GANTI_DENGAN_PASSWORD_KUAT';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON keuangan_app.* TO 'keuangan_app'@'localhost';
-- FLUSH PRIVILEGES;
