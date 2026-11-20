<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_crud_helpers.php';
require_role(['admin', 'guru', 'siswa']);

$u = current_user();
$role = $u['role'];
$isAdmin = $role === 'admin';
$self = BASE_URL . '/dashboard/feedback.php';
$error = '';

// ================= POST =================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('csrf_feedback');
    $task = $_POST['task'] ?? '';
    $fid = (int)($_POST['feedback_id'] ?? 0);
    $back = $self . ($isAdmin ? '?' . http_build_query(array_filter(['status' => $_POST['f_status'] ?? '', 'role' => $_POST['f_role'] ?? ''])) : '');

    if ($isAdmin) {
        $cek = $pdo->prepare("SELECT id FROM feedback WHERE id=?");
        $cek->execute([$fid]);
        if (!$cek->fetch()) {
            $error = 'Pesan tidak ditemukan.';
        } elseif ($task === 'balas') {
            $balasan = trim($_POST['balasan'] ?? '');
            if ($balasan === '') $error = 'Isi balasan tidak boleh kosong.';
            elseif (mb_strlen($balasan) > 2000) $error = 'Balasan maksimal 2000 karakter.';
            else {
                $pdo->prepare("UPDATE feedback SET balasan=?, status='dibalas', dibalas_oleh=?, dibalas_at=? WHERE id=?")
                    ->execute([$balasan, $u['id'], date('Y-m-d H:i:s'), $fid]);
                sg_flash_redirect('Balasan berhasil disimpan dan dapat dilihat pengirim.', $back);
            }
        } elseif ($task === 'hapus') {
            $pdo->prepare("DELETE FROM feedback WHERE id=?")->execute([$fid]);
            sg_flash_redirect('Pesan feedback dihapus.', $back);
        }
    } else {
        if ($task === 'kirim') {
            $subjek = trim($_POST['subjek'] ?? '');
            $pesan = trim($_POST['pesan'] ?? '');
            if ($subjek === '' || $pesan === '') $error = 'Subjek dan pesan wajib diisi.';
            elseif (mb_strlen($subjek) > 150) $error = 'Subjek maksimal 150 karakter.';
            elseif (mb_strlen($pesan) > 2000) $error = 'Pesan maksimal 2000 karakter.';
            else {
                $pdo->prepare("INSERT INTO feedback(user_id,subjek,pesan) VALUES(?,?,?)")->execute([$u['id'], $subjek, $pesan]);
                sg_flash_redirect('Pesan terkirim ke Admin. Balasan akan muncul di halaman ini.', $self);
            }
        } elseif ($task === 'hapus') {
            $pdo->prepare("DELETE FROM feedback WHERE id=? AND user_id=?")->execute([$fid, $u['id']]);
            sg_flash_redirect('Pesan dihapus.', $self);
        }
    }
}

// ================= DATA =================
$namaExpr = "COALESCE(g.nama_lengkap, s.nama_lengkap, x.username)";
$items = [];
$fStatus = $_GET['status'] ?? '';
$fRole = $_GET['role'] ?? '';
$count = ['all' => 0, 'baru' => 0, 'dibalas' => 0];

if ($isAdmin) {
    if (!in_array($fStatus, ['baru', 'dibalas'], true)) $fStatus = '';
    if (!in_array($fRole, ['guru', 'siswa'], true)) $fRole = '';
    foreach ($pdo->query("SELECT status, COUNT(*) AS n FROM feedback GROUP BY status")->fetchAll() as $r) { $count[$r['status']] = (int)$r['n']; $count['all'] += (int)$r['n']; }

    $sql = "SELECT f.*, x.role AS pengirim_role, $namaExpr AS pengirim,
                   g.mata_pelajaran, s.kelas AS siswa_kelas
            FROM feedback f
            JOIN users x ON x.id = f.user_id
            LEFT JOIN guru g ON g.user_id = x.id
            LEFT JOIN siswa s ON s.user_id = x.id
            WHERE 1=1";
    $params = [];
    if ($fStatus) { $sql .= " AND f.status=?"; $params[] = $fStatus; }
    if ($fRole) { $sql .= " AND x.role=?"; $params[] = $fRole; }
    $sql .= " ORDER BY (f.status='baru') DESC, f.created_at DESC, f.id DESC";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $items = $st->fetchAll();
} else {
    $st = $pdo->prepare("SELECT * FROM feedback WHERE user_id=? ORDER BY created_at DESC, id DESC");
    $st->execute([$u['id']]);
    $items = $st->fetchAll();
}

