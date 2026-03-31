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

$sayfa_basligi = 'Araç Türü Yönetimi';
$ku = mevcutKullanici();

// ── EKLE ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ekle'])) {
    csrfDogrula();
    $tur_adi  = trim($_POST['tur_adi']);
    $oncelik  = max(1, (int)($_POST['oncelik'] ?? 1));
    if ($tur_adi) {
        $mevcut = $pdo->prepare("SELECT * FROM vehicle_types WHERE type_name = ?");
        $mevcut->execute([$tur_adi]); $mevcut = $mevcut->fetch();
        if ($mevcut && $mevcut['is_active'] == 0) {
            $pdo->prepare("UPDATE vehicle_types SET is_active=1, priority=? WHERE id=?")->execute([$oncelik, $mevcut['id']]);
            logYaz($pdo,'ekle','arac_tur','Silinen araç türü reaktif edildi: '.$tur_adi, $mevcut['id'], null, ['type_name'=>$tur_adi,'priority'=>$oncelik], 'lite');
            flash('Daha önce silinmiş araç türü tekrar aktif edildi.');
        } elseif ($mevcut && $mevcut['aktif'] == 1) {
            flash('Bu araç türü zaten kayıtlı.', 'danger');
        } else {
            $pdo->prepare("INSERT INTO vehicle_types (type_name, priority) VALUES (?, ?)")->execute([$tur_adi, $oncelik]);
            $yeni_id = $pdo->lastInsertId();
            logYaz($pdo,'ekle','arac_tur','Araç türü eklendi: '.$tur_adi.' (öncelik:'.$oncelik.')', $yeni_id, null, ['type_name'=>$tur_adi,'priority'=>$oncelik], 'lite');
            flash('Araç türü eklendi.');
        }
    } else {
        flash('Tür adı zorunludur.', 'danger');
    }
    header('Location: vehicle_types.php'); exit;
}

// ── DÜZENLE ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['duzenle'])) {
    csrfDogrula();
    $did     = (int)$_POST['duzenle_id'];
    $tur_adi = trim($_POST['duzenle_tur_adi']);
    $oncelik = max(1, (int)($_POST['duzenle_oncelik'] ?? 1));
    if ($did && $tur_adi) {
        $sr = $pdo->prepare('SELECT * FROM vehicle_types WHERE id=?');
        $sr->execute([$did]); $sr = $sr->fetch();
        $cakisma = $pdo->prepare("SELECT id FROM vehicle_types WHERE type_name=? AND id!=? AND is_active=1");
        $cakisma->execute([$tur_adi, $did]);
        if ($cakisma->fetch()) {
            flash('Bu araç türü adı zaten kullanımda.', 'danger');
        } else {
            $pdo->prepare("UPDATE vehicle_types SET type_name=?, priority=? WHERE id=?")->execute([$tur_adi, $oncelik, $did]);
            logYaz($pdo,'guncelle','arac_tur','Araç türü güncellendi: '.$tur_adi.' (öncelik:'.$oncelik.')', $did,
                ['type_name' => $sr['type_name'], 'priority' => $sr['priority']],
                ['type_name' => $tur_adi, 'priority' => $oncelik], 'lite');
            flash('Araç türü güncellendi.');
        }
    } else {
        flash('Tür adı zorunludur.', 'danger');
    }
    header('Location: vehicle_types.php'); exit;
}

// ── SİL ──
if (isset($_GET['sil'])) {
    $sil_id = (int)$_GET['sil'];
    $kullanimda = $pdo->prepare("SELECT COUNT(*) FROM vehicles WHERE vehicle_type_id=? AND is_active=1");
    $kullanimda->execute([$sil_id]);
    if ((int)$kullanimda->fetchColumn() > 0) {
        flash('Bu araç türü aktif araçlarda kullanılıyor, silinemez.', 'danger');
    } else {
        $sr = $pdo->prepare('SELECT * FROM vehicle_types WHERE id=?');
        $sr->execute([$sil_id]); $sr = $sr->fetch();
        $pdo->prepare("UPDATE vehicle_types SET is_active=0 WHERE id=?")->execute([$sil_id]);
        if ($sr) logYaz($pdo,'sil','arac_tur','Araç türü silindi: '.$sr['type_name'], $sil_id, $sr, null, 'lite');
        flash('Araç türü silindi.');
    }
    header('Location: vehicle_types.php'); exit;
}

