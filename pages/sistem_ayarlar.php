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
require_once __DIR__ . '/../includes/mail.php';
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
        'smtp_sifrelem'  => in_array($_POST['smtp_sifrelem'] ?? '', ['tls','ssl']) ? $_POST['smtp_sifrelem'] : 'tls',
        'smtp_kullanici' => trim($_POST['smtp_kullanici'] ?? ''),
        'smtp_gonderen'  => trim($_POST['smtp_gonderen']  ?? ''),
        'smtp_ad'        => trim($_POST['smtp_ad']        ?? ''),
    ];
    // Şifre boş bırakılırsa mevcut şifreyi koru
    if (!empty($_POST['smtp_sifre'])) {
        $ayarlar['smtp_sifre'] = $_POST['smtp_sifre'];
    }

    $stmt = $pdo->prepare("INSERT INTO sistem_ayarlar (anahtar, deger) VALUES (?, ?) ON DUPLICATE KEY UPDATE deger = VALUES(deger)");
    foreach ($ayarlar as $k => $v) {
        $stmt->execute([$k, $v]);
    }
    flash('SMTP ayarları kaydedildi.');
    header('Location: sistem_ayarlar.php'); exit;
}

// ── TEST MAILI GÖNDER ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_mail'])) {
    csrfDogrula();
    $test_email = trim($_POST['test_email'] ?? '');
    if ($test_email && filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $sonuc = mailGonder($pdo, $test_email, 'Test', '🧪 SMTP Test — ' . SITE_ADI,
            '<p>Bu bir test mailidir. SMTP ayarlarınız doğru çalışıyor!</p><p style="color:#666;font-size:13px;">Gönderim zamanı: ' . date('d.m.Y H:i:s') . '</p>'
        );
        if ($sonuc['ok']) {
            flash('Test maili başarıyla gönderildi: ' . $test_email);
        } else {
            flash('Test maili gönderilemedi: ' . $sonuc['hata'], 'danger');
        }
    } else {
        flash('Geçerli bir e-posta adresi girin.', 'danger');
    }
    header('Location: sistem_ayarlar.php'); exit;
}

// ── BİLDİRİM FİLTRELERİNİ KAYDET ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bildirim_kaydet'])) {
    csrfDogrula();
    $hedef_id = (int)$_POST['bildirim_kullanici_id'];
    if ($hedef_id) {
        // Mevcut filtreleri sil
        $pdo->prepare("DELETE FROM admin_bildirim_filtreler WHERE kullanici_id = ?")->execute([$hedef_id]);

        // Yenilerini ekle
        $secili = $_POST['bildirim_filtreler'] ?? [];
        if (!empty($secili)) {
            $stmt = $pdo->prepare("INSERT INTO admin_bildirim_filtreler (kullanici_id, aktif, modul, aksiyon) VALUES (?, 1, ?, ?)");
            foreach ($secili as $filtre) {
                [$modul, $aksiyon] = explode('|', $filtre);
                $stmt->execute([$hedef_id, $modul, $aksiyon]);
            }
        }
        flash('Bildirim filtreleri kaydedildi.');
    }
    header('Location: sistem_ayarlar.php'); exit;
}

// Mevcut SMTP ayarlarını çek
$smtp = smtpAyarlariGetir($pdo);

// Admin kullanıcıları çek
$adminler = $pdo->query("SELECT id, ad_soyad, kullanici_adi, email FROM kullanicilar WHERE rol = 'admin' AND aktif = 1 ORDER BY ad_soyad")->fetchAll();

// Bildirim filtrelerini çek
$mevcut_filtreler = [];
$filtre_rows = $pdo->query("SELECT kullanici_id, modul, aksiyon FROM admin_bildirim_filtreler WHERE aktif = 1")->fetchAll();
foreach ($filtre_rows as $row) {
    $mevcut_filtreler[$row['kullanici_id']][$row['modul'] . '|' . $row['aksiyon']] = true;
}

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

require_once __DIR__ . '/../includes/header.php';
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
                <input type="number" name="smtp_port" value="<?= htmlspecialchars($smtp['smtp_port'] ?? '587') ?>" placeholder="587">
            </div>
            <div class="form-group">
                <label>Şifreleme</label>
                <select name="smtp_sifrelem">
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

<!-- ── BİLDİRİM FİLTRELERİ ── -->
<div class="card">
    <div class="card-title">🔔 Admin Bildirim Filtreleri</div>
    <p style="color:var(--muted);font-size:13px;margin-bottom:18px;">
        Hangi admin hangi işlemlerde e-posta alsın? Sadece e-posta adresi tanımlı adminler listelenmiştir.
    </p>

    <?php
    $mail_adminler = array_filter($adminler, fn($a) => !empty($a['email']));
    if (empty($mail_adminler)):
    ?>
    <div class="alert alert-warning">⚠️ E-posta adresi tanımlı admin bulunamadı. Kullanıcı yönetiminden admin hesaplarına e-posta ekleyin.</div>
    <?php else: ?>

    <?php foreach ($mail_adminler as $admin): ?>
    <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--r-sm);padding:16px;margin-bottom:14px;">
        <div style="font-weight:700;font-size:14px;margin-bottom:4px;">
            👤 <?= htmlspecialchars($admin['ad_soyad']) ?>
            <span style="font-weight:400;font-size:12px;color:var(--muted);">(<?= htmlspecialchars($admin['email']) ?>)</span>
        </div>
        <form method="post" style="margin-top:14px;">
            <?= csrfInput() ?>
            <input type="hidden" name="bildirim_kullanici_id" value="<?= $admin['id'] ?>">

            <?php foreach ($bildirim_secenekleri as $grup_adi => $secenekler): ?>
            <div style="margin-bottom:14px;">
                <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;"><?= $grup_adi ?></div>
                <div style="display:flex;flex-wrap:wrap;gap:8px;">
                    <?php foreach ($secenekler as [$modul, $aksiyon, $etiket]): ?>
                    <?php $key = $modul . '|' . $aksiyon; $checked = isset($mevcut_filtreler[$admin['id']][$key]); ?>
                    <label style="display:flex;align-items:center;gap:6px;padding:6px 12px;border:1.5px solid <?= $checked ? 'var(--primary)' : 'var(--border)' ?>;border-radius:6px;cursor:pointer;font-size:12px;font-weight:500;background:<?= $checked ? 'var(--primary-bg-l)' : 'var(--card)' ?>;color:<?= $checked ? 'var(--primary-text)' : 'var(--text)' ?>;">
                        <input type="checkbox" name="bildirim_filtreler[]" value="<?= $key ?>"
                               <?= $checked ? 'checked' : '' ?>
                               style="width:14px;height:14px;accent-color:var(--primary);cursor:pointer;"
                               onchange="this.closest('label').style.borderColor=this.checked?'var(--primary)':'var(--border)';this.closest('label').style.background=this.checked?'var(--primary-bg-l)':'var(--card)';this.closest('label').style.color=this.checked?'var(--primary-text)':'var(--text)';">
                        <?= $etiket ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <button type="submit" name="bildirim_kaydet" class="btn btn-sm btn-primary" style="margin-top:6px;">💾 Kaydet</button>
        </form>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
