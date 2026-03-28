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

// ── Zaten kuruluysa engelle ve yönlendir ──
if (file_exists(ROOT_DIR . '/.env')) {
    die(renderEngel());
}

session_start();

$adim   = (int)($_GET['adim'] ?? 1);
$hatalar = [];
$basarili = false;

// ════════════════════════════════════════════════
// ADIM İŞLEMLERİ
// ════════════════════════════════════════════════

// Adım 2 → Veritabanı bağlantı testi (AJAX)
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
        // Veritabanı zaten var mı kontrol et
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

    // dbname olmadan bağlantıyı doğrula
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

// Adım 3 → Admin kullanıcı oluştur + kurulumu tamamla
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adim3'])) {
    
    $skip_admin = isset($_POST['skip_admin']); // Atla butonuna basıldı mı?
    
    $ad_soyad = trim($_POST['ad_soyad'] ?? '');
    $k_adi    = trim($_POST['kullanici_adi'] ?? '');
    $sifre    = $_POST['sifre'] ?? '';
    $sifre2   = $_POST['sifre2'] ?? '';

    // Eğer "Atla" seçilmediyse form doğrulaması yap
    if (!$skip_admin) {
        if (!$ad_soyad)              $hatalar[] = 'Ad Soyad zorunludur.';
        if (!$k_adi)                 $hatalar[] = 'Kullanıcı adı zorunludur.';
        if (strlen($sifre) < 6)      $hatalar[] = 'Şifre en az 6 karakter olmalıdır.';
        if ($sifre !== $sifre2)      $hatalar[] = 'Şifreler eşleşmiyor.';
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

            // SQL dosyasını çalıştır (eski tablolar varsa database.sql'deki yapıya göre korunur)
            $sql_dosya = INSTALL_DIR . '/database.sql';
            if (!file_exists($sql_dosya)) {
                $hatalar[] = 'database.sql dosyası bulunamadı: ' . $sql_dosya;
            } else {
                $sql = file_get_contents($sql_dosya);

                $satirlar = explode("\n", str_replace("\r\n", "\n", $sql));
                $temiz = [];
                foreach ($satirlar as $satir) {
                    $s = trim($satir);
                    if ($s === '' || str_starts_with($s, '--')) continue;
                    $temiz[] = $s;
                }
                $temiz_sql = implode("\n", $temiz);

                $pdo->exec("SET foreign_key_checks = 0");
                $ifadeler = array_filter(
                    array_map('trim', explode(';', $temiz_sql)),
                    fn($s) => $s !== ''
                );
                foreach ($ifadeler as $ifade) {
                    $pdo->exec($ifade);
                }
                $pdo->exec("SET foreign_key_checks = 1");

                // Sadece yeni kullanıcı istenmişse admin ekle
                if (!$skip_admin) {
                    $pdo->prepare(
                        "INSERT INTO kullanicilar (ad_soyad, kullanici_adi, sifre, rol) VALUES (?, ?, ?, 'admin')"
                    )->execute([$ad_soyad, $k_adi, password_hash($sifre, PASSWORD_DEFAULT)]);
                }

                // İlk kurulum versiyonunu migration tablosuna kaydet
                $ver_dosya = ROOT_DIR . '/version.php';
                $ilk_versiyon = '1.0';
                if (file_exists($ver_dosya)) {
                    $ver_icerik = file_get_contents($ver_dosya);
                    if (preg_match("/SITE_VERSIYONU[',\\s]+([0-9.]+)/", $ver_icerik, $vm)) {
                        $ilk_versiyon = $vm[1];
                    }
                }
                $pdo->prepare("INSERT IGNORE INTO sistem_migrations (versiyon) VALUES (?)")->execute([$ilk_versiyon]);

                // .env dosyasını oluştur
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
// GEREKSİNİM KONTROL FONKSİYONLARI
// ════════════════════════════════════════════════
function gereksinimler(): array {
    return [
        ['PHP ≥ 8.0',        PHP_VERSION_ID >= 80000,         PHP_VERSION],
        ['PDO',              extension_loaded('pdo'),         extension_loaded('pdo') ? 'Yüklü' : 'Eksik'],
        ['PDO MySQL',        extension_loaded('pdo_mysql'),   extension_loaded('pdo_mysql') ? 'Yüklü' : 'Eksik'],
        ['ZipArchive',       class_exists('ZipArchive'),      class_exists('ZipArchive') ? 'Yüklü' : 'Eksik'],
        ['allow_url_fopen',  ini_get('allow_url_fopen'),      ini_get('allow_url_fopen') ? 'Açık' : 'Kapalı'],
        ['.env yazılabilir', is_writable(ROOT_DIR),           is_writable(ROOT_DIR) ? 'Yazılabilir' : 'İzin Yok'],
    ];
}

function tumGereksinimleriKarsiladi(): bool {
    foreach (gereksinimler() as [, $durum]) {
        if (!$durum) return false;
    }
    return true;
}

// ════════════════════════════════════════════════
// ENGEL VE YÖNLENDİRME EKRANI (.env varsa)
// ════════════════════════════════════════════════
function renderEngel(): string {
    return '<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8">
    <title>Kurulum Tamamlandı</title>
    <style>
        body{font-family:sans-serif;background:#0f172a;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;}
        .box{background:#1e293b;border:1px solid #334155;border-radius:16px;padding:40px;text-align:center;max-width:440px;}
        h2{color:#3fb950;margin:0 0 12px;}p{color:#94a3b8;margin:0;}
        .loader{border:4px solid #334155;border-top:4px solid #3fb950;border-radius:50%;width:30px;height:30px;animation:spin 1s linear infinite;margin:20px auto;}
        @keyframes spin {0%{transform:rotate(0deg);}100%{transform:rotate(360deg);}}
    </style></head><body>
    <div class="box">
        <div style="font-size:48px;margin-bottom:16px;">✅</div>
        <h2>Kurulum Tamamlandı</h2>
        <p>Sistem halihazırda kurulu. Giriş sayfasına yönlendiriliyorsunuz...</p>
        <div class="loader"></div>
        <script>setTimeout(function(){ window.location.href="../pages/auth/login.php"; }, 2000);</script>
    </div></body></html>';
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Oil — Kurulum</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:        #0d1117;
            --surface:   #161b22;
            --surface2:  #21262d;
            --border:    #30363d;
            --border2:   #3d444d;
            --text:      #e6edf3;
            --muted:     #7d8590;
            --primary:   #1f6feb;
            --primary-l: #388bfd;
            --success:   #238636;
            --success-l: #2ea043;
            --success-t: #3fb950;
            --danger:    #da3633;
            --danger-l:  #f85149;
            --warning:   #9e6a03;
            --warning-t: #d29922;
            --mono:      'IBM Plex Mono', monospace;
            --sans:      'IBM Plex Sans', sans-serif;
        }

        body {
            font-family: var(--sans);
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 40px 16px 60px;
        }

        /* ── HEADER ── */
        .install-header { text-align: center; margin-bottom: 36px; }
        .install-logo { width: 56px; height: 56px; background: linear-gradient(135deg, #1f6feb, #388bfd); border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; font-size: 28px; margin-bottom: 14px; box-shadow: 0 0 0 1px rgba(56,139,253,.3), 0 8px 24px rgba(31,111,235,.25); }
        .install-header h1 { font-size: 22px; font-weight: 700; color: var(--text); letter-spacing: -.3px; }
        .install-header p { font-size: 13px; color: var(--muted); margin-top: 4px; }

        /* ── ADIM ÇİZGİSİ ── */
        .stepper { display: flex; align-items: center; gap: 0; margin-bottom: 28px; width: 100%; max-width: 560px; }
        .step { display: flex; flex-direction: column; align-items: center; gap: 6px; flex: 1; position: relative; }
        .step::after { content: ''; position: absolute; top: 15px; left: 50%; width: 100%; height: 2px; background: var(--border); z-index: 0; }
        .step:last-child::after { display: none; }
        .step-dot { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; border: 2px solid var(--border); background: var(--surface); color: var(--muted); z-index: 1; position: relative; transition: all .3s; }
        .step.aktif .step-dot { border-color: var(--primary-l); background: var(--primary); color: #fff; box-shadow: 0 0 0 4px rgba(56,139,253,.2); }
        .step.tamam .step-dot { border-color: var(--success-t); background: var(--success); color: #fff; }
        .step.tamam::after { background: var(--success); }
        .step-label { font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; font-weight: 600; white-space: nowrap; }
        .step.aktif .step-label { color: var(--primary-l); }
        .step.tamam .step-label { color: var(--success-t); }

        /* ── KART ── */
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 28px 28px; width: 100%; max-width: 560px; }
        .card-title { font-size: 16px; font-weight: 700; color: var(--text); margin-bottom: 6px; display: flex; align-items: center; gap: 8px; }
        .card-desc { font-size: 13px; color: var(--muted); margin-bottom: 24px; line-height: 1.6; }

        /* ── FORM ── */
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 7px; }
        input[type=text], input[type=password] { width: 100%; padding: 10px 13px; background: var(--surface2); border: 1px solid var(--border2); border-radius: 7px; color: var(--text); font-size: 14px; font-family: var(--sans); transition: border-color .2s, box-shadow .2s; outline: none; }
        input:focus { border-color: var(--primary-l); box-shadow: 0 0 0 3px rgba(56,139,253,.15); }
        input::placeholder { color: var(--muted); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        /* ── BUTONLAR ── */
        .btn { display: inline-flex; align-items: center; gap: 7px; padding: 10px 20px; border-radius: 7px; font-size: 14px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: all .2s; font-family: var(--sans); }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-l); }
        .btn-secondary { background: var(--surface2); color: var(--text); border: 1px solid var(--border2); }
        .btn-secondary:hover { border-color: var(--primary-l); color: var(--primary-l); }
        .btn-success { background: var(--success); color: #fff; }
        .btn-success:hover { background: var(--success-l); }
        .btn-warning { background: var(--warning); color: #fff; }
        .btn-warning:hover { background: var(--warning-t); }
        .btn:disabled { opacity: .5; cursor: not-allowed; }

        /* ── UYARILAR & LOADER ── */
        .alert { padding: 12px 14px; border-radius: 7px; font-size: 13px; margin-bottom: 16px; display: flex; gap: 8px; align-items: flex-start; line-height:1.5; }
        .alert-danger  { background: rgba(218,54,51,.12); border: 1px solid rgba(248,81,73,.3); color: var(--danger-l); }
        .alert-success { background: rgba(35,134,54,.12); border: 1px solid rgba(63,185,80,.3); color: var(--success-t); }
        .alert-warning { background: rgba(158,106,3,.15); border: 1px solid rgba(210,153,34,.3); color: var(--warning-t); }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .loader-sm { border: 3px solid var(--border2); border-top: 3px solid var(--success-t); border-radius: 50%; width: 24px; height: 24px; animation: spin 1s linear infinite; margin: 10px auto; }

        /* ── GEREKSİNİM TABLOSU ── */
        .req-list { list-style: none; display: flex; flex-direction: column; gap: 8px; }
        .req-item { display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: var(--surface2); border: 1px solid var(--border); border-radius: 7px; font-size: 13px; }
        .req-name { color: var(--text); font-weight: 500; }
        .req-val  { font-family: var(--mono); font-size: 11px; color: var(--muted); margin: 0 12px; }
        .req-ok   { color: var(--success-t); font-size: 16px; }
        .req-fail { color: var(--danger-l);  font-size: 16px; }

        /* ── DB TEST BUTONU ── */
        .db-test-row { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
        #db_test_sonuc { font-size: 12px; font-weight: 600; }

        /* ── TAMAMLANDI ── */
        .success-box { text-align: center; padding: 16px 0; }
        .success-icon { width: 72px; height: 72px; background: linear-gradient(135deg, var(--success), var(--success-l)); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 36px; margin-bottom: 20px; box-shadow: 0 0 0 8px rgba(35,134,54,.15); }
        .success-box h2 { font-size: 22px; font-weight: 700; margin-bottom: 8px; }
        .success-box p { color: var(--muted); font-size: 14px; line-height: 1.7; margin-bottom: 6px; }

        /* ── ŞİFRE GÖSTERİCİ & GÜÇ ── */
        .pass-wrap { position: relative; }
        .pass-toggle { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--muted); cursor: pointer; font-size: 14px; padding: 2px 4px; }
        .pass-toggle:hover { color: var(--text); }
        .strength-bar { height: 3px; border-radius: 2px; background: var(--border); margin-top: 6px; overflow: hidden; }
        .strength-fill { height: 100%; border-radius: 2px; transition: width .3s, background .3s; width: 0; }

        /* ── BÖLÜCÜ & FOOTER ── */
        .divider { border: none; border-top: 1px solid var(--border); margin: 24px 0; }
        .install-footer { margin-top: 24px; font-size: 11px; color: var(--muted); text-align: center; }
    </style>
</head>
<body>

<div class="install-header">
    <div class="install-logo">🔩</div>
    <h1>Project Oil</h1>
    <p>Kurulum Sihirbazı</p>
</div>

<div class="stepper">
    <?php
    $adimlar = ['Gereksinimler', 'Veritabanı', 'Admin', 'Tamamlandı'];
    foreach ($adimlar as $i => $etiket):
        $no = $i + 1;
        $sinif = $no < $adim ? 'tamam' : ($no === $adim ? 'aktif' : '');
    ?>
    <div class="step <?= $sinif ?>">
        <div class="step-dot"><?= $no < $adim ? '✓' : $no ?></div>
        <div class="step-label"><?= $etiket ?></div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($adim === 4): ?>
<div class="card">
    <div class="success-box">
        <div class="success-icon">✓</div>
        <h2>Kurulum Tamamlandı!</h2>
        <p>Sistem başarıyla kuruldu ve ayarlandı.<br>Giriş sayfasına yönlendiriliyorsunuz...</p>
        <div class="loader-sm"></div>
        <script>setTimeout(function(){ window.location.href="../pages/auth/login.php"; }, 3000);</script>
    </div>
</div>

<?php elseif ($adim === 3): ?>
<?php
// Adım 3'e gelirken mevcut bir admin olup olmadığını kontrol et
$admin_var_mi = false;
try {
    $pdo_test = new PDO(
        "mysql:host={$_SESSION['db_host']};dbname={$_SESSION['db_name']};charset=utf8mb4",
        $_SESSION['db_user'], $_SESSION['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    // kullanicilar tablosu var mı?
    $tbl = $pdo_test->query("SHOW TABLES LIKE 'kullanicilar'")->fetch();
    if ($tbl) {
        $say = $pdo_test->query("SELECT COUNT(*) FROM kullanicilar WHERE rol = 'admin'")->fetchColumn();
        if ($say > 0) {
            $admin_var_mi = true;
        }
    }
} catch (Exception $e) {
    // Veritabanı veya tablo henüz yok, doğal olarak admin de yok
}
?>
<div class="card">
    <div class="card-title">👤 Admin Hesabı Oluştur</div>
    <div class="card-desc">Bu hesap sisteme tam yetkiyle erişebilir. Güçlü bir şifre belirleyin.</div>

    <?php if ($admin_var_mi): ?>
    <div style="background-color: rgba(180, 83, 9, 0.1); border: 1px solid rgba(217, 119, 6, 0.5); border-radius: 8px; padding: 16px; display: flex; align-items: flex-start; gap: 12px; margin-bottom: 1rem;">
    
		<div style="color: #f59e0b; flex-shrink: 0; margin-top: 2px;">
			<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path>
				<line x1="12" y1="9" x2="12" y2="13"></line>
				<line x1="12" y1="17" x2="12.01" y2="17"></line>
			</svg>
		</div>

		<div>
			<h4 style="color: #f59e0b; margin: 0 0 4px 0; font-weight: 600; font-size: 15px;">Sistemde zaten bir admin hesabı bulunuyor.</h4>
			<p style="color: #d97706; margin: 0; font-size: 14px; line-height: 1.5;">
				Yeni bir hesap oluşturmak istemiyorsanız, aşağıdaki <strong style="color: #fcd34d;">Atla ve Bitir</strong> butonunu kullanarak bu adımı geçebilirsiniz.
			</p>
		</div>
	</div>
    <?php endif; ?>

    <?php foreach ($hatalar as $h): ?>
    <div class="alert alert-danger">⚠️ <?= htmlspecialchars($h) ?></div>
    <?php endforeach; ?>

    <form method="post">
        <input type="hidden" name="adim3" value="1">
        <div class="form-row">
            <div class="form-group">
                <label>Ad Soyad *</label>
                <input type="text" name="ad_soyad" required placeholder="Ahmet Yılmaz"
                       value="<?= htmlspecialchars($_POST['ad_soyad'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Kullanıcı Adı *</label>
                <input type="text" name="kullanici_adi" required placeholder="admin"
                       value="<?= htmlspecialchars($_POST['kullanici_adi'] ?? '') ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Şifre * <span style="text-transform:none;font-size:11px;color:var(--muted);">(min. 6 karakter)</span></label>
            <div class="pass-wrap">
                <input type="password" name="sifre" id="sifre" required minlength="6"
                       placeholder="••••••••" oninput="sifreGuc(this.value)">
                <button type="button" class="pass-toggle" onclick="sifreToggle('sifre', this)">👁</button>
            </div>
            <div class="strength-bar"><div class="strength-fill" id="strength_fill"></div></div>
        </div>
        <div class="form-group">
            <label>Şifre Tekrar *</label>
            <div class="pass-wrap">
                <input type="password" name="sifre2" id="sifre2" required minlength="6"
                       placeholder="••••••••" oninput="sifreTekrarKontrol()">
                <button type="button" class="pass-toggle" onclick="sifreToggle('sifre2', this)">👁</button>
            </div>
            <div id="sifre_eslesme" style="font-size:11px;margin-top:5px;"></div>
        </div>
        <hr class="divider">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <a href="?adim=2" class="btn btn-secondary">← Geri</a>
            <div style="display:flex; gap: 10px;">
                <?php if ($admin_var_mi): ?>
                <button type="submit" name="skip_admin" value="1" class="btn btn-warning" formnovalidate>
                    Atla ve Bitir ⏭️
                </button>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary" id="adim3_btn">
                    ⚙️ Kurulumu Tamamla
                </button>
            </div>
        </div>
    </form>
</div>

<?php elseif ($adim === 2): ?>
<div class="card">
    <div class="card-title">🗄️ Veritabanı Ayarları</div>
    <div class="card-desc">MySQL/MariaDB bağlantı bilgilerini girin. Veritabanı önceden oluşturulmuş olmalıdır.</div>

    <?php foreach ($hatalar as $h): ?>
    <div class="alert alert-danger">⚠️ <?= htmlspecialchars($h) ?></div>
    <?php endforeach; ?>

    <form method="post" id="form_adim2">
        <input type="hidden" name="adim2" value="1">
        <div class="form-row">
            <div class="form-group">
                <label>Host</label>
                <input type="text" name="db_host" id="db_host" required placeholder="localhost"
                       value="<?= htmlspecialchars($_SESSION['db_host'] ?? 'localhost') ?>">
            </div>
            <div class="form-group">
                <label>Veritabanı Adı</label>
                <input type="text" name="db_name" id="db_name" required placeholder="project_oil"
                       value="<?= htmlspecialchars($_SESSION['db_name'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Kullanıcı Adı</label>
                <input type="text" name="db_user" id="db_user" required placeholder="root"
                       value="<?= htmlspecialchars($_SESSION['db_user'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Şifre</label>
                <div class="pass-wrap">
                    <input type="password" name="db_pass" id="db_pass" placeholder="(boş bırakılabilir)"
                           value="<?= htmlspecialchars($_SESSION['db_pass'] ?? '') ?>">
                    <button type="button" class="pass-toggle" onclick="sifreToggle('db_pass', this)">👁</button>
                </div>
            </div>
        </div>
        <hr class="divider" style="margin:16px 0;">
        <div class="form-group">
            <label>Site Adı</label>
            <input type="text" name="site_adi" placeholder="Project Oil"
                   value="<?= htmlspecialchars($_SESSION['site_adi'] ?? 'Project Oil') ?>">
        </div>
        <hr class="divider">
        <div class="db-test-row">
            <button type="button" class="btn btn-secondary" onclick="dbTest()">🔌 Bağlantıyı Test Et</button>
            <span id="db_test_sonuc"></span>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <a href="?adim=1" class="btn btn-secondary">← Geri</a>
            <button type="submit" class="btn btn-primary">Devam Et →</button>
        </div>
    </form>
</div>

<?php else: ?>
<div class="card">
    <div class="card-title">🔍 Sistem Gereksinimleri</div>
    <div class="card-desc">Kuruluma başlamadan önce sunucunuzun gereksinimleri karşıladığından emin olalım.</div>

    <?php $kontroller = gereksinimler(); $tumTamam = tumGereksinimleriKarsiladi(); ?>

    <ul class="req-list">
        <?php foreach ($kontroller as [$isim, $durum, $deger]): ?>
        <li class="req-item">
            <span class="req-name"><?= $isim ?></span>
            <span class="req-val"><?= htmlspecialchars($deger) ?></span>
            <span class="<?= $durum ? 'req-ok' : 'req-fail' ?>"><?= $durum ? '✓' : '✗' ?></span>
        </li>
        <?php endforeach; ?>
    </ul>

    <?php if (!$tumTamam): ?>
    <div class="alert alert-danger" style="margin-top:20px;">
        ⚠️ Bazı gereksinimler karşılanmıyor. Lütfen sunucu ayarlarınızı kontrol edin.
    </div>
    <?php else: ?>
    <div class="alert alert-success" style="margin-top:20px;">
        ✓ Tüm gereksinimler karşılanıyor. Devam edebilirsiniz.
    </div>
    <?php endif; ?>

    <hr class="divider">
    <div style="display:flex;justify-content:flex-end;">
        <a href="?adim=2" class="btn btn-primary <?= !$tumTamam ? 'disabled' : '' ?>"
           <?= !$tumTamam ? 'onclick="return false;" style="opacity:.4;cursor:not-allowed;"' : '' ?>>
            Devam Et →
        </a>
    </div>
</div>
<?php endif; ?>

<div class="install-footer">
    Project Oil &mdash; Kurulum Sihirbazı &nbsp;·&nbsp; Tüm adımlar tamamlandıktan sonra <code>install/</code> klasörünü silin.
</div>

<script>
// ── Şifre göster/gizle ──
function sifreToggle(id, btn) {
    var inp = document.getElementById(id);
    if (inp.type === 'password') { inp.type = 'text'; btn.textContent = '🙈'; }
    else { inp.type = 'password'; btn.textContent = '👁'; }
}

// ── Şifre güç göstergesi ──
function sifreGuc(val) {
    var fill = document.getElementById('strength_fill');
    if (!fill) return;
    var puan = 0;
    if (val.length >= 6)  puan++;
    if (val.length >= 10) puan++;
    if (/[A-Z]/.test(val)) puan++;
    if (/[0-9]/.test(val)) puan++;
    if (/[^A-Za-z0-9]/.test(val)) puan++;
    var renkler = ['', '#f85149', '#d29922', '#d29922', '#3fb950', '#3fb950'];
    fill.style.width    = (puan / 5 * 100) + '%';
    fill.style.background = renkler[puan] || '#f85149';
    sifreTekrarKontrol();
}

// ── Şifre eşleşme kontrolü ──
function sifreTekrarKontrol() {
    var s1 = document.getElementById('sifre');
    var s2 = document.getElementById('sifre2');
    var msg = document.getElementById('sifre_eslesme');
    if (!s1 || !s2 || !msg || !s2.value) { if(msg) msg.textContent=''; return; }
    if (s1.value === s2.value) {
        msg.style.color = '#3fb950'; msg.textContent = '✓ Şifreler eşleşiyor';
    } else {
        msg.style.color = '#f85149'; msg.textContent = '✗ Şifreler eşleşmiyor';
    }
}

// ── Veritabanı bağlantı testi ──
async function dbTest() {
    var sonuc = document.getElementById('db_test_sonuc');
    sonuc.textContent = '⏳ Test ediliyor...';
    sonuc.style.color = '#7d8590';
    var form = document.getElementById('form_adim2');
    var data = new FormData(form);
    try {
        var r = await fetch('?db_test=1', { method:'POST', body: data });
        var j = await r.json();
        if (j.ok) {
            sonuc.style.color = '#3fb950';
            sonuc.textContent = '✓ ' + j.mesaj;
        } else {
            sonuc.style.color = '#f85149';
            sonuc.textContent = '✗ ' + j.mesaj;
        }
    } catch(e) {
        sonuc.style.color = '#f85149';
        sonuc.textContent = '✗ İstek başarısız';
    }
}
</script>

</body>
</html>