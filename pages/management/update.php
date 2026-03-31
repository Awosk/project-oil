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

$sayfa_basligi = 'Sistem Güncelleme';

define('GITHUB_REPO',    'Awosk/project-oil');
define('GITHUB_API',     'https://api.github.com/repos/' . GITHUB_REPO . '/releases/latest');
define('ROOT_DIR',       dirname(__DIR__, 2));
define('MIGRATIONS_DIR', ROOT_DIR . '/migrations');

// Korunacak yol/dosyalar — güncelleme sırasında üzerine yazılmaz
define('KORUNANLAR', [
    '.env',
    'pages/db_backups/',
]);

// ── Migration tablosu yoksa oluştur ──
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `migrations` (
        `version` varchar(20) NOT NULL,
        `applied_at` datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`version`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");
} catch (Exception $e) {}

// ── Uygulanan migrationları çek ──
function uygulanmisVersiyon($pdo): array {
    try {
        return $pdo->query("SELECT version FROM migrations ORDER BY version")
                   ->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) { return []; }
}

// ── GitHub API isteği ──
function githubApiIste(string $url): ?array {
    $ctx = stream_context_create(['http' => [
        'method'  => 'GET',
        'header'  => "User-Agent: ProjectOil-Updater/1.0\r\n",
        'timeout' => 10,
    ]]);
    $json = @file_get_contents($url, false, $ctx);
    if (!$json) return null;
    return json_decode($json, true);
}

// ── Versiyon karşılaştır ──
function yeniVersionVar(string $lokal, string $uzak): bool {
    return version_compare(ltrim($uzak, 'v'), ltrim($lokal, 'v'), '>');
}

// ── Koruma kontrolü ──
function korunuyorMu(string $dosyaYolu): bool {
    foreach (KORUNANLAR as $koruma) {
        if (str_starts_with($dosyaYolu, $koruma)) return true;
        if ($dosyaYolu === rtrim($koruma, '/')) return true;
    }
    return false;
}

