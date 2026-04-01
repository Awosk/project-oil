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
require_once __DIR__ . '/../../classes/SistemYedek.php';
adminKontrol();

$sayfa_basligi = 'Veritabanı Yedekleme';

$backup_dir = __DIR__ . DIRECTORY_SEPARATOR . 'db_backups';

if (!is_dir($backup_dir)) {
    $mkdir_ok = mkdir($backup_dir, 0755, true);
} else {
    $mkdir_ok = true;
}

$dir_yazilabilir = $mkdir_ok && is_writable($backup_dir);

$tablolar = SistemYedek::tablolariGetir($pdo);

// ═══ YEDEK AL ═══
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'backup') {
    csrfDogrula();
    if (!$dir_yazilabilir) {
        flash('Klasör oluşturulamadı veya yazılamıyor: ' . $backup_dir, 'danger');
        header('Location: backup.php'); exit;
    }
    $secili_tablolar = $_POST['tablolar'] ?? [];
    if (empty($secili_tablolar)) {
        flash('En az bir tablo seçmelisiniz.', 'danger');
        header('Location: backup.php'); exit;
    }

    $sonuc = SistemYedek::yedekAl($pdo, $secili_tablolar, $backup_dir);

    if ($sonuc === false) {
        flash('SQL dosyası kaydedilemedi: ' . $backup_dir, 'danger');
        header('Location: backup.php'); exit;
    }

    logYaz($pdo, 'ekle', 'sistem', 'Veritabanı yedeği alındı: ' . $sonuc['dosya_adi'] . ' (' . count($secili_tablolar) . ' tablo)', null, null, ['tablolar' => implode(',', $secili_tablolar)], 'lite');
    flash('Yedek başarıyla alındı: ' . $sonuc['dosya_adi'] . ' (' . $sonuc['boyut_kb'] . ' KB)');
    header('Location: backup.php'); exit;
}

// ═══ YEDEK İNDİR ═══
if (isset($_GET['indir'])) {
    $dosya = basename($_GET['indir']);
    $yol   = $backup_dir . DIRECTORY_SEPARATOR . $dosya;
    if (file_exists($yol) && str_ends_with($dosya, '.sql')) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $dosya . '"');
        header('Content-Length: ' . filesize($yol));
        readfile($yol);
        exit;
    }
    flash('Dosya bulunamadı.', 'danger');
    header('Location: backup.php'); exit;
}

// ═══ YEDEK SİL ═══
if (isset($_GET['sil'])) {
    $dosya = basename($_GET['sil']);
    $yol   = $backup_dir . DIRECTORY_SEPARATOR . $dosya;
    if (file_exists($yol) && str_ends_with($dosya, '.sql')) {
        unlink($yol);
        logYaz($pdo, 'sil', 'sistem', 'Veritabanı yedeği silindi: ' . $dosya, null, null, null, 'lite');
        flash('Yedek silindi.');
    }
    header('Location: backup.php'); exit;
}

// ═══ GERİ YÜKLE — Tablo seçim ekranı (GET) ═══
$restore_file     = null;
$restore_tablolar = [];
if (isset($_GET['geri_yukle'])) {
    $dosya = basename($_GET['geri_yukle']);
    $yol   = $backup_dir . DIRECTORY_SEPARATOR . $dosya;
    if (file_exists($yol) && str_ends_with($dosya, '.sql')) {
        $restore_file = $dosya;
        $icerik = file_get_contents($yol);
        preg_match_all('/^DROP TABLE IF EXISTS `([^`]+)`/m', $icerik, $m);
        $restore_tablolar = $m[1] ?? [];
    } else {
        flash('Dosya bulunamadı.', 'danger');
        header('Location: backup.php'); exit;
    }
}

