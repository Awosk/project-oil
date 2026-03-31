-- Project Oil — Migration 2.0.0
-- Veritabanı şemasını Türkçe'den İngilizce'ye dönüştürür.
-- Tüm veriler korunur; sadece tablo ve sütun isimleri değişir.
-- MariaDB 5.5+ / MySQL 5.6+ uyumlu (CHANGE COLUMN kullanır).

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ═══════════════════════════════════════════════════════════════
-- 1. kullanicilar → users
-- ═══════════════════════════════════════════════════════════════

-- Foreign key kısıtlamalarını kaldır
ALTER TABLE `lite_araclar`             DROP FOREIGN KEY IF EXISTS `lite_araclar_ibfk_1`;
ALTER TABLE `lite_tesisler`            DROP FOREIGN KEY IF EXISTS `lite_tesisler_ibfk_1`;
ALTER TABLE `lite_urunler`             DROP FOREIGN KEY IF EXISTS `lite_urunler_ibfk_1`;
ALTER TABLE `lite_kayitlar`            DROP FOREIGN KEY IF EXISTS `lite_kayitlar_ibfk_1`;
ALTER TABLE `lite_kayitlar`            DROP FOREIGN KEY IF EXISTS `lite_kayitlar_ibfk_2`;
ALTER TABLE `lite_kayitlar`            DROP FOREIGN KEY IF EXISTS `lite_kayitlar_ibfk_3`;
ALTER TABLE `lite_kayitlar`            DROP FOREIGN KEY IF EXISTS `lite_kayitlar_ibfk_4`;
ALTER TABLE `lite_kayitlar`            DROP FOREIGN KEY IF EXISTS `fk_islendi_kullanici`;
ALTER TABLE `sistem_loglari`           DROP FOREIGN KEY IF EXISTS `sistem_loglari_ibfk_1`;
ALTER TABLE `admin_bildirim_filtreler` DROP FOREIGN KEY IF EXISTS `fk_bildirim_kullanici`;
ALTER TABLE `sifre_sifirlama`          DROP FOREIGN KEY IF EXISTS `fk_sifre_sifirlama_kullanici`;

-- Tabloyu yeniden adlandır
RENAME TABLE `kullanicilar` TO `users`;

-- Sütunları yeniden adlandır (CHANGE sözdizimi: eski_ad yeni_ad TİP)
ALTER TABLE `users`
    CHANGE `ad_soyad`           `full_name`          varchar(100) NOT NULL,
    CHANGE `kullanici_adi`      `username`           varchar(50)  NOT NULL,
    CHANGE `sifre`              `password`           varchar(255) NOT NULL,
    CHANGE `rol`                `role`               enum('admin','kullanici') NOT NULL DEFAULT 'kullanici',
    CHANGE `aktif`              `is_active`          tinyint(1)   NOT NULL DEFAULT 1,
    CHANGE `tema`               `theme`              enum('light','dark') NOT NULL DEFAULT 'light',
    CHANGE `olusturma_tarihi`   `created_at`         datetime     NOT NULL DEFAULT current_timestamp(),
    CHANGE `mail_bildirim_aktif` `mail_notifications` tinyint(1)  NOT NULL DEFAULT 0;

-- Unique key adını güncelle
ALTER TABLE `users` DROP KEY IF EXISTS `kullanici_adi`;
ALTER TABLE `users` ADD UNIQUE KEY `username` (`username`);

-- ═══════════════════════════════════════════════════════════════
-- 2. lite_arac_turleri → vehicle_types
-- ═══════════════════════════════════════════════════════════════

ALTER TABLE `lite_araclar` DROP FOREIGN KEY IF EXISTS `lite_araclar_ibfk_tur`;

RENAME TABLE `lite_arac_turleri` TO `vehicle_types`;

ALTER TABLE `vehicle_types`
    CHANGE `tur_adi`          `type_name` varchar(100) NOT NULL,
    CHANGE `oncelik`          `priority`  int(11)      NOT NULL DEFAULT 1,
    CHANGE `aktif`            `is_active` tinyint(1)   NOT NULL DEFAULT 1,
    CHANGE `olusturma_tarihi` `created_at` datetime    NOT NULL DEFAULT current_timestamp();

ALTER TABLE `vehicle_types` DROP KEY IF EXISTS `tur_adi`;
ALTER TABLE `vehicle_types` ADD UNIQUE KEY `type_name` (`type_name`);

-- ═══════════════════════════════════════════════════════════════
-- 3. lite_araclar → vehicles
-- ═══════════════════════════════════════════════════════════════

RENAME TABLE `lite_araclar` TO `vehicles`;

