<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('siswa');

// ========================================
// 1. DATA PENGGUNA
// ========================================

$u = current_user();
$uid = (int)($u['id'] ?? $u['user_id'] ?? 0);

$error = '';
$success = '';

$isEditing = isset($_GET['edit']) && $_GET['edit'] === '1';

// ========================================
// 2. AMBIL DATA PROFIL SISWA
// ========================================

$get = $pdo->prepare("
    SELECT
        s.*,
        u.username,
        u.email
    FROM siswa s
    LEFT JOIN users u ON u.id = s.user_id
    WHERE s.user_id = ?
    LIMIT 1
");

$get->execute([$uid]);
$s = $get->fetch(PDO::FETCH_ASSOC);

// ========================================
// 3. PROSES SIMPAN DATA PROFIL
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verify_csrf('csrf_profil');

    $isEditing = true;

    $nama = trim($_POST['nama_lengkap'] ?? '');
    $wa = trim($_POST['no_wa'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');

    if (!$s) {

        $error = 'Profil siswa belum terhubung dengan akun ini.';

    } elseif ($nama === '' || $wa === '' || $alamat === '') {

        $error = 'Nama, WhatsApp, dan alamat wajib diisi.';

    } else {

        // Pertahankan foto lama jika tidak ada foto baru
        $fotoPath = $s['foto_profil'] ?? null;

        // ========================================
        // 4. PROSES UPLOAD FOTO PROFIL
        // ========================================

        if (
            isset($_FILES['foto_profil']) &&
            $_FILES['foto_profil']['error'] !== UPLOAD_ERR_NO_FILE
        ) {

            if ($_FILES['foto_profil']['error'] !== UPLOAD_ERR_OK) {

                $error = 'Upload foto gagal. Silakan coba kembali.';

            } elseif ($_FILES['foto_profil']['size'] > 2 * 1024 * 1024) {

                $error = 'Ukuran foto maksimal 2 MB.';

            } else {

                $tmpFile = $_FILES['foto_profil']['tmp_name'];

                // Validasi MIME berdasarkan isi file
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($tmpFile);

                $allowed = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png'
                ];

                $imageInfo = getimagesize($tmpFile);

                if (
                    !isset($allowed[$mime]) ||
                    $imageInfo === false ||
                    !is_uploaded_file($tmpFile)
                ) {

                    $error = 'Format foto harus JPG, JPEG, atau PNG.';

                } else {

                    // Lokasi folder upload
                    $uploadDir = __DIR__ . '/../assets/uploads/profil/';

                    // Buat folder jika belum tersedia
                    if (!is_dir($uploadDir)) {
                        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                            $error = 'Folder penyimpanan foto gagal dibuat.';
                        }
                    }

                    if ($error === '') {

                        // Nama file acak agar tidak bentrok
                        $filename = bin2hex(random_bytes(16))
                            . '.' . $allowed[$mime];

                        $destination = $uploadDir . $filename;

                        if (move_uploaded_file($tmpFile, $destination)) {

                            $oldPhoto = (string)($s['foto_profil'] ?? '');
                            $fotoPath = 'assets/uploads/profil/' . $filename;
                            $removeOldPhoto = ($oldPhoto !== '' && strpos($oldPhoto, 'assets/uploads/profil/') === 0)
                                ? $oldPhoto : null;

                        } else {

                            $error = 'Foto tidak berhasil disimpan ke server.';

                        }
                    }
                }
            }
        }

        // ========================================
        // 5. SIMPAN PERUBAHAN KE DATABASE
        // ========================================

        if ($error === '') {

            $up = $pdo->prepare("
                UPDATE siswa
                SET
                    nama_lengkap = ?,
                    no_wa = ?,
                    alamat = ?,
                    foto_profil = ?
                WHERE user_id = ?
            ");

            $up->execute([
                $nama,
                $wa,
                $alamat,
                $fotoPath,
                $uid
            ]);

            // Hapus file foto lama setelah database berhasil diperbarui
            if (!empty($removeOldPhoto)) {
                $oldFile = realpath(__DIR__ . '/../' . $removeOldPhoto);
                $baseDir = realpath(__DIR__ . '/../assets/uploads/profil');
                if ($oldFile && $baseDir && strpos($oldFile, $baseDir . DIRECTORY_SEPARATOR) === 0) {
                    @unlink($oldFile);
                }
            }

            // Ambil kembali data terbaru
            $get->execute([$uid]);
            $s = $get->fetch(PDO::FETCH_ASSOC);

            $_SESSION['user']['nama'] = $s['nama_lengkap'];
            $success = 'Profil berhasil diperbarui.';
            $isEditing = false;
        }
    }
}

// ========================================
// 6. JUDUL HALAMAN
// ========================================

$pageTitle = 'Profil Siswa — STEINS GATE';

include __DIR__ . '/../includes/dashboard_page_start.php';
?>

<!-- ========================================
     HEADER HALAMAN
======================================== -->

<div class="page-heading">
    <div>
        <div class="section-kicker">PERSONAL ARCHIVE</div>
        <h1>Profil <em>Siswa.</em></h1>
        <p>Informasi akun dan identitas siswa.</p>
    </div>
</div>

<!-- ========================================
     NOTIFIKASI
======================================== -->

<?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-circle me-2"></i>
        <?= e($error) ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle me-2"></i>
        <?= e($success) ?>
    </div>
<?php endif; ?>

<!-- ========================================
     DATA PROFIL SISWA
======================================== -->

<?php if (!$s): ?>

    <div class="sg-panel text-center">
        <i class="bi bi-person-x fs-1 mb-3"></i>
        <h4>Profil Siswa Tidak Ditemukan</h4>
        <p>
            Pastikan data siswa sudah terhubung dengan akun pengguna.
        </p>
    </div>

<?php else: ?>

<div class="sg-panel">

    <!-- ========================================
         FOTO DAN IDENTITAS SISWA
    ======================================== -->

    <div class="profile-header">

        <?php if (!empty($s['foto_profil'])): ?>

            <img
                src="<?= BASE_URL ?>/<?= e($s['foto_profil']) ?>"
                alt="Foto Profil <?= e($s['nama_lengkap']) ?>"
                class="profile-photo"
            >

        <?php else: ?>

            <div class="profile-photo-placeholder">
                <i class="bi bi-person-fill"></i>
            </div>

        <?php endif; ?>

        <div class="profile-header-info">
            <h3><?= e($s['nama_lengkap']) ?></h3>

            <p class="text-muted mb-1">
                NIS: <?= e($s['nis']) ?>
            </p>

            <span class="sg-badge">
                <?= e($s['angkatan']) ?>
            </span>
        </div>

    </div>

    <!-- ========================================
         JUDUL INFORMASI PRIBADI
    ======================================== -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>
            <h3 class="mb-1">Informasi Pribadi</h3>

            <p class="text-muted mb-0">
                <?= $isEditing
                    ? 'Silakan ubah informasi profil Anda.'
                    : 'Berikut informasi identitas akun dan siswa Anda.' ?>
            </p>
        </div>

        <?php if (!$isEditing): ?>

            <a
                href="<?= BASE_URL ?>/dashboard/profil.php?edit=1"
                class="btn btn-brown"
            >
                <i class="bi bi-pencil-square me-1"></i>
                Ubah Data
            </a>

        <?php endif; ?>

    </div>

    <!-- ========================================
         MODE EDIT PROFIL
    ======================================== -->

    <?php if ($isEditing): ?>

        <form
            method="post"
            enctype="multipart/form-data"
            class="row g-3"
        >

            <input type="hidden" name="csrf_token" value="<?= e(csrf_token('csrf_profil')) ?>">

            <!-- Nama Lengkap -->

            <div class="col-md-6">
                <label class="form-label">Nama Lengkap</label>

                <input
                    type="text"
                    class="form-control custom-input"
                    name="nama_lengkap"
                    value="<?= e($_POST['nama_lengkap'] ?? $s['nama_lengkap']) ?>"
                    required
                >
            </div>

            <!-- NIS -->

            <div class="col-md-6">
                <label class="form-label">NIS</label>

                <input
                    type="text"
                    class="form-control custom-input"
                    value="<?= e($s['nis']) ?>"
                    disabled
                >
            </div>

            <!-- Email -->

            <div class="col-md-6">
                <label class="form-label">Email</label>

                <input
                    type="text"
                    class="form-control custom-input"
                    value="<?= e($s['email'] ?? '') ?>"
                    disabled
                >
            </div>

            <!-- Username -->

            <div class="col-md-6">
                <label class="form-label">Username</label>

                <input
                    type="text"
                    class="form-control custom-input"
                    value="<?= e($s['username'] ?? '') ?>"
                    disabled
                >
            </div>

            <!-- Jenis Kelamin -->

            <div class="col-md-6">
                <label class="form-label">Jenis Kelamin</label>

                <input
                    type="text"
                    class="form-control custom-input"
                    value="<?= e($s['jenis_kelamin']) ?>"
                    disabled
                >
            </div>

            <!-- Angkatan -->

            <div class="col-md-6">
                <label class="form-label">Angkatan</label>

                <input
                    type="text"
                    class="form-control custom-input"
                    value="<?= e($s['angkatan']) ?>"
                    disabled
                >
            </div>

            <!-- WhatsApp -->

            <div class="col-md-6">
                <label class="form-label">WhatsApp</label>

                <input
                    type="text"
                    class="form-control custom-input"
                    name="no_wa"
                    value="<?= e($_POST['no_wa'] ?? $s['no_wa']) ?>"
                    required
                >
            </div>

            <!-- Foto Profil -->

            <div class="col-md-6">
                <label class="form-label">Foto Profil</label>

                <input
                    type="file"
                    class="form-control custom-input"
                    name="foto_profil"
                    accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                >

                <small class="text-muted">
                    Format JPG atau PNG, maksimal 2 MB.
                    Kosongkan jika tidak ingin mengganti foto.
                </small>
            </div>

            <!-- Alamat -->

            <div class="col-12">
                <label class="form-label">Alamat</label>

                <textarea
                    class="form-control custom-input"
                    name="alamat"
                    rows="3"
                    required
                ><?= e($_POST['alamat'] ?? $s['alamat']) ?></textarea>
            </div>

            <!-- Tombol -->

            <div class="col-12 d-flex gap-2">

                <button
                    type="submit"
                    class="btn btn-brown"
                >
                    <i class="bi bi-check-circle me-1"></i>
                    Simpan Perubahan
                </button>

                <a
                    href="<?= BASE_URL ?>/dashboard/profil.php"
                    class="btn btn-outline-secondary"
                >
                    Batal
                </a>

            </div>

        </form>

    <!-- ========================================
         MODE LIHAT PROFIL
    ======================================== -->

    <?php else: ?>

        <div class="row g-4">

            <!-- Nama Lengkap -->

            <div class="col-md-6">
                <label class="form-label">Nama Lengkap</label>

                <div class="profile-value">
                    <?= e($s['nama_lengkap']) ?>
                </div>
            </div>

            <!-- NIS -->

            <div class="col-md-6">
                <label class="form-label">NIS</label>

                <div class="profile-value">
                    <?= e($s['nis']) ?>
                </div>
            </div>

            <!-- Email -->

            <div class="col-md-6">
                <label class="form-label">Email</label>

                <div class="profile-value">
                    <?= e($s['email'] ?? '-') ?>
                </div>
            </div>

            <!-- Username -->

            <div class="col-md-6">
                <label class="form-label">Username</label>

                <div class="profile-value">
                    <?= e($s['username'] ?? '-') ?>
                </div>
            </div>

            <!-- Jenis Kelamin -->

            <div class="col-md-6">
                <label class="form-label">Jenis Kelamin</label>

                <div class="profile-value">
                    <?= e($s['jenis_kelamin']) ?>
                </div>
            </div>

            <!-- Angkatan -->

            <div class="col-md-6">
                <label class="form-label">Angkatan</label>

                <div class="profile-value">
                    <?= e($s['angkatan']) ?>
                </div>
            </div>

            <!-- WhatsApp -->

            <div class="col-md-6">
                <label class="form-label">WhatsApp</label>

                <div class="profile-value">
                    <?= e($s['no_wa']) ?>
                </div>
            </div>

            <!-- Alamat -->

            <div class="col-12">
                <label class="form-label">Alamat</label>

                <div class="profile-value profile-address">
                    <?= nl2br(e($s['alamat'])) ?>
                </div>
            </div>

        </div>

    <?php endif; ?>

</div>

<?php endif; ?>

<?php
include __DIR__ . '/../includes/dashboard_page_end.php';
?>
