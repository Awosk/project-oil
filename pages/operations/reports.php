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
girisKontrol();

$sayfa_basligi = 'Raporlar';
$ku = mevcutKullanici();

// ── FİLTRE DEĞERLERİ ──
$f_donem       = $_GET['donem']      ?? 'bu_ay';
$f_tarih_bas   = $_GET['tarih_bas']  ?? '';
$f_tarih_bit   = $_GET['tarih_bit']  ?? '';
$f_urun_ids    = array_filter(array_map('intval', (array)($_GET['urun_ids'] ?? [])));
$f_tur         = in_array($_GET['tur'] ?? '', ['arac','tesis']) ? $_GET['tur'] : 'tumu';
$f_kullanici   = (int)($_GET['kullanici_id'] ?? 0);

// Dönem → tarih aralığına çevir
$bugun = date('Y-m-d');
switch ($f_donem) {
    case 'bugun':
        $tarih_bas = $bugun; $tarih_bit = $bugun; break;
    case 'bu_hafta':
        $tarih_bas = date('Y-m-d', strtotime('monday this week'));
        $tarih_bit = $bugun; break;
    case 'bu_ay':
        $tarih_bas = date('Y-m-01'); $tarih_bit = $bugun; break;
    case 'gecen_ay':
        $tarih_bas = date('Y-m-01', strtotime('first day of last month'));
        $tarih_bit = date('Y-m-t',  strtotime('last day of last month')); break;
    case 'ozel':
        $tarih_bas = $f_tarih_bas ?: date('Y-m-01');
        $tarih_bit = $f_tarih_bit ?: $bugun; break;
    default:
        $tarih_bas = date('Y-m-01'); $tarih_bit = $bugun;
}

// ── SORGU ──
$where  = ["lk.is_active = 1", "lk.date BETWEEN ? AND ?"];
$params = [$tarih_bas, $tarih_bit];

if ($f_tur === 'arac')  { $where[] = "lk.record_type = 'arac'"; }
elseif ($f_tur === 'tesis') { $where[] = "lk.record_type = 'tesis'"; }

if ($f_kullanici) { $where[] = "lk.created_by = ?"; $params[] = $f_kullanici; }

if (!empty($f_urun_ids)) {
    $placeholders = implode(',', array_fill(0, count($f_urun_ids), '?'));
    $where[]  = "lk.product_id IN ($placeholders)";
    $params   = array_merge($params, $f_urun_ids);
}

$where_sql = implode(" AND ", $where);

