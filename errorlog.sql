-- Project Oil — Migration 2.0.0
-- Veritabanı şemasını Türkçe'den İngilizce'ye dönüştürür.
-- Tüm veriler korunur; sadece tablo ve sütun isimleri değişir.
-- MariaDB 10.5+ / MySQL 8.0+ gerektirir (RENAME COLUMN desteği).

SET NAMES utf8mb4;
SET foreign_key_checks = 0;
-- ═══════════════════════════════════════════════════════════════
-- 1. kullanicilar → users
-- ═══════════════════════════════════════════════════════════════

-- Foreign key kısıtlamalarını kaldır (diğer tablolarda kullanicilar'a referans var)
ALTER TABLE `lite_araclar`   DROP FOREIGN KEY IF EXISTS `lite_araclar_ibfk_1`;
/* Bilgi: Records: 0  Duplicates: 0  Warnings: 0 */
ALTER TABLE `lite_tesisler`  DROP FOREIGN KEY IF EXISTS `lite_tesisler_ibfk_1`;
/* Bilgi: Records: 0  Duplicates: 0  Warnings: 0 */
ALTER TABLE `lite_urunler`   DROP FOREIGN KEY IF EXISTS `lite_urunler_ibfk_1`;
/* Bilgi: Records: 0  Duplicates: 0  Warnings: 0 */
ALTER TABLE `lite_kayitlar`  DROP FOREIGN KEY IF EXISTS `lite_kayitlar_ibfk_4`;
/* Bilgi: Records: 0  Duplicates: 0  Warnings: 0 */
ALTER TABLE `lite_kayitlar`  DROP FOREIGN KEY IF EXISTS `fk_islendi_kullanici`;
/* Bilgi: Records: 0  Duplicates: 0  Warnings: 0 */
ALTER TABLE `sistem_loglari` DROP FOREIGN KEY IF EXISTS `sistem_loglari_ibfk_1`;
/* Bilgi: Records: 0  Duplicates: 0  Warnings: 0 */
ALTER TABLE `admin_bildirim_filtreler` DROP FOREIGN KEY IF EXISTS `fk_bildirim_kullanici`;
/* Bilgi: Records: 0  Duplicates: 0  Warnings: 0 */
ALTER TABLE `sifre_sifirlama` DROP FOREIGN KEY IF EXISTS `fk_sifre_sifirlama_kullanici`;
/* Bilgi: Records: 0  Duplicates: 0  Warnings: 0 */
-- Tabloyu yeniden adlandır
RENAME TABLE `kullanicilar` TO `users`;
-- Sütunları yeniden adlandır
ALTER TABLE `users`
    RENAME COLUMN `ad_soyad`            TO `full_name`,
    RENAME COLUMN `kullanici_adi`        TO `username`,
    RENAME COLUMN `sifre`                TO `password`,
    RENAME COLUMN `rol`                  TO `role`,
    RENAME COLUMN `aktif`                TO `is_active`,
    RENAME COLUMN `tema`                 TO `theme`,
    RENAME COLUMN `olusturma_tarihi`     TO `created_at`,
    RENAME COLUMN `mail_bildirim_aktif`  TO `mail_notifications`;
/* SQL Hatası (1064): You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near 'COLUMN `ad_soyad`            TO `full_name`,
    RENAME COLUMN `kullanici_ad...' at line 3 */
-- Unique key adını güncelle
ALTER TABLE `users` DROP KEY IF EXISTS `kullanici_adi`;
/* Bilgi: Records: 0  Duplicates: 0  Warnings: 0 */
ALTER TABLE `users` ADD UNIQUE KEY `username` (`username`);
/* SQL Hatası (1072): Key column 'username' doesn't exist in table */
-- ═══════════════════════════════════════════════════════════════
-- 2. lite_arac_turleri → vehicle_types
-- ═══════════════════════════════════════════════════════════════

ALTER TABLE `lite_araclar` DROP FOREIGN KEY IF EXISTS `lite_araclar_ibfk_tur`;
/* Bilgi: Records: 0  Duplicates: 0  Warnings: 0 */
RENAME TABLE `lite_arac_turleri` TO `vehicle_types`;
ALTER TABLE `vehicle_types`
    RENAME COLUMN `tur_adi`          TO `type_name`,
    RENAME COLUMN `oncelik`          TO `priority`,
    RENAME COLUMN `aktif`            TO `is_active`,
    RENAME COLUMN `olusturma_tarihi` TO `created_at`;
/* SQL Hatası (1064): You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near 'COLUMN `tur_adi`          TO `type_name`,
    RENAME COLUMN `oncelik`       ...' at line 2 */
ALTER TABLE `vehicle_types` DROP KEY IF EXISTS `tur_adi`;
/* Bilgi: Records: 0  Duplicates: 0  Warnings: 0 */
ALTER TABLE `vehicle_types` ADD UNIQUE KEY `type_name` (`type_name`);
/* SQL Hatası (1072): Key column 'type_name' doesn't exist in table */
-- ═══════════════════════════════════════════════════════════════
-- 3. lite_araclar → vehicles
-- ═══════════════════════════════════════════════════════════════

RENAME TABLE `lite_araclar` TO `vehicles`;
ALTER TABLE `vehicles`
    RENAME COLUMN `arac_turu_id`     TO `vehicle_type_id`,
    RENAME COLUMN `plaka`            TO `plate`,
    RENAME COLUMN `marka_model`      TO `brand_model`,
    RENAME COLUMN `aktif`            TO `is_active`,
    RENAME COLUMN `olusturma_tarihi` TO `created_at`,
    RENAME COLUMN `olusturan_id`     TO `created_by`;
/* SQL Hatası (1064): You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near 'COLUMN `arac_turu_id`     TO `vehicle_type_id`,
    RENAME COLUMN `plaka`   ...' at line 2 */
ALTER TABLE `vehicles` DROP KEY IF EXISTS `plaka`;
/* Bilgi: Records: 0  Duplicates: 0  Warnings: 0 */
ALTER TABLE `vehicles` ADD UNIQUE KEY `plate` (`plate`);
/* SQL Hatası (1072): Key column 'plate' doesn't exist in table */
-- Foreign key'leri yeni isimlerle yeniden ekle
ALTER TABLE `vehicles`
    ADD CONSTRAINT `vehicles_ibfk_1`    FOREIGN KEY (`created_by`)      REFERENCES `users` (`id`),
    ADD CONSTRAINT `vehicles_ibfk_type` FOREIGN KEY (`vehicle_type_id`) REFERENCES `vehicle_types` (`id`);
/* SQL Hatası (1072): Key column 'created_by' doesn't exist in table */
-- ═══════════════════════════════════════════════════════════════
-- 4. lite_tesisler → facilities
-- ═══════════════════════════════════════════════════════════════

RENAME TABLE `lite_tesisler` TO `facilities`;
ALTER TABLE `facilities`
    RENAME COLUMN `firma_adi`        TO `name`,
    RENAME COLUMN `firma_adresi`     TO `address`,
    RENAME COLUMN `aktif`            TO `is_active`,
    RENAME COLUMN `olusturma_tarihi` TO `created_at`,
    RENAME COLUMN `olusturan_id`     TO `created_by`;
/* SQL Hatası (1064): You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near 'COLUMN `firma_adi`        TO `name`,
    RENAME COLUMN `firma_adresi`     TO...' at line 2 */
ALTER TABLE `facilities`
    ADD CONSTRAINT `facilities_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);
/* SQL Hatası (1072): Key column 'created_by' doesn't exist in table */
-- ═══════════════════════════════════════════════════════════════
-- 5. lite_urunler → products
-- ═══════════════════════════════════════════════════════════════

RENAME TABLE `lite_urunler` TO `products`;
ALTER TABLE `products`
    RENAME COLUMN `urun_kodu`        TO `product_code`,
    RENAME COLUMN `urun_adi`         TO `product_name`,
    RENAME COLUMN `aktif`            TO `is_active`,
    RENAME COLUMN `olusturma_tarihi` TO `created_at`,
    RENAME COLUMN `olusturan_id`     TO `created_by`;
/* SQL Hatası (1064): You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near 'COLUMN `urun_kodu`        TO `product_code`,
    RENAME COLUMN `urun_adi`   ...' at line 2 */
ALTER TABLE `products` DROP KEY IF EXISTS `urun_kodu`;
/* Bilgi: Records: 0  Duplicates: 0  Warnings: 0 */
ALTER TABLE `products` ADD UNIQUE KEY `product_code` (`product_code`);
/* SQL Hatası (1072): Key column 'product_code' doesn't exist in table */
ALTER TABLE `products`
    ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);
/* SQL Hatası (1072): Key column 'created_by' doesn't exist in table */
-- ═══════════════════════════════════════════════════════════════
-- 6. lite_kayitlar → oil_records
-- ═══════════════════════════════════════════════════════════════

RENAME TABLE `lite_kayitlar` TO `oil_records`;
ALTER TABLE `oil_records`
    RENAME COLUMN `kayit_turu`            TO `record_type`,
    RENAME COLUMN `arac_id`               TO `vehicle_id`,
    RENAME COLUMN `tesis_id`              TO `facility_id`,
    RENAME COLUMN `urun_id`               TO `product_id`,
    RENAME COLUMN `miktar`                TO `quantity`,
    RENAME COLUMN `tarih`                 TO `date`,
    RENAME COLUMN `aciklama`              TO `notes`,
    RENAME COLUMN `olusturma_tarihi`      TO `created_at`,
    RENAME COLUMN `olusturan_id`          TO `created_by`,
    RENAME COLUMN `aktif`                 TO `is_active`,
    RENAME COLUMN `yag_bakimi`            TO `is_oil_change`,
    RENAME COLUMN `mevcut_km`             TO `current_km`,
    RENAME COLUMN `islendi`               TO `is_processed`,
    RENAME COLUMN `islendi_tarih`         TO `processed_at`,
    RENAME COLUMN `islendi_kullanici_id`  TO `processed_by`;
/* SQL Hatası (1064): You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near 'COLUMN `kayit_turu`            TO `record_type`,
    RENAME COLUMN `arac_id`...' at line 2 */
ALTER TABLE `oil_records`
    ADD CONSTRAINT `fk_processed_by`   FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `oil_records_ibfk_1` FOREIGN KEY (`vehicle_id`)  REFERENCES `vehicles` (`id`),
    ADD CONSTRAINT `oil_records_ibfk_2` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`),
    ADD CONSTRAINT `oil_records_ibfk_3` FOREIGN KEY (`product_id`)  REFERENCES `products` (`id`),
    ADD CONSTRAINT `oil_records_ibfk_4` FOREIGN KEY (`created_by`)  REFERENCES `users` (`id`);
