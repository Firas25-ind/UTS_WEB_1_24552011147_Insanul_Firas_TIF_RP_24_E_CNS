<?php

// ========================================
// 1. KONFIGURASI DAN DATABASE
// ========================================

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';


// ========================================
// 2. VALIDASI ID INFORMASI
// ========================================

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$info = null;

if ($id !== false && $id !== null && $id > 0) {

    $stmt = $pdo->prepare("
        SELECT
            i.*,
            k.nama AS kategori
        FROM informasi i
        LEFT JOIN kategori_informasi k
            ON k.id = i.kategori_id
        WHERE i.id = ?
          AND i.status = 'published'
        LIMIT 1
    ");

    $stmt->execute([$id]);

    $info = $stmt->fetch(PDO::FETCH_ASSOC);
}


// ========================================
// 3. MENENTUKAN ASAL PENGGUNA
// ========================================

// Jika berasal dari dashboard siswa
$fromDashboard = (
    ($_GET['from'] ?? '') === 'dashboard'
);

// Tentukan alamat kembali berdasarkan asal halaman
if ($fromDashboard) {

    $backUrl = BASE_URL . '/dashboard/informasi.php';

    $backLabel = 'Kembali ke Informasi Kelas';

} else {

    $backUrl = BASE_URL . '/index.php#informasi';

    $backLabel = 'Kembali ke informasi';

}


// ========================================
// 4. JUDUL HALAMAN
// ========================================

$pageTitle = $info
    ? $info['judul'] . ' — STEINS GATE'
    : 'Informasi Tidak Ditemukan — STEINS GATE';


// ========================================
// 5. FUNGSI PATH GAMBAR
// ========================================

function informasiImageUrl($gambar)
{
    if (empty($gambar)) {
        return null;
    }

    // Jika gambar menggunakan URL eksternal
    if (preg_match('~^https?://~i', $gambar)) {
        return $gambar;
    }

    // Jika path sudah dimulai dengan assets/
    if (strpos($gambar, 'assets/') === 0) {
        return BASE_URL . '/' . $gambar;
    }

    // Jika hanya nama file
    return BASE_URL . '/assets/images/' . $gambar;
}


// ========================================
// 6. HEADER
// ========================================

include __DIR__ . '/includes/header.php';

?>

<!-- ========================================
     NAVIGATION
======================================== -->

<nav class="navbar site-nav">

    <div class="container">

        <!-- BRAND -->
        <a
            class="navbar-brand brand"
            href="<?= BASE_URL ?>/index.php"
        >
            <?= brand_mark() ?>
            STEINS GATE
        </a>

        <!-- TOMBOL KEMBALI -->
        <a
            href="<?= e($backUrl) ?>"
            class="text-link"
        >
            <i class="bi bi-arrow-left"></i>
            <?= e($backLabel) ?>
        </a>

    </div>

</nav>


<!-- ========================================
     DETAIL INFORMASI
======================================== -->

<main class="detail-wrap">

    <div class="container narrow-container">

        <?php if (!$info): ?>

            <!-- ========================================
                 INFORMASI TIDAK DITEMUKAN
            ======================================== -->

            <div class="not-found">

                <span>
                    404 / ARCHIVE NOT FOUND
                </span>

                <h1>
                    Informasi tidak
                    <br>
                    <em>ditemukan.</em>
                </h1>

                <p>
                    Informasi mungkin telah dihapus,
                    belum dipublikasikan, atau alamat
                    yang kamu buka tidak valid.
                </p>

                <a
                    href="<?= e($backUrl) ?>"
                    class="btn btn-brown"
                >
                    <i class="bi bi-arrow-left me-1"></i>
                    <?= e($backLabel) ?>
                </a>

            </div>

        <?php else: ?>

            <!-- ========================================
                 KATEGORI INFORMASI
            ======================================== -->

            <div class="section-kicker">

                CLASS INFORMATION /

                <?= e(strtoupper($info['kategori'] ?? 'GENERAL')) ?>

            </div>


            <!-- ========================================
                 JUDUL INFORMASI
            ======================================== -->

            <h1 class="detail-title">
                <?= e($info['judul']) ?>
            </h1>


            <!-- ========================================
                 METADATA INFORMASI
            ======================================== -->

            <div class="detail-meta">

                <span>
                    <i class="bi bi-calendar3"></i>

                    <?= date(
                        'd F Y',
                        strtotime($info['tanggal_publikasi'])
                    ) ?>
                </span>

                <span>
                    <i class="bi bi-person"></i>

                    <?= e(
                        $info['penulis'] ?? 'Admin STEINS GATE'
                    ) ?>
                </span>

            </div>


            <!-- ========================================
                 GAMBAR INFORMASI
            ======================================== -->

            <?php if (!empty($info['gambar'])): ?>

                <img
                    class="detail-cover"
                    src="<?= e(informasiImageUrl($info['gambar'])) ?>"
                    alt="<?= e($info['judul']) ?>"
                    loading="lazy"
                >

            <?php endif; ?>


            <!-- ========================================
                 ISI INFORMASI
            ======================================== -->

            <article class="detail-content">

                <?= nl2br(e($info['isi'])) ?>

            </article>


            <!-- ========================================
                 TOMBOL KEMBALI
            ======================================== -->

            <a
                href="<?= e($backUrl) ?>"
                class="btn btn-outline-brown mt-4"
            >
                <i class="bi bi-arrow-left me-1"></i>
                <?= e($backLabel) ?>
            </a>

        <?php endif; ?>

    </div>

</main>


<!-- ========================================
     FOOTER
======================================== -->

<?php include __DIR__ . '/includes/footer.php'; ?>
