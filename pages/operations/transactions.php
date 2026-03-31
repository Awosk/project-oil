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

$sayfa_basligi = 'İşlemler';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aciklama_guncelle'])) {
    csrfDogrula();
    $kayit_id      = (int)$_POST['kayit_id'];
    $yeni_aciklama = trim($_POST['aciklama_yeni'] ?? '');
    $sr = $pdo->prepare('SELECT lk.*,u.product_code AS urun_kodu,a.plate AS plaka,t.name AS firma_adi FROM oil_records lk JOIN products u ON lk.product_id=u.id LEFT JOIN vehicles a ON lk.vehicle_id=a.id LEFT JOIN facilities t ON lk.facility_id=t.id WHERE lk.id=? AND lk.is_active=1');
    $sr->execute([$kayit_id]); $sr = $sr->fetch();
    if ($sr) {
        $pdo->prepare("UPDATE oil_records SET notes=? WHERE id=?")->execute([$yeni_aciklama ?: null, $kayit_id]);
        $hedef = $sr['plaka'] ?? $sr['firma_adi'] ?? '?';
        logYaz($pdo,'guncelle','arac_kayit', $hedef.' kaydına açıklama güncellendi: '.$sr['urun_kodu'], $kayit_id, ['notes'=>$sr['notes']], ['notes'=>$yeni_aciklama], 'lite');
        flash('Açıklama güncellendi.');
    }
    $qs = $_GET;
    header('Location: transactions.php?' . http_build_query($qs)); exit;
}

if (isset($_GET['islendi_toggle'])) {
    $toggle_id = (int)$_GET['islendi_toggle'];
    $mevcut = $pdo->prepare("SELECT is_processed FROM oil_records WHERE id=?");
    $mevcut->execute([$toggle_id]); $mevcut_val = $mevcut->fetchColumn();
    $ku = mevcutKullanici();
    if ($mevcut_val == 0) {
        $pdo->prepare("UPDATE oil_records SET is_processed=1, processed_at=NOW(), processed_by=? WHERE id=?")
            ->execute([$ku['id'], $toggle_id]);
        $kd = $pdo->prepare("SELECT lk.*, u.product_code AS urun_kodu, u.product_name AS urun_adi, a.plate AS plaka, t.name AS firma_adi FROM oil_records lk JOIN products u ON lk.product_id=u.id LEFT JOIN vehicles a ON lk.vehicle_id=a.id LEFT JOIN facilities t ON lk.facility_id=t.id WHERE lk.id=?");
        $kd->execute([$toggle_id]); $kd = $kd->fetch();
        $hedef = $kd ? ($kd['plaka'] ?? $kd['firma_adi'] ?? '?') : '?';
        $urun  = $kd ? ($kd['urun_kodu'].' '.$kd['urun_adi']) : '?';
        logYaz($pdo,'guncelle','islendi','Kayıt depoya işlendi: '.$hedef.' — '.$urun.', '.($kd['quantity']??'?').'L', $toggle_id, ['is_processed'=>0], ['is_processed'=>1,'processed_by'=>$ku['id']], 'lite');
    } else {
        $pdo->prepare("UPDATE oil_records SET is_processed=0, processed_at=NULL, processed_by=NULL WHERE id=?")
            ->execute([$toggle_id]);
        $kd = $pdo->prepare("SELECT lk.*, u.product_code AS urun_kodu, u.product_name AS urun_adi, a.plate AS plaka, t.name AS firma_adi FROM oil_records lk JOIN products u ON lk.product_id=u.id LEFT JOIN vehicles a ON lk.vehicle_id=a.id LEFT JOIN facilities t ON lk.facility_id=t.id WHERE lk.id=?");
        $kd->execute([$toggle_id]); $kd = $kd->fetch();
        $hedef = $kd ? ($kd['plaka'] ?? $kd['firma_adi'] ?? '?') : '?';
        $urun  = $kd ? ($kd['urun_kodu'].' '.$kd['urun_adi']) : '?';
        logYaz($pdo,'guncelle','islendi','İşlendi işareti geri alındı: '.$hedef.' — '.$urun.', '.($kd['quantity']??'?').'L', $toggle_id, ['is_processed'=>1], ['is_processed'=>0], 'lite');
    }
    $qs = $_GET; unset($qs['islendi_toggle']);
    header('Location: transactions.php?' . http_build_query($qs)); exit;
}

