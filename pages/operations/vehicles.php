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

$sayfa_basligi = 'Araç Yönetimi';
$ku = mevcutKullanici();

// ── EKLE ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ekle'])) {
    csrfDogrula();
    $tur_id = (int)$_POST['arac_turu_id'];
    $plaka  = strtoupper(trim($_POST['plaka']));
    $model  = trim($_POST['marka_model']);
    if ($tur_id && $plaka && $model) {
        $mevcut = $pdo->prepare("SELECT * FROM vehicles WHERE plate=?");
        $mevcut->execute([$plaka]); $mevcut = $mevcut->fetch();
        // Tür adını log için çek
        $tur_adi = $pdo->prepare("SELECT type_name FROM vehicle_types WHERE id=?");
        $tur_adi->execute([$tur_id]); $tur_adi = $tur_adi->fetchColumn();

        if ($mevcut && $mevcut['is_active'] == 0) {
            $pdo->prepare("UPDATE vehicles SET vehicle_type_id=?, brand_model=?, created_by=?, is_active=1 WHERE id=?")
                ->execute([$tur_id, $model, $ku['id'], $mevcut['id']]);
            logYaz($pdo,'ekle','arac','Silinen araç reaktif edildi: '.$plaka.' ('.$tur_adi.') - '.$model, $mevcut['id'], null, ['tur_id'=>$tur_id,'plaka'=>$plaka,'model'=>$model], 'lite');
            flash('Daha önce silinmiş araç tekrar aktif edildi.');
        } elseif ($mevcut && $mevcut['aktif'] == 1) {
            flash('Bu plaka zaten kayıtlı.', 'danger');
        } else {
            try {
                $pdo->prepare("INSERT INTO vehicles (vehicle_type_id, plate, brand_model, created_by) VALUES (?,?,?,?)")
                    ->execute([$tur_id, $plaka, $model, $ku['id']]);
                $yeni_id = $pdo->lastInsertId();
                logYaz($pdo,'ekle','arac','Araç eklendi: '.$plaka.' ('.$tur_adi.') - '.$model, $yeni_id, null, ['tur_id'=>$tur_id,'plaka'=>$plaka,'model'=>$model], 'lite');
                flash('Araç eklendi.');
            } catch (PDOException $e) { flash('Bir hata oluştu.', 'danger'); }
        }
    } else { flash('Tüm alanlar zorunludur.', 'danger'); }
    header('Location: vehicles.php'); exit;
}

// ── DÜZENLE ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['duzenle'])) {
    csrfDogrula();
    $did    = (int)$_POST['duzenle_id'];
    $tur_id = (int)$_POST['duzenle_tur_id'];
    $plaka  = strtoupper(trim($_POST['duzenle_plaka']));
    $model  = trim($_POST['duzenle_model']);
    if ($did && $tur_id && $plaka && $model) {
        $sr = $pdo->prepare('SELECT * FROM vehicles WHERE id=?'); $sr->execute([$did]); $sr = $sr->fetch();
        $cakisma = $pdo->prepare("SELECT id FROM vehicles WHERE plate=? AND id!=? AND is_active=1");
        $cakisma->execute([$plaka, $did]);
        if ($cakisma->fetch()) {
            flash('Bu plaka başka bir araçta kayıtlı.', 'danger');
        } else {
            $tur_adi = $pdo->prepare("SELECT type_name FROM vehicle_types WHERE id=?");
            $tur_adi->execute([$tur_id]); $tur_adi = $tur_adi->fetchColumn();
            $pdo->prepare("UPDATE vehicles SET vehicle_type_id=?, plate=?, brand_model=? WHERE id=?")
                ->execute([$tur_id, $plaka, $model, $did]);
            logYaz($pdo,'guncelle','arac','Araç güncellendi: '.$plaka, $did,
                ['vehicle_type_id'=>$sr['vehicle_type_id'],'plate'=>$sr['plate'],'brand_model'=>$sr['brand_model']],
                ['vehicle_type_id'=>$tur_id,'plate'=>$plaka,'brand_model'=>$model], 'lite');
            flash('Araç güncellendi.');
        }
    } else { flash('Tüm alanlar zorunludur.', 'danger'); }
    header('Location: vehicles.php'); exit;
}

// ── SİL ──
if (isset($_GET['sil'])) {
    $sil_id = (int)$_GET['sil'];
    $sr = $pdo->prepare('SELECT a.*, t.type_name AS tur_adi FROM vehicles a LEFT JOIN vehicle_types t ON a.vehicle_type_id=t.id WHERE a.id=?');
    $sr->execute([$sil_id]); $sr = $sr->fetch();
    $pdo->prepare("UPDATE vehicles SET is_active=0 WHERE id=?")->execute([$sil_id]);
    if ($sr) logYaz($pdo,'sil','arac','Araç silindi: '.$sr['plate'].' - '.$sr['brand_model'], $sil_id, $sr, null, 'lite');
    flash('Araç silindi.');
    header('Location: vehicles.php'); exit;
}

