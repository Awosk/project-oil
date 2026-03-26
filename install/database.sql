-- Project Oil — Veritabanı Kurulum Dosyası
-- Bu dosya kurulum sihirbazı tarafından otomatik olarak kullanılır.
-- Tablolar yoksa oluşturulur, mevcutsa ve veri varsa dokunulmaz.
-- Varsayılan kullanıcı yoktur; kurulum sırasında oluşturulur.

SET NAMES utf8mb4;
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

CREATE TABLE IF NOT EXISTS `kullanicilar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ad_soyad` varchar(100) NOT NULL,
  `kullanici_adi` varchar(50) NOT NULL,
  `sifre` varchar(255) NOT NULL,
  `rol` enum('admin','kullanici') NOT NULL DEFAULT 'kullanici',
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `tema` enum('light','dark') NOT NULL DEFAULT 'light',
  `email` varchar(150) DEFAULT NULL,
  `olusturma_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kullanici_adi` (`kullanici_adi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS `lite_arac_turleri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tur_adi` varchar(100) NOT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `olusturma_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tur_adi` (`tur_adi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS `lite_araclar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `arac_turu_id` int(11) NULL,
  `plaka` varchar(20) NOT NULL,
  `marka_model` varchar(150) NOT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `olusturma_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  `olusturan_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plaka` (`plaka`),
  KEY `olusturan_id` (`olusturan_id`),
  KEY `arac_turu_id` (`arac_turu_id`),
  CONSTRAINT `lite_araclar_ibfk_1` FOREIGN KEY (`olusturan_id`) REFERENCES `kullanicilar` (`id`),
  CONSTRAINT `lite_araclar_ibfk_tur` FOREIGN KEY (`arac_turu_id`) REFERENCES `lite_arac_turleri` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS `lite_tesisler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_adi` varchar(200) NOT NULL,
  `firma_adresi` text NOT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `olusturma_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  `olusturan_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `olusturan_id` (`olusturan_id`),
  CONSTRAINT `lite_tesisler_ibfk_1` FOREIGN KEY (`olusturan_id`) REFERENCES `kullanicilar` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS `lite_urunler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `urun_kodu` varchar(50) NOT NULL,
  `urun_adi` varchar(200) NOT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `olusturma_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  `olusturan_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `urun_kodu` (`urun_kodu`),
  KEY `olusturan_id` (`olusturan_id`),
  CONSTRAINT `lite_urunler_ibfk_1` FOREIGN KEY (`olusturan_id`) REFERENCES `kullanicilar` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS `lite_kayitlar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kayit_turu` enum('arac','tesis') NOT NULL,
  `arac_id` int(11) DEFAULT NULL,
  `tesis_id` int(11) DEFAULT NULL,
  `urun_id` int(11) NOT NULL,
  `miktar` decimal(10,2) NOT NULL,
  `tarih` date NOT NULL,
  `aciklama` text DEFAULT NULL,
  `olusturma_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  `olusturan_id` int(11) DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `yag_bakimi` tinyint(1) NOT NULL DEFAULT 0,
  `mevcut_km` int(11) DEFAULT NULL,
  `islendi` tinyint(1) NOT NULL DEFAULT 0,
  `islendi_tarih` datetime DEFAULT NULL,
  `islendi_kullanici_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `arac_id` (`arac_id`),
  KEY `tesis_id` (`tesis_id`),
  KEY `urun_id` (`urun_id`),
  KEY `olusturan_id` (`olusturan_id`),
  KEY `islendi_kullanici_id` (`islendi_kullanici_id`),
  CONSTRAINT `fk_islendi_kullanici` FOREIGN KEY (`islendi_kullanici_id`) REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL,
  CONSTRAINT `lite_kayitlar_ibfk_1` FOREIGN KEY (`arac_id`) REFERENCES `lite_araclar` (`id`),
  CONSTRAINT `lite_kayitlar_ibfk_2` FOREIGN KEY (`tesis_id`) REFERENCES `lite_tesisler` (`id`),
  CONSTRAINT `lite_kayitlar_ibfk_3` FOREIGN KEY (`urun_id`) REFERENCES `lite_urunler` (`id`),
  CONSTRAINT `lite_kayitlar_ibfk_4` FOREIGN KEY (`olusturan_id`) REFERENCES `kullanicilar` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS `sistem_loglari` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kullanici_id` int(11) DEFAULT NULL,
  `kullanici_adi` varchar(50) DEFAULT NULL,
  `ad_soyad` varchar(100) DEFAULT NULL,
  `sistem` enum('ana','lite') NOT NULL DEFAULT 'ana',
  `aksiyon` enum('ekle','guncelle','sil','giris','cikis') NOT NULL,
  `modul` varchar(50) NOT NULL,
  `kayit_id` int(11) DEFAULT NULL,
  `aciklama` text NOT NULL,
  `eski_deger` longtext DEFAULT NULL CHECK (json_valid(`eski_deger`)),
  `yeni_deger` longtext DEFAULT NULL CHECK (json_valid(`yeni_deger`)),
  `ip_adresi` varchar(45) DEFAULT NULL,
  `olusturma_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `kullanici_id` (`kullanici_id`),
  CONSTRAINT `sistem_loglari_ibfk_1` FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS `sistem_ayarlar` (
  `anahtar` varchar(100) NOT NULL,
  `deger` text DEFAULT NULL,
  `guncelleme_tarihi` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`anahtar`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

INSERT IGNORE INTO `sistem_ayarlar` (`anahtar`, `deger`) VALUES
('smtp_aktif',     '0'),
('smtp_host',      ''),
('smtp_port',      '587'),
('smtp_sifrelem',  'tls'),
('smtp_kullanici', ''),
('smtp_sifre',     ''),
('smtp_gonderen',  ''),
('smtp_ad',        '');

CREATE TABLE IF NOT EXISTS `admin_bildirim_filtreler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kullanici_id` int(11) NOT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 0,
  `modul` varchar(50) NOT NULL,
  `aksiyon` varchar(50) NOT NULL,
  `olusturma_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kullanici_modul_aksiyon` (`kullanici_id`, `modul`, `aksiyon`),
  CONSTRAINT `fk_bildirim_kullanici` FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS `sifre_sifirlama` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kullanici_id` int(11) NOT NULL,
  `token` varchar(100) NOT NULL,
  `son_kullanma` datetime NOT NULL,
  `kullanildi` tinyint(1) NOT NULL DEFAULT 0,
  `olusturma_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `kullanici_id` (`kullanici_id`),
  CONSTRAINT `fk_sifre_sifirlama_kullanici` FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS `mail_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `to_email` varchar(150) NOT NULL,
  `to_name` varchar(100) NOT NULL DEFAULT '',
  `subject` varchar(255) NOT NULL,
  `body` longtext NOT NULL,
  `status` enum('pending','sent','failed','paused','processing','force','cancelled') NOT NULL DEFAULT 'pending',
  `attempt_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL,
  `hata_mesaji` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
 
-- sistem_ayarlar INSERT bloğuna şunları da ekle:
INSERT IGNORE INTO `sistem_ayarlar` (`anahtar`, `deger`) VALUES
('mail_rate_limit_adet',   '10'),
('mail_rate_limit_dakika', '5'),
('mail_cooldown_dakika',   '15'),
('mail_cooldown_bitis',    '');

CREATE TABLE IF NOT EXISTS `sistem_migrations` (
  `versiyon` varchar(20) NOT NULL,
  `uygulandi_tarih` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`versiyon`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

SET foreign_key_checks = 1;
