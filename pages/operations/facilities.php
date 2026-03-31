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
girisKontrol();

$sayfa_basligi = 'Tesisler';

$tesisler = $pdo->query("
    SELECT t.*,
           COUNT(CASE WHEN lk.is_active = 1 THEN 1 END) AS kayit_sayisi,
           MAX(CASE WHEN lk.is_active = 1 THEN lk.date END) AS son_kayit
    FROM facilities t
    LEFT JOIN oil_records lk ON lk.facility_id = t.id
    WHERE t.is_active = 1
    GROUP BY t.id
    ORDER BY t.name
")->fetchAll();

$arama = trim($_GET['q'] ?? '');
if ($arama) {
    $tesisler = array_filter($tesisler, function($t) use ($arama) {
        return stripos($t['name'], $arama) !== false
            || stripos($t['address'], $arama) !== false;
    });
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1><span>🏭</span> Tesisler</h1>
    <a href="facilities_management.php" class="btn btn-primary btn-sm">➕ Tesis Ekle</a>
</div>

<!-- Arama -->
<div class="card" style="padding:12px 16px; margin-bottom:14px;">
    <form method="get" style="display:flex; gap:8px;">
        <input type="text" name="q" value="<?= htmlspecialchars($arama) ?>" placeholder="🔍  Tesis veya adres ara..." style="flex:1;">
        <?php if ($arama): ?>
        <a href="facilities.php" class="btn btn-secondary btn-sm">✕</a>
        <?php endif; ?>
    </form>
</div>

<?php if (empty($tesisler)): ?>
<div class="alert alert-info">Henüz tesis kaydı yok. <a href="facilities_management.php" class="btn btn-sm btn-primary" style="margin-left:8px">➕ Ekle</a></div>
<?php else: ?>
<div class="arac-grid">
    <?php foreach ($tesisler as $t): ?>
    <a href="facility_detail.php?id=<?= $t['id'] ?>" class="arac-card">
        <div class="arac-card-plaka" style="font-size:16px;"><?= htmlspecialchars($t['name']) ?></div>
        <div class="arac-card-model"><?= htmlspecialchars($t['address']) ?></div>
        <div class="arac-card-meta">
            <span class="badge badge-primary">🏭 Tesis</span>
            <span class="arac-card-sayi">
                <?php if ($t['kayit_sayisi'] > 0): ?>
                📋 <?= $t['kayit_sayisi'] ?> kayıt
                <?php if ($t['son_kayit']): ?> · <?= formatliTarih($t['son_kayit']) ?><?php endif; ?>
                <?php else: ?>
                <span style="color:var(--muted)">Kayıt yok</span>
                <?php endif; ?>
            </span>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
