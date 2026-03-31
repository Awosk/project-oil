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
adminKontrol();

$sayfa_basligi = 'Sistem Logları';
$ku = mevcutKullanici();

// ── TEK LOG SİL ──
if (isset($_GET['log_sil']) && is_numeric($_GET['log_sil'])) {
    $log_id = (int)$_GET['log_sil'];
    $pdo->prepare("DELETE FROM system_logs WHERE id=?")->execute([$log_id]);
    flash('Log kaydı silindi.');
    $qs = $_GET; unset($qs['log_sil']);
    header('Location: logs.php?' . http_build_query($qs)); exit;
}

// ── TOPLU SİL ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mod = $_POST['toplu_sil_mod'] ?? '';

    if ($mod === 'tumü') {
        // Tabloyu düşür ve yeniden oluştur — en hızlı temizleme yöntemi
        $pdo->exec("DROP TABLE IF EXISTS `system_logs`");
        $pdo->exec("CREATE TABLE `system_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) DEFAULT NULL,
            `username` varchar(50) DEFAULT NULL,
            `full_name` varchar(100) DEFAULT NULL,
            `system` enum('ana','lite') NOT NULL DEFAULT 'ana',
            `action` enum('ekle','guncelle','sil','giris','cikis') NOT NULL,
            `module` varchar(50) NOT NULL,
            `record_id` int(11) DEFAULT NULL,
            `description` text NOT NULL,
            `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_value`)),
            `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_value`)),
            `ip_address` varchar(45) DEFAULT NULL,
            `created_at` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`),
            CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");
        flash('Tüm log kayıtları silindi, tablo sıfırlandı.');

    } elseif ($mod === 'tarih' && !empty($_POST['sil_tarih_bit'])) {
        $tarih = $_POST['sil_tarih_bit'];
        $stmt  = $pdo->prepare("DELETE FROM system_logs WHERE system='lite' AND DATE(created_at) <= ?");
        $stmt->execute([$tarih]);
        flash($stmt->rowCount() . ' log kaydı silindi (' . date('d.m.Y', strtotime($tarih)) . ' tarihine kadar).');

    } elseif ($mod === 'secili' && !empty($_POST['log_ids'])) {
        $ids = array_values(array_filter(array_map('intval', (array)$_POST['log_ids'])));
        if (!empty($ids)) {
            $ph   = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM system_logs WHERE id IN ($ph) AND system='lite'");
            $stmt->execute($ids);
            flash($stmt->rowCount() . ' log kaydı silindi.');
        }
    }
    header('Location: logs.php'); exit;
}

// Filtreler
$f_kullanici = trim($_GET['kullanici'] ?? '');
$f_aksiyon   = $_GET['aksiyon'] ?? '';
$f_modul     = $_GET['modul']   ?? '';
$f_tarih_bas = $_GET['tarih_bas'] ?? '';
$f_tarih_bit = $_GET['tarih_bit'] ?? date('Y-m-d');

$where   = ["system = 'lite'"];
$params  = [];

if ($f_kullanici) {
    $where[]  = "(username LIKE ? OR full_name LIKE ?)";
    $params[] = "%$f_kullanici%";
    $params[] = "%$f_kullanici%";
}
if ($f_aksiyon) {
    $where[]  = "action = ?";
    $params[] = $f_aksiyon;
}
if ($f_modul) {
    $where[]  = "module = ?";
    $params[] = $f_modul;
}
if ($f_tarih_bas) {
    $where[]  = "DATE(created_at) >= ?";
    $params[] = $f_tarih_bas;
}
if ($f_tarih_bit) {
    $where[]  = "DATE(created_at) <= ?";
    $params[] = $f_tarih_bit;
}

$where_sql = implode(" AND ", $where);

$loglar = $pdo->prepare("
    SELECT * FROM system_logs
    WHERE $where_sql
    ORDER BY created_at DESC
    LIMIT 1000
");
$loglar->execute($params);
$loglar = $loglar->fetchAll();

// Özet sayılar (bugün)
$bugun      = $pdo->query("SELECT COUNT(*) FROM system_logs WHERE system='lite' AND DATE(created_at)=CURDATE()")->fetchColumn();
$bugun_sil  = $pdo->query("SELECT COUNT(*) FROM system_logs WHERE system='lite' AND action='sil' AND DATE(created_at)=CURDATE()")->fetchColumn();
$bugun_ekle = $pdo->query("SELECT COUNT(*) FROM system_logs WHERE system='lite' AND action='ekle' AND DATE(created_at)=CURDATE()")->fetchColumn();
$toplam     = $pdo->query("SELECT COUNT(*) FROM system_logs WHERE system='lite'")->fetchColumn();

$filtre_aktif = $f_kullanici || $f_aksiyon || $f_modul || $f_tarih_bas;

// arac_kayit / islendi / tesis_kayit modüllerindeki kayit_id'leri toplu çek
$kayit_id_listesi = array_filter(array_unique(array_column(
    array_filter($loglar, fn($l) => in_array($l['module'], ['arac_kayit','islendi','tesis_kayit'])),
    'record_id'
)));
$kayit_hedef_map = []; // record_id => ['turu'=>'arac'|'tesis', 'hedef_id'=>X]
if (!empty($kayit_id_listesi)) {
    $ph = implode(',', array_fill(0, count($kayit_id_listesi), '?'));
    $stmt = $pdo->prepare("SELECT id, record_type, vehicle_id, facility_id FROM oil_records WHERE id IN ($ph)");
    $stmt->execute(array_values($kayit_id_listesi));
    foreach ($stmt->fetchAll() as $row) {
        $kayit_hedef_map[$row['id']] = [
            'turu'     => $row['record_type'],
            'hedef_id' => $row['record_type'] === 'arac' ? $row['vehicle_id'] : $row['facility_id'],
        ];
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1><span>🔍</span> Sistem Logları</h1>
    <button class="btn btn-danger btn-sm" onclick="document.getElementById('temizleModal').style.display='flex'">🗑️ Logları Temizle</button>
</div>

<!-- Özet -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-label">Toplam Log</div>
        <div class="stat-value"><?= number_format($toplam) ?></div>
        <div class="stat-sub">Tüm zamanlar</div>
    </div>
    <div class="stat-card success">
        <div class="stat-label">Bugün İşlem</div>
        <div class="stat-value"><?= $bugun ?></div>
        <div class="stat-sub"><?= date('d.m.Y') ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Bugün Ekleme</div>
        <div class="stat-value"><?= $bugun_ekle ?></div>
        <div class="stat-sub">Yeni kayıt</div>
    </div>
    <div class="stat-card danger">
        <div class="stat-label">Bugün Silme</div>
        <div class="stat-value"><?= $bugun_sil ?></div>
        <div class="stat-sub">Silinen kayıt</div>
    </div>
</div>

<!-- Filtre -->
<div class="card">
    <div class="card-title" style="cursor:pointer;" onclick="toggleFiltre()">
        🔍 Filtrele
        <span id="filtre_ok" style="float:right;"><?= $filtre_aktif ? '▲' : '▼' ?></span>
        <?php if ($filtre_aktif): ?><span class="badge badge-warning" style="margin-left:8px;">Aktif</span><?php endif; ?>
    </div>
    <div id="filtre_panel" style="<?= $filtre_aktif ? '' : 'display:none;' ?>">
        <form method="get">
            <div class="form-grid">
                <div class="form-group">
                    <label>Kullanıcı</label>
                    <input type="text" name="kullanici" value="<?= htmlspecialchars($f_kullanici) ?>" placeholder="Ad veya kullanıcı adı">
                </div>
                <div class="form-group">
                    <label>Aksiyon</label>
                    <select name="aksiyon">
                        <option value="">Tümü</option>
                        <option value="ekle"     <?= $f_aksiyon=='ekle'    ?'selected':'' ?>>➕ Ekleme</option>
                        <option value="sil"      <?= $f_aksiyon=='sil'     ?'selected':'' ?>>🗑️ Silme</option>
                        <option value="guncelle" <?= $f_aksiyon=='guncelle'?'selected':'' ?>>✏️ Güncelleme</option>
                        <option value="giris"    <?= $f_aksiyon=='giris'   ?'selected':'' ?>>🔐 Giriş</option>
                        <option value="cikis"    <?= $f_aksiyon=='cikis'   ?'selected':'' ?>>🚪 Çıkış</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Modül</label>
                    <select name="modul">
                        <option value="">Tümü</option>
                        <option value="arac_kayit"  <?= $f_modul=='arac_kayit' ?'selected':'' ?>>🚗 Araç Yağ Kaydı</option>
                        <option value="tesis_kayit" <?= $f_modul=='tesis_kayit'?'selected':'' ?>>🏭 Tesis Yağ Kaydı</option>
                        <option value="arac"        <?= $f_modul=='arac'       ?'selected':'' ?>>🚗 Araç</option>
                        <option value="tesis"       <?= $f_modul=='tesis'      ?'selected':'' ?>>🏭 Tesis</option>
                        <option value="urun"        <?= $f_modul=='urun'       ?'selected':'' ?>>🛢️ Ürün</option>
                        <option value="kullanici"   <?= $f_modul=='kullanici'  ?'selected':'' ?>>👤 Kullanıcı</option>
                        <option value="auth"        <?= $f_modul=='auth'       ?'selected':'' ?>>🔐 Oturum</option>
                        <option value="islendi">✅ Depoya İşlendi</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Başlangıç Tarihi</label>
                    <input type="date" name="tarih_bas" value="<?= htmlspecialchars($f_tarih_bas) ?>">
                </div>
                <div class="form-group">
                    <label>Bitiş Tarihi</label>
                    <input type="date" name="tarih_bit" value="<?= htmlspecialchars($f_tarih_bit) ?>">
                </div>
            </div>
            <div class="btn-group" style="margin-top:14px;">
                <button type="submit" class="btn btn-primary">🔍 Filtrele</button>
                <a href="logs.php" class="btn btn-secondary">✕ Temizle</a>
            </div>
        </form>
    </div>
</div>

<!-- Log Listesi -->
<div class="card">
    <div class="card-title" style="display:flex;align-items:center;gap:10px;">
        <span style="flex:1;">📋 Log Kayıtları <span style="font-weight:400;font-size:13px;color:var(--muted);">(<?= count($loglar) ?> kayıt)</span></span>
        <?php if (!empty($loglar)): ?>
        <button class="btn btn-sm btn-secondary" onclick="tumunuSec()" id="sec_btn">Tümünü Seç</button>
        <button class="btn btn-sm btn-danger" onclick="seciliSil()" style="display:none;" id="sil_btn">🗑️ Seçilileri Sil (<span id="sec_sayi">0</span>)</button>
        <?php endif; ?>
    </div>

    <?php if (empty($loglar)): ?>
    <div style="text-align:center;padding:40px;color:var(--muted);">
        <div style="font-size:36px;margin-bottom:10px;">📭</div>
        Bu filtreye uygun log bulunamadı.
    </div>
    <?php else: ?>
    <div class="log-liste">
        <?php foreach ($loglar as $log):
            $aksiyon_stil = match($log['action']) {
                'ekle'      => ['renk' => '#16a34a', 'bg' => '#dcfce7', 'ikon' => '➕', 'etiket' => 'Ekleme'],
                'sil'       => ['renk' => '#dc2626', 'bg' => '#fee2e2', 'ikon' => '🗑️', 'etiket' => 'Silme'],
                'guncelle'  => ['renk' => '#d97706', 'bg' => '#fef3c7', 'ikon' => '✏️', 'etiket' => 'Güncelleme'],
                'giris'     => ['renk' => '#2563a8', 'bg' => '#dbeafe', 'ikon' => '🔐', 'etiket' => 'Giriş'],
                'cikis'     => ['renk' => '#64748b', 'bg' => '#f1f5f9', 'ikon' => '🚪', 'etiket' => 'Çıkış'],
                default     => ['renk' => '#64748b', 'bg' => '#f1f5f9', 'ikon' => '•',  'etiket' => $log['action']],
            };
            $eski = $log['old_value'] ? json_decode($log['old_value'], true) : null;
            $yeni = $log['new_value'] ? json_decode($log['new_value'], true) : null;

            // Hedef link hesapla
            $hedef_url = null;
            $kid = (int)($log['record_id'] ?? 0);
            if ($kid) {
                switch ($log['module']) {
                    case 'arac':
                        $hedef_url = ROOT_URL . 'pages/operations/vehicle_detail.php?id=' . $kid;
                        break;
                    case 'arac_kayit':
                    case 'islendi':
                        if (isset($kayit_hedef_map[$kid])) {
                            $km = $kayit_hedef_map[$kid];
                            if ($km['turu'] === 'arac' && $km['hedef_id'])
                                $hedef_url = ROOT_URL . 'pages/operations/vehicle_detail.php?id=' . $km['hedef_id'] . '#kayit-' . $kid;
                            elseif ($km['turu'] === 'tesis' && $km['hedef_id'])
                                $hedef_url = ROOT_URL . 'pages/operations/facility_detail.php?id=' . $km['hedef_id'] . '#kayit-' . $kid;
                        }
                        break;
                    case 'tesis_kayit':
                        if (isset($kayit_hedef_map[$kid])) {
                            $km = $kayit_hedef_map[$kid];
                            if ($km['hedef_id'])
                                $hedef_url = ROOT_URL . 'pages/operations/facility_detail.php?id=' . $km['hedef_id'] . '#kayit-' . $kid;
                        }
                        break;
                    case 'tesis':
                        $hedef_url = ROOT_URL . 'pages/operations/facility_detail.php?id=' . $kid;
                        break;
                    case 'urun':
                        $hedef_url = ROOT_URL . 'pages/operations/products.php';
                        break;
                    case 'kullanici':
                        $hedef_url = ROOT_URL . 'pages/management/users.php';
                        break;
                }
            }
        ?>
        <div class="log-item <?= $log['action'] === 'sil' ? 'log-sil' : '' ?> <?= $hedef_url ? 'log-tiklanabilir' : '' ?>"
             <?= $hedef_url ? 'onclick="logTikla(event, \''.htmlspecialchars($hedef_url).'\')" title="Git →"' : '' ?>>
            <!-- Checkbox -->
            <input type="checkbox" class="log-cb" value="<?= $log['id'] ?>"
                   onclick="event.stopPropagation(); secGuncelle()"
                   style="width:16px;height:16px;flex-shrink:0;accent-color:var(--danger);cursor:pointer;margin-top:2px;">
            <div class="log-aksiyon" style="background:<?= $aksiyon_stil['bg'] ?>;color:<?= $aksiyon_stil['renk'] ?>;">
                <?= $aksiyon_stil['ikon'] ?> <?= $aksiyon_stil['etiket'] ?>
            </div>
            <div class="log-icerik">
                <div class="log-aciklama">
                    <?= htmlspecialchars($log['description']) ?>
                    <?php if ($hedef_url): ?>
                    <span style="font-size:10px;color:var(--muted);margin-left:6px;">→ git</span>
                    <?php endif; ?>
                </div>
                <div class="log-meta">
                    <span>👤 <strong><?= htmlspecialchars($log['full_name'] ?? $log['username'] ?? 'Bilinmiyor') ?></strong></span>
                    <span style="color:var(--border)">·</span>
                    <span>📦 <?= htmlspecialchars($log['module']) ?></span>
                    <?php if ($log['record_id']): ?>
                    <span style="color:var(--border)">·</span>
                    <span>#<?= $log['record_id'] ?></span>
                    <?php endif; ?>
                    <span style="color:var(--border)">·</span>
                    <span>🌐 <?= htmlspecialchars($log['ip_address'] ?? '-') ?></span>
                </div>
                <!-- Silinen veri varsa göster -->
                <?php if ($eski && $log['action'] === 'sil'): ?>
                <div class="log-veri log-veri-eski">
                    <span class="log-veri-baslik">🗂️ Silinen Veri:</span>
                    <?php foreach ($eski as $k => $v): ?>
                    <?php if (!in_array($k, ['password','is_active']) && $v !== null && $v !== ''): ?>
                    <span class="log-veri-item"><em><?= htmlspecialchars($k) ?></em>: <?= htmlspecialchars((string)$v) ?></span>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if ($yeni && $log['action'] === 'ekle'): ?>
                <div class="log-veri log-veri-yeni">
                    <span class="log-veri-baslik">📝 Eklenen Veri:</span>
                    <?php foreach ($yeni as $k => $v): ?>
                    <?php if (!in_array($k, ['password']) && $v !== null && $v !== ''): ?>
                    <span class="log-veri-item"><em><?= htmlspecialchars($k) ?></em>: <?= htmlspecialchars((string)$v) ?></span>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if ($log['action'] === 'guncelle' && ($eski || $yeni)): ?>
                <div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;font-size:11px;">
                    <?php if ($eski): foreach ($eski as $k => $v): if (in_array($k, ['password'])) continue; ?>
                    <span class="log-veri-eski-span">
                        <em><?= htmlspecialchars($k) ?></em>:
                        <?= ($v !== null && $v !== '') ? htmlspecialchars((string)$v) : '<span class="text-empty">boş</span>' ?>
                    </span>
                    <?php endforeach; endif; ?>
                    <?php if ($eski && $yeni): ?>
                    <span style="color:var(--muted);font-size:13px;">→</span>
                    <?php endif; ?>
                    <?php if ($yeni): foreach ($yeni as $k => $v): if (in_array($k, ['password'])) continue; ?>
                    <span class="log-veri-yeni-span">
                        <em><?= htmlspecialchars($k) ?></em>:
                        <?= ($v !== null && $v !== '') ? htmlspecialchars((string)$v) : '<span class="text-empty">boş</span>' ?>
                    </span>
                    <?php endforeach; endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="log-zaman">
                <?= date('d.m.Y', strtotime($log['created_at'])) ?><br>
                <strong><?= date('H:i:s', strtotime($log['created_at'])) ?></strong>
                <br>
                <a href="?log_sil=<?= $log['id'] ?>&<?= http_build_query(array_diff_key($_GET, ['log_sil'=>''])) ?>"
                   onclick="event.stopPropagation(); return confirm('Bu log kaydını silmek istiyor musunuz?')"
                   class="log-sil-link">🗑️ sil</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Toplu Silme Modal -->
<div id="temizleModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;">
    <div class="modal-box" style="max-width:420px;">
        <div style="font-weight:700;font-size:16px;margin-bottom:16px;">🗑️ Logları Temizle</div>
        <form method="post" action="logs.php" onsubmit="return onayTemizle()">
            <div class="form-group">
                <label>Silme Yöntemi</label>
                <select name="toplu_sil_mod" id="sil_mod" onchange="silModDegisti(this.value)">
                    <option value="tarih">📅 Belirli tarihten öncesini sil</option>
                    <option value="tumü">⚠️ Tümünü sil</option>
                </select>
            </div>
            <div class="form-group" id="tarih_grup">
                <label>Bu tarihe kadar olan logları sil</label>
                <input type="date" name="sil_tarih_bit" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
            </div>
            <div class="warn-box" id="tumu_uyari" style="display:none;">
                ⚠️ <strong>Dikkat:</strong> Tüm log kayıtları kalıcı olarak silinecek!
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" class="btn btn-danger" style="flex:1;">🗑️ Sil</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('temizleModal').style.display='none'">İptal</button>
            </div>
        </form>
    </div>
</div>

<!-- Seçili Sil Formu -->
<form id="seciliSilForm" method="post" action="logs.php" style="display:none;">
    <input type="hidden" name="toplu_sil_mod" value="secili">
    <div id="secili_ids"></div>
</form>

<style>
.log-liste { display:flex; flex-direction:column; gap:0; }
.log-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 4px;
    border-bottom: 1px solid var(--border);
    transition: background .15s;
}
.log-item:last-child { border-bottom: none; }
.log-item:hover { background: var(--hover-bg); }
.log-tiklanabilir { cursor: pointer; }
.log-tiklanabilir:hover { background: var(--hover-bg-blue) !important; }
.log-sil { background: var(--danger-l) !important; }
.log-sil:hover { background: rgba(239, 68, 68, 0.25) !important; }

.log-aksiyon {
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
    flex-shrink: 0;
    text-align: center;
}
.log-icerik { flex: 1; min-width: 0; overflow: hidden; }
.log-aciklama {
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 4px;
    word-break: break-word;
}
.log-meta {
    font-size: 11px;
    color: var(--muted);
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
    align-items: center;
    line-height: 1.7;
}
.log-veri {
    margin-top: 6px;
    padding: 8px 10px;
    border-radius: 6px;
    font-size: 11px;
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    align-items: flex-start;
    word-break: break-all;
    overflow: hidden;
}
.log-veri-eski { background: var(--danger-l); border: 1px solid var(--danger); }
.log-veri-yeni { background: var(--success-l); border: 1px solid var(--success); }
.log-veri-baslik { font-weight: 700; color: var(--muted); width: 100%; margin-bottom: 2px; }
.log-veri-item {
    background: rgba(255, 255, 255, 0.1);
    padding: 2px 7px;
    border-radius: 4px;
    color: var(--text);
    max-width: 100%;
    overflow-wrap: break-word;
}
.log-veri-item em { font-style: normal; color: var(--muted); }
.log-zaman {
    font-size: 11px;
    color: var(--muted);
    text-align: right;
    flex-shrink: 0;
    white-space: nowrap;
    line-height: 1.6;
}

/* ── MOBİL ── */
@media (max-width: 540px) {
    .log-item {
        flex-wrap: wrap;
        gap: 6px;
        padding: 10px 2px;
    }
    .log-item > input[type=checkbox] { margin-top: 3px; flex-shrink: 0; }
    .log-aksiyon { font-size: 10px; padding: 3px 8px; }
    .log-icerik { width: calc(100% - 110px); }

    /* Tarih + sil linki: içeriğin altına tam genişlik */
    .log-zaman {
        order: 10;
        flex-basis: 100%;
        text-align: left;
        display: flex;
        align-items: center;
        gap: 8px;
        padding-left: 22px;
        white-space: normal;
        flex-shrink: unset;
    }
    .log-zaman br { display: none; }
    .log-zaman a { margin-top: 0 !important; margin-left: auto; }
    .log-aciklama { font-size: 12px; }
}
</style>

<script>
function toggleFiltre() {
    var p = document.getElementById('filtre_panel');
    var o = document.getElementById('filtre_ok');
    var g = p.style.display === 'none';
    p.style.display = g ? '' : 'none';
    o.textContent = g ? '▲' : '▼';
}

function logTikla(e, url) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'A' || e.target.tagName === 'BUTTON') return;
    window.location = url;
}

var tumSecili = false;
function tumunuSec() {
    tumSecili = !tumSecili;
    document.querySelectorAll('.log-cb').forEach(cb => cb.checked = tumSecili);
    document.getElementById('sec_btn').textContent = tumSecili ? 'Seçimi Kaldır' : 'Tümünü Seç';
    secGuncelle();
}

function secGuncelle() {
    var secili = document.querySelectorAll('.log-cb:checked').length;
    var silBtn = document.getElementById('sil_btn');
    document.getElementById('sec_sayi').textContent = secili;
    silBtn.style.display = secili > 0 ? '' : 'none';
}

function seciliSil() {
    var ids = [...document.querySelectorAll('.log-cb:checked')].map(cb => cb.value);
    if (ids.length === 0) return;
    if (!confirm(ids.length + ' log kaydını silmek istiyor musunuz?')) return;
    var container = document.getElementById('secili_ids');
    container.innerHTML = '';
    ids.forEach(id => {
        var inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'log_ids[]'; inp.value = id;
        container.appendChild(inp);
    });
    document.getElementById('seciliSilForm').submit();
}

function silModDegisti(val) {
    document.getElementById('tarih_grup').style.display = val === 'tarih' ? '' : 'none';
    document.getElementById('tumu_uyari').style.display = val === 'tumü' ? '' : 'none';
}

function onayTemizle() {
    var mod = document.getElementById('sil_mod').value;
    if (mod === 'tumü') return confirm('TÜM log kayıtları silinecek!\n\nBu işlem geri alınamaz. Emin misiniz?');
    return confirm('Seçilen tarihe kadar olan loglar silinecek. Emin misiniz?');
}

document.getElementById('temizleModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>