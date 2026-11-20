<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Ambil data history
$items = $pdo->query("
    SELECT *
    FROM history
    ORDER BY tanggal ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Ambil foto galeri
$photos = $pdo->query("
    SELECT *
    FROM galeri
    ORDER BY tanggal_dokumentasi DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// Fungsi menentukan lokasi gambar
function history_image_url($gambar) {
    $gambar = trim((string)$gambar);

    if ($gambar === '') {
        return BASE_URL . '/assets/images/placeholder.jpg';
    }

    // Jika gambar menggunakan URL eksternal
    if (preg_match('~^https?://~i', $gambar)) {
        return $gambar;
    }

    // Jika database sudah menyimpan path lengkap
    if (str_starts_with($gambar, 'assets/')) {
        return BASE_URL . '/' . ltrim($gambar, '/');
    }

    // Jika hanya nama file, gunakan folder gambar lama
    return BASE_URL . '/assets/images/' . rawurlencode(basename($gambar));
}

$pageTitle = 'Our History — STEINS GATE';

include __DIR__ . '/includes/header.php';
?>

<!-- NAVIGATION -->
<nav class="navbar site-nav">
    <div class="container">
        <a class="navbar-brand brand" href="index.php">
            <?= brand_mark() ?> STEINS GATE
        </a>

        <a href="index.php" class="text-link">
            ← Home
        </a>
    </div>
</nav>

<!-- HISTORY HERO -->
<section class="history-hero">
    <div class="container">
        <div class="section-kicker">
            DIGITAL MEMORY ARCHIVE / 05
        </div>

        <h1>Our <em>History.</em></h1>

        <p>A Thousand Memories, One Story.</p>

        <span>Every Moment Has Its Own Timeline.</span>
    </div>
</section>

<!-- TIMELINE HISTORY -->
<section class="section-pad">
    <div class="container narrow-container">

        <div class="timeline">

            <?php foreach ($items as $it): ?>

                <article class="timeline-item">

                    <div class="timeline-dot"></div>

                    <time>
                        <?= date('Y', strtotime($it['tanggal'])) ?>
                        ·
                        <?= date('d M', strtotime($it['tanggal'])) ?>
                    </time>

                    <h3><?= e($it['judul']) ?></h3>

                    <p><?= e($it['deskripsi']) ?></p>

                </article>

            <?php endforeach; ?>

            <?php if (!$items): ?>

                <div class="empty-state">
                    Timeline kenangan belum tersedia.
                    Tambahkan dokumentasi melalui database.
                </div>

            <?php endif; ?>

        </div>
    </div>
</section>

<!-- MEMORY GALLERY -->
<section class="section-pad section-soft">

    <div class="container">

        <div class="section-kicker">
            MEMORY GALLERY
        </div>

        <h2 class="section-title">
            Captured <em>moments.</em>
        </h2>

        <div class="row g-3 mt-3">

            <?php foreach ($photos as $p): ?>

                <?php
                    $imageUrl = history_image_url($p['gambar']);
                ?>

                <div class="col-6 col-lg-3">

                    
<div class="memory-tile">

    <a
        href="<?= e($imageUrl) ?>"
        target="_blank"
        rel="noopener noreferrer"
        class="memory-image-link"
        title="Lihat foto asli"
    >
        <img
            src="<?= e($imageUrl) ?>"
            alt="<?= e($p['judul']) ?>"
            loading="lazy"
        >
    </a>

    <div>
        <strong><?= e($p['judul']) ?></strong>
        <small><?= e($p['kategori'] ?? 'Memory') ?></small>
    </div>

</div>

                </div>

            <?php endforeach; ?>

        </div>

        <?php if (!$photos): ?>

            <p class="muted-copy mt-4">
                Belum ada foto. Dokumentasi kelas akan
                ditampilkan setelah foto tersedia.
            </p>

        <?php endif; ?>

    </div>

</section>

<!-- QUOTE -->
<section class="quote-band">
    <div class="container">
        <blockquote>
            “We may have left the classroom, but the memories will always remain.”
        </blockquote>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
