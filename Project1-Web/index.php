<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// ========================================
// 1. INFORMASI TERBARU
// ========================================

$infos = $pdo->query("
    SELECT
        i.*,
        k.nama AS kategori
    FROM informasi i
    LEFT JOIN kategori_informasi k
        ON k.id = i.kategori_id
    WHERE i.status = 'published'
    ORDER BY i.tanggal_publikasi DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);


// ========================================
// 2. STATISTIK WEBSITE
// ========================================

$stats = [

    // Hanya menghitung siswa yang mempunyai akun
    'siswa' => (int)$pdo->query("
        SELECT COUNT(*)
        FROM siswa s
        INNER JOIN users u ON u.id = s.user_id
    ")->fetchColumn(),

    'informasi' => (int)$pdo->query("
        SELECT COUNT(*)
        FROM informasi
        WHERE status = 'published'
    ")->fetchColumn(),

    'kegiatan' => (int)$pdo->query("
        SELECT COUNT(*)
        FROM kegiatan
    ")->fetchColumn(),

    'galeri' => (int)$pdo->query("
        SELECT COUNT(*)
        FROM galeri
    ")->fetchColumn()

];


// ========================================
// 3. KEGIATAN TERBARU
// ========================================

$activities = $pdo->query("
    SELECT *
    FROM kegiatan
    ORDER BY tanggal DESC
")->fetchAll(PDO::FETCH_ASSOC);


// ========================================
// 4. MATA PELAJARAN (tentang sekolah)
// ========================================

$mapelSekolah = $pdo->query("
    SELECT nama
    FROM mata_pelajaran
    ORDER BY urutan, id
")->fetchAll(PDO::FETCH_COLUMN);

// ========================================
// DATA SEKOLAH (ubah sesuai data resmi)
// ========================================
$sekolah = [
    'nama'    => 'MAN 2 Padang Panjang',
    'gambar'  => 'assets/images/sekolah.jpg',          // GANTI: taruh fotonya di folder ini
    'situs'   => 'https://man2pp.sch.id/',             // GANTI: URL situs resmi sekolah
    'alamat'  => 'Jl. Pendidikan No.1, Koto Baru, Kec. Sepuluh Koto, Kabupaten Tanah Datar, Sumatera Barat 27151',
    'maps'    => '',                                   // opsional: link Google Maps
];

// Jika file gambar belum ada, kartu gambar disembunyikan otomatis
$adaGambarSekolah = is_file(__DIR__ . '/' . $sekolah['gambar']);
$adaSitus = filter_var($sekolah['situs'], FILTER_VALIDATE_URL) && strpos($sekolah['situs'], 'GANTI') === false;

// ========================================
// 5. PAGE TITLE
// ========================================

$pageTitle = 'STEINS GATE — Preserving Memories';

include __DIR__ . '/includes/header.php';
?>

<!-- ========================================
     NAVIGATION
======================================== -->

<nav class="navbar navbar-expand-lg site-nav sticky-top">

    <div class="container">

        <a class="navbar-brand brand" href="<?= BASE_URL ?>/index.php">
            <?= brand_mark() ?>
            STEINS GATE
        </a>

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#mainNav"
            aria-controls="mainNav"
            aria-expanded="false"
            aria-label="Toggle navigation"
        >
            <i class="bi bi-list"></i>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">

            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-3">

                <li class="nav-item">
                    <a class="nav-link" href="#home">Home</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#about">Tentang</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#sekolah">Sekolah</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#informasi">
                        Informasi
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="#kegiatan">Kegiatan</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/history.php">
                        History
                    </a>
                </li>

                <!-- MENU SISWA BARU -->

                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/siswa.php">
                        Siswa
                    </a>
                </li>

                <!-- THEME TOGGLE -->

                <li class="nav-item">

                    <button
                        class="theme-toggle"
                        data-theme-toggle
                        aria-label="Ganti tema"
                        type="button"
                    >
                        <i class="bi bi-moon-stars"></i>
                    </button>

                </li>

                <?php if (is_logged_in()): ?>

                    <li class="nav-item">
                        <a
                            class="btn btn-brown btn-sm px-3"
                            href="<?= BASE_URL ?>/login.php"
                        >
                            Dashboard
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="btn btn-outline-brown btn-sm px-3"
                            href="<?= BASE_URL ?>/logout.php"
                        >
                            Logout
                        </a>
                    </li>

                <?php else: ?>

                    <li class="nav-item">
                        <a
                            class="btn btn-outline-brown btn-sm px-3"
                            href="<?= BASE_URL ?>/login.php"
                        >
                            Login
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="btn btn-brown btn-sm px-3"
                            href="<?= BASE_URL ?>/register.php"
                        >
                            Register
                        </a>
                    </li>

                <?php endif; ?>

            </ul>

        </div>

    </div>

</nav>


<main>

    <!-- ========================================
         HERO SECTION
    ======================================== -->

    <section class="hero-section" id="home">

        <div class="hero-overlay"></div>

        <div class="container position-relative">

            <div class="row align-items-center min-vh-75">

                <div class="col-lg-8 py-5">

                    <div class="eyebrow">
                        <span></span>
                        DIGITAL CLASS ARCHIVE · IPA 2
                    </div>

                    <h1 class="hero-title">
                        STEINS
                        <br>
                        <em>GATE.</em>
                    </h1>

                    <p class="hero-tagline">
                        Preserving Memories, Connecting Generations.
                    </p>

                    <p class="hero-copy">
                        Sebuah ruang digital untuk mengenang perjalanan,
                        menyimpan dokumentasi, mengelola informasi akademik,
                        dan menjaga cerita kebersamaan keluarga besar
                        IPA 2 MAN 2 Padang Panjang.
                    </p>

                    <div class="d-flex flex-wrap gap-3 mt-4">

                        <a
                            class="btn btn-cream"
                            href="<?= BASE_URL ?>/history.php"
                        >
                            Jelajahi Kenangan
                            <i class="bi bi-arrow-up-right"></i>
                        </a>

                        <a class="btn btn-glass" href="#informasi">
                            Lihat Informasi
                        </a>

                    </div>

                    <div class="hero-meta mt-5">

                        <span>EST. IPA 2</span>

                        <span class="meta-line"></span>

                        <span>MAN 2 PADANG PANJANG</span>

                    </div>

                </div>


                <div class="col-lg-4 d-none d-lg-block">

                    <div class="hero-stamp">

                        <span>ARCHIVE</span>

                        <strong>02</strong>

                        <small>
                            EVERY MOMENT
                            <br>
                            MATTERS
                        </small>

                    </div>

                </div>

            </div>

        </div>

    </section>


    <!-- ========================================
         ABOUT / OUR STORY
    ======================================== -->

    <section class="section-pad" id="about">

        <div class="container">

            <div class="row g-5 align-items-center">

                <div class="col-lg-5">

                    <div class="section-kicker d-block mb-2">
                        01 / OUR STORY
                    </div>

                    <h2 class="section-title">
                        A place to
                        <br>
                        <em>remember.</em>
                    </h2>

                </div>


                <div class="col-lg-7">

                    <p class="lead-copy">
                        STEINS GATE adalah ruang bersama untuk menyimpan
                        cerita dan perjalanan kelas IPA 2 MAN 2
                        Padang Panjang.
                    </p>

                    <p class="muted-copy">
                        Dari kegiatan belajar, agenda sekolah, hingga
                        momen sederhana di ruang kelas—setiap cerita
                        memiliki tempatnya sendiri. Arsip ini dibuat
                        agar kenangan tetap terhubung, kapan pun kita
                        ingin kembali melihatnya.
                    </p>

                    <div class="about-tags">

                        <span>Academic Information</span>

                        <span>Class Management</span>

                        <span>Memory Archive</span>

                    </div>

                </div>

            </div>

        </div>

    </section>


    <!-- ========================================
         TENTANG SEKOLAH
    ======================================== -->

    <section class="section-pad section-soft" id="sekolah">
        <div class="container">

            <div class="row g-5 align-items-end mb-5">
                <div class="col-lg-6 d-flex flex-column align-items-start">
                    <div class="section-kicker d-block mb-2">02 / OUR SCHOOL</div>
                    <h2 class="section-title mb-0">
                        Tentang
                        <br>
                        <em>Sekolah.</em>
                    </h2>
                </div>
                <div class="col-lg-6">
                    <p class="lead-copy mb-0">
                        MAN 2 Padang Panjang adalah madrasah tempat kelas IPA 2
                        belajar, bertumbuh, dan menyimpan cerita bersama.
                    </p>
                </div>
            </div>

            <!-- Gambar + kontak sekolah -->
            <div class="row g-4 mb-1">

                <?php if ($adaGambarSekolah): ?>
                <div class="col-lg-7">
                    <figure class="school-photo h-100 mb-0">
                        <img
                            src="<?= BASE_URL . '/' . e($sekolah['gambar']) ?>"
                            alt="Gedung <?= e($sekolah['nama']) ?>"
                            loading="lazy"
                        >
                        <figcaption><?= e($sekolah['nama']) ?></figcaption>
                    </figure>
                </div>
                <?php endif; ?>

                <div class="<?= $adaGambarSekolah ? 'col-lg-5' : 'col-12' ?>">
                    <div class="info-card h-100">
                        <i class="bi bi-building fs-3 school-icon"></i>
                        <h3>Profil Singkat</h3>

                        <ul class="school-list">
                            <li><span>Nama</span> <?= e($sekolah['nama']) ?></li>
                            <li><span>Kelas</span> IPA 2</li>
                            <li><span>Angkatan</span> 2024</li>
                            <li><span>Alamat</span> <?= e($sekolah['alamat']) ?></li>
                            <?php if ($adaSitus): ?>
                            <li>
                                <span>Situs Sekolah</span>
                                <a href="<?= e($sekolah['situs']) ?>" target="_blank" rel="noopener noreferrer">
                                    <?= e(preg_replace('#^https?://#', '', rtrim($sekolah['situs'], '/'))) ?>
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>

                        <div class="d-flex flex-wrap gap-2 mt-4">
                            <?php if ($adaSitus): ?>
                            <a class="btn btn-brown btn-sm px-3" href="<?= e($sekolah['situs']) ?>" target="_blank" rel="noopener noreferrer">
                                Kunjungi Situs <i class="bi bi-arrow-up-right"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($sekolah['maps'] !== ''): ?>
                            <a class="btn btn-outline-brown btn-sm px-3" href="<?= e($sekolah['maps']) ?>" target="_blank" rel="noopener noreferrer">
                                <i class="bi bi-geo-alt"></i> Lihat di Maps
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Visi & Misi -->
            <div class="row g-4 mt-1">
                <div class="col-lg-6">
                    <div class="info-card h-100">
                        <i class="bi bi-eye fs-3 school-icon"></i>
                        <h3>Visi</h3>
                        <p class="muted-copy mb-0">”Kementerian Agama yang profesional dan handal dalam membangun masyarakat yang saleh, moderat, cerdas dan unggul untuk mewujudkan Indonesia maju yang berdaulat, mandiri dan berkepribadian berdasarkan gotong royong”.</p>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="info-card h-100">
                        <i class="bi bi-flag fs-3 school-icon"></i>
                        <h3>Misi</h3>
                        <ul class="school-list school-list-plain">
                            <li>Meningkatkan kualitas pembelajaran melalui pengembangan kurikulum, profesionalisme personil madrasah.</li>
                            <li>Mengintegrasikan Alquran dan sunnah ke dalam materi pembelajaran.</li>
                            <li>Mempersiapkan kader ulama dan cendikiawan muslim yang dapat menjadi panutan masyarakat, bangsa, dan negara.</li>
                            <li>Menerapkan pembelajaran berbasis bahasa arab dan bahasa asing.</li>
                            <li>Menciptakan suasana disiplin, kondusif, dan islami di lingkungan madrasah.</li>
                            <li>Melengkapi fasilitas pembelajaran dan mengoptimalkan pemanfaatannya.</li>
                            <li>Mempersiapkan peserta didik yang mampu bersaing di Perguruan Tinggi dan dunia kerja.</li>
                            <li>Menjalin kerjasama dengan pihak luar yang terkait dengan pendidikan (stakeholder).</li>
                            <li>Menciptakan lingkungan yang asri, sehat dan nyaman untuk terwujudnya madrasah adiwiyata.</li> 
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Fasilitas / mata pelajaran -->
            <div class="row g-4 mt-1">
                <div class="col-12">
                    <div class="info-card">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h3 class="mb-0 mt-0">Mata Pelajaran</h3>
                            <span class="section-kicker"><?= count($mapelSekolah) ?> MAPEL</span>
                        </div>
                        <div class="about-tags mt-3">
                            <?php foreach ($mapelSekolah as $m): ?>
                                <span><?= e($m) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>


    <!-- ========================================
         STATISTICS
    ======================================== -->

    <section class="stats-band">

        <div class="container">

            <div class="row g-4">

                <?php
                $statItems = [
                    ['siswa', 'Siswa Terdaftar'],
                    ['informasi', 'Informasi Kelas'],
                    ['kegiatan', 'Kegiatan'],
                    ['galeri', 'Dokumentasi']
                ];
                ?>

                <?php foreach ($statItems as $s): ?>

                    <div class="col-6 col-lg-3">

                        <div class="stat-item">

                            <strong>
                                <?= number_format($stats[$s[0]], 0, ',', '.') ?>
                            </strong>

                            <span>
                                <?= e($s[1]) ?>
                            </span>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        </div>

    </section>


    <!-- ========================================
         LATEST INFORMATION
    ======================================== -->

    <section class="section-pad section-soft" id="informasi">

        <div class="container">

            <div class="section-heading">

                <div>

                    <div class="section-kicker">
                        03 / LATEST UPDATE
                    </div>

                    <h2 class="section-title">
                        Informasi
                        <em>terbaru.</em>
                    </h2>

                </div>

                <span class="heading-note">
                    STAY INFORMED · STAY CONNECTED
                </span>

            </div>


            <div class="row g-4">

                <?php if (!$infos): ?>

                    <div class="col-12">

                        <div class="empty-state">
                            Belum ada informasi yang dipublikasikan.
                        </div>

                    </div>

                <?php endif; ?>


<?php foreach ($infos as $i): ?>

    <div class="col-md-6 col-lg-4 d-flex">

        <article class="info-card w-100 h-100 d-flex flex-column">

            <div class="card-topline">

                <span>
                    <?= e($i['kategori'] ?? 'Informasi') ?>
                </span>

                <i class="bi bi-arrow-up-right"></i>

            </div>


            <h3>
                <?= e($i['judul']) ?>
            </h3>


            <p class="flex-grow-1">
                <?= e(
                    mb_strimwidth(
                        strip_tags($i['isi']),
                        0,
                        125,
                        '…'
                    )
                ) ?>
            </p>


            <div class="card-bottom mt-auto">

                <time>
                    <?= date(
                        'd M Y',
                        strtotime($i['tanggal_publikasi'])
                    ) ?>
                </time>

                <a href="<?= BASE_URL ?>/detail.php?id=<?= (int)$i['id'] ?>">
                    Baca selengkapnya
                    <i class="bi bi-arrow-right"></i>
                </a>

            </div>

        </article>

    </div>

<?php endforeach; ?>

            </div>

        </div>

    </section>


    <!-- ========================================
         CLASS ACTIVITIES
    ======================================== -->

    <section class="section-pad" id="kegiatan">

        <div class="container">

            <div class="section-heading">

                <div>

                    <div class="section-kicker">
                        04 / CLASS ACTIVITIES
                    </div>

                    <h2 class="section-title">
                        Moments in
                        <em>motion.</em>
                    </h2>

                </div>

                <a
                    class="text-link"
                    href="<?= BASE_URL ?>/history.php"
                >
                    Lihat perjalanan
                    <i class="bi bi-arrow-up-right"></i>
                </a>

            </div>


            <div class="row g-4">

                <?php foreach ($activities as $a): ?>

                    <div class="col-md-4">

                        <div class="activity-card">

                            <div class="activity-number">
                                <?= date('d', strtotime($a['tanggal'])) ?>
                            </div>

                            <div>

                                <span>
                                    <?= date('F Y', strtotime($a['tanggal'])) ?>
                                </span>

                                <h3>
                                    <?= e($a['judul']) ?>
                                </h3>

                                <p>
                                    <?= e($a['deskripsi']) ?>
                                </p>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>


                <?php if (!$activities): ?>

                    <div class="col-12">

                        <div class="empty-state">
                            Dokumentasi kegiatan akan hadir di sini.
                        </div>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </section>


    <!-- ========================================
         STUDENTS DIRECTORY CTA
    ======================================== -->

    <section class="memory-cta">

        <div class="container">

            <div class="row align-items-center">

                <div class="col-lg-8">

                    <div class="section-kicker">
                        05 / CLASS MEMBERS DIRECTORY
                    </div>

                    <h2>
                        Every face tells a story,
                        <br>
                        <em>every student belongs.</em>
                    </h2>

                    <p class="mt-3 mb-0" style="color: #C4B3A9;">
                        Kenali anggota keluarga besar IPA 2
                        melalui galeri profil siswa STEINS GATE.
                    </p>

                </div>


                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">

                    <a
                        href="<?= BASE_URL ?>/siswa.php"
                        class="btn btn-cream"
                    >
                        Lihat Semua Siswa
                        <i class="bi bi-arrow-up-right"></i>
                    </a>

                </div>

            </div>

        </div>

    </section>


    <!-- ========================================
         MEMORY ARCHIVE CTA
    ======================================== -->

    <section class="memory-cta">

        <div class="container">

            <div class="row align-items-center">

                <div class="col-lg-8">

                    <div class="section-kicker">
                        06 / DIGITAL MEMORY ARCHIVE
                    </div>

                    <h2>
                        We may have left the classroom,
                        <br>
                        <em>but the memories remain.</em>
                    </h2>

                </div>


                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">

                    <a
                        href="<?= BASE_URL ?>/history.php"
                        class="btn btn-cream"
                    >
                        Buka Memory Archive
                        <i class="bi bi-arrow-up-right"></i>
                    </a>

                </div>

            </div>

        </div>

    </section>

</main>


<?php include __DIR__ . '/includes/footer.php'; ?>