ALTER TABLE `vehicles`
    CHANGE `arac_turu_id`     `vehicle_type_id` int(11)      DEFAULT NULL,
    CHANGE `plaka`            `plate`           varchar(20)  NOT NULL,
    CHANGE `marka_model`      `brand_model`     varchar(150) NOT NULL,
    CHANGE `aktif`            `is_active`       tinyint(1)   NOT NULL DEFAULT 1,
    CHANGE `olusturma_tarihi` `created_at`      datetime     NOT NULL DEFAULT current_timestamp(),
    CHANGE `olusturan_id`     `created_by`      int(11)      DEFAULT NULL;

ALTER TABLE `vehicles` DROP KEY IF EXISTS `plaka`;
ALTER TABLE `vehicles` ADD UNIQUE KEY `plate` (`plate`);

ALTER TABLE `vehicles`
    ADD CONSTRAINT `vehicles_ibfk_1`    FOREIGN KEY (`created_by`)      REFERENCES `users` (`id`),
    ADD CONSTRAINT `vehicles_ibfk_type` FOREIGN KEY (`vehicle_type_id`) REFERENCES `vehicle_types` (`id`);

-- ═══════════════════════════════════════════════════════════════
-- 4. lite_tesisler → facilities
-- ═══════════════════════════════════════════════════════════════

RENAME TABLE `lite_tesisler` TO `facilities`;

ALTER TABLE `facilities`
    CHANGE `firma_adi`        `name`       varchar(200) NOT NULL,
    CHANGE `firma_adresi`     `address`    text         NOT NULL,
    CHANGE `aktif`            `is_active`  tinyint(1)   NOT NULL DEFAULT 1,
    CHANGE `olusturma_tarihi` `created_at` datetime     NOT NULL DEFAULT current_timestamp(),
    CHANGE `olusturan_id`     `created_by` int(11)      DEFAULT NULL;

