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
if (!$id) { header('Location: facilities.php'); exit; }
$ku = mevcutKullanici();

$tesis = $pdo->prepare("SELECT * FROM facilities WHERE id=? AND is_active=1");
$tesis->execute([$id]);
$tesis = $tesis->fetch();
if (!$tesis) { flash('Tesis bulunamadı.', 'danger'); header('Location: facilities.php'); exit; }

$sayfa_basligi = $tesis['name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ekle'])) {
    csrfDogrula();
    $urun_id  = (int)$_POST['urun_id'];
    $miktar   = (float)str_replace(',', '.', $_POST['miktar']);
    $tarih    = $_POST['tarih'];
    $aciklama = trim($_POST['aciklama'] ?? '');

    if ($urun_id && $miktar > 0 && $tarih) {
        $stmt = $pdo->prepare("
            INSERT INTO oil_records (record_type, facility_id, product_id, quantity, date, notes, created_by)
            VALUES ('tesis', ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$id, $urun_id, $miktar, $tarih, $aciklama ?: null, $ku['id']]);
        $yeni_id = $pdo->lastInsertId();
        $urun_adi_log = $pdo->prepare('SELECT product_code, product_name FROM products WHERE id=?'); $urun_adi_log->execute([$urun_id]); $urun_adi_log = $urun_adi_log->fetch();
        logYaz($pdo,'ekle','tesis_kayit',
            $tesis['name'].' tesisine yağ eklendi: '.($urun_adi_log['product_code']??'').' - '.($urun_adi_log['product_name']??'').', '.$miktar.'L, tarih:'.$tarih.'. Açıklama: '.($aciklama ?? 'Yok'),
            $yeni_id, null,
            ['facility_id'=>$id,'name'=>$tesis['name'],'product_id'=>$urun_id,'quantity'=>$miktar,'date'=>$tarih,'notes'=>$aciklama],
            'lite');
        flash('Kayıt eklendi.');
    } else {
        flash('Ürün, miktar ve tarih zorunludur.', 'danger');
    }
    header('Location: facility_detail.php?id=' . $id); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aciklama_guncelle'])) {
    csrfDogrula();
    $kayit_id      = (int)$_POST['kayit_id'];
    $yeni_aciklama = trim($_POST['aciklama_yeni'] ?? '');
    $sr = $pdo->prepare('SELECT lk.*,u.product_code AS urun_kodu,u.product_name AS urun_adi FROM oil_records lk JOIN products u ON lk.product_id=u.id WHERE lk.id=? AND lk.facility_id=? AND lk.is_active=1');
    $sr->execute([$kayit_id, $id]); $sr = $sr->fetch();
    if ($sr) {
        $pdo->prepare("UPDATE oil_records SET notes=? WHERE id=?")->execute([$yeni_aciklama ?: null, $kayit_id]);
        logYaz($pdo,'guncelle','tesis_kayit', $tesis['name'].' Adlı Firmanın '.$sr['urun_kodu'].' kaydının açıklaması güncellendi', $kayit_id, ['notes'=>$sr['notes']], ['notes'=>$yeni_aciklama], 'lite');
        flash('Açıklama güncellendi.');
    }
    header('Location: facility_detail.php?id='.$id); exit;
}

if (isset($_GET['sil'])) {
    $sil_id = (int)$_GET['sil'];
    $sr = $pdo->prepare('SELECT lk.*, u.product_code AS urun_kodu, u.product_name AS urun_adi FROM oil_records lk JOIN products u ON lk.product_id=u.id WHERE lk.id=? AND lk.facility_id=?');
    $sr->execute([$sil_id, $id]); $sr = $sr->fetch();
    $pdo->prepare("UPDATE oil_records SET is_active=0 WHERE id=? AND facility_id=?")->execute([$sil_id, $id]);
    if ($sr) logYaz($pdo,'sil','tesis_kayit',
        $tesis['name'].' tesisinden yağ kaydı silindi: '.$sr['urun_kodu'].' - '.$sr['urun_adi'].', '.$sr['quantity'].'L, tarih:'.$sr['date'].'. Açıklama: '.($sr['notes'] ?? 'Yok'),
        $sil_id, $sr, null, 'lite');
    flash('Kayıt silindi.');
    header('Location: facility_detail.php?id=' . $id); exit;
}

$urunler  = $pdo->query("SELECT * FROM products WHERE is_active=1 ORDER BY product_name")->fetchAll();
$kayitlar = $pdo->prepare("
    SELECT lk.*, u.product_name AS urun_adi, u.product_code AS urun_kodu, k.full_name AS ad_soyad
    FROM oil_records lk
    JOIN products u ON lk.product_id = u.id
    LEFT JOIN users k ON lk.created_by = k.id
    WHERE lk.facility_id = ? AND lk.is_active = 1
    ORDER BY lk.date DESC, lk.created_at DESC
");
$kayitlar->execute([$id]);
$kayitlar = $kayitlar->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1><span>🏭</span> <?= htmlspecialchars($tesis['name']) ?></h1>
    <a href="facilities.php" class="btn btn-secondary btn-sm">← Geri</a>
</div>

<div class="card" style="padding:14px 16px; margin-bottom:14px;">
    <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
        <div>
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:700;">Adres</div>
            <div style="font-weight:600;"><?= htmlspecialchars($tesis['address']) ?></div>
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
                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['product_code'] . ' - ' . $u['product_name']) ?></option>
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
                    🛢️ <strong title="Yağın verildiği tarih"><?= formatliTarih($k['date']) ?></strong>
                    <span class="kayit-giris-tarihi">· 🕐 <?= formatliTarih($k['created_at']) ?></span>
                    · 👤 <?= htmlspecialchars($k['ad_soyad'] ?? '-') ?>
                    <?php if ($k['notes']): ?> · <?= htmlspecialchars($k['notes']) ?><?php endif; ?>
                </div>
            </div>
            <div class="kayit-miktar"><?= formatliMiktar($k['quantity']) ?></div>
            <button class="btn btn-sm btn-secondary" style="flex-shrink:0;font-size:11px;padding:4px 8px;"
                onclick="aciklamaModal(<?= $k['id'] ?>, '<?= htmlspecialchars($k['notes'] ?? '', ENT_QUOTES) ?>')" title="Açıklama düzenle">✏️</button>
            <button class="kayit-sil" onclick="if(confirm('Bu kaydı silmek istiyor musunuz?')) location.href='tesis_detay.php?id=<?= $id ?>&sil=<?= $k['id'] ?>'" title="Sil">🗑️</button>
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
