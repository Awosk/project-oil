<?php
/*
 * Project Oil - Vehicle and Facility Industrial Oil Tracking System
 * Copyright (C) 2026 Awosk
 */

class Tesis {
    public static function listele($pdo) {
        return $pdo->query("
            SELECT t.*,
                   COUNT(CASE WHEN lk.aktif = 1 THEN 1 END) AS kayit_sayisi,
                   MAX(CASE WHEN lk.aktif = 1 THEN lk.tarih END) AS son_kayit
            FROM facilities t
            LEFT JOIN records lk ON lk.tesis_id = t.id
            WHERE t.aktif = 1
            GROUP BY t.id
            ORDER BY t.firma_adi
        ")->fetchAll();
    }

    public static function listeleYonetim($pdo) {
        return $pdo->query("
            SELECT t.*, k.ad_soyad 
            FROM facilities t 
            LEFT JOIN users k ON t.olusturan_id=k.id 
            WHERE t.aktif=1 
            ORDER BY t.firma_adi
        ")->fetchAll();
    }

    public static function tumTesislerIdAd($pdo) {
        return $pdo->query("SELECT id, firma_adi FROM facilities WHERE aktif=1 ORDER BY firma_adi")->fetchAll();
    }

    public static function bulId($pdo, $id) {
        $stmt = $pdo->prepare('SELECT * FROM facilities WHERE id=?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function aktifBulId($pdo, $id) {
        $stmt = $pdo->prepare('SELECT * FROM facilities WHERE id=? AND aktif=1');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function bulAd($pdo, $firma_adi) {
        $stmt = $pdo->prepare("SELECT * FROM facilities WHERE firma_adi=?");
        $stmt->execute([$firma_adi]);
        return $stmt->fetch();
    }

    public static function adCakismaVarMi($pdo, $firma_adi, $id) {
        $stmt = $pdo->prepare("SELECT id FROM facilities WHERE firma_adi=? AND id!=?");
        $stmt->execute([$firma_adi, $id]);
        return $stmt->fetch() ? true : false;
    }

    public static function ekle($pdo, $firma_adi, $firma_adresi, $olusturan_id) {
        $pdo->prepare("INSERT INTO facilities (firma_adi, firma_adresi, olusturan_id) VALUES (?,?,?)")
            ->execute([$firma_adi, $firma_adresi, $olusturan_id]);
        return $pdo->lastInsertId();
    }

    public static function reaktifEt($pdo, $id, $firma_adresi, $olusturan_id) {
        return $pdo->prepare("UPDATE facilities SET firma_adresi=?, olusturan_id=?, aktif=1 WHERE id=?")
            ->execute([$firma_adresi, $olusturan_id, $id]);
    }

    public static function guncelle($pdo, $id, $firma_adi, $firma_adresi) {
        return $pdo->prepare("UPDATE facilities SET firma_adi=?, firma_adresi=? WHERE id=?")
            ->execute([$firma_adi, $firma_adresi, $id]);
    }

    public static function sil($pdo, $id) {
        return $pdo->prepare("UPDATE facilities SET aktif=0 WHERE id=?")->execute([$id]);
    }
}