ALTER TABLE `facilities`
    ADD CONSTRAINT `facilities_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

-- ═══════════════════════════════════════════════════════════════
-- 5. lite_urunler → products
-- ═══════════════════════════════════════════════════════════════

RENAME TABLE `lite_urunler` TO `products`;

ALTER TABLE `products`
    CHANGE `urun_kodu`        `product_code` varchar(50)  NOT NULL,
    CHANGE `urun_adi`         `product_name` varchar(200) NOT NULL,
    CHANGE `aktif`            `is_active`    tinyint(1)   NOT NULL DEFAULT 1,
    CHANGE `olusturma_tarihi` `created_at`   datetime     NOT NULL DEFAULT current_timestamp(),
    CHANGE `olusturan_id`     `created_by`   int(11)      DEFAULT NULL;

ALTER TABLE `products` DROP KEY IF EXISTS `urun_kodu`;
ALTER TABLE `products` ADD UNIQUE KEY `product_code` (`product_code`);

ALTER TABLE `products`
    ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

-- ═══════════════════════════════════════════════════════════════
-- 6. lite_kayitlar → oil_records
-- ═══════════════════════════════════════════════════════════════

RENAME TABLE `lite_kayitlar` TO `oil_records`;

ALTER TABLE `oil_records`
    CHANGE `kayit_turu`           `record_type`  enum('arac','tesis') NOT NULL,
    CHANGE `arac_id`              `vehicle_id`   int(11)      DEFAULT NULL,
    CHANGE `tesis_id`             `facility_id`  int(11)      DEFAULT NULL,
    CHANGE `urun_id`              `product_id`   int(11)      NOT NULL,
    CHANGE `miktar`               `quantity`     decimal(10,2) NOT NULL,
    CHANGE `tarih`                `date`         date         NOT NULL,
    CHANGE `aciklama`             `notes`        text         DEFAULT NULL,
    CHANGE `olusturma_tarihi`     `created_at`   datetime     NOT NULL DEFAULT current_timestamp(),
    CHANGE `olusturan_id`         `created_by`   int(11)      DEFAULT NULL,
    CHANGE `aktif`                `is_active`    tinyint(1)   NOT NULL DEFAULT 1,
    CHANGE `yag_bakimi`           `is_oil_change` tinyint(1)  NOT NULL DEFAULT 0,
    CHANGE `mevcut_km`            `current_km`   int(11)      DEFAULT NULL,
    CHANGE `islendi`              `is_processed` tinyint(1)   NOT NULL DEFAULT 0,
    CHANGE `islendi_tarih`        `processed_at` datetime     DEFAULT NULL,
    CHANGE `islendi_kullanici_id` `processed_by` int(11)      DEFAULT NULL;

ALTER TABLE `oil_records`
    ADD CONSTRAINT `fk_processed_by`    FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `oil_records_ibfk_1` FOREIGN KEY (`vehicle_id`)   REFERENCES `vehicles` (`id`),
    ADD CONSTRAINT `oil_records_ibfk_2` FOREIGN KEY (`facility_id`)  REFERENCES `facilities` (`id`),
    ADD CONSTRAINT `oil_records_ibfk_3` FOREIGN KEY (`product_id`)   REFERENCES `products` (`id`),
    ADD CONSTRAINT `oil_records_ibfk_4` FOREIGN KEY (`created_by`)   REFERENCES `users` (`id`);

-- ═══════════════════════════════════════════════════════════════
-- 7. sistem_loglari → system_logs
-- ═══════════════════════════════════════════════════════════════

RENAME TABLE `sistem_loglari` TO `system_logs`;

ALTER TABLE `system_logs`
    CHANGE `kullanici_id`     `user_id`     int(11)      DEFAULT NULL,
    CHANGE `kullanici_adi`    `username`    varchar(50)  DEFAULT NULL,
    CHANGE `ad_soyad`         `full_name`   varchar(100) DEFAULT NULL,
    CHANGE `sistem`           `system`      enum('lite','ana') NOT NULL DEFAULT 'lite',
    CHANGE `aksiyon`          `action`      enum('ekle','guncelle','sil','giris','cikis') NOT NULL,
    CHANGE `modul`            `module`      varchar(50)  NOT NULL,
    CHANGE `kayit_id`         `record_id`   int(11)      DEFAULT NULL,
    CHANGE `aciklama`         `description` text         NOT NULL,
    CHANGE `eski_deger`       `old_value`   longtext     DEFAULT NULL,
    CHANGE `yeni_deger`       `new_value`   longtext     DEFAULT NULL,
    CHANGE `ip_adresi`        `ip_address`  varchar(45)  DEFAULT NULL,
    CHANGE `olusturma_tarihi` `created_at`  datetime     NOT NULL DEFAULT current_timestamp();

ALTER TABLE `system_logs`
    ADD CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- ═══════════════════════════════════════════════════════════════
-- 8. sistem_ayarlar → system_settings
-- ═══════════════════════════════════════════════════════════════

RENAME TABLE `sistem_ayarlar` TO `system_settings`;

ALTER TABLE `system_settings`
    CHANGE `anahtar`           `key`        varchar(100) NOT NULL,
    CHANGE `deger`             `value`      text         DEFAULT NULL,
    CHANGE `guncelleme_tarihi` `updated_at` datetime     NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp();

-- ═══════════════════════════════════════════════════════════════
-- 9. admin_bildirim_filtreler → notification_filters
-- ═══════════════════════════════════════════════════════════════

RENAME TABLE `admin_bildirim_filtreler` TO `notification_filters`;

ALTER TABLE `notification_filters`
    CHANGE `kullanici_id`     `user_id`    int(11)     NOT NULL,
    CHANGE `aktif`            `is_active`  tinyint(1)  NOT NULL DEFAULT 0,
    CHANGE `modul`            `module`     varchar(50) NOT NULL,
    CHANGE `aksiyon`          `action`     varchar(50) NOT NULL,
    CHANGE `olusturma_tarihi` `created_at` datetime    NOT NULL DEFAULT current_timestamp();

ALTER TABLE `notification_filters` DROP KEY IF EXISTS `kullanici_modul_aksiyon`;
ALTER TABLE `notification_filters` ADD UNIQUE KEY `user_module_action` (`user_id`, `module`, `action`);

ALTER TABLE `notification_filters`
    ADD CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- ═══════════════════════════════════════════════════════════════
-- 10. sifre_sifirlama → password_resets
-- ═══════════════════════════════════════════════════════════════

RENAME TABLE `sifre_sifirlama` TO `password_resets`;

ALTER TABLE `password_resets`
    CHANGE `kullanici_id`     `user_id`    int(11)      NOT NULL,
    CHANGE `son_kullanma`     `expires_at` datetime     NOT NULL,
    CHANGE `kullanildi`       `is_used`    tinyint(1)   NOT NULL DEFAULT 0,
    CHANGE `olusturma_tarihi` `created_at` datetime     NOT NULL DEFAULT current_timestamp();

ALTER TABLE `password_resets`
    ADD CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- ═══════════════════════════════════════════════════════════════
-- 11. mail_queue — sadece hata_mesaji → error_message
-- ═══════════════════════════════════════════════════════════════

ALTER TABLE `mail_queue`
    CHANGE `hata_mesaji` `error_message` text DEFAULT NULL;

-- ═══════════════════════════════════════════════════════════════
-- 12. sistem_migrations → migrations
-- ═══════════════════════════════════════════════════════════════

RENAME TABLE `sistem_migrations` TO `migrations`;

ALTER TABLE `migrations`
    CHANGE `versiyon`        `version`    varchar(20) NOT NULL,
    CHANGE `uygulandi_tarih` `applied_at` datetime    NOT NULL DEFAULT current_timestamp();

-- ═══════════════════════════════════════════════════════════════
-- 13. Migration kaydını ekle
-- ═══════════════════════════════════════════════════════════════

SET foreign_key_checks = 1;