<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_crud_helpers.php';
require_once __DIR__ . '/../includes/guru_helpers.php';
require_role(['admin', 'guru', 'siswa']);

$u = current_user();
$role = $u['role'];
$isAdmin = $role === 'admin';
$days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$kelasList = ['10', '11', '12'];
$errors = [];

// ---------- Data guru (untuk pemilihan guru & penamaan) ----------
$gurus = $pdo->query("SELECT id, nama_lengkap, mata_pelajaran FROM guru ORDER BY mata_pelajaran, nama_lengkap")->fetchAll();
$gurusById = [];
$byMapel = [];
foreach ($gurus as $g) {
    $gurusById[(int)$g['id']] = $g;
    $byMapel[mb_strtolower(trim($g['mata_pelajaran']))][] = $g;
}
/** Nama guru untuk satu baris jadwal: guru terpilih, atau satu-satunya guru mapel itu, atau kosong. */
function jadwal_guru_name(array $row, array $gurusById, array $byMapel): string {
    if (!empty($row['guru_id']) && isset($gurusById[(int)$row['guru_id']])) return $gurusById[(int)$row['guru_id']]['nama_lengkap'];
    $c = $byMapel[mb_strtolower(trim($row['mata_pelajaran']))] ?? [];
    return count($c) === 1 ? $c[0]['nama_lengkap'] : '';
}

// ---------- Konteks per peran ----------
$me = null; $siswaKelas = null;
if ($role === 'guru') {
    $q = $pdo->prepare("SELECT id, mata_pelajaran FROM guru WHERE user_id=?");
    $q->execute([$u['id']]);
    $me = $q->fetch();
    $myKelas = $me ? guru_kelas($pdo, (int)$me['id']) : [];   // kelas yang ditetapkan admin
} elseif ($role === 'siswa') {
    $q = $pdo->prepare("SELECT kelas FROM siswa WHERE user_id=?");
    $q->execute([$u['id']]);
    $siswaKelas = $q->fetchColumn() ?: null;
}

if ($role === 'siswa') {
    $k = $siswaKelas;                       // siswa hanya melihat kelasnya sendiri
} elseif ($role === 'guru') {
    $defaultK = count($myKelas) === 1 ? $myKelas[0] : 'all';
    $k = $_GET['k'] ?? $defaultK;
    if (!in_array($k, array_merge(['all'], $myKelas), true)) $k = $defaultK;
} else {
    $k = $_GET['k'] ?? ($_POST['k'] ?? '10');
    if (!in_array($k, $kelasList, true)) $k = '10';
}

