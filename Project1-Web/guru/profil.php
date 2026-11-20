<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('guru');

$u = current_user();
$uid = (int)$u['id'];
$error = '';
$success = '';
$isEditing = ($_GET['edit'] ?? '') === '1';

$get = $pdo->prepare("SELECT g.*, u.username, u.email FROM guru g JOIN users u ON u.id = g.user_id WHERE g.user_id = ? LIMIT 1");
$get->execute([$uid]);
$g = $get->fetch();
if (!$g) exit('Profil guru tidak ditemukan.');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('csrf_guru_profil');
    $isEditing = true;
    $nama = trim($_POST['nama_lengkap'] ?? '');
    $jk = $_POST['jenis_kelamin'] ?? '';
    $wa = trim($_POST['no_wa'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');

    if ($nama === '' || $wa === '' || $alamat === '') {
        $error = 'Nama, WhatsApp, dan alamat wajib diisi.';
    } elseif (!in_array($jk, ['', 'Laki-laki', 'Perempuan'], true)) {
        $error = 'Pilihan jenis kelamin tidak valid.';
    } else {
        $foto = $g['foto_profil'];
        $up = handle_upload($_FILES['foto_profil'] ?? null, 'profil', upload_allowed_types('gambar'), 2 * 1024 * 1024, $error);
        if ($error === '') {
            $oldFoto = null;
            if ($up) { $oldFoto = $foto; $foto = $up['path']; }
            $pdo->prepare("UPDATE guru SET nama_lengkap=?, jenis_kelamin=?, no_wa=?, alamat=?, foto_profil=? WHERE user_id=?")
                ->execute([$nama, $jk ?: null, $wa, $alamat, $foto, $uid]);
            if ($oldFoto) delete_upload($oldFoto, 'profil');
            $_SESSION['user']['nama'] = $nama;
            $get->execute([$uid]);
            $g = $get->fetch();
            $success = 'Profil berhasil diperbarui.';
            $isEditing = false;
        }
    }
}

$namaUser = $g['nama_lengkap'];
$pageTitle = 'Profil Guru — STEINS GATE';
include __DIR__ . '/../includes/dashboard_page_start.php';
$foto = photo_url($g['foto_profil']);
?>
<div class="page-heading"><div><div class="section-kicker">TEACHER / PROFILE</div><h1>Profil <em>Guru.</em></h1><p>Informasi akun dan identitas guru.</p></div></div>

<?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= e($success) ?></div><?php endif; ?>

<div class="sg-panel">
    <div class="profile-header">
        <?php if ($foto): ?>
            <img src="<?= e($foto) ?>" alt="Foto <?= e($g['nama_lengkap']) ?>" class="profile-photo">
        <?php else: ?>
            <div class="profile-photo-placeholder"><i class="bi bi-person-fill"></i></div>
        <?php endif; ?>
        <div class="profile-header-info">
            <h3><?= e($g['nama_lengkap']) ?></h3>
            <p class="text-muted mb-1">Guru <?= e($g['mata_pelajaran']) ?></p>
            <?php if ($g['nip']): ?><span class="sg-badge">NIP <?= e($g['nip']) ?></span><?php endif; ?>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1">Informasi Pribadi</h3>
            <p class="text-muted mb-0"><?= $isEditing ? 'Silakan ubah informasi profil Anda.' : 'Berikut informasi identitas akun guru Anda.' ?></p>
        </div>
        <?php if (!$isEditing): ?><a href="<?= BASE_URL ?>/guru/profil.php?edit=1" class="btn btn-brown"><i class="bi bi-pencil-square me-1"></i> Ubah Data</a><?php endif; ?>
    </div>

    <?php if ($isEditing): ?>
        <form method="post" enctype="multipart/form-data" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token('csrf_guru_profil')) ?>">
            <div class="col-md-6"><label class="form-label">Nama Lengkap</label><input class="form-control custom-input" name="nama_lengkap" maxlength="120" required value="<?= e($_POST['nama_lengkap'] ?? $g['nama_lengkap']) ?>"></div>
            <div class="col-md-6"><label class="form-label">Mata Pelajaran</label><input class="form-control custom-input" value="<?= e($g['mata_pelajaran']) ?>" disabled></div>
            <div class="col-md-6"><label class="form-label">Username</label><input class="form-control custom-input" value="<?= e($g['username']) ?>" disabled></div>
            <div class="col-md-6"><label class="form-label">Email</label><input class="form-control custom-input" value="<?= e($g['email']) ?>" disabled></div>
            <div class="col-md-6"><label class="form-label">Jenis Kelamin</label>
                <select class="form-select custom-input" name="jenis_kelamin"><option value="">Pilih</option>
                <?php foreach (['Laki-laki', 'Perempuan'] as $jk): ?><option <?= (($_POST['jenis_kelamin'] ?? $g['jenis_kelamin']) === $jk) ? 'selected' : '' ?>><?= e($jk) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">WhatsApp</label><input class="form-control custom-input" name="no_wa" maxlength="25" required value="<?= e($_POST['no_wa'] ?? $g['no_wa']) ?>"></div>
            <div class="col-12"><label class="form-label">Alamat</label><textarea class="form-control custom-input" name="alamat" rows="3" required><?= e($_POST['alamat'] ?? $g['alamat']) ?></textarea></div>
            <div class="col-12"><label class="form-label">Foto Profil</label><input type="file" class="form-control custom-input" name="foto_profil" accept=".jpg,.jpeg,.png,.webp"><small class="text-muted">JPG, PNG, atau WEBP, maksimal 2 MB. Kosongkan jika tidak ingin mengganti foto. NIP diatur oleh Admin.</small></div>
            <div class="col-12 d-flex gap-2"><button class="btn btn-brown"><i class="bi bi-check-circle me-1"></i> Simpan Perubahan</button><a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/guru/profil.php">Batal</a></div>
        </form>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ([
                'Nama Lengkap' => $g['nama_lengkap'], 'Mata Pelajaran' => $g['mata_pelajaran'],
                'NIP' => $g['nip'] ?: '-', 'Jenis Kelamin' => $g['jenis_kelamin'] ?: '-',
                'Username' => $g['username'], 'Email' => $g['email'], 'WhatsApp' => $g['no_wa'] ?: '-',
            ] as $label => $val): ?>
                <div class="col-md-6"><label class="form-label"><?= e($label) ?></label><div class="profile-value"><?= e($val) ?></div></div>
            <?php endforeach; ?>
            <div class="col-12"><label class="form-label">Alamat</label><div class="profile-value profile-address"><?= nl2br(e($g['alamat'] ?: '-')) ?></div></div>
        </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/dashboard_page_end.php'; ?>
