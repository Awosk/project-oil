<?php
/*
 * Project Oil - Vehicle and Facility Industrial Oil Tracking System
 * Copyright (C) 2026 Awosk
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

class Arac {
    /**
     * Tüm aktif araçları, türleri, kayıt sayıları ve son işlem tarihleriyle birlikte getirir.
     *
     * @param PDO $pdo
     * @return array
     */
    public static function tumAraclar($pdo) {
        return $pdo->query("
            SELECT a.*,
                   t.tur_adi,
                   t.oncelik,
                   k.ad_soyad AS olusturan_adi,
                   COUNT(CASE WHEN lk.aktif = 1 THEN 1 END) AS kayit_sayisi,
                   MAX(CASE WHEN lk.aktif = 1 THEN lk.tarih END) AS son_kayit
            FROM vehicles a
            LEFT JOIN vehicles_type t  ON a.arac_turu_id = t.id
            LEFT JOIN users k       ON a.olusturan_id = k.id
            LEFT JOIN records lk     ON lk.arac_id = a.id
            WHERE a.aktif = 1
            GROUP BY a.id
            ORDER BY t.oncelik DESC, t.tur_adi, a.plaka
        ")->fetchAll();
    }

    /**
     * Araç Yönetimi sayfası için liste (gelişmiş).
     *
     * @param PDO $pdo
     * @return array
     */
    public static function listele($pdo) {
        return $pdo->query("
            SELECT a.*, t.tur_adi, k.ad_soyad
            FROM vehicles a
            LEFT JOIN vehicles_type t ON a.arac_turu_id = t.id
            LEFT JOIN users k ON a.olusturan_id = k.id
            WHERE a.aktif = 1
            ORDER BY t.tur_adi, a.plaka
        ")->fetchAll();
    }

    public static function bulPlaka($pdo, $plaka) {
        $stmt = $pdo->prepare("SELECT * FROM vehicles WHERE plaka=?");
        $stmt->execute([$plaka]);
        return $stmt->fetch();
    }

    public static function bulId($pdo, $id) {
        $stmt = $pdo->prepare('SELECT * FROM vehicles WHERE id=?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function detayliBulId($pdo, $id) {
        $stmt = $pdo->prepare('SELECT a.*, t.tur_adi FROM vehicles a LEFT JOIN vehicles_type t ON a.arac_turu_id=t.id WHERE a.id=?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function aktifDetayliBulId($pdo, $id) {
        $stmt = $pdo->prepare("
            SELECT a.*, t.tur_adi
            FROM vehicles a
            LEFT JOIN vehicles_type t ON a.arac_turu_id = t.id
            WHERE a.id=? AND a.aktif=1
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function plakaCakismaVarMi($pdo, $plaka, $id) {
        $stmt = $pdo->prepare("SELECT id FROM vehicles WHERE plaka=? AND id!=?");
        $stmt->execute([$plaka, $id]);
        return $stmt->fetch() ? true : false;
    }

    public static function ekle($pdo, $tur_id, $plaka, $model, $olusturan_id) {
        $pdo->prepare("INSERT INTO vehicles (arac_turu_id, plaka, marka_model, olusturan_id) VALUES (?,?,?,?)")
            ->execute([$tur_id, $plaka, $model, $olusturan_id]);
        return $pdo->lastInsertId();
    }

    public static function reaktifEt($pdo, $id, $tur_id, $model, $olusturan_id) {
        return $pdo->prepare("UPDATE vehicles SET arac_turu_id=?, marka_model=?, olusturan_id=?, aktif=1 WHERE id=?")
            ->execute([$tur_id, $model, $olusturan_id, $id]);
    }

    public static function guncelle($pdo, $id, $tur_id, $plaka, $model) {
        return $pdo->prepare("UPDATE vehicles SET arac_turu_id=?, plaka=?, marka_model=? WHERE id=?")
            ->execute([$tur_id, $plaka, $model, $id]);
    }

    public static function sil($pdo, $id) {
        return $pdo->prepare("UPDATE vehicles SET aktif=0 WHERE id=?")->execute([$id]);
    }
}
