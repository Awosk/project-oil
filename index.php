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

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
girisKontrol();

if (isset($_GET['hata']) && $_GET['hata'] === 'yetki') {
    flash('Bu sayfaya erişim yetkiniz bulunmuyor.', 'danger');
}

$sayfa_basligi = 'Araçlar';

$araclar = $pdo->query("
    SELECT a.*,
           t.tur_adi,
           t.oncelik,
           k.ad_soyad AS olusturan_adi,
           COUNT(CASE WHEN lk.aktif = 1 THEN 1 END) AS kayit_sayisi,
           MAX(CASE WHEN lk.aktif = 1 THEN lk.tarih END) AS son_kayit
    FROM lite_araclar a
    LEFT JOIN lite_arac_turleri t  ON a.arac_turu_id = t.id
    LEFT JOIN kullanicilar k       ON a.olusturan_id = k.id
    LEFT JOIN lite_kayitlar lk     ON lk.arac_id = a.id
    WHERE a.aktif = 1
    GROUP BY a.id
    ORDER BY t.oncelik ASC, t.tur_adi, a.plaka
")->fetchAll();

$arama = trim($_GET['q'] ?? '');
if ($arama) {
    $araclar = array_filter($araclar, function($a) use ($arama) {
        return stripos($a['plaka'], $arama) !== false
            || stripos($a['marka_model'], $arama) !== false
            || stripos($a['tur_adi'] ?? '', $arama) !== false;
    });
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><span>🚗</span> Araçlar</h1>
    <a href="pages/operations/vehicles.php" class="btn btn-primary btn-sm">➕ Araç Ekle</a>
</div>

<div class="card" style="padding:12px 16px; margin-bottom:14px;">
    <form method="get" style="display:flex; gap:8px;">
        <input type="text" name="q" value="<?= htmlspecialchars($arama) ?>" placeholder="🔍  Plaka veya model ara..." style="flex:1;">
        <?php if ($arama): ?>
        <a href="index.php" class="btn btn-secondary btn-sm">✕</a>
        <?php endif; ?>
    </form>
</div>

<?php if (empty($araclar)): ?>
<div class="alert alert-info">Henüz araç kaydı yok. <a href="pages/operations/vehicles.php" class="btn btn-sm btn-primary" style="margin-left:8px">➕ Ekle</a></div>
<?php else: ?>
<div class="arac-grid">
    <?php foreach ($araclar as $a): ?>
    <a href="pages/operations/vehicle_detail.php?id=<?= $a['id'] ?>" class="arac-card">
        <div class="arac-card-plaka"><?= htmlspecialchars($a['plaka']) ?></div>
        <div class="arac-card-model"><?= htmlspecialchars($a['marka_model']) ?></div>
        <div class="arac-card-meta">
            <span class="badge badge-info arac-card-tur"><?= htmlspecialchars($a['tur_adi'] ?? '—') ?></span>
            <span class="arac-card-sayi">
                <?php if ($a['kayit_sayisi'] > 0): ?>
                📋 <?= $a['kayit_sayisi'] ?> kayıt
                <?php if ($a['son_kayit']): ?>
                · <?= formatliTarih($a['son_kayit']) ?>
                <?php endif; ?>
                <?php else: ?>
                <span style="color:var(--muted)">Kayıt yok</span>
                <?php endif; ?>
            </span>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>