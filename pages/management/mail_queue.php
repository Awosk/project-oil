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
require_once __DIR__ . '/../../includes/mail.php';
adminKontrol();

$sayfa_basligi = 'Mail Kuyruğu';
$ku = mevcutKullanici();

// ── TEK MAIL YENİDEN KUYRUĞA EKLE (retry) ──
if (isset($_GET['retry'])) {
    $rid = (int)$_GET['retry'];
    $pdo->prepare("UPDATE mail_queue SET status='pending', attempt_count=0, error_message=NULL WHERE id=? AND status IN ('failed','cancelled')")
        ->execute([$rid]);
    flash('Mail yeniden kuyruğa eklendi.');
    header('Location: mail_queue.php'); exit;
}

// ── TEK MAIL SİL ──
if (isset($_GET['sil'])) {
    $sid = (int)$_GET['sil'];
    $pdo->prepare("DELETE FROM mail_queue WHERE id=?")->execute([$sid]);
    flash('Mail silindi.');
    header('Location: mail_queue.php'); exit;
}

// ── TOPLU İŞLEM ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toplu_islem'])) {
    csrfDogrula();
    $islem = $_POST['toplu_islem'];

    if ($islem === 'temizle_hepsi') {
        $silinen = $pdo->exec("DELETE FROM mail_queue WHERE status IN ('sent','failed','cancelled')");
        flash($silinen . ' adet tamamlanmış mail silindi.');
    } elseif ($islem === 'retry_hepsi') {
        $guncellenen = $pdo->exec("UPDATE mail_queue SET status='pending', attempt_count=0, error_message=NULL WHERE status IN ('failed','cancelled')");
        flash($guncellenen . ' adet mail yeniden kuyruğa alındı.');
    } elseif ($islem === 'iptal_pending') {
        $guncellenen = $pdo->exec("UPDATE mail_queue SET status='cancelled' WHERE status='pending'");
        flash($guncellenen . ' adet bekleyen mail iptal edildi.');
    } elseif ($islem === 'tum_temizle') {
        $silinen = $pdo->exec("DELETE FROM mail_queue WHERE status NOT IN ('processing')");
        flash($silinen . ' adet mail silindi.');
    }
    header('Location: mail_queue.php'); exit;
}

// ── FİLTRELER ──
$f_status = $_GET['status'] ?? '';
$f_email  = trim($_GET['email'] ?? '');
$f_sayfa  = max(1, (int)($_GET['sayfa'] ?? 1));
$sayfa_basina = 30;

$where  = ['1=1'];
$params = [];

if ($f_status && in_array($f_status, ['pending','sent','failed','paused','processing','force','cancelled'])) {
    $where[]  = 'status = ?';
    $params[] = $f_status;
}
if ($f_email) {
    $where[]  = 'to_email LIKE ?';
    $params[] = '%' . $f_email . '%';
}

$where_sql = implode(' AND ', $where);

