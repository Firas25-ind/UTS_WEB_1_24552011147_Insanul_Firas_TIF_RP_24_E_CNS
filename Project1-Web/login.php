<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) dashboard_home();

$error = '';
$old = '';
$flash = get_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf('csrf_login');
    $old = trim($_POST['identity'] ?? '');
    $pass = $_POST['password'] ?? '';

    if ($old === '' || $pass === '') {
        $error = 'Username/email dan password wajib diisi.';
    } else {
        $st = $pdo->prepare("
            SELECT u.id, u.username, u.email, u.password, u.role,
                   COALESCE(s.nama_lengkap, g.nama_lengkap, u.username) AS nama
            FROM users u
            LEFT JOIN siswa s ON s.user_id = u.id
            LEFT JOIN guru g ON g.user_id = u.id
            WHERE u.username = ? OR u.email = ?
            LIMIT 1
        ");
        $st->execute([$old, $old]);
        $user = $st->fetch();

        if ($user && password_verify($pass, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'       => (int)$user['id'],
                'username' => $user['username'],
                'email'    => $user['email'],
                'nama'     => $user['nama'],
                'role'     => $user['role'],
            ];
            dashboard_home();
        }
        $error = 'Username/email atau password tidak sesuai.';
    }
}

$pageTitle = 'Login — STEINS GATE';
include __DIR__ . '/includes/header.php';
?>
<main class="auth-page">
    <a class="auth-brand brand" href="<?= BASE_URL ?>/index.php"><?= brand_mark() ?> STEINS GATE</a>
    <div class="auth-card">
        <div class="section-kicker">MEMBER ACCESS / 01</div>
        <h1>Welcome <em>back.</em></h1>
        <p class="muted-copy">Masuk kembali ke ruang cerita IPA 2.</p>

        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['type'] === 'success' ? 'success' : 'danger') ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" id="loginForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token('csrf_login')) ?>">
            <label class="form-label">Username atau Email</label>
            <input class="form-control custom-input" name="identity" value="<?= e($old) ?>" autocomplete="username" required>
            <label class="form-label mt-3">Password</label>
            <input class="form-control custom-input" type="password" name="password" autocomplete="current-password" required>
            <button class="btn btn-brown w-100 mt-4 py-3">Login to your account <i class="bi bi-arrow-right"></i></button>
        </form>

        <p class="auth-switch">Belum memiliki akun? <a href="<?= BASE_URL ?>/register.php">Daftar di sini</a></p>
        <a href="<?= BASE_URL ?>/index.php" class="back-home">← Kembali ke halaman utama</a>
    </div>
    <div class="auth-copyright"><?= e(COPYRIGHT_TEXT) ?></div>
</main>
<script src="<?= BASE_URL ?>/assets/js/validation.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
