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

class SistemGuncelleme {
    public const GITHUB_REPO = 'Awosk/project-oil';
    public const GITHUB_API  = 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest';
    
    // Korunacak yol/dosyalar — güncelleme sırasında üzerine yazılmaz
    public const KORUNANLAR  = [
        '.env',
        'pages/db_backups/',
    ];

    /**
     * Migration tablosu yoksa oluştur.
     */
    public static function hazirla(PDO $pdo): void {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `system_migrations` (
                `versiyon` varchar(20) NOT NULL,
                `uygulandi_tarih` datetime NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`versiyon`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci");
        } catch (Exception $e) {}
    }

    /**
     * Uygulanan migrationları getirir
     */
    public static function uygulanmisVersiyonlar(PDO $pdo): array {
        try {
            return $pdo->query("SELECT versiyon FROM system_migrations ORDER BY versiyon")
                       ->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) { return []; }
    }

    /**
     * GitHub Releases API'ye GET isteği atar
     */
    public static function githubApiIste(): ?array {
        $ctx = stream_context_create(['http' => [
            'method'  => 'GET',
            'header'  => "User-Agent: ProjectOil-Updater/1.0\r\n",
            'timeout' => 10,
        ]]);
        $json = @file_get_contents(self::GITHUB_API, false, $ctx);
        if (!$json) return null;
        return json_decode($json, true);
    }

    /**
     * Uzak versiyon lokalden büyük mü kontrolü
     */
    public static function yeniVersionVar(string $lokal, string $uzak): bool {
        return version_compare(ltrim($uzak, 'v'), ltrim($lokal, 'v'), '>');
    }

    /**
     * Dosya yolunun korunup korunmayacağını kontrol eder
     */
    public static function korunuyorMu(string $dosyaYolu): bool {
        foreach (self::KORUNANLAR as $koruma) {
            if (str_starts_with($dosyaYolu, $koruma)) return true;
            if ($dosyaYolu === rtrim($koruma, '/')) return true;
        }
        return false;
    }

    /**
     * Klasörü içiyle beraber siler
     */
    public static function klasorSil(string $dir): void {
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

    /**
     * Sürüm kontrolü (AJAX endpoint'i için)
     */
    public static function surumKontrol(PDO $pdo): array {
        $data = self::githubApiIste();
        if (!$data || empty($data['tag_name'])) {
            return ['ok' => false, 'mesaj' => 'GitHub API\'ye erişilemedi.'];
        }
        $uzak_tag      = $data['tag_name'];
        $uzak_versiyon = ltrim($uzak_tag, 'v');
        $yeni_var      = self::yeniVersionVar(SITE_VERSIYONU, $uzak_versiyon);

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
        $uygulanmis   = self::uygulanmisVersiyonlar($pdo);
        $bekleyen_sql = array_filter($migration_listesi, function($v) use ($uygulanmis) {
            return !in_array($v, $uygulanmis) && version_compare($v, SITE_VERSIYONU, '>');
        });

        return [
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
        ];
    }

    /**
     * Güncellemeyi bizzat uygulama
     */
    public static function uygula(PDO $pdo, string $zipball_url, string $uzak_versiyon, array $assets, string $rootDir): array {
        $log = [];
        $tmp_zip = sys_get_temp_dir() . '/project_oil_update_' . time() . '.zip';
        $tmp_dir = sys_get_temp_dir() . '/project_oil_extract_' . time();

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
                if (self::korunuyorMu($goreceli)) { $atlanan++; continue; }

                $hedef = $rootDir . '/' . $goreceli;
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
            $uygulanmis = self::uygulanmisVersiyonlar($pdo);
            $migration_yapildi = 0;

            $sql_assets = [];
            foreach ($assets as $asset) {
                if (preg_match('/^(\d+\.\d+(?:\.\d+)?)\.sql$/', $asset['name'], $m)) {
                    $versiyon = $m[1];
                    if (!in_array($versiyon, $uygulanmis) && version_compare($versiyon, SITE_VERSIYONU, '>')) {
                        $sql_assets[$versiyon] = $asset['browser_download_url'];
                    }
                }
            }

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

                    $satirlar = explode("\n", str_replace("\r\n", "\n", $sql));
                    $temiz = implode("\n", array_filter(
                        array_map('trim', $satirlar),
                        fn($s) => $s !== '' && !str_starts_with($s, '--')
                    ));
                    $ifadeler = array_filter(array_map('trim', explode(';', $temiz)), fn($s) => $s !== '');
                    foreach ($ifadeler as $ifade) {
                        $pdo->exec($ifade);
                    }

                    $pdo->prepare("INSERT IGNORE INTO system_migrations (versiyon) VALUES (?)")->execute([$versiyon]);
                    $log[] = "✓ Migration $versiyon tamamlandı";
                    $migration_yapildi++;
                } catch (Exception $me) {
                    $log[] = "⚠️ Migration $versiyon hatası: " . $me->getMessage();
                }
            }

            if ($migration_yapildi === 0) $log[] = 'ℹ️ Uygulanacak SQL migrasyonu yok';

            // ── 5. version.php güncelle ──
            file_put_contents($rootDir . '/version.php', "<?php\n/*\n * Project Oil - Vehicle and Facility Industrial Oil Tracking System\n * Copyright (C) 2026 Awosk\n *\n * This program is free software: you can redistribute it and/or modify\n * it under the terms of the GNU General Public License as published by\n * the Free Software Foundation, either version 3 of the License, or\n * (at your option) any later version.\n */\n\ndefine('SITE_VERSIYONU', '$uzak_versiyon');\n");
            $log[] = "✓ Versiyon $uzak_versiyon olarak güncellendi";

            // ── 6. Temp temizle ──
            self::klasorSil($tmp_dir);

            // ── 7. Log yaz ──
            logYaz($pdo, 'guncelle', 'sistem',
                "Sistem güncellendi: v" . SITE_VERSIYONU . " → v$uzak_versiyon",
                null, ['versiyon' => SITE_VERSIYONU], ['versiyon' => $uzak_versiyon], 'lite');

            return ['ok' => true, 'log' => $log, 'yeni_versiyon' => $uzak_versiyon];

        } catch (Exception $e) {
            if (file_exists($tmp_zip)) @unlink($tmp_zip);
            if (is_dir($tmp_dir))     self::klasorSil($tmp_dir);
            $log[] = '❌ Hata: ' . $e->getMessage();
            return ['ok' => false, 'log' => $log, 'mesaj' => $e->getMessage()];
        }
    }
}