/* SQL Hatası (1072): Key column 'processed_by' doesn't exist in table */
-- ═══════════════════════════════════════════════════════════════
-- 7. sistem_loglari → system_logs
-- ═══════════════════════════════════════════════════════════════

RENAME TABLE `sistem_loglari` TO `system_logs`;
ALTER TABLE `system_logs`
    RENAME COLUMN `kullanici_id`     TO `user_id`,
    RENAME COLUMN `kullanici_adi`    TO `username`,
    RENAME COLUMN `ad_soyad`         TO `full_name`,
    RENAME COLUMN `aksiyon`          TO `action`,
    RENAME COLUMN `modul`            TO `module`,
    RENAME COLUMN `kayit_id`         TO `record_id`,
    RENAME COLUMN `aciklama`         TO `description`,
    RENAME COLUMN `eski_deger`       TO `old_value`,
    RENAME COLUMN `yeni_deger`       TO `new_value`,
    RENAME COLUMN `ip_adresi`        TO `ip_address`,
    RENAME COLUMN `olusturma_tarihi` TO `created_at`;
/* SQL Hatası (1064): You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near 'COLUMN `kullanici_id`     TO `user_id`,
    RENAME COLUMN `kullanici_adi`   ...' at line 2 */
ALTER TABLE `system_logs`
    ADD CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