// Sayım & istatistik
$stats = $pdo->query("
    SELECT
        COUNT(*) AS toplam,
        SUM(CASE WHEN status='pending'    THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status='sent'       THEN 1 ELSE 0 END) AS sent,
        SUM(CASE WHEN status='failed'     THEN 1 ELSE 0 END) AS failed,
        SUM(CASE WHEN status='paused'     THEN 1 ELSE 0 END) AS paused,
        SUM(CASE WHEN status='processing' THEN 1 ELSE 0 END) AS processing,
        SUM(CASE WHEN status='force'      THEN 1 ELSE 0 END) AS `force`,
        SUM(CASE WHEN status='cancelled'  THEN 1 ELSE 0 END) AS cancelled
    FROM mail_queue
")->fetch();

$toplam_kayit = (int)$pdo->prepare("SELECT COUNT(*) FROM mail_queue WHERE $where_sql")->execute($params) 
    ? (int)$pdo->prepare("SELECT COUNT(*) FROM mail_queue WHERE $where_sql")->execute($params) 
    : 0;

// Düzgün sayım
$cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM mail_queue WHERE $where_sql");
$cnt_stmt->execute($params);
$toplam_kayit = (int)$cnt_stmt->fetchColumn();

$toplam_sayfa = max(1, (int)ceil($toplam_kayit / $sayfa_basina));
$f_sayfa = min($f_sayfa, $toplam_sayfa);
$offset  = ($f_sayfa - 1) * $sayfa_basina;

$mailler = $pdo->prepare("
    SELECT * FROM mail_queue
    WHERE $where_sql
    ORDER BY created_at DESC
    LIMIT $sayfa_basina OFFSET $offset
");
$mailler->execute($params);
$mailler = $mailler->fetchAll();

// Cooldown durumu
$cooldown_bitis   = sistemAyarGetir($pdo, 'mail_cooldown_bitis', '');
$cooldown_aktif   = $cooldown_bitis && strtotime($cooldown_bitis) > time();
$smtp_aktif       = smtpAktifMi($pdo);

$filtre_aktif = $f_status || $f_email;

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
/* ── DURUM BADGE RENKLERİ ── */
.sq-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}
.sq-pending    { background: var(--warning-l);    color: var(--warning-text); }
.sq-sent       { background: var(--success-l);    color: var(--success-text); }
.sq-failed     { background: var(--danger-l);     color: var(--danger-text); }
.sq-paused     { background: var(--info-l);       color: var(--info-text); }
.sq-processing { background: var(--primary-bg-l); color: var(--primary-text); }
.sq-force      { background: #fce7f3;             color: #be185d; }
.sq-cancelled  { background: var(--hover-bg);     color: var(--muted); border: 1px solid var(--border); }

/* ── STAT KARTLARI ── */
.sq-stat-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-bottom: 16px;
}
@media (max-width: 767px) {
    .sq-stat-grid { grid-template-columns: repeat(2, 1fr); }
}
.sq-stat {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--r-sm);
    padding: 12px 14px;
    cursor: pointer;
    transition: all .15s;
    text-decoration: none;
    display: block;
}
.sq-stat:hover { border-color: var(--primary-l); transform: translateY(-1px); box-shadow: var(--shadow-md); }
.sq-stat.aktif { border-color: var(--primary-l); background: var(--hover-bg-blue); }
.sq-stat-sayi  { font-size: 26px; font-weight: 800; color: var(--primary); line-height: 1.1; }
.sq-stat-etiket { font-size: 11px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .4px; margin-top: 2px; }

/* ── MAIL SATIRI ── */
.sq-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 4px;
    border-bottom: 1px solid var(--border);
    transition: background .12s;
}
.sq-row:last-child { border-bottom: none; }
.sq-row:hover { background: var(--hover-bg); }
.sq-row-icerik { flex: 1; min-width: 0; }
.sq-row-email  { font-weight: 700; font-size: 13px; color: var(--text); }
.sq-row-konu   { font-size: 12px; color: var(--muted); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sq-row-meta   { font-size: 11px; color: var(--muted); margin-top: 4px; display: flex; flex-wrap: wrap; gap: 6px; }
.sq-row-sag    { text-align: right; flex-shrink: 0; }
.sq-row-tarih  { font-size: 11px; color: var(--muted); }
.sq-row-islem  { display: flex; gap: 4px; margin-top: 6px; justify-content: flex-end; }
.sq-hata       { margin-top: 6px; padding: 6px 10px; background: var(--danger-l); border-radius: 6px; font-size: 11px; color: var(--danger-text); word-break: break-all; }

/* ── TOPLU İŞLEM ARAÇ ÇUBUĞU ── */
.sq-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 4px;
}

