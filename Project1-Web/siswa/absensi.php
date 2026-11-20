<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/akademik_helpers.php';
require_role('siswa');

$u = current_user();
$q = $pdo->prepare("SELECT id, kelas FROM siswa WHERE user_id=?");
$q->execute([$u['id']]);
$s = $q->fetch();
if (!$s) exit('Profil siswa tidak ditemukan.');

$self = BASE_URL . '/siswa/absensi.php';
$statusList = ['Hadir', 'Izin', 'Sakit', 'Alpa'];
$mapelAktif = trim($_GET['mapel'] ?? '');

$rows = [];
$mapelList = [];
if ($s['kelas']) {
    $st = $pdo->prepare("
        SELECT a.status, a.keterangan, ses.tanggal, ses.hari, ses.deskripsi, ses.kelas,
               g.nama_lengkap AS guru_nama, g.mata_pelajaran
        FROM absensi a
        JOIN absensi_sesi ses ON ses.id = a.sesi_id
        JOIN guru g ON g.id = ses.guru_id
        WHERE a.siswa_id = ?
        ORDER BY ses.tanggal DESC, ses.id DESC
    ");
    $st->execute([$s['id']]);
    $rows = $st->fetchAll();

    $jd = $pdo->prepare("SELECT DISTINCT mata_pelajaran FROM jadwal_pelajaran WHERE kelas=?");
    $jd->execute([$s['kelas']]);
    foreach (array_merge($jd->fetchAll(PDO::FETCH_COLUMN), array_column($rows, 'mata_pelajaran')) as $m) {
        $key = mb_strtolower(trim($m));
        if ($key !== '' && !isset($mapelList[$key])) $mapelList[$key] = trim($m);
    }
    asort($mapelList, SORT_NATURAL | SORT_FLAG_CASE);
}
$aktifKey = mb_strtolower($mapelAktif);
if ($aktifKey !== '' && !isset($mapelList[$aktifKey])) { $aktifKey = ''; $mapelAktif = ''; }

$perMapel = [];
foreach ($rows as $r) $perMapel[mb_strtolower(trim($r['mata_pelajaran']))][] = $r;
// Mapel yang ada di jadwal tapi belum punya absensi tetap ditampilkan (kosong) saat tab dipilih
$tampil = $aktifKey === '' ? $perMapel : [$aktifKey => $perMapel[$aktifKey] ?? []];

$pageTitle = 'Absensi Saya — STEINS GATE';
include __DIR__ . '/../includes/dashboard_page_start.php';
?>
<div class="page-heading"><div><div class="section-kicker">STUDENT / ATTENDANCE</div><h1>Riwayat <em>Absensi.</em></h1><p>Catatan kehadiran Anda per mata pelajaran, lengkap dengan hari dan keterangan pertemuan.</p></div></div>

<?php if (!$s['kelas']): ?>
    <div class="alert alert-warning">Kelas akun Anda belum ditetapkan oleh Admin.</div>
<?php else: ?>
    <div class="class-tabs">
        <a class="<?= $aktifKey === '' ? 'active' : '' ?>" href="<?= $self ?>">Semua<span class="count"><?= count($rows) ?></span></a>
        <?php foreach ($mapelList as $key => $nama): ?>
            <a class="<?= $aktifKey === $key ? 'active' : '' ?>" href="<?= $self ?>?mapel=<?= urlencode($nama) ?>"><?= e($nama) ?><span class="count"><?= count($perMapel[$key] ?? []) ?></span></a>
        <?php endforeach; ?>
    </div>

    <?php if (!$tampil): ?>
        <div class="sg-panel">Belum ada catatan absensi.</div>
    <?php else: foreach ($tampil as $key => $list):
        $cnt = array_fill_keys($statusList, 0);
        foreach ($list as $r) if (isset($cnt[$r['status']])) $cnt[$r['status']]++;
        $total = count($list);
        $persen = $total ? round($cnt['Hadir'] / $total * 100) : 0;
    ?>
        <div class="sg-panel mb-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h3 class="mb-0"><i class="bi bi-book"></i> <?= e($mapelList[$key] ?? $key) ?></h3>
                <div class="absen-summary">
                    <?php foreach ($statusList as $st): ?><span class="sg-badge"><?= e($st) ?>: <?= (int)$cnt[$st] ?></span><?php endforeach; ?>
                    <span class="sg-badge"><?= $total ?> pertemuan · <?= $persen ?>% hadir</span>
                </div>
            </div>
            <?php if (!$list): ?>
                <p class="muted-copy mb-0">Belum ada catatan absensi untuk mata pelajaran ini.</p>
            <?php else: ?>
            <div class="table-responsive"><table class="table align-middle">
                <thead><tr><th>Hari, Tanggal</th><th>Pertemuan</th><th>Status</th><th>Guru</th><th>Keterangan</th></tr></thead>
                <tbody>
                <?php foreach ($list as $r): ?>
                    <tr>
                        <td><?= e($r['hari']) ?>, <?= date('d M Y', strtotime($r['tanggal'])) ?></td>
                        <td><?= e($r['deskripsi'] ?: '-') ?></td>
                        <td><span class="sg-badge <?= $r['status'] === 'Hadir' ? '' : 'sg-badge-warn' ?>"><?= e($r['status']) ?></span></td>
                        <td><?= e($r['guru_nama']) ?></td>
                        <td><?= e($r['keterangan'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
        </div>
    <?php endforeach; endif; ?>
<?php endif; ?>
<?php include __DIR__ . '/../includes/dashboard_page_end.php'; ?>