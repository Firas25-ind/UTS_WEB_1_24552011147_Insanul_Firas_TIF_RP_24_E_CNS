<?php

define('APP_NAME', 'STEINS GATE');

// Zona waktu Indonesia Barat: dipakai untuk deadline tugas, hari, dan tanggal absensi.
date_default_timezone_set('Asia/Jakarta');
/*
 * BASE_URL dideteksi otomatis dari lokasi folder proyek terhadap document root,
 * sehingga tetap berfungsi walaupun nama folder di htdocs diganti.
 * Jika perlu, definisikan manual SEBELUM file ini dimuat, atau ubah nilai $manualBase.
 * Contoh: $manualBase = '/steins-gate';
 */
$manualBase = null;

if ($manualBase !== null) {
    $base = rtrim($manualBase, '/');
} else {
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $appRoot = realpath(__DIR__ . '/..') ?: '';
    $docRoot = rtrim(str_replace('\\', '/', $docRoot), '/');
    $appRoot = str_replace('\\', '/', $appRoot);
    $base = '';
    if ($docRoot !== '' && strpos($appRoot, $docRoot) === 0) {
        $rel = trim(substr($appRoot, strlen($docRoot)), '/');
        // Nama folder boleh mengandung spasi/karakter khusus, jadi di-encode per segmen.
        $base = $rel === '' ? '' : '/' . implode('/', array_map('rawurlencode', explode('/', $rel)));
    }
}
define('BASE_URL', $base);

define('COPYRIGHT_TEXT', '@Copyright by 24552011147_Insanul Firas TIF RP 24 E CNS');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
