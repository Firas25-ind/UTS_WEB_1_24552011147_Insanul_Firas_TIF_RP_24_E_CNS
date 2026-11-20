<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$u = current_user();
$isAdmin = (($u['role'] ?? '') === 'admin');
if (empty($_SESSION['csrf_siswa'])) {
    $_SESSION['csrf_siswa'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_siswa'];
$error = '';
$success = '';
$q = trim($_GET['q'] ?? '');
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);
$editing = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Permintaan tidak valid. Muat ulang halaman dan coba kembali.');
    }
    $task = $_POST['task'] ?? '';
    try {
        if (!$isAdmin) {
            throw new RuntimeException('Hanya admin yang dapat mengelola data siswa.');
        }

        if ($task === 'delete') {
            $studentId = (int)($_POST['student_id'] ?? 0);
            $pdo->beginTransaction();
            $find = $pdo->prepare("SELECT user_id FROM siswa WHERE id = ? FOR UPDATE");
            $find->execute([$studentId]);
            $record = $find->fetch(PDO::FETCH_ASSOC);
            if (!$record) throw new RuntimeException('Data siswa tidak ditemukan.');
            // Hapus siswa dahulu, kemudian akun yang terhubung bila ada.
            $files = $pdo->prepare("SELECT file_path FROM pengumpulan_tugas WHERE siswa_id = ?");
            $files->execute([$studentId]);
            $jawabanFiles = $files->fetchAll(PDO::FETCH_COLUMN);
            $del = $pdo->prepare("DELETE FROM siswa WHERE id = ?");
            $del->execute([$studentId]);
            if (!empty($record['user_id'])) {
                $delUser = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $delUser->execute([$record['user_id']]);
            }
            $pdo->commit();
            foreach ($jawabanFiles as $jf) delete_upload($jf, 'jawaban');
            $success = 'Data siswa dan akun terkait berhasil dihapus.';
            $action = 'list';
        } elseif ($task === 'setkelas') {
            $sid = (int)($_POST['student_id'] ?? 0);
            $kl = $_POST['kelas'] ?? '';
            if (!in_array($kl, ['', '10', '11', '12'], true)) throw new RuntimeException('Pilihan kelas tidak valid.');
            $pdo->prepare("UPDATE siswa SET kelas=? WHERE id=?")->execute([$kl ?: null, $sid]);
            $success = $kl ? 'Siswa dimasukkan ke kelas ' . $kl . '.' : 'Kelas siswa dikosongkan.';
            $action = 'list';
        } elseif ($task === 'save') {
            $studentId = (int)($_POST['student_id'] ?? 0);
            $nis = trim($_POST['nis'] ?? '');
            $nama = trim($_POST['nama_lengkap'] ?? '');
            $jk = $_POST['jenis_kelamin'] ?? '';
            $wa = trim($_POST['no_wa'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');
            $angkatan = trim($_POST['angkatan'] ?? '');
            $kelas = $_POST['kelas'] ?? '';
            $jabatan = trim($_POST['jabatan'] ?? 'Anggota');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (!$nis || !$nama || !$jk || !$wa || !$alamat || !$angkatan || !$username || !$email) {
                throw new RuntimeException('Semua kolom wajib diisi kecuali password saat mengedit.');
            }
            if (!in_array($kelas, ['', '10', '11', '12'], true)) { throw new RuntimeException('Pilihan kelas tidak valid.'); }
            if (!in_array($jk, ['Laki-laki', 'Perempuan'], true)) {
                throw new RuntimeException('Pilihan jenis kelamin tidak valid.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Format email tidak valid.');
            }
            if ($studentId === 0 && strlen($password) < 8) {
                throw new RuntimeException('Password akun minimal 8 karakter.');
            }
            if ($studentId > 0 && $password !== '' && strlen($password) < 8) {
                throw new RuntimeException('Password baru minimal 8 karakter.');
            }

            $pdo->beginTransaction();
            if ($studentId > 0) {
                $find = $pdo->prepare("SELECT user_id FROM siswa WHERE id = ? FOR UPDATE");
                $find->execute([$studentId]);
                $old = $find->fetch(PDO::FETCH_ASSOC);
                if (!$old) throw new RuntimeException('Data siswa tidak ditemukan.');

                $dup = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id <> ?");
                $dup->execute([$username, $email, (int)$old['user_id']]);
                if ($dup->fetch()) throw new RuntimeException('Username atau email sudah digunakan akun lain.');
                $dupNis = $pdo->prepare("SELECT id FROM siswa WHERE nis = ? AND id <> ?");
                $dupNis->execute([$nis, $studentId]);
                if ($dupNis->fetch()) throw new RuntimeException('NIS sudah digunakan siswa lain.');

                if (!empty($old['user_id'])) {
                    if ($password !== '') {
                        $upUser = $pdo->prepare("UPDATE users SET username=?, email=?, password=? WHERE id=?");
                        $upUser->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $old['user_id']]);
                    } else {
                        $upUser = $pdo->prepare("UPDATE users SET username=?, email=? WHERE id=?");
                        $upUser->execute([$username, $email, $old['user_id']]);
                    }
                    $up = $pdo->prepare("UPDATE siswa SET nis=?, nama_lengkap=?, jenis_kelamin=?, no_wa=?, alamat=?, angkatan=?, kelas=?, jabatan=? WHERE id=?");
                    $up->execute([$nis,$nama,$jk,$wa,$alamat,$angkatan,$kelas?:null,$jabatan,$studentId]);
                } else {
                    // Data siswa lama yang belum memiliki akun: buatkan akun siswa.
                    if ($password === '') throw new RuntimeException('Masukkan password untuk membuat akun siswa ini.');
                    $insUser = $pdo->prepare("INSERT INTO users(username,email,password,role) VALUES(?,?,?,'siswa')");
                    $insUser->execute([$username,$email,password_hash($password,PASSWORD_DEFAULT)]);
                    $newUid = (int)$pdo->lastInsertId();
                    $up = $pdo->prepare("UPDATE siswa SET user_id=?, nis=?, nama_lengkap=?, jenis_kelamin=?, no_wa=?, alamat=?, angkatan=?, kelas=?, jabatan=? WHERE id=?");
                    $up->execute([$newUid,$nis,$nama,$jk,$wa,$alamat,$angkatan,$kelas?:null,$jabatan,$studentId]);
                }
                $success = 'Data siswa berhasil diperbarui.';
            } else {
                $dup = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $dup->execute([$username, $email]);
                if ($dup->fetch()) throw new RuntimeException('Username atau email sudah digunakan.');
                $dupNis = $pdo->prepare("SELECT id FROM siswa WHERE nis = ?");
                $dupNis->execute([$nis]);
                if ($dupNis->fetch()) throw new RuntimeException('NIS sudah terdaftar.');

                $insUser = $pdo->prepare("INSERT INTO users(username,email,password,role) VALUES(?,?,?,'siswa')");
                $insUser->execute([$username,$email,password_hash($password,PASSWORD_DEFAULT)]);
                $newUid = (int)$pdo->lastInsertId();
                $ins = $pdo->prepare("INSERT INTO siswa(user_id,nis,nama_lengkap,jenis_kelamin,no_wa,alamat,angkatan,kelas,jabatan) VALUES(?,?,?,?,?,?,?,?,?)");
                $ins->execute([$newUid,$nis,$nama,$jk,$wa,$alamat,$angkatan,$kelas?:null,$jabatan]);
                $success = 'Siswa dan akun login berhasil ditambahkan.';
            }
            $pdo->commit();
            $action = 'list';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'Terjadi kesalahan saat memproses data. Periksa kembali data yang dimasukkan.';
        if (($task ?? '') === 'save') {
            $action = ((int)($_POST['student_id'] ?? 0) > 0) ? 'edit' : 'add';
            $id = (int)($_POST['student_id'] ?? 0);
        }
    }
}