/* SQL Hatası (1072): Key column 'user_id' doesn't exist in table */
-- ═══════════════════════════════════════════════════════════════
-- 8. sistem_ayarlar → system_settings
-- ═══════════════════════════════════════════════════════════════

RENAME TABLE `sistem_ayarlar` TO `system_settings`;
ALTER TABLE `system_settings`
    RENAME COLUMN `anahtar`          TO `key`,
    RENAME COLUMN `deger`            TO `value`,
    RENAME COLUMN `guncelleme_tarihi` TO `updated_at`;
/* SQL Hatası (1064): You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near 'COLUMN `anahtar`          TO `key`,
    RENAME COLUMN `deger`            TO ...' at line 2 */
-- ═══════════════════════════════════════════════════════════════
-- 9. admin_bildirim_filtreler → notification_filters
-- ═══════════════════════════════════════════════════════════════

RENAME TABLE `admin_bildirim_filtreler` TO `notification_filters`;
ALTER TABLE `notification_filters`
    RENAME COLUMN `kullanici_id`     TO `user_id`,
    RENAME COLUMN `aktif`            TO `is_active`,
    RENAME COLUMN `modul`            TO `module`,
    RENAME COLUMN `aksiyon`          TO `action`,
    RENAME COLUMN `olusturma_tarihi` TO `created_at`;
