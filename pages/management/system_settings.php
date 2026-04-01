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
require_once __DIR__ . '/../../classes/SistemAyarlari.php';
adminKontrol();

$sayfa_basligi = 'Sistem Ayarları';
$ku = mevcutKullanici();

// ── SMTP AYARLARINI KAYDET ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['smtp_kaydet'])) {
    csrfDogrula();
    $ayarlar = [
        'smtp_aktif'     => isset($_POST['smtp_aktif']) ? '1' : '0',
        'smtp_host'      => trim($_POST['smtp_host']      ?? ''),
        'smtp_port'      => trim($_POST['smtp_port']      ?? '587'),
        'smtp_sifrelem'  => in_array($_POST['smtp_sifrelem'] ?? '', ['tls','ssl','none']) ? $_POST['smtp_sifrelem'] : 'none',
        'smtp_kullanici' => trim($_POST['smtp_kullanici'] ?? ''),
        'smtp_gonderen'  => trim($_POST['smtp_gonderen']  ?? ''),
        'smtp_ad'        => trim($_POST['smtp_ad']        ?? ''),
    ];
    // Şifre boş bırakılırsa mevcut şifreyi koru
    if (!empty($_POST['smtp_sifre'])) {
        $ayarlar['smtp_sifre'] = $_POST['smtp_sifre'];
    }

    foreach ($ayarlar as $k => $v) {
        SistemAyarlari::ayarKaydet($pdo, $k, $v);
    }
    flash('SMTP ayarları kaydedildi.');
    header('Location: system_settings.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rate_limit_kaydet'])) {
    csrfDogrula();
    $rl_adet    = max(1, (int)($_POST['rate_limit_adet']   ?? 10));
    $rl_dakika  = max(1, (int)($_POST['rate_limit_dakika'] ?? 5));
    $cl_dakika  = max(1, (int)($_POST['cooldown_dakika']   ?? 15));
 
    SistemAyarlari::ayarKaydet($pdo, 'mail_rate_limit_adet',   $rl_adet);
    SistemAyarlari::ayarKaydet($pdo, 'mail_rate_limit_dakika', $rl_dakika);
    SistemAyarlari::ayarKaydet($pdo, 'mail_cooldown_dakika',   $cl_dakika);
    flash('Rate limit ayarları kaydedildi.');
    header('Location: system_settings.php'); exit;
}

// ── COOLDOWN İPTAL ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cooldown_iptal'])) {
    csrfDogrula();
    SistemAyarlari::cooldownIptal($pdo);
    flash('Cooldown iptal edildi. Paused mailler iptal edildi.');
    header('Location: system_settings.php'); exit;
}

// ── TEST MAILI GÖNDER ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_mail'])) {
    csrfDogrula();
    $test_email = trim($_POST['test_email'] ?? '');
    if ($test_email && filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $sonuc = testMailiGonder($pdo, $test_email);
        if ($sonuc['ok']) {
            flash('Test maili başarıyla gönderildi: ' . $test_email);
        } else {
            flash('Test maili gönderilemedi: ' . $sonuc['hata'], 'danger');
        }
    } else {
        flash('Geçerli bir e-posta adresi girin.', 'danger');
    }
    header('Location: system_settings.php'); exit;
}

// ── BİLDİRİM FİLTRELERİNİ KAYDET ──
// system_settings.php -> bildirim_kaydet POST bloğu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bildirim_kaydet'])) {
    csrfDogrula();
    $hedef_id = (int)$_POST['bildirim_kullanici_id'];
    
    if ($hedef_id) {
        $mail_aktif = isset($_POST['mail_bildirim_aktif']) ? 1 : 0;
        $secili     = $_POST['bildirim_filtreler'] ?? [];
        SistemAyarlari::adminBildirimGuncelle($pdo, $hedef_id, $mail_aktif, $secili);
        flash('Bildirim filtreleri ve aktiflik durumu kaydedildi.');
    }
    header('Location: system_settings.php'); exit;
}