$pageTitle = 'Feedback — STEINS GATE';
include __DIR__ . '/../includes/dashboard_page_start.php';
?>
<div class="page-heading"><div><div class="section-kicker">FEEDBACK</div><h1><?= $isAdmin ? 'Feedback <em>Masuk.</em>' : 'Pesan ke <em>Admin.</em>' ?></h1>
    <p><?= $isAdmin ? 'Kritik, saran, dan pertanyaan dari guru dan siswa. Balas langsung dari halaman ini.' : 'Kirim kritik, saran, atau pertanyaan kepada Admin. Balasan Admin tampil di bawah pesan Anda.' ?></p></div></div>

<?php sg_show_flash(); ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<?php if ($isAdmin): ?>
    <div class="class-tabs">
        <a class="<?= $fStatus === '' ? 'active' : '' ?>" href="?role=<?= e($fRole) ?>">Semua<span class="count"><?= $count['all'] ?></span></a>
        <a class="<?= $fStatus === 'baru' ? 'active' : '' ?>" href="?status=baru&role=<?= e($fRole) ?>">Belum dibalas<span class="count"><?= $count['baru'] ?></span></a>
        <a class="<?= $fStatus === 'dibalas' ? 'active' : '' ?>" href="?status=dibalas&role=<?= e($fRole) ?>">Sudah dibalas<span class="count"><?= $count['dibalas'] ?></span></a>
    </div>
    <div class="class-tabs">
        <a class="<?= $fRole === '' ? 'active' : '' ?>" href="?status=<?= e($fStatus) ?>">Semua pengirim</a>
        <a class="<?= $fRole === 'guru' ? 'active' : '' ?>" href="?status=<?= e($fStatus) ?>&role=guru">Guru</a>
        <a class="<?= $fRole === 'siswa' ? 'active' : '' ?>" href="?status=<?= e($fStatus) ?>&role=siswa">Siswa</a>
    </div>

    <?php if (!$items): ?><div class="sg-panel">Belum ada pesan feedback<?= ($fStatus || $fRole) ? ' untuk filter ini' : '' ?>.</div>
    <?php else: foreach ($items as $f): ?>
        <div class="sg-panel mb-3 feedback-card <?= $f['status'] === 'baru' ? 'feedback-new' : '' ?>">
            <div class="d-flex justify-content-between flex-wrap gap-2">
                <div class="section-kicker"><?= e(strtoupper($f['pengirim_role'])) ?> · <?= e($f['pengirim']) ?><?= $f['pengirim_role'] === 'guru' && $f['mata_pelajaran'] ? ' · ' . e($f['mata_pelajaran']) : '' ?><?= $f['pengirim_role'] === 'siswa' && $f['siswa_kelas'] ? ' · Kelas ' . e($f['siswa_kelas']) : '' ?></div>
                <div class="d-flex gap-2 align-items-center">
                    <span class="sg-badge <?= $f['status'] === 'baru' ? 'sg-badge-warn' : '' ?>"><?= $f['status'] === 'baru' ? 'Belum dibalas' : 'Sudah dibalas' ?></span>
                    <small class="text-muted"><?= date('d M Y H:i', strtotime($f['created_at'])) ?></small>
                </div>
            </div>
            <h3 class="mt-2"><?= e($f['subjek']) ?></h3>
            <p class="mb-3"><?= nl2br(e($f['pesan'])) ?></p>

            <form method="post" class="row g-2">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token('csrf_feedback')) ?>">
                <input type="hidden" name="task" value="balas"><input type="hidden" name="feedback_id" value="<?= (int)$f['id'] ?>">
                <input type="hidden" name="f_status" value="<?= e($fStatus) ?>"><input type="hidden" name="f_role" value="<?= e($fRole) ?>">
                <div class="col-12"><label class="form-label"><?= $f['status'] === 'dibalas' ? 'Balasan Anda (dapat diubah)' : 'Balas pesan ini' ?></label>
                    <textarea class="form-control custom-input" name="balasan" rows="3" maxlength="2000" required><?= e($f['balasan'] ?? '') ?></textarea>
                    <?php if ($f['status'] === 'dibalas' && $f['dibalas_at']): ?><small class="text-muted">Dibalas <?= date('d M Y H:i', strtotime($f['dibalas_at'])) ?></small><?php endif; ?></div>
                <div class="col-12"><button class="btn btn-brown"><i class="bi bi-reply"></i> <?= $f['status'] === 'dibalas' ? 'Perbarui Balasan' : 'Kirim Balasan' ?></button></div>
            </form>
            <form method="post" class="mt-2" onsubmit="return confirm('Hapus pesan feedback ini?');">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token('csrf_feedback')) ?>">
                <input type="hidden" name="task" value="hapus"><input type="hidden" name="feedback_id" value="<?= (int)$f['id'] ?>">
                <input type="hidden" name="f_status" value="<?= e($fStatus) ?>"><input type="hidden" name="f_role" value="<?= e($fRole) ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i> Hapus pesan</button>
            </form>
        </div>
    <?php endforeach; endif; ?>

