-- Tambahan fitur ADMIN untuk database yang SUDAH berisi data.
-- Jalankan SEKALI di phpMyAdmin (tab SQL). Data lama tidak terhapus.
USE keuangan_app;

ALTER TABLE users
  ADD COLUMN role  ENUM('user','admin') NOT NULL DEFAULT 'user' AFTER password,
  ADD COLUMN aktif TINYINT(1) NOT NULL DEFAULT 1 AFTER role;

CREATE TABLE IF NOT EXISTS log_aktivitas (
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

-- Jadikan akunmu admin (ganti USERNAME dengan username yang sudah terdaftar):
-- UPDATE users SET role = 'admin' WHERE username = 'USERNAME';