if ($isAdmin && $action === 'edit' && $id > 0 && !$editing) {
    $st = $pdo->prepare("SELECT s.*, u.username, u.email FROM siswa s LEFT JOIN users u ON u.id=s.user_id WHERE s.id=?");
    $st->execute([$id]);
    $editing = $st->fetch(PDO::FETCH_ASSOC);
    if (!$editing) { $error = 'Data siswa tidak ditemukan.'; $action = 'list'; }
}
if ($isAdmin && $action === 'add') {
    $editing = ['id'=>0,'nis'=>'','nama_lengkap'=>'','jenis_kelamin'=>'','no_wa'=>'','alamat'=>'','angkatan'=>'','kelas'=>'','jabatan'=>'Anggota','username'=>'','email'=>''];
}

$tab = $_GET['k'] ?? 'all';
if (!in_array($tab, ['all', '10', '11', '12', 'none'], true)) $tab = 'all';
$kelasWhere = ['all' => '1=1', 'none' => 's.kelas IS NULL', '10' => "s.kelas='10'", '11' => "s.kelas='11'", '12' => "s.kelas='12'"][$tab];
// Hanya siswa (bukan admin/guru): siswa yang punya akun harus ber-role 'siswa'
$st = $pdo->prepare("SELECT s.id,s.user_id,s.nis,s.nama_lengkap,s.jenis_kelamin,s.no_wa,s.alamat,s.angkatan,s.kelas,s.jabatan,s.foto_profil,u.username,u.email FROM siswa s LEFT JOIN users u ON u.id=s.user_id WHERE (u.id IS NULL OR u.role='siswa') AND $kelasWhere AND (?='' OR s.nama_lengkap LIKE ? OR s.nis LIKE ? OR s.angkatan LIKE ?) ORDER BY s.nama_lengkap");
$like = '%' . $q . '%';
$st->execute([$q,$like,$like,$like]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
$cnt = ['all' => 0, 'none' => 0, '10' => 0, '11' => 0, '12' => 0];
foreach ($pdo->query("SELECT s.kelas, COUNT(*) c FROM siswa s LEFT JOIN users u ON u.id=s.user_id WHERE (u.id IS NULL OR u.role='siswa') GROUP BY s.kelas")->fetchAll(PDO::FETCH_ASSOC) as $c) {
    $cnt['all'] += (int)$c['c'];
    $cnt[$c['kelas'] ?? 'none'] = (int)$c['c'];
}
$pageTitle = 'Data Siswa — STEINS GATE';
include __DIR__ . '/../includes/dashboard_page_start.php';
?>
<div class="page-heading admin-page-heading">
    <div><div class="section-kicker">CLASS DIRECTORY / MANAGEMENT</div><h1>Data <em>Siswa.</em></h1><p>Direktori dan pengelolaan anggota keluarga IPA 2.</p></div>
    <div class="admin-heading-meta"><span class="sg-badge"><?= count($rows) ?> siswa terdaftar</span>
    <?php if ($isAdmin && $action === 'list'): ?><a class="btn btn-brown" href="<?= BASE_URL ?>/dashboard/siswa.php?action=add"><i class="bi bi-plus-lg me-1"></i> Tambah Siswa</a><?php endif; ?></div>
</div>
<?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= e($success) ?></div><?php endif; ?>

<?php if ($isAdmin && in_array($action, ['add','edit'], true) && $editing): ?>
<section class="sg-panel admin-form-panel">
    <div class="admin-form-title"><div><span class="section-kicker"><?= $action === 'add' ? 'NEW MEMBER' : 'UPDATE MEMBER' ?></span><h2><?= $action === 'add' ? 'Tambah Siswa' : 'Ubah Data Siswa' ?></h2><p>Identitas siswa dan akun untuk mengakses STEINS GATE.</p></div><a class="btn btn-outline-brown" href="<?= BASE_URL ?>/dashboard/siswa.php"><i class="bi bi-arrow-left me-1"></i> Kembali</a></div>
    <form method="post" class="row g-3">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="task" value="save"><input type="hidden" name="student_id" value="<?= (int)$editing['id'] ?>">
        <div class="col-md-6"><label class="form-label">Nama Lengkap</label><input class="form-control custom-input" name="nama_lengkap" maxlength="120" required value="<?= e($_POST['nama_lengkap'] ?? $editing['nama_lengkap']) ?>"></div>
        <div class="col-md-6"><label class="form-label">NIS</label><input class="form-control custom-input" name="nis" maxlength="30" required value="<?= e($_POST['nis'] ?? $editing['nis']) ?>"></div>
        <div class="col-md-6"><label class="form-label">Jenis Kelamin</label><select class="form-select custom-input" name="jenis_kelamin" required><option value="">Pilih jenis kelamin</option><?php foreach(['Laki-laki','Perempuan'] as $jk): ?><option value="<?= e($jk) ?>" <?= (($_POST['jenis_kelamin'] ?? $editing['jenis_kelamin']) === $jk) ? 'selected' : '' ?>><?= e($jk) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Nomor WhatsApp</label><input class="form-control custom-input" name="no_wa" maxlength="25" required value="<?= e($_POST['no_wa'] ?? $editing['no_wa']) ?>"></div>
        <div class="col-md-6"><label class="form-label">Angkatan</label><input class="form-control custom-input" name="angkatan" maxlength="20" required value="<?= e($_POST['angkatan'] ?? $editing['angkatan']) ?>"></div>
        <div class="col-md-6"><label class="form-label">Kelas</label><select class="form-select custom-input" name="kelas"><option value="">Belum ditentukan</option><?php foreach(['10','11','12'] as $k): ?><option value="<?= $k ?>" <?= (($_POST['kelas'] ?? ($editing['kelas'] ?? '')) === $k) ? 'selected' : '' ?>>Kelas <?= $k ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Jabatan</label><input class="form-control custom-input" name="jabatan" maxlength="60" value="<?= e($_POST['jabatan'] ?? $editing['jabatan']) ?>"></div>
        <div class="col-12"><label class="form-label">Alamat</label><textarea class="form-control custom-input" name="alamat" rows="3" required><?= e($_POST['alamat'] ?? $editing['alamat']) ?></textarea></div>
        <div class="col-12"><div class="admin-form-divider"><span class="section-kicker">LOGIN CREDENTIALS</span><p>Data akun digunakan siswa untuk login ke website.</p></div></div>
        <div class="col-md-6"><label class="form-label">Username</label><input class="form-control custom-input" name="username" maxlength="50" required value="<?= e($_POST['username'] ?? $editing['username']) ?>"></div>
        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control custom-input" type="email" name="email" maxlength="120" required value="<?= e($_POST['email'] ?? $editing['email']) ?>"></div>
        <div class="col-md-6"><label class="form-label">Password <?= $action === 'edit' ? '(opsional)' : '' ?></label><input class="form-control custom-input" type="password" name="password" <?= $action === 'add' ? 'required minlength="8"' : 'minlength="8"' ?> autocomplete="new-password"><small class="text-muted"><?= $action === 'edit' ? 'Kosongkan jika tidak ingin mengganti password.' : 'Minimal 8 karakter.' ?></small></div>
        <div class="col-12 d-flex flex-wrap gap-2 pt-2"><button class="btn btn-brown" type="submit"><i class="bi bi-check2-circle me-1"></i> Simpan Data</button><a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/dashboard/siswa.php">Batal</a></div>
    </form>
</section>
<?php else: ?>
<section class="sg-panel admin-directory-panel">
    <div class="class-tabs">
        <?php foreach (['all' => 'Semua', '10' => 'Kelas 10', '11' => 'Kelas 11', '12' => 'Kelas 12', 'none' => 'Belum Ada Kelas'] as $tk => $tl): ?>
            <a class="<?= $tab === (string)$tk ? 'active' : '' ?>" href="<?= BASE_URL ?>/dashboard/siswa.php?k=<?= $tk ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>"><?= $tl ?><span class="count"><?= (int)$cnt[(string)$tk] ?></span></a>
        <?php endforeach; ?>
    </div>
    <?php if ($tab === 'none' && $rows): ?><div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Siswa yang baru mendaftar ada di sini. Pilih kelas pada kolom <strong>Kelas</strong> untuk memasukkan mereka ke kelas 10, 11, atau 12.</div><?php endif; ?>
    <form class="admin-search" method="get"><input type="hidden" name="k" value="<?= e($tab) ?>"><div class="admin-search-field"><i class="bi bi-search"></i><input class="form-control custom-input" name="q" value="<?= e($q) ?>" placeholder="Cari nama, NIS, atau angkatan..."></div><button class="btn btn-brown" type="submit">Cari Siswa</button><?php if ($q !== ''): ?><a class="btn btn-outline-brown" href="<?= BASE_URL ?>/dashboard/siswa.php?k=<?= e($tab) ?>">Reset</a><?php endif; ?></form>
    <div class="table-responsive"><table class="table sg-table align-middle admin-student-table"><thead><tr><th>Anggota</th><th>NIS</th><th>Jenis Kelamin</th><th>Angkatan</th><th>Kelas</th><th>Jabatan</th><?php if ($isAdmin): ?><th class="text-end">Aksi</th><?php endif; ?></tr></thead><tbody>
    <?php foreach ($rows as $r): ?><tr>
        <td><div class="student-identity"><?php if (!empty($r['foto_profil'])): ?><img src="<?= BASE_URL ?>/<?= e($r['foto_profil']) ?>" alt="" class="student-mini-avatar"><?php else: ?><span class="student-mini-avatar student-mini-placeholder"><?= e(strtoupper(substr($r['nama_lengkap'],0,1))) ?></span><?php endif; ?><span><strong><?= e($r['nama_lengkap']) ?></strong><small><?= e($r['email'] ?? 'Belum terhubung akun') ?></small></span></div></td>
        <td><?= e($r['nis']) ?></td><td><?= e($r['jenis_kelamin']) ?></td><td><?= e($r['angkatan']) ?></td><td><form method="post" class="kelas-quick"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="task" value="setkelas"><input type="hidden" name="student_id" value="<?= (int)$r['id'] ?>"><select name="kelas" class="form-select form-select-sm custom-input" onchange="this.form.submit()" aria-label="Kelas"><option value="" <?= $r['kelas'] === null ? 'selected' : '' ?>>Belum ada kelas</option><?php foreach (['10','11','12'] as $kk): ?><option value="<?= $kk ?>" <?= $r['kelas'] === $kk ? 'selected' : '' ?>>Kelas <?= $kk ?></option><?php endforeach; ?></select></form></td><td><span class="sg-badge"><?= e($r['jabatan'] ?? 'Anggota') ?></span></td>
        <?php if ($isAdmin): ?><td><div class="admin-row-actions"><a class="btn btn-sm btn-outline-brown" href="<?= BASE_URL ?>/dashboard/siswa.php?action=edit&id=<?= (int)$r['id'] ?>" title="Edit"><i class="bi bi-pencil-square"></i></a><form method="post" onsubmit="return confirm('Hapus data siswa <?= e($r['nama_lengkap']) ?> beserta akun login terkait? Tindakan ini tidak dapat dibatalkan.');"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="task" value="delete"><input type="hidden" name="student_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit" title="Hapus"><i class="bi bi-trash3"></i></button></form></div></td><?php endif; ?>
    </tr><?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="<?= $isAdmin ? 7 : 6 ?>" class="text-center py-5"><i class="bi bi-people fs-2 d-block mb-2 text-muted"></i>Belum ada data siswa yang sesuai.</td></tr><?php endif; ?>
    </tbody></table></div>
    <div class="admin-table-foot"><span>Menampilkan <?= count($rows) ?> data siswa</span><span><?= $isAdmin ? 'ADMINISTRATOR ACCESS' : 'STUDENT DIRECTORY' ?></span></div>
</section>
<?php endif; ?>
<?php include __DIR__ . '/../includes/dashboard_page_end.php'; ?>
