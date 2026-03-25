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

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/log.php';
girisKontrol();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ../index.php'); exit; }
$ku = mevcutKullanici();

$arac = $pdo->prepare("
    SELECT a.*, t.tur_adi
    FROM lite_araclar a
    LEFT JOIN lite_arac_turleri t ON a.arac_turu_id = t.id
    WHERE a.id=? AND a.aktif=1
");
$arac->execute([$id]);
$arac = $arac->fetch();
if (!$arac) { flash('Araç bulunamadı.', 'danger'); header('Location: ../index.php'); exit; }

$sayfa_basligi = $arac['plaka'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['yag_ekle'])) {
    csrfDogrula();
    $urun_id    = (int)$_POST['urun_id'];
    $miktar     = (float)str_replace(',', '.', $_POST['miktar']);
    $tarih      = $_POST['tarih'];
    $aciklama   = trim($_POST['aciklama'] ?? '');
    $yag_bakimi = isset($_POST['yag_bakimi']) ? 1 : 0;
    $mevcut_km  = ($yag_bakimi && !empty($_POST['mevcut_km'])) ? (int)$_POST['mevcut_km'] : null;

    if ($urun_id && $miktar > 0 && $tarih) {
        $stmt = $pdo->prepare("INSERT INTO lite_kayitlar (kayit_turu,arac_id,urun_id,miktar,tarih,aciklama,yag_bakimi,mevcut_km,olusturan_id) VALUES ('arac',?,?,?,?,?,?,?,?)");
        $stmt->execute([$id,$urun_id,$miktar,$tarih,$aciklama?:null,$yag_bakimi,$mevcut_km,$ku['id']]);
        $yeni_id = $pdo->lastInsertId();
        $ul = $pdo->prepare('SELECT urun_kodu,urun_adi FROM lite_urunler WHERE id=?');
        $ul->execute([$urun_id]); $ul = $ul->fetch();
        $log_msg = $arac['plaka'].' aracına yağ eklendi: '.($ul['urun_kodu']??'').' '.($ul['urun_adi']??'').', '.$miktar.'L';
        if ($yag_bakimi) $log_msg .= ' [YAĞ BAKIMI - '.($mevcut_km ? number_format($mevcut_km).' KM' : 'KM girilmedi').']';
        if ($aciklama)   $log_msg .= '. Açıklama: '.$aciklama;
        logYaz($pdo,'ekle','arac_kayit',$log_msg,$yeni_id,null,['plaka'=>$arac['plaka'],'urun_id'=>$urun_id,'miktar'=>$miktar,'tarih'=>$tarih,'yag_bakimi'=>$yag_bakimi,'mevcut_km'=>$mevcut_km,'aciklama'=>$aciklama],'lite');
        flash('Yağ kaydı eklendi.');
    } else {
        flash('Ürün, miktar ve tarih zorunludur.', 'danger');
    }
    header('Location: arac_detay.php?id='.$id); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aciklama_guncelle'])) {
    csrfDogrula();
    $kayit_id  = (int)$_POST['kayit_id'];
    $yeni_aciklama = trim($_POST['aciklama_yeni'] ?? '');
    $sr = $pdo->prepare('SELECT lk.*,u.urun_kodu,u.urun_adi FROM lite_kayitlar lk JOIN lite_urunler u ON lk.urun_id=u.id WHERE lk.id=? AND lk.arac_id=? AND lk.aktif=1');
    $sr->execute([$kayit_id, $id]); $sr = $sr->fetch();
    if ($sr) {
        $pdo->prepare("UPDATE lite_kayitlar SET aciklama=? WHERE id=?")->execute([$yeni_aciklama ?: null, $kayit_id]);
        logYaz($pdo,'guncelle','arac_kayit', $arac['plaka'].' Plakalı Aracın '.$sr['urun_kodu'].' kaydının açıklaması güncellendi', $kayit_id, ['aciklama'=>$sr['aciklama']], ['aciklama'=>$yeni_aciklama], 'lite');
        flash('Açıklama güncellendi.');
    }
    header('Location: arac_detay.php?id='.$id); exit;
}

