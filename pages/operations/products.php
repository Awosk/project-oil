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
require_once __DIR__ . '/../../classes/Urun.php';

girisKontrol();

$sayfa_basligi = 'Ürün Yönetimi';
$ku = mevcutKullanici();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ekle'])) {
    csrfDogrula();
    $kod = strtoupper(trim($_POST['urun_kodu']));
    $adi = trim($_POST['urun_adi']);
    
    if ($kod && $adi) {
        $mevcut = Urun::bulKod($pdo, $kod);
        
        if ($mevcut && $mevcut['aktif'] == 0) {
            Urun::reaktifEt($pdo, $mevcut['id'], $adi, $ku['id']);
            logYaz($pdo,'ekle','urun','Silinen ürün reaktif edildi: '.$kod.' - '.$adi, $mevcut['id'], null, ['kod'=>$kod,'adi'=>$adi], 'lite');
            flash('Daha önce silinmiş ürün tekrar aktif edildi.');
        } elseif ($mevcut && $mevcut['aktif'] == 1) {
            flash('Bu ürün kodu zaten kayıtlı.', 'danger');
        } else {
            try {
                $yeni_id = Urun::ekle($pdo, $kod, $adi, $ku['id']);
                logYaz($pdo,'ekle','urun','Ürün eklendi: '.$kod.' - '.$adi, $yeni_id, null, ['kod'=>$kod,'adi'=>$adi], 'lite');
                flash('Ürün eklendi.');
            } catch (PDOException $e) { flash('Bir hata oluştu.', 'danger'); }
        }
    } else { flash('Tüm alanlar zorunludur.', 'danger'); }
    header('Location: products.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['duzenle'])) {
    csrfDogrula();
    $did = (int)$_POST['duzenle_id'];
    $kod = strtoupper(trim($_POST['duzenle_kod']));
    $adi = trim($_POST['duzenle_adi']);
    
    if ($did && $kod && $adi) {
        $sr = Urun::bulId($pdo, $did);
        $cakisma = Urun::kodCakismaVarMi($pdo, $kod, $did);
        
        if ($cakisma) {
            flash('Bu ürün kodu başka bir üründe kayıtlı.', 'danger');
        } else {
            Urun::guncelle($pdo, $did, $kod, $adi);
            logYaz($pdo,'guncelle','urun','Ürün güncellendi: '.$kod.' - '.$adi, $did,
                ['urun_kodu'=>$sr['urun_kodu'],'urun_adi'=>$sr['urun_adi']],
                ['urun_kodu'=>$kod,'urun_adi'=>$adi], 'lite');
            flash('Ürün güncellendi.');
        }
    } else { flash('Tüm alanlar zorunludur.', 'danger'); }
    header('Location: products.php'); exit;
}

if (isset($_GET['sil'])) {
    $sil_id = (int)$_GET['sil'];
    $sr = Urun::bulId($pdo, $sil_id);
    Urun::sil($pdo, $sil_id);
    if ($sr) logYaz($pdo,'sil','urun','Ürün silindi: '.$sr['urun_kodu'].' - '.$sr['urun_adi'], $sil_id, $sr, null, 'lite');
    flash('Ürün silindi.');
    header('Location: products.php'); exit;
}

$urunler = Urun::listeleDetayli($pdo);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1><span>🛢️</span> Ürün Yönetimi</h1>
</div>

<div class="card">
    <div class="card-title">➕ Yeni Ürün Ekle</div>
    <form method="post">
        <?= csrfInput() ?>
        <div class="form-grid">
            <div class="form-group">
                <label>Ürün Kodu *</label>
                <input type="text" name="urun_kodu" required placeholder="Örn: ATF-DEXRON" maxlength="50">
            </div>
            <div class="form-group">
                <label>Ürün Adı *</label>
                <input type="text" name="urun_adi" required placeholder="Örn: ATF Dexron III Şanzıman Yağı" maxlength="200">
            </div>
        </div>
        <div style="margin-top:14px;">
            <button type="submit" name="ekle" class="btn btn-primary">💾 Ekle</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-title">📋 Kayıtlı Ürünler (<?= count($urunler) ?>)</div>
    <?php if (empty($urunler)): ?>
    <div class="alert alert-info">Henüz ürün kaydı yok.</div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Kod</th><th>Ürün Adı</th><th>Ekleyen</th><th>İşlem</th></tr></thead>
            <tbody>
            <?php foreach ($urunler as $i => $u): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><strong><?= htmlspecialchars($u['urun_kodu']) ?></strong></td>
                <td><?= htmlspecialchars($u['urun_adi']) ?></td>
                <td><?= htmlspecialchars($u['ad_soyad'] ?? '-') ?></td>
                <td style="display:flex;gap:6px;flex-wrap:wrap;">
                    <button class="btn btn-sm btn-secondary"
                        onclick="urunDuzenleModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['urun_kodu'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['urun_adi'], ENT_QUOTES) ?>')">✏️ Düzenle</button>
                    <a href="?sil=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Silmek istediğinize emin misiniz?')">🗑️ Sil</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Düzenleme Modal -->
<div id="urunDuzenleModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;">
    <div class="modal-box" style="max-width:420px;">
        <div style="font-weight:700;font-size:16px;margin-bottom:16px;">✏️ Ürün Düzenle</div>
        <form method="post">
            <?= csrfInput() ?>
            <input type="hidden" name="duzenle_id" id="duzenle_urun_id">
            <div class="form-group">
                <label>Ürün Kodu *</label>
                <input type="text" name="duzenle_kod" id="duzenle_urun_kod" required maxlength="50">
            </div>
            <div class="form-group">
                <label>Ürün Adı *</label>
                <input type="text" name="duzenle_adi" id="duzenle_urun_adi" required maxlength="200">
            </div>
            <div style="display:flex;gap:8px;margin-top:16px;">
                <button type="submit" name="duzenle" class="btn btn-primary" style="flex:1;">💾 Kaydet</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('urunDuzenleModal').style.display='none'">İptal</button>
            </div>
        </form>
    </div>
</div>

<script>
function urunDuzenleModal(id, kod, adi) {
    document.getElementById('duzenle_urun_id').value = id;
    document.getElementById('duzenle_urun_kod').value = kod;
    document.getElementById('duzenle_urun_adi').value = adi;
    var m = document.getElementById('urunDuzenleModal');
    m.style.display = 'flex';
    setTimeout(() => document.getElementById('duzenle_urun_adi').focus(), 50);
}
document.getElementById('urunDuzenleModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