// Mevcut SMTP ayarlarını çek
$smtp = smtpAyarlariGetir($pdo);
$rate_limit_adet   = sistemAyarGetir($pdo, 'mail_rate_limit_adet',   '10');
$rate_limit_dakika = sistemAyarGetir($pdo, 'mail_rate_limit_dakika', '5');
$cooldown_dakika   = sistemAyarGetir($pdo, 'mail_cooldown_dakika',   '15');
$cooldown_bitis    = sistemAyarGetir($pdo, 'mail_cooldown_bitis',    '');

// Admin kullanıcıları çek
$adminler = SistemAyarlari::getAdminKullanicilar($pdo);

// Bildirim filtrelerini çek
$mevcut_filtreler = SistemAyarlari::getMevcutFiltreler($pdo);

// Tanımlı modül/aksiyon çiftleri
$bildirim_secenekleri = [
    'Araç Yağ Kayıtları' => [
        ['arac_kayit', 'ekle',     '🛢️ Araç yağ kaydı eklendi'],
        ['arac_kayit', 'sil',      '🗑️ Araç yağ kaydı silindi'],
        ['arac_kayit', 'guncelle', '✏️ Araç yağ kaydı güncellendi'],
        ['islendi',    'guncelle', '✅ Kayıt depoya işlendi'],
    ],
    'Tesis Yağ Kayıtları' => [
        ['tesis_kayit', 'ekle',     '🛢️ Tesis yağ kaydı eklendi'],
        ['tesis_kayit', 'sil',      '🗑️ Tesis yağ kaydı silindi'],
        ['tesis_kayit', 'guncelle', '✏️ Tesis yağ kaydı güncellendi'],
    ],
    'Araç Yönetimi' => [
        ['arac',      'ekle',     '🚗 Araç eklendi'],
        ['arac',      'sil',      '🗑️ Araç silindi'],
        ['arac',      'guncelle', '✏️ Araç güncellendi'],
        ['arac_tur',  'ekle',     '🚗 Araç türü eklendi'],
        ['arac_tur',  'sil',      '🗑️ Araç türü silindi'],
        ['arac_tur',  'guncelle', '✏️ Araç türü güncellendi'],
    ],
    'Tesis Yönetimi' => [
        ['tesis', 'ekle',     '🏭 Tesis eklendi'],
        ['tesis', 'sil',      '🗑️ Tesis silindi'],
        ['tesis', 'guncelle', '✏️ Tesis güncellendi'],
    ],
    'Ürün Yönetimi' => [
        ['urun', 'ekle',     '📦 Ürün eklendi'],
        ['urun', 'sil',      '🗑️ Ürün silindi'],
        ['urun', 'guncelle', '✏️ Ürün güncellendi'],
    ],
    'Kullanıcı İşlemleri' => [
        ['kullanici', 'ekle',     '👤 Kullanıcı eklendi'],
        ['kullanici', 'sil',      '🗑️ Kullanıcı silindi'],
        ['kullanici', 'guncelle', '✏️ Kullanıcı güncellendi'],
        ['auth',      'giris',    '🔐 Sisteme giriş yapıldı'],
        ['auth',      'cikis',    '🚪 Sistemden çıkış yapıldı'],
    ],
    'Sistem' => [
        ['sistem', 'guncelle', '🔄 Sistem güncellendi'],
        ['sistem', 'ekle',     '💾 Veritabanı yedeği alındı'],
        ['sistem', 'sil',      '🗑️ Veritabanı yedeği silindi'],
    ],
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1><span>⚙️</span> Sistem Ayarları</h1>
</div>

<!-- ── SMTP AYARLARI ── -->
<div class="card">
    <div class="card-title">📧 E-posta / SMTP Ayarları</div>

    <form method="post">
        <?= csrfInput() ?>
        <div style="margin-bottom:18px;">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;text-transform:none;font-size:14px;font-weight:600;color:var(--text);background:var(--success-l);padding:12px 14px;border-radius:var(--r-sm);border:1.5px solid var(--success);">
                <input type="checkbox" name="smtp_aktif" id="smtp_aktif" value="1"
                       <?= ($smtp['smtp_aktif'] ?? '0') === '1' ? 'checked' : '' ?>
                       style="width:20px;height:20px;accent-color:var(--success);cursor:pointer;flex-shrink:0;">
                ✅ E-posta servisini aktif et
            </label>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label>SMTP Sunucu *</label>
                <input type="text" name="smtp_host" value="<?= htmlspecialchars($smtp['smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com">
            </div>

            <div class="form-group">
                <label>Port *</label>
                <input type="number" id="smtp_port" name="smtp_port" value="<?= htmlspecialchars($smtp['smtp_port'] ?? '587') ?>" placeholder="587">
            </div>

            <div class="form-group">
                <label>Şifreleme</label>
                <select id="smtp_sifrelem" name="smtp_sifrelem">
                    <option value="none" <?= ($smtp['smtp_sifrelem'] ?? '') === 'none' ? 'selected' : '' ?>>Yok (None)</option>
                    <option value="tls" <?= ($smtp['smtp_sifrelem'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS (Önerilen)</option>
                    <option value="ssl" <?= ($smtp['smtp_sifrelem'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                </select>
            </div>
            <div class="form-group">
                <label>SMTP Kullanıcı Adı *</label>
                <input type="text" name="smtp_kullanici" value="<?= htmlspecialchars($smtp['smtp_kullanici'] ?? '') ?>" placeholder="ornek@gmail.com">
            </div>
            <div class="form-group">
                <label>SMTP Şifre <?php if (!empty($smtp['smtp_sifre'])): ?><span style="font-weight:400;color:var(--success);font-size:11px;">✓ Kayıtlı</span><?php endif; ?></label>
                <input type="password" name="smtp_sifre" placeholder="<?= !empty($smtp['smtp_sifre']) ? '(değiştirmek için girin)' : 'Şifre' ?>">
            </div>
            <div class="form-group">
                <label>Gönderen E-posta <span style="font-weight:400;color:var(--muted);font-size:11px;">(boşsa kullanıcı adı)</span></label>
                <input type="text" name="smtp_gonderen" value="<?= htmlspecialchars($smtp['smtp_gonderen'] ?? '') ?>" placeholder="noreply@sirket.com">
            </div>
            <div class="form-group">
                <label>Gönderen Adı <span style="font-weight:400;color:var(--muted);font-size:11px;">(boşsa site adı)</span></label>
                <input type="text" name="smtp_ad" value="<?= htmlspecialchars($smtp['smtp_ad'] ?? '') ?>" placeholder="<?= SITE_ADI ?>">
            </div>
        </div>
        <div style="margin-top:14px;">
            <button type="submit" name="smtp_kaydet" class="btn btn-primary">💾 Kaydet</button>
        </div>
    </form>

    <!-- Test maili -->
    <?php if (($smtp['smtp_aktif'] ?? '0') === '1'): ?>
    <div style="margin-top:20px;padding-top:18px;border-top:1px solid var(--border);">
        <div style="font-weight:700;font-size:13px;margin-bottom:10px;">🧪 SMTP Bağlantısını Test Et</div>
        <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
            <?= csrfInput() ?>
            <div class="form-group" style="margin:0;flex:1;min-width:200px;">
                <input type="email" name="test_email" placeholder="Test için e-posta adresi" required>
            </div>
            <button type="submit" name="test_mail" class="btn btn-secondary">📤 Test Maili Gönder</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<!-- ── RATE LIMIT AYARLARI ── -->
<div class="card">
    <div class="card-title">⏱️ Mail Rate Limit & Cooldown</div>
    <p style="color:var(--muted);font-size:13px;margin-bottom:18px;">
        Belirli süre içinde çok fazla mail gönderilirse sistem otomatik olarak duraklatılır ve adminlere uyarı maili gönderilir.
    </p>
 
    <?php if ($cooldown_bitis && strtotime($cooldown_bitis) > time()): ?>
    <div class="warn-box" style="margin-bottom:18px;">
        ⚠️ <strong>Cooldown Aktif!</strong>
        Mail gönderimi duraklatıldı. Bitiş: <strong><?= date('d.m.Y H:i:s', strtotime($cooldown_bitis)) ?></strong>
        <form method="post" style="display:inline;margin-left:12px;">
            <?= csrfInput() ?>
            <button type="submit" name="cooldown_iptal" class="btn btn-sm btn-danger">✕ Cooldown'ı İptal Et</button>
        </form>
    </div>
    <?php endif; ?>
 
    <form method="post">
        <?= csrfInput() ?>
        <div class="form-grid">
            <div class="form-group">
                <label>Limit — Kaç Mail *</label>
                <input type="number" name="rate_limit_adet" value="<?= htmlspecialchars($rate_limit_adet) ?>" min="1" placeholder="10">
                <div class="form-note">Bu kadar mail gönderilince rate limit devreye girer.</div>
            </div>
            <div class="form-group">
                <label>Limit — Kaç Dakikada *</label>
                <input type="number" name="rate_limit_dakika" value="<?= htmlspecialchars($rate_limit_dakika) ?>" min="1" placeholder="5">
                <div class="form-note">Kaç dakikalık pencerede sayılsın.</div>
            </div>
            <div class="form-group">
                <label>Cooldown Süresi (Dakika) *</label>
                <input type="number" name="cooldown_dakika" value="<?= htmlspecialchars($cooldown_dakika) ?>" min="1" placeholder="15">
                <div class="form-note">Rate limit aşılınca kaç dakika duraklatılsın.</div>
            </div>
        </div>
        <div style="margin-top:14px;">
            <button type="submit" name="rate_limit_kaydet" class="btn btn-primary">💾 Kaydet</button>
        </div>
    </form>
</div>


<!-- ── BİLDİRİM FİLTRELERİ ── -->
<div class="card">
    <div class="card-title">🔔 Admin Bildirim Filtreleri</div>
    <p style="color:var(--muted);font-size:13px;margin-bottom:18px;">
        Hangi admin hangi işlemlerde e-posta alsın? Genel mail gönderimini durdurabilir veya modül bazlı filtreleyebilirsiniz.
    </p>

    <?php
    $mail_adminler = array_filter($adminler, fn($a) => !empty($a['email']));
    if (empty($mail_adminler)):
    ?>
    <div class="alert alert-warning">⚠️ E-posta adresi tanımlı aktif admin bulunamadı.</div>
    <?php else: ?>

    <?php foreach ($mail_adminler as $admin): ?>
    <div style="background:var(--bg); border:1px solid var(--border); border-radius:var(--r-sm); padding:16px; margin-bottom:14px;">
        
        <form method="post">
            <?= csrfInput() ?>
            <input type="hidden" name="bildirim_kullanici_id" value="<?= $admin['id'] ?>">

            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; padding-bottom:12px; border-bottom:1px solid var(--border); cursor:pointer;" 
                 onclick="toggleAdminPanel(<?= $admin['id'] ?>)">
                
                <div style="display:flex; align-items:center; gap:10px;">
                    <input type="checkbox" name="mail_bildirim_aktif" value="1" 
                           <?= ($admin['mail_bildirim_aktif'] == 1) ? 'checked' : '' ?> 
                           style="width:18px; height:18px; accent-color:var(--success);"
                           onclick="event.stopPropagation();">
                    
                    <div style="display:flex; flex-direction:column;">
                        <span style="font-weight:700; font-size:14px;">
                            👤 <?= htmlspecialchars($admin['ad_soyad']) ?>
                        </span>
                        <span style="font-weight:400; font-size:12px; color:var(--muted);">
                            (<?= htmlspecialchars($admin['email']) ?>)
                        </span>
                    </div>
                </div>

                <div style="display:flex; align-items:center; gap:12px;">
                    <div style="display:flex; gap:5px;" onclick="event.stopPropagation();">
                        <button type="button" class="btn btn-sm btn-secondary" onclick="topluSec(this, true)" style="font-size:11px; padding:4px 8px;">✅ Tümü</button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="topluSec(this, false)" style="font-size:11px; padding:4px 8px;">❌ Seçme</button>
                    </div>
                    <span id="admin_ok_<?= $admin['id'] ?>" style="font-size:12px; color:var(--muted); transition:.2s;">▼</span>
                </div>
            </div>

            <div id="admin_panel_<?= $admin['id'] ?>" style="display:none; padding-top:16px;">
                
                <?php foreach ($bildirim_secenekleri as $grup_adi => $secenekler): ?>
                <div style="margin-bottom:14px;">
                    <div style="font-size:11px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.5px; margin-bottom:8px;"><?= $grup_adi ?></div>
                    <div style="display:flex; flex-wrap:wrap; gap:8px;">
                        <?php foreach ($secenekler as [$modul, $aksiyon, $etiket]): ?>
                        <?php $key = $modul . '|' . $aksiyon; $checked = isset($mevcut_filtreler[$admin['id']][$key]); ?>
                        <label style="display:flex; align-items:center; gap:6px; padding:6px 12px; border:1.5px solid <?= $checked ? 'var(--primary)' : 'var(--border)' ?>; border-radius:6px; cursor:pointer; font-size:12px; font-weight:500; background:<?= $checked ? 'var(--primary-bg-l)' : 'var(--card)' ?>; color:<?= $checked ? 'var(--primary-text)' : 'var(--text)' ?>;">
                            <input type="checkbox" name="bildirim_filtreler[]" value="<?= $key ?>" class="filtre-check"
                                   <?= $checked ? 'checked' : '' ?>
                                   style="width:14px; height:14px; accent-color:var(--primary); cursor:pointer;"
                                   onchange="filtreStilGuncelle(this)">
                            <?= $etiket ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <button type="submit" name="bildirim_kaydet" class="btn btn-sm btn-primary" style="margin-top:6px;">💾 Değişiklikleri Kaydet</button>
            </div>

        </form>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
// Admin panel açıp kapatma (islemler.php mantığıyla)
function toggleAdminPanel(adminId) {
    var panel = document.getElementById('admin_panel_' + adminId);
    var ok = document.getElementById('admin_ok_' + adminId);
    
    var gizli = (panel.style.display === 'none');
    
    panel.style.display = gizli ? 'block' : 'none';
    ok.textContent = gizli ? '▲' : '▼';
}

// Filtreler kutucuğu seçildiğinde stilini (renklerini) günceller
function filtreStilGuncelle(el) {
    const parent = el.closest('label');
    if (el.checked) {
        parent.style.borderColor = 'var(--primary)';
        parent.style.background = 'var(--primary-bg-l)';
        parent.style.color = 'var(--primary-text)';
    } else {
        parent.style.borderColor = 'var(--border)';
        parent.style.background = 'var(--card)';
        parent.style.color = 'var(--text)';
    }
}

// Tümünü Seç / Tümünü Kaldır
function topluSec(btn, durum) {
    const form = btn.closest('form');
    const checks = form.querySelectorAll('.filtre-check');
    
    checks.forEach(check => {
        check.checked = durum;
        filtreStilGuncelle(check);
    });
}

// SMTP ve Şifreleme Port Otomasyonu
document.addEventListener("DOMContentLoaded", function() {
    const selectSifreleme = document.getElementById('smtp_sifrelem');
    const inputPort = document.getElementById('smtp_port');

    if(selectSifreleme && inputPort) {
        selectSifreleme.addEventListener('change', function() {
            const secilen = this.value;
            
            if (secilen === 'ssl') {
                inputPort.value = 465;
            } else if (secilen === 'tls') {
                inputPort.value = 587;
            } else if (secilen === 'none') {
                inputPort.value = 25;
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
