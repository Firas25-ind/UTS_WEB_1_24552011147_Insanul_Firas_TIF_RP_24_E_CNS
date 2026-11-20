<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_crud_helpers.php';
require_once __DIR__ . '/../includes/guru_helpers.php';
require_once __DIR__ . '/../includes/akademik_helpers.php';
require_role('guru');

$u = current_user();
$q = $pdo->prepare("SELECT id, nama_lengkap, mata_pelajaran FROM guru WHERE user_id=?");
$q->execute([$u['id']]);
$g = $q->fetch();
if (!$g) exit('Profil guru tidak ditemukan.');

$myKelas = guru_kelas($pdo, (int)$g['id']);   // kelas yang ditetapkan admin
$self = BASE_URL . '/guru/tugas.php';
$error = '';
$editing = null;

/** Ambil tugas milik guru ini beserta jumlah jawaban. */
function ambil_tugas(PDO $pdo, int $id, int $guruId): ?array {
    $st = $pdo->prepare("SELECT t.*, (SELECT COUNT(*) FROM pengumpulan_tugas p WHERE p.tugas_id=t.id) AS jml_jawaban
                         FROM tugas t WHERE t.id=? AND t.guru_id=?");
    $st->execute([$id, $guruId]);
    return $st->fetch() ?: null;
}

// ---------- Tambah / ubah / hapus ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('csrf_tugas');
    $task = $_POST['task'] ?? 'save';
    $tid = (int)($_POST['tugas_id'] ?? 0);

    if ($task === 'delete') {
        $t = ambil_tugas($pdo, $tid, (int)$g['id']);
        if (!$t) {
            $error = 'Tugas tidak ditemukan.';
        } else {
            $files = $pdo->prepare("SELECT file_path FROM pengumpulan_tugas WHERE tugas_id=?");
            $files->execute([$tid]);
            $jawabanFiles = $files->fetchAll(PDO::FETCH_COLUMN);
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM pengumpulan_tugas WHERE tugas_id=?")->execute([$tid]);
            $pdo->prepare("DELETE FROM tugas WHERE id=? AND guru_id=?")->execute([$tid, $g['id']]);
            $pdo->commit();
            foreach ($jawabanFiles as $f) delete_upload($f, 'jawaban');
            delete_upload($t['lampiran'], 'tugas');
            sg_flash_redirect('Tugas "' . $t['judul'] . '" beserta jawaban siswa berhasil dihapus.', $self);
        }
    } else {
        $judul = trim($_POST['judul'] ?? '');
        $desc = trim($_POST['deskripsi'] ?? '');
        $kelas = $_POST['target_kelas'] ?? '';
        [$deadline, $dlError] = parse_deadline_input($_POST['deadline'] ?? '');
        $hapusLampiran = !empty($_POST['hapus_lampiran']);
        $old = $tid > 0 ? ambil_tugas($pdo, $tid, (int)$g['id']) : null;

        if (!$myKelas) {
            $error = 'Anda belum ditetapkan mengajar di kelas mana pun. Hubungi Admin.';
        } elseif ($tid > 0 && !$old) {
            $error = 'Tugas tidak ditemukan.';
        } elseif (!$judul || !$desc || $kelas === '') {
            $error = 'Lengkapi judul, deskripsi, dan kelas tujuan.';
        } elseif (!in_array($kelas, $myKelas, true)) {
            $error = 'Anda tidak ditetapkan mengajar di kelas ' . $kelas . '.';
        } elseif ($dlError !== '') {
            $error = $dlError;
        } elseif ($old === null && $deadline !== null && strtotime($deadline) <= time()) {
            $error = 'Deadline harus setelah waktu sekarang.';
        } elseif ($old && (int)$old['jml_jawaban'] > 0 && $old['target_kelas'] !== $kelas) {
            $error = 'Kelas tidak dapat diubah karena sudah ada jawaban siswa pada tugas ini.';
        } else {
            $up = handle_upload($_FILES['lampiran'] ?? null, 'tugas', upload_allowed_types('dokumen'), 10 * 1024 * 1024, $error);
            if ($error === '') {
                if ($old) {
                    $lampiran = $old['lampiran'];
                    if ($up) $lampiran = $up['path'];
                    elseif ($hapusLampiran) $lampiran = null;
                    $pdo->prepare("UPDATE tugas SET judul=?, deskripsi=?, target_kelas=?, deadline=?, lampiran=? WHERE id=? AND guru_id=?")
                        ->execute([$judul, $desc, $kelas, $deadline, $lampiran, $tid, $g['id']]);
                    if ($old['lampiran'] && $old['lampiran'] !== $lampiran) delete_upload($old['lampiran'], 'tugas');
                    sg_flash_redirect('Tugas berhasil diperbarui.', $self);
                }
                $pdo->prepare("INSERT INTO tugas(guru_id,judul,deskripsi,target_kelas,lampiran,deadline) VALUES(?,?,?,?,?,?)")
                    ->execute([$g['id'], $judul, $desc, $kelas, $up['path'] ?? null, $deadline]);
                sg_flash_redirect('Tugas berhasil dipublikasikan untuk kelas ' . $kelas . '.', $self);
            }
        }
        // Gagal: tampilkan kembali form dengan isian pengguna
        $editing = ['id' => $tid, 'judul' => $judul, 'deskripsi' => $desc, 'target_kelas' => $kelas,
                    'deadline' => $deadline, 'lampiran' => $old['lampiran'] ?? null, 'jml_jawaban' => (int)($old['jml_jawaban'] ?? 0)];
    }
}

