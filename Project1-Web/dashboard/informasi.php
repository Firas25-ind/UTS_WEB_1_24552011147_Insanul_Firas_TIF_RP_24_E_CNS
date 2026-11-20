<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/admin_crud_helpers.php';
require_role(['admin', 'guru', 'siswa']);

$u = current_user();
$isAdmin = $u['role'] === 'admin';
$errors = [];
$edit = null;
$cats = $pdo->query("SELECT id, nama FROM kategori_informasi ORDER BY nama")->fetchAll();

// ---------- CRUD (admin) ----------
if ($isAdmin && isset($_GET['edit'])) {
    $q = $pdo->prepare('SELECT * FROM informasi WHERE id=?');
    $q->execute([(int)$_GET['edit']]);
    $edit = $q->fetch(PDO::FETCH_ASSOC) ?: null;
}
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    sg_verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'delete') {
        $q = $pdo->prepare('SELECT gambar FROM informasi WHERE id=?');
        $q->execute([$id]);
        $old = $q->fetchColumn();
        $pdo->prepare('DELETE FROM informasi WHERE id=?')->execute([$id]);
        delete_upload($old ?: null, 'informasi');
        sg_flash_redirect('Informasi berhasil dihapus.', BASE_URL . '/dashboard/informasi.php');
    }

    $judul = trim($_POST['judul'] ?? '');
    $isi = trim($_POST['isi'] ?? '');
    $kategori = (int)($_POST['kategori_id'] ?? 0);
    $tanggal = $_POST['tanggal_publikasi'] ?? '';
    $status = $_POST['status'] ?? 'published';
    $penulis = trim($_POST['penulis'] ?? '') ?: 'Admin STEINS GATE';

    if ($judul === '' || $isi === '' || !$tanggal) $errors[] = 'Judul, isi, dan tanggal publikasi wajib diisi.';
    if (!in_array($status, ['draft', 'published'], true)) $errors[] = 'Status tidak valid.';
    if ($kategori && !in_array($kategori, array_map('intval', array_column($cats, 'id')), true)) $errors[] = 'Kategori tidak valid.';

    $oldImg = null;
    if ($action === 'update') {
        $q = $pdo->prepare('SELECT gambar FROM informasi WHERE id=?');
        $q->execute([$id]);
        $oldImg = $q->fetchColumn() ?: null;
    }
    $gambar = $oldImg;
    $err = '';
    $up = handle_upload($_FILES['gambar'] ?? null, 'informasi', upload_allowed_types('gambar'), 5 * 1024 * 1024, $err);
    if ($err !== '') $errors[] = $err;
    if ($up) $gambar = $up['path'];

    if (!$errors) {
        if ($action === 'update') {
            $pdo->prepare('UPDATE informasi SET kategori_id=?,judul=?,isi=?,gambar=?,penulis=?,status=?,tanggal_publikasi=? WHERE id=?')
                ->execute([$kategori ?: null, $judul, $isi, $gambar, $penulis, $status, $tanggal, $id]);
            if ($up && $oldImg) delete_upload($oldImg, 'informasi');
        } else {
            $pdo->prepare('INSERT INTO informasi(kategori_id,judul,isi,gambar,penulis,status,tanggal_publikasi) VALUES(?,?,?,?,?,?,?)')
                ->execute([$kategori ?: null, $judul, $isi, $gambar, $penulis, $status, $tanggal]);
        }
        sg_flash_redirect('Informasi berhasil disimpan' . ($status === 'published' ? ' dan tampil di halaman awal.' : ' sebagai draft.'), BASE_URL . '/dashboard/informasi.php');
    }
    if ($up) delete_upload($up['path'], 'informasi'); // simpan gagal: buang file baru
    $edit = ['id' => $id, 'judul' => $judul, 'isi' => $isi, 'kategori_id' => $kategori, 'tanggal_publikasi' => $tanggal,
             'status' => $status, 'penulis' => $penulis, 'gambar' => $oldImg];
}