if (isset($_GET['yag_sil'])) {
    $sil_id = (int)$_GET['yag_sil'];
    $sr = $pdo->prepare('SELECT lk.*,u.urun_kodu,u.urun_adi FROM lite_kayitlar lk JOIN lite_urunler u ON lk.urun_id=u.id WHERE lk.id=? AND lk.arac_id=?');
    $sr->execute([$sil_id,$id]); $sr = $sr->fetch();
    if ($sr) {
        $pdo->prepare("UPDATE lite_kayitlar SET aktif=0 WHERE id=? AND arac_id=?")->execute([$sil_id,$id]);
        $log_msg = $arac['plaka'].' aracından yağ kaydı silindi: '.$sr['urun_kodu'].' '.$sr['urun_adi'].', '.$sr['miktar'].'L';
        if ($sr['yag_bakimi']) $log_msg .= ' [YAĞ BAKIMI]';
        if ($sr['aciklama'])   $log_msg .= '. Açıklama: '.$sr['aciklama'];
        logYaz($pdo,'sil','arac_kayit',$log_msg,$sil_id,$sr,null,'lite');
    }
    flash('Yağ kaydı silindi.');
    header('Location: arac_detay.php?id='.$id); exit;
}

$urunler = $pdo->query("SELECT * FROM lite_urunler WHERE aktif=1 ORDER BY urun_adi")->fetchAll();