// ---------- CRUD (admin) ----------
$edit = null;
if ($isAdmin && isset($_GET['edit'])) {
    $q = $pdo->prepare('SELECT * FROM jadwal_pelajaran WHERE id=?');
    $q->execute([(int)$_GET['edit']]);
    $edit = $q->fetch(PDO::FETCH_ASSOC);
    if ($edit) $k = $edit['kelas'];
}
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    sg_verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM jadwal_pelajaran WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
        sg_flash_redirect('Jadwal berhasil dihapus.', BASE_URL . '/dashboard/jadwal.php?k=' . urlencode($_POST['k'] ?? $k));
    }
    $kelas = $_POST['kelas'] ?? '';
    $hari = $_POST['hari'] ?? '';
    $mulai = $_POST['jam_mulai'] ?? '';
    $selesai = $_POST['jam_selesai'] ?? '';
    $mapel = trim($_POST['mata_pelajaran'] ?? '');
    $gid = (int)($_POST['guru_id'] ?? 0);
    $ruangan = trim($_POST['ruangan'] ?? '') ?: 'Kelas IPA 2';

    if (!in_array($kelas, $kelasList, true) || !in_array($hari, $days, true) || !$mulai || !$selesai || $selesai <= $mulai || $mapel === '') {
        $errors[] = 'Lengkapi kelas, hari, mata pelajaran, dan pastikan jam selesai setelah jam mulai.';
    } else {
        $cands = $byMapel[mb_strtolower($mapel)] ?? [];
        if ($gid > 0) {
            if (!isset($gurusById[$gid]) || mb_strtolower(trim($gurusById[$gid]['mata_pelajaran'])) !== mb_strtolower($mapel)) {
                $errors[] = 'Guru yang dipilih tidak mengajar mata pelajaran tersebut.';
            }
        } elseif (count($cands) === 1) {
            $gid = (int)$cands[0]['id'];
        } elseif (count($cands) > 1) {
            $errors[] = 'Ada ' . count($cands) . ' guru untuk mata pelajaran ini. Pilih salah satu guru.';
        }
    }
    if (!$errors) {
        $namaGuru = $gid > 0 ? $gurusById[$gid]['nama_lengkap'] : '';
        $params = [$kelas, $hari, $mulai, $selesai, $mapel, $gid ?: null, $namaGuru, $ruangan];
        if ($action === 'update') {
            $pdo->prepare('UPDATE jadwal_pelajaran SET kelas=?,hari=?,jam_mulai=?,jam_selesai=?,mata_pelajaran=?,guru_id=?,nama_guru=?,ruangan=? WHERE id=?')
                ->execute(array_merge($params, [(int)($_POST['id'] ?? 0)]));
        } else {
            $pdo->prepare('INSERT INTO jadwal_pelajaran(kelas,hari,jam_mulai,jam_selesai,mata_pelajaran,guru_id,nama_guru,ruangan) VALUES(?,?,?,?,?,?,?,?)')->execute($params);
        }
        sg_flash_redirect('Jadwal berhasil disimpan.', BASE_URL . '/dashboard/jadwal.php?k=' . urlencode($kelas));
    }
    $edit = ['id' => (int)($_POST['id'] ?? 0), 'kelas' => $kelas, 'hari' => $hari, 'jam_mulai' => $mulai, 'jam_selesai' => $selesai,
             'mata_pelajaran' => $mapel, 'guru_id' => $gid, 'ruangan' => $ruangan];
}

