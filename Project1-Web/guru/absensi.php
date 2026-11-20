<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_crud_helpers.php';
require_once __DIR__ . '/../includes/guru_helpers.php';
require_once __DIR__ . '/../includes/akademik_helpers.php';
require_role('guru');

$u = current_user();
$q = $pdo->prepare("SELECT id, mata_pelajaran FROM guru WHERE user_id=?");
$q->execute([$u['id']]);
$guru = $q->fetch();
if (!$guru) exit('Profil guru tidak ditemukan.');
$guruId = (int)$guru['id'];

$myKelas = guru_kelas($pdo, $guruId);   // kelas yang ditetapkan admin
$statusList = ['Hadir', 'Izin', 'Sakit', 'Alpa'];
$self = BASE_URL . '/guru/absensi.php';
$error = '';

/** Folder absensi milik guru ini dan di kelas yang masih ditetapkan untuknya. */
function ambil_sesi(PDO $pdo, int $id, int $guruId, array $myKelas): ?array {
    $st = $pdo->prepare("SELECT * FROM absensi_sesi WHERE id=? AND guru_id=?");
    $st->execute([$id, $guruId]);
    $row = $st->fetch();
    return ($row && in_array((string)$row['kelas'], $myKelas, true)) ? $row : null;
}
function tanggal_valid(string $t): bool {
    $d = DateTime::createFromFormat('Y-m-d', $t);
    return $d && $d->format('Y-m-d') === $t;
}

// ---------- Aksi (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('csrf_absen');
    $task = $_POST['task'] ?? '';
    $sid = (int)($_POST['sesi_id'] ?? 0);

    if ($task === 'buat') {
        $kelas = $_POST['kelas'] ?? '';
        $tanggal = $_POST['tanggal'] ?? '';
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        if (!in_array($kelas, $myKelas, true)) $error = 'Anda tidak ditetapkan mengajar di kelas tersebut.';
        elseif (!tanggal_valid($tanggal)) $error = 'Tanggal tidak valid.';
        elseif ($deskripsi === '') $error = 'Deskripsi folder wajib diisi (mis. "Pertemuan 3 – Bab Limit").';
        elseif (mb_strlen($deskripsi) > 255) $error = 'Deskripsi maksimal 255 karakter.';
        else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("INSERT INTO absensi_sesi(guru_id,kelas,tanggal,hari,deskripsi) VALUES(?,?,?,?,?)")
                    ->execute([$guruId, $kelas, $tanggal, hari_indonesia($tanggal), $deskripsi]);
                $newId = (int)$pdo->lastInsertId();
                $stu = $pdo->prepare("SELECT id FROM siswa WHERE kelas=?");
                $stu->execute([$kelas]);
                $ins = $pdo->prepare("INSERT INTO absensi(sesi_id,guru_id,siswa_id,tanggal,kelas,status) VALUES(?,?,?,?,?,'Hadir')");
                foreach ($stu->fetchAll(PDO::FETCH_COLUMN) as $siswaId) $ins->execute([$newId, $guruId, $siswaId, $tanggal, $kelas]);
                $pdo->commit();
                sg_flash_redirect('Folder absensi dibuat. Silakan ubah status siswa bila ada yang tidak hadir.', $self . '?sesi=' . $newId);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Gagal membuat folder absensi. Pastikan migrasi database sudah dijalankan.';
            }
        }
    } elseif ($task === 'ubah_folder') {
        $sesi = ambil_sesi($pdo, $sid, $guruId, $myKelas);
        $tanggal = $_POST['tanggal'] ?? '';
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        if (!$sesi) $error = 'Folder absensi tidak ditemukan.';
        elseif (!tanggal_valid($tanggal)) $error = 'Tanggal tidak valid.';
        elseif ($deskripsi === '' || mb_strlen($deskripsi) > 255) $error = 'Deskripsi wajib diisi (maksimal 255 karakter).';
        else {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE absensi_sesi SET tanggal=?, hari=?, deskripsi=? WHERE id=?")->execute([$tanggal, hari_indonesia($tanggal), $deskripsi, $sid]);
            $pdo->prepare("UPDATE absensi SET tanggal=? WHERE sesi_id=?")->execute([$tanggal, $sid]);
            $pdo->commit();
            sg_flash_redirect('Folder absensi diperbarui.', $self . '?sesi=' . $sid);
        }
        $_GET['edit'] = $sid;
    } elseif ($task === 'hapus_folder') {
        $sesi = ambil_sesi($pdo, $sid, $guruId, $myKelas);
        if (!$sesi) $error = 'Folder absensi tidak ditemukan.';
        else {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM absensi WHERE sesi_id=?")->execute([$sid]);
            $pdo->prepare("DELETE FROM absensi_sesi WHERE id=?")->execute([$sid]);
            $pdo->commit();
            sg_flash_redirect('Folder absensi "' . $sesi['deskripsi'] . '" beserta isinya dihapus.', $self);
        }
    } elseif ($task === 'simpan_absen') {
        $sesi = ambil_sesi($pdo, $sid, $guruId, $myKelas);
        if (!$sesi) $error = 'Folder absensi tidak ditemukan.';
        else {
            $pdo->beginTransaction();
            try {
                $stu = $pdo->prepare("SELECT id FROM siswa WHERE kelas=?");
                $stu->execute([$sesi['kelas']]);
                $valid = $stu->fetchAll(PDO::FETCH_COLUMN);
                $ex = $pdo->prepare("SELECT id, siswa_id FROM absensi WHERE sesi_id=?");
                $ex->execute([$sid]);
                $exist = [];
                foreach ($ex->fetchAll() as $r) $exist[(int)$r['siswa_id']] = (int)$r['id'];
                $upd = $pdo->prepare("UPDATE absensi SET status=?, keterangan=? WHERE id=?");
                $ins = $pdo->prepare("INSERT INTO absensi(sesi_id,guru_id,siswa_id,tanggal,kelas,status,keterangan) VALUES(?,?,?,?,?,?,?)");
                foreach ($valid as $siswaId) {
                    $status = $_POST['status'][$siswaId] ?? 'Hadir';
                    if (!in_array($status, $statusList, true)) $status = 'Hadir';
                    $ket = mb_substr(trim($_POST['keterangan'][$siswaId] ?? ''), 0, 255) ?: null;
                    if (isset($exist[(int)$siswaId])) $upd->execute([$status, $ket, $exist[(int)$siswaId]]);
                    else $ins->execute([$sid, $guruId, $siswaId, $sesi['tanggal'], $sesi['kelas'], $status, $ket]);
                }
                $pdo->commit();
                sg_flash_redirect('Absensi berhasil disimpan.', $self . '?sesi=' . $sid);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Gagal menyimpan absensi.';
            }
        }
        $_GET['sesi'] = $sid;
    }
}

