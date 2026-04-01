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
require_once __DIR__ . '/../../classes/SistemGuncelleme.php';
adminKontrol();

$sayfa_basligi = 'Sistem Güncelleme';

// Migration tablosu yoksa oluştur
SistemGuncelleme::hazirla($pdo);

// ════════════════════════════════════════════════
// AJAX: Sürüm kontrolü
// ════════════════════════════════════════════════
if (isset($_GET['kontrol'])) {
    header('Content-Type: application/json');
    echo json_encode(SistemGuncelleme::surumKontrol($pdo));
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

    $root_dir = dirname(__DIR__, 2);
    $sonuc = SistemGuncelleme::uygula($pdo, $zipball_url, $uzak_versiyon, $assets, $root_dir);
    echo json_encode($sonuc);
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