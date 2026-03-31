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

if (girisYapildi()) { header('Location: ' . ROOT_URL . 'index.php'); exit; }

$token     = trim($_GET['token'] ?? '');
$mesaj     = '';
$mesaj_tur = '';
$gecerli   = false;
$tamamlandi = false;
$kullanici  = null;

if (!$token) {
    header('Location: ' . ROOT_URL . 'pages/auth/login.php');
    exit;
}

// Token kontrolü
$sifirlama = $pdo->prepare("
    SELECT s.*, k.full_name AS ad_soyad, k.username AS kullanici_adi
    FROM password_resets s
    JOIN users k ON s.user_id = k.id
    WHERE s.token = ?
      AND s.is_used = 0
      AND s.expires_at > NOW()
      AND k.is_active = 1
");
$sifirlama->execute([$token]);
$sifirlama = $sifirlama->fetch();

if (!$sifirlama) {
    $mesaj     = 'Bu bağlantı geçersiz veya süresi dolmuş. Lütfen yeni bir sıfırlama talebi oluşturun.';
    $mesaj_tur = 'danger';
} else {
    $gecerli   = true;
    $kullanici = $sifirlama;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $gecerli) {
    csrfDogrula();
    $yeni    = $_POST['yeni_sifre']  ?? '';
    $tekrar  = $_POST['yeni_tekrar'] ?? '';

    if (strlen($yeni) < 6) {
        $mesaj = 'Şifre en az 6 karakter olmalıdır.';
        $mesaj_tur = 'danger';
    } elseif ($yeni !== $tekrar) {
        $mesaj = 'Şifreler eşleşmiyor.';
        $mesaj_tur = 'danger';
    } else {
        // Şifreyi güncelle
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
            ->execute([password_hash($yeni, PASSWORD_DEFAULT), $sifirlama['user_id']]);

        // Token'ı iptal et
        $pdo->prepare("UPDATE password_resets SET is_used = 1 WHERE token = ?")
            ->execute([$token]);

        $tamamlandi = true;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1e4d6b">
    <title>Yeni Şifre Belirle | <?= SITE_ADI ?></title>
    <link rel="stylesheet" href="<?= ROOT_URL ?>assets/css/style.css">
</head>
<body>
<div class="login-page">
    <div class="login-box">
        <div class="login-logo">
            <div class="logo-icon">🔩</div>
            <h2><?= SITE_ADI ?></h2>
            <p>Yeni Şifre Belirle</p>
        </div>

        <?php if ($tamamlandi): ?>
        <div class="alert alert-success">✅ Şifreniz başarıyla güncellendi.</div>
        <a href="<?= ROOT_URL ?>pages/auth/login.php" class="btn btn-primary" style="width:100%;margin-top:8px;">🔐 Giriş Yap</a>

        <?php elseif (!$gecerli): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($mesaj) ?></div>
        <a href="<?= ROOT_URL ?>pages/auth/forgot_password.php" class="btn btn-secondary" style="width:100%;margin-top:8px;">🔄 Yeni Talep Oluştur</a>

        <?php else: ?>
        <p style="font-size:13px;color:var(--muted);margin-bottom:20px;">
            Merhaba <strong><?= htmlspecialchars($kullanici['ad_soyad']) ?></strong>, yeni şifrenizi belirleyin.
        </p>

        <?php if ($mesaj): ?>
        <div class="alert alert-<?= $mesaj_tur ?>"><?= htmlspecialchars($mesaj) ?></div>
        <?php endif; ?>

        <form method="post">
            <?= csrfInput() ?>
            <div class="form-group">
                <label>Yeni Şifre * <span style="font-weight:400;color:var(--muted);font-size:12px;">(min. 6 karakter)</span></label>
                <input type="password" name="yeni_sifre" required minlength="6" autofocus placeholder="••••••••">
            </div>
            <div class="form-group">
                <label>Yeni Şifre Tekrar *</label>
                <input type="password" name="yeni_tekrar" required minlength="6" placeholder="••••••••">
            </div>
            <button type="submit" class="btn btn-primary">💾 Şifremi Güncelle</button>
        </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