// ---------- Tampilan ----------
$view = 'list';
$sesi = null;
if (isset($_GET['sesi'])) {
    $sesi = ambil_sesi($pdo, (int)$_GET['sesi'], $guruId, $myKelas);
    if ($sesi) $view = 'folder';
    elseif ($error === '') $error = 'Folder absensi tidak ditemukan atau kelasnya tidak lagi Anda ajar.';
}
$editSesi = null;
if ($view === 'list' && isset($_GET['edit'])) {
    $editSesi = ambil_sesi($pdo, (int)$_GET['edit'], $guruId, $myKelas);
}

$students = [];
$ringkas = array_fill_keys($statusList, 0);
if ($view === 'folder') {
    $st = $pdo->prepare("SELECT s.id, s.nis, s.nama_lengkap, a.status, a.keterangan
                         FROM siswa s LEFT JOIN absensi a ON a.siswa_id=s.id AND a.sesi_id=?
                         WHERE s.kelas=? ORDER BY s.nama_lengkap");
    $st->execute([(int)$sesi['id'], $sesi['kelas']]);
    $students = $st->fetchAll();
    foreach ($students as $s) if (isset($ringkas[$s['status']])) $ringkas[$s['status']]++;
}

$folders = [];
$tahunList = [(int)date('Y')];
$fKelas = $_GET['kelas'] ?? 'all';
$fBulan = (int)($_GET['bulan'] ?? 0);
$fTahun = (int)($_GET['tahun'] ?? 0);
if ($view === 'list' && $myKelas) {
    if ($fKelas !== 'all' && !in_array($fKelas, $myKelas, true)) $fKelas = 'all';
    if ($fBulan < 0 || $fBulan > 12) $fBulan = 0;

    $yr = $pdo->prepare("SELECT DISTINCT SUBSTR(tanggal,1,4) FROM absensi_sesi WHERE guru_id=?");
    $yr->execute([$guruId]);
    foreach ($yr->fetchAll(PDO::FETCH_COLUMN) as $y) $tahunList[] = (int)$y;
    $tahunList = array_values(array_unique($tahunList));
    rsort($tahunList);
    if ($fTahun && !in_array($fTahun, $tahunList, true)) $fTahun = 0;

    $kelasFilter = $fKelas === 'all' ? $myKelas : [$fKelas];
    $in = implode(',', array_fill(0, count($kelasFilter), '?'));
    $sql = "SELECT s.*,
              (SELECT COUNT(*) FROM absensi a WHERE a.sesi_id=s.id AND a.status='Hadir') AS n_hadir,
              (SELECT COUNT(*) FROM absensi a WHERE a.sesi_id=s.id AND a.status='Izin') AS n_izin,
              (SELECT COUNT(*) FROM absensi a WHERE a.sesi_id=s.id AND a.status='Sakit') AS n_sakit,
              (SELECT COUNT(*) FROM absensi a WHERE a.sesi_id=s.id AND a.status='Alpa') AS n_alpa
            FROM absensi_sesi s WHERE s.guru_id=? AND s.kelas IN ($in)";
    $params = array_merge([$guruId], $kelasFilter);
    if ($fTahun) {
        if ($fBulan) { $from = sprintf('%04d-%02d-01', $fTahun, $fBulan); $to = date('Y-m-d', strtotime($from . ' +1 month')); }
        else { $from = sprintf('%04d-01-01', $fTahun); $to = sprintf('%04d-01-01', $fTahun + 1); }
        $sql .= " AND s.tanggal >= ? AND s.tanggal < ?";
        array_push($params, $from, $to);
    }
    $sql .= " ORDER BY s.tanggal DESC, s.id DESC";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();
    if ($fBulan && !$fTahun) $rows = array_values(array_filter($rows, fn($r) => (int)substr($r['tanggal'], 5, 2) === $fBulan));
    foreach ($rows as $r) $folders[date('Y-m', strtotime($r['tanggal']))][] = $r;
}
$bulanNama = bulan_indonesia();

$pageTitle = 'Absensi Kehadiran — STEINS GATE';
include __DIR__ . '/../includes/dashboard_page_start.php';
?>
<div class="page-heading"><div><div class="section-kicker">TEACHER / ATTENDANCE</div><h1>Absensi <em>Kehadiran.</em></h1>
    <p><?= $myKelas ? 'Satu folder = satu pertemuan. Buat folder tanggal, lalu isi kehadiran siswa kelas ' . e(implode(', ', $myKelas)) . '.' : 'Kelas ajar Anda belum ditetapkan oleh Admin.' ?></p></div></div>

<?php sg_show_flash(); ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<?php if (!$myKelas): ?>
    <div class="alert alert-warning"><i class="bi bi-info-circle me-2"></i>Anda belum ditetapkan mengajar di kelas mana pun, sehingga belum bisa mengisi absensi. Hubungi Admin untuk menetapkan kelas ajar Anda.</div>

<?php elseif ($view === 'folder'): ?>
<div class="sg-panel mb-3">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <div class="section-kicker"><i class="bi bi-folder2-open"></i> FOLDER ABSENSI · KELAS <?= e($sesi['kelas']) ?></div>
            <h2 class="mb-1"><?= e($sesi['deskripsi']) ?></h2>
            <p class="mb-0 muted-copy"><?= e(tanggal_panjang($sesi['tanggal'])) ?> · <?= e($guru['mata_pelajaran']) ?></p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="<?= $self ?>"><i class="bi bi-arrow-left"></i> Semua folder</a>
            <a class="btn btn-sm btn-outline-brown" href="<?= $self ?>?edit=<?= (int)$sesi['id'] ?>"><i class="bi bi-pencil-square"></i> Ubah folder</a>
        </div>
    </div>
    <div class="absen-summary mt-3">
        <?php foreach ($statusList as $s): ?><span class="sg-badge"><?= e($s) ?>: <?= (int)$ringkas[$s] ?></span><?php endforeach; ?>
        <span class="sg-badge"><?= count($students) ?> siswa</span>
    </div>
</div>

<div class="sg-panel">
    <?php if (!$students): ?>
        <p class="muted-copy mb-0">Belum ada siswa yang ditetapkan ke kelas ini oleh Admin.</p>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token('csrf_absen')) ?>">
            <input type="hidden" name="task" value="simpan_absen">
            <input type="hidden" name="sesi_id" value="<?= (int)$sesi['id'] ?>">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                <button type="button" class="btn btn-sm btn-outline-brown" id="semuaHadir"><i class="bi bi-check2-all"></i> Setel semua Hadir</button>
                <small class="text-muted">Ubah status siswa yang berbeda, lalu klik Simpan Absensi.</small>
            </div>
            <div class="table-responsive"><table class="table align-middle">
                <thead><tr><th>Nama Siswa</th><th>NIS</th><th style="min-width:140px">Status</th><th>Keterangan</th></tr></thead>
                <tbody>
                <?php foreach ($students as $s): ?>
                    <tr>
                        <td><?= e($s['nama_lengkap']) ?></td>
                        <td><?= e($s['nis']) ?></td>
                        <td><select class="form-select custom-input status-select" name="status[<?= (int)$s['id'] ?>]">
                            <?php foreach ($statusList as $v): ?><option <?= ($s['status'] ?? 'Hadir') === $v ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
                        </select></td>
                        <td><input class="form-control custom-input" name="keterangan[<?= (int)$s['id'] ?>]" maxlength="255" value="<?= e($s['keterangan'] ?? '') ?>"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <button class="btn btn-brown"><i class="bi bi-save me-1"></i> Simpan Absensi</button>
        </form>
        <script>document.getElementById('semuaHadir').addEventListener('click',function(){document.querySelectorAll('.status-select').forEach(function(s){s.value='Hadir';});});</script>
    <?php endif; ?>
