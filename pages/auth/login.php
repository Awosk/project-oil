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

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
if (function_exists('smtpAktifMi') === false && file_exists(__DIR__ . '/../../includes/mail.php')) {
    require_once __DIR__ . '/../../includes/mail.php';
}


if (girisYapildi()) { header('Location: ' . ROOT_URL . 'index.php'); exit; }

// Oturum sonlandırma bildirimi
$oturum_mesaj = '';
if (isset($_GET['oturum']) && $_GET['oturum'] === 'sonlandi') {
    $oturum_mesaj = 'Hesabınız devre dışı bırakıldığı için oturumunuz sonlandırıldı.';
}

$hata = '';
$ip   = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')[0]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Brute force kontrolü
    $engel_sure = bfEngelliMi($ip);
    if ($engel_sure > 0) {
        $dk  = ceil($engel_sure / 60);
        $hata = "Çok fazla başarısız giriş denemesi. Lütfen {$dk} dakika bekleyin.";
    } else {
        $kadi  = trim($_POST['kullanici_adi'] ?? '');
        $sifre = $_POST['sifre'] ?? '';

        if ($kadi && $sifre) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username=? AND is_active=1");
            $stmt->execute([$kadi]);
            $k = $stmt->fetch();

            if ($k && password_verify($sifre, $k['password'])) {
                // Başarılı giriş — sayacı sıfırla, session güvenli yenile
                bfSifirla($ip);
                session_regenerate_id(true);

                $_SESSION['kullanici_id']   = $k['id'];
                $_SESSION['kullanici_adi']  = $k['username'];
                $_SESSION['ad_soyad']       = $k['full_name'];
                $_SESSION['kullanici_rol']  = $k['role'];
                $_SESSION['kullanici_tema'] = $k['theme'] ?? 'light';
                $_SESSION['son_aktivite']   = time();
                $_SESSION['aktif_kontrol_zaman'] = time(); // İlk kontrolü şimdi yaptı say

                require_once __DIR__ . '/../../includes/log.php';
                logGiris($pdo, $k, 'lite');
                header('Location: ' . ROOT_URL . 'index.php'); exit;
            } else {
                bfDenemeEkle($ip);
                $kalan_hak = BF_LIMIT - ($_SESSION['bf_' . md5($ip)]['sayi'] ?? 0);
                if ($kalan_hak > 0) {
                    $hata = "Kullanıcı adı veya şifre hatalı. ({$kalan_hak} deneme hakkınız kaldı)";
                } else {
                    $dk   = ceil(BF_SURE / 60);
                    $hata = "Çok fazla başarısız giriş denemesi. Lütfen {$dk} dakika bekleyin.";
                }
            }
        } else {
            $hata = 'Lütfen tüm alanları doldurun.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1e4d6b">
    <title>Giriş | <?= SITE_ADI ?></title>
    <link rel="stylesheet" href="<?= ROOT_URL ?>assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-box">
        <div class="login-logo">
            <div class="logo-icon">🔩</div>
            <h2><?= SITE_ADI ?></h2>
            <p>Araç/Tesis Yağ Takip Sistemi</p>
        </div>
        <?php if ($oturum_mesaj): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($oturum_mesaj) ?></div>
        <?php endif; ?>
        <?php if ($hata): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($hata) ?></div>
        <?php endif; ?>
        <form method="post">
            <?= csrfInput() ?>
            <div class="form-group">
                <label>Kullanıcı Adı</label>
                <input type="text" name="kullanici_adi" autofocus required placeholder="kullanıcı adı"
                       value="<?= htmlspecialchars($_POST['kullanici_adi'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Şifre</label>
                <input type="password" name="sifre" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn btn-primary">🔐 Giriş Yap</button>
        </form>
        <div style="text-align:center;margin-top:14px;">
            <a href="<?= ROOT_URL ?>pages/auth/forgot_password.php" style="font-size:13px;color:var(--muted);">🔑 Şifremi unuttum</a>
        </div>
    </div>
</div>
</body>
</html>