$araclar  = $pdo->query("
    SELECT a.*, t.type_name AS tur_adi, k.full_name AS ad_soyad
    FROM vehicles a
    LEFT JOIN vehicle_types t ON a.vehicle_type_id = t.id
    LEFT JOIN users k ON a.created_by = k.id
    WHERE a.is_active = 1
    ORDER BY t.type_name, a.plate
")->fetchAll();

$arac_turleri = $pdo->query("SELECT id, type_name AS tur_adi FROM vehicle_types WHERE is_active=1 ORDER BY type_name")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1><span>🚗</span> Araç Yönetimi</h1>
    <a href="vehicle_types.php" class="btn btn-secondary btn-sm">⚙️ Araç Türleri</a>
</div>

<?php if (empty($arac_turleri)): ?>
<div style="background:var(--warning-l);border:1.5px solid var(--warning);border-radius:var(--r-sm);padding:16px 20px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div style="display:flex;align-items:center;gap:10px;">
        <span style="font-size:22px;">⚠️</span>
        <div>
            <div style="font-weight:700;color:var(--warning-text);font-size:14px;">Araç türü tanımlanmamış</div>
            <div style="font-size:12px;color:var(--warning-text);opacity:.85;margin-top:2px;">Araç ekleyebilmek için önce en az bir araç türü oluşturmalısınız.</div>
        </div>
    </div>
    <a href="vehicle_types.php" class="btn btn-sm" style="background:var(--warning);color:#fff;white-space:nowrap;">⚙️ Araç Türü Ekle →</a>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-title">➕ Yeni Araç Ekle</div>
    <form method="post">
        <?= csrfInput() ?>
        <div class="form-grid">
            <div class="form-group">
                <label>Araç Türü *</label>
                <select name="arac_turu_id" required <?= empty($arac_turleri) ? 'disabled' : '' ?>>
                    <option value="">-- Seçin --</option>
                    <?php foreach ($arac_turleri as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['type_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Plaka *</label>
                <input type="text" name="plaka" required placeholder="Örn: 34 ABC 123" maxlength="20">
            </div>
            <div class="form-group">
                <label>Marka / Model *</label>
                <input type="text" name="marka_model" required placeholder="Örn: Ford Cargo 1848T" maxlength="150">
            </div>
        </div>
        <div style="margin-top:14px;">
            <button type="submit" name="ekle" class="btn btn-primary" <?= empty($arac_turleri) ? 'disabled' : '' ?>>💾 Ekle</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-title">📋 Kayıtlı Araçlar (<?= count($araclar) ?>)</div>
    <?php if (empty($araclar)): ?>
    <div class="alert alert-info">Henüz araç kaydı yok.</div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Tür</th><th>Plaka</th><th>Marka/Model</th><th>İşlem</th></tr></thead>
            <tbody>
            <?php foreach ($araclar as $i => $a): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><span class="badge badge-info"><?= htmlspecialchars($a['tur_adi'] ?? $a['type_name'] ?? '—') ?></span></td>
                <td><strong><?= htmlspecialchars($a['plate']) ?></strong></td>
                <td><?= htmlspecialchars($a['brand_model']) ?></td>
                <td style="display:flex;gap:6px;flex-wrap:wrap;">
                    <a href="vehicle_detail.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-primary">👁️ Detay</a>
                    <button class="btn btn-sm btn-secondary"
                        onclick="aracDuzenleModal(<?= $a['id'] ?>, <?= $a['vehicle_type_id'] ?>, '<?= htmlspecialchars($a['plate'], ENT_QUOTES) ?>', '<?= htmlspecialchars($a['brand_model'], ENT_QUOTES) ?>')">
                        ✏️ Düzenle
                    </button>
                    <a href="?sil=<?= $a['id'] ?>" class="btn btn-sm btn-danger"
                       onclick="return confirm('Silmek istediğinize emin misiniz?')">🗑️</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Düzenleme Modal -->
<div id="aracDuzenleModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;">
    <div class="modal-box" style="max-width:460px;">
        <div style="font-weight:700;font-size:16px;margin-bottom:16px;">✏️ Araç Düzenle</div>
        <form method="post">
            <?= csrfInput() ?>
            <input type="hidden" name="duzenle_id" id="duzenle_arac_id">
            <div class="form-group">
                <label>Araç Türü *</label>
                <select name="duzenle_tur_id" id="duzenle_tur_id" required>
                    <?php foreach ($arac_turleri as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['type_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Plaka *</label>
                <input type="text" name="duzenle_plaka" id="duzenle_plaka" required maxlength="20">
            </div>
            <div class="form-group">
                <label>Marka / Model *</label>
                <input type="text" name="duzenle_model" id="duzenle_model" required maxlength="150">
            </div>
            <div style="display:flex;gap:8px;margin-top:16px;">
                <button type="submit" name="duzenle" class="btn btn-primary" style="flex:1;">💾 Kaydet</button>
                <button type="button" class="btn btn-secondary"
                    onclick="document.getElementById('aracDuzenleModal').style.display='none'">İptal</button>
            </div>
        </form>
    </div>
</div>

<script>
function aracDuzenleModal(id, tur_id, plaka, model) {
    document.getElementById('duzenle_arac_id').value  = id;
    document.getElementById('duzenle_tur_id').value   = tur_id;
    document.getElementById('duzenle_plaka').value    = plaka;
    document.getElementById('duzenle_model').value    = model;
    var m = document.getElementById('aracDuzenleModal');
    m.style.display = 'flex';
}
document.getElementById('aracDuzenleModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>