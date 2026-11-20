<?php
function sg_require_admin(): void {
    require_login();
    $user = current_user();
    if (($user['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Akses ditolak. Fitur pengelolaan hanya tersedia untuk Administrator.');
    }
}
function sg_csrf_token(): string {
    if (empty($_SESSION['sg_csrf'])) $_SESSION['sg_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['sg_csrf'];
}
function sg_verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['sg_csrf'] ?? '', $token)) {
        http_response_code(400); exit('Permintaan tidak valid. Muat ulang halaman dan coba kembali.');
    }
}
function sg_flash_redirect(string $message, string $url): void {
    $_SESSION['sg_crud_flash'] = $message;
    header('Location: ' . $url); exit;
}
function sg_show_flash(): void {
    if (!empty($_SESSION['sg_crud_flash'])) {
        echo '<div class="alert alert-success">' . e($_SESSION['sg_crud_flash']) . '</div>';
        unset($_SESSION['sg_crud_flash']);
    }
}
