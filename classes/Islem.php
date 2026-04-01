<?php
/*
 * Project Oil - Vehicle and Facility Industrial Oil Tracking System
 * Copyright (C) 2026 Awosk
 */

class Islem {
    public static function aracYagEkle($pdo, $arac_id, $urun_id, $miktar, $tarih, $aciklama, $yag_bakimi, $mevcut_km, $olusturan_id) {
        $stmt = $pdo->prepare("INSERT INTO records (kayit_turu,arac_id,urun_id,miktar,tarih,aciklama,yag_bakimi,mevcut_km,olusturan_id) VALUES ('arac',?,?,?,?,?,?,?,?)");
        $stmt->execute([$arac_id, $urun_id, $miktar, $tarih, $aciklama ?: null, $yag_bakimi, $mevcut_km, $olusturan_id]);
        return $pdo->lastInsertId();
    }

    public static function aracKayitBul($pdo, $kayit_id, $arac_id) {
        $stmt = $pdo->prepare('SELECT lk.*,u.urun_kodu,u.urun_adi FROM records lk JOIN products u ON lk.urun_id=u.id WHERE lk.id=? AND lk.arac_id=? AND lk.aktif=1');
        $stmt->execute([$kayit_id, $arac_id]);
        return $stmt->fetch();
    }
    
    public static function aciklamaGuncelle($pdo, $kayit_id, $aciklama) {
        return $pdo->prepare("UPDATE records SET aciklama=? WHERE id=?")->execute([$aciklama ?: null, $kayit_id]);
    }

    public static function kayitGuncelle($pdo, $kayit_id, $urun_id, $miktar, $tarih, $aciklama, $yag_bakimi = 0, $mevcut_km = null) {
        return $pdo->prepare("UPDATE records SET urun_id=?, miktar=?, tarih=?, aciklama=?, yag_bakimi=?, mevcut_km=? WHERE id=?")
            ->execute([$urun_id, $miktar, $tarih, $aciklama ?: null, $yag_bakimi, $mevcut_km, $kayit_id]);
    }
    
    public static function aracKayitSilBul($pdo, $kayit_id, $arac_id) {
        $stmt = $pdo->prepare('SELECT lk.*,u.urun_kodu,u.urun_adi FROM records lk JOIN products u ON lk.urun_id=u.id WHERE lk.id=? AND lk.arac_id=?');
        $stmt->execute([$kayit_id, $arac_id]);
        return $stmt->fetch();
    }

    public static function kayitSil($pdo, $kayit_id, $arac_id) {
        return $pdo->prepare("UPDATE records SET aktif=0 WHERE id=? AND arac_id=?")->execute([$kayit_id, $arac_id]);
    }

