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
require_once __DIR__ . '/../../classes/Urun.php';
require_once __DIR__ . '/../../classes/Islem.php';

girisKontrol();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: facilities.php'); exit; }
$ku = mevcutKullanici();

$tesis = Tesis::aktifBulId($pdo, $id);
if (!$tesis) { flash('Tesis bulunamadı.', 'danger'); header('Location: facilities.php'); exit; }

$sayfa_basligi = $tesis['firma_adi'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ekle'])) {
    csrfDogrula();
    $urun_id  = (int)$_POST['urun_id'];
    $miktar   = (float)str_replace(',', '.', $_POST['miktar']);
    $tarih    = $_POST['tarih'];
    $aciklama = trim($_POST['aciklama'] ?? '');

    if ($urun_id && $miktar > 0 && $tarih) {
        $yeni_id = Islem::tesisYagEkle($pdo, $id, $urun_id, $miktar, $tarih, $aciklama, $ku['id']);
        $urun_adi_log = Urun::bulId($pdo, $urun_id);
        
        logYaz($pdo,'ekle','tesis_kayit',
            $tesis['firma_adi'].' tesisine yağ eklendi: '.($urun_adi_log['urun_kodu']??'').' - '.($urun_adi_log['urun_adi']??'').', '.$miktar.'L, tarih:'.$tarih.'. Açıklama: '.($aciklama ?? 'Yok'),
            $yeni_id, null,
            ['tesis_id'=>$id,'firma'=>$tesis['firma_adi'],'urun_id'=>$urun_id,'miktar'=>$miktar,'tarih'=>$tarih,'aciklama'=>$aciklama],
            'lite');
        flash('Kayıt eklendi.');
    } else {
        flash('Ürün, miktar ve tarih zorunludur.', 'danger');
    }
    header('Location: facility_detail.php?id=' . $id); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kayit_guncelle'])) {
    csrfDogrula();
    $kayit_id  = (int)$_POST['kayit_id'];
    $urun_id   = (int)$_POST['guncelle_urun_id'];
    $miktar    = (float)str_replace(',', '.', $_POST['guncelle_miktar']);
    $tarih     = $_POST['guncelle_tarih'];
    $aciklama  = trim($_POST['guncelle_aciklama'] ?? '');
    
    if ($kayit_id && $urun_id && $miktar > 0 && $tarih) {
        $sr = Islem::tesisKayitBul($pdo, $kayit_id, $id);
        if ($sr) {
            Islem::kayitGuncelle($pdo, $kayit_id, $urun_id, $miktar, $tarih, $aciklama);
            logYaz($pdo,'guncelle','tesis_kayit', $tesis['firma_adi'].' tesisinin yağ kaydı güncellendi', $kayit_id, 
                   ['urun_id'=>$sr['urun_id'], 'miktar'=>$sr['miktar'], 'tarih'=>$sr['tarih'], 'aciklama'=>$sr['aciklama']], 
                   ['urun_id'=>$urun_id, 'miktar'=>$miktar, 'tarih'=>$tarih, 'aciklama'=>$aciklama], 'lite');
            flash('Kayıt güncellendi.');
        }
    } else {
        flash('Ürün, miktar ve tarih zorunludur.', 'danger');
    }
    header('Location: facility_detail.php?id='.$id); exit;
}

if (isset($_GET['sil'])) {
    $sil_id = (int)$_GET['sil'];
    $sr = Islem::tesisKayitSilBul($pdo, $sil_id, $id);
    
    if ($sr) {
        Islem::tesisKayitSil($pdo, $sil_id, $id);
        logYaz($pdo,'sil','tesis_kayit',
            $tesis['firma_adi'].' tesisinden yağ kaydı silindi: '.$sr['urun_kodu'].' - '.$sr['urun_adi'].', '.$sr['miktar'].'L, tarih:'.$sr['tarih'].'. Açıklama: '.($sr['aciklama'] ?? 'Yok'),
            $sil_id, $sr, null, 'lite');
    }
    flash('Kayıt silindi.');
    header('Location: facility_detail.php?id=' . $id); exit;
}

$urunler  = Urun::tumUrunler($pdo);
$kayitlar = Islem::tesisKayitlari($pdo, $id);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1><span>🏭</span> <?= htmlspecialchars($tesis['firma_adi']) ?></h1>
    <a href="facilities.php" class="btn btn-secondary btn-sm">← Geri</a>
</div>