// ---------- Ambil jadwal ----------
$rows = [];
if ($k !== null) {
    if ($role === 'guru' && !$myKelas) {
        $rows = [];                          // guru belum ditetapkan ke kelas mana pun
    } elseif ($k === 'all' && $role === 'guru') {
        $in = implode(',', array_fill(0, count($myKelas), '?'));
        $st = $pdo->prepare("SELECT * FROM jadwal_pelajaran WHERE kelas IN ($in) ORDER BY kelas, FIELD(hari,'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'), jam_mulai");
        $st->execute($myKelas);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($k === 'all') {
        $rows = $pdo->query("SELECT * FROM jadwal_pelajaran ORDER BY kelas, FIELD(hari,'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'), jam_mulai")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $st = $pdo->prepare("SELECT * FROM jadwal_pelajaran WHERE kelas=? ORDER BY FIELD(hari,'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'), jam_mulai");
        $st->execute([$k]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    }
}
$groups = [];
foreach ($rows as $r) $groups[$r['kelas']][$r['hari']][] = $r;

// daftar mapel untuk saran isian
$mapelOptions = $pdo->query("SELECT nama FROM mata_pelajaran UNION SELECT mata_pelajaran FROM guru UNION SELECT mata_pelajaran FROM jadwal_pelajaran ORDER BY 1")->fetchAll(PDO::FETCH_COLUMN);

function is_mine(array $r, ?array $me): bool {
    if (!$me) return false;
    return (int)$r['guru_id'] === (int)$me['id'] || mb_strtolower(trim($r['mata_pelajaran'])) === mb_strtolower(trim($me['mata_pelajaran']));
}

$pageTitle = 'Jadwal Pelajaran — STEINS GATE';
include __DIR__ . '/../includes/dashboard_page_start.php';
$formKelas = $edit['kelas'] ?? ($k !== 'all' && $k ? $k : '10');
?>
<div class="page-heading">
    <div>
        <div class="section-kicker">WEEKLY SCHEDULE</div>
        <h1>Jadwal <em>Pelajaran.</em></h1>
        <p><?php if ($role === 'siswa'): ?><?= $siswaKelas ? 'Jadwal kelas ' . e($siswaKelas) . ' sesuai akun Anda.' : 'Kelas Anda belum ditetapkan.' ?><?php elseif ($role === 'guru'): ?><?= $myKelas ? 'Jadwal kelas ajar Anda (' . e(implode(', ', $myKelas)) . '). Mata pelajaran Anda diberi warna.' : 'Kelas ajar Anda belum ditetapkan oleh Admin.' ?><?php else: ?>Kelola jadwal untuk kelas 10, 11, dan 12.<?php endif; ?></p>
    </div>
    <?php if ($isAdmin): ?><a class="btn btn-brown" href="#form-jadwal"><i class="bi bi-plus-lg me-1"></i>Tambah Jadwal</a><?php endif; ?>
</div>

<?php sg_show_flash(); foreach ($errors as $er): ?><div class="alert alert-danger"><?= e($er) ?></div><?php endforeach; ?>

<?php if ($role !== 'siswa'): ?>
    <div class="class-tabs">
        <?php if ($role === 'guru' && count($myKelas) > 1): ?><a class="<?= $k === 'all' ? 'active' : '' ?>" href="?k=all">Semua Kelas Saya</a><?php endif; ?>
        <?php foreach (($role === 'guru' ? $myKelas : $kelasList) as $kl): ?><a class="<?= $k === $kl ? 'active' : '' ?>" href="?k=<?= $kl ?>">Kelas <?= $kl ?></a><?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($role === 'guru' && $me): ?>
    <div class="jadwal-legend mb-3"><span class="legend-swatch"></span> Jadwal mata pelajaran Anda (<?= e($me['mata_pelajaran']) ?>)</div>
<?php endif; ?>

<?php if ($isAdmin): ?>
<div class="sg-panel mb-4" id="form-jadwal">
    <h3><?= $edit && !empty($edit['id']) ? 'Ubah Jadwal' : 'Tambah Jadwal' ?></h3>
    <form method="post" class="row g-3">
        <input type="hidden" name="csrf_token" value="<?= e(sg_csrf_token()) ?>">
        <input type="hidden" name="action" value="<?= $edit && !empty($edit['id']) ? 'update' : 'create' ?>">
        <input type="hidden" name="k" value="<?= e($k) ?>">
        <?php if ($edit && !empty($edit['id'])): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
        <div class="col-md-3"><label class="form-label">Kelas</label>
            <select name="kelas" class="form-select custom-input" required>
                <?php foreach ($kelasList as $kl): ?><option value="<?= $kl ?>" <?= $formKelas === $kl ? 'selected' : '' ?>>Kelas <?= $kl ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-3"><label class="form-label">Hari</label>
            <select name="hari" class="form-select custom-input" required>
                <?php foreach ($days as $d): ?><option <?= $d === ($edit['hari'] ?? '') ? 'selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-3"><label class="form-label">Jam Mulai</label><input type="time" name="jam_mulai" class="form-control custom-input" value="<?= e(substr($edit['jam_mulai'] ?? '', 0, 5)) ?>" required></div>
        <div class="col-md-3"><label class="form-label">Jam Selesai</label><input type="time" name="jam_selesai" class="form-control custom-input" value="<?= e(substr($edit['jam_selesai'] ?? '', 0, 5)) ?>" required></div>
        <div class="col-md-4"><label class="form-label">Mata Pelajaran</label>
            <input name="mata_pelajaran" id="mapelInput" list="mapelList" class="form-control custom-input" value="<?= e($edit['mata_pelajaran'] ?? '') ?>" required autocomplete="off">
            <datalist id="mapelList"><?php foreach ($mapelOptions as $m): ?><option value="<?= e($m) ?>"><?php endforeach; ?></datalist></div>
        <div class="col-md-4"><label class="form-label">Guru Pengajar</label>
            <select name="guru_id" id="guruSelect" class="form-select custom-input">
                <option value="">— Belum ada guru (dikosongkan) —</option>
                <?php foreach ($gurus as $g): ?><option value="<?= (int)$g['id'] ?>" data-mapel="<?= e(mb_strtolower(trim($g['mata_pelajaran']))) ?>" <?= (int)($edit['guru_id'] ?? 0) === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['nama_lengkap']) ?> — <?= e($g['mata_pelajaran']) ?></option><?php endforeach; ?>
            </select>
            <small class="text-muted" id="guruHint">Guru dipilih dari akun guru dengan mata pelajaran yang sama.</small></div>
        <div class="col-md-4"><label class="form-label">Ruangan</label><input name="ruangan" class="form-control custom-input" value="<?= e($edit['ruangan'] ?? 'Kelas IPA 2') ?>"></div>
        <div class="col-12"><button class="btn btn-brown">Simpan</button> <?php if ($edit && !empty($edit['id'])): ?><a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/dashboard/jadwal.php?k=<?= e($k) ?>">Batal</a><?php endif; ?></div>
    </form>
</div>
<?php endif; ?>

<?php if ($role === 'guru' && !$myKelas): ?>
    <div class="alert alert-warning">Anda belum ditetapkan mengajar di kelas mana pun oleh Admin, sehingga jadwal belum dapat ditampilkan.</div>
<?php endif; ?>

<?php if ($role === 'siswa' && !$siswaKelas): ?>
    <div class="alert alert-warning">Kelas akun Anda belum ditetapkan oleh Admin, sehingga jadwal belum dapat ditampilkan.</div>
<?php endif; ?>

<?php foreach ($groups as $kelasKey => $perHari): foreach ($perHari as $day => $items): ?>
    <div class="sg-panel mb-3">
        <h3><?= $k === 'all' ? 'Kelas ' . e($kelasKey) . ' · ' : '' ?><?= e($day) ?></h3>
        <div class="table-responsive">
            <table class="table sg-table">
                <thead><tr><th>Waktu</th><th>Mata Pelajaran</th><th>Guru</th><th>Ruangan</th><?php if ($isAdmin): ?><th>Aksi</th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($items as $r): $mine = ($role === 'guru') && is_mine($r, $me); $gn = jadwal_guru_name($r, $gurusById, $byMapel); ?>
                    <tr class="<?= $mine ? 'jadwal-mine' : '' ?>">
                        <td><?= e(substr($r['jam_mulai'], 0, 5)) ?>–<?= e(substr($r['jam_selesai'], 0, 5)) ?></td>
                        <td><?= e($r['mata_pelajaran']) ?><?= $mine ? ' <span class="sg-badge">Mapel Anda</span>' : '' ?></td>
                        <td><?= $gn !== '' ? e($gn) : '<span class="text-muted">—</span>' ?></td>
                        <td><?= e($r['ruangan']) ?></td>
                        <?php if ($isAdmin): ?>
                        <td>
                            <a class="btn btn-sm btn-outline-secondary" href="?edit=<?= (int)$r['id'] ?>#form-jadwal">Edit</a>
                            <form method="post" class="d-inline" onsubmit="return confirm('Hapus jadwal ini?')">
                                <input type="hidden" name="csrf_token" value="<?= e(sg_csrf_token()) ?>"><input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="k" value="<?= e($k) ?>">
                                <button class="btn btn-sm btn-outline-danger">Hapus</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; endforeach; ?>
<?php if (!$rows && ($role === 'admin' || ($role === 'siswa' && $siswaKelas) || ($role === 'guru' && $myKelas))): ?><div class="sg-panel text-center">Belum ada jadwal pelajaran<?= $k && $k !== 'all' ? ' untuk kelas ' . e($k) : '' ?>.</div><?php endif; ?>

<?php if ($isAdmin): ?>
<script>
(function () {
    const mapel = document.getElementById('mapelInput'), sel = document.getElementById('guruSelect'), hint = document.getElementById('guruHint');
    function sync() {
        const m = mapel.value.trim().toLowerCase();
        let n = 0, first = null;
        Array.from(sel.options).forEach(o => {
            if (!o.value) { o.hidden = false; o.disabled = false; return; }
            const ok = o.dataset.mapel === m;
            o.hidden = !ok; o.disabled = !ok;
            if (ok) { n++; first = first || o; }
        });
        if (sel.selectedOptions[0] && sel.selectedOptions[0].disabled) sel.value = '';
        if (n === 1 && !sel.value) sel.value = first.value;
        hint.textContent = m === '' ? 'Isi mata pelajaran terlebih dahulu.'
            : n === 0 ? 'Belum ada guru untuk mata pelajaran ini. Kolom guru akan kosong sampai guru mendaftar.'
            : n === 1 ? 'Satu guru tersedia dan dipilih otomatis.'
            : 'Ada ' + n + ' guru untuk mata pelajaran ini. Pilih salah satu.';
    }
    mapel.addEventListener('input', sync); mapel.addEventListener('change', sync); sync();
})();
</script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/dashboard_page_end.php'; ?>