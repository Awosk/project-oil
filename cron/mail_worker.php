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

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Bu script sadece komut satırından çalıştırılabilir.');
}

define('WORKER_START', microtime(true));
define('BATCH_SIZE', 10);
define('MAX_SURE', 50);
define('MAX_ATTEMPTS', 3); // Max deneme sayısı

define('ROOT_DIR', dirname(__DIR__));

require_once ROOT_DIR . '/config/database.php';
require_once ROOT_DIR . '/includes/mail.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ─────────────────────────────────────────────
// COOLDOWN KONTROLÜ
// ─────────────────────────────────────────────
$cooldown_bitis = sistemAyarGetir($pdo, 'mail_cooldown_bitis');

if ($cooldown_bitis && strtotime($cooldown_bitis) <= time()) {
    $iptal = $pdo->exec("UPDATE mail_queue SET status = 'cancelled' WHERE status = 'paused'");
    if ($iptal > 0) {
        _workerLog("Cooldown bitti. $iptal adet paused mail iptal edildi.");
    }
    $pdo->prepare("UPDATE system_settings SET `value` = '' WHERE `key` = 'mail_cooldown_bitis'")->execute();
}

$cooldown_aktif = cooldownAktifMi($pdo);

// ─────────────────────────────────────────────
// MAİLLERİ ÇEK VE REZERV ET (Race Condition Önlemi)
// ─────────────────────────────────────────────
try {
    $pdo->beginTransaction();

    // Sorgu mantığı: 
    // - Önce 'force' olanları al (limit/cooldown dinlemez)
    // - Sonra 'pending' olan ve deneme sayısı aşılmamış olanları al (cooldown aktif değilse)
    
    $subQuery = "status = 'force'";
    if (!$cooldown_aktif) {
        $subQuery .= " OR (status = 'pending' AND attempt_count < " . MAX_ATTEMPTS . ")";
    }

    // Seçilen satırları kilitliyoruz (FOR UPDATE)
    $stmt = $pdo->prepare("
        SELECT id FROM mail_queue 
        WHERE ($subQuery) 
        ORDER BY (CASE WHEN status = 'force' THEN 0 ELSE 1 END), created_at ASC 
        LIMIT " . BATCH_SIZE . " FOR UPDATE
    ");
    $stmt->execute();
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($ids)) {
        $pdo->commit();
        _workerLog("Gönderilecek mail yok.");
        exit(0);
    }

    // Seçilen mailleri 'processing' durumuna çekiyoruz ki başka worker çekemesin
    $placeholders = rtrim(str_repeat('?,', count($ids)), ',');
    $pdo->prepare("UPDATE mail_queue SET status = 'processing' WHERE id IN ($placeholders)")->execute($ids);

    $pdo->commit();

    // İşlenecek maillerin detayını alalım
    $stmtDetail = $pdo->prepare("SELECT * FROM mail_queue WHERE id IN ($placeholders)");
    $stmtDetail->execute($ids);
    $mailler = $stmtDetail->fetchAll();

} catch (Exception $e) {
    $pdo->rollBack();
    _workerLog("Veritabanı kilitleme hatası: " . $e->getMessage());
    exit(1);
}

_workerLog(count($mailler) . " adet mail işlem sırasına alındı.");

$gonderilen = 0;
$basarisiz  = 0;

foreach ($mailler as $mail) {
    if ((microtime(true) - WORKER_START) > MAX_SURE) {
        // Süre biterse kalanları tekrar 'pending' yapıp çıkıyoruz
        $pdo->prepare("UPDATE mail_queue SET status = 'pending' WHERE id = ? AND attempt_count < ?")
            ->execute([$mail['id'], MAX_ATTEMPTS]);
        _workerLog("Süre limiti aşıldı, durduruluyor.");
        continue;
    }

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
        
        // Deneme sayısını arttır
        $newAttempt = (int)$mail['attempt_count'] + 1;
        
        // Maksimum denemeyi aştıysa failed, aşmadıysa tekrar pending (retry)
        $newStatus = ($newAttempt >= MAX_ATTEMPTS) ? 'failed' : 'pending';

        $pdo->prepare("
            UPDATE mail_queue
            SET status = ?, attempt_count = ?, error_message = ?
            WHERE id = ?
        ")->execute([$newStatus, $newAttempt, $hata, $mail['id']]);

        _workerLog("✗ Başarısız → {$mail['to_email']} [Deneme: $newAttempt] | Hata: $hata");
        $basarisiz++;
    }
}

$sure = round(microtime(true) - WORKER_START, 2);
_workerLog("Tamamlandı. Gönderilen: $gonderilen | Başarısız: $basarisiz | Süre: {$sure}sn");

function _workerLog(string $mesaj): void {
    $zaman = date('Y-m-d H:i:s');
    echo "[$zaman] $mesaj" . PHP_EOL;
}

// Haftada 1 kez rastgele denk gelirse (Yüzde 5 ihtimalle) çalışır ve 30 günden eski 'sent' veya 'failed' mailleri siler.
if (rand(1, 100) <= 5) {
    $silinen = $pdo->exec("DELETE FROM mail_queue WHERE (status = 'sent' OR status = 'failed' OR status = 'cancelled') AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    if ($silinen > 0) {
        _workerLog("♻️ Çöp Toplayıcı: 30 günden eski $silinen adet geçmiş mail silindi.");
    }
}