// ═══ GERİ YÜKLE — Uygula (POST) ═══
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restore') {
    csrfDogrula();
    $dosya           = basename($_POST['dosya'] ?? '');
    $secili_tablolar = $_POST['tablolar'] ?? [];
    $yol             = $backup_dir . DIRECTORY_SEPARATOR . $dosya;

    if (!file_exists($yol) || empty($secili_tablolar)) {
        flash('Dosya bulunamadı veya tablo seçilmedi.', 'danger');
        header('Location: backup.php'); exit;
    }

    $sonuc = SistemYedek::geriYukle($pdo, $yol, $secili_tablolar);
    $basarili = $sonuc['basarili'];
    $hatalar = $sonuc['hatalar'];

    logYaz($pdo, 'guncelle', 'sistem', 'Veritabanı geri yüklendi: ' . $dosya . ' — ' . implode(', ', $basarili), null, null, ['tablolar' => implode(',', $basarili)], 'lite');

    if (empty($hatalar)) {
        flash(count($basarili) . ' tablo başarıyla geri yüklendi: ' . implode(', ', $basarili));
    } else {
        flash(count($basarili) . ' tablo yüklendi. Hatalar: ' . implode(' | ', $hatalar), 'danger');
    }
    header('Location: backup.php'); exit;
}

// Mevcut yedek dosyaları listele
$yedekler = SistemYedek::listele($backup_dir);

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1><span>💾</span> Veritabanı Yedekleme</h1>
</div>

<?php if (!$dir_yazilabilir): ?>
<div class="alert alert-danger">
    ⚠️ <strong>Klasör sorunu!</strong> Yedek klasörü oluşturulamıyor veya yazılamıyor.<br>
    <code><?= htmlspecialchars($backup_dir) ?></code><br>
    Bu klasörü elle oluşturun veya Apache/PHP'ye yazma izni verin.
</div>
<?php else: ?>
<div style="font-size:12px;color:var(--muted);margin-bottom:12px;">
    📁 Yedek klasörü: <code><?= htmlspecialchars($backup_dir) ?></code>
</div>
<?php endif; ?>

<?php if ($restore_file): ?>
<!-- ═══ GERİ YÜKLEME TABLO SEÇİM EKRANI ═══ -->
<div class="card" style="border:2px solid var(--warning);">
    <div class="card-title" style="color:var(--warning);">↩️ Geri Yükleme — <?= htmlspecialchars($restore_file) ?></div>
    <div class="warn-box">⚠️ <strong>Dikkat:</strong> Seçilen tablolar <strong>tamamen silinip</strong> yedekteki verilerle değiştirilecek. Bu işlem geri alınamaz.</div>
    <form method="post">
        <?= csrfInput() ?>
        <input type="hidden" name="action" value="restore">
        <input type="hidden" name="dosya" value="<?= htmlspecialchars($restore_file) ?>">
        <div style="margin-bottom:14px;">
            <div style="font-weight:700;margin-bottom:10px;">Geri yüklenecek tabloları seçin:</div>
            <div style="display:flex;gap:8px;margin-bottom:10px;">
                <button type="button" class="btn btn-sm btn-secondary" onclick="tumunuSec(true)">Tümünü Seç</button>
                <button type="button" class="btn btn-sm btn-secondary" onclick="tumunuSec(false)">Tümünü Kaldır</button>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <?php foreach ($restore_tablolar as $t): ?>
                <label style="display:flex;align-items:center;gap:6px;padding:7px 12px;border:2px solid var(--primary);border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;color:var(--primary);background:#e8f0fe;transition:.15px;"
                       id="lbl_<?= htmlspecialchars($t) ?>">
                    <input type="checkbox" name="tablolar[]" value="<?= htmlspecialchars($t) ?>" checked onchange="labelGuncelle(this)" style="width:16px;height:16px;accent-color:var(--primary);">
                    <span>📋 <?= htmlspecialchars($t) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div style="display:flex;gap:8px;margin-top:16px;">
            <button type="submit" class="btn btn-danger" onclick="return confirm('Seçilen tablolar geri yüklenecek. Mevcut veriler silinecek!\n\nEmin misiniz?')">↩️ Geri Yükle</button>
            <a href="backup.php" class="btn btn-secondary">İptal</a>
        </div>
    </form>
