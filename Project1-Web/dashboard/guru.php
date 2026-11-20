<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/guru_helpers.php';
require_role('admin');

$csrf = csrf_token('csrf_guru_admin');
$error = '';
$success = '';
$q = trim($_GET['q'] ?? '');
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('csrf_guru_admin');
    $task = $_POST['task'] ?? '';
    $newPhoto = null;
    try {
        if ($task === 'delete') {
            $gid = (int)($_POST['guru_id'] ?? 0);
            $find = $pdo->prepare("SELECT user_id, foto_profil FROM guru WHERE id=?");
            $find->execute([$gid]);
            $rec = $find->fetch();
            if (!$rec) throw new RuntimeException('Data guru tidak ditemukan.');
            $used = $pdo->prepare("SELECT (SELECT COUNT(*) FROM tugas WHERE guru_id=?) + (SELECT COUNT(*) FROM absensi WHERE guru_id=?)");
            $used->execute([$gid, $gid]);
            if ((int)$used->fetchColumn() > 0) throw new RuntimeException('Guru ini masih memiliki tugas/absensi sehingga tidak dapat dihapus.');
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM guru WHERE id=?")->execute([$gid]);
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$rec['user_id']]);
            $pdo->commit();
            delete_upload($rec['foto_profil'], 'profil');
            $success = 'Data guru dan akun login berhasil dihapus. Jadwal terkait menjadi tanpa guru.';
            $action = 'list';
        } elseif ($task === 'save') {
            $gid = (int)($_POST['guru_id'] ?? 0);
            $nama = trim($_POST['nama_lengkap'] ?? '');
            $mapel = trim($_POST['mata_pelajaran'] ?? '');
            $nip = trim($_POST['nip'] ?? '');
            $jk = $_POST['jenis_kelamin'] ?? '';
            $wa = trim($_POST['no_wa'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $kelasAjar = array_values(array_intersect(guru_kelas_options(), (array)($_POST['kelas_ajar'] ?? [])));

            if (!$nama || !$mapel || !$username || !$email) throw new RuntimeException('Nama, mata pelajaran, username, dan email wajib diisi.');
            if (!$kelasAjar) throw new RuntimeException('Pilih minimal satu kelas yang diajar guru (10, 11, dan/atau 12).');
            if (!in_array($jk, ['', 'Laki-laki', 'Perempuan'], true)) throw new RuntimeException('Jenis kelamin tidak valid.');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Format email tidak valid.');
            if ($gid === 0 && strlen($password) < 8) throw new RuntimeException('Password akun minimal 8 karakter.');
            if ($gid > 0 && $password !== '' && strlen($password) < 8) throw new RuntimeException('Password baru minimal 8 karakter.');

            $upErr = '';
            $up = handle_upload($_FILES['foto_profil'] ?? null, 'profil', upload_allowed_types('gambar'), 2 * 1024 * 1024, $upErr);
            if ($upErr !== '') throw new RuntimeException($upErr);
            $newPhoto = $up['path'] ?? null;

            $pdo->beginTransaction();
            if ($gid > 0) {
                $find = $pdo->prepare("SELECT user_id, foto_profil FROM guru WHERE id=?");
                $find->execute([$gid]);
                $old = $find->fetch();
                if (!$old) throw new RuntimeException('Data guru tidak ditemukan.');
                $dup = $pdo->prepare("SELECT id FROM users WHERE (username=? OR email=?) AND id<>?");
                $dup->execute([$username, $email, (int)$old['user_id']]);
                if ($dup->fetch()) throw new RuntimeException('Username atau email sudah digunakan akun lain.');
                if ($password !== '') {
                    $pdo->prepare("UPDATE users SET username=?, email=?, password=? WHERE id=?")->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $old['user_id']]);
                } else {
                    $pdo->prepare("UPDATE users SET username=?, email=? WHERE id=?")->execute([$username, $email, $old['user_id']]);
                }
                $pdo->prepare("UPDATE guru SET nama_lengkap=?, mata_pelajaran=?, nip=?, jenis_kelamin=?, no_wa=?, alamat=?, foto_profil=? WHERE id=?")
                    ->execute([$nama, $mapel, $nip ?: null, $jk ?: null, $wa ?: null, $alamat ?: null, $newPhoto ?: $old['foto_profil'], $gid]);
                $oldPhoto = $newPhoto ? $old['foto_profil'] : null;
                $kelasGuruId = $gid;
                $success = 'Data guru berhasil diperbarui.';
            } else {
                $dup = $pdo->prepare("SELECT id FROM users WHERE username=? OR email=?");
                $dup->execute([$username, $email]);
                if ($dup->fetch()) throw new RuntimeException('Username atau email sudah digunakan.');
                $pdo->prepare("INSERT INTO users(username,email,password,role) VALUES(?,?,?,'guru')")->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
                $uid = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO guru(user_id,nama_lengkap,mata_pelajaran,nip,jenis_kelamin,no_wa,alamat,foto_profil) VALUES(?,?,?,?,?,?,?,?)")
                    ->execute([$uid, $nama, $mapel, $nip ?: null, $jk ?: null, $wa ?: null, $alamat ?: null, $newPhoto]);
                $kelasGuruId = (int)$pdo->lastInsertId();
                $success = 'Guru dan akun login berhasil ditambahkan.';
            }
            // Simpan kelas ajar (admin memilih satu, dua, atau tiga kelas)
            $pdo->prepare("DELETE FROM guru_kelas WHERE guru_id=?")->execute([$kelasGuruId]);
            $insK = $pdo->prepare("INSERT INTO guru_kelas(guru_id,kelas) VALUES(?,?)");
            foreach ($kelasAjar as $kl) $insK->execute([$kelasGuruId, $kl]);
            $pdo->commit();
            if (!empty($oldPhoto)) delete_upload($oldPhoto, 'profil');
            $action = 'list';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($newPhoto) delete_upload($newPhoto, 'profil');
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'Terjadi kesalahan saat memproses data guru.';
        if (($task ?? '') === 'save') {
            $id = (int)($_POST['guru_id'] ?? 0);
            $action = $id > 0 ? 'edit' : 'add';
        }
    }
}