<?php else: ?>
    <div class="sg-panel mb-4">
        <h3 class="mb-3"><i class="bi bi-chat-left-text"></i> Tulis Pesan</h3>
        <form method="post" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token('csrf_feedback')) ?>">
            <input type="hidden" name="task" value="kirim">
            <div class="col-12"><label class="form-label">Subjek</label><input class="form-control custom-input" name="subjek" maxlength="150" required value="<?= e($_POST['subjek'] ?? '') ?>" placeholder="mis. Saran untuk jadwal pelajaran"></div>
            <div class="col-12"><label class="form-label">Pesan</label><textarea class="form-control custom-input" name="pesan" rows="4" maxlength="2000" required><?= e($_POST['pesan'] ?? '') ?></textarea></div>
            <div class="col-12"><button class="btn btn-brown"><i class="bi bi-send"></i> Kirim ke Admin</button></div>
        </form>
    </div>

    <div class="sg-panel">
        <h3 class="mb-3">Pesan Saya</h3>
        <?php if (!$items): ?><p class="muted-copy mb-0">Belum ada pesan yang Anda kirim.</p>
        <?php else: foreach ($items as $f): ?>
            <div class="feedback-card mb-3">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <strong><?= e($f['subjek']) ?></strong>
                    <span class="sg-badge <?= $f['status'] === 'baru' ? 'sg-badge-warn' : '' ?>"><?= $f['status'] === 'baru' ? 'Menunggu balasan' : 'Sudah dibalas' ?></span>
                </div>
                <small class="text-muted"><?= date('d M Y H:i', strtotime($f['created_at'])) ?></small>
                <p class="mt-2 mb-2"><?= nl2br(e($f['pesan'])) ?></p>
                <?php if ($f['status'] === 'dibalas'): ?>
                    <div class="feedback-reply"><div class="section-kicker"><i class="bi bi-reply-fill"></i> BALASAN ADMIN<?= $f['dibalas_at'] ? ' · ' . date('d M Y H:i', strtotime($f['dibalas_at'])) : '' ?></div>
                        <p class="mb-0"><?= nl2br(e($f['balasan'])) ?></p></div>
                <?php endif; ?>
                <form method="post" class="mt-2" onsubmit="return confirm('Hapus pesan ini?');">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token('csrf_feedback')) ?>">
                    <input type="hidden" name="task" value="hapus"><input type="hidden" name="feedback_id" value="<?= (int)$f['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i> Hapus</button>
                </form>
            </div>
        <?php endforeach; endif; ?>
    </div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/dashboard_page_end.php'; ?>