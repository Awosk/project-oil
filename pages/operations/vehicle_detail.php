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

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ../index.php'); exit; }
$ku = mevcutKullanici();

$arac = $pdo->prepare("
    SELECT a.*, t.type_name AS tur_adi
    FROM vehicles a
    LEFT JOIN vehicle_types t ON a.vehicle_type_id = t.id
    WHERE a.id=? AND a.is_active=1
");
$arac->execute([$id]);
$arac = $arac->fetch();
if (!$arac) { flash('Araç bulunamadı.', 'danger'); header('Location: ../index.php'); exit; }

$sayfa_basligi = $arac['plate'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['yag_ekle'])) {
    csrfDogrula();
    $urun_id    = (int)$_POST['urun_id'];
    $miktar     = (float)str_replace(',', '.', $_POST['miktar']);
    $tarih      = $_POST['tarih'];
    $aciklama   = trim($_POST['aciklama'] ?? '');
    $yag_bakimi = isset($_POST['yag_bakimi']) ? 1 : 0;
    $mevcut_km  = ($yag_bakimi && !empty($_POST['mevcut_km'])) ? (int)$_POST['mevcut_km'] : null;

    if ($urun_id && $miktar > 0 && $tarih) {
        $stmt = $pdo->prepare("INSERT INTO oil_records (record_type,vehicle_id,product_id,quantity,date,notes,is_oil_change,current_km,created_by) VALUES ('arac',?,?,?,?,?,?,?,?)");
        $stmt->execute([$id,$urun_id,$miktar,$tarih,$aciklama?:null,$yag_bakimi,$mevcut_km,$ku['id']]);
        $yeni_id = $pdo->lastInsertId();
        $ul = $pdo->prepare('SELECT product_code,product_name FROM products WHERE id=?');
        $ul->execute([$urun_id]); $ul = $ul->fetch();
        $log_msg = $arac['plate'].' aracına yağ eklendi: '.($ul['product_code']??'').' '.($ul['product_name']??'').', '.$miktar.'L';
        if ($yag_bakimi) $log_msg .= ' [YAĞ BAKIMI - '.($mevcut_km ? number_format($mevcut_km).' KM' : 'KM girilmedi').']';
        if ($aciklama)   $log_msg .= '. Açıklama: '.$aciklama;
        logYaz($pdo,'ekle','arac_kayit',$log_msg,$yeni_id,null,['plate'=>$arac['plate'],'product_id'=>$urun_id,'quantity'=>$miktar,'date'=>$tarih,'is_oil_change'=>$yag_bakimi,'current_km'=>$mevcut_km,'notes'=>$aciklama],'lite');
        flash('Yağ kaydı eklendi.');
    } else {
        flash('Ürün, miktar ve tarih zorunludur.', 'danger');
    }
    header('Location: vehicle_detail.php?id='.$id); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aciklama_guncelle'])) {
    csrfDogrula();
    $kayit_id  = (int)$_POST['kayit_id'];
    $yeni_aciklama = trim($_POST['aciklama_yeni'] ?? '');
    $sr = $pdo->prepare('SELECT lk.*,u.product_code AS urun_kodu,u.product_name AS urun_adi FROM oil_records lk JOIN products u ON lk.product_id=u.id WHERE lk.id=? AND lk.vehicle_id=? AND lk.is_active=1');
    $sr->execute([$kayit_id, $id]); $sr = $sr->fetch();
    if ($sr) {
        $pdo->prepare("UPDATE oil_records SET notes=? WHERE id=?")->execute([$yeni_aciklama ?: null, $kayit_id]);
        logYaz($pdo,'guncelle','arac_kayit', $arac['plate'].' Plakalı Aracın '.$sr['urun_kodu'].' kaydının açıklaması güncellendi', $kayit_id, ['notes'=>$sr['notes']], ['notes'=>$yeni_aciklama], 'lite');
        flash('Açıklama güncellendi.');
    }
    header('Location: vehicle_detail.php?id='.$id); exit;
}

if (isset($_GET['yag_sil'])) {
    $sil_id = (int)$_GET['yag_sil'];
    $sr = $pdo->prepare('SELECT lk.*,u.product_code AS urun_kodu,u.product_name AS urun_adi FROM oil_records lk JOIN products u ON lk.product_id=u.id WHERE lk.id=? AND lk.vehicle_id=?');
    $sr->execute([$sil_id,$id]); $sr = $sr->fetch();
    if ($sr) {
        $pdo->prepare("UPDATE oil_records SET is_active=0 WHERE id=? AND vehicle_id=?")->execute([$sil_id,$id]);
        $log_msg = $arac['plate'].' aracından yağ kaydı silindi: '.$sr['urun_kodu'].' '.$sr['urun_adi'].', '.$sr['quantity'].'L';
        if ($sr['is_oil_change']) $log_msg .= ' [YAĞ BAKIMI]';
        if ($sr['notes'])         $log_msg .= '. Açıklama: '.$sr['notes'];
        logYaz($pdo,'sil','arac_kayit',$log_msg,$sil_id,$sr,null,'lite');
    }
    flash('Yağ kaydı silindi.');
    header('Location: vehicle_detail.php?id='.$id); exit;
}