$f_tarih_bas  = $_GET['tarih_bas']  ?? '';
$f_tarih_bit  = $_GET['tarih_bit']  ?? '';
$f_arac_id    = (int)($_GET['arac_id']   ?? 0);
$f_tesis_id   = (int)($_GET['tesis_id']  ?? 0);
$f_urun_id    = (int)($_GET['urun_id']   ?? 0);
$f_tur     = in_array($_GET['tur']     ?? '', ['arac','tesis']) ? $_GET['tur'] : 'tumu';
$f_islendi = in_array($_GET['islendi'] ?? '', ['islendi','islenmedi']) ? $_GET['islendi'] : 'tumu';

$where  = ["lk.is_active = 1"];
$params = [];

if ($f_tarih_bas) { $where[] = "lk.date >= ?"; $params[] = $f_tarih_bas; }
if ($f_tarih_bit) { $where[] = "lk.date <= ?"; $params[] = $f_tarih_bit; }
if ($f_arac_id)   { $where[] = "lk.vehicle_id = ?"; $params[] = $f_arac_id; }
if ($f_tesis_id)  { $where[] = "lk.facility_id = ?"; $params[] = $f_tesis_id; }
if ($f_urun_id)   { $where[] = "lk.product_id = ?"; $params[] = $f_urun_id; }
if ($f_tur === 'arac')  { $where[] = "lk.record_type = 'arac'"; }
elseif ($f_tur === 'tesis') { $where[] = "lk.record_type = 'tesis'"; }
if ($f_islendi === 'islendi')   { $where[] = "lk.is_processed = 1"; }
elseif ($f_islendi === 'islenmedi') { $where[] = "lk.is_processed = 0"; }

$where_sql = implode(" AND ", $where);

$sayfa_basina = 50;
$sayfa = max(1, (int)($_GET['sayfa'] ?? 1));

$count_stmt = $pdo->prepare("SELECT COUNT(*), COUNT(CASE WHEN lk.record_type='arac' THEN 1 END), COUNT(CASE WHEN lk.record_type='tesis' THEN 1 END), COALESCE(SUM(lk.quantity),0) FROM oil_records lk LEFT JOIN products u ON lk.product_id=u.id WHERE $where_sql");
$count_stmt->execute($params);
$count_row = $count_stmt->fetch(PDO::FETCH_NUM);
$toplam_islem  = (int)$count_row[0];
$arac_islem    = (int)$count_row[1];
$tesis_islem   = (int)$count_row[2];
$toplam_litre  = (float)$count_row[3];

$toplam_sayfa = max(1, (int)ceil($toplam_islem / $sayfa_basina));
$sayfa = min($sayfa, $toplam_sayfa);
$offset = ($sayfa - 1) * $sayfa_basina;

