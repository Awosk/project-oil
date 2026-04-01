<?php
/*
 * Project Oil - Vehicle and Facility Industrial Oil Tracking System
 * Copyright (C) 2026 Awosk
 */

class SistemLog {
    
    public static function sil($pdo, $id) {
        return $pdo->prepare("DELETE FROM system_logs WHERE id=?")->execute([$id]);
    }

    public static function tabloyuSifirla($pdo) {
        $pdo->exec("DROP TABLE IF EXISTS `system_logs`");
        $pdo->exec("CREATE TABLE `system_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `kullanici_id` int(11) DEFAULT NULL,
            `kullanici_adi` varchar(50) DEFAULT NULL,
            `ad_soyad` varchar(100) DEFAULT NULL,
            `sistem` enum('ana','lite') NOT NULL DEFAULT 'ana',
            `aksiyon` enum('ekle','guncelle','sil','giris','cikis') NOT NULL,
            `modul` varchar(50) NOT NULL,
            `kayit_id` int(11) DEFAULT NULL,
            `aciklama` text NOT NULL,
            `eski_deger` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`eski_deger`)),
            `yeni_deger` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`yeni_deger`)),
            `ip_adresi` varchar(45) DEFAULT NULL,
            `olusturma_tarihi` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `kullanici_id` (`kullanici_id`),
            CONSTRAINT `sistem_loglari_ibfk_1` FOREIGN KEY (`kullanici_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");
        return true;
    }

    public static function tariheKadarSil($pdo, $tarih) {
        $stmt = $pdo->prepare("DELETE FROM system_logs WHERE sistem='lite' AND DATE(olusturma_tarihi) <= ?");
        $stmt->execute([$tarih]);
        return $stmt->rowCount();
    }

    public static function seciliSil($pdo, $ids) {
        if (empty($ids)) return 0;
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM system_logs WHERE id IN ($ph) AND sistem='lite'");
        $stmt->execute($ids);
        return $stmt->rowCount();
    }

    public static function listele($pdo, $where_sql, $params, $limit = 1000) {
        $stmt = $pdo->prepare("SELECT * FROM system_logs WHERE $where_sql ORDER BY olusturma_tarihi DESC LIMIT " . (int)$limit);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function istatistikGetir($pdo) {
        return [
            'bugun'      => $pdo->query("SELECT COUNT(*) FROM system_logs WHERE sistem='lite' AND DATE(olusturma_tarihi)=CURDATE()")->fetchColumn(),
            'bugun_sil'  => $pdo->query("SELECT COUNT(*) FROM system_logs WHERE sistem='lite' AND aksiyon='sil' AND DATE(olusturma_tarihi)=CURDATE()")->fetchColumn(),
            'bugun_ekle' => $pdo->query("SELECT COUNT(*) FROM system_logs WHERE sistem='lite' AND aksiyon='ekle' AND DATE(olusturma_tarihi)=CURDATE()")->fetchColumn(),
            'toplam'     => $pdo->query("SELECT COUNT(*) FROM system_logs WHERE sistem='lite'")->fetchColumn()
        ];
    }

    public static function kayitHedefMapOlustur($pdo, $kayit_id_listesi) {
        $harita = [];
        if (!empty($kayit_id_listesi)) {
            $ph = implode(',', array_fill(0, count($kayit_id_listesi), '?'));
            $stmt = $pdo->prepare("SELECT id, kayit_turu, arac_id, tesis_id FROM records WHERE id IN ($ph)");
            $stmt->execute(array_values($kayit_id_listesi));
            foreach ($stmt->fetchAll() as $row) {
                $harita[$row['id']] = [
                    'turu'     => $row['kayit_turu'],
                    'hedef_id' => $row['kayit_turu'] === 'arac' ? $row['arac_id'] : $row['tesis_id'],
                ];
            }
        }
        return $harita;
    }
}
