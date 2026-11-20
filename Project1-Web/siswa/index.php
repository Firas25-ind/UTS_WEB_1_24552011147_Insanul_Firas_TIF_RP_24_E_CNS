<?php
require_once __DIR__.'/../config/database.php';require_once __DIR__.'/../includes/functions.php';require_role('siswa');
$u=current_user();$q=$pdo->prepare("SELECT nama_lengkap,kelas FROM siswa WHERE user_id=?");$q->execute([$u['id']]);$s=$q->fetch();if(!$s)exit('Profil siswa tidak ditemukan.');
$pageTitle='Dashboard Siswa — STEINS GATE';include __DIR__.'/../includes/dashboard_page_start.php';
?>
<div class="page-heading"><div><div class="section-kicker">STUDENT WORKSPACE</div><h1>Dashboard <em>Siswa.</em></h1><p>Selamat datang, <?=e($s['nama_lengkap'])?>.</p></div></div><div class="sg-panel"><span class="section-kicker">KELAS ANDA</span><h2><?= $s['kelas']?'Kelas '.e($s['kelas']):'Belum ditentukan'?></h2><p class="muted-copy">Tugas dan absensi akan mengikuti kelas yang ditetapkan Admin.</p><a class="btn btn-brown" href="<?=BASE_URL?>/siswa/tugas.php">Lihat Tugas</a> <a class="btn btn-outline-brown" href="<?=BASE_URL?>/siswa/absensi.php">Riwayat Absensi</a></div>
<?php include __DIR__.'/../includes/dashboard_page_end.php';?>