// ── Klasörü recursive sil ──
function klasorSil(string $dir): void {
    if (!is_dir($dir)) return;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

// ════════════════════════════════════════════════
// AJAX: Sürüm kontrolü
// ════════════════════════════════════════════════
if (isset($_GET['kontrol'])) {
    header('Content-Type: application/json');
    $data = githubApiIste(GITHUB_API);
    if (!$data || empty($data['tag_name'])) {
        echo json_encode(['ok' => false, 'mesaj' => 'GitHub API\'ye erişilemedi.']);
        exit;
    }
    $uzak_tag     = $data['tag_name'];
    $uzak_versiyon = ltrim($uzak_tag, 'v');
    $yeni_var      = yeniVersionVar(SITE_VERSIYONU, $uzak_versiyon);

    // Migration listesi
    $assets = $data['assets'] ?? [];
    $migration_listesi = [];
    foreach ($assets as $asset) {
        if (preg_match('/^(\d+\.\d+(?:\.\d+)?)\.sql$/', $asset['name'], $m)) {
            $migration_listesi[] = $m[1];
        }
    }
    usort($migration_listesi, 'version_compare');

    // Zaten uygulanmış olanları filtrele
    $uygulanmis   = uygulanmisVersiyon($pdo);
    $bekleyen_sql = array_filter($migration_listesi, function($v) use ($uygulanmis) {
        return !in_array($v, $uygulanmis) && version_compare($v, SITE_VERSIYONU, '>');
    });

    echo json_encode([
        'ok'             => true,
        'lokal'          => SITE_VERSIYONU,
        'uzak'           => $uzak_versiyon,
        'tag'            => $uzak_tag,
        'yeni_var'       => $yeni_var,
        'zipball_url'    => $data['zipball_url'] ?? '',
        'aciklama'       => $data['body'] ?? '',
        'yayin_tarihi'   => $data['published_at'] ?? '',
        'bekleyen_sql'   => array_values($bekleyen_sql),
        'assets'         => $assets,
    ]);
    exit;
}

// ════════════════════════════════════════════════
// AJAX: Güncellemeyi uygula
// ════════════════════════════════════════════════
if (isset($_GET['guncelle']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $zipball_url   = $_POST['zipball_url']   ?? '';
    $uzak_versiyon = trim($_POST['uzak_versiyon'] ?? '');
    $assets_json   = $_POST['assets']        ?? '[]';
    $assets        = json_decode($assets_json, true) ?: [];

    if (!$zipball_url || !$uzak_versiyon) {
        echo json_encode(['ok' => false, 'mesaj' => 'Geçersiz istek.']); exit;
    }

    $log = [];
    $tmp_zip  = sys_get_temp_dir() . '/project_oil_update_' . time() . '.zip';
    $tmp_dir  = sys_get_temp_dir() . '/project_oil_extract_' . time();

    try {
        // ── 1. ZIP indir ──
        $log[] = '📥 ZIP indiriliyor...';
        $ctx = stream_context_create(['http' => [
            'method'          => 'GET',
            'header'          => "User-Agent: ProjectOil-Updater/1.0\r\n",
            'timeout'         => 60,
            'follow_location' => true,
        ]]);
        $zip_icerik = @file_get_contents($zipball_url, false, $ctx);
        if (!$zip_icerik) throw new Exception('ZIP dosyası indirilemedi. allow_url_fopen aktif mi?');
        file_put_contents($tmp_zip, $zip_icerik);
        $log[] = '✓ ZIP indirildi (' . round(filesize($tmp_zip) / 1024) . ' KB)';

        // ── 2. ZIP aç ──
        $log[] = '📦 ZIP açılıyor...';
        $zip = new ZipArchive();
        if ($zip->open($tmp_zip) !== true) throw new Exception('ZIP açılamadı.');
        mkdir($tmp_dir, 0755, true);
        $zip->extractTo($tmp_dir);
        $zip->close();
        unlink($tmp_zip);

        // GitHub zipball içinde Awosk-project-oil-XXXXX/ gibi bir klasör var
        $alt_klasorler = glob($tmp_dir . '/*', GLOB_ONLYDIR);
        if (empty($alt_klasorler)) throw new Exception('ZIP içeriği beklenmedik formatta.');
        $kaynak_dir = $alt_klasorler[0];
        $log[] = '✓ ZIP açıldı';

        // ── 3. Dosyaları kopyala ──
        $log[] = '🔄 Dosyalar güncelleniyor...';
        $guncellenen = 0;
        $atlanan     = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($kaynak_dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $goreceli = ltrim(str_replace($kaynak_dir, '', $item->getPathname()), '/\\');

            // Koruma kontrolü
            if (korunuyorMu($goreceli)) { $atlanan++; continue; }

            $hedef = ROOT_DIR . '/' . $goreceli;

            if ($item->isDir()) {
                if (!is_dir($hedef)) mkdir($hedef, 0755, true);
            } else {
                $hedef_klasor = dirname($hedef);
                if (!is_dir($hedef_klasor)) mkdir($hedef_klasor, 0755, true);
                copy($item->getPathname(), $hedef);
                $guncellenen++;
            }
        }
        $log[] = "✓ $guncellenen dosya güncellendi, $atlanan dosya korundu";

        // ── 4. SQL Migrationları uygula ──
        $uygulanmis = uygulanmisVersiyon($pdo);
        $migration_yapildi = 0;

        // Asset'lerden .sql dosyalarını bul
        $sql_assets = [];
        foreach ($assets as $asset) {
            if (preg_match('/^(\d+\.\d+(?:\.\d+)?)\.sql$/', $asset['name'], $m)) {
                $versiyon = $m[1];
                if (!in_array($versiyon, $uygulanmis) && version_compare($versiyon, SITE_VERSIYONU, '>')) {
                    $sql_assets[$versiyon] = $asset['browser_download_url'];
                }
            }
        }

        // Ayrıca ZIP içindeki migrations/ klasörüne de bak
        $zip_migrations_dir = $kaynak_dir . '/migrations';
        if (is_dir($zip_migrations_dir)) {
            foreach (glob($zip_migrations_dir . '/*.sql') as $sql_dosya) {
                $dosya_adi = basename($sql_dosya);
                if (preg_match('/^(\d+\.\d+(?:\.\d+)?)\.sql$/', $dosya_adi, $m)) {
                    $versiyon = $m[1];
                    if (!in_array($versiyon, $uygulanmis) && version_compare($versiyon, SITE_VERSIYONU, '>')) {
                        $sql_assets[$versiyon] = 'local:' . $sql_dosya;
                    }
                }
            }
        }

        uksort($sql_assets, 'version_compare');

        foreach ($sql_assets as $versiyon => $kaynak) {
            $log[] = "🗄️ Migration $versiyon uygulanıyor...";
            try {
                if (str_starts_with($kaynak, 'local:')) {
                    $sql = file_get_contents(substr($kaynak, 6));
                } else {
                    $ctx2 = stream_context_create(['http' => [
                        'method'  => 'GET',
                        'header'  => "User-Agent: ProjectOil-Updater/1.0\r\n",
                        'timeout' => 15,
                    ]]);
                    $sql = @file_get_contents($kaynak, false, $ctx2);
                }

                if (!$sql) throw new Exception("SQL dosyası okunamadı: $versiyon");

                // Yorum satırlarını temizle, çalıştır
                $satirlar = explode("\n", str_replace("\r\n", "\n", $sql));
                $temiz = implode("\n", array_filter(
                    array_map('trim', $satirlar),
                    fn($s) => $s !== '' && !str_starts_with($s, '--')
                ));
                $ifadeler = array_filter(array_map('trim', explode(';', $temiz)), fn($s) => $s !== '');
                foreach ($ifadeler as $ifade) {
                    $pdo->exec($ifade);
                }

                // Migration kaydını ekle
                $pdo->prepare("INSERT IGNORE INTO migrations (version) VALUES (?)")->execute([$versiyon]);
                $log[] = "✓ Migration $versiyon tamamlandı";
                $migration_yapildi++;
            } catch (Exception $me) {
                $log[] = "⚠️ Migration $versiyon hatası: " . $me->getMessage();
            }
        }

        if ($migration_yapildi === 0) $log[] = 'ℹ️ Uygulanacak SQL migrasyonu yok';

        // ── 5. version.php güncelle ──
        file_put_contents(ROOT_DIR . '/version.php', "<?php\n/*\n * Project Oil - Vehicle and Facility Industrial Oil Tracking System\n * Copyright (C) 2026 Awosk\n *\n * This program is free software: you can redistribute it and/or modify\n * it under the terms of the GNU General Public License as published by\n * the Free Software Foundation, either version 3 of the License, or\n * (at your option) any later version.\n */\n\ndefine('SITE_VERSIYONU', '$uzak_versiyon');\n");
        $log[] = "✓ Versiyon $uzak_versiyon olarak güncellendi";

        // ── 6. Temp temizle ──
        klasorSil($tmp_dir);

        // ── 7. Log yaz ──
        logYaz($pdo, 'guncelle', 'sistem',
            "Sistem güncellendi: v" . SITE_VERSIYONU . " → v$uzak_versiyon",
            null, ['versiyon' => SITE_VERSIYONU], ['versiyon' => $uzak_versiyon], 'lite');

        echo json_encode(['ok' => true, 'log' => $log, 'yeni_versiyon' => $uzak_versiyon]);

    } catch (Exception $e) {
        if (file_exists($tmp_zip)) @unlink($tmp_zip);
        if (is_dir($tmp_dir))     klasorSil($tmp_dir);
        $log[] = '❌ Hata: ' . $e->getMessage();
        echo json_encode(['ok' => false, 'log' => $log, 'mesaj' => $e->getMessage()]);
    }
    exit;
}

// ════════════════════════════════════════════════
// SAYFA
// ════════════════════════════════════════════════
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1><span>🔄</span> Sistem Güncelleme</h1>
</div>

<!-- Mevcut durum -->
<div class="stat-grid" style="grid-template-columns: repeat(auto-fill, minmax(180px,1fr));">
    <div class="stat-card">
        <div class="stat-label">Mevcut Sürüm</div>
        <div class="stat-value" style="font-size:26px;">v<?= SITE_VERSIYONU ?></div>
        <div class="stat-sub">Kurulu sürüm</div>
    </div>
    <div class="stat-card" id="stat_uzak">
        <div class="stat-label">Son Sürüm</div>
        <div class="stat-value" style="font-size:26px;" id="uzak_versiyon_stat">—</div>
        <div class="stat-sub" id="uzak_tarih_stat">Kontrol edilmedi</div>
    </div>
    <div class="stat-card" id="stat_durum">
        <div class="stat-label">Durum</div>
        <div class="stat-value" style="font-size:22px;" id="durum_stat">⏳</div>
        <div class="stat-sub" id="durum_sub_stat">Bekleniyor</div>
    </div>
</div>

<!-- Kontrol & Güncelleme -->
<div class="card" id="kontrol_kart">
    <div class="card-title">🔍 Güncelleme Kontrolü</div>
    <p style="color:var(--muted);font-size:13px;margin-bottom:18px;">
        GitHub üzerinden en son sürümü kontrol eder. İnternet bağlantısı gereklidir.
    </p>
    <button class="btn btn-primary" id="btn_kontrol" onclick="surumKontrol()">
        🔍 Sürüm Kontrolü Yap
    </button>
</div>

<!-- Güncelleme detay kartı (başta gizli) -->
<div class="card" id="guncelleme_kart" style="display:none;">
    <div class="card-title" id="guncelleme_kart_baslik">📦 Güncelleme Mevcut</div>

    <!-- Release notları -->
    <div id="release_notlari" style="display:none;margin-bottom:20px;">
        <div style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">📋 Sürüm Notları</div>
        <div id="release_body" style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:14px;font-size:13px;color:var(--text);line-height:1.7;white-space:pre-wrap;max-height:200px;overflow-y:auto;"></div>
    </div>

    <!-- Migration bilgisi -->
    <div id="migration_bilgi" style="display:none;margin-bottom:20px;">
        <div style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">🗄️ Veritabanı Güncellemeleri</div>
        <div id="migration_listesi" style="display:flex;flex-wrap:wrap;gap:6px;"></div>
    </div>

    <!-- Uyarı -->
    <div style="background:var(--warning-l);border:1.5px solid var(--warning);border-radius:var(--r-sm);padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:20px;">🛡️</span>
            <div>
                <div style="font-weight:700;color:var(--warning-text);font-size:13px;">Güncelleme öncesinde yedek almanız önerilir</div>
                <div style="font-size:12px;color:var(--warning-text);opacity:.85;margin-top:2px;">Bir sorun oluşursa verilerinizi geri yükleyebilirsiniz.</div>
            </div>
        </div>
        <a href="backup.php" target="_blank" class="btn btn-sm" style="background:var(--warning);color:#fff;white-space:nowrap;">💾 Yedek Al →</a>
    </div>

    <button class="btn btn-primary" id="btn_guncelle" onclick="guncellemeBaslat()">
        ⚙️ Güncellemeyi Başlat
    </button>
</div>

<!-- Güncel kart -->
<div class="card" id="guncel_kart" style="display:none;">
    <div style="text-align:center;padding:20px 0;">
        <div style="font-size:48px;margin-bottom:12px;">✅</div>
        <div style="font-weight:700;font-size:16px;color:var(--text);">Sistem Güncel</div>
        <div style="color:var(--muted);font-size:13px;margin-top:6px;">En son sürümü kullanıyorsunuz.</div>
        <div style="margin-top:16px;">
            <button id="btn_yeniden_yukle" class="btn btn-secondary" style="display:none;" onclick="yenidenYukle()">
                🔄 Mevcut Sürümü Yeniden Yükle
            </button>
        </div>
    </div>
</div>

<!-- İlerleme & Log -->
<div class="card" id="log_kart" style="display:none;">
    <div class="card-title" id="log_baslik">⚙️ Güncelleme Yapılıyor...</div>
    <div id="log_listesi" style="font-family:'Courier New',monospace;font-size:12px;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:14px;line-height:2;max-height:300px;overflow-y:auto;"></div>
    <div id="log_sonuc" style="display:none;margin-top:16px;"></div>
</div>

<script>
function yenidenYukle() {
    if (!confirm('v' + _uzak_versiyon + ' sürümü yeniden yüklenecek.\n\nMevcut dosyalar üzerine yazılacak, veriler korunacak.\n\nDevam edilsin mi?')) return;
    guncellemeBaslat();
}
var _zipball_url    = '';
var _uzak_versiyon  = '';
var _assets         = [];

async function surumKontrol() {
    var btn = document.getElementById('btn_kontrol');
    btn.disabled = true;
    btn.textContent = '⏳ Kontrol ediliyor...';

    try {
        var r = await fetch('?kontrol=1');
        var d = await r.json();

        btn.disabled = false;
        btn.textContent = '🔍 Sürüm Kontrolü Yap';

        if (!d.ok) {
            alert('Hata: ' + d.mesaj);
            return;
        }

        // Stat kartları güncelle
        document.getElementById('uzak_versiyon_stat').textContent = 'v' + d.uzak;
        var tarih = d.yayin_tarihi ? new Date(d.yayin_tarihi).toLocaleDateString('tr-TR') : '';
        document.getElementById('uzak_tarih_stat').textContent = tarih ? tarih + ' yayınlandı' : 'GitHub';

        _zipball_url   = d.zipball_url;
        _uzak_versiyon = d.uzak;
        _assets        = d.assets || [];

        if (d.yeni_var) {
            // Durum kartı
            document.getElementById('durum_stat').textContent    = '🆕';
            document.getElementById('durum_sub_stat').textContent = 'Güncelleme var';
            document.getElementById('stat_uzak').style.borderLeftColor = 'var(--success)';
            document.getElementById('stat_durum').style.borderLeftColor = 'var(--warning)';

            // Güncelleme kartı
            document.getElementById('guncelleme_kart_baslik').textContent = '📦 v' + d.uzak + ' Mevcut';
            document.getElementById('guncelleme_kart').style.display = '';
            document.getElementById('guncel_kart').style.display = 'none';

            // Release notları
            if (d.aciklama && d.aciklama.trim()) {
                document.getElementById('release_body').textContent = d.aciklama;
                document.getElementById('release_notlari').style.display = '';
            }

            // Migration listesi
            if (d.bekleyen_sql && d.bekleyen_sql.length > 0) {
                var ml = document.getElementById('migration_listesi');
                ml.innerHTML = d.bekleyen_sql.map(v =>
                    '<span style="background:var(--warning-l);color:var(--warning);padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;">🗄️ ' + v + '.sql</span>'
                ).join('');
                document.getElementById('migration_bilgi').style.display = '';
            }
        } else {
            // Güncel
            document.getElementById('durum_stat').textContent     = '✅';
            document.getElementById('durum_sub_stat').textContent  = 'Güncel';
            document.getElementById('stat_durum').style.borderLeftColor = 'var(--success)';
            document.getElementById('guncelleme_kart').style.display = 'none';
            document.getElementById('guncel_kart').style.display = '';
            // Yeniden yükleme butonunu hazırla
            document.getElementById('btn_yeniden_yukle').style.display = '';
        }

    } catch(e) {
        btn.disabled = false;
        btn.textContent = '🔍 Sürüm Kontrolü Yap';
        alert('Bağlantı hatası: ' + e.message);
    }
}

async function guncellemeBaslat() {
    if (!confirm('Güncelleme başlatılsın mı?\n\nÖncesinde yedek aldığınızdan emin olun.')) return;

    var btn = document.getElementById('btn_guncelle');
    btn.disabled = true;
    btn.textContent = '⏳ Güncelleniyor...';

    document.getElementById('log_kart').style.display = '';
    document.getElementById('log_baslik').textContent = '⚙️ Güncelleme Yapılıyor...';
    document.getElementById('log_listesi').innerHTML = '';
    document.getElementById('log_sonuc').style.display = 'none';

    logEkle('🚀 Güncelleme başlatıldı...');

    try {
        var form = new FormData();
        form.append('zipball_url',    _zipball_url);
        form.append('uzak_versiyon',  _uzak_versiyon);
        form.append('assets',         JSON.stringify(_assets));

        var r = await fetch('?guncelle=1', { method: 'POST', body: form });
        var d = await r.json();

        // Logları göster
        (d.log || []).forEach(logEkle);

        var sonucEl = document.getElementById('log_sonuc');
        sonucEl.style.display = '';

        if (d.ok) {
            document.getElementById('log_baslik').textContent = '✅ Güncelleme Tamamlandı';
            sonucEl.innerHTML = '<div class="alert alert-success">✅ Sistem <strong>v' + d.yeni_versiyon + '</strong> sürümüne güncellendi. Sayfa yenileniyor...</div>';
            setTimeout(() => location.reload(), 2500);
        } else {
            document.getElementById('log_baslik').textContent = '❌ Güncelleme Başarısız';
            sonucEl.innerHTML = '<div class="alert alert-danger">❌ ' + (d.mesaj || 'Bilinmeyen hata') + '</div>';
            btn.disabled = false;
            btn.textContent = '⚙️ Tekrar Dene';
        }

    } catch(e) {
        logEkle('❌ İstek hatası: ' + e.message);
        btn.disabled = false;
        btn.textContent = '⚙️ Tekrar Dene';
    }
}

function logEkle(mesaj) {
    var el = document.getElementById('log_listesi');
    var satir = document.createElement('div');
    satir.textContent = mesaj;
    if (mesaj.startsWith('❌')) satir.style.color = 'var(--danger)';
    else if (mesaj.startsWith('✓') || mesaj.startsWith('✅')) satir.style.color = 'var(--success)';
    else if (mesaj.startsWith('⚠️')) satir.style.color = 'var(--warning)';
    else satir.style.color = 'var(--muted)';
    el.appendChild(satir);
    el.scrollTop = el.scrollHeight;
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>