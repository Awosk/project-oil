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

/**
 * Project Oil — Kurulum Sihirbazı
 * install/index.php
 */

define('INSTALL_DIR', __DIR__);
define('ROOT_DIR',    dirname(__DIR__));

// ── Zaten kuruluysa engelle ──
if (file_exists(ROOT_DIR . '/.env')) {
    die(renderEngel());
}

session_start();

$adim    = (int)($_GET['adim'] ?? 1);
$hatalar = [];

// ════════════════════════════════════════════════
// ADIM İŞLEMLERİ
// ════════════════════════════════════════════════

// Adım 2 → DB test (AJAX)
if (isset($_GET['db_test'])) {
    header('Content-Type: application/json');
    $host = trim($_POST['db_host'] ?? '');
    $user = trim($_POST['db_user'] ?? '');
    $pass = $_POST['db_pass']      ?? '';
    $name = trim($_POST['db_name'] ?? '');
    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $var = $pdo->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = " . $pdo->quote($name))->fetchColumn();
        $mesaj = $var
            ? "Bağlantı başarılı! '$name' veritabanı mevcut, kurulumda kullanılacak."
            : "Bağlantı başarılı! '$name' veritabanı kurulum sırasında oluşturulacak.";
        echo json_encode(['ok' => true, 'mesaj' => $mesaj]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'mesaj' => $e->getMessage()]);
    }
    exit;
}

