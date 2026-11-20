<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Ambil seluruh siswa yang memiliki akun
$stmt = $pdo->query("
    SELECT
        s.id,
        s.nama_lengkap,
        s.foto_profil,
        s.angkatan,
        s.jabatan
    FROM siswa s
    INNER JOIN users u ON u.id = s.user_id
    WHERE u.role = 'siswa'
    ORDER BY s.nama_lengkap ASC
");


$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Menentukan URL foto profil
function student_photo_url($photo) {
    $photo = trim((string)$photo);

    if ($photo === '') {
        return BASE_URL . '/assets/images/default-profile.png';
    }

    if (preg_match('~^https?://~i', $photo)) {
        return $photo;
    }

    if (strpos($photo, 'assets/') === 0) {
        return BASE_URL . '/' . ltrim($photo, '/');
    }

    return BASE_URL . '/assets/uploads/profil/' . rawurlencode(basename($photo));
}

$pageTitle = 'Siswa — STEINS GATE';

include __DIR__ . '/includes/header.php';
?>

<!-- NAVBAR -->
<nav class="navbar site-nav">
    <div class="container">
        <a class="navbar-brand brand" href="index.php">
            <?= brand_mark() ?> STEINS GATE
        </a>

        <div class="d-flex align-items-center gap-3">
            <a href="index.php" class="text-link">Home</a>
            <a href="history.php" class="text-link">History</a>
            <a href="siswa.php" class="text-link">Siswa</a>
        </div>
    </div>
</nav>

<!-- HERO -->
<section class="students-hero">
    <div class="container">
        <div class="section-kicker">DIGITAL CLASS DIRECTORY / 06</div>

        <h1>Our <em>Students.</em></h1>

        <p>
            Every Face Has a Story, Every Student Is Part of Our Journey.
        </p>

        <div class="students-total">
            <span><?= count($students) ?></span>
            <small>REGISTERED STUDENTS</small>
        </div>
    </div>
</section>

<!-- STUDENT GALLERY -->
<section class="section-pad section-soft">
    <div class="container">

        <div class="section-kicker">CLASS MEMBERS</div>

        <h2 class="section-title">
            Meet Our <em>Family.</em>
        </h2>

        <p class="muted-copy mb-4">
            Mengenal anggota keluarga besar kelas IPA 2
            melalui dokumentasi profil masing-masing siswa.
        </p>

        <?php if ($students): ?>

            <div class="row g-4">

                <?php foreach ($students as $student): ?>

                    <?php
                        $photo = student_photo_url($student['foto_profil']);
                    ?>

                    <div class="col-6 col-md-4 col-lg-3">

                        <article class="student-card">

                            <a
                                href="<?= !empty($student['foto_profil']) ? e($photo) : '#' ?>"
                                <?= !empty($student['foto_profil']) ? 'target="_blank" rel="noopener noreferrer"' : 'tabindex="-1" aria-disabled="true"' ?>
                                class="student-photo-link"
                                title="<?= !empty($student['foto_profil']) ? 'Lihat foto asli' : 'Belum ada foto' ?>"
                            >
                                <?php if (!empty($student['foto_profil'])): ?>

                                    <img
                                        src="<?= e($photo) ?>"
                                        alt="Foto <?= e($student['nama_lengkap']) ?>"
                                        class="student-photo"
                                        loading="lazy"
                                    >

                                <?php else: ?>

                                    <div class="student-photo-placeholder">
                                        <i class="bi bi-person-fill"></i>
                                    </div>

                                <?php endif; ?>

                                <span class="student-zoom">
                                    <i class="bi bi-zoom-in"></i>
                                </span>
                            </a>

                            <div class="student-info">

                                <h3>
                                    <?= e($student['nama_lengkap']) ?>
                                </h3>

                                <span>
                                    <?= e($student['jabatan'] ?: 'Anggota') ?>
                                </span>

                                <small>
                                    Angkatan <?= e($student['angkatan']) ?>
                                </small>

                            </div>

                        </article>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-state">
                <i class="bi bi-people fs-1 d-block mb-3"></i>

                <h4>Belum Ada Data Siswa</h4>

                <p>
                    Profil siswa akan ditampilkan setelah akun
                    siswa terdaftar.
                </p>
            </div>

        <?php endif; ?>

    </div>
</section>

<!-- QUOTE -->
<section class="quote-band">
    <div class="container">
        <blockquote>
            “Different Faces, One Class, Countless Memories.”
        </blockquote>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
