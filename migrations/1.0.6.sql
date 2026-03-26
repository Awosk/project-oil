-- Migration: v1.0.6
-- Asenkron mail kuyruğu sistemi

SET foreign_key_checks = 0;

ALTER TABLE `kullanicilar`
  ADD COLUMN IF NOT EXISTS `mail_bildirim_aktif` TINYINT(1) NOT NULL DEFAULT 0;

-- 1. Mail kuyruğu tablosu
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

-- 2. Rate limit ve cooldown ayarları
INSERT IGNORE INTO `sistem_ayarlar` (`anahtar`, `deger`) VALUES
('mail_rate_limit_adet',   '10'),
('mail_rate_limit_dakika', '5'),
('mail_cooldown_dakika',   '15'),
('mail_cooldown_bitis',    '');

SET foreign_key_checks = 1;