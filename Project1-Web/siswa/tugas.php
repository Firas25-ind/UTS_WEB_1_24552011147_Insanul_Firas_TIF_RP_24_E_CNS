<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_crud_helpers.php';
require_once __DIR__ . '/../includes/akademik_helpers.php';
require_role('siswa');

$u = current_user();
$q = $pdo->prepare("SELECT id, kelas FROM siswa WHERE user_id=?");
$q->execute([$u['id']]);
$s = $q->fetch();
if (!$s) exit('Profil siswa tidak ditemukan.');

$self = BASE_URL . '/siswa/tugas.php';
$error = '';
$mapelAktif = trim($_GET['mapel'] ?? '');

// ---------- Kirim / ganti / hapus jawaban ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('csrf_jawab');
    $task = $_POST['task'] ?? 'kirim';
    $tugasId = (int)($_POST['tugas_id'] ?? 0);
    $catatan = mb_substr(trim($_POST['catatan'] ?? ''), 0, 255);
    $mapelAktif = trim($_POST['mapel'] ?? '');
    $back = $self . ($mapelAktif !== '' ? '?mapel=' . urlencode($mapelAktif) : '');

    $t = $pdo->prepare("SELECT id, judul, target_kelas, deadline FROM tugas WHERE id=?");
    $t->execute([$tugasId]);
    $tugas = $t->fetch();

    $old = $pdo->prepare("SELECT id, file_path FROM pengumpulan_tugas WHERE tugas_id=? AND siswa_id=?");
    $old->execute([$tugasId, $s['id']]);
    $prev = $old->fetch();

    if (!$tugas || !$s['kelas'] || $tugas['target_kelas'] !== $s['kelas']) {
        $error = 'Tugas tidak ditemukan atau bukan untuk kelas Anda.';
    } elseif (!tugas_terbuka($tugas['deadline'])) {
        $error = 'Deadline tugas "' . $tugas['judul'] . '" sudah lewat. Jawaban tidak dapat dikirim, diubah, atau dihapus.';
    } elseif ($task === 'hapus') {
        if (!$prev) $error = 'Belum ada jawaban yang bisa dihapus.';
        else {
            $pdo->prepare("DELETE FROM pengumpulan_tugas WHERE id=?")->execute([$prev['id']]);
            delete_upload($prev['file_path'], 'jawaban');
            sg_flash_redirect('Jawaban dihapus. Anda masih bisa mengirim jawaban baru sebelum deadline.', $back);
        }
    } else {
        $noFile = empty($_FILES['jawaban']) || $_FILES['jawaban']['error'] === UPLOAD_ERR_NO_FILE;
        if ($noFile && !$prev) {
            $error = 'Pilih file jawaban terlebih dahulu.';
        } elseif ($noFile && $prev) {
            // hanya memperbarui catatan
            $pdo->prepare("UPDATE pengumpulan_tugas SET catatan=?, submitted_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$catatan ?: null, $prev['id']]);
            sg_flash_redirect('Catatan jawaban diperbarui.', $back);
        } else {
            $up = handle_upload($_FILES['jawaban'], 'jawaban', upload_allowed_types('dokumen'), 10 * 1024 * 1024, $error);
            if ($up && $error === '') {
                if ($prev) {
                    $pdo->prepare("UPDATE pengumpulan_tugas SET file_path=?, nama_asli=?, catatan=?, submitted_at=CURRENT_TIMESTAMP WHERE id=?")
                        ->execute([$up['path'], $up['original'], $catatan ?: null, $prev['id']]);
                    delete_upload($prev['file_path'], 'jawaban');
                    sg_flash_redirect('Jawaban berhasil diganti.', $back);
                }
                $pdo->prepare("INSERT INTO pengumpulan_tugas(tugas_id,siswa_id,file_path,nama_asli,catatan) VALUES(?,?,?,?,?)")
                    ->execute([$tugasId, $s['id'], $up['path'], $up['original'], $catatan ?: null]);
                sg_flash_redirect('Jawaban berhasil dikirim ke guru.', $back);
            }
        }
    }
}

