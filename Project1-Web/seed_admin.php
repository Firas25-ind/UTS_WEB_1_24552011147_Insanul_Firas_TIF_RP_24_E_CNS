<?php
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(403); exit('Akses ditolak. File ini hanya boleh dibuka dari localhost.'); }
require_once __DIR__.'/config/database.php';
$username='admin'; $email='admin@steinsgate.local'; $password='admin123';
$check=$pdo->prepare("SELECT id FROM users WHERE username=?"); $check->execute([$username]);
if($check->fetch()){echo "Akun admin sudah tersedia. Hapus file seed_admin.php setelah setup.";exit;}
$hash=password_hash($password,PASSWORD_DEFAULT);
$st=$pdo->prepare("INSERT INTO users(username,email,password,role) VALUES(?,?,?,'admin')");$st->execute([$username,$email,$hash]);
echo "Admin berhasil dibuat. Username: admin | Password: admin123. HAPUS seed_admin.php setelah digunakan.";