$urunler = $pdo->query("SELECT * FROM products WHERE is_active=1 ORDER BY product_name")->fetchAll();

$yag_kayitlari = $pdo->prepare("
    SELECT lk.*,u.product_name AS urun_adi,u.product_code AS urun_kodu,k.full_name AS ad_soyad
    FROM oil_records lk
    JOIN products u ON lk.product_id=u.id
    LEFT JOIN users k ON lk.created_by=k.id
    WHERE lk.vehicle_id=? AND lk.is_active=1
    ORDER BY lk.date DESC, lk.created_at DESC
");
$yag_kayitlari->execute([$id]);
$yag_kayitlari = $yag_kayitlari->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1>
        <span>🚗</span>
        <?= htmlspecialchars($arac['plate']) ?>
        <span class="badge badge-info" style="font-size:13px;"><?= htmlspecialchars($arac['tur_adi'] ?? '—') ?></span>
    </h1>
    <a href="../../index.php" class="btn btn-secondary btn-sm">← Geri</a>
</div>

<div class="card" style="padding:14px 16px;margin-bottom:14px;">
    <div style="display:flex;gap:20px;flex-wrap:wrap;align-items:center;">
        <div>
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:700;">Marka / Model</div>
            <div style="font-weight:600;"><?= htmlspecialchars($arac['brand_model']) ?></div>
        </div>
        <div>
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:700;">Toplam Kayıt</div>
            <div style="font-weight:600;"><?= count($yag_kayitlari) ?> işlem</div>
        </div>
        <?php
        $son_bakim = null;
        foreach ($yag_kayitlari as $yk) {
            if ($yk['is_oil_change']) { $son_bakim = $yk; break; }
        }
        ?>
        <?php if (!empty($yag_kayitlari)): ?>
        <div>
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:700;">Son İşlem</div>
            <div style="font-weight:600;"><?= formatliTarih($yag_kayitlari[0]['created_at']) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($son_bakim): ?>
        <div>
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:700;">Son Yağ Bakımı</div>
            <div style="font-weight:600;color:var(--warning);">
                🔧 <?= formatliTarih($son_bakim['date']) ?>
                <?php if ($son_bakim['current_km']): ?> · <?= number_format($son_bakim['current_km']) ?> KM<?php endif; ?>
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
                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['product_code'].' - '.$u['product_name']) ?></option>
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
        <div id="kayit-<?= $k['id'] ?>" class="kayit-item" style="<?= $k['is_oil_change'] ? 'border-left:3px solid var(--warning);' : '' ?>">
            <div class="kayit-info">
                <div class="kayit-urun">
                    <?= htmlspecialchars($k['urun_adi']) ?>
                    <small><?= htmlspecialchars($k['urun_kodu']) ?></small>
                    <?php if ($k['is_oil_change']): ?>
                    <span style="background:var(--warning-l);color:var(--warning);font-size:10px;font-weight:700;padding:2px 6px;border-radius:4px;margin-left:4px;">🔧 BAKIM</span>
                    <?php endif; ?>
                </div>
                <div class="kayit-meta">
                    🛢️ <strong title="Yağın verildiği tarih"><?= formatliTarih($k['date']) ?></strong>
                    <span class="kayit-giris-tarihi">· 🕐 <?= formatliTarih($k['created_at']) ?></span>
                    · 👤 <?= htmlspecialchars($k['ad_soyad'] ?? '-') ?>
                    <?php if ($k['is_oil_change'] && $k['current_km']): ?>
                    · 🛣️ <?= number_format($k['current_km']) ?> KM
                    <?php endif; ?>
                    <?php if ($k['notes']): ?> · <?= htmlspecialchars($k['notes']) ?><?php endif; ?>
                </div>
            </div>
            <div class="kayit-miktar"><?= formatliMiktar($k['quantity']) ?></div>
            <button class="btn btn-sm btn-secondary" style="flex-shrink:0;font-size:11px;padding:4px 8px;"
                onclick="aciklamaModal(<?= $k['id'] ?>, '<?= htmlspecialchars($k['notes'] ?? '', ENT_QUOTES) ?>')" title="Açıklama düzenle">✏️</button>
            <button class="kayit-sil" onclick="if(confirm('Bu kaydı silmek istiyor musunuz?')) location.href='vehicle_detail.php?id=<?= $id ?>&yag_sil=<?= $k['id'] ?>'" title="Sil">🗑️</button>
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