// ---------- Buka form ubah ----------
if ($editing === null && isset($_GET['edit'])) {
    $found = ambil_tugas($pdo, (int)$_GET['edit'], (int)$g['id']);
    if ($found && in_array((string)$found['target_kelas'], $myKelas, true)) $editing = $found;
    else $error = $error ?: 'Tugas tidak ditemukan atau kelasnya tidak lagi Anda ajar.';
}
$isEdit = $editing && (int)$editing['id'] > 0;

$items = [];
if ($myKelas) {
    $in = implode(',', array_fill(0, count($myKelas), '?'));
    $st = $pdo->prepare("SELECT t.*, (SELECT COUNT(*) FROM pengumpulan_tugas p WHERE p.tugas_id=t.id) AS jml_jawaban
                         FROM tugas t WHERE t.guru_id=? AND t.target_kelas IN ($in) ORDER BY t.created_at DESC, t.id DESC");
    $st->execute(array_merge([$g['id']], $myKelas));
    $items = $st->fetchAll();
}

$pageTitle = 'Buat Tugas — STEINS GATE';
include __DIR__ . '/../includes/dashboard_page_start.php';
?>
<div class="page-heading"><div><div class="section-kicker">TEACHER / ASSIGNMENTS</div><h1>Buat <em>Tugas.</em></h1>
    <p><?= $myKelas ? 'Anda mengajar ' . e($g['mata_pelajaran']) . ' di kelas ' . e(implode(', ', $myKelas)) . '.' : 'Kelas ajar Anda belum ditetapkan oleh Admin.' ?></p></div></div>

<?php sg_show_flash(); ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<?php if (!$myKelas): ?>
    <div class="alert alert-warning"><i class="bi bi-info-circle me-2"></i>Anda belum ditetapkan mengajar di kelas mana pun, sehingga belum bisa membuat tugas. Hubungi Admin untuk menetapkan kelas ajar Anda (kelas 10, 11, dan/atau 12).</div>
<?php else: ?>
<div class="sg-panel mb-4" id="form-tugas">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h3 class="mb-0"><?= $isEdit ? 'Ubah Tugas' : 'Tugas Baru' ?></h3>
        <?php if ($isEdit): ?><a class="btn btn-sm btn-outline-secondary" href="<?= $self ?>"><i class="bi bi-x-lg"></i> Batal ubah</a><?php endif; ?>
    </div>
    <form method="post" enctype="multipart/form-data" class="row g-3">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token('csrf_tugas')) ?>">
        <input type="hidden" name="task" value="save">
        <input type="hidden" name="tugas_id" value="<?= $isEdit ? (int)$editing['id'] : 0 ?>">
        <div class="col-md-8"><label class="form-label">Judul Tugas</label><input class="form-control custom-input" name="judul" maxlength="180" required value="<?= e($editing['judul'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Target Kelas</label>
            <select class="form-select custom-input" name="target_kelas" required>
                <option value="">Pilih kelas</option>
                <?php foreach ($myKelas as $k): ?><option value="<?= e($k) ?>" <?= ($editing['target_kelas'] ?? '') === $k ? 'selected' : '' ?>>Kelas <?= e($k) ?></option><?php endforeach; ?>
            </select>
            <?php if ($isEdit && (int)$editing['jml_jawaban'] > 0): ?><small class="text-muted">Kelas terkunci karena sudah ada jawaban siswa.</small><?php endif; ?></div>
        <div class="col-12"><label class="form-label">Deskripsi</label><textarea class="form-control custom-input" name="deskripsi" rows="4" required><?= e($editing['deskripsi'] ?? '') ?></textarea></div>
        <div class="col-md-5"><label class="form-label">Deadline Pengumpulan</label>
            <input class="form-control custom-input" type="datetime-local" name="deadline" value="<?= e(deadline_input_value($editing['deadline'] ?? null)) ?>">
            <small class="text-muted">Setelah deadline, siswa tidak bisa mengirim/mengubah jawaban. Kosongkan jika tanpa batas waktu.</small></div>
        <div class="col-md-7"><label class="form-label">Lampiran (opsional, maks. 10 MB)</label>
            <input class="form-control custom-input" type="file" name="lampiran" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip,.png,.jpg,.jpeg">
            <?php if ($isEdit && !empty($editing['lampiran'])): ?>
                <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="hapus_lampiran" value="1" id="hapusLampiran">
                    <label class="form-check-label" for="hapusLampiran">Hapus lampiran saat ini (<a href="<?= BASE_URL ?>/<?= e($editing['lampiran']) ?>" target="_blank" rel="noopener">lihat</a>)</label></div>
                <small class="text-muted">Memilih file baru akan menggantikan lampiran lama.</small>
            <?php else: ?><small class="text-muted">Tugas tetap tersimpan jika tidak melampirkan file.</small><?php endif; ?></div>
        <div class="col-12"><button class="btn btn-brown"><i class="bi bi-check2-circle me-1"></i><?= $isEdit ? 'Simpan Perubahan' : 'Publikasikan Tugas' ?></button></div>
    </form>
