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
require_once __DIR__ . '/../../includes/mail.php';

if (girisYapildi()) { header('Location: ' . ROOT_URL . 'index.php'); exit; }

$mesaj      = '';
$mesaj_tur  = '';
$gonderildi = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfDogrula();
    $email = trim($_POST['email'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mesaj     = 'Geçerli bir e-posta adresi girin.';
        $mesaj_tur = 'danger';
    } elseif (!smtpAktifMi($pdo)) {
        $mesaj     = 'E-posta servisi aktif değil. Lütfen yöneticinizle iletişime geçin.';
        $mesaj_tur = 'danger';
    } else {
        $kullanici = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $kullanici->execute([$email]);
        $kullanici = $kullanici->fetch();

        // Güvenlik: kullanıcı bulunsun ya da bulunmasın aynı mesajı göster
        if ($kullanici) {
            // Eski tokenları iptal et
            $pdo->prepare("UPDATE password_resets SET is_used = 1 WHERE user_id = ? AND is_used = 0")
                ->execute([$kullanici['id']]);

            $token       = bin2hex(random_bytes(32));
            $son_kullanma = date('Y-m-d H:i:s', strtotime('+30 minutes'));

            $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)")
                ->execute([$kullanici['id'], $token, $son_kullanma]);

            sifreSifirlamaMailiGonder($pdo, $kullanici, $token);
        }

        $gonderildi = true;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1e4d6b">
    <title>Şifremi Unuttum | <?= SITE_ADI ?></title>
    <link rel="stylesheet" href="<?= ROOT_URL ?>assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-box">
        <div class="login-logo">
            <div class="logo-icon">🔩</div>
            <h2><?= SITE_ADI ?></h2>
            <p>Şifre Sıfırlama</p>
        </div>

        <?php if ($gonderildi): ?>
        <div class="alert alert-success">
            ✅ Eğer bu e-posta adresiyle kayıtlı bir hesap varsa, şifre sıfırlama bağlantısı gönderildi. Gelen kutunuzu kontrol edin.
        </div>
        <a href="<?= ROOT_URL ?>pages/auth/login.php" class="btn btn-secondary" style="width:100%;margin-top:8px;">← Giriş Sayfasına Dön</a>

        <?php else: ?>

        <?php if ($mesaj): ?>
        <div class="alert alert-<?= $mesaj_tur ?>"><?= htmlspecialchars($mesaj) ?></div>
        <?php endif; ?>

        <p style="font-size:13px;color:var(--muted);margin-bottom:20px;">
            Hesabınıza kayıtlı e-posta adresinizi girin. Şifre sıfırlama bağlantısı göndereceğiz.
        </p>

        <form method="post">
            <?= csrfInput() ?>
            <div class="form-group">
                <label>E-posta Adresi</label>
                <input type="email" name="email" required autofocus placeholder="ornek@eposta.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-primary">📧 Sıfırlama Bağlantısı Gönder</button>
        </form>

        <div style="text-align:center;margin-top:16px;">
            <a href="<?= ROOT_URL ?>pages/auth/login.php" style="font-size:13px;color:var(--muted);">← Giriş sayfasına dön</a>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
