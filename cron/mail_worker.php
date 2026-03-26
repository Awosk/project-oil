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
 * Mail Worker — Kuyruktan mail gönderir.
 *
 * Linux cron (dakikada bir):
 *   * * * * * php /var/www/html/project-oil/cron/mail_worker.php >> /var/log/mail_worker.log 2>&1
 *
 * Windows Task Scheduler:
 *   Program: php.exe
 *   Argüman: C:\xampp\htdocs\project-oil\cron\mail_worker.php
 *   Sıklık: Her 1 dakika
 */

// ── Ortam kontrolü — sadece CLI'dan çalışsın ──
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Bu script sadece komut satırından çalıştırılabilir.');
}

define('WORKER_START', microtime(true));
define('BATCH_SIZE', 10);      // Tek seferde gönderilecek max mail
define('MAX_SURE', 50);        // Script max çalışma süresi (saniye) — cron 60sn'de bir çalışır

// ── Root dizini bul ──
define('ROOT_DIR', dirname(__DIR__));

// ── Config yükle ──
require_once ROOT_DIR . '/config/database.php';
require_once ROOT_DIR . '/includes/mail.php';

// ─────────────────────────────────────────────
// COOLDOWN KONTROLÜ
// ─────────────────────────────────────────────
$cooldown_bitis = sistemAyarGetir($pdo, 'mail_cooldown_bitis');

if ($cooldown_bitis && strtotime($cooldown_bitis) > time()) {
    $kalan = strtotime($cooldown_bitis) - time();
    _workerLog("Cooldown aktif. Bitiş: $cooldown_bitis (kalan: {$kalan}sn). Çıkılıyor.");
    exit(0);
}

// Cooldown bitti mi? paused mailleri iptal et
if ($cooldown_bitis && strtotime($cooldown_bitis) <= time()) {
    $iptal = $pdo->exec("UPDATE mail_queue SET status = 'cancelled' WHERE status = 'paused'");
    if ($iptal > 0) {
        _workerLog("Cooldown bitti. $iptal adet paused mail iptal edildi.");
    }
    // Cooldown bitişini temizle
    $pdo->prepare("UPDATE sistem_ayarlar SET deger = '' WHERE anahtar = 'mail_cooldown_bitis'")->execute();
}

// ─────────────────────────────────────────────
// PENDING MAİLLERİ GÖNDERi
// ─────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT * FROM mail_queue
    WHERE status = 'pending'
    ORDER BY created_at ASC
    LIMIT " . BATCH_SIZE
);
$stmt->execute();
$mailler = $stmt->fetchAll();

if (empty($mailler)) {
    _workerLog("Gönderilecek mail yok.");
    exit(0);
}

_workerLog(count($mailler) . " adet pending mail bulundu.");

$gonderilen = 0;
$basarisiz  = 0;

foreach ($mailler as $mail) {
    // Süre aşıldıysa dur
    if ((microtime(true) - WORKER_START) > MAX_SURE) {
        _workerLog("Süre limiti ({$MAX_SURE}sn) aşıldı, durduruluyor.");
        break;
    }

    // Cooldown bu iterasyonda başladıysa dur
    if (cooldownAktifMi($pdo)) {
        _workerLog("Cooldown başladı, gönderim durduruluyor.");
        break;
    }

    // Gönder
    $sonuc = mailGonderSMTP(
        $pdo,
        $mail['to_email'],
        $mail['to_name'],
        $mail['subject'],
        $mail['body']
    );

    if ($sonuc['ok']) {
        $pdo->prepare("
            UPDATE mail_queue
            SET status = 'sent', sent_at = NOW(), attempt_count = attempt_count + 1
            WHERE id = ?
        ")->execute([$mail['id']]);
        _workerLog("✓ Gönderildi → {$mail['to_email']} | Konu: {$mail['subject']}");
        $gonderilen++;
    } else {
        $hata = $sonuc['hata'];
        $pdo->prepare("
            UPDATE mail_queue
            SET status = 'failed', attempt_count = attempt_count + 1, hata_mesaji = ?
            WHERE id = ?
        ")->execute([$hata, $mail['id']]);
        _workerLog("✗ Başarısız → {$mail['to_email']} | Hata: $hata");
        $basarisiz++;
    }
}

$sure = round(microtime(true) - WORKER_START, 2);
_workerLog("Tamamlandı. Gönderilen: $gonderilen | Başarısız: $basarisiz | Süre: {$sure}sn");

// ─────────────────────────────────────────────
// LOG FONKSİYONU
// ─────────────────────────────────────────────
function _workerLog(string $mesaj): void {
    $zaman = date('Y-m-d H:i:s');
    echo "[$zaman] $mesaj" . PHP_EOL;
}