/* SQL Hatası (1064): You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near 'COLUMN `kullanici_id`     TO `user_id`,
    RENAME COLUMN `aktif`           ...' at line 2 */
ALTER TABLE `notification_filters` DROP KEY IF EXISTS `kullanici_modul_aksiyon`;
/* Bilgi: Records: 0  Duplicates: 0  Warnings: 0 */
ALTER TABLE `notification_filters` ADD UNIQUE KEY `user_module_action` (`user_id`, `module`, `action`);
/* SQL Hatası (1072): Key column 'user_id' doesn't exist in table */
ALTER TABLE `notification_filters`
    ADD CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
/* SQL Hatası (1072): Key column 'user_id' doesn't exist in table */
-- ═══════════════════════════════════════════════════════════════
-- 10. sifre_sifirlama → password_resets
-- ═══════════════════════════════════════════════════════════════

RENAME TABLE `sifre_sifirlama` TO `password_resets`;
ALTER TABLE `password_resets`
    RENAME COLUMN `kullanici_id`     TO `user_id`,
    RENAME COLUMN `son_kullanma`     TO `expires_at`,
    RENAME COLUMN `kullanildi`       TO `is_used`,
    RENAME COLUMN `olusturma_tarihi` TO `created_at`;
/* SQL Hatası (1064): You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near 'COLUMN `kullanici_id`     TO `user_id`,
    RENAME COLUMN `son_kullanma`    ...' at line 2 */
ALTER TABLE `password_resets`
    ADD CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
/* SQL Hatası (1072): Key column 'user_id' doesn't exist in table */
-- ═══════════════════════════════════════════════════════════════
-- 11. mail_queue — sadece hata_mesaji → error_message
-- ═══════════════════════════════════════════════════════════════

ALTER TABLE `mail_queue`
    RENAME COLUMN `hata_mesaji` TO `error_message`;
/* SQL Hatası (1064): You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near 'COLUMN `hata_mesaji` TO `error_message`' at line 6 */
-- ═══════════════════════════════════════════════════════════════
-- 12. sistem_migrations → migrations
-- ═══════════════════════════════════════════════════════════════

RENAME TABLE `sistem_migrations` TO `migrations`;
ALTER TABLE `migrations`
    RENAME COLUMN `versiyon`        TO `version`,
    RENAME COLUMN `uygulandi_tarih` TO `applied_at`;
/* SQL Hatası (1064): You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near 'COLUMN `versiyon`        TO `version`,
    RENAME COLUMN `uygulandi_tarih` T...' at line 2 */
-- ═══════════════════════════════════════════════════════════════
-- 13. Migration kaydını ekle
-- ═══════════════════════════════════════════════════════════════

SET foreign_key_checks = 1;
INSERT IGNORE INTO `migrations` (`version`) VALUES ('2.0.0');
/* SQL Hatası (1054): Unknown column 'version' in 'field list' */
/* Etkilenen satırlar: 0  Bulunan satırlar: 0  Uyarılar: 0  Süre  52 de 53 sorgular: 0,219 sn. */
SHOW DATABASES;
/* "localhost" oturumuna başlanıyor */
SELECT `DEFAULT_COLLATION_NAME` FROM `information_schema`.`SCHEMATA` WHERE `SCHEMA_NAME`='project_oil_test';
SHOW TABLE STATUS FROM `project_oil_test`;
SHOW FUNCTION STATUS WHERE `Db`='project_oil_test';
SHOW PROCEDURE STATUS WHERE `Db`='project_oil_test';
SHOW TRIGGERS FROM `project_oil_test`;
SELECT *, EVENT_SCHEMA AS `Db`, EVENT_NAME AS `Name` FROM information_schema.`EVENTS` WHERE `EVENT_SCHEMA`='project_oil_test';