<div class="card" style="padding:14px 16px; margin-bottom:14px;">
    <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
        <div>
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:700;">Adres</div>
            <div style="font-weight:600;"><?= htmlspecialchars($tesis['firma_adresi']) ?></div>
        </div>
        <div>
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:700;">Toplam Kayıt</div>
            <div style="font-weight:600;"><?= count($kayitlar) ?> işlem</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-title">➕ Yağ Kaydı Ekle</div>
    <form method="post">
        <?= csrfInput() ?>
        <div class="form-grid">
            <div class="form-group">
                <label>Ürün *</label>
                <select name="urun_id" required>
                    <option value="">-- Ürün Seçin --</option>
                    <?php foreach ($urunler as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['urun_kodu'] . ' - ' . $u['urun_adi']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Miktar (Litre) *</label>
                <input type="number" name="miktar" required min="0.01" step="0.01" placeholder="Örn: 5.00">
            </div>
            <div class="form-group">
                <label>Tarih *</label>
                <input type="date" name="tarih" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Açıklama</label>
                <input type="text" name="aciklama" placeholder="İsteğe bağlı not...">
            </div>
        </div>
        <div style="margin-top:14px;">
            <button type="submit" name="ekle" class="btn btn-success">💾 Kaydet</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-title">📋 Yağ Geçmişi (<?= count($kayitlar) ?> kayıt)</div>
    <?php if (empty($kayitlar)): ?>
    <div style="text-align:center; padding:28px; color:var(--muted);">Henüz kayıt yok.</div>
    <?php else: ?>
    <div class="kayit-list">
        <?php foreach ($kayitlar as $k): ?>
        <div id="kayit-<?= $k['id'] ?>" class="kayit-item">
            <div class="kayit-info">
                <div class="kayit-urun">
                    <?= htmlspecialchars($k['urun_adi']) ?>
                    <small><?= htmlspecialchars($k['urun_kodu']) ?></small>
                </div>
                <div class="kayit-meta">
                    🛢️ <strong title="Yağın verildiği tarih"><?= formatliTarih($k['tarih']) ?></strong>
                    <span class="kayit-giris-tarihi">· 🕐 <?= formatliTarih($k['olusturma_tarihi']) ?></span>
                    · 👤 <?= htmlspecialchars($k['ad_soyad'] ?? '-') ?>
                    <?php if ($k['aciklama']): ?> · <?= htmlspecialchars($k['aciklama']) ?><?php endif; ?>
                </div>
            </div>
            <div class="kayit-miktar"><?= formatliMiktar($k['miktar']) ?></div>
            <button class="btn btn-sm btn-secondary" style="flex-shrink:0;font-size:11px;padding:4px 8px;"
                onclick="kayitDuzenleModal(<?= $k['id'] ?>, <?= $k['urun_id'] ?>, '<?= $k['miktar'] ?>', '<?= $k['tarih'] ?>', '<?= htmlspecialchars($k['aciklama'] ?? '', ENT_QUOTES) ?>')" title="Kayıt düzenle">✏️ Düzenle</button>
            <button class="kayit-sil" onclick="if(confirm('Bu kaydı silmek istiyor musunuz?')) location.href='facility_detail.php?id=<?= $id ?>&sil=<?= $k['id'] ?>'" title="Sil">🗑️</button>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Kayıt Düzenleme Modal -->
<div id="kayitDuzenleModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;">
    <div class="modal-box" style="max-width:400px;width:100%;margin:20px;">
        <div style="font-weight:700;font-size:16px;margin-bottom:16px;">✏️ Kayıt Düzenle</div>
        <form method="post">
            <?= csrfInput() ?>
            <input type="hidden" name="kayit_id" id="duzenle_kayit_id">
            <div class="form-group">
                <label>Ürün *</label>
                <select name="guncelle_urun_id" id="guncelle_urun_id" required>
                    <?php foreach ($urunler as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['urun_kodu'].' - '.$u['urun_adi']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:12px;">
                <div class="form-group" style="flex:1;">
                    <label>Miktar (Litre) *</label>
                    <input type="number" name="guncelle_miktar" id="guncelle_miktar" required min="0.01" step="0.01">
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Tarih *</label>
                    <input type="date" name="guncelle_tarih" id="guncelle_tarih" required>
                </div>
            </div>
            <div class="form-group">
                <label>Açıklama</label>
                <input type="text" name="guncelle_aciklama" id="guncelle_aciklama" maxlength="255">
            </div>
            <div style="display:flex;gap:8px;margin-top:16px;">
                <button type="submit" name="kayit_guncelle" class="btn btn-primary" style="flex:1;">💾 Kaydet</button>
                <button type="button" class="btn btn-secondary" onclick="kayitDuzenleModalKapat()">İptal</button>
            </div>
        </form>
    </div>
</div>

<script>
function kayitDuzenleModal(id, urun_id, miktar, tarih, aciklama) {
    document.getElementById('duzenle_kayit_id').value = id;
    document.getElementById('guncelle_urun_id').value = urun_id;
    document.getElementById('guncelle_miktar').value = miktar;
    document.getElementById('guncelle_tarih').value = tarih;
    document.getElementById('guncelle_aciklama').value = aciklama;
    var m = document.getElementById('kayitDuzenleModal');
    m.style.display = 'flex';
}
function kayitDuzenleModalKapat() {
    document.getElementById('kayitDuzenleModal').style.display = 'none';
}
document.getElementById('kayitDuzenleModal').addEventListener('click', function(e) {
    if (e.target === this) kayitDuzenleModalKapat();
});

(function() {
    var hash = window.location.hash;
    if (hash && hash.startsWith('#kayit-')) {
        var el = document.querySelector(hash);
        if (el) {
            setTimeout(function() {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                el.classList.add('kayit-parlat');
                setTimeout(function() { el.classList.remove('kayit-parlat'); }, 2500);
            }, 300);
        }
    }
})();
</script>

<style>
@keyframes parlat {
    0%   { background: #fef9c3; box-shadow: 0 0 0 3px #fbbf24; }
    60%  { background: #fef9c3; box-shadow: 0 0 0 3px #fbbf24; }
    100% { background: transparent; box-shadow: none; }
}
.kayit-parlat { animation: parlat 2.5s ease-out forwards; border-radius: 8px; }
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
