<?php
// Buka / unduh jawaban tugas. Hanya siswa pemilik, guru pemberi tugas, atau admin.
// ?id=ID          -> unduh file
// ?id=ID&view=1   -> tampilkan langsung di browser (PDF, gambar, TXT); format lain otomatis diunduh
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("
    SELECT p.file_path, p.nama_asli, g.user_id AS guru_user, s.user_id AS siswa_user
    FROM pengumpulan_tugas p
    JOIN tugas t ON t.id = p.tugas_id
    JOIN guru g ON g.id = t.guru_id
    JOIN siswa s ON s.id = p.siswa_id
    WHERE p.id = ?
");
$st->execute([$id]);
$row = $st->fetch();

$u = current_user();
$allowed = $row && (
    $u['role'] === 'admin' ||
    ((int)$row['guru_user'] === (int)$u['id'] && $u['role'] === 'guru') ||
    ((int)$row['siswa_user'] === (int)$u['id'] && $u['role'] === 'siswa')
);
if (!$allowed) { http_response_code(403); exit('Akses ditolak atau file tidak ditemukan.'); }

$base = realpath(__DIR__ . '/assets/uploads/jawaban');
$file = realpath(__DIR__ . '/' . ltrim($row['file_path'], '/'));
if (!$base || !$file || strpos($file, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($file)) {
    http_response_code(404); exit('File tidak ditemukan di server.');
}

// Hanya jenis yang aman & bisa dirender browser yang boleh tampil langsung
$inlineTypes = [
    'pdf'  => 'application/pdf',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'txt'  => 'text/plain; charset=UTF-8',
];
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$inline = isset($_GET['view']) && isset($inlineTypes[$ext]);

$name = str_replace(['"', "\r", "\n"], '_', $row['nama_asli']);
header('Content-Type: ' . ($inline ? $inlineTypes[$ext] : 'application/octet-stream'));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode($row['nama_asli']));
header('Content-Length: ' . filesize($file));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');
readfile($file);
exit;