// ---------- Daftar ----------
$where = $isAdmin ? '' : "WHERE i.status = 'published'";
$infos = $pdo->query("
    SELECT i.*, k.nama AS kategori
    FROM informasi i
    LEFT JOIN kategori_informasi k ON k.id = i.kategori_id
    $where
    ORDER BY i.tanggal_publikasi DESC, i.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Informasi Kelas — STEINS GATE';
include __DIR__ . '/../includes/dashboard_page_start.php';
$editing = $edit && !empty($edit['id']);
?>
<div class="page-heading">
    <div>
        <div class="section-kicker">CLASS INFORMATION</div>
        <h1>Informasi <em>Kelas.</em></h1>
        <p><?= $isAdmin ? 'Kelola informasi. Yang berstatus Published tampil di halaman awal publik.' : 'Pusat informasi dan kabar terbaru keluarga besar IPA 2 MAN 2 Padang Panjang.' ?></p>
    </div>
    <?php if ($isAdmin): ?><a class="btn btn-brown" href="#form-informasi"><i class="bi bi-plus-lg me-1"></i>Tambah Informasi</a><?php endif; ?>
</div>

<?php sg_show_flash(); foreach ($errors as $er): ?><div class="alert alert-danger"><?= e($er) ?></div><?php endforeach; ?>

<?php if ($isAdmin): ?>
<div class="sg-panel mb-4" id="form-informasi">
    <h3><?= $editing ? 'Ubah Informasi' : 'Tambah Informasi' ?></h3>
    <form method="post" enctype="multipart/form-data" class="row g-3">
        <input type="hidden" name="csrf_token" value="<?= e(sg_csrf_token()) ?>">
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><?php endif; ?>
        <div class="col-md-8"><label class="form-label">Judul</label><input name="judul" maxlength="180" class="form-control custom-input" required value="<?= e($edit['judul'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Kategori</label>
            <select name="kategori_id" class="form-select custom-input"><option value="">Tanpa kategori</option>
                <?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)($edit['kategori_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['nama']) ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-4"><label class="form-label">Tanggal Publikasi</label><input type="date" name="tanggal_publikasi" class="form-control custom-input" required value="<?= e($edit['tanggal_publikasi'] ?? date('Y-m-d')) ?>"></div>
        <div class="col-md-4"><label class="form-label">Status</label>
            <select name="status" class="form-select custom-input">
                <option value="published" <?= ($edit['status'] ?? 'published') === 'published' ? 'selected' : '' ?>>Published (tampil di halaman awal)</option>
                <option value="draft" <?= ($edit['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft (disembunyikan)</option>
            </select></div>
        <div class="col-md-4"><label class="form-label">Penulis</label><input name="penulis" maxlength="100" class="form-control custom-input" value="<?= e($edit['penulis'] ?? $u['nama'] ?? 'Admin STEINS GATE') ?>"></div>
        <div class="col-12"><label class="form-label">Isi Informasi</label><textarea name="isi" rows="6" class="form-control custom-input" required><?= e($edit['isi'] ?? '') ?></textarea></div>
        <div class="col-12"><label class="form-label">Gambar sampul (opsional, JPG/PNG/WEBP maks. 5 MB)<?= $editing && !empty($edit['gambar']) ? ' — kosongkan jika tidak diganti' : '' ?></label>
            <input type="file" name="gambar" accept=".jpg,.jpeg,.png,.webp" class="form-control custom-input"></div>
        <div class="col-12"><button class="btn btn-brown">Simpan</button> <?php if ($editing): ?><a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/dashboard/informasi.php">Batal</a><?php endif; ?></div>
    </form>
</div>
<?php endif; ?>

<div class="sg-panel mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div><div class="section-kicker">INFORMATION ARCHIVE</div><h4 class="mb-1">Arsip Informasi Kelas</h4>
            <p class="text-muted mb-0"><?= $isAdmin ? 'Semua informasi, termasuk draft.' : 'Daftar informasi yang telah dipublikasikan.' ?></p></div>
        <div class="text-end"><h2 class="mb-0"><?= count($infos) ?></h2><small class="text-muted">Informasi</small></div>
    </div>
</div>

<div class="row g-4">
<?php if (!$infos): ?>
    <div class="col-12"><div class="sg-panel text-center py-5"><i class="bi bi-inbox fs-1 mb-3"></i><h4>Belum Ada Informasi</h4><p class="text-muted mb-0">Informasi kelas akan tampil setelah dipublikasikan oleh admin.</p></div></div>
<?php else: foreach ($infos as $i): ?>
    <div class="col-md-6 col-xl-4">
        <article class="info-card h-100">
            <div class="card-topline">
                <span><?= e($i['kategori'] ?? 'Informasi') ?></span>
                <?php if ($isAdmin): ?><span class="sg-badge <?= $i['status'] === 'draft' ? 'sg-badge-warn' : '' ?>"><?= e($i['status']) ?></span><?php else: ?><i class="bi bi-arrow-up-right"></i><?php endif; ?>
            </div>
            <h3><?= e($i['judul']) ?></h3>
            <p><?= e(mb_strimwidth(strip_tags($i['isi']), 0, 180, '…')) ?></p>
            <div class="card-bottom">
                <time><?= date('d M Y', strtotime($i['tanggal_publikasi'])) ?></time>
                <?php if ($i['status'] === 'published'): ?><a href="<?= BASE_URL ?>/detail.php?id=<?= (int)$i['id'] ?>&from=dashboard">Baca selengkapnya <i class="bi bi-arrow-right"></i></a><?php endif; ?>
            </div>
            <?php if ($isAdmin): ?>
            <div class="mt-3 d-flex gap-2">
                <a class="btn btn-sm btn-outline-secondary" href="?edit=<?= (int)$i['id'] ?>#form-informasi">Edit</a>
                <form method="post" onsubmit="return confirm('Hapus informasi ini?')">
                    <input type="hidden" name="csrf_token" value="<?= e(sg_csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger">Hapus</button>
                </form>
            </div>
            <?php endif; ?>
        </article>
    </div>
<?php endforeach; endif; ?>
</div>
<?php include __DIR__ . '/../includes/dashboard_page_end.php'; ?>