// ── ÖZET: Ürün bazlı toplam (sayfalama olmadan, tüm kayıtlar için) ──
$ozet_stmt = $pdo->prepare("
    SELECT lk.product_id, u.product_code AS urun_kodu, u.product_name AS urun_adi,
           COUNT(*) AS adet, COALESCE(SUM(lk.quantity), 0) AS toplam
    FROM oil_records lk
    JOIN products u ON lk.product_id = u.id
    WHERE $where_sql
    GROUP BY lk.product_id, u.product_code, u.product_name
    ORDER BY toplam DESC
");
$ozet_stmt->execute($params);
$urun_ozet = $ozet_stmt->fetchAll();

$genel_toplam   = array_sum(array_column($urun_ozet, 'toplam'));
$genel_adet     = array_sum(array_column($urun_ozet, 'adet'));

// ── SAYFALAMA ──
$pdf_mod      = isset($_GET['pdf']);
$sayfa_basina = 100;
$sayfa        = max(1, (int)($_GET['sayfa'] ?? 1));

// Toplam kayıt sayısı
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM oil_records lk WHERE $where_sql");
$count_stmt->execute($params);
$toplam_kayit = (int)$count_stmt->fetchColumn();

$toplam_sayfa = max(1, (int)ceil($toplam_kayit / $sayfa_basina));
$sayfa        = min($sayfa, $toplam_sayfa);
$offset       = ($sayfa - 1) * $sayfa_basina;

// PDF modunda tüm kayıtları çek (sayfalama yok)
if ($pdf_mod) {
    $kayitlar_stmt = $pdo->prepare("
        SELECT lk.*, u.product_name AS urun_adi, u.product_code AS urun_kodu,
               a.plate AS plaka, a.brand_model AS marka_model, vt.type_name AS arac_turu,
               t.name AS firma_adi, k.full_name AS ad_soyad
        FROM oil_records lk
        JOIN products u         ON lk.product_id  = u.id
        LEFT JOIN vehicles a    ON lk.vehicle_id   = a.id
        LEFT JOIN vehicle_types vt ON a.vehicle_type_id = vt.id
        LEFT JOIN facilities t  ON lk.facility_id  = t.id
        LEFT JOIN users k       ON lk.created_by   = k.id
        WHERE $where_sql
        ORDER BY lk.date DESC, lk.created_at DESC
    ");
    $kayitlar_stmt->execute($params);
} else {
    $kayitlar_stmt = $pdo->prepare("
        SELECT lk.*, u.product_name AS urun_adi, u.product_code AS urun_kodu,
               a.plate AS plaka, a.brand_model AS marka_model, vt.type_name AS arac_turu,
               t.name AS firma_adi, k.full_name AS ad_soyad
        FROM oil_records lk
        JOIN products u         ON lk.product_id  = u.id
        LEFT JOIN vehicles a    ON lk.vehicle_id   = a.id
        LEFT JOIN vehicle_types vt ON a.vehicle_type_id = vt.id
        LEFT JOIN facilities t  ON lk.facility_id  = t.id
        LEFT JOIN users k       ON lk.created_by   = k.id
        WHERE $where_sql
        ORDER BY lk.date DESC, lk.created_at DESC
        LIMIT $sayfa_basina OFFSET $offset
    ");
    $kayitlar_stmt->execute($params);
}
$kayitlar = $kayitlar_stmt->fetchAll();

// ── Filtre listeleri ──
$tum_urunler      = $pdo->query("SELECT id, product_code AS urun_kodu, product_name AS urun_adi FROM products WHERE is_active=1 ORDER BY product_name")->fetchAll();
$tum_kullanicilar = $pdo->query("SELECT id, full_name AS ad_soyad FROM users WHERE is_active=1 ORDER BY full_name")->fetchAll();

if ($pdf_mod) { ob_start(); }
if (!$pdf_mod) require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($pdf_mod): ?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Rapor <?= htmlspecialchars($tarih_bas) ?> / <?= htmlspecialchars($tarih_bit) ?></title>
<style>
    body { font-family: Arial, sans-serif; font-size: 12px; color: #222; margin: 20px; }
    h1 { font-size: 18px; margin-bottom: 4px; }
    .meta { font-size: 11px; color: #666; margin-bottom: 16px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    th { background: #1e4d6b; color: #fff; padding: 6px 8px; text-align: left; font-size: 11px; }
    td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; font-size: 11px; }
    tr:nth-child(even) td { background: #f9fafb; }
    .ozet-tablo th { background: #374151; }
    .toplam-row td { font-weight: 700; background: #f0f9ff !important; }
    @media print { @page { margin: 15mm; } body { margin: 0; } }
</style>
</head>
<body>
<?php else: ?>
<div class="page-header">
    <h1><span>📊</span> Raporlar</h1>
    <a href="?<?= http_build_query(array_merge($_GET, ['pdf'=>'1'])) ?>" target="_blank" class="btn btn-primary btn-sm">🖨️ PDF / Yazdır</a>
</div>

<!-- FİLTRE -->
<div class="card">
    <div class="card-title">🔍 Rapor Filtresi</div>
    <form method="get" id="rapor_form">
        <div class="form-grid">
            <div class="form-group">
                <label>Dönem</label>
                <select name="donem" onchange="donemDegisti(this.value)">
                    <option value="bugun"      <?= $f_donem=='bugun'    ?'selected':'' ?>>Bugün</option>
                    <option value="bu_hafta"   <?= $f_donem=='bu_hafta' ?'selected':'' ?>>Bu Hafta</option>
                    <option value="bu_ay"      <?= $f_donem=='bu_ay'    ?'selected':'' ?>>Bu Ay</option>
                    <option value="gecen_ay"   <?= $f_donem=='gecen_ay' ?'selected':'' ?>>Geçen Ay</option>
                    <option value="ozel"       <?= $f_donem=='ozel'     ?'selected':'' ?>>Özel Aralık</option>
                </select>
            </div>
            <div class="form-group" id="ozel_tarih_bas" style="<?= $f_donem!='ozel'?'display:none':'' ?>">
                <label>Başlangıç</label>
                <input type="date" name="tarih_bas" value="<?= htmlspecialchars($f_tarih_bas) ?>">
            </div>
            <div class="form-group" id="ozel_tarih_bit" style="<?= $f_donem!='ozel'?'display:none':'' ?>">
                <label>Bitiş</label>
                <input type="date" name="tarih_bit" value="<?= htmlspecialchars($f_tarih_bit) ?>">
            </div>
            <div class="form-group">
                <label>Kayıt Türü</label>
                <select name="tur">
                    <option value="tumu"  <?= $f_tur=='tumu' ?'selected':'' ?>>Tümü</option>
                    <option value="arac"  <?= $f_tur=='arac' ?'selected':'' ?>>🚗 Araçlar</option>
                    <option value="tesis" <?= $f_tur=='tesis'?'selected':'' ?>>🏭 Tesisler</option>
                </select>
            </div>
            <div class="form-group">
                <label>Kaydeden</label>
                <select name="kullanici_id">
                    <option value="">Tüm Kullanıcılar</option>
                    <?php foreach ($tum_kullanicilar as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $f_kullanici==$u['id']?'selected':'' ?>><?= htmlspecialchars($u['ad_soyad']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group" style="margin-top:12px;">
            <label>Ürün Seçimi <span style="font-weight:400;color:var(--muted);font-size:12px;">(boş bırakılırsa tümü)</span></label>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:6px;">
                <?php foreach ($tum_urunler as $u): ?>
                <label style="display:flex;align-items:center;gap:5px;font-size:13px;font-weight:400;cursor:pointer;padding:4px 10px;border:1.5px solid var(--border);border-radius:6px;<?= in_array($u['id'], $f_urun_ids) ? 'background:var(--primary);color:#fff;border-color:var(--primary);' : '' ?>">
                    <input type="checkbox" name="urun_ids[]" value="<?= $u['id'] ?>"
                           <?= in_array($u['id'], $f_urun_ids) ? 'checked' : '' ?>
                           style="display:none;" onchange="this.closest('label').style.background=this.checked?'var(--primary)':''; this.closest('label').style.color=this.checked?'#fff':''; this.closest('label').style.borderColor=this.checked?'var(--primary)':'var(--border)';">
                    <?= htmlspecialchars($u['product_code']) ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="btn-group" style="margin-top:14px;">
            <button type="submit" class="btn btn-primary">📊 Raporu Getir</button>
            <a href="reports.php" class="btn btn-secondary">✕ Temizle</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- RAPOR BAŞLIĞI -->
<?php
$donem_etiket = ['bugun'=>'Bugün','bu_hafta'=>'Bu Hafta','bu_ay'=>'Bu Ay','gecen_ay'=>'Geçen Ay','ozel'=>'Özel Aralık'][$f_donem] ?? 'Bu Ay';
$tur_etiket   = ['arac'=>'Araçlar','tesis'=>'Tesisler','tumu'=>'Tümü'][$f_tur];
$secili_urun_adlari = [];
if (!empty($f_urun_ids)) {
    foreach ($tum_urunler as $u) {
        if (in_array($u['id'], $f_urun_ids)) $secili_urun_adlari[] = $u['product_code'];
    }
}
?>

<?php if ($pdf_mod): ?>
<h1>📊 <?= SITE_ADI ?> — Rapor</h1>
<div class="meta">
    Dönem: <strong><?= htmlspecialchars($tarih_bas) ?> – <?= htmlspecialchars($tarih_bit) ?></strong>
    &nbsp;|&nbsp; Tür: <strong><?= $tur_etiket ?></strong>
    <?php if (!empty($secili_urun_adlari)): ?>&nbsp;|&nbsp; Ürünler: <strong><?= htmlspecialchars(implode(', ', $secili_urun_adlari)) ?></strong><?php endif; ?>
    &nbsp;|&nbsp; Toplam: <strong><?= number_format($genel_toplam, 2, ',', '.') ?> L</strong>
    &nbsp;|&nbsp; <?= $genel_adet ?> kayıt
    &nbsp;|&nbsp; Oluşturulma: <?= date('d.m.Y H:i') ?>
</div>
<?php else: ?>
<div class="card" style="padding:14px 16px;">
    <div style="display:flex;flex-wrap:wrap;gap:20px;align-items:center;">
        <div>
            <div style="font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase;">Dönem</div>
            <div style="font-weight:700;"><?= $donem_etiket ?>: <?= date('d.m.Y', strtotime($tarih_bas)) ?> – <?= date('d.m.Y', strtotime($tarih_bit)) ?></div>
        </div>
        <div>
            <div style="font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase;">Toplam Miktar</div>
            <div style="font-weight:700;font-size:20px;color:var(--primary-l);"><?= number_format($genel_toplam, 2, ',', '.') ?> L</div>
        </div>
        <div>
            <div style="font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase;">Kayıt Sayısı</div>
            <div style="font-weight:700;"><?= $genel_adet ?></div>
        </div>
        <?php if (!empty($secili_urun_adlari)): ?>
        <div>
            <div style="font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase;">Ürünler</div>
            <div style="font-weight:600;"><?= htmlspecialchars(implode(', ', $secili_urun_adlari)) ?></div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($urun_ozet)): ?>

<!-- ÖZET TABLO: Ürün Bazlı -->
<?php if ($pdf_mod): ?>
<h3 style="margin:16px 0 8px;">Ürün Bazlı Özet</h3>
<table class="ozet-tablo">
    <thead><tr><th>Ürün Kodu</th><th>Ürün Adı</th><th>İşlem Sayısı</th><th>Toplam (L)</th></tr></thead>
    <tbody>
    <?php foreach ($urun_ozet as $o): ?>
    <tr><td><?= htmlspecialchars($o['product_code']) ?></td><td><?= htmlspecialchars($o['product_name']) ?></td><td><?= $o['adet'] ?></td><td><?= number_format($o['toplam'], 2, ',', '.') ?></td></tr>
    <?php endforeach; ?>
    <tr class="toplam-row"><td colspan="2"><strong>GENEL TOPLAM</strong></td><td><strong><?= $genel_adet ?></strong></td><td><strong><?= number_format($genel_toplam, 2, ',', '.') ?> L</strong></td></tr>
    </tbody>
</table>
<?php else: ?>
<div class="card">
    <div class="card-title">📦 Ürün Bazlı Özet</div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Ürün Kodu</th><th>Ürün Adı</th><th>İşlem</th><th>Toplam</th></tr></thead>
            <tbody>
            <?php foreach ($urun_ozet as $o): ?>
            <tr>
                <td><code><?= htmlspecialchars($o['product_code']) ?></code></td>
                <td><?= htmlspecialchars($o['product_name']) ?></td>
                <td><?= $o['adet'] ?></td>
                <td><strong><?= number_format($o['toplam'], 2, ',', '.') ?> L</strong></td>
            </tr>
            <?php endforeach; ?>
            <tr style="background:var(--hover);font-weight:700;">
                <td colspan="2">Genel Toplam</td>
                <td><?= $genel_adet ?></td>
                <td><?= number_format($genel_toplam, 2, ',', '.') ?> L</td>
            </tr>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- DETAY TABLO -->
<?php if ($pdf_mod): ?>
<h3 style="margin:16px 0 8px;">Detaylı Kayıtlar</h3>
<table>
    <thead><tr><th>Tarih</th><th>Tür</th><th>Araç / Tesis</th><th>Ürün</th><th>Miktar (L)</th><th>Kaydeden</th><th>Açıklama</th></tr></thead>
    <tbody>
    <?php foreach ($kayitlar as $r): ?>
    <tr>
        <td><?= date('d.m.Y', strtotime($r['date'])) ?></td>
        <td><?= $r['record_type'] === 'arac' ? 'Araç' : 'Tesis' ?></td>
        <td><?= $r['record_type'] === 'arac' ? htmlspecialchars($r['plate']).'<br><small>'.htmlspecialchars($r['brand_model']).'</small>' : htmlspecialchars($r['name']) ?></td>
        <td><?= htmlspecialchars($r['product_code']) ?><br><small><?= htmlspecialchars($r['product_name']) ?></small></td>
        <td><?= number_format($r['quantity'], 2, ',', '.') ?></td>
        <td><?= htmlspecialchars($r['ad_soyad'] ?? '-') ?></td>
        <td><?= $r['notes'] ? htmlspecialchars($r['notes']) : '—' ?><?php if ($r['is_oil_change']): ?><br><strong>🔧 YAĞ BAKIMI<?= $r['current_km'] ? ' — '.number_format($r['current_km']).' KM' : '' ?></strong><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<div class="card">
    <div class="card-title" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
        <span>📋 Detaylı Kayıtlar <span style="font-weight:400;font-size:13px;color:var(--muted);">(<?= $toplam_kayit ?> toplam)</span></span>
        <?php if ($toplam_sayfa > 1): ?>
        <span style="font-weight:400;font-size:13px;color:var(--muted);">
            <?= (($sayfa-1)*$sayfa_basina+1) ?>–<?= min($sayfa*$sayfa_basina, $toplam_kayit) ?> / <?= $toplam_kayit ?> &nbsp;·&nbsp; Sayfa <?= $sayfa ?>/<?= $toplam_sayfa ?>
        </span>
        <?php endif; ?>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Tarih</th><th>Tür</th><th>Araç / Tesis</th><th>Ürün</th><th>Miktar</th><th>Kaydeden</th><th>Açıklama</th></tr></thead>
            <tbody>
            <?php foreach ($kayitlar as $r): ?>
            <tr>
                <td><?= date('d.m.Y', strtotime($r['date'])) ?></td>
                <td><?= $r['record_type']==='arac' ? '<span class="badge badge-info">🚗 Araç</span>' : '<span class="badge badge-success">🏭 Tesis</span>' ?></td>
                <td><?php if ($r['record_type']==='arac'): ?><strong><?= htmlspecialchars($r['plate']) ?></strong><br><span style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($r['brand_model']) ?></span><?php else: ?><strong><?= htmlspecialchars($r['name']) ?></strong><?php endif; ?></td>
                <td><code><?= htmlspecialchars($r['product_code']) ?></code><br><span style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($r['product_name']) ?></span></td>
                <td><strong><?= number_format($r['quantity'], 2, ',', '.') ?> L</strong></td>
                <td><?= htmlspecialchars($r['ad_soyad'] ?? '-') ?></td>
                <td style="font-size:12px;color:var(--muted);">
                    <?= $r['notes'] ? htmlspecialchars($r['notes']) : '—' ?>
                    <?php if ($r['is_oil_change']): ?><br><span style="font-size:11px;font-weight:700;color:var(--warning);">🔧 YAĞ BAKIMI<?= $r['current_km'] ? ' — '.number_format($r['current_km']).' KM' : '' ?></span><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Sayfalama -->
    <?php if ($toplam_sayfa > 1):
        $sayfa_params = array_diff_key($_GET, ['sayfa'=>'']);
        function raporSayfaUrl($n, $base) { return 'reports.php??' . http_build_query(array_merge($base, ['sayfa'=>$n])); }
        $goster_bas = max(1, $sayfa - 2);
        $goster_bit = min($toplam_sayfa, $sayfa + 2);
    ?>
    <div style="display:flex;align-items:center;justify-content:center;gap:6px;padding:16px 0;flex-wrap:wrap;">
        <?php if ($sayfa > 1): ?>
        <a href="<?= raporSayfaUrl(1, $sayfa_params) ?>" class="btn btn-secondary btn-sm" style="font-size:12px;">« İlk</a>
        <a href="<?= raporSayfaUrl($sayfa-1, $sayfa_params) ?>" class="btn btn-secondary btn-sm" style="font-size:12px;">‹ Önceki</a>
        <?php endif; ?>
        <?php if ($goster_bas > 1): ?><span style="color:var(--muted);font-size:13px;padding:0 4px;">…</span><?php endif; ?>
        <?php for ($i = $goster_bas; $i <= $goster_bit; $i++): ?>
        <a href="<?= raporSayfaUrl($i, $sayfa_params) ?>" class="btn btn-sm <?= $i===$sayfa ? 'btn-primary' : 'btn-secondary' ?>" style="font-size:12px;min-width:36px;text-align:center;"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($goster_bit < $toplam_sayfa): ?><span style="color:var(--muted);font-size:13px;padding:0 4px;">…</span><?php endif; ?>
        <?php if ($sayfa < $toplam_sayfa): ?>
        <a href="<?= raporSayfaUrl($sayfa+1, $sayfa_params) ?>" class="btn btn-secondary btn-sm" style="font-size:12px;">Sonraki ›</a>
        <a href="<?= raporSayfaUrl($toplam_sayfa, $sayfa_params) ?>" class="btn btn-secondary btn-sm" style="font-size:12px;">Son »</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php else: ?>
<?php if (!$pdf_mod): ?>
<div class="alert alert-info" style="margin-top:14px;">Bu filtreye uygun kayıt bulunamadı.</div>
<?php endif; ?>
<?php endif; ?>

<?php if ($pdf_mod): ?>
<script>window.onload = function(){ window.print(); }</script>
</body></html>
<?php else: ?>
<script>
function donemDegisti(val) {
    var ozel = val === 'ozel';
    document.getElementById('ozel_tarih_bas').style.display = ozel ? '' : 'none';
    document.getElementById('ozel_tarih_bit').style.display = ozel ? '' : 'none';
}
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<?php endif; ?>