</div>
<script>
function tumunuSec(sec) { document.querySelectorAll('input[name="tablolar[]"]').forEach(cb => { cb.checked = sec; labelGuncelle(cb); }); }
function labelGuncelle(cb) {
    var lbl = document.getElementById('lbl_' + cb.value);
    if (!lbl) return;
    if (cb.checked) { lbl.style.borderColor='var(--primary)'; lbl.style.background='var(--primary-bg-l)'; lbl.style.color='var(--primary-text)'; }
    else { lbl.style.borderColor='var(--border)'; lbl.style.background='var(--card)'; lbl.style.color='var(--text)'; }
}
document.querySelectorAll('input[name="tablolar[]"]').forEach(cb => labelGuncelle(cb));
</script>
<?php else: ?>

<!-- ═══ ANA EKRAN ═══ -->
<div class="card">
    <div class="card-title">➕ Yeni Yedek Al</div>
    <form method="post">
        <?= csrfInput() ?>
        <input type="hidden" name="action" value="backup">
        <div style="margin-bottom:12px;">
            <div style="font-weight:700;margin-bottom:10px;">Yedeklenecek tabloları seçin:</div>
            <div style="display:flex;gap:8px;margin-bottom:10px;">
                <button type="button" class="btn btn-sm btn-secondary" onclick="tumunuSecB(true)">Tümünü Seç</button>
                <button type="button" class="btn btn-sm btn-secondary" onclick="tumunuSecB(false)">Tümünü Kaldır</button>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <?php foreach ($tablolar as $t): ?>
                <label style="display:flex;align-items:center;gap:6px;padding:7px 12px;border:2px solid var(--border);border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;color:var(--text);background:#fff;transition:.15s;" id="blbl_<?= htmlspecialchars($t) ?>">
                    <input type="checkbox" name="tablolar[]" value="<?= htmlspecialchars($t) ?>" checked onchange="bLabelGuncelle(this)" style="width:16px;height:16px;accent-color:var(--primary);">
                    <span>📋 <?= htmlspecialchars($t) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div style="margin-top:14px;display:flex;align-items:center;gap:12px;">
            <button type="submit" class="btn btn-primary">💾 Yedek Al</button>
            <span style="font-size:12px;color:var(--muted);">Yedek <code><?= htmlspecialchars(basename($backup_dir)) ?>/</code> klasörüne kaydedilir.</span>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-title">📂 Mevcut Yedekler <span style="font-weight:400;font-size:13px;color:var(--muted);">(<?= count($yedekler) ?>)</span></div>
    <?php if (empty($yedekler)): ?>
    <div style="text-align:center;padding:32px;color:var(--muted);"><div style="font-size:36px;margin-bottom:8px;">📭</div>Henüz yedek yok.</div>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Dosya Adı</th><th>Tarih</th><th>Boyut</th><th>İşlem</th></tr></thead>
            <tbody>
            <?php foreach ($yedekler as $y): ?>
            <tr>
                <td><code style="font-size:12px;"><?= htmlspecialchars($y['dosya']) ?></code></td>
                <td style="font-size:13px;color:var(--muted);"><?= $y['tarih'] ?></td>
                <td style="font-size:13px;color:var(--muted);"><?= $y['boyut'] ?> KB</td>
                <td style="display:flex;gap:6px;flex-wrap:wrap;">
                    <a href="?indir=<?= urlencode($y['dosya']) ?>" class="btn btn-sm btn-secondary">⬇️ İndir</a>
                    <a href="?geri_yukle=<?= urlencode($y['dosya']) ?>" class="btn btn-sm btn-primary">↩️ Geri Yükle</a>
                    <a href="?sil=<?= urlencode($y['dosya']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bu yedeği silmek istediğinizden emin misiniz?')">🗑️</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
function tumunuSecB(sec) { document.querySelectorAll('input[name="tablolar[]"]').forEach(cb => { cb.checked = sec; bLabelGuncelle(cb); }); }
function bLabelGuncelle(cb) {
    var lbl = document.getElementById('blbl_' + cb.value);
    if (!lbl) return;
    if (cb.checked) { lbl.style.borderColor='var(--primary)'; lbl.style.background='var(--primary-bg-l)'; lbl.style.color='var(--primary-text)'; }
    else { lbl.style.borderColor='var(--border)'; lbl.style.background='var(--card)'; lbl.style.color='var(--text)'; }
}
document.querySelectorAll('input[name="tablolar[]"]').forEach(cb => bLabelGuncelle(cb));
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
