<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/guru_helpers.php';
require_once __DIR__ . '/../includes/akademik_helpers.php';
require_role('guru');

$u = current_user();
$q = $pdo->prepare("SELECT id FROM guru WHERE user_id=?");
$q->execute([$u['id']]);
$guruId = (int)$q->fetchColumn();
if (!$guruId) exit('Profil guru tidak ditemukan.');

$tid = (int)($_GET['tugas'] ?? 0);
$t = $pdo->prepare("SELECT * FROM tugas WHERE id=? AND guru_id=?");
$t->execute([$tid, $guruId]);
$tugas = $t->fetch();
if (!$tugas) { http_response_code(404); exit('Tugas tidak ditemukan.'); }
// Hanya untuk kelas yang ditetapkan admin kepada guru ini
if (!in_array((string)$tugas['target_kelas'], guru_kelas($pdo, $guruId), true)) {
    http_response_code(403);
    exit('Anda tidak ditetapkan mengajar di kelas ' . htmlspecialchars((string)$tugas['target_kelas']) . '.');
}

$st = $pdo->prepare("
    SELECT s.id, s.nis, s.nama_lengkap, p.id AS jawaban_id, p.nama_asli, p.catatan, p.submitted_at
    FROM siswa s
    LEFT JOIN pengumpulan_tugas p ON p.siswa_id = s.id AND p.tugas_id = ?
    WHERE s.kelas = ?
    ORDER BY (p.id IS NULL), s.nama_lengkap
");
$st->execute([$tid, $tugas['target_kelas']]);
$rows = $st->fetchAll();
$sudah = count(array_filter($rows, fn($r) => $r['jawaban_id']));

// Pratinjau langsung di halaman (?lihat=ID jawaban)
$preview = null;
$lihat = (int)($_GET['lihat'] ?? 0);
foreach ($rows as $r) {
    if ($lihat > 0 && (int)$r['jawaban_id'] === $lihat) { $preview = $r; break; }
}
$previewExt = $preview ? strtolower(pathinfo($preview['nama_asli'], PATHINFO_EXTENSION)) : '';
$canInline = in_array($previewExt, ['pdf', 'png', 'jpg', 'jpeg', 'txt'], true);

$pageTitle = 'Jawaban Tugas — STEINS GATE';
include __DIR__ . '/../includes/dashboard_page_start.php';
?>
<div class="page-heading">
    <div>
        <div class="section-kicker">TEACHER / SUBMISSIONS</div>
        <h1>Jawaban <em>Siswa.</em></h1>
        <p><?= e($tugas['judul']) ?> · Kelas <?= e($tugas['target_kelas']) ?> · <?= $sudah ?> dari <?= count($rows) ?> siswa sudah mengirim · Deadline: <?= e(deadline_label($tugas['deadline'] ?? null)) ?><?= !tugas_terbuka($tugas['deadline'] ?? null) ? ' (ditutup)' : '' ?></p>
    </div>
    <a class="btn btn-outline-brown" href="<?= BASE_URL ?>/guru/tugas.php"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<?php if ($preview): $viewUrl = BASE_URL . '/download.php?id=' . (int)$preview['jawaban_id']; ?>
<div class="sg-panel mb-4" id="preview">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <div class="section-kicker">PREVIEW JAWABAN</div>
            <h3 class="mb-0"><?= e($preview['nama_lengkap']) ?> <small class="text-muted">· <?= e($preview['nama_asli']) ?></small></h3>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-brown" href="<?= $viewUrl ?>&view=1" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Buka di tab baru</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= $viewUrl ?>"><i class="bi bi-download"></i> Unduh</a>
            <a class="btn btn-sm btn-outline-secondary" href="?tugas=<?= (int)$tid ?>"><i class="bi bi-x-lg"></i> Tutup</a>
        </div>
    </div>
    <?php if (!$canInline): ?>
        <div class="alert alert-info mb-0"><i class="bi bi-info-circle me-2"></i>File <strong>.<?= e($previewExt) ?></strong> tidak bisa ditampilkan langsung oleh browser. Hanya PDF, gambar (PNG/JPG), dan TXT yang bisa dilihat di sini. Silakan unduh file ini untuk membukanya.</div>
    <?php elseif (in_array($previewExt, ['png', 'jpg', 'jpeg'], true)): ?>
        <div class="text-center"><img src="<?= $viewUrl ?>&view=1" alt="Jawaban <?= e($preview['nama_lengkap']) ?>" style="max-width:100%;max-height:80vh;border-radius:12px"></div>
    <?php else: ?>
        <iframe src="<?= $viewUrl ?>&view=1" title="Jawaban <?= e($preview['nama_lengkap']) ?>" style="width:100%;height:80vh;border:1px solid var(--line);border-radius:12px;background:#fff"></iframe>
    <?php endif; ?>
    <?php if (!empty($preview['catatan'])): ?><p class="mt-3 mb-0 text-muted">Catatan siswa: <?= e($preview['catatan']) ?></p><?php endif; ?>
</div>
<?php endif; ?>

<div class="sg-panel table-responsive">
    <table class="table sg-table align-middle">
        <thead><tr><th>Nama Siswa</th><th>NIS</th><th>Status</th><th>File Jawaban</th><th>Catatan</th><th>Waktu Kirim</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r['nama_lengkap']) ?></td>
                <td><?= e($r['nis']) ?></td>
                <td><?= $r['jawaban_id'] ? '<span class="sg-badge">Terkirim</span>' : '<span class="sg-badge sg-badge-warn">Belum</span>' ?></td>
                <td><?= $r['jawaban_id'] ? '<i class="bi bi-file-earmark-text"></i> ' . e($r['nama_asli']) : '—' ?></td>
                <td><?= e($r['catatan'] ?? '—') ?></td>
                <td><?= $r['submitted_at'] ? date('d M Y H:i', strtotime($r['submitted_at'])) : '—' ?></td>
                <td><?php if ($r['jawaban_id']): ?>
                    <a class="btn btn-sm btn-brown" href="?tugas=<?= (int)$tid ?>&lihat=<?= (int)$r['jawaban_id'] ?>#preview"><i class="bi bi-eye"></i> Lihat</a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= BASE_URL ?>/download.php?id=<?= (int)$r['jawaban_id'] ?>" title="Unduh"><i class="bi bi-download"></i></a>
                <?php else: ?>—<?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="text-center py-4">Belum ada siswa yang ditetapkan ke kelas ini oleh Admin.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/../includes/dashboard_page_end.php'; ?>