</div>

<div class="sg-panel">
    <h3 class="mb-3">Tugas yang Dibuat</h3>
    <?php if (!$items): ?><p class="muted-copy">Belum ada tugas.</p>
    <?php else: foreach ($items as $it): $open = tugas_terbuka($it['deadline']); ?>
        <div class="recent-item flex-wrap">
            <div style="min-width:0;flex:1 1 280px">
                <strong><?= e($it['judul']) ?></strong>
                <small>Kelas <?= e($it['target_kelas']) ?> · dibuat <?= date('d M Y H:i', strtotime($it['created_at'])) ?></small>
                <small><i class="bi bi-alarm"></i> <?= e(deadline_label($it['deadline'])) ?>
                    <?php if (!$it['deadline']): ?><span class="sg-badge ms-1">Tanpa batas</span>
                    <?php elseif ($open): ?><span class="sg-badge ms-1"><?= e(sisa_waktu($it['deadline'])) ?></span>
                    <?php else: ?><span class="sg-badge sg-badge-warn ms-1">Ditutup</span><?php endif; ?></small>
                <?php if ($it['lampiran']): ?><a href="<?= BASE_URL ?>/<?= e($it['lampiran']) ?>" target="_blank" rel="noopener">Lihat lampiran</a><?php endif; ?>
            </div>
            <div class="d-flex flex-wrap gap-2 ms-auto">
                <a class="btn btn-sm btn-outline-brown" href="<?= BASE_URL ?>/guru/jawaban.php?tugas=<?= (int)$it['id'] ?>"><i class="bi bi-inboxes"></i> Jawaban (<?= (int)$it['jml_jawaban'] ?>)</a>
                <a class="btn btn-sm btn-outline-secondary" href="<?= $self ?>?edit=<?= (int)$it['id'] ?>#form-tugas"><i class="bi bi-pencil-square"></i> Ubah</a>
                <form method="post" class="d-inline" onsubmit="return confirm('Hapus tugas ini beserta <?= (int)$it['jml_jawaban'] ?> jawaban siswa? Tindakan ini tidak dapat dibatalkan.');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token('csrf_tugas')) ?>">
                    <input type="hidden" name="task" value="delete"><input type="hidden" name="tugas_id" value="<?= (int)$it['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i> Hapus</button>
                </form>
            </div>
        </div>
    <?php endforeach; endif; ?>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/dashboard_page_end.php'; ?>