/* ── COOLDOWN BANNER ── */
.sq-cooldown-banner {
    background: linear-gradient(135deg, var(--danger-l), #fde8e8);
    border: 2px solid var(--danger);
    border-radius: var(--r-sm);
    padding: 14px 18px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
</style>

<div class="page-header">
    <h1><span>📬</span> Mail Kuyruğu</h1>
    <?php if (!$smtp_aktif): ?>
    <span class="badge badge-danger">⛔ SMTP Kapalı</span>
    <?php else: ?>
    <span class="badge badge-success">✅ SMTP Aktif</span>
    <?php endif; ?>
</div>

<?php if ($cooldown_aktif): ?>
<div class="sq-cooldown-banner">
    <div style="display:flex; align-items:center; gap:10px;">
        <span style="font-size:24px;">🚨</span>
        <div>
            <div style="font-weight:700; color:var(--danger-text); font-size:14px;">Mail Gönderimi Duraklatıldı (Cooldown Aktif)</div>
            <div style="font-size:12px; color:var(--danger-text); opacity:.85; margin-top:2px;">
                Bitiş: <strong><?= date('d.m.Y H:i:s', strtotime($cooldown_bitis)) ?></strong>
                &nbsp;·&nbsp; Kalan: <strong id="sq_countdown">...</strong>
            </div>
        </div>
    </div>
    <a href="system_settings.php" class="btn btn-sm btn-danger">⚙️ Ayarlara Git</a>
</div>
<script>
(function() {
    var bitis = <?= strtotime($cooldown_bitis) * 1000 ?>;
    function guncelle() {
        var kalan = Math.max(0, Math.floor((bitis - Date.now()) / 1000));
        var dk = Math.floor(kalan / 60), sn = kalan % 60;
        document.getElementById('sq_countdown').textContent = dk + 'd ' + String(sn).padStart(2,'0') + 'sn';
        if (kalan > 0) setTimeout(guncelle, 1000);
        else location.reload();
    }
    guncelle();
})();
</script>
<?php endif; ?>

<!-- İSTATİSTİK KARTLARI -->
<div class="sq-stat-grid">
    <a href="mail_queue.php" class="sq-stat <?= !$f_status ? 'aktif' : '' ?>">
        <div class="sq-stat-sayi"><?= (int)$stats['toplam'] ?></div>
        <div class="sq-stat-etiket">📬 Toplam</div>
    </a>
    <a href="?status=pending" class="sq-stat <?= $f_status==='pending' ? 'aktif' : '' ?>" style="border-left: 3px solid var(--warning);">
        <div class="sq-stat-sayi" style="color:var(--warning-text);"><?= (int)$stats['pending'] ?></div>
        <div class="sq-stat-etiket">⏳ Bekliyor</div>
    </a>
    <a href="?status=sent" class="sq-stat <?= $f_status==='sent' ? 'aktif' : '' ?>" style="border-left: 3px solid var(--success);">
        <div class="sq-stat-sayi" style="color:var(--success-text);"><?= (int)$stats['sent'] ?></div>
        <div class="sq-stat-etiket">✅ Gönderildi</div>
    </a>
    <a href="?status=failed" class="sq-stat <?= $f_status==='failed' ? 'aktif' : '' ?>" style="border-left: 3px solid var(--danger);">
        <div class="sq-stat-sayi" style="color:var(--danger-text);"><?= (int)$stats['failed'] ?></div>
        <div class="sq-stat-etiket">❌ Başarısız</div>
    </a>
    <a href="?status=paused" class="sq-stat <?= $f_status==='paused' ? 'aktif' : '' ?>" style="border-left: 3px solid var(--info);">
        <div class="sq-stat-sayi" style="color:var(--info-text);"><?= (int)$stats['paused'] ?></div>
        <div class="sq-stat-etiket">⏸️ Duraklatıldı</div>
    </a>
    <a href="?status=processing" class="sq-stat <?= $f_status==='processing' ? 'aktif' : '' ?>">
        <div class="sq-stat-sayi"><?= (int)$stats['processing'] ?></div>
        <div class="sq-stat-etiket">⚙️ İşleniyor</div>
    </a>
    <a href="?status=force" class="sq-stat <?= $f_status==='force' ? 'aktif' : '' ?>" style="border-left: 3px solid #ec4899;">
        <div class="sq-stat-sayi" style="color:#be185d;"><?= (int)$stats['force'] ?></div>
        <div class="sq-stat-etiket">🚀 Zorla Gönder</div>
    </a>
    <a href="?status=cancelled" class="sq-stat <?= $f_status==='cancelled' ? 'aktif' : '' ?>">
        <div class="sq-stat-sayi" style="color:var(--muted);"><?= (int)$stats['cancelled'] ?></div>
        <div class="sq-stat-etiket">🚫 İptal</div>
    </a>
</div>

<!-- FİLTRE & TOPLU İŞLEM -->
<div class="card">
    <div class="sq-toolbar">
        <form method="get" style="display:flex; gap:8px; flex-wrap:wrap; flex:1;">
            <input type="text" name="email" value="<?= htmlspecialchars($f_email) ?>"
                   placeholder="🔍 E-posta ara..." style="flex:1; min-width:180px;">
            <select name="status" style="min-width:140px;">
                <option value="">Tüm durumlar</option>
                <option value="pending"    <?= $f_status==='pending'    ? 'selected' : '' ?>>⏳ Bekliyor</option>
                <option value="sent"       <?= $f_status==='sent'       ? 'selected' : '' ?>>✅ Gönderildi</option>
                <option value="failed"     <?= $f_status==='failed'     ? 'selected' : '' ?>>❌ Başarısız</option>
                <option value="paused"     <?= $f_status==='paused'     ? 'selected' : '' ?>>⏸️ Duraklatıldı</option>
                <option value="processing" <?= $f_status==='processing' ? 'selected' : '' ?>>⚙️ İşleniyor</option>
                <option value="force"      <?= $f_status==='force'      ? 'selected' : '' ?>>🚀 Force</option>
                <option value="cancelled"  <?= $f_status==='cancelled'  ? 'selected' : '' ?>>🚫 İptal</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">🔍 Filtrele</button>
            <?php if ($filtre_aktif): ?>
            <a href="mail_queue.php" class="btn btn-secondary btn-sm">✕ Temizle</a>
            <?php endif; ?>
        </form>
    </div>

    <div style="display:flex; gap:8px; flex-wrap:wrap; padding-top:12px; border-top:1px solid var(--border); margin-top:4px;">
        <form method="post" onsubmit="return confirm('Tüm başarısız/iptal mailler yeniden kuyruğa alınacak. Devam?')">
            <?= csrfInput() ?>
            <button type="submit" name="toplu_islem" value="retry_hepsi" class="btn btn-sm btn-secondary">🔄 Başarısızları Yeniden Dene</button>
        </form>
        <form method="post" onsubmit="return confirm('Gönderilmiş, başarısız ve iptal edilmiş mailler silinecek. Devam?')">
            <?= csrfInput() ?>
            <button type="submit" name="toplu_islem" value="temizle_hepsi" class="btn btn-sm btn-secondary">🗑️ Tamamlananları Temizle</button>
        </form>
        <form method="post" onsubmit="return confirm('Bekleyen tüm mailler iptal edilecek. Devam?')">
            <?= csrfInput() ?>
            <button type="submit" name="toplu_islem" value="iptal_pending" class="btn btn-sm btn-warning">⛔ Bekleyenleri İptal Et</button>
        </form>
        <form method="post" onsubmit="return confirm('KUYRUĞUNDAKİ TÜM MAİLLER SİLİNECEK (işlenenler hariç). Emin misiniz?')">
            <?= csrfInput() ?>
            <button type="submit" name="toplu_islem" value="tum_temizle" class="btn btn-sm btn-danger">💣 Tümünü Temizle</button>
        </form>
    </div>
</div>

<!-- MAIL LİSTESİ -->
<div class="card">
    <div class="card-title" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
        <span>
            📋 Mailler
            <span style="font-weight:400; font-size:13px; color:var(--muted);">(<?= $toplam_kayit ?> kayıt<?= $filtre_aktif ? ' — filtrelenmiş' : '' ?>)</span>
        </span>
        <?php if ($toplam_sayfa > 1): ?>
        <span style="font-weight:400; font-size:12px; color:var(--muted);">
            Sayfa <?= $f_sayfa ?> / <?= $toplam_sayfa ?>
        </span>
        <?php endif; ?>
    </div>

    <?php if (empty($mailler)): ?>
    <div style="text-align:center; padding:40px; color:var(--muted);">
        <div style="font-size:40px; margin-bottom:10px;">📭</div>
        Bu filtreye uygun mail bulunamadı.
    </div>
    <?php else: ?>
    <div>
        <?php
        $status_config = [
            'pending'    => ['ikon' => '⏳', 'css' => 'sq-pending',    'etiket' => 'Bekliyor'],
            'sent'       => ['ikon' => '✅', 'css' => 'sq-sent',       'etiket' => 'Gönderildi'],
            'failed'     => ['ikon' => '❌', 'css' => 'sq-failed',     'etiket' => 'Başarısız'],
            'paused'     => ['ikon' => '⏸️', 'css' => 'sq-paused',     'etiket' => 'Duraklatıldı'],
            'processing' => ['ikon' => '⚙️', 'css' => 'sq-processing', 'etiket' => 'İşleniyor'],
            'force'      => ['ikon' => '🚀', 'css' => 'sq-force',      'etiket' => 'Force'],
            'cancelled'  => ['ikon' => '🚫', 'css' => 'sq-cancelled',  'etiket' => 'İptal'],
        ];
        foreach ($mailler as $m):
            $sc = $status_config[$m['status']] ?? ['ikon' => '•', 'css' => 'sq-cancelled', 'etiket' => $m['status']];
        ?>
        <div class="sq-row">
            <!-- Durum badge -->
            <div style="flex-shrink:0; padding-top:2px;">
                <span class="sq-badge <?= $sc['css'] ?>"><?= $sc['ikon'] ?> <?= $sc['etiket'] ?></span>
            </div>

            <!-- İçerik -->
            <div class="sq-row-icerik">
                <div class="sq-row-email">
                    <?= htmlspecialchars($m['to_name'] ?: $m['to_email']) ?>
                    <?php if ($m['to_name']): ?>
                    <span style="font-weight:400; color:var(--muted); font-size:11px;">&lt;<?= htmlspecialchars($m['to_email']) ?>&gt;</span>
                    <?php endif; ?>
                </div>
                <div class="sq-row-konu" title="<?= htmlspecialchars($m['subject']) ?>">
                    📌 <?= htmlspecialchars($m['subject']) ?>
                </div>
                <div class="sq-row-meta">
                    <span>🔁 Deneme: <strong><?= (int)$m['attempt_count'] ?></strong></span>
                    <span style="color:var(--border)">·</span>
                    <span>📅 Oluşturulma: <?= date('d.m.Y H:i', strtotime($m['created_at'])) ?></span>
                    <?php if ($m['sent_at']): ?>
                    <span style="color:var(--border)">·</span>
                    <span style="color:var(--success-text);">✅ Gönderilme: <?= date('d.m.Y H:i', strtotime($m['sent_at'])) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($m['error_message']): ?>
                <div class="sq-hata">
                    ⚠️ <strong>Hata:</strong> <?= htmlspecialchars($m['error_message']) ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- İşlemler -->
            <div class="sq-row-sag">
                <div class="sq-row-islem">
                    <?php if (in_array($m['status'], ['failed', 'cancelled'])): ?>
                    <a href="?retry=<?= $m['id'] ?>" class="btn btn-sm btn-secondary" title="Yeniden dene">🔄</a>
                    <?php endif; ?>
                    <a href="?sil=<?= $m['id'] ?>" class="btn btn-sm btn-danger"
                       onclick="return confirm('Bu mail kaydı silinecek. Emin misiniz?')" title="Sil">🗑️</a>
                </div>
                <div class="sq-row-tarih" style="margin-top:4px;">#<?= $m['id'] ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- SAYFALAMA -->
    <?php if ($toplam_sayfa > 1):
        $sp = array_diff_key($_GET, ['sayfa' => '']);
        function sqSayfaUrl($n, $base) { return 'mail_queue.php?' . http_build_query(array_merge($base, ['sayfa' => $n])); }
        $gBas = max(1, $f_sayfa - 2);
        $gBit = min($toplam_sayfa, $f_sayfa + 2);
    ?>
    <div style="display:flex; align-items:center; justify-content:center; gap:6px; padding:16px 0; flex-wrap:wrap;">
        <?php if ($f_sayfa > 1): ?>
        <a href="<?= sqSayfaUrl(1, $sp) ?>" class="btn btn-secondary btn-sm">«</a>
        <a href="<?= sqSayfaUrl($f_sayfa - 1, $sp) ?>" class="btn btn-secondary btn-sm">‹</a>
        <?php endif; ?>
        <?php if ($gBas > 1): ?><span style="color:var(--muted);">…</span><?php endif; ?>
        <?php for ($i = $gBas; $i <= $gBit; $i++): ?>
        <a href="<?= sqSayfaUrl($i, $sp) ?>"
           class="btn btn-sm <?= $i === $f_sayfa ? 'btn-primary' : 'btn-secondary' ?>"
           style="min-width:34px; text-align:center;"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($gBit < $toplam_sayfa): ?><span style="color:var(--muted);">…</span><?php endif; ?>
        <?php if ($f_sayfa < $toplam_sayfa): ?>
        <a href="<?= sqSayfaUrl($f_sayfa + 1, $sp) ?>" class="btn btn-secondary btn-sm">›</a>
        <a href="<?= sqSayfaUrl($toplam_sayfa, $sp) ?>" class="btn btn-secondary btn-sm">»</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>