$kayitlar = $pdo->prepare("
    SELECT lk.*, u.product_name AS urun_adi, u.product_code AS urun_kodu,
           a.plate AS plaka, a.brand_model AS marka_model, vt.type_name AS arac_turu,
           t.name AS firma_adi, k.full_name AS ad_soyad, ik.full_name AS islendi_ad_soyad
    FROM oil_records lk
    LEFT JOIN products u ON lk.product_id = u.id
    LEFT JOIN vehicles a ON lk.vehicle_id = a.id
    LEFT JOIN vehicle_types vt ON a.vehicle_type_id = vt.id
    LEFT JOIN facilities t ON lk.facility_id = t.id
    LEFT JOIN users k ON lk.created_by = k.id
    LEFT JOIN users ik ON lk.processed_by = ik.id
    WHERE $where_sql
    ORDER BY lk.date DESC, lk.created_at DESC
    LIMIT $sayfa_basina OFFSET $offset
");
$kayitlar->execute($params);
$kayitlar = $kayitlar->fetchAll();

$bekleyen_sayisi = (int)$pdo->query("SELECT COUNT(*) FROM oil_records WHERE is_active=1 AND is_processed=0")->fetchColumn();

$tum_araclar  = $pdo->query("SELECT id, plate AS plaka, brand_model AS marka_model FROM vehicles WHERE is_active=1 ORDER BY plate")->fetchAll();
$tum_tesisler = $pdo->query("SELECT id, name AS firma_adi FROM facilities WHERE is_active=1 ORDER BY name")->fetchAll();
$tum_urunler  = $pdo->query("SELECT id, product_code AS urun_kodu, product_name AS urun_adi FROM products WHERE is_active=1 ORDER BY product_name")->fetchAll();

$filtre_aktif = $f_tarih_bas || $f_tarih_bit || $f_arac_id || $f_tesis_id || $f_urun_id || $f_tur !== 'tumu' || $f_islendi !== 'tumu';

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1><span>📋</span> İşlemler</h1>
</div>

<div class="stat-grid">
    <a href="transactions.php" class="stat-card <?= $f_tur==='tumu' && $f_islendi==='tumu' && !$f_tarih_bas && !$f_arac_id && !$f_tesis_id && !$f_urun_id ? 'active' : '' ?>" style="text-decoration:none;">
        <div class="stat-label">Tüm Kayıtlar</div>
        <div class="stat-value"><?= $toplam_islem ?></div>
        <div class="stat-sub">Kayıt</div>
    </a>
    <a href="transactions.php?tur=arac" class="stat-card <?= $f_tur==='arac' ? 'active' : '' ?>" style="text-decoration:none;">
        <div class="stat-label">🚗 Araç</div>
        <div class="stat-value"><?= $arac_islem ?></div>
        <div class="stat-sub">Kayıt</div>
    </a>
    <a href="transactions.php?tur=tesis" class="stat-card <?= $f_tur==='tesis' ? 'active' : '' ?>" style="text-decoration:none;">
        <div class="stat-label">🏭 Tesis</div>
        <div class="stat-value"><?= $tesis_islem ?></div>
        <div class="stat-sub">Kayıt</div>
    </a>
    <a href="transactions.php?islendi=islenmedi" class="stat-card <?= $bekleyen_sayisi > 0 ? 'warning' : 'success' ?> <?= $f_islendi==='islenmedi' ? 'active' : '' ?>" style="text-decoration:none;">
        <div class="stat-label">⏳ Bekleyen</div>
        <div class="stat-value"><?= $bekleyen_sayisi ?></div>
        <div class="stat-sub"><?= $bekleyen_sayisi > 0 ? 'İşlenmedi' : 'Hepsi tamam' ?></div>
    </a>
    <a href="transactions.php?islendi=islendi" class="stat-card success <?= $f_islendi==='islendi' ? 'active' : '' ?>" style="text-decoration:none;">
        <div class="stat-label">✅ İşlendi</div>
        <div class="stat-value"><?= (int)$pdo->query("SELECT COUNT(*) FROM oil_records WHERE is_active=1 AND is_processed=1")->fetchColumn() ?></div>
        <div class="stat-sub">Kayıt</div>
    </a>
</div>

<div class="card">
    <div class="card-title" style="cursor:pointer; display:flex; align-items:center; gap:8px;" onclick="toggleFiltre()">
        <span style="flex:1;">🔍 Filtrele & Ara
            <span id="filtre_ok" style="margin-left:4px; transition:.3s;"><?= $filtre_aktif ? '▲' : '▼' ?></span>
            <?php if ($filtre_aktif): ?>
            <span class="badge badge-warning" style="margin-left:8px;">Filtre Aktif</span>
            <?php endif; ?>
        </span>
        <?php if ($filtre_aktif): ?>
        <a href="transactions.php" class="btn btn-sm btn-danger" onclick="event.stopPropagation();" style="flex-shrink:0;">✕ Temizle</a>
        <?php endif; ?>
    </div>
    <div id="filtre_panel" style="<?= $filtre_aktif ? '' : 'display:none;' ?>">
        <form method="get">
            <div class="form-grid">
                <div class="form-group">
                    <label>Başlangıç Tarihi</label>
                    <input type="date" name="tarih_bas" value="<?= htmlspecialchars($f_tarih_bas) ?>">
                </div>
                <div class="form-group">
                    <label>Bitiş Tarihi</label>
                    <input type="date" name="tarih_bit" value="<?= htmlspecialchars($f_tarih_bit) ?>">
                </div>
                <div class="form-group">
                    <label>İşlem Türü</label>
                    <select name="tur">
                        <option value="tumu" <?= $f_tur=='tumu'?'selected':'' ?>>Tümü</option>
                        <option value="arac"  <?= $f_tur=='arac' ?'selected':'' ?>>🚗 Sadece Araçlar</option>
                        <option value="tesis" <?= $f_tur=='tesis'?'selected':'' ?>>🏭 Sadece Tesisler</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Araç</label>
                    <select name="arac_id">
                        <option value="">Tüm Araçlar</option>
                        <?php foreach ($tum_araclar as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= $f_arac_id==$a['id']?'selected':'' ?>><?= htmlspecialchars($a['plate'] . ' - ' . $a['brand_model']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tesis / Şantiye</label>
                    <select name="tesis_id">
                        <option value="">Tüm Tesisler</option>
                        <?php foreach ($tum_tesisler as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $f_tesis_id==$t['id']?'selected':'' ?>><?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Yağ / Ürün</label>
                    <select name="urun_id">
                        <option value="">Tüm Ürünler</option>
                        <?php foreach ($tum_urunler as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $f_urun_id==$u['id']?'selected':'' ?>><?= htmlspecialchars($u['urun_kodu'] . ' - ' . $u['product_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Depo Durumu</label>
                    <select name="islendi">
                        <option value="tumu"      <?= $f_islendi=='tumu'      ?'selected':'' ?>>Tümü</option>
                        <option value="islenmedi" <?= $f_islendi=='islenmedi' ?'selected':'' ?>>⏳ İşlenmedi</option>
                        <option value="islendi"   <?= $f_islendi=='islendi'   ?'selected':'' ?>>✅ İşlendi</option>
                    </select>
                </div>
            </div>
            <div class="btn-group" style="margin-top:14px;">
                <button type="submit" class="btn btn-primary">🔍 Filtrele</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-title" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
        <span>
            📋 Kayıtlar
            <?php if ($filtre_aktif): ?>
            <span style="font-weight:400; font-size:13px; color:var(--muted);">— Filtrelenmiş sonuç: <?= $toplam_islem ?> kayıt</span>
            <?php else: ?>
            <span style="font-weight:400; font-size:13px; color:var(--muted);">(<?= $toplam_islem ?>)</span>
            <?php endif; ?>
        </span>
        <?php if ($toplam_sayfa > 1): ?>
        <span style="font-weight:400; font-size:13px; color:var(--muted);">
            <?= (($sayfa-1)*$sayfa_basina+1) ?>–<?= min($sayfa*$sayfa_basina, $toplam_islem) ?> / <?= $toplam_islem ?> kayıt &nbsp;·&nbsp; Sayfa <?= $sayfa ?>/<?= $toplam_sayfa ?>
        </span>
        <?php endif; ?>
    </div>

    <?php if (empty($kayitlar)): ?>
    <div style="text-align:center; padding:40px; color:var(--muted);">
        <div style="font-size:40px; margin-bottom:12px;">🔍</div>
        <div>Bu filtreye uygun kayıt bulunamadı.</div>
    </div>
    <?php else: ?>
    <div class="kayit-list">
        <?php foreach ($kayitlar as $k):
            $detay_url = $k['record_type'] === 'arac'
                ? 'vehicle_detail.php?id='.$k['vehicle_id'].'#kayit-'.$k['id']
                : 'facility_detail.php?id='.$k['facility_id'].'#kayit-'.$k['id'];
        ?>
        <div class="islem-item" class="<?= $k['is_processed'] ? 'islem-islendi' : '' ?>"
             onclick="window.location='<?= $detay_url ?>'" style="cursor:pointer;">
            <div class="islem-hedef">
                <?php if ($k['record_type'] === 'arac'): ?>
                <a href="vehicle_detail.php?id=<?= $k['vehicle_id'] ?>" class="islem-plaka">🚗 <?= htmlspecialchars($k['plate']) ?></a>
                <div class="islem-alt"><?= htmlspecialchars($k['brand_model']) ?></div>
                <?php else: ?>
                <a href="facility_detail.php?id=<?= $k['facility_id'] ?>" class="islem-plaka">🏭 <?= htmlspecialchars($k['name']) ?></a>
                <div class="islem-alt">Tesis / Şantiye</div>
                <?php endif; ?>
            </div>

            <div class="islem-urun">
                <div class="islem-urun-adi">
                    <?= htmlspecialchars($k['urun_adi']) ?>
                    <?php if ($k['is_oil_change']): ?>
                    <span style="background:var(--warning-l);color:var(--warning);font-size:10px;font-weight:700;padding:2px 6px;border-radius:4px;margin-left:4px;">🔧 BAKIM</span>
                    <?php endif; ?>
                </div>
                <div class="islem-alt">
                    <?= htmlspecialchars($k['urun_kodu']) ?>
                    <?php if ($k['is_oil_change'] && $k['current_km']): ?> · 🛣️ <?= number_format($k['current_km']) ?> KM<?php endif; ?>
                    <?php if ($k['notes']): ?> · <?= htmlspecialchars($k['notes']) ?><?php endif; ?>
                </div>
            </div>

            <div class="islem-sag">
                <div class="islem-miktar"><?= formatliMiktar($k['quantity']) ?></div>
                <div class="islem-tarih">🛢️ <?= formatliTarih($k['date']) ?></div>
                <div class="islem-giris-tarihi">🕐 <?= formatliTarih($k['created_at']) ?></div>
                <div class="islem-kisi">👤 <?= htmlspecialchars($k['ad_soyad'] ?? '-') ?></div>
                <button onclick="aciklamaModal(<?= $k['id'] ?>, '<?= htmlspecialchars($k['notes'] ?? '', ENT_QUOTES) ?>')"
                        style="margin-top:4px;background:none;border:none;cursor:pointer;font-size:11px;color:var(--muted);padding:0;"
                        title="Açıklama düzenle">✏️ <?= $k['notes'] ? 'Düzenle' : 'Açıklama ekle' ?></button>
            </div>

            <div style="flex-shrink:0;text-align:center;">
                <?php if ($k['is_processed']): ?>
                <a href="?islendi_toggle=<?= $k['id'] ?>&<?= http_build_query(array_diff_key($_GET, ['islendi_toggle'=>''])) ?>"
                   class="islendi-btn islendi-btn--on"
                   onclick="event.stopPropagation(); return confirm('İşlendi işaretini kaldırmak istiyor musunuz?')">
                    <span style="font-size:20px;">✅</span>
                    İşlendi
                    <span class="islendi-ad"><?= $k['islendi_ad_soyad'] ? htmlspecialchars($k['islendi_ad_soyad']) : '' ?></span>
                </a>
                <?php else: ?>
                <a href="?islendi_toggle=<?= $k['id'] ?>&<?= http_build_query(array_diff_key($_GET, ['islendi_toggle'=>''])) ?>"
                   class="islendi-btn islendi-btn--off"
                   onclick="event.stopPropagation();">
                    <span style="font-size:20px;">⬜</span>
                    İşle
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($toplam_sayfa > 1):
        $sayfa_params = array_diff_key($_GET, ['sayfa' => '']);
        function sayfaUrl($n, $base) { return 'transactions.php??' . http_build_query(array_merge($base, ['sayfa' => $n])); }
        $goster_bas  = max(1, $sayfa - 2);
        $goster_bit  = min($toplam_sayfa, $sayfa + 2);
    ?>
    <div style="display:flex; align-items:center; justify-content:center; gap:6px; padding:16px 0; flex-wrap:wrap;">
        <?php if ($sayfa > 1): ?>
        <a href="<?= sayfaUrl(1, $sayfa_params) ?>" class="btn btn-secondary btn-sm" style="font-size:12px;">« İlk</a>
        <a href="<?= sayfaUrl($sayfa - 1, $sayfa_params) ?>" class="btn btn-secondary btn-sm" style="font-size:12px;">‹ Önceki</a>
        <?php endif; ?>
        <?php if ($goster_bas > 1): ?><span style="color:var(--muted); font-size:13px; padding:0 4px;">…</span><?php endif; ?>
        <?php for ($i = $goster_bas; $i <= $goster_bit; $i++): ?>
        <a href="<?= sayfaUrl($i, $sayfa_params) ?>"
           class="btn btn-sm <?= $i === $sayfa ? 'btn-primary' : 'btn-secondary' ?>"
           style="font-size:12px; min-width:36px; text-align:center;"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($goster_bit < $toplam_sayfa): ?><span style="color:var(--muted); font-size:13px; padding:0 4px;">…</span><?php endif; ?>
        <?php if ($sayfa < $toplam_sayfa): ?>
        <a href="<?= sayfaUrl($sayfa + 1, $sayfa_params) ?>" class="btn btn-secondary btn-sm" style="font-size:12px;">Sonraki ›</a>
        <a href="<?= sayfaUrl($toplam_sayfa, $sayfa_params) ?>" class="btn btn-secondary btn-sm" style="font-size:12px;">Son »</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.stat-card.active { border-color:var(--primary); background:var(--primary); color:#fff; }
.stat-card.active .stat-label, .stat-card.active .stat-sub { color:rgba(255,255,255,0.8); }
.stat-card.active .stat-value { color:#fff; }
.islem-item { display:flex; align-items:center; gap:12px; padding:12px 0; border-bottom:1px solid var(--border); flex-wrap:wrap; cursor:pointer; transition:background .15s; }
.islem-item:hover { background:var(--hover-bg-blue); }
.islem-item:last-child { border-bottom:none; }
.islem-hedef { min-width:110px; flex-shrink:0; }
.islem-plaka { font-weight:800; font-size:14px; color:var(--primary); text-decoration:none; display:block; }
.islem-plaka:hover { color:var(--primary-l); }
.islem-urun { flex:1; min-width:120px; }
.islem-urun-adi { font-weight:600; font-size:13px; color:var(--text); }
.islem-alt { font-size:11px; color:var(--muted); margin-top:2px; }
.islem-sag { text-align:right; flex-shrink:0; }
.islem-miktar { font-size:18px; font-weight:800; color:var(--primary-l); }
.islem-tarih  { font-size:11px; color:var(--muted); margin-top:2px; }
.islem-kisi   { font-size:11px; color:var(--muted); }
.islem-giris-tarihi { font-size: 11px; color: var(--muted); margin-top: 1px; }
</style>

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
function toggleFiltre() {
    var panel = document.getElementById('filtre_panel');
    var ok    = document.getElementById('filtre_ok');
    var gizli = panel.style.display === 'none';
    panel.style.display = gizli ? '' : 'none';
    ok.textContent = gizli ? '▲' : '▼';
}
function aciklamaModal(id, mevcutAciklama) {
    event.stopPropagation();
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
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
