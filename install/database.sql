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
  `olusturma_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kullanici_adi` (`kullanici_adi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

CREATE TABLE IF NOT EXISTS `lite_araclar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `arac_turu` varchar(100) NOT NULL,
  `plaka` varchar(20) NOT NULL,
  `marka_model` varchar(150) NOT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `olusturma_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
  `olusturan_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plaka` (`plaka`),
  KEY `olusturan_id` (`olusturan_id`),
  CONSTRAINT `lite_araclar_ibfk_1` FOREIGN KEY (`olusturan_id`) REFERENCES `kullanicilar` (`id`)
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

CREATE TABLE IF NOT EXISTS `sistem_migrations` (
  `versiyon` varchar(20) NOT NULL,
  `uygulandi_tarih` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`versiyon`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

SET foreign_key_checks = 1;