</div>

<?php else: /* ---------- DAFTAR FOLDER ---------- */ ?>
<div class="sg-panel mb-4" id="form-folder">
    <?php if ($editSesi): ?>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h3 class="mb-0">Ubah Folder Absensi · Kelas <?= e($editSesi['kelas']) ?></h3>
            <a class="btn btn-sm btn-outline-secondary" href="<?= $self ?>"><i class="bi bi-x-lg"></i> Batal</a>
        </div>
        <form method="post" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token('csrf_absen')) ?>">
            <input type="hidden" name="task" value="ubah_folder"><input type="hidden" name="sesi_id" value="<?= (int)$editSesi['id'] ?>">
            <div class="col-md-4"><label class="form-label">Tanggal</label><input class="form-control custom-input" type="date" name="tanggal" required value="<?= e($_POST['tanggal'] ?? $editSesi['tanggal']) ?>">
                <small class="text-muted">Hari (<?= e($editSesi['hari']) ?>) mengikuti tanggal.</small></div>
            <div class="col-md-8"><label class="form-label">Deskripsi</label><input class="form-control custom-input" name="deskripsi" maxlength="255" required value="<?= e($_POST['deskripsi'] ?? $editSesi['deskripsi']) ?>"></div>
            <div class="col-12"><button class="btn btn-brown"><i class="bi bi-check2-circle me-1"></i> Simpan Perubahan</button>
                <small class="text-muted ms-2">Kelas folder tidak dapat diubah.</small></div>
        </form>
    <?php else: ?>
        <h3 class="mb-3"><i class="bi bi-folder-plus"></i> Buat Folder Absensi</h3>
        <form method="post" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token('csrf_absen')) ?>">
            <input type="hidden" name="task" value="buat">
            <div class="col-md-3"><label class="form-label">Kelas</label>
                <select class="form-select custom-input" name="kelas" required>
                    <?php foreach ($myKelas as $k): ?><option value="<?= e($k) ?>" <?= ($_POST['kelas'] ?? '') === $k ? 'selected' : '' ?>>Kelas <?= e($k) ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-3"><label class="form-label">Tanggal</label><input class="form-control custom-input" type="date" name="tanggal" required value="<?= e($_POST['tanggal'] ?? date('Y-m-d')) ?>"></div>
            <div class="col-md-6"><label class="form-label">Deskripsi</label><input class="form-control custom-input" name="deskripsi" maxlength="255" required placeholder="mis. Pertemuan 3 – Bab Limit" value="<?= e($_POST['deskripsi'] ?? '') ?>"></div>
            <div class="col-12"><button class="btn btn-brown"><i class="bi bi-folder-plus me-1"></i> Buat Folder & Isi Absensi</button>
                <small class="text-muted ms-2">Hari terisi otomatis dari tanggal. Semua siswa awalnya Hadir.</small></div>
        </form>
    <?php endif; ?>