// ---------- Data tugas ----------
$items = [];
$mapelList = [];
if ($s['kelas']) {
    $st = $pdo->prepare("
        SELECT t.*, g.mata_pelajaran, g.nama_lengkap,
               p.id AS jawaban_id, p.nama_asli, p.catatan AS jawaban_catatan, p.submitted_at
        FROM tugas t
        JOIN guru g ON g.id = t.guru_id
        LEFT JOIN pengumpulan_tugas p ON p.tugas_id = t.id AND p.siswa_id = ?
        WHERE t.target_kelas = ?
        ORDER BY t.created_at DESC, t.id DESC
    ");
    $st->execute([$s['id'], $s['kelas']]);
    $items = $st->fetchAll();

    // Tab mata pelajaran: dari tugas yang ada + mapel di jadwal kelas
    $jd = $pdo->prepare("SELECT DISTINCT mata_pelajaran FROM jadwal_pelajaran WHERE kelas=?");
    $jd->execute([$s['kelas']]);
    foreach (array_merge($jd->fetchAll(PDO::FETCH_COLUMN), array_column($items, 'mata_pelajaran')) as $m) {
        $key = mb_strtolower(trim($m));
        if ($key !== '' && !isset($mapelList[$key])) $mapelList[$key] = trim($m);
    }
    asort($mapelList, SORT_NATURAL | SORT_FLAG_CASE);
}
$aktifKey = mb_strtolower($mapelAktif);
if ($aktifKey !== '' && !isset($mapelList[$aktifKey])) { $aktifKey = ''; $mapelAktif = ''; }

$perMapel = [];       // key => daftar tugas
$hitung = [];         // key => [total, belum]
foreach ($items as $it) {
    $k = mb_strtolower(trim($it['mata_pelajaran']));
    $perMapel[$k][] = $it;
    $hitung[$k]['total'] = ($hitung[$k]['total'] ?? 0) + 1;
    if (!$it['jawaban_id'] && tugas_terbuka($it['deadline'])) $hitung[$k]['belum'] = ($hitung[$k]['belum'] ?? 0) + 1;
}
$tampil = $aktifKey === '' ? $perMapel : array_intersect_key($perMapel, [$aktifKey => 1]);

$pageTitle = 'Tugas Siswa — STEINS GATE';
include __DIR__ . '/../includes/dashboard_page_start.php';
?>
<div class="page-heading"><div><div class="section-kicker">STUDENT / ASSIGNMENTS</div><h1>Daftar <em>Tugas.</em></h1><p>Tugas kelas Anda dikelompokkan per mata pelajaran. Jawaban bisa diganti selama deadline belum lewat.</p></div></div>

<?php sg_show_flash(); ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<?php if (!$s['kelas']): ?>
    <div class="alert alert-warning">Kelas akun Anda belum ditetapkan oleh Admin, sehingga belum ada tugas yang tampil.</div>
<?php else: ?>
    <div class="class-tabs">
        <a class="<?= $aktifKey === '' ? 'active' : '' ?>" href="<?= $self ?>">Semua<span class="count"><?= count($items) ?></span></a>
        <?php foreach ($mapelList as $key => $nama): $b = $hitung[$key]['belum'] ?? 0; ?>
            <a class="<?= $aktifKey === $key ? 'active' : '' ?>" href="<?= $self ?>?mapel=<?= urlencode($nama) ?>"><?= e($nama) ?><span class="count"><?= (int)($hitung[$key]['total'] ?? 0) ?><?= $b ? ' · ' . $b . ' belum' : '' ?></span></a>
        <?php endforeach; ?>
    </div>

    <?php if (!$tampil): ?>
        <div class="sg-panel">Belum ada tugas<?= $aktifKey !== '' ? ' untuk mata pelajaran ' . e($mapelList[$aktifKey]) : ' untuk kelas ' . e($s['kelas']) ?>.</div>
    <?php else: foreach ($tampil as $key => $list): ?>
        <h4 class="folder-month"><i class="bi bi-book"></i> <?= e($mapelList[$key] ?? $key) ?> <small>· <?= count($list) ?> tugas</small></h4>
        <?php foreach ($list as $it):
            $open = tugas_terbuka($it['deadline']);
            $inline = $it['jawaban_id'] ? file_inline_ext($it['nama_asli']) : '';
            $viewUrl = $it['jawaban_id'] ? BASE_URL . '/download.php?id=' . (int)$it['jawaban_id'] : '';
        ?>
        <div class="sg-panel mb-3">
            <div class="d-flex justify-content-between flex-wrap gap-2">
                <div class="section-kicker"><?= e($it['mata_pelajaran']) ?> · KELAS <?= e($it['target_kelas']) ?></div>
                <div class="d-flex gap-2 flex-wrap">
                    <?php if ($it['jawaban_id']): ?><span class="sg-badge"><i class="bi bi-check-circle"></i> Sudah dikirim</span>
                    <?php elseif ($open): ?><span class="sg-badge sg-badge-warn">Belum dikirim</span>
                    <?php else: ?><span class="sg-badge sg-badge-warn">Tidak dikumpulkan</span><?php endif; ?>
                    <?php if ($it['deadline']): ?><span class="sg-badge <?= $open ? '' : 'sg-badge-warn' ?>"><i class="bi bi-alarm"></i> <?= $open ? e(sisa_waktu($it['deadline'])) : 'Ditutup' ?></span><?php endif; ?>
                </div>
            </div>
            <h3 class="mt-2"><?= e($it['judul']) ?></h3>
            <p><?= nl2br(e($it['deskripsi'])) ?></p>
            <small class="text-muted">Guru: <?= e($it['nama_lengkap']) ?> · dibuat <?= date('d M Y H:i', strtotime($it['created_at'])) ?> · Deadline: <strong><?= e(deadline_label($it['deadline'])) ?></strong></small>
            <?php if ($it['lampiran']): ?>
                <div class="mt-3"><a href="<?= BASE_URL ?>/<?= e($it['lampiran']) ?>" target="_blank" rel="noopener">Unduh / buka lampiran tugas <i class="bi bi-download"></i></a></div>
            <?php endif; ?>

            <hr class="my-3">
            <?php if ($it['jawaban_id']): ?>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <div><strong>Jawaban Anda:</strong> <i class="bi bi-file-earmark-text"></i> <?= e($it['nama_asli']) ?>
                        <small class="text-muted"> · dikirim <?= date('d M Y H:i', strtotime($it['submitted_at'])) ?></small></div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php if ($inline): ?>
                            <button type="button" class="btn btn-sm btn-brown" data-preview-btn="prev-<?= (int)$it['id'] ?>" data-url="<?= e($viewUrl . '&view=1') ?>" data-ext="<?= e($inline) ?>"><i class="bi bi-eye"></i> Lihat file</button>
                        <?php else: ?>
                            <span class="small text-muted align-self-center">Format .<?= e(strtolower(pathinfo($it['nama_asli'], PATHINFO_EXTENSION))) ?> tidak bisa dilihat langsung di browser.</span>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e($viewUrl) ?>"><i class="bi bi-download"></i> Unduh</a>
                    </div>
                </div>
                <?php if ($it['jawaban_catatan']): ?><p class="mb-2 text-muted">Catatan: <?= e($it['jawaban_catatan']) ?></p><?php endif; ?>
                <div id="prev-<?= (int)$it['id'] ?>" class="preview-box" hidden></div>
            <?php endif; ?>

            <?php if ($open): ?>
                <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end mt-1">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token('csrf_jawab')) ?>">
                    <input type="hidden" name="tugas_id" value="<?= (int)$it['id'] ?>">
                    <input type="hidden" name="mapel" value="<?= e($mapelAktif) ?>">
                    <input type="hidden" name="task" value="kirim">
                    <div class="col-md-5">
                        <label class="form-label"><?= $it['jawaban_id'] ? 'Ganti file jawaban (opsional)' : 'File jawaban' ?></label>
                        <input class="form-control custom-input" type="file" name="jawaban" <?= $it['jawaban_id'] ? '' : 'required' ?> accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip,.png,.jpg,.jpeg">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Catatan untuk guru (opsional)</label>
                        <input class="form-control custom-input" type="text" name="catatan" maxlength="255" value="<?= e($it['jawaban_catatan'] ?? '') ?>">
                    </div>
                    <div class="col-md-3 d-grid"><button class="btn btn-brown"><i class="bi bi-send"></i> <?= $it['jawaban_id'] ? 'Simpan Perubahan' : 'Kirim' ?></button></div>
                    <div class="col-12"><small class="text-muted">Format: PDF, DOC/DOCX, PPT/PPTX, XLS/XLSX, TXT, ZIP, PNG, JPG. Maksimal 10 MB.<?= $it['jawaban_id'] ? ' File baru akan menggantikan jawaban lama.' : '' ?></small></div>
                </form>
                <?php if ($it['jawaban_id']): ?>
                <form method="post" class="mt-2" onsubmit="return confirm('Hapus jawaban ini? Anda masih bisa mengirim ulang sebelum deadline.');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token('csrf_jawab')) ?>">
                    <input type="hidden" name="tugas_id" value="<?= (int)$it['id'] ?>"><input type="hidden" name="mapel" value="<?= e($mapelAktif) ?>">
                    <input type="hidden" name="task" value="hapus">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i> Hapus jawaban</button>
                </form>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-secondary mb-0"><i class="bi bi-lock me-2"></i>Pengumpulan ditutup sejak <?= e(deadline_label($it['deadline'])) ?>. Jawaban tidak dapat dikirim atau diubah lagi.</div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endforeach; endif; ?>
<?php endif; ?>

<script>
document.querySelectorAll('[data-preview-btn]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var box = document.getElementById(btn.dataset.previewBtn);
        if (!box) return;
        if (!box.hidden) { box.hidden = true; box.innerHTML = ''; btn.innerHTML = '<i class="bi bi-eye"></i> Lihat file'; return; }
        var ext = btn.dataset.ext, el;
        if (ext === 'png' || ext === 'jpg' || ext === 'jpeg') {
            el = document.createElement('img'); el.alt = 'Jawaban'; el.className = 'preview-img';
        } else {
            el = document.createElement('iframe'); el.title = 'Jawaban'; el.className = 'preview-frame';
        }
        el.src = btn.dataset.url;
        box.innerHTML = ''; box.appendChild(el); box.hidden = false;
        btn.innerHTML = '<i class="bi bi-eye-slash"></i> Tutup';
    });
});
</script>
<?php include __DIR__ . '/../includes/dashboard_page_end.php'; ?>