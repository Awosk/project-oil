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
adminKontrol();

$sayfa_basligi = 'Veritabanı Yedekleme';

$backup_dir = __DIR__ . DIRECTORY_SEPARATOR . 'db_backups';

if (!is_dir($backup_dir)) {
    $mkdir_ok = mkdir($backup_dir, 0755, true);
} else {
    $mkdir_ok = true;
}

$dir_yazilabilir = $mkdir_ok && is_writable($backup_dir);

$tablolar = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

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

    $sql  = "-- " . SITE_ADI . " Veritabanı Yedeği\n";
    $sql .= "-- Tarih: " . date('d.m.Y H:i:s') . "\n";
    $sql .= "-- Veritabanı: " . DB_NAME . "\n";
    $sql .= "-- Tablolar: " . implode(', ', $secili_tablolar) . "\n\n";
    $sql .= "SET NAMES utf8mb4;\n";
    $sql .= "SET foreign_key_checks = 0;\n\n";

    foreach ($secili_tablolar as $tablo) {
        if (!in_array($tablo, $tablolar)) continue;
        $create = $pdo->query("SHOW CREATE TABLE `$tablo`")->fetch();
        $sql .= "-- ─────────────────────────────────\n";
        $sql .= "DROP TABLE IF EXISTS `$tablo`;\n";
        $sql .= $create['Create Table'] . ";\n\n";
        $rows = $pdo->query("SELECT * FROM `$tablo`")->fetchAll();
        if (!empty($rows)) {
            $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';
            $sql .= "INSERT INTO `$tablo` ($cols) VALUES\n";
            $inserts = [];
            foreach ($rows as $row) {
                $vals = array_map(function($v) use ($pdo) {
                    if ($v === null) return 'NULL';
                    return $pdo->quote($v);
                }, array_values($row));
                $inserts[] = '(' . implode(', ', $vals) . ')';
            }
            $sql .= implode(",\n", $inserts) . ";\n\n";
        }
    }
    $sql .= "SET foreign_key_checks = 1;\n";

    $dosya_adi  = 'backup_' . date('Ymd_His') . '_' . DB_NAME . '.sql';
    $dosya_yolu = $backup_dir . DIRECTORY_SEPARATOR . $dosya_adi;
    $yazildi    = file_put_contents($dosya_yolu, $sql);

    if ($yazildi === false) {
        flash('SQL dosyası kaydedilemedi: ' . $dosya_yolu, 'danger');
        header('Location: backup.php'); exit;
    }

    logYaz($pdo, 'ekle', 'sistem', 'Veritabanı yedeği alındı: ' . $dosya_adi . ' (' . count($secili_tablolar) . ' tablo)', null, null, ['tablolar' => implode(',', $secili_tablolar)], 'lite');
    flash('Yedek başarıyla alındı: ' . $dosya_adi . ' (' . round($yazildi/1024,1) . ' KB)');
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

    $icerik  = file_get_contents($yol);
    $bloklar = [];
    $satirlar = explode("\n", str_replace("\r\n", "\n", $icerik));
    $aktif_tablo = null;
    $aktif_blok  = [];

    foreach ($satirlar as $satir) {
        if (preg_match('/^DROP TABLE IF EXISTS `([^`]+)`/i', $satir, $m)) {
            if ($aktif_tablo !== null) { $bloklar[$aktif_tablo] = implode("\n", $aktif_blok); }
            $aktif_tablo = $m[1]; $aktif_blok = [$satir];
        } elseif ($aktif_tablo !== null) {
            if (str_starts_with(trim($satir), 'SET foreign_key_checks = 1')) {
                $bloklar[$aktif_tablo] = implode("\n", $aktif_blok);
                $aktif_tablo = null; $aktif_blok = [];
            } else { $aktif_blok[] = $satir; }
        }
    }
    if ($aktif_tablo !== null && !empty($aktif_blok)) { $bloklar[$aktif_tablo] = implode("\n", $aktif_blok); }

    $pdo->exec("SET foreign_key_checks = 0");
    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec("SET SESSION wait_timeout = 300");
    $pdo->exec("SET SESSION interactive_timeout = 300");
    $pdo->exec("SET SESSION net_write_timeout = 300");
    $pdo->exec("SET SESSION net_read_timeout = 300");

    $hatalar  = [];
    $basarili = [];

    foreach ($secili_tablolar as $tablo) {
        if (!isset($bloklar[$tablo])) { $hatalar[] = $tablo . ': Yedekte bulunamadı'; continue; }
        $blok = trim($bloklar[$tablo]);
        $ifadeler = [];
        $tmp = ''; $tirnak = false; $tirnak_char = ''; $blok_len = strlen($blok);
        for ($i = 0; $i < $blok_len; $i++) {
            $c = $blok[$i];
            if (!$tirnak && ($c === "'" || $c === '"' || $c === '`')) { $tirnak = true; $tirnak_char = $c; $tmp .= $c; }
            elseif ($tirnak && $c === $tirnak_char && ($i === 0 || $blok[$i-1] !== '\\')) { $tirnak = false; $tmp .= $c; }
            elseif (!$tirnak && $c === ';') { $s = trim($tmp); if ($s !== '') $ifadeler[] = $s; $tmp = ''; }
            else { $tmp .= $c; }
        }
        if (trim($tmp) !== '') $ifadeler[] = trim($tmp);

        $tablo_hata = false;
        foreach ($ifadeler as $ifade) {
            $ifade = trim($ifade);
            if ($ifade === '' || str_starts_with($ifade, '--')) continue;
            if (stripos($ifade, 'INSERT INTO') === 0) {
                $values_pos = stripos($ifade, ' VALUES');
                if ($values_pos === false) {
                    try { $pdo->exec($ifade); } catch (PDOException $e) { $hatalar[] = $tablo . ': ' . $e->getMessage(); $tablo_hata = true; break; }
                    continue;
                }
                $prefix = substr($ifade, 0, $values_pos + 7);
                $values_raw = trim(substr($ifade, $values_pos + 7));
                $values_raw = rtrim($values_raw, ';');
                $rows = []; $buf = ''; $depth = 0; $in_str = false; $str_char = ''; $vlen = strlen($values_raw);
                for ($vi = 0; $vi < $vlen; $vi++) {
                    $vc = $values_raw[$vi];
                    if (!$in_str && ($vc === "'" || $vc === '"')) { $in_str = true; $str_char = $vc; $buf .= $vc; }
                    elseif ($in_str && $vc === $str_char && ($vi === 0 || $values_raw[$vi-1] !== '\\')) { $in_str = false; $buf .= $vc; }
                    elseif (!$in_str && $vc === '(') { $depth++; $buf .= $vc; }
                    elseif (!$in_str && $vc === ')') {
                        $depth--; $buf .= $vc;
                        if ($depth === 0) { $rows[] = trim($buf); $buf = ''; while ($vi + 1 < $vlen) { $next = $values_raw[$vi + 1]; if ($next === ',' || $next === "\n" || $next === "\r" || $next === ' ') { $vi++; } else { break; } } }
                    } else { $buf .= $vc; }
                }
                if (empty($rows)) { try { $pdo->exec($ifade); } catch (PDOException $e) { $hatalar[] = $tablo . ': ' . $e->getMessage(); $tablo_hata = true; break; } continue; }
                $batch_size = 200; $chunks = array_chunk($rows, $batch_size);
                foreach ($chunks as $chunk) {
                    $batch_sql = $prefix . "\n" . implode(",\n", $chunk) . ";";
                    try { $pdo->exec($batch_sql); } catch (PDOException $e) { $hatalar[] = $tablo . ': ' . $e->getMessage(); $tablo_hata = true; break 2; }
                }
            } else {
                try { $pdo->exec($ifade); } catch (PDOException $e) { $hatalar[] = $tablo . ': ' . $e->getMessage(); $tablo_hata = true; break; }
            }
        }
        if (!$tablo_hata) { $basarili[] = $tablo; }
    }

    $pdo->exec("SET foreign_key_checks = 1");
    logYaz($pdo, 'guncelle', 'sistem', 'Veritabanı geri yüklendi: ' . $dosya . ' — ' . implode(', ', $basarili), null, null, ['tablolar' => implode(',', $basarili)], 'lite');

    if (empty($hatalar)) {
        flash(count($basarili) . ' tablo başarıyla geri yüklendi: ' . implode(', ', $basarili));
    } else {
        flash(count($basarili) . ' tablo yüklendi. Hatalar: ' . implode(' | ', $hatalar), 'danger');
    }
    header('Location: backup.php'); exit;
}

// Mevcut yedek dosyaları listele
$yedekler = [];
foreach (glob($backup_dir . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $f) {
    $yedekler[] = [
        'dosya' => basename($f),
        'boyut' => round(filesize($f) / 1024, 1),
        'tarih' => date('d.m.Y H:i', filemtime($f)),
        'zaman' => filemtime($f),
    ];
}
usort($yedekler, fn($a, $b) => $b['zaman'] - $a['zaman']);

require_once __DIR__ . '/../includes/header.php';
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
    if (cb.checked) { lbl.style.borderColor='var(--primary)'; lbl.style.background='#e8f0fe'; lbl.style.color='var(--primary)'; }
    else { lbl.style.borderColor='var(--border)'; lbl.style.background='#fff'; lbl.style.color='var(--text)'; }
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
