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

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 3600,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.gc_maxlifetime', 3600);
    session_start();

    if (isset($_SESSION['kullanici_id'])) {
        $son_aktivite = $_SESSION['son_aktivite'] ?? time();
        if (time() - $son_aktivite > 3600) {
            session_destroy();
            session_start();
        } else {
            $_SESSION['son_aktivite'] = time();
        }
    }
}

if (!defined('ROOT_URL')) {
    $proto = 'http';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $proto = 'https';
    } elseif (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
        $proto = 'https';
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $root_path = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $doc_root  = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));

    if ($doc_root && str_starts_with($root_path, $doc_root)) {
        $base = substr($root_path, strlen($doc_root));
    } else {
        $base = '';
    }

    $base = '/' . ltrim($base, '/');
    $base = rtrim($base, '/');

    define('ROOT_URL', $proto . '://' . $host . $base . '/');
}

// =====================================================
// CSRF KORUМASI
// =====================================================

/**
 * Mevcut CSRF token'ı döner, yoksa yeni oluşturur.
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Formlar için gizli CSRF input alanı HTML'i döner.
 */
function csrfInput(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

/**
 * POST isteğindeki CSRF token'ı doğrular.
 * Başarısız olursa 403 döner ve uyarı gösterir.
 */
function csrfDogrula(): void {
    $gelen = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrfToken(), $gelen)) {
        http_response_code(403);
        // Token'ı yenile (olası replay saldırısına karşı)
        unset($_SESSION['csrf_token']);
        die('<div style="font-family:sans-serif;padding:40px;color:#c0392b">
            <h2>⛔ Güvenlik Hatası</h2>
            <p>Geçersiz veya süresi dolmuş form token. Lütfen sayfayı yenileyip tekrar deneyin.</p>
            <a href="javascript:history.back()">← Geri Dön</a>
        </div>');
    }
}

// =====================================================
// SİLİNMİŞ / PASİF KULLANICI KONTROLÜ
// =====================================================

/**
 * Oturumda kayıtlı kullanıcının veritabanında hâlâ aktif olup olmadığını kontrol eder.
 * Admin hesabı silerse veya pasife alırsa oturum otomatik sonlandırılır.
 * Performans için her 60 saniyede bir kontrol edilir.
 */
function aktifKullaniciKontrol($pdo): void {
    if (!isset($_SESSION['kullanici_id'])) return;

    $son_kontrol = $_SESSION['aktif_kontrol_zaman'] ?? 0;
    if (time() - $son_kontrol < 20) return; // 60 saniyede bir kontrol

    $_SESSION['aktif_kontrol_zaman'] = time();

    $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['kullanici_id']]);
    $sonuc = $stmt->fetchColumn();

    if ($sonuc === false || (int)$sonuc !== 1) {
        // Kullanıcı silinmiş veya pasife alınmış
        session_destroy();
        session_start();
        header('Location: ' . ROOT_URL . 'pages/auth/login.php?oturum=sonlandi');
        exit;
    }
}

// =====================================================
// BRUTE FORCE KORUМASI
// =====================================================

define('BF_LIMIT',    5);   // Maksimum başarısız deneme
define('BF_SURE',    900);  // Engel süresi: 15 dakika (saniye)

/**
 * Belirli bir anahtar (IP) için başarısız deneme kaydeder.
 */
function bfDenemeEkle(string $ip): void {
    $key = 'bf_' . md5($ip);
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['sayi' => 0, 'ilk' => time()];
    }
    // Süre dolmuşsa sıfırla
    if (time() - $_SESSION[$key]['ilk'] > BF_SURE) {
        $_SESSION[$key] = ['sayi' => 0, 'ilk' => time()];
    }
    $_SESSION[$key]['sayi']++;
    $_SESSION[$key]['son']  = time();
}

/**
 * IP'nin engellenip engellenmediğini kontrol eder.
 * Engelliyse kalan süreyi (saniye) döner, değilse 0.
 */
function bfEngelliMi(string $ip): int {
    $key = 'bf_' . md5($ip);
    if (!isset($_SESSION[$key])) return 0;

    $veri = $_SESSION[$key];
    // Süre geçmişse engel kalktı
    if (time() - $veri['ilk'] > BF_SURE) {
        unset($_SESSION[$key]);
        return 0;
    }
    if ($veri['sayi'] >= BF_LIMIT) {
        $kalan = BF_SURE - (time() - $veri['ilk']);
        return max(0, $kalan);
    }
    return 0;
}

/**
 * Başarılı girişte sayacı sıfırlar.
 */
function bfSifirla(string $ip): void {
    $key = 'bf_' . md5($ip);
    unset($_SESSION[$key]);
}

// =====================================================
// MEVCUT FONKSİYONLAR
// =====================================================

function girisKontrol() {
    if (!isset($_SESSION['kullanici_id'])) {
        header('Location: ' . ROOT_URL . 'pages/auth/login.php');
        exit;
    }
}

function adminKontrol() {
    girisKontrol();
    if ($_SESSION['kullanici_rol'] !== 'admin') {
        header('Location: ' . ROOT_URL . 'index.php?hata=yetki');
        exit;
    }
}

function girisYapildi()   { return isset($_SESSION['kullanici_id']); }
function mevcutTema()     { return $_SESSION['kullanici_tema'] ?? 'light'; }
function isAdmin()         { return ($_SESSION['kullanici_rol'] ?? '') === 'admin'; }
function mevcutKullanici() {
    return [
        'id'       => $_SESSION['kullanici_id']  ?? null,
        'adi'      => $_SESSION['kullanici_adi']  ?? '',
        'ad_soyad' => $_SESSION['ad_soyad']       ?? '',
        'rol'      => $_SESSION['kullanici_rol']  ?? 'kullanici',
        'tema'     => $_SESSION['kullanici_tema'] ?? 'light',
    ];
}

function flash($mesaj, $tur = 'success') {
    $_SESSION['flash'] = ['mesaj' => $mesaj, 'tur' => $tur];
}
function getFlash() {
    if (isset($_SESSION['flash'])) { $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f; }
    return null;
}

function formatliTarih($t) {
    if (!$t) return '-';
    if (strlen($t) > 10) return date('d.m.Y H:i', strtotime($t));
    return date('d.m.Y', strtotime($t));
}
function formatliMiktar($m) {
    return number_format($m, 2, ',', '.') . ' L';
}
