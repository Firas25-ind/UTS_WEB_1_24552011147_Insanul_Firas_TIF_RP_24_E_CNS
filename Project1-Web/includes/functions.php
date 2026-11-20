<?php
require_once __DIR__.'/../config/app.php';
function e($value){ return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function redirect($path){ header('Location: '.BASE_URL.'/'.$path); exit; }
function is_logged_in(){ return !empty($_SESSION['user']); }
function require_login(){ if(!is_logged_in()){ redirect('login.php'); } }
function current_user(){ return $_SESSION['user'] ?? null; }
function set_flash($type,$message){ $_SESSION['flash']=['type'=>$type,'message'=>$message]; }
function get_flash(){ $f=$_SESSION['flash']??null; unset($_SESSION['flash']); return $f; }

function require_role($roles){
    require_login();
    $roles = (array)$roles;
    $role = current_user()['role'] ?? '';
    if (!in_array($role, $roles, true)) {
        http_response_code(403);
        exit('Akses ditolak. Anda tidak memiliki izin untuk membuka halaman ini.');
    }
}
function dashboard_home(){
    $role = current_user()['role'] ?? 'siswa';
    if ($role === 'guru') redirect('guru/index.php');
    if ($role === 'siswa') redirect('siswa/index.php');
    redirect('dashboard/index.php');
}
function csrf_token($key='csrf_token'){
    if (empty($_SESSION[$key])) $_SESSION[$key] = bin2hex(random_bytes(32));
    return $_SESSION[$key];
}
function verify_csrf($key='csrf_token'){
    if (!hash_equals($_SESSION[$key] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(403); exit('Permintaan tidak valid. Muat ulang halaman.');
    }
}

/*
 * Logo di samping judul. Taruh file logo di assets/images/ dengan nama
 * logo.png (atau logo.svg / logo.webp / logo.jpg). Jika belum ada, tampil huruf "S".
 */
function brand_mark(){
    foreach (['logo.png','logo.svg','logo.webp','logo.jpg'] as $f) {
        if (is_file(__DIR__.'/../assets/images/'.$f)) {
            return '<img src="'.BASE_URL.'/assets/images/'.$f.'" alt="Logo '.APP_NAME.'" class="brand-logo">';
        }
    }
    return '<span class="brand-mark">S</span>';
}

/* =========================================================
   UPLOAD FILE (dipakai jawaban tugas, foto guru, gambar informasi)
   Validasi: ekstensi + isi file (MIME) + ukuran.
========================================================= */
function upload_allowed_types(string $group = 'dokumen'): array {
    $office = ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'];
    $docs = [
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage', 'application/octet-stream'],
        'docx' => array_merge(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'], $office),
        'ppt'  => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage', 'application/octet-stream'],
        'pptx' => array_merge(['application/vnd.openxmlformats-officedocument.presentationml.presentation'], $office),
        'xls'  => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage', 'application/octet-stream'],
        'xlsx' => array_merge(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], $office),
        'txt'  => ['text/plain'],
        'zip'  => ['application/zip', 'application/x-zip-compressed'],
        'png'  => ['image/png'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
    ];
    $images = ['png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'webp' => ['image/webp']];
    return $group === 'gambar' ? $images : $docs;
}

function upload_type_ok(string $tmpFile, string $ext, array $allowed): bool {
    if (!isset($allowed[$ext])) return false;
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpFile);
    if (!in_array($mime, $allowed[$ext], true)) return false;
    if (strpos($mime, 'image/') === 0 && @getimagesize($tmpFile) === false) return false;
    return true;
}

/**
 * Simpan satu file upload ke assets/uploads/<subdir>/.
 * Return ['path'=>'assets/uploads/..','original'=>'nama.ext'] atau null (pesan di $error).
 * Jika tidak ada file dipilih: return null dan $error tetap ''.
 */
function handle_upload(?array $f, string $subdir, array $allowed, int $maxBytes, string &$error): ?array {
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($f['error'] !== UPLOAD_ERR_OK) { $error = 'Upload gagal (kode ' . (int)$f['error'] . '). Periksa ukuran file.'; return null; }
    if ($f['size'] > $maxBytes) { $error = 'Ukuran file maksimal ' . round($maxBytes / 1048576) . ' MB.'; return null; }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!is_uploaded_file($f['tmp_name']) || !upload_type_ok($f['tmp_name'], $ext, $allowed)) {
        $error = 'Format file tidak didukung. Gunakan: ' . strtoupper(implode(', ', array_keys($allowed))) . '.';
        return null;
    }
    $dir = __DIR__ . '/../assets/uploads/' . $subdir . '/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) { $error = 'Folder upload tidak dapat dibuat.'; return null; }
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . $name)) { $error = 'File gagal disimpan di server.'; return null; }
    $orig = preg_replace('/[^\w\.\- ()]+/u', '_', basename($f['name']));
    return ['path' => 'assets/uploads/' . $subdir . '/' . $name, 'original' => mb_substr($orig, 0, 200)];
}

/** Hapus file di assets/uploads/<subdir>/ secara aman (path dari database). */
function delete_upload(?string $path, string $subdir): void {
    if (!$path) return;
    $base = realpath(__DIR__ . '/../assets/uploads/' . $subdir);
    $file = realpath(__DIR__ . '/../' . ltrim($path, '/'));
    if ($base && $file && strpos($file, $base . DIRECTORY_SEPARATOR) === 0) @unlink($file);
}

function photo_url(?string $path): ?string {
    $path = trim((string)$path);
    if ($path === '') return null;
    return preg_match('~^https?://~i', $path) ? $path : BASE_URL . '/' . ltrim($path, '/');
}
