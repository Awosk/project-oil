<?php
/*
 * Project Oil - Vehicle and Facility Industrial Oil Tracking System
 * Copyright (C) 2026 Awosk
 */

class SistemYedek {

    public static function tablolariGetir($pdo) {
        return $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function listele($backup_dir) {
        $yedekler = [];
        foreach (glob($backup_dir . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $f) {
            $yedekler[] = [
                'dosya' => basename($f),
                'boyut' => round(filesize($f) / 1024, 1),
                'tarih' => date('d.m.Y H:i', filemtime($f)),
                'zaman' => filemtime($f),
            ];
        }
        usort($yedekler, fn($a, $b) => $b['zaman'] - $a['zaman']);
        return $yedekler;
    }

    public static function yedekAl($pdo, $secili_tablolar, $backup_dir) {
        $tablolar = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        $sql  = "-- " . SITE_ADI . " Veritabanı Yedeği\n";
        $sql .= "-- Tarih: " . date('d.m.Y H:i:s') . "\n";
        $sql .= "-- Veritabanı: " . DB_NAME . "\n";
        $sql .= "-- Tablolar: " . implode(', ', $secili_tablolar) . "\n\n";
        $sql .= "SET NAMES utf8mb4;\n";
        $sql .= "SET foreign_key_checks = 0;\n\n";

        foreach ($secili_tablolar as $tablo) {
            if (!in_array($tablo, $tablolar)) continue;
            $create = $pdo->query("SHOW CREATE TABLE `$tablo`")->fetch();
            $sql .= "-- ─────────────────────────────────\n";
            $sql .= "DROP TABLE IF EXISTS `$tablo`;\n";
            $sql .= $create['Create Table'] . ";\n\n";
            $rows = $pdo->query("SELECT * FROM `$tablo`")->fetchAll();
            if (!empty($rows)) {
                $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';
                $sql .= "INSERT INTO `$tablo` ($cols) VALUES\n";
                $inserts = [];
                foreach ($rows as $row) {
                    $vals = array_map(function($v) use ($pdo) {
                        if ($v === null) return 'NULL';
                        return $pdo->quote($v);
                    }, array_values($row));
                    $inserts[] = '(' . implode(', ', $vals) . ')';
                }
                $sql .= implode(",\n", $inserts) . ";\n\n";
            }
        }
        $sql .= "SET foreign_key_checks = 1;\n";

        $dosya_adi  = 'backup_' . date('Ymd_His') . '_' . DB_NAME . '.sql';
        $dosya_yolu = $backup_dir . DIRECTORY_SEPARATOR . $dosya_adi;
        $yazildi    = file_put_contents($dosya_yolu, $sql);

        if ($yazildi === false) return false;
        return ['dosya_adi' => $dosya_adi, 'boyut_kb' => round($yazildi / 1024, 1)];
    }

    public static function geriYukle($pdo, $yol, $secili_tablolar) {
        $icerik  = file_get_contents($yol);
        $bloklar = [];
        $satirlar = explode("\n", str_replace("\r\n", "\n", $icerik));
        $aktif_tablo = null;
        $aktif_blok  = [];

        foreach ($satirlar as $satir) {
            if (preg_match('/^DROP TABLE IF EXISTS `([^`]+)`/i', $satir, $m)) {
                if ($aktif_tablo !== null) { $bloklar[$aktif_tablo] = implode("\n", $aktif_blok); }
                $aktif_tablo = $m[1]; $aktif_blok = [$satir];
            } elseif ($aktif_tablo !== null) {
                if (str_starts_with(trim($satir), 'SET foreign_key_checks = 1')) {
                    $bloklar[$aktif_tablo] = implode("\n", $aktif_blok);
                    $aktif_tablo = null; $aktif_blok = [];
                } else { $aktif_blok[] = $satir; }
            }
        }
        if ($aktif_tablo !== null && !empty($aktif_blok)) { $bloklar[$aktif_tablo] = implode("\n", $aktif_blok); }

        $pdo->exec("SET foreign_key_checks = 0");
        $pdo->exec("SET NAMES utf8mb4");
        $pdo->exec("SET SESSION wait_timeout = 300");
        $pdo->exec("SET SESSION interactive_timeout = 300");
        $pdo->exec("SET SESSION net_write_timeout = 300");
        $pdo->exec("SET SESSION net_read_timeout = 300");

        $hatalar  = [];
        $basarili = [];

        foreach ($secili_tablolar as $tablo) {
            if (!isset($bloklar[$tablo])) { $hatalar[] = $tablo . ': Yedekte bulunamadı'; continue; }
            $blok = trim($bloklar[$tablo]);
            $ifadeler = [];
            $tmp = ''; $tirnak = false; $tirnak_char = ''; $blok_len = strlen($blok);
            for ($i = 0; $i < $blok_len; $i++) {
                $c = $blok[$i];
                if (!$tirnak && ($c === "'" || $c === '"' || $c === '`')) { $tirnak = true; $tirnak_char = $c; $tmp .= $c; }
                elseif ($tirnak && $c === $tirnak_char && ($i === 0 || $blok[$i-1] !== '\\')) { $tirnak = false; $tmp .= $c; }
                elseif (!$tirnak && $c === ';') { $s = trim($tmp); if ($s !== '') $ifadeler[] = $s; $tmp = ''; }
                else { $tmp .= $c; }
            }
            if (trim($tmp) !== '') $ifadeler[] = trim($tmp);

            $tablo_hata = false;
            foreach ($ifadeler as $ifade) {
                $ifade = trim($ifade);
                if ($ifade === '' || str_starts_with($ifade, '--')) continue;
                if (stripos($ifade, 'INSERT INTO') === 0) {
                    $values_pos = stripos($ifade, ' VALUES');
                    if ($values_pos === false) {
                        try { $pdo->exec($ifade); } catch (PDOException $e) { $hatalar[] = $tablo . ': ' . $e->getMessage(); $tablo_hata = true; break; }
                        continue;
                    }
                    $prefix = substr($ifade, 0, $values_pos + 7);
                    $values_raw = trim(substr($ifade, $values_pos + 7));
                    $values_raw = rtrim($values_raw, ';');
                    $rows = []; $buf = ''; $depth = 0; $in_str = false; $str_char = ''; $vlen = strlen($values_raw);
                    for ($vi = 0; $vi < $vlen; $vi++) {
                        $vc = $values_raw[$vi];
                        if (!$in_str && ($vc === "'" || $vc === '"')) { $in_str = true; $str_char = $vc; $buf .= $vc; }
                        elseif ($in_str && $vc === $str_char && ($vi === 0 || $values_raw[$vi-1] !== '\\')) { $in_str = false; $buf .= $vc; }
                        elseif (!$in_str && $vc === '(') { $depth++; $buf .= $vc; }
                        elseif (!$in_str && $vc === ')') {
                            $depth--; $buf .= $vc;
                            if ($depth === 0) { $rows[] = trim($buf); $buf = ''; while ($vi + 1 < $vlen) { $next = $values_raw[$vi + 1]; if ($next === ',' || $next === "\n" || $next === "\r" || $next === ' ') { $vi++; } else { break; } } }
                        } else { $buf .= $vc; }
                    }
                    if (empty($rows)) { try { $pdo->exec($ifade); } catch (PDOException $e) { $hatalar[] = $tablo . ': ' . $e->getMessage(); $tablo_hata = true; break; } continue; }
                    $batch_size = 200; $chunks = array_chunk($rows, $batch_size);
                    foreach ($chunks as $chunk) {
                        $batch_sql = $prefix . "\n" . implode(",\n", $chunk) . ";";
                        try { $pdo->exec($batch_sql); } catch (PDOException $e) { $hatalar[] = $tablo . ': ' . $e->getMessage(); $tablo_hata = true; break 2; }
                    }
                } else {
                    try { $pdo->exec($ifade); } catch (PDOException $e) { $hatalar[] = $tablo . ': ' . $e->getMessage(); $tablo_hata = true; break; }
                }
            }
            if (!$tablo_hata) { $basarili[] = $tablo; }
        }

        $pdo->exec("SET foreign_key_checks = 1");

        return [
            'basarili' => $basarili,
            'hatalar' => $hatalar
        ];
    }
}
