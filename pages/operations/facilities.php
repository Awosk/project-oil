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
require_once __DIR__ . '/../../classes/Tesis.php';
girisKontrol();

$sayfa_basligi = 'Tesisler';

    $tesisler = Tesis::listele($pdo);

$arama = trim($_GET['q'] ?? '');
if ($arama) {
    $tesisler = array_filter($tesisler, function($t) use ($arama) {
        return stripos($t['firma_adi'], $arama) !== false
            || stripos($t['firma_adresi'], $arama) !== false;
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
    <div style="display:flex; gap:8px;">
        <input type="text" id="liveArama" value="<?= htmlspecialchars($arama) ?>" placeholder="🔍  Tesis veya adres anında ara..." style="flex:1;" oninput="canliArama()">
        <?php if ($arama): ?>
        <a href="facilities.php" class="btn btn-secondary btn-sm">✕</a>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($tesisler)): ?>
<div class="alert alert-info">Henüz tesis kaydı yok. <a href="facilities_management.php" class="btn btn-sm btn-primary" style="margin-left:8px">➕ Ekle</a></div>
<?php else: ?>
<div class="arac-grid">
    <?php foreach ($tesisler as $t): ?>
    <a href="facility_detail.php?id=<?= $t['id'] ?>" class="arac-card">
        <div class="arac-card-plaka" style="font-size:16px;"><?= htmlspecialchars($t['firma_adi']) ?></div>
        <div class="arac-card-model"><?= htmlspecialchars($t['firma_adresi']) ?></div>
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
<script>
function canliArama() {
    var val = document.getElementById('liveArama').value.toLowerCase();
    var kartlar = document.querySelectorAll('.arac-card');
    kartlar.forEach(function(kart) {
        if (kart.textContent.toLowerCase().indexOf(val) > -1) {
            kart.style.display = '';
        } else {
            kart.style.display = 'none';
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