</div>

<div class="sg-panel">
    <form method="get" class="row g-2 align-items-end mb-3">
        <div class="col-6 col-md-3"><label class="form-label">Kelas</label>
            <select class="form-select custom-input" name="kelas" onchange="this.form.submit()">
                <option value="all">Semua kelas saya</option>
                <?php foreach ($myKelas as $k): ?><option value="<?= e($k) ?>" <?= $fKelas === $k ? 'selected' : '' ?>>Kelas <?= e($k) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-6 col-md-3"><label class="form-label">Bulan</label>
            <select class="form-select custom-input" name="bulan" onchange="this.form.submit()">
                <option value="0">Semua bulan</option>
                <?php foreach ($bulanNama as $n => $nm): ?><option value="<?= $n ?>" <?= $fBulan === $n ? 'selected' : '' ?>><?= e($nm) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-6 col-md-3"><label class="form-label">Tahun</label>
            <select class="form-select custom-input" name="tahun" onchange="this.form.submit()">
                <option value="0">Semua tahun</option>
                <?php foreach ($tahunList as $th): ?><option value="<?= $th ?>" <?= $fTahun === $th ? 'selected' : '' ?>><?= $th ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-6 col-md-3 d-flex gap-2"><button class="btn btn-outline-brown">Terapkan</button><a class="btn btn-outline-secondary" href="<?= $self ?>">Reset</a></div>
    </form>

    <?php if (!$folders): ?>
        <p class="muted-copy mb-0">Belum ada folder absensi<?= ($fKelas !== 'all' || $fBulan || $fTahun) ? ' untuk filter ini' : '' ?>. Buat folder pertama di atas.</p>
    <?php else: foreach ($folders as $ym => $list): ?>
        <h4 class="folder-month"><?= e($bulanNama[(int)substr($ym, 5, 2)] . ' ' . substr($ym, 0, 4)) ?> <small>· <?= count($list) ?> folder</small></h4>
        <div class="folder-grid">
        <?php foreach ($list as $f): ?>
            <div class="folder-card">
                <a class="folder-main" href="<?= $self ?>?sesi=<?= (int)$f['id'] ?>">
                    <i class="bi bi-folder-fill folder-icon"></i>
                    <span>
                        <strong><?= e($f['deskripsi']) ?></strong>
                        <small><?= e($f['hari']) ?>, <?= date('d M Y', strtotime($f['tanggal'])) ?> · Kelas <?= e($f['kelas']) ?></small>
                        <small>H <?= (int)$f['n_hadir'] ?> · I <?= (int)$f['n_izin'] ?> · S <?= (int)$f['n_sakit'] ?> · A <?= (int)$f['n_alpa'] ?></small>
                    </span>
                </a>
                <div class="folder-actions">
                    <a class="btn btn-sm btn-brown" href="<?= $self ?>?sesi=<?= (int)$f['id'] ?>"><i class="bi bi-folder2-open"></i> Buka</a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= $self ?>?edit=<?= (int)$f['id'] ?>#form-folder" title="Ubah"><i class="bi bi-pencil-square"></i></a>
                    <form method="post" class="d-inline" onsubmit="return confirm('Hapus folder ini beserta seluruh isi absensinya?');">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token('csrf_absen')) ?>">
                        <input type="hidden" name="task" value="hapus_folder"><input type="hidden" name="sesi_id" value="<?= (int)$f['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash3"></i></button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endforeach; endif; ?>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/dashboard_page_end.php'; ?>