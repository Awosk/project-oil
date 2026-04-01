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
require_once __DIR__ . '/../../classes/Tesis.php';

girisKontrol();

$sayfa_basligi = 'Tesis Yönetimi';
$ku = mevcutKullanici();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ekle'])) {
    csrfDogrula();
    $firma = trim($_POST['firma_adi']);
    $adres = trim($_POST['firma_adresi']);
    
    if ($firma && $adres) {
        $mevcut = Tesis::bulAd($pdo, $firma);

        if ($mevcut && $mevcut['aktif'] == 0) {
            Tesis::reaktifEt($pdo, $mevcut['id'], $adres, $ku['id']);
            logYaz($pdo,'ekle','tesis','Silinen tesis reaktif edildi: '.$firma, $mevcut['id'], null, ['firma'=>$firma,'adres'=>$adres], 'lite');
            flash('Daha önce silinmiş tesis tekrar aktif edildi.');
        } elseif ($mevcut && $mevcut['aktif'] == 1) {
            flash('Bu tesis adı zaten kayıtlı.', 'danger');
        } else {
            $yeni_id = Tesis::ekle($pdo, $firma, $adres, $ku['id']);
            logYaz($pdo,'ekle','tesis','Tesis eklendi: '.$firma, $yeni_id, null, ['firma'=>$firma,'adres'=>$adres], 'lite');
            flash('Tesis eklendi.');
        }
    } else { flash('Tüm alanlar zorunludur.', 'danger'); }
    header('Location: facilities_management.php'); exit;
}

if (isset($_GET['sil'])) {
    $sil_id = (int)$_GET['sil'];
    $sr = Tesis::bulId($pdo, $sil_id);
    Tesis::sil($pdo, $sil_id);
    if ($sr) logYaz($pdo,'sil','tesis','Tesis silindi: '.$sr['firma_adi'], $sil_id, $sr, null, 'lite');
    flash('Tesis silindi.');
    header('Location: facilities_management.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['duzenle'])) {
    csrfDogrula();
    $did   = (int)$_POST['duzenle_id'];
    $firma = trim($_POST['duzenle_firma']);
    $adres = trim($_POST['duzenle_adres']);
    
    if ($did && $firma && $adres) {
        $sr = Tesis::bulId($pdo, $did);
        $cakisma = Tesis::adCakismaVarMi($pdo, $firma, $did);
        
        if ($cakisma) {
            flash('Bu tesis adı başka bir kayıtta kullanılıyor.', 'danger');
        } else {
            Tesis::guncelle($pdo, $did, $firma, $adres);
            logYaz($pdo,'guncelle','tesis','Tesis güncellendi: '.$firma, $did,
                ['firma_adi'=>$sr['firma_adi'],'firma_adresi'=>$sr['firma_adresi']],
                ['firma_adi'=>$firma,'firma_adresi'=>$adres], 'lite');
            flash('Tesis güncellendi.');
        }
    } else { flash('Tüm alanlar zorunludur.', 'danger'); }
    header('Location: facilities_management.php'); exit;
}

$tesisler = Tesis::listeleYonetim($pdo);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1><span>🏭</span> Tesis Yönetimi</h1>
</div>

<div class="card">
    <div class="card-title">➕ Yeni Tesis / Şantiye Ekle</div>
    <form method="post">
        <?= csrfInput() ?>
        <div class="form-grid">
            <div class="form-group">
                <label>Firma / Şantiye Adı *</label>
                <input type="text" name="firma_adi" required placeholder="Örn: ABC Şantiyesi" maxlength="200">
            </div>
            <div class="form-group">
                <label>Adres *</label>
                <input type="text" name="firma_adresi" required placeholder="Tam adres">
            </div>
        </div>
        <div style="margin-top:14px;">
            <button type="submit" name="ekle" class="btn btn-primary">💾 Ekle</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-title">📋 Kayıtlı Tesisler (<?= count($tesisler) ?>)</div>
    <?php if (empty($tesisler)): ?>
    <div class="alert alert-info">Henüz tesis kaydı yok.</div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Firma Adı</th><th>Adres</th><th>İşlem</th></tr></thead>
            <tbody>
            <?php foreach ($tesisler as $i => $t): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><strong><?= htmlspecialchars($t['firma_adi']) ?></strong></td>
                <td><?= htmlspecialchars($t['firma_adresi']) ?></td>
                <td style="display:flex;gap:6px;flex-wrap:wrap;">
                    <a href="facility_detail.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-primary">👁️ Detay</a>
                    <button class="btn btn-sm btn-secondary"
                        onclick="tesisDuzenleModal(<?= $t['id'] ?>, '<?= htmlspecialchars($t['firma_adi'], ENT_QUOTES) ?>', '<?= htmlspecialchars($t['firma_adresi'], ENT_QUOTES) ?>')">✏️ Düzenle</button>
                    <a href="?sil=<?= $t['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Silmek istediğinize emin misiniz?')">🗑️</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Düzenleme Modal -->
<div id="tesisDuzenleModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;">
    <div class="modal-box" style="max-width:420px;">
        <div style="font-weight:700;font-size:16px;margin-bottom:16px;">✏️ Tesis Düzenle</div>
        <form method="post">
            <?= csrfInput() ?>
            <input type="hidden" name="duzenle_id" id="duzenle_tesis_id">
            <div class="form-group">
                <label>Firma / Şantiye Adı *</label>
                <input type="text" name="duzenle_firma" id="duzenle_tesis_firma" required maxlength="200">
            </div>
            <div class="form-group">
                <label>Adres *</label>
                <input type="text" name="duzenle_adres" id="duzenle_tesis_adres" required>
            </div>
            <div style="display:flex;gap:8px;margin-top:16px;">
                <button type="submit" name="duzenle" class="btn btn-primary" style="flex:1;">💾 Kaydet</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('tesisDuzenleModal').style.display='none'">İptal</button>
            </div>
        </form>
    </div>
</div>

<script>
function tesisDuzenleModal(id, firma, adres) {
    document.getElementById('duzenle_tesis_id').value = id;
    document.getElementById('duzenle_tesis_firma').value = firma;
    document.getElementById('duzenle_tesis_adres').value = adres;
    var m = document.getElementById('tesisDuzenleModal');
    m.style.display = 'flex';
    setTimeout(() => document.getElementById('duzenle_tesis_firma').focus(), 50);
}
document.getElementById('tesisDuzenleModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
