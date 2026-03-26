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

// =====================================================
// LOG SİSTEMİ — Ana ve Lite için ortak
// =====================================================
// mail.php henüz yüklü değilse yükle
function logYaz($pdo, $aksiyon, $modul, $aciklama, $kayit_id = null, $eski = null, $yeni = null, $sistem = 'ana') {
    $ku = mevcutKullanici();
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '?';
    // Birden fazla IP varsa ilkini al
    $ip = trim(explode(',', $ip)[0]);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO sistem_loglari
                (kullanici_id, kullanici_adi, ad_soyad, sistem, aksiyon, modul, kayit_id, aciklama, eski_deger, yeni_deger, ip_adresi)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $ku['id'],
            $ku['adi'],
            $ku['ad_soyad'],
            $sistem,
            $aksiyon,
            $modul,
            $kayit_id,
            $aciklama,
            $eski ? json_encode($eski, JSON_UNESCAPED_UNICODE) : null,
            $yeni  ? json_encode($yeni,  JSON_UNESCAPED_UNICODE) : null,
            $ip,
        ]);
    } catch (Exception $e) {
        error_log('Log yazma hatası: ' . $e->getMessage());
    }

    // Admin bildirimlerini tetikle
    static $mail_yuklendi = false;
    if (!$mail_yuklendi) {
        $mail_path = __DIR__ . '/mail.php';
        if (file_exists($mail_path)) {
            require_once $mail_path;
            $mail_yuklendi = true;
        }
    }
    if (function_exists('adminBildirimGonder')) {
        adminBildirimGonder($pdo, $aksiyon, $modul, $aciklama, $ku);
    }
}

// Giriş/çıkış logları için oturum olmadan da çalışır
function logGiris($pdo, $kullanici, $sistem = 'ana') {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '?';
    $ip = trim(explode(',', $ip)[0]);
    try {
        $pdo->prepare("
            INSERT INTO sistem_loglari (kullanici_id, kullanici_adi, ad_soyad, sistem, aksiyon, modul, aciklama, ip_adresi)
            VALUES (?, ?, ?, ?, 'giris', 'auth', 'Sisteme giriş yapıldı', ?)
        ")->execute([$kullanici['id'], $kullanici['kullanici_adi'], $kullanici['ad_soyad'], $sistem, $ip]);
    } catch (Exception $e) { error_log('Log hatası: ' . $e->getMessage()); }
}

function logCikis($pdo, $sistem = 'ana') {
    $ku = mevcutKullanici();
    if (!$ku['id']) return;
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '?';
    $ip = trim(explode(',', $ip)[0]);
    try {
        $pdo->prepare("
            INSERT INTO sistem_loglari (kullanici_id, kullanici_adi, ad_soyad, sistem, aksiyon, modul, aciklama, ip_adresi)
            VALUES (?, ?, ?, ?, 'cikis', 'auth', 'Sistemden çıkış yapıldı', ?)
        ")->execute([$ku['id'], $ku['adi'], $ku['ad_soyad'], $sistem, $ip]);
    } catch (Exception $e) { error_log('Log hatası: ' . $e->getMessage()); }
}
