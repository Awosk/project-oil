<?php
/*
 * Project Oil - Vehicle and Facility Industrial Oil Tracking System
 * Copyright (C) 2026 Awosk
 */

class Urun {
    public static function tumUrunler($pdo) {
        return $pdo->query("SELECT * FROM products WHERE aktif=1 ORDER BY urun_adi")->fetchAll();
    }

    public static function bulId($pdo, $id) {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id=?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function listeleDetayli($pdo) {
        return $pdo->query("SELECT u.*, k.ad_soyad FROM products u LEFT JOIN users k ON u.olusturan_id=k.id WHERE u.aktif=1 ORDER BY u.urun_adi")->fetchAll();
    }

    public static function bulKod($pdo, $kod) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE urun_kodu=?");
        $stmt->execute([$kod]);
        return $stmt->fetch();
    }

    public static function kodCakismaVarMi($pdo, $kod, $id) {
        $stmt = $pdo->prepare("SELECT id FROM products WHERE urun_kodu=? AND id!=?");
        $stmt->execute([$kod, $id]);
        return $stmt->fetch() ? true : false;
    }

    public static function ekle($pdo, $kod, $adi, $olusturan_id) {
        $pdo->prepare("INSERT INTO products (urun_kodu, urun_adi, olusturan_id) VALUES (?,?,?)")
            ->execute([$kod, $adi, $olusturan_id]);
        return $pdo->lastInsertId();
    }

    public static function reaktifEt($pdo, $id, $adi, $olusturan_id) {
        return $pdo->prepare("UPDATE products SET urun_adi=?, olusturan_id=?, aktif=1 WHERE id=?")
            ->execute([$adi, $olusturan_id, $id]);
    }

    public static function guncelle($pdo, $id, $kod, $adi) {
        return $pdo->prepare("UPDATE products SET urun_kodu=?, urun_adi=? WHERE id=?")
            ->execute([$kod, $adi, $id]);
    }

    public static function sil($pdo, $id) {
        return $pdo->prepare("UPDATE products SET aktif=0 WHERE id=?")->execute([$id]);
    }
}
