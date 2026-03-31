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
require_once __DIR__ . '/../../includes/log.php';
girisKontrol();

$sayfa_basligi = 'Şifre Değiştir';
$ku = mevcutKullanici();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfDogrula();
    $eski  = $_POST['eski_sifre']  ?? '';
    $yeni  = $_POST['yeni_sifre']  ?? '';
    $tekrar= $_POST['yeni_tekrar'] ?? '';

    if (!$eski || !$yeni || !$tekrar) {
        flash('Tüm alanlar zorunludur.', 'danger');
    } elseif (strlen($yeni) < 6) {
        flash('Yeni şifre en az 6 karakter olmalıdır.', 'danger');
    } elseif ($yeni !== $tekrar) {
        flash('Yeni şifreler eşleşmiyor.', 'danger');
    } else {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id=? AND is_active=1");
        $stmt->execute([$ku['id']]);
        $mevcut_hash = $stmt->fetchColumn();

        if (!$mevcut_hash || !password_verify($eski, $mevcut_hash)) {
            flash('Mevcut şifreniz hatalı.', 'danger');
        } else {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")
                ->execute([password_hash($yeni, PASSWORD_DEFAULT), $ku['id']]);
            logYaz($pdo, 'guncelle', 'kullanici', 'Kendi şifresini değiştirdi: ' . $ku['adi'], $ku['id'], null, null, 'lite');
            flash('Şifreniz başarıyla güncellendi.');
            header('Location: profile_password.php'); exit;
        }
    }
    header('Location: profile_password.php'); exit;
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1><span>🔑</span> Şifre Değiştir</h1>
</div>

<div class="card" style="max-width:480px;">
    <div class="card-title">🔒 Yeni Şifre Belirle</div>
    <form method="post">
        <?= csrfInput() ?>
        <div class="form-group">
            <label>Mevcut Şifre *</label>
            <input type="password" name="eski_sifre" required autofocus placeholder="••••••••">
        </div>
        <div class="form-group">
            <label>Yeni Şifre * <span style="font-weight:400;color:var(--muted);font-size:12px;">(min. 6 karakter)</span></label>
            <input type="password" name="yeni_sifre" required minlength="6" placeholder="••••••••">
        </div>
        <div class="form-group">
            <label>Yeni Şifre Tekrar *</label>
            <input type="password" name="yeni_tekrar" required minlength="6" placeholder="••••••••">
        </div>
        <div style="margin-top:16px;">
            <button type="submit" class="btn btn-primary">💾 Şifreyi Güncelle</button>
            <a href="<?= ROOT_URL ?>index.php" class="btn btn-secondary" style="margin-left:8px;">İptal</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
