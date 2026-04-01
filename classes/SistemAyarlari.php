<?php
/*
 * Project Oil - Vehicle and Facility Industrial Oil Tracking System
 * Copyright (C) 2026 Awosk
 */

class SistemAyarlari {

    public static function ayarKaydet($pdo, $anahtar, $deger) {
        return $pdo->prepare("INSERT INTO system_settings (anahtar, deger) VALUES (?, ?) ON DUPLICATE KEY UPDATE deger = VALUES(deger)")
            ->execute([$anahtar, $deger]);
    }

    public static function cooldownIptal($pdo) {
        $pdo->prepare("UPDATE system_settings SET deger = '' WHERE anahtar = 'mail_cooldown_bitis'")->execute();
        $pdo->exec("UPDATE mail_queue SET status = 'cancelled' WHERE status = 'paused'");
    }

    public static function adminBildirimGuncelle($pdo, $hedef_id, $mail_aktif, $filtreler) {
        $pdo->prepare("UPDATE users SET mail_bildirim_aktif = ? WHERE id = ?")->execute([$mail_aktif, $hedef_id]);
        $pdo->prepare("DELETE FROM users_notifications WHERE kullanici_id = ?")->execute([$hedef_id]);
        
        if (!empty($filtreler)) {
            $stmt = $pdo->prepare("INSERT INTO users_notifications (kullanici_id, aktif, modul, aksiyon) VALUES (?, 1, ?, ?)");
            foreach ($filtreler as $filtre) {
                [$modul, $aksiyon] = explode('|', $filtre);
                $stmt->execute([$hedef_id, $modul, $aksiyon]);
            }
        }
    }

    public static function getAdminKullanicilar($pdo) {
        return $pdo->query("SELECT id, ad_soyad, kullanici_adi, email, mail_bildirim_aktif FROM users WHERE rol = 'admin' AND aktif = 1 ORDER BY ad_soyad")->fetchAll();
    }

    public static function getMevcutFiltreler($pdo) {
        $mevcut_filtreler = [];
        $filtre_rows = $pdo->query("SELECT kullanici_id, modul, aksiyon FROM users_notifications WHERE aktif = 1")->fetchAll();
        foreach ($filtre_rows as $row) {
            $mevcut_filtreler[$row['kullanici_id']][$row['modul'] . '|' . $row['aksiyon']] = true;
        }
        return $mevcut_filtreler;
    }
}
