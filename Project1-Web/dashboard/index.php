<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$counts = [
    'Siswa'       => $pdo->query("SELECT COUNT(*) FROM siswa")->fetchColumn(),
    'Guru'        => $pdo->query("SELECT COUNT(*) FROM guru")->fetchColumn(),
    'Informasi'   => $pdo->query("SELECT COUNT(*) FROM informasi")->fetchColumn(),
    'Kegiatan'    => $pdo->query("SELECT COUNT(*) FROM kegiatan")->fetchColumn(),
    'Dokumentasi' => $pdo->query("SELECT COUNT(*) FROM galeri")->fetchColumn(),
];
$latest = $pdo->query("SELECT id,judul,tanggal_publikasi FROM informasi ORDER BY tanggal_publikasi DESC, id DESC LIMIT 5")->fetchAll();

$pageTitle = 'Dashboard — STEINS GATE';
include __DIR__ . '/../includes/dashboard_page_start.php';
?>
<div class="section-kicker">YOUR CLASS AT A GLANCE</div>
<h1 class="dash-welcome">Welcome, <em><?= e($namaUser) ?>.</em></h1>
<p class="muted-copy">Selamat datang kembali di ruang digital keluarga IPA 2.</p>

<div class="row g-3 mt-3">
    <?php foreach ($counts as $label => $count): ?>
        <div class="col-6 col-xl">
            <div class="dash-stat"><span><?= e($label) ?></span><strong><?= (int)$count ?></strong><small>Data tersimpan</small></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mt-2">
    <div class="col-lg-7">
        <div class="dash-panel">
            <div class="panel-heading"><h3>Informasi Terbaru</h3><span>RECENT UPDATES</span></div>
            <?php foreach ($latest as $item): ?>
                <a class="recent-item text-decoration-none" href="<?= BASE_URL ?>/detail.php?id=<?= (int)$item['id'] ?>&from=dashboard">
                    <span class="recent-icon"><i class="bi bi-file-earmark-text"></i></span>
                    <div><strong><?= e($item['judul']) ?></strong><small><?= date('d M Y', strtotime($item['tanggal_publikasi'])) ?></small></div>
                    <i class="bi bi-arrow-up-right ms-auto"></i>
                </a>
            <?php endforeach; ?>
            <?php if (!$latest): ?><p class="muted-copy">Belum ada informasi.</p><?php endif; ?>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="dash-panel welcome-panel">
            <div class="section-kicker">CLASS MANAGEMENT</div>
            <h3>Kelola kelas dengan rapi.</h3>
            <p>Atur kelas siswa baru, data guru, jadwal, dan informasi yang tampil di halaman awal.</p>
            <a href="<?= BASE_URL ?>/dashboard/siswa.php?k=none" class="btn btn-cream">Siswa Belum Ada Kelas <i class="bi bi-arrow-up-right"></i></a>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/dashboard_page_end.php'; ?>
