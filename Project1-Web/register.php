<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    dashboard_home();
}

$error = '';

$subjects = $pdo->query("
    SELECT nama FROM mata_pelajaran
    UNION
    SELECT mata_pelajaran FROM jadwal_pelajaran
    WHERE mata_pelajaran IS NOT NULL AND mata_pelajaran <> ''
    ORDER BY 1
")->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Data akun
    $role = $_POST['role'] ?? 'siswa';
    $nama = trim($_POST['nama_lengkap'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Data siswa
    $nis = trim($_POST['nis'] ?? '');
    $jk = $_POST['jenis_kelamin'] ?? '';
    $wa = trim($_POST['no_wa'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $angkatan = trim($_POST['angkatan'] ?? '');

    // Data guru
    $subject = trim($_POST['mata_pelajaran'] ?? '');

    // Validasi
    if (!in_array($role, ['guru', 'siswa'], true)) {
        $error = 'Pilihan jenis akun tidak valid.';

    } elseif (
        !$nama || !$email || !$username ||
        !$pass || !$confirm
    ) {
        $error = 'Lengkapi seluruh data akun.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';

    } elseif (strlen($pass) < 8) {
        $error = 'Password minimal 8 karakter.';

    } elseif ($pass !== $confirm) {
        $error = 'Konfirmasi password tidak sama.';

    } elseif (
        $role === 'guru' &&
        !in_array($subject, $subjects, true)
    ) {
        $error = 'Pilih mata pelajaran yang tersedia.';

    } elseif (
        $role === 'siswa' &&
        (!$nis || !$jk || !$wa || !$alamat || !$angkatan)
    ) {
        $error = 'Lengkapi seluruh identitas siswa.';

    } elseif (
        $role === 'siswa' &&
        !in_array($jk, ['Laki-laki', 'Perempuan'], true)
    ) {
        $error = 'Jenis kelamin tidak valid.';

    } else {

        // Periksa username dan email
        $check = $pdo->prepare("
            SELECT id
            FROM users
            WHERE username = ? OR email = ?
            LIMIT 1
        ");
        $check->execute([$username, $email]);

        // Periksa NIS khusus siswa
        $nisExists = false;

        if ($role === 'siswa') {
            $nisCheck = $pdo->prepare("
                SELECT id
                FROM siswa
                WHERE nis = ?
                LIMIT 1
            ");
            $nisCheck->execute([$nis]);

            $nisExists = (bool) $nisCheck->fetch();
        }

        if ($check->fetch()) {
            $error = 'Username atau email sudah digunakan.';

        } elseif ($nisExists) {
            $error = 'NIS sudah terdaftar.';

        } else {

            try {
                $pdo->beginTransaction();

                // Simpan akun pengguna
                $ins = $pdo->prepare("
                    INSERT INTO users
                        (username, email, password, role)
                    VALUES (?, ?, ?, ?)
                ");

                $ins->execute([
                    $username,
                    $email,
                    password_hash($pass, PASSWORD_DEFAULT),
                    $role
                ]);

                $uid = (int) $pdo->lastInsertId();

                // Jika Guru
                if ($role === 'guru') {

                    $guru = $pdo->prepare("
                        INSERT INTO guru
                            (user_id, nama_lengkap, mata_pelajaran)
                        VALUES (?, ?, ?)
                    ");

                    $guru->execute([
                        $uid,
                        $nama,
                        $subject
                    ]);

                } else {

                    // Jika Siswa
                    $siswa = $pdo->prepare("
                        INSERT INTO siswa (
                            user_id,
                            nis,
                            nama_lengkap,
                            jenis_kelamin,
                            no_wa,
                            alamat,
                            angkatan
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");

                    $siswa->execute([
                        $uid,
                        $nis,
                        $nama,
                        $jk,
                        $wa,
                        $alamat,
                        $angkatan
                    ]);
                }

                $pdo->commit();

                set_flash(
                    'success',
                    'Registrasi berhasil. Silakan login.'
                );

                redirect('login.php');

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Registrasi gagal: ' . $e->getMessage());

                $error = 'Registrasi gagal. Periksa struktur database dan pastikan migrasi sudah dijalankan.';
            }
        }
    }
}

$pageTitle = 'Registrasi — STEINS GATE';

include __DIR__ . '/includes/header.php';
?>

<main class="auth-page register-page">

    <a class="auth-brand brand" href="index.php">
        <?= brand_mark() ?>
        STEINS GATE
    </a>

    <div class="auth-card auth-card-wide">

        <div class="section-kicker">
            BECOME A PART OF OUR ARCHIVE / 02
        </div>

        <h1>Create your <em>account.</em></h1>

        <p class="muted-copy">
            Pilih jenis akun untuk melanjutkan pendaftaran.
        </p>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" id="registerForm">

            <div class="row g-3">

                <!-- PILIH ROLE -->
                <div class="col-12">
                    <label class="form-label">
                        Daftar Sebagai
                    </label>

                    <select
                        class="form-select custom-input"
                        name="role"
                        id="roleSelect"
                        required
                    >
                        <option
                            value="siswa"
                            <?= (($_POST['role'] ?? 'siswa') === 'siswa') ? 'selected' : '' ?>
                        >
                            Siswa
                        </option>

                        <option
                            value="guru"
                            <?= (($_POST['role'] ?? '') === 'guru') ? 'selected' : '' ?>
                        >
                            Guru
                        </option>
                    </select>
                </div>

                <!-- NAMA LENGKAP -->
                <div class="col-md-6">
                    <label class="form-label">
                        Nama Lengkap
                    </label>

                    <input
                        class="form-control custom-input"
                        type="text"
                        name="nama_lengkap"
                        required
                        value="<?= e($_POST['nama_lengkap'] ?? '') ?>"
                    >
                </div>

                <!-- EMAIL -->
                <div class="col-md-6">
                    <label class="form-label">
                        Email
                    </label>

                    <input
                        class="form-control custom-input"
                        type="email"
                        name="email"
                        required
                        value="<?= e($_POST['email'] ?? '') ?>"
                    >
                </div>

                <!-- USERNAME -->
                <div class="col-md-6">
                    <label class="form-label">
                        Username
                    </label>

                    <input
                        class="form-control custom-input"
                        type="text"
                        name="username"
                        required
                        value="<?= e($_POST['username'] ?? '') ?>"
                    >
                </div>

                <!-- MATA PELAJARAN GURU -->
                <div class="col-md-6" id="subjectField">
                    <label class="form-label">
                        Mata Pelajaran
                    </label>

                    <select
                        class="form-select custom-input"
                        name="mata_pelajaran"
                        id="subjectSelect"
                    >
                        <option value="">
                            Pilih mata pelajaran
                        </option>

                        <?php foreach ($subjects as $sub): ?>
                            <option
                                value="<?= e($sub) ?>"
                                <?= (($_POST['mata_pelajaran'] ?? '') === $sub) ? 'selected' : '' ?>
                            >
                                <?= e($sub) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <!-- PASSWORD -->
                <div class="col-md-6">
                    <label class="form-label">
                        Password
                    </label>

                    <input
                        class="form-control custom-input"
                        type="password"
                        name="password"
                        minlength="8"
                        required
                    >
                </div>

                <!-- KONFIRMASI PASSWORD -->
                <div class="col-md-6">
                    <label class="form-label">
                        Konfirmasi Password
                    </label>

                    <input
                        class="form-control custom-input"
                        type="password"
                        name="confirm_password"
                        minlength="8"
                        required
                    >
                </div>

                <!-- DATA KHUSUS SISWA -->
                <div
                    id="studentFields"
                    class="row g-3 m-0 p-0"
                >

                    <div class="col-md-6">
                        <label class="form-label">
                            NIS
                        </label>

                        <input
                            class="form-control custom-input"
                            type="text"
                            name="nis"
                            value="<?= e($_POST['nis'] ?? '') ?>"
                        >
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">
                            Jenis Kelamin
                        </label>

                        <select
                            class="form-select custom-input"
                            name="jenis_kelamin"
                        >
                            <option value="">
                                Pilih
                            </option>

                            <option
                                value="Laki-laki"
                                <?= (($_POST['jenis_kelamin'] ?? '') === 'Laki-laki') ? 'selected' : '' ?>
                            >
                                Laki-laki
                            </option>

                            <option
                                value="Perempuan"
                                <?= (($_POST['jenis_kelamin'] ?? '') === 'Perempuan') ? 'selected' : '' ?>
                            >
                                Perempuan
                            </option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">
                            Nomor WhatsApp
                        </label>

                        <input
                            class="form-control custom-input"
                            type="text"
                            name="no_wa"
                            value="<?= e($_POST['no_wa'] ?? '') ?>"
                        >
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">
                            Angkatan / Tahun Masuk
                        </label>

                        <input
                            class="form-control custom-input"
                            type="text"
                            name="angkatan"
                            value="<?= e($_POST['angkatan'] ?? '') ?>"
                        >
                    </div>

                    <div class="col-12">
                        <label class="form-label">
                            Alamat
                        </label>

                        <textarea
                            class="form-control custom-input"
                            name="alamat"
                            rows="2"
                        ><?= e($_POST['alamat'] ?? '') ?></textarea>
                    </div>

                </div>

            </div>

            <button class="btn btn-brown w-100 mt-4 py-3">
                Buat Akun
                <i class="bi bi-arrow-right"></i>
            </button>

        </form>

        <p class="auth-switch">
            Sudah memiliki akun?
            <a href="login.php">Login</a>
        </p>

        <a href="index.php" class="back-home">
            ← Kembali ke halaman utama
        </a>

    </div>

    <div class="auth-copyright">
        <?= e(COPYRIGHT_TEXT) ?>
    </div>

</main>

<script>
const roleSelect = document.getElementById('roleSelect');
const studentFields = document.getElementById('studentFields');
const subjectField = document.getElementById('subjectField');
const subjectSelect = document.getElementById('subjectSelect');

function syncRole() {

    const isStudent = roleSelect.value === 'siswa';

    // Tampilkan/sembunyikan data siswa
    studentFields.hidden = !isStudent;

    // Tampilkan/sembunyikan mata pelajaran
    subjectField.hidden = isStudent;

    // Atur validasi data siswa
    studentFields.querySelectorAll(
        'input, select, textarea'
    ).forEach(field => {
        field.required = isStudent;
    });

    // Mata pelajaran wajib untuk Guru
    subjectSelect.required = !isStudent;
}

roleSelect.addEventListener('change', syncRole);

syncRole();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/validation.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>

</body>
</html>
