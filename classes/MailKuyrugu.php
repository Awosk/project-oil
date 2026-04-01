<?php
/*
 * Project Oil - Vehicle and Facility Industrial Oil Tracking System
 * Copyright (C) 2026 Awosk
 */

class MailKuyrugu {
    public static function tekYenidenDene($pdo, $id) {
        return $pdo->prepare("UPDATE mail_queue SET status='pending', attempt_count=0, hata_mesaji=NULL WHERE id=? AND status IN ('failed','cancelled')")
            ->execute([$id]);
    }

    public static function tekSil($pdo, $id) {
        return $pdo->prepare("DELETE FROM mail_queue WHERE id=?")->execute([$id]);
    }

    public static function temizleHepsi($pdo) {
        return $pdo->exec("DELETE FROM mail_queue WHERE status IN ('sent','failed','cancelled')");
    }

    public static function retryHepsi($pdo) {
        return $pdo->exec("UPDATE mail_queue SET status='pending', attempt_count=0, hata_mesaji=NULL WHERE status IN ('failed','cancelled')");
    }

    public static function iptalPending($pdo) {
        return $pdo->exec("UPDATE mail_queue SET status='cancelled' WHERE status='pending'");
    }

    public static function tumTemizle($pdo) {
        return $pdo->exec("DELETE FROM mail_queue WHERE status NOT IN ('processing')");
    }

    public static function istatistik($pdo) {
        return $pdo->query("
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
    }

    public static function sayfaSayisiGetir($pdo, $where_sql, $params) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM mail_queue WHERE $where_sql");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public static function listeSayfalamali($pdo, $where_sql, $params, $limit, $offset) {
        $stmt = $pdo->prepare("SELECT * FROM mail_queue WHERE $where_sql ORDER BY created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