$yag_kayitlari = $pdo->prepare("
    SELECT lk.*,u.urun_adi,u.urun_kodu,k.ad_soyad
    FROM lite_kayitlar lk
    JOIN lite_urunler u ON lk.urun_id=u.id
    LEFT JOIN kullanicilar k ON lk.olusturan_id=k.id
    WHERE lk.arac_id=? AND lk.aktif=1
    ORDER BY lk.tarih DESC, lk.olusturma_tarihi DESC
");
$yag_kayitlari->execute([$id]);
$yag_kayitlari = $yag_kayitlari->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>
        <span>🚗</span>
        <?= htmlspecialchars($arac['plaka']) ?>
        <span class="badge badge-info" style="font-size:13px;"><?= htmlspecialchars($arac['tur_adi'] ?? '—') ?></span>
    </h1>
    <a href="../index.php" class="btn btn-secondary btn-sm">← Geri</a>
</div>

<div class="card" style="padding:14px 16px;margin-bottom:14px;">
    <div style="display:flex;gap:20px;flex-wrap:wrap;align-items:center;">
        <div>
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:700;">Marka / Model</div>
            <div style="font-weight:600;"><?= htmlspecialchars($arac['marka_model']) ?></div>
        </div>
        <div>
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:700;">Toplam Kayıt</div>
            <div style="font-weight:600;"><?= count($yag_kayitlari) ?> işlem</div>
        </div>
        <?php
        $son_bakim = null;
        foreach ($yag_kayitlari as $yk) {
            if ($yk['yag_bakimi']) { $son_bakim = $yk; break; }
        }
        ?>
        <?php if (!empty($yag_kayitlari)): ?>
        <div>
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:700;">Son İşlem</div>
            <div style="font-weight:600;"><?= formatliTarih($yag_kayitlari[0]['olusturma_tarihi']) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($son_bakim): ?>
        <div>
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:700;">Son Yağ Bakımı</div>
            <div style="font-weight:600;color:var(--warning);">
                🔧 <?= formatliTarih($son_bakim['tarih']) ?>
                <?php if ($son_bakim['mevcut_km']): ?> · <?= number_format($son_bakim['mevcut_km']) ?> KM<?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
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
                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['urun_kodu'].' - '.$u['urun_adi']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Miktar (Litre) *</label>
                <input type="number" name="miktar" required min="0.01" step="0.01" placeholder="Örn: 2.00">
            </div>
            <div class="form-group">
                <label>Tarih *</label>
                <input type="date" name="tarih" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group full">
                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;text-transform:none;font-size:14px;font-weight:600;color:var(--text);background:var(--warning-l);padding:12px 14px;border-radius:var(--r-sm);border:1.5px solid var(--warning);">
                    <input type="checkbox" name="yag_bakimi" id="yag_bakimi" value="1" onchange="kmToggle()" style="width:20px;height:20px;accent-color:var(--warning);cursor:pointer;flex-shrink:0;">
                    🔧 Bu işlem yağ & filtre bakımıdır
                </label>
            </div>
            <div class="form-group" id="km_grup" style="display:none;">
                <label>Mevcut KM</label>
                <input type="number" name="mevcut_km" id="mevcut_km" min="0" placeholder="Örn: 125000">
            </div>
            <div class="form-group">
                <label>Açıklama</label>
                <input type="text" name="aciklama" placeholder="İsteğe bağlı...">
            </div>
        </div>
        <div style="margin-top:14px;">
            <button type="submit" name="yag_ekle" class="btn btn-success">💾 Kaydet</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-title">📋 Yağ Geçmişi (<?= count($yag_kayitlari) ?>)</div>
    <?php if (empty($yag_kayitlari)): ?>
    <div style="text-align:center;padding:28px;color:var(--muted);">Henüz yağ kaydı yok.</div>
    <?php else: ?>
    <div class="kayit-list">
        <?php foreach ($yag_kayitlari as $k): ?>
        <div id="kayit-<?= $k['id'] ?>" class="kayit-item" style="<?= $k['yag_bakimi'] ? 'border-left:3px solid var(--warning);' : '' ?>">
            <div class="kayit-info">
                <div class="kayit-urun">
                    <?= htmlspecialchars($k['urun_adi']) ?>
                    <small><?= htmlspecialchars($k['urun_kodu']) ?></small>
                    <?php if ($k['yag_bakimi']): ?>
                    <span style="background:var(--warning-l);color:var(--warning);font-size:10px;font-weight:700;padding:2px 6px;border-radius:4px;margin-left:4px;">🔧 BAKIM</span>
                    <?php endif; ?>
                </div>
                <div class="kayit-meta">
                    🛢️ <strong title="Yağın verildiği tarih"><?= formatliTarih($k['tarih']) ?></strong>
                    <span class="kayit-giris-tarihi">· 🕐 <?= formatliTarih($k['olusturma_tarihi']) ?></span>
                    · 👤 <?= htmlspecialchars($k['ad_soyad'] ?? '-') ?>
                    <?php if ($k['yag_bakimi'] && $k['mevcut_km']): ?>
                    · 🛣️ <?= number_format($k['mevcut_km']) ?> KM
                    <?php endif; ?>
                    <?php if ($k['aciklama']): ?> · <?= htmlspecialchars($k['aciklama']) ?><?php endif; ?>
                </div>
            </div>
            <div class="kayit-miktar"><?= formatliMiktar($k['miktar']) ?></div>
            <button class="btn btn-sm btn-secondary" style="flex-shrink:0;font-size:11px;padding:4px 8px;"
                onclick="aciklamaModal(<?= $k['id'] ?>, '<?= htmlspecialchars($k['aciklama'] ?? '', ENT_QUOTES) ?>')" title="Açıklama düzenle">✏️</button>
            <button class="kayit-sil" onclick="if(confirm('Bu kaydı silmek istiyor musunuz?')) location.href='arac_detay.php?id=<?= $id ?>&yag_sil=<?= $k['id'] ?>'" title="Sil">🗑️</button>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Açıklama Modal -->
<div id="aciklamaModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;">
    <div class="modal-box" style="max-width:400px;">
        <div style="font-weight:700;font-size:16px;margin-bottom:16px;">✏️ Açıklama Düzenle</div>
        <form method="post">
            <?= csrfInput() ?>
            <input type="hidden" name="kayit_id" id="aciklama_kayit_id">
            <div class="form-group">
                <label>Açıklama <span style="font-weight:400;color:var(--muted);font-size:12px;">(boş bırakılırsa silinir)</span></label>
                <input type="text" name="aciklama_yeni" id="aciklama_input" placeholder="Açıklama girin..." maxlength="255">
            </div>
            <div style="display:flex;gap:8px;margin-top:16px;">
                <button type="submit" name="aciklama_guncelle" class="btn btn-primary" style="flex:1;">💾 Kaydet</button>
                <button type="button" class="btn btn-secondary" onclick="aciklamaModalKapat()">İptal</button>
            </div>
        </form>
    </div>
</div>

<script>
function kmToggle() {
    var cb  = document.getElementById('yag_bakimi');
    var grp = document.getElementById('km_grup');
    grp.style.display = cb.checked ? '' : 'none';
}
function aciklamaModal(id, mevcutAciklama) {
    document.getElementById('aciklama_kayit_id').value = id;
    document.getElementById('aciklama_input').value = mevcutAciklama;
    var m = document.getElementById('aciklamaModal');
    m.style.display = 'flex';
    setTimeout(() => document.getElementById('aciklama_input').focus(), 50);
}
function aciklamaModalKapat() {
    document.getElementById('aciklamaModal').style.display = 'none';
}
document.getElementById('aciklamaModal').addEventListener('click', function(e) {
    if (e.target === this) aciklamaModalKapat();
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
