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

// =====================================================
// MAIL SERVİSİ — Asenkron kuyruk tabanlı
// =====================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';
require_once __DIR__ . '/phpmailer/Exception.php';

// ─────────────────────────────────────────────
// YARDIMCI FONKSİYONLAR
// ─────────────────────────────────────────────

function smtpAyarlariGetir($pdo): array {
    try {
        $rows = $pdo->query("SELECT anahtar, deger FROM sistem_ayarlar WHERE anahtar LIKE 'smtp_%'")->fetchAll();
        $ayarlar = [];
        foreach ($rows as $row) {
            $ayarlar[$row['anahtar']] = $row['deger'];
        }
        return $ayarlar;
    } catch (Exception $e) {
        return [];
    }
}

function sistemAyarGetir($pdo, string $anahtar, string $varsayilan = ''): string {
    try {
        $stmt = $pdo->prepare("SELECT deger FROM sistem_ayarlar WHERE anahtar = ?");
        $stmt->execute([$anahtar]);
        $deger = $stmt->fetchColumn();
        return $deger !== false ? (string)$deger : $varsayilan;
    } catch (Exception $e) {
        return $varsayilan;
    }
}

function smtpAktifMi($pdo): bool {
    return sistemAyarGetir($pdo, 'smtp_aktif') === '1';
}

function cooldownAktifMi($pdo): bool {
    $bitis = sistemAyarGetir($pdo, 'mail_cooldown_bitis');
    if (!$bitis) return false;
    return strtotime($bitis) > time();
}

// ─────────────────────────────────────────────
// KUYRUK SİSTEMİ
// ─────────────────────────────────────────────

/**
 * Maili kuyruğa ekler.
 * @param string $status 'pending' veya 'force' olabilir.
 */
function mailQueueEkle($pdo, string $to_email, string $to_name, string $subject, string $body, string $status = 'pending'): bool {
    if (!smtpAktifMi($pdo)) return false;
    if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) return false;

    // Eğer force değilse ve cooldown aktifse 'paused' yap
    if ($status !== 'force' && cooldownAktifMi($pdo)) {
        $status = 'paused';
    }

    try {
        $pdo->prepare("
            INSERT INTO mail_queue (to_email, to_name, subject, body, status)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$to_email, $to_name, $subject, $body, $status]);
        return true;
    } catch (Exception $e) {
        error_log('mailQueueEkle hatası: ' . $e->getMessage());
        return false;
    }
}

function mailGonderSMTP($pdo, string $to_email, string $to_name, string $subject, string $body): array {
    $ayarlar = smtpAyarlariGetir($pdo);

    if (empty($ayarlar['smtp_host']) || empty($ayarlar['smtp_kullanici'])) {
        return ['ok' => false, 'hata' => 'SMTP ayarları eksik'];
    }

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $ayarlar['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $ayarlar['smtp_kullanici'];
        $mail->Password   = $ayarlar['smtp_sifre'] ?? '';
        $mail->SMTPSecure = ($ayarlar['smtp_sifrelem'] ?? 'tls') === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)($ayarlar['smtp_port'] ?? 587);
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 5;

        $gonderen_ad    = $ayarlar['smtp_ad']   ?: (defined('SITE_ADI') ? SITE_ADI : 'Project Oil');
        $gonderen_email = $ayarlar['smtp_gonderen'] ?: $ayarlar['smtp_kullanici'];

        $mail->setFrom($gonderen_email, $gonderen_ad);
        $mail->addAddress($to_email, $to_name);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

        $mail->send();
        return ['ok' => true, 'hata' => ''];

    } catch (MailException $e) {
        return ['ok' => false, 'hata' => $e->getMessage()];
    } catch (Exception $e) {
        return ['ok' => false, 'hata' => $e->getMessage()];
    }
}

// ─────────────────────────────────────────────
// BİLDİRİM SİSTEMİ
// ─────────────────────────────────────────────

function adminBildirimGonder($pdo, string $aksiyon, string $modul, string $aciklama, ?array $kullanici_bilgi = null): void {
    if (!smtpAktifMi($pdo)) return;

    try {
        $stmt = $pdo->prepare("
            SELECT k.email, k.ad_soyad
            FROM admin_bildirim_filtreler f
            JOIN kullanicilar k ON f.kullanici_id = k.id
            WHERE f.aktif = 1
              AND f.modul = ?
              AND f.aksiyon = ?
              AND k.aktif = 1
              AND k.email IS NOT NULL
              AND k.email != ''
        ");
        $stmt->execute([$modul, $aksiyon]);
        $alicilar = $stmt->fetchAll();

        if (empty($alicilar)) return;

        $konu   = _bildirimKonuOlustur($aksiyon, $modul);
        $icerik = _bildirimIcerikOlustur($aksiyon, $modul, $aciklama, $kullanici_bilgi);

        if (cooldownAktifMi($pdo)) {
            foreach ($alicilar as $alici) {
                mailQueueEkle($pdo, $alici['email'], $alici['ad_soyad'], $konu, $icerik, 'paused');
            }
            return;
        }

        $limit_adet   = (int)sistemAyarGetir($pdo, 'mail_rate_limit_adet', '10');
        $limit_dakika = (int)sistemAyarGetir($pdo, 'mail_rate_limit_dakika', '5');
        $cooldown_dk  = (int)sistemAyarGetir($pdo, 'mail_cooldown_dakika', '15');

        $pencere_bas = date('Y-m-d H:i:s', time() - ($limit_dakika * 60));
        $stmt2 = $pdo->prepare("
            SELECT COUNT(*) FROM mail_queue
            WHERE created_at >= ?
            AND status IN ('pending', 'sent', 'failed', 'force')
        ");
        $stmt2->execute([$pencere_bas]);
        $son_mail_sayisi = (int)$stmt2->fetchColumn();

        if ($son_mail_sayisi >= $limit_adet) {
            $cooldown_bitis = date('Y-m-d H:i:s', time() + ($cooldown_dk * 60));
            $pdo->prepare("
                INSERT INTO sistem_ayarlar (anahtar, deger) VALUES ('mail_cooldown_bitis', ?)
                ON DUPLICATE KEY UPDATE deger = VALUES(deger)
            ")->execute([$cooldown_bitis]);

            $pdo->exec("UPDATE mail_queue SET status = 'paused' WHERE status = 'pending'");

            // UYARI MAİLİNİ 'force' OLARAK QUEUE'YE EKLİYORUZ
            $site_adi     = defined('SITE_ADI') ? SITE_ADI : 'Project Oil';
            $uyari_konu   = $site_adi . ' — ⚠️ Mail Rate Limit Aşıldı';
            $uyari_icerik = mailSablonu($uyari_konu, '
                <p>Son <strong>' . $limit_dakika . ' dakika</strong> içinde <strong>' . $son_mail_sayisi . '</strong> mail gönderildi.</p>
                <p>Rate limit aşıldığı için mail gönderimi duraklatıldı.</p>
                <p>Cooldown bitiş: <strong>' . date('d.m.Y H:i:s', strtotime($cooldown_bitis)) . '</strong></p>
            ');

            foreach ($alicilar as $alici) {
                mailQueueEkle($pdo, $alici['email'], $alici['ad_soyad'], $uyari_konu, $uyari_icerik, 'force');
            }
            return;
        }

        foreach ($alicilar as $alici) {
            mailQueueEkle($pdo, $alici['email'], $alici['ad_soyad'], $konu, $icerik);
        }

    } catch (Exception $e) {
        error_log('adminBildirimGonder hatası: ' . $e->getMessage());
    }
}

// ─────────────────────────────────────────────
// KRİTİK MAİLLER (direkt SMTP)
// ─────────────────────────────────────────────

/**
 * Şifre sıfırlama maili — kuyruğa girmez, direkt gönderilir.
 */
function sifreSifirlamaMailiGonder($pdo, array $kullanici, string $token): bool {
    $site_adi = defined('SITE_ADI') ? SITE_ADI : 'Project Oil';
    $link     = ROOT_URL . 'sifre_sifirlama.php?token=' . $token;
    $icerik   = mailSablonu('🔑 Şifre Sıfırlama', '
        <p>Merhaba <strong>' . htmlspecialchars($kullanici['ad_soyad']) . '</strong>,</p>
        <p>' . $site_adi . ' hesabınız için şifre sıfırlama talebinde bulunuldu.</p>
        <p style="margin:24px 0;">
            <a href="' . $link . '" style="background:#1e4d6b;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:700;display:inline-block;">
                🔑 Şifremi Sıfırla
            </a>
        </p>
        <p style="color:#666;font-size:13px;">Bu link <strong>30 dakika</strong> geçerlidir. Eğer bu talebi siz yapmadıysanız bu maili görmezden gelebilirsiniz.</p>
        <p style="color:#666;font-size:12px;word-break:break-all;">Link çalışmıyorsa kopyalayıp tarayıcınıza yapıştırın:<br>' . $link . '</p>
    ');

    $sonuc = mailGonderSMTP($pdo, $kullanici['email'], $kullanici['ad_soyad'],
        '🔑 Şifre Sıfırlama — ' . $site_adi, $icerik
    );
    return $sonuc['ok'];
}

/**
 * Test maili — direkt SMTP.
 */
function testMailiGonder($pdo, string $email): array {
    $icerik = mailSablonu('🧪 SMTP Test', '
        <p>Bu bir test mailidir. SMTP ayarlarınız doğru çalışıyor!</p>
        <p style="color:#666;font-size:13px;">Gönderim zamanı: ' . date('d.m.Y H:i:s') . '</p>
    ');
    return mailGonderSMTP($pdo, $email, 'Test',
        '🧪 SMTP Test — ' . (defined('SITE_ADI') ? SITE_ADI : 'Project Oil'),
        $icerik
    );
}

// ─────────────────────────────────────────────
// ÖZEL YARDIMCI FONKSİYONLAR
// ─────────────────────────────────────────────

function _bildirimKonuOlustur(string $aksiyon, string $modul): string {
    $aksiyon_etiket = [
        'ekle'     => '➕ Ekleme',
        'sil'      => '🗑️ Silme',
        'guncelle' => '✏️ Güncelleme',
        'giris'    => '🔐 Giriş',
        'cikis'    => '🚪 Çıkış',
    ][$aksiyon] ?? $aksiyon;

    $modul_etiket = [
        'arac'        => '🚗 Araç',
        'arac_tur'    => '🚗 Araç Türü',
        'tesis'       => '🏭 Tesis',
        'arac_kayit'  => '🛢️ Araç Yağ Kaydı',
        'tesis_kayit' => '🛢️ Tesis Yağ Kaydı',
        'urun'        => '📦 Ürün',
        'kullanici'   => '👤 Kullanıcı',
        'auth'        => '🔐 Oturum',
        'sistem'      => '⚙️ Sistem',
        'islendi'     => '✅ Depoya İşlendi',
    ][$modul] ?? $modul;

    return '[' . (defined('SITE_ADI') ? SITE_ADI : 'Project Oil') . '] ' . $aksiyon_etiket . ' — ' . $modul_etiket;
}

function _bildirimIcerikOlustur(string $aksiyon, string $modul, string $aciklama, ?array $kullanici_bilgi): string {
    $aksiyon_etiket = [
        'ekle'     => '➕ Ekleme',
        'sil'      => '🗑️ Silme',
        'guncelle' => '✏️ Güncelleme',
        'giris'    => '🔐 Giriş',
        'cikis'    => '🚪 Çıkış',
    ][$aksiyon] ?? $aksiyon;

    $modul_etiket = [
        'arac'        => '🚗 Araç',
        'arac_tur'    => '🚗 Araç Türü',
        'tesis'       => '🏭 Tesis',
        'arac_kayit'  => '🛢️ Araç Yağ Kaydı',
        'tesis_kayit' => '🛢️ Tesis Yağ Kaydı',
        'urun'        => '📦 Ürün',
        'kullanici'   => '👤 Kullanıcı',
        'auth'        => '🔐 Oturum',
        'sistem'      => '⚙️ Sistem',
        'islendi'     => '✅ Depoya İşlendi',
    ][$modul] ?? $modul;

    $yapan = $kullanici_bilgi
        ? htmlspecialchars($kullanici_bilgi['ad_soyad'] ?? $kullanici_bilgi['adi'] ?? 'Bilinmiyor')
        : 'Sistem';

    $tablo = '
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
            <tr>
                <td style="padding:8px 12px;background:#f8fafc;border:1px solid #e2e8f0;font-weight:700;width:140px;">İşlem</td>
                <td style="padding:8px 12px;border:1px solid #e2e8f0;">' . $aksiyon_etiket . '</td>
            </tr>
            <tr>
                <td style="padding:8px 12px;background:#f8fafc;border:1px solid #e2e8f0;font-weight:700;">Modül</td>
                <td style="padding:8px 12px;border:1px solid #e2e8f0;">' . $modul_etiket . '</td>
            </tr>
            <tr>
                <td style="padding:8px 12px;background:#f8fafc;border:1px solid #e2e8f0;font-weight:700;">Açıklama</td>
                <td style="padding:8px 12px;border:1px solid #e2e8f0;">' . htmlspecialchars($aciklama) . '</td>
            </tr>
            <tr>
                <td style="padding:8px 12px;background:#f8fafc;border:1px solid #e2e8f0;font-weight:700;">Yapan</td>
                <td style="padding:8px 12px;border:1px solid #e2e8f0;">' . $yapan . '</td>
            </tr>
            <tr>
                <td style="padding:8px 12px;background:#f8fafc;border:1px solid #e2e8f0;font-weight:700;">Tarih</td>
                <td style="padding:8px 12px;border:1px solid #e2e8f0;">' . date('d.m.Y H:i:s') . '</td>
            </tr>
        </table>
    ';

    return mailSablonu(_bildirimKonuOlustur($aksiyon, $modul), $tablo);
}

// ─────────────────────────────────────────────
// MAIL HTML ŞABLONU
// ─────────────────────────────────────────────

function mailSablonu(string $baslik, string $icerik): string {
    $site_adi = defined('SITE_ADI') ? SITE_ADI : 'Project Oil';
    return '<!DOCTYPE html>
<html lang="tr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f4f8;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:32px 16px;">
    <tr><td align="center">
        <table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;">
            <tr>
                <td style="background:linear-gradient(135deg,#1e4d6b,#2980b9);border-radius:12px 12px 0 0;padding:24px 32px;text-align:center;">
                    <div style="font-size:28px;margin-bottom:8px;">🔩</div>
                    <div style="color:#fff;font-size:18px;font-weight:700;">' . $site_adi . '</div>
                </td>
            </tr>
            <tr>
                <td style="background:#fff;padding:32px;border-left:1px solid #e2e8f0;border-right:1px solid #e2e8f0;">
                    <h2 style="margin:0 0 20px;color:#1e293b;font-size:18px;">' . htmlspecialchars($baslik) . '</h2>
                    ' . $icerik . '
                </td>
            </tr>
            <tr>
                <td style="background:#f8fafc;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 12px 12px;padding:16px 32px;text-align:center;">
                    <p style="margin:0;color:#94a3b8;font-size:12px;">' . $site_adi . ' &copy; ' . date('Y') . ' — Bu mail otomatik olarak gönderilmiştir.</p>
                </td>
            </tr>
        </table>
    </td></tr>
</table>
</body>
</html>';
}