$turler = $pdo->query("
    SELECT t.*, COUNT(a.id) AS arac_sayisi
    FROM vehicle_types t
    LEFT JOIN vehicles a ON a.vehicle_type_id = t.id AND a.is_active = 1
    WHERE t.is_active = 1
    GROUP BY t.id
    ORDER BY t.priority DESC, t.type_name
")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1><span>🚗</span> Araç Türü Yönetimi</h1>
</div>

<div class="card">
    <div class="card-title">➕ Yeni Araç Türü Ekle</div>
    <form method="post">
        <?= csrfInput() ?>
        <div class="form-grid">
            <div class="form-group">
                <label>Araç Türü Adı *</label>
                <input type="text" name="tur_adi" required placeholder="Örn: Damper" maxlength="100" autofocus>
            </div>
            <div class="form-group">
                <label>Öncelik <span style="font-weight:400;color:var(--muted);font-size:11px;">(büyük = üstte)</span></label>
                <input type="number" name="oncelik" min="1" value="1" placeholder="1">
            </div>
        </div>
        <div style="margin-top:14px;">
            <button type="submit" name="ekle" class="btn btn-primary">💾 Ekle</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-title">📋 Kayıtlı Araç Türleri (<?= count($turler) ?>)</div>
    <?php if (empty($turler)): ?>
    <div class="alert alert-info">Henüz araç türü kaydı yok.</div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Öncelik</th>
                    <th>Tür Adı</th>
                    <th>Araç Sayısı</th>
                    <th>İşlem</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($turler as $t): ?>
            <tr>
                <td>
                    <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:var(--primary);color:#fff;font-size:12px;font-weight:700;">
                        <?= (int)$t['priority'] ?>
                    </span>
                </td>
                <td><strong><?= htmlspecialchars($t['type_name']) ?></strong></td>
                <td>
                    <?php if ($t['arac_sayisi'] > 0): ?>
                    <span class="badge badge-info"><?= $t['arac_sayisi'] ?> araç</span>
                    <?php else: ?>
                    <span style="color:var(--muted);font-size:12px;">Kullanılmıyor</span>
                    <?php endif; ?>
                </td>
                <td style="display:flex;gap:6px;flex-wrap:wrap;">
                    <button class="btn btn-sm btn-secondary"
                        onclick="turDuzenleModal(<?= $t['id'] ?>, '<?= htmlspecialchars($t['type_name'], ENT_QUOTES) ?>', <?= (int)$t['priority'] ?>)">
                        ✏️ Düzenle
                    </button>
                    <?php if ($t['arac_sayisi'] == 0): ?>
                    <a href="?sil=<?= $t['id'] ?>" class="btn btn-sm btn-danger"
                       onclick="return confirm('Bu araç türünü silmek istiyor musunuz?')">🗑️ Sil</a>
                    <?php else: ?>
                    <button class="btn btn-sm btn-danger" disabled title="Aktif araçlarda kullanılıyor" style="opacity:.4;cursor:not-allowed;">🗑️ Sil</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Düzenleme Modal -->
<div id="turDuzenleModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;">
    <div class="modal-box" style="max-width:400px;">
        <div style="font-weight:700;font-size:16px;margin-bottom:16px;">✏️ Araç Türü Düzenle</div>
        <form method="post">
            <?= csrfInput() ?>
            <input type="hidden" name="duzenle_id" id="duzenle_tur_id">
            <div class="form-group">
                <label>Tür Adı *</label>
                <input type="text" name="duzenle_tur_adi" id="duzenle_tur_adi" required maxlength="100">
            </div>
            <div class="form-group">
                <label>Öncelik <span style="font-weight:400;color:var(--muted);font-size:11px;">(küçük = üstte)</span></label>
                <input type="number" name="duzenle_oncelik" id="duzenle_oncelik" min="1">
            </div>
            <div style="display:flex;gap:8px;margin-top:16px;">
                <button type="submit" name="duzenle" class="btn btn-primary" style="flex:1;">💾 Kaydet</button>
                <button type="button" class="btn btn-secondary"
                    onclick="document.getElementById('turDuzenleModal').style.display='none'">İptal</button>
            </div>
        </form>
    </div>
</div>

<script>
function turDuzenleModal(id, tur_adi, oncelik) {
    document.getElementById('duzenle_tur_id').value   = id;
    document.getElementById('duzenle_tur_adi').value  = tur_adi;
    document.getElementById('duzenle_oncelik').value  = oncelik;
    var m = document.getElementById('turDuzenleModal');
    m.style.display = 'flex';
    setTimeout(() => document.getElementById('duzenle_tur_adi').focus(), 50);
}
document.getElementById('turDuzenleModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>