    public static function aracKayitlari($pdo, $arac_id) {
        $stmt = $pdo->prepare("
            SELECT lk.*,u.urun_adi,u.urun_kodu,k.ad_soyad
            FROM records lk
            JOIN products u ON lk.urun_id=u.id
            LEFT JOIN users k ON lk.olusturan_id=k.id
            WHERE lk.arac_id=? AND lk.aktif=1
            ORDER BY lk.tarih DESC, lk.olusturma_tarihi DESC
        ");
        $stmt->execute([$arac_id]);
        return $stmt->fetchAll();
    }

    public static function tesisYagEkle($pdo, $tesis_id, $urun_id, $miktar, $tarih, $aciklama, $olusturan_id) {
        $stmt = $pdo->prepare("INSERT INTO records (kayit_turu, tesis_id, urun_id, miktar, tarih, aciklama, olusturan_id) VALUES ('tesis', ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$tesis_id, $urun_id, $miktar, $tarih, $aciklama ?: null, $olusturan_id]);
        return $pdo->lastInsertId();
    }

    public static function tesisKayitBul($pdo, $kayit_id, $tesis_id) {
        $stmt = $pdo->prepare('SELECT lk.*,u.urun_kodu,u.urun_adi FROM records lk JOIN products u ON lk.urun_id=u.id WHERE lk.id=? AND lk.tesis_id=? AND lk.aktif=1');
        $stmt->execute([$kayit_id, $tesis_id]);
        return $stmt->fetch();
    }
    
    public static function tesisKayitSilBul($pdo, $kayit_id, $tesis_id) {
        $stmt = $pdo->prepare('SELECT lk.*,u.urun_kodu,u.urun_adi FROM records lk JOIN products u ON lk.urun_id=u.id WHERE lk.id=? AND lk.tesis_id=?');
        $stmt->execute([$kayit_id, $tesis_id]);
        return $stmt->fetch();
    }

    public static function tesisKayitSil($pdo, $kayit_id, $tesis_id) {
        return $pdo->prepare("UPDATE records SET aktif=0 WHERE id=? AND tesis_id=?")->execute([$kayit_id, $tesis_id]);
    }

    public static function tesisKayitlari($pdo, $tesis_id) {
        $stmt = $pdo->prepare("
            SELECT lk.*, u.urun_adi, u.urun_kodu, k.ad_soyad
            FROM records lk
            JOIN products u ON lk.urun_id = u.id
            LEFT JOIN users k ON lk.olusturan_id = k.id
            WHERE lk.tesis_id = ? AND lk.aktif = 1
            ORDER BY lk.tarih DESC, lk.olusturma_tarihi DESC
        ");
        $stmt->execute([$tesis_id]);
        return $stmt->fetchAll();
    }

    public static function islendiMi($pdo, $id) {
        $stmt = $pdo->prepare("SELECT islendi FROM records WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn();
    }

    public static function genelKayitBul($pdo, $id) {
        $stmt = $pdo->prepare('
            SELECT lk.*, u.urun_kodu, u.urun_adi, a.plaka, t.firma_adi
            FROM records lk
            JOIN products u ON lk.urun_id=u.id
            LEFT JOIN vehicles a ON lk.arac_id=a.id
            LEFT JOIN facilities t ON lk.tesis_id=t.id
            WHERE lk.id=?
        ');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function aktifGenelKayitBul($pdo, $id) {
        $stmt = $pdo->prepare('
            SELECT lk.*, u.urun_kodu, u.urun_adi, a.plaka, t.firma_adi
            FROM records lk
            JOIN products u ON lk.urun_id=u.id
            LEFT JOIN vehicles a ON lk.arac_id=a.id
            LEFT JOIN facilities t ON lk.tesis_id=t.id
            WHERE lk.id=? AND lk.aktif=1
        ');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function islendiYap($pdo, $id, $ku_id) {
        return $pdo->prepare("UPDATE records SET islendi=1, islendi_tarih=NOW(), islendi_kullanici_id=? WHERE id=?")
            ->execute([$ku_id, $id]);
    }

    public static function islendiIptal($pdo, $id) {
        return $pdo->prepare("UPDATE records SET islendi=0, islendi_tarih=NULL, islendi_kullanici_id=NULL WHERE id=?")
            ->execute([$id]);
    }

    public static function aramaSartlariniOlustur($filtreler) {
        $where = ["lk.aktif = 1"];
        $params = [];

        if (!empty($filtreler['tarih_bas'])) { $where[] = "lk.tarih >= ?"; $params[] = $filtreler['tarih_bas']; }
        if (!empty($filtreler['tarih_bit'])) { $where[] = "lk.tarih <= ?"; $params[] = $filtreler['tarih_bit']; }
        if (!empty($filtreler['arac_id'])) { $where[] = "lk.arac_id = ?"; $params[] = $filtreler['arac_id']; }
        if (!empty($filtreler['tesis_id'])) { $where[] = "lk.tesis_id = ?"; $params[] = $filtreler['tesis_id']; }
        if (!empty($filtreler['urun_id'])) { $where[] = "lk.urun_id = ?"; $params[] = $filtreler['urun_id']; }
        if ($filtreler['tur'] === 'arac') { $where[] = "lk.kayit_turu = 'arac'"; }
        elseif ($filtreler['tur'] === 'tesis') { $where[] = "lk.kayit_turu = 'tesis'"; }
        if ($filtreler['islendi'] === 'islendi') { $where[] = "lk.islendi = 1"; }
        elseif ($filtreler['islendi'] === 'islenmedi') { $where[] = "lk.islendi = 0"; }

        return ['where' => implode(" AND ", $where), 'params' => $params];
    }

    public static function istatistikGetir($pdo, $sartlar) {
        $stmt = $pdo->prepare("SELECT COUNT(*), COUNT(CASE WHEN lk.kayit_turu='arac' THEN 1 END), COUNT(CASE WHEN lk.kayit_turu='tesis' THEN 1 END), COALESCE(SUM(lk.miktar),0) FROM records lk LEFT JOIN products u ON lk.urun_id=u.id WHERE " . $sartlar['where']);
        $stmt->execute($sartlar['params']);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        return [
            'toplam' => (int)$row[0],
            'arac' => (int)$row[1],
            'tesis' => (int)$row[2],
            'litre' => (float)$row[3]
        ];
    }

    public static function listeSayfalamali($pdo, $sartlar, $offset, $limit) {
        $stmt = $pdo->prepare("
            SELECT lk.*, u.urun_adi, u.urun_kodu, a.plaka, a.marka_model, at.tur_adi AS arac_turu,
                   t.firma_adi, k.ad_soyad, ik.ad_soyad AS islendi_ad_soyad
            FROM records lk
            LEFT JOIN products u ON lk.urun_id = u.id
            LEFT JOIN vehicles a ON lk.arac_id = a.id
            LEFT JOIN vehicles_type at ON a.arac_turu_id = at.id
            LEFT JOIN facilities t ON lk.tesis_id = t.id
            LEFT JOIN users k ON lk.olusturan_id = k.id
            LEFT JOIN users ik ON lk.islendi_kullanici_id = ik.id
            WHERE " . $sartlar['where'] . "
            ORDER BY lk.tarih DESC, lk.olusturma_tarihi DESC
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset
        );
        $stmt->execute($sartlar['params']);
        return $stmt->fetchAll();
    }

    public static function bekleyenSayisi($pdo) {
        return (int)$pdo->query("SELECT COUNT(*) FROM records WHERE aktif=1 AND islendi=0")->fetchColumn();
    }

    public static function islenenSayisi($pdo) {
        return (int)$pdo->query("SELECT COUNT(*) FROM records WHERE aktif=1 AND islendi=1")->fetchColumn();
    }

    public static function raporOzetUrunBazli($pdo, $where_sql, $params) {
        $stmt = $pdo->prepare("
            SELECT lk.urun_id, u.urun_kodu, u.urun_adi,
                   COUNT(*) AS adet, COALESCE(SUM(lk.miktar), 0) AS toplam
            FROM records lk
            JOIN products u ON lk.urun_id = u.id
            WHERE $where_sql
            GROUP BY lk.urun_id, u.urun_kodu, u.urun_adi
            ORDER BY toplam DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function raporToplamKayit($pdo, $where_sql, $params) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM records lk WHERE $where_sql");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public static function raporDetayliKayitlar($pdo, $where_sql, $params, $limit = null, $offset = null) {
        $sql = "
            SELECT lk.*, u.urun_adi, u.urun_kodu, a.plaka, a.marka_model, at.tur_adi AS arac_turu,
               t.firma_adi, k.ad_soyad
            FROM records lk
            JOIN products u  ON lk.urun_id    = u.id
            LEFT JOIN vehicles  a  ON lk.arac_id    = a.id
            LEFT JOIN vehicles_type at ON a.arac_turu_id = at.id
            LEFT JOIN facilities t  ON lk.tesis_id   = t.id
            LEFT JOIN users  k  ON lk.olusturan_id = k.id
            WHERE $where_sql
            ORDER BY lk.tarih DESC, lk.olusturma_tarihi DESC
        ";
        if ($limit !== null && $offset !== null) {
            $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
