<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/guru_helpers.php';
require_role('guru');

$u = current_user();
$q = $pdo->prepare("SELECT id, nama_lengkap, mata_pelajaran FROM guru WHERE user_id=?");
$q->execute([$u['id']]);
$g = $q->fetch();
if (!$g) { http_response_code(403); exit('Profil guru tidak ditemukan.'); }
$myKelas = guru_kelas($pdo, (int)$g['id']);

$pageTitle = 'Dashboard Guru — STEINS GATE';
include __DIR__ . '/../includes/dashboard_page_start.php';
?>
<div class="page-heading"><div><div class="section-kicker">TEACHER WORKSPACE</div><h1>Dashboard <em>Guru.</em></h1><p>Selamat datang, <?= e($g['nama_lengkap']) ?>.</p></div></div>

<?php if (!$myKelas): ?>
    <div class="alert alert-warning"><i class="bi bi-info-circle me-2"></i>Kelas ajar Anda belum ditetapkan oleh Admin. Tugas, jawaban siswa, dan absensi baru bisa dipakai setelah kelas ditetapkan.</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-md-4"><div class="sg-panel"><span class="section-kicker">MATA PELAJARAN</span><h2><?= e($g['mata_pelajaran']) ?></h2></div></div>
    <div class="col-md-4"><div class="sg-panel"><span class="section-kicker">KELAS AJAR</span>
        <h2><?= $myKelas ? 'Kelas ' . e(implode(', ', $myKelas)) : '—' ?></h2></div></div>
    <div class="col-md-4"><div class="sg-panel"><span class="section-kicker">AKTIVITAS</span><p>Kelola tugas dan absensi untuk kelas ajar Anda.</p>
        <a class="btn btn-brown" href="<?= BASE_URL ?>/guru/tugas.php">Buat Tugas</a>
        <a class="btn btn-outline-brown" href="<?= BASE_URL ?>/guru/absensi.php">Absensi</a></div></div>
</div>
<?php include __DIR__ . '/../includes/dashboard_page_end.php'; ?>