// Adım 2 → Form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adim2'])) {
    $_SESSION['db_host']  = trim($_POST['db_host']);
    $_SESSION['db_name']  = trim($_POST['db_name']);
    $_SESSION['db_user']  = trim($_POST['db_user']);
    $_SESSION['db_pass']  = $_POST['db_pass'];
    $_SESSION['site_adi'] = trim($_POST['site_adi']) ?: 'Project Oil';
    try {
        new PDO(
            "mysql:host={$_SESSION['db_host']};charset=utf8mb4",
            $_SESSION['db_user'], $_SESSION['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );
        header('Location: ?adim=3'); exit;
    } catch (PDOException $e) {
        $hatalar[] = 'MySQL sunucusuna bağlanılamadı: ' . $e->getMessage();
        $adim = 2;
    }
}

// Adım 3 → Admin kullanıcı + kurulum
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['adim3']) || isset($_POST['skip_admin']))) {
    $skip_admin = isset($_POST['skip_admin']);
    $ad_soyad   = trim($_POST['ad_soyad']      ?? '');
    $k_adi      = trim($_POST['kullanici_adi']  ?? '');
    $sifre      = $_POST['sifre']               ?? '';
    $sifre2     = $_POST['sifre2']              ?? '';

    if (!$skip_admin) {
        if (!$ad_soyad)         $hatalar[] = 'Ad Soyad zorunludur.';
        if (!$k_adi)            $hatalar[] = 'Kullanıcı adı zorunludur.';
        if (strlen($sifre) < 6) $hatalar[] = 'Şifre en az 6 karakter olmalıdır.';
        if ($sifre !== $sifre2) $hatalar[] = 'Şifreler eşleşmiyor.';
    }

    if (empty($hatalar)) {
        try {
            $pdo = new PDO(
                "mysql:host={$_SESSION['db_host']};charset=utf8mb4",
                $_SESSION['db_user'], $_SESSION['db_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $dbName = $_SESSION['db_name'];
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci");
            $pdo->exec("USE `$dbName`");

            $sql_dosya = INSTALL_DIR . '/database.sql';
            if (!file_exists($sql_dosya)) {
                $hatalar[] = 'database.sql dosyası bulunamadı.';
            } else {
                $sql     = file_get_contents($sql_dosya);
                $satirlar = explode("\n", str_replace("\r\n", "\n", $sql));
                $temiz   = [];
                foreach ($satirlar as $satir) {
                    $s = trim($satir);
                    if ($s === '' || str_starts_with($s, '--')) continue;
                    $temiz[] = $s;
                }
                $temiz_sql = implode("\n", $temiz);
                $pdo->exec("SET foreign_key_checks = 0");
                $ifadeler = array_filter(array_map('trim', explode(';', $temiz_sql)), fn($s) => $s !== '');
                foreach ($ifadeler as $ifade) { $pdo->exec($ifade); }
                $pdo->exec("SET foreign_key_checks = 1");

                if (!$skip_admin) {
                    $pdo->prepare(
                        "INSERT INTO users (full_name, username, password, role) VALUES (?, ?, ?, 'admin')"
                    )->execute([$ad_soyad, $k_adi, password_hash($sifre, PASSWORD_DEFAULT)]);
                }

                $ver_dosya    = ROOT_DIR . '/version.php';
                $ilk_versiyon = '1.0';
                if (file_exists($ver_dosya)) {
                    $ver_icerik = file_get_contents($ver_dosya);
                    if (preg_match("/SITE_VERSIYONU[',\\s]+([0-9.]+)/", $ver_icerik, $vm)) {
                        $ilk_versiyon = $vm[1];
                    }
                }
                $pdo->prepare("INSERT IGNORE INTO migrations (version) VALUES (?)")->execute([$ilk_versiyon]);

                $env = "DB_HOST={$_SESSION['db_host']}\n"
                     . "DB_NAME={$_SESSION['db_name']}\n"
                     . "DB_USER={$_SESSION['db_user']}\n"
                     . "DB_PASS={$_SESSION['db_pass']}\n"
                     . "DB_CHARSET=utf8mb4\n"
                     . "SITE_ADI={$_SESSION['site_adi']}\n";

                if (file_put_contents(ROOT_DIR . '/.env', $env) === false) {
                    $hatalar[] = '.env dosyası oluşturulamadı. Kök dizine yazma izni verin.';
                } else {
                    session_destroy();
                    header('Location: ?adim=4'); exit;
                }
            }
        } catch (PDOException $e) {
            $hatalar[] = 'Veritabanı hatası: ' . $e->getMessage();
        }
    }
    if (!empty($hatalar)) $adim = 3;
}

// ════════════════════════════════════════════════
// GEREKSİNİM KONTROL
// ════════════════════════════════════════════════
function gereksinimler(): array {
    return [
        ['PHP ≥ 8.0',        PHP_VERSION_ID >= 80000,       PHP_VERSION],
        ['PDO',              extension_loaded('pdo'),        extension_loaded('pdo')        ? 'Yüklü' : 'Eksik'],
        ['PDO MySQL',        extension_loaded('pdo_mysql'),  extension_loaded('pdo_mysql')  ? 'Yüklü' : 'Eksik'],
        ['ZipArchive',       class_exists('ZipArchive'),     class_exists('ZipArchive')     ? 'Yüklü' : 'Eksik'],
        ['allow_url_fopen',  ini_get('allow_url_fopen'),     ini_get('allow_url_fopen')     ? 'Açık'  : 'Kapalı'],
        ['.env yazılabilir', is_writable(ROOT_DIR),          is_writable(ROOT_DIR)          ? 'Yazılabilir' : 'İzin Yok'],
    ];
}
function tumGereksinimleriKarsiladi(): bool {
    foreach (gereksinimler() as [, $d]) { if (!$d) return false; }
    return true;
}

// ════════════════════════════════════════════════
// ENGEL EKRANI
// ════════════════════════════════════════════════
function renderEngel(): string {
    return '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8">
    <title>Kurulum Tamamlandı</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>.login-page{background:linear-gradient(145deg,#1e4d6b 0%,#2980b9 100%)}</style>
    </head><body>
    <div class="login-page">
        <div class="login-box" style="text-align:center;">
            <div class="login-logo">
                <div class="logo-icon">🔩</div>
                <h2>Kurulum Tamamlandı</h2>
                <p>Sistem halihazırda kurulu</p>
            </div>
            <div class="alert alert-success">✅ Giriş sayfasına yönlendiriliyorsunuz...</div>
            <script>setTimeout(function(){ window.location.href="../pages/auth/login.php"; }, 2000);</script>
        </div>
    </div></body></html>';
}

// Adım 3 için mevcut admin kontrolü
$admin_var_mi = false;
if ($adim === 3) {
    try {
        $pdo_test = new PDO(
            "mysql:host={$_SESSION['db_host']};dbname={$_SESSION['db_name']};charset=utf8mb4",
            $_SESSION['db_user'], $_SESSION['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $tbl = $pdo_test->query("SHOW TABLES LIKE 'kullanicilar'")->fetch();
        if ($tbl) {
            $say = $pdo_test->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            if ($say > 0) $admin_var_mi = true;
        }
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Oil — Kurulum</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .login-page {
            min-height: 100vh;
            background: linear-gradient(145deg, #1e4d6b 0%, #2980b9 100%);
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 32px 16px 48px;
        }

        /* ── Stepper ── */
        .stepper {
            display: flex;
            align-items: center;
            margin-bottom: 24px;
        }
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            flex: 1;
            position: relative;
        }
        .step::after {
            content: '';
            position: absolute;
            top: 14px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: var(--border);
            z-index: 0;
        }
        .step:last-child::after { display: none; }
        .step-dot {
            width: 28px; height: 28px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700;
            border: 2px solid var(--border);
            background: var(--card);
            color: var(--muted);
            z-index: 1;
            position: relative;
        }
        .step.aktif .step-dot { border-color: var(--primary-l); background: var(--primary-l); color: #fff; }
        .step.tamam .step-dot { border-color: var(--success); background: var(--success-l); color: var(--success-text); }
        .step.tamam::after   { background: var(--success); }
        .step-label { font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; font-weight: 700; white-space: nowrap; }
        .step.aktif .step-label { color: var(--primary-l); }
        .step.tamam .step-label { color: var(--success-text); }

        /* ── Gereksinim listesi ── */
        .req-list { display: flex; flex-direction: column; gap: 7px; margin-bottom: 16px; }
        .req-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 9px 12px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: var(--r-sm);
            font-size: 13px;
        }
        .req-name { color: var(--text); font-weight: 500; }
        .req-val  { font-size: 11px; color: var(--muted); margin: 0 10px; font-family: monospace; }
        .req-ok   { color: var(--success-text); font-weight: 700; }
        .req-fail { color: var(--danger-text);  font-weight: 700; }

        /* ── DB test butonu satırı ── */
        .db-test-row { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
        #db_test_sonuc { font-size: 12px; font-weight: 600; }

        /* ── Şifre güç çubuğu ── */
        .strength-bar { height: 3px; border-radius: 2px; background: var(--border); margin-top: 5px; overflow: hidden; }
        .strength-fill { height: 100%; border-radius: 2px; transition: width .3s, background .3s; width: 0; }

        /* ── Gizle/göster toggle ── */
        .pass-wrap { position: relative; }
        .pass-toggle {
            position: absolute; right: 11px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: var(--muted); cursor: pointer; font-size: 14px; padding: 2px 4px;
        }
        .pass-toggle:hover { color: var(--text); }

        /* ── Başarı kutusu ── */
        .success-box { text-align: center; padding: 12px 0; }
        .success-icon {
            width: 68px; height: 68px;
            background: var(--success-l);
            border: 2px solid var(--success);
            border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 32px; margin-bottom: 16px;
        }

        /* ── Login-box genişletme ── */
        .login-box { max-width: 600px; padding: 32px 28px; }
        .login-box .btn { width: 100%; padding: 12px; font-size: 14px; margin-top: 6px; border-radius: 10px; }
        .login-logo { margin-bottom: 24px; }

        /* ── Spinner inline ── */
        .spinner-inline {
            display: inline-block;
            width: 13px; height: 13px;
            border: 2px solid var(--border);
            border-top-color: var(--primary-l);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            vertical-align: middle;
            margin-right: 4px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
<div class="login-page">
    <div class="login-box">

        <!-- Logo -->
        <div class="login-logo">
            <div class="logo-icon">🔩</div>
            <h2>Project Oil</h2>
            <p>Kurulum Sihirbazı</p>
        </div>

        <!-- Stepper -->
        <div class="stepper">
            <?php
            $adimlar = ['Gereksinimler', 'Veritabanı', 'Admin', 'Tamamlandı'];
            foreach ($adimlar as $i => $etiket):
                $no    = $i + 1;
                $sinif = $no < $adim ? 'tamam' : ($no === $adim ? 'aktif' : '');
            ?>
            <div class="step <?= $sinif ?>">
                <div class="step-dot"><?= $no < $adim ? '✓' : $no ?></div>
                <div class="step-label"><?= $etiket ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Hatalar -->
        <?php foreach ($hatalar as $h): ?>
        <div class="alert alert-danger" style="margin-bottom:12px;">⚠️ <?= htmlspecialchars($h) ?></div>
        <?php endforeach; ?>

        <?php if ($adim === 4): ?>
        <!-- ════ ADIM 4: TAMAMLANDI ════ -->
        <div class="success-box">
            <div class="success-icon">✓</div>
            <div style="font-weight:700; font-size:18px; margin-bottom:8px;">Kurulum Tamamlandı!</div>
            <p style="color:var(--muted); font-size:13px; margin-bottom:20px;">Sistem başarıyla kuruldu. Giriş sayfasına yönlendiriliyorsunuz...</p>
            <div class="alert alert-success"><span class="spinner-inline"></span> Yönlendiriliyor...</div>
            <script>setTimeout(function(){ window.location.href="../pages/auth/login.php"; }, 3000);</script>
        </div>

        <?php elseif ($adim === 3): ?>
        <!-- ════ ADIM 3: ADMİN ════ -->
        <div style="font-weight:700; font-size:15px; margin-bottom:14px;">👤 Admin Hesabı Oluştur</div>

        <?php if ($admin_var_mi): ?>
			<div class="alert alert-warning" style="margin-bottom:14px; display: block; text-align: center; line-height: 1.6;">
				⚠️ Sistemde zaten bir admin hesabı var.
				<strong>Atla ve Bitir</strong> butonunu kullanabilirsiniz.
			</div>
		<?php endif; ?>

        <form method="post">
            <div class="form-grid">
                <div class="form-group">
                    <label>Ad Soyad *</label>
                    <input type="text" name="ad_soyad" placeholder="Ahmet Yılmaz"
                           value="<?= htmlspecialchars($_POST['ad_soyad'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Kullanıcı Adı *</label>
                    <input type="text" name="kullanici_adi" placeholder="admin"
                           value="<?= htmlspecialchars($_POST['kullanici_adi'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Şifre * <span style="font-weight:400;font-size:11px;color:var(--muted);">(min. 6 karakter)</span></label>
                    <div class="pass-wrap">
                        <input type="password" name="sifre" id="sifre" placeholder="••••••••"
                               oninput="sifreGuc(this.value)">
                        <button type="button" class="pass-toggle" onclick="sifreToggle('sifre',this)">👁</button>
                    </div>
                    <div class="strength-bar"><div class="strength-fill" id="strength_fill"></div></div>
                </div>
                <div class="form-group">
                    <label>Şifre Tekrar *</label>
                    <div class="pass-wrap">
                        <input type="password" name="sifre2" id="sifre2" placeholder="••••••••"
                               oninput="sifreTekrarKontrol()">
                        <button type="button" class="pass-toggle" onclick="sifreToggle('sifre2',this)">👁</button>
                    </div>
                    <div id="sifre_eslesme" style="font-size:11px;margin-top:4px;"></div>
                </div>
            </div>
            <div style="display:flex;gap:8px;margin-top:14px;">
                <a href="?adim=2" class="btn btn-secondary" style="flex:0 0 auto;width:auto;padding:12px 16px;">← Geri</a>
                <?php if ($admin_var_mi): ?>
                <button type="submit" name="skip_admin" value="1" class="btn btn-warning" formnovalidate style="flex:1;">Atla ve Bitir ⏭️</button>
                <?php endif; ?>
                <button type="submit" name="adim3" class="btn btn-primary" style="flex:1;">⚙️ Kurulumu Tamamla</button>
            </div>
        </form>

        <?php elseif ($adim === 2): ?>
        <!-- ════ ADIM 2: VERİTABANI ════ -->
        <div style="font-weight:700; font-size:15px; margin-bottom:14px;">🗄️ Veritabanı Ayarları</div>
        <form method="post" id="form_adim2">
            <input type="hidden" name="adim2" value="1">
            <div class="form-grid">
                <div class="form-group">
                    <label>Host</label>
                    <input type="text" name="db_host" id="db_host" placeholder="localhost"
                           value="<?= htmlspecialchars($_SESSION['db_host'] ?? 'localhost') ?>">
                </div>
                <div class="form-group">
                    <label>Veritabanı Adı</label>
                    <input type="text" name="db_name" id="db_name" placeholder="project_oil"
                           value="<?= htmlspecialchars($_SESSION['db_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Kullanıcı Adı</label>
                    <input type="text" name="db_user" id="db_user" placeholder="root"
                           value="<?= htmlspecialchars($_SESSION['db_user'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Şifre</label>
                    <div class="pass-wrap">
                        <input type="password" name="db_pass" id="db_pass" placeholder="(boş bırakılabilir)"
                               value="<?= htmlspecialchars($_SESSION['db_pass'] ?? '') ?>">
                        <button type="button" class="pass-toggle" onclick="sifreToggle('db_pass',this)">👁</button>
                    </div>
                </div>
                <div class="form-group full">
                    <label>Site Adı</label>
                    <input type="text" name="site_adi" placeholder="Project Oil"
                           value="<?= htmlspecialchars($_SESSION['site_adi'] ?? 'Project Oil') ?>">
                </div>
            </div>
            <div class="db-test-row">
                <button type="button" class="btn btn-secondary" style="width:auto;padding:10px 14px;" onclick="dbTest()">
                    🔌 Bağlantıyı Test Et
                </button>
                <span id="db_test_sonuc"></span>
            </div>
            <div style="display:flex;gap:8px;">
                <a href="?adim=1" class="btn btn-secondary" style="flex:0 0 auto;width:auto;padding:12px 16px;">← Geri</a>
                <button type="submit" class="btn btn-primary" style="flex:1;">Devam Et →</button>
            </div>
        </form>

        <?php else: ?>
        <!-- ════ ADIM 1: GEREKSİNİMLER ════ -->
        <div style="font-weight:700; font-size:15px; margin-bottom:14px;">🔍 Sistem Gereksinimleri</div>

        <?php $kontroller = gereksinimler(); $tumTamam = tumGereksinimleriKarsiladi(); ?>

        <div class="req-list">
            <?php foreach ($kontroller as [$isim, $durum, $deger]): ?>
            <div class="req-item">
                <span class="req-name"><?= $isim ?></span>
                <span class="req-val"><?= htmlspecialchars($deger) ?></span>
                <span class="<?= $durum ? 'req-ok' : 'req-fail' ?>"><?= $durum ? '✓' : '✗' ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (!$tumTamam): ?>
        <div class="alert alert-danger" style="margin-bottom:14px;">
            ⚠️ Bazı gereksinimler karşılanmıyor. Lütfen sunucu ayarlarınızı kontrol edin.
        </div>
        <?php else: ?>
        <div class="alert alert-success" style="margin-bottom:14px;">
            ✅ Tüm gereksinimler karşılanıyor. Devam edebilirsiniz.
        </div>
        <?php endif; ?>

        <a href="?adim=2"
           class="btn btn-primary <?= !$tumTamam ? 'disabled' : '' ?>"
           <?= !$tumTamam ? 'onclick="return false;" style="opacity:.4;cursor:not-allowed;"' : '' ?>>
            Devam Et →
        </a>
        <?php endif; ?>

        <p style="text-align:center;font-size:11px;color:var(--muted);margin-top:18px;opacity:.7;">
            Kurulum tamamlandıktan sonra <code>install/</code> klasörünü silin.
        </p>
    </div>
</div>

<script>
function sifreToggle(id, btn) {
    var inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    btn.textContent = inp.type === 'password' ? '👁' : '🙈';
}

function sifreGuc(val) {
    var fill  = document.getElementById('strength_fill');
    if (!fill) return;
    var puan  = 0;
    if (val.length >= 6)           puan++;
    if (val.length >= 10)          puan++;
    if (/[A-Z]/.test(val))         puan++;
    if (/[0-9]/.test(val))         puan++;
    if (/[^A-Za-z0-9]/.test(val)) puan++;
    var renkler = ['', '#ef4444', '#f59e0b', '#f59e0b', '#22c55e', '#22c55e'];
    fill.style.width      = (puan / 5 * 100) + '%';
    fill.style.background = renkler[puan] || '#ef4444';
    sifreTekrarKontrol();
}

function sifreTekrarKontrol() {
    var s1  = document.getElementById('sifre');
    var s2  = document.getElementById('sifre2');
    var msg = document.getElementById('sifre_eslesme');
    if (!s1 || !s2 || !msg || !s2.value) { if (msg) msg.textContent = ''; return; }
    if (s1.value === s2.value) {
        msg.style.color   = 'var(--success-text)';
        msg.textContent   = '✓ Şifreler eşleşiyor';
    } else {
        msg.style.color   = 'var(--danger-text)';
        msg.textContent   = '✗ Şifreler eşleşmiyor';
    }
}

async function dbTest() {
    var sonuc = document.getElementById('db_test_sonuc');
    sonuc.textContent  = '⏳ Test ediliyor...';
    sonuc.style.color  = 'var(--muted)';
    var form = document.getElementById('form_adim2');
    var data = new FormData(form);
    try {
        var r = await fetch('?db_test=1', { method: 'POST', body: data });
        var j = await r.json();
        sonuc.style.color = j.ok ? 'var(--success-text)' : 'var(--danger-text)';
        sonuc.textContent = (j.ok ? '✓ ' : '✗ ') + j.mesaj;
    } catch(e) {
        sonuc.style.color = 'var(--danger-text)';
        sonuc.textContent = '✗ İstek başarısız';
    }
}
</script>
</body>
</html>