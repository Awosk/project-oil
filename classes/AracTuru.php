<?php
/*
 * Project Oil - Vehicle and Facility Industrial Oil Tracking System
 * Copyright (C) 2026 Awosk
 */

class AracTuru {
    /**
     * Tüm aktif araç türlerini getirir.
     *
     * @param PDO $pdo
     * @return array
     */
    public static function tumTurler($pdo) {
        return $pdo->query("SELECT id, tur_adi FROM vehicles_type WHERE aktif=1 ORDER BY tur_adi")->fetchAll();
    }

    /**
     * ID'ye göre tür adını getirir
     * 
     * @param PDO $pdo
     * @param int $id
     * @return string|false
     */
    public static function getAdById($pdo, $id) {
        $stmt = $pdo->prepare("SELECT tur_adi FROM vehicles_type WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn();
    }
    
    public static function bulAd($pdo, $tur_adi) {
        $stmt = $pdo->prepare("SELECT * FROM vehicles_type WHERE tur_adi = ?");
        $stmt->execute([$tur_adi]);
        return $stmt->fetch();
    }
    
    public static function bulId($pdo, $id) {
        $stmt = $pdo->prepare('SELECT * FROM vehicles_type WHERE id=?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public static function listeleDetayli($pdo) {
        return $pdo->query("
            SELECT t.*, COUNT(a.id) AS arac_sayisi
            FROM vehicles_type t
            LEFT JOIN vehicles a ON a.arac_turu_id = t.id AND a.aktif = 1
            WHERE t.aktif = 1
            GROUP BY t.id
            ORDER BY t.oncelik DESC, t.tur_adi
        ")->fetchAll();
    }
    
    public static function ekle($pdo, $tur_adi, $oncelik) {
        $pdo->prepare("INSERT INTO vehicles_type (tur_adi, oncelik) VALUES (?, ?)")
            ->execute([$tur_adi, $oncelik]);
        return $pdo->lastInsertId();
    }
    
    public static function reaktifEt($pdo, $id, $oncelik) {
        return $pdo->prepare("UPDATE vehicles_type SET aktif=1, oncelik=? WHERE id=?")
            ->execute([$oncelik, $id]);
    }
    
    public static function adCakismaVarMi($pdo, $tur_adi, $id) {
        $stmt = $pdo->prepare("SELECT id FROM vehicles_type WHERE tur_adi=? AND id!=? AND aktif=1");
        $stmt->execute([$tur_adi, $id]);
        return $stmt->fetch() ? true : false;
    }
    
    public static function guncelle($pdo, $id, $tur_adi, $oncelik) {
        return $pdo->prepare("UPDATE vehicles_type SET tur_adi=?, oncelik=? WHERE id=?")
            ->execute([$tur_adi, $oncelik, $id]);
    }
    
    public static function kullanimSayisi($pdo, $id) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM vehicles WHERE arac_turu_id=? AND aktif=1");
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn();
    }
    
    public static function sil($pdo, $id) {
        return $pdo->prepare("UPDATE vehicles_type SET aktif=0 WHERE id=?")->execute([$id]);
    }
}
