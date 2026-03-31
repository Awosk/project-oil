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
girisKontrol();

header('Content-Type: application/json; charset=utf-8');

$tema = trim($_POST['tema'] ?? '');
if (!in_array($tema, ['light', 'dark'])) {
    echo json_encode(['ok' => false]);
    exit;
}

$ku = mevcutKullanici();
$pdo->prepare("UPDATE users SET theme = ? WHERE id = ?")
    ->execute([$tema, $ku['id']]);

$_SESSION['kullanici_tema'] = $tema;

echo json_encode(['ok' => true]);