if ($action === 'edit' && $id > 0) {
    $st = $pdo->prepare("SELECT g.*, u.username, u.email FROM guru g LEFT JOIN users u ON u.id=g.user_id WHERE g.id=?");
    $st->execute([$id]);
    $editing = $st->fetch(PDO::FETCH_ASSOC);
    if (!$editing) { $error = 'Data guru tidak ditemukan.'; $action = 'list'; }
    else $editing['kelas_ajar'] = guru_kelas($pdo, (int)$editing['id']);
}
if ($action === 'add') {
    $editing = ['id' => 0, 'nama_lengkap' => '', 'mata_pelajaran' => '', 'nip' => '', 'jenis_kelamin' => '', 'no_wa' => '', 'alamat' => '', 'username' => '', 'email' => '', 'foto_profil' => null, 'kelas_ajar' => []];
}

$st = $pdo->prepare("SELECT g.*, u.username, u.email FROM guru g LEFT JOIN users u ON u.id=g.user_id WHERE (?='' OR g.nama_lengkap LIKE ? OR g.mata_pelajaran LIKE ? OR g.nip LIKE ?) ORDER BY g.mata_pelajaran, g.nama_lengkap");
$like = '%' . $q . '%';
$st->execute([$q, $like, $like, $like]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
$kelasMap = [];
foreach ($pdo->query("SELECT guru_id, kelas FROM guru_kelas ORDER BY kelas")->fetchAll() as $kr) $kelasMap[(int)$kr['guru_id']][] = $kr['kelas'];
$mapelOptions = $pdo->query("SELECT nama FROM mata_pelajaran UNION SELECT mata_pelajaran FROM guru UNION SELECT mata_pelajaran FROM jadwal_pelajaran ORDER BY 1")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Data Guru — STEINS GATE';
include __DIR__ . '/../includes/dashboard_page_start.php';
$v = fn($k) => e($_POST[$k] ?? ($editing[$k] ?? ''));
?>
<div class="page-heading admin-page-heading">
    <div><div class="section-kicker">TEACHER DIRECTORY / MANAGEMENT</div><h1>Data <em>Guru.</em></h1><p>Direktori dan pengelolaan guru pengajar.</p></div>
    <div class="admin-heading-meta"><span class="sg-badge"><?= count($rows) ?> guru</span>
    <?php if ($action === 'list'): ?><a class="btn btn-brown" href="<?= BASE_URL ?>/dashboard/guru.php?action=add"><i class="bi bi-plus-lg me-1"></i> Tambah Guru</a><?php endif; ?></div>
</div>
<?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= e($success) ?></div><?php endif; ?>

<?php if (in_array($action, ['add', 'edit'], true) && $editing): ?>
<section class="sg-panel admin-form-panel">
    <div class="admin-form-title"><div><span class="section-kicker"><?= $action === 'add' ? 'NEW TEACHER' : 'UPDATE TEACHER' ?></span><h2><?= $action === 'add' ? 'Tambah Guru' : 'Ubah Data Guru' ?></h2><p>Identitas guru dan akun untuk mengakses STEINS GATE.</p></div>
        <a class="btn btn-outline-brown" href="<?= BASE_URL ?>/dashboard/guru.php"><i class="bi bi-arrow-left me-1"></i> Kembali</a></div>
    <form method="post" enctype="multipart/form-data" class="row g-3">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="task" value="save"><input type="hidden" name="guru_id" value="<?= (int)$editing['id'] ?>">
        <div class="col-md-6"><label class="form-label">Nama Lengkap</label><input class="form-control custom-input" name="nama_lengkap" maxlength="120" required value="<?= $v('nama_lengkap') ?>"></div>
        <div class="col-md-6"><label class="form-label">Mata Pelajaran</label><input class="form-control custom-input" name="mata_pelajaran" maxlength="100" list="mapelList" required value="<?= $v('mata_pelajaran') ?>">
            <datalist id="mapelList"><?php foreach ($mapelOptions as $m): ?><option value="<?= e($m) ?>"><?php endforeach; ?></datalist></div>
        <div class="col-12"><label class="form-label">Kelas yang Diajar <span class="text-muted">(pilih satu, dua, atau tiga kelas)</span></label>
            <div class="d-flex flex-wrap gap-3">
            <?php $kelasTerpilih = $_SERVER['REQUEST_METHOD'] === 'POST' ? (array)($_POST['kelas_ajar'] ?? []) : $editing['kelas_ajar'];
            foreach (guru_kelas_options() as $kl): ?>
                <label class="form-check d-flex align-items-center gap-2 mb-0">
                    <input class="form-check-input mt-0" type="checkbox" name="kelas_ajar[]" value="<?= e($kl) ?>" <?= in_array($kl, $kelasTerpilih, true) ? 'checked' : '' ?>>
                    <span>Kelas <?= e($kl) ?></span>
                </label>
            <?php endforeach; ?>
            </div>
            <small class="text-muted">Guru hanya dapat membuat tugas, melihat jawaban, mengisi absensi, dan melihat jadwal di kelas yang dipilih.</small></div>
        <div class="col-md-6"><label class="form-label">NIP</label><input class="form-control custom-input" name="nip" maxlength="30" value="<?= $v('nip') ?>"></div>
        <div class="col-md-6"><label class="form-label">Jenis Kelamin</label><select class="form-select custom-input" name="jenis_kelamin"><option value="">Pilih</option>
            <?php foreach (['Laki-laki', 'Perempuan'] as $jk): ?><option <?= (($_POST['jenis_kelamin'] ?? $editing['jenis_kelamin']) === $jk) ? 'selected' : '' ?>><?= e($jk) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Nomor WhatsApp</label><input class="form-control custom-input" name="no_wa" maxlength="25" value="<?= $v('no_wa') ?>"></div>
        <div class="col-md-6"><label class="form-label">Foto Profil</label><input type="file" class="form-control custom-input" name="foto_profil" accept=".jpg,.jpeg,.png,.webp"><small class="text-muted">JPG/PNG/WEBP maks. 2 MB.</small></div>
        <div class="col-12"><label class="form-label">Alamat</label><textarea class="form-control custom-input" name="alamat" rows="3"><?= $v('alamat') ?></textarea></div>
        <div class="col-12"><div class="admin-form-divider"><span class="section-kicker">LOGIN CREDENTIALS</span><p>Data akun digunakan guru untuk login ke website.</p></div></div>
        <div class="col-md-6"><label class="form-label">Username</label><input class="form-control custom-input" name="username" maxlength="50" required value="<?= $v('username') ?>"></div>
        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control custom-input" type="email" name="email" maxlength="120" required value="<?= $v('email') ?>"></div>
        <div class="col-md-6"><label class="form-label">Password <?= $action === 'edit' ? '(opsional)' : '' ?></label><input class="form-control custom-input" type="password" name="password" autocomplete="new-password" <?= $action === 'add' ? 'required minlength="8"' : 'minlength="8"' ?>><small class="text-muted"><?= $action === 'edit' ? 'Kosongkan jika tidak ingin mengganti password.' : 'Minimal 8 karakter.' ?></small></div>
        <div class="col-12 d-flex flex-wrap gap-2 pt-2"><button class="btn btn-brown" type="submit"><i class="bi bi-check2-circle me-1"></i> Simpan Data</button><a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/dashboard/guru.php">Batal</a></div>
    </form>
</section>
<?php else: ?>
<section class="sg-panel admin-directory-panel">
    <form class="admin-search" method="get"><div class="admin-search-field"><i class="bi bi-search"></i><input class="form-control custom-input" name="q" value="<?= e($q) ?>" placeholder="Cari nama, mata pelajaran, atau NIP..."></div><button class="btn btn-brown" type="submit">Cari Guru</button><?php if ($q !== ''): ?><a class="btn btn-outline-brown" href="<?= BASE_URL ?>/dashboard/guru.php">Reset</a><?php endif; ?></form>
    <div class="table-responsive"><table class="table sg-table align-middle admin-student-table"><thead><tr><th>Guru</th><th>NIP</th><th>Mata Pelajaran</th><th>Kelas Ajar</th><th>Jenis Kelamin</th><th>WhatsApp</th><th class="text-end">Aksi</th></tr></thead><tbody>
    <?php foreach ($rows as $r): $f = photo_url($r['foto_profil']); ?><tr>
        <td><div class="student-identity"><?php if ($f): ?><img src="<?= e($f) ?>" alt="" class="student-mini-avatar"><?php else: ?><span class="student-mini-avatar student-mini-placeholder"><?= e(strtoupper(substr($r['nama_lengkap'], 0, 1))) ?></span><?php endif; ?><span><strong><?= e($r['nama_lengkap']) ?></strong><small><?= e($r['email'] ?? '') ?></small></span></div></td>
        <td><?= e($r['nip'] ?: '-') ?></td><td><span class="sg-badge"><?= e($r['mata_pelajaran']) ?></span></td><td><?php $kk = $kelasMap[(int)$r['id']] ?? []; echo $kk ? e('Kelas ' . implode(', ', $kk)) : '<span class="text-muted">Belum ditetapkan</span>'; ?></td><td><?= e($r['jenis_kelamin'] ?: '-') ?></td><td><?= e($r['no_wa'] ?: '-') ?></td>
        <td><div class="admin-row-actions"><a class="btn btn-sm btn-outline-brown" href="<?= BASE_URL ?>/dashboard/guru.php?action=edit&id=<?= (int)$r['id'] ?>" title="Edit"><i class="bi bi-pencil-square"></i></a>
            <form method="post" onsubmit="return confirm('Hapus guru <?= e($r['nama_lengkap']) ?> beserta akun login? Tindakan ini tidak dapat dibatalkan.');"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="task" value="delete"><input type="hidden" name="guru_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit" title="Hapus"><i class="bi bi-trash3"></i></button></form></div></td>
    </tr><?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="text-center py-5"><i class="bi bi-person-workspace fs-2 d-block mb-2 text-muted"></i>Belum ada data guru yang sesuai.</td></tr><?php endif; ?>
    </tbody></table></div>
    <div class="admin-table-foot"><span>Menampilkan <?= count($rows) ?> guru</span><span>ADMINISTRATOR ACCESS</span></div>
</section>
<?php endif; ?>
<?php include __DIR__ . '/../includes/dashboard_page_end.php'; ?>