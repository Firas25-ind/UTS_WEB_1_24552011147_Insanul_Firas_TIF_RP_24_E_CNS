<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

require_login();

$u = current_user();
$role = $u['role'] ?? 'siswa';
$namaUser = $u['nama'] ?? $u['username'] ?? 'Pengguna';

$pageTitle = $pageTitle ?? 'STEINS GATE';
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');

// Jumlah feedback yang belum dibalas (lencana di menu admin). Aman jika tabel belum dimigrasi.
$fbBaru = 0;
if ($role === 'admin') {
    try { $fbBaru = (int)$pdo->query("SELECT COUNT(*) FROM feedback WHERE status='baru'")->fetchColumn(); } catch (Throwable $e) { $fbBaru = 0; }
}

include __DIR__ . '/header.php';
?>

<div class="dashboard-shell" id="dashShell">
<script>try{if(localStorage.getItem('sg-sidebar')==='hidden')document.getElementById('dashShell').classList.add('sidebar-hidden');}catch(e){}</script>

    <!-- SIDEBAR -->
    <aside class="dash-sidebar">

        <!-- BRAND -->
        <a class="brand" href="<?= BASE_URL ?>/index.php">
            <?= brand_mark() ?>
            STEINS GATE
        </a>

        <!-- WORKSPACE -->
        <div class="sidebar-label">
            WORKSPACE
        </div>

        <?php if ($role === 'admin'): ?>
            <div class="sidebar-label mt-3">
                ADMINISTRATION
            </div>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
            <a class="side-link<?= $currentScript === 'index.php' ? ' active' : '' ?>" href="<?= BASE_URL ?>/dashboard/index.php"><i class="bi bi-grid"></i> Dashboard Admin</a>
            <a class="side-link<?= $currentScript === 'siswa.php' ? ' active' : '' ?>" href="<?= BASE_URL ?>/dashboard/siswa.php"><i class="bi bi-people"></i> Data Siswa</a>
            <a class="side-link<?= $currentScript === 'guru.php' ? ' active' : '' ?>" href="<?= BASE_URL ?>/dashboard/guru.php"><i class="bi bi-person-workspace"></i> Data Guru</a>
            <a class="side-link<?= $currentScript === 'informasi.php' ? ' active' : '' ?>" href="<?= BASE_URL ?>/dashboard/informasi.php"><i class="bi bi-journal-text"></i> Informasi Kelas</a>
            <a class="side-link<?= $currentScript === 'jadwal.php' ? ' active' : '' ?>" href="<?= BASE_URL ?>/dashboard/jadwal.php"><i class="bi bi-calendar3"></i> Jadwal Pelajaran</a>
            <a class="side-link<?= $currentScript === 'kegiatan.php' ? ' active' : '' ?>" href="<?= BASE_URL ?>/dashboard/kegiatan.php"><i class="bi bi-calendar-event"></i> Agenda Kegiatan</a>
            <a class="side-link<?= $currentScript === 'pengumuman.php' ? ' active' : '' ?>" href="<?= BASE_URL ?>/dashboard/pengumuman.php"><i class="bi bi-megaphone"></i> Pengumuman</a>
            <a class="side-link<?= $currentScript === 'galeri.php' ? ' active' : '' ?>" href="<?= BASE_URL ?>/dashboard/galeri.php"><i class="bi bi-images"></i> Galeri Kenangan</a>
            <a class="side-link<?= $currentScript === 'feedback.php' ? ' active' : '' ?>" href="<?= BASE_URL ?>/dashboard/feedback.php"><i class="bi bi-chat-left-text"></i> Feedback<?php if ($fbBaru > 0): ?> <span class="side-badge"><?= $fbBaru ?></span><?php endif; ?></a>
        <?php elseif ($role === 'guru'): ?>
            <a class="side-link<?= $currentScript === 'index.php' ? ' active' : '' ?>" href="<?= BASE_URL ?>/guru/index.php"><i class="bi bi-grid"></i> Dashboard Guru</a>
            <a class="side-link<?= $currentScript === 'profil.php' ? ' active' : '' ?>" href="<?= BASE_URL ?>/guru/profil.php"><i class="bi bi-person-badge"></i> Profil Guru</a>
            <a class="side-link<?= $currentScript === 'tugas.php' ? ' active' : '' ?>" href="<?= BASE_URL ?>/guru/tugas.php"><i class="bi bi-journal-plus"></i> Buat Tugas</a>
            <a class="side-link<?= $currentScript === 'absensi.php' ? ' active' : '' ?>" href="<?= BASE_URL ?>/guru/absensi.php"><i class="bi bi-clipboard-check"></i> Absensi Kehadiran</a>
            <a class="side-link<?= $currentScript === 'informasi.php' ? ' active' : '' ?>" href="<?= BASE_URL ?>/dashboard/informasi.php"><i class="bi bi-journal-text"></i> Informasi Kelas</a>
            <a class="side-link<?= $currentScript === 'jadwal.php' ? ' active' : '' ?>" href="<?= BASE_URL ?>/dashboard/jadwal.php"><i class="bi bi-calendar3"></i> Jadwal Pelajaran</a>
            <a class="side-link<?= $currentScript === 'feedback.php' ? ' active' : '' ?>" href="<?= BASE_URL ?>/dashboard/feedback.php"><i class="bi bi-chat-left-text"></i> Feedback ke Admin</a>
        <?php else: ?>
            <a class="side-link<?= $currentScript === 'index.php' ? ' active' : '' ?>" href="<?= BASE_URL ?>/siswa/index.php"><i class="bi bi-grid"></i> Dashboard Siswa</a>
            <a class="side-link<?= $currentScript === 'profil.php' ? ' active' : '' ?>" href="<?= BASE_URL ?>/dashboard/profil.php"><i class="bi bi-person"></i> Profil Siswa</a>
            <a class="side-link<?= $currentScript === 'tugas.php' ? ' active' : '' ?>" href="<?= BASE_URL ?>/siswa/tugas.php"><i class="bi bi-journal-text"></i> Tugas</a>
            <a class="side-link<?= $currentScript === 'absensi.php' ? ' active' : '' ?>" href="<?= BASE_URL ?>/siswa/absensi.php"><i class="bi bi-clipboard-check"></i> Absensi</a>
            <a class="side-link<?= $currentScript === 'informasi.php' ? ' active' : '' ?>" href="<?= BASE_URL ?>/dashboard/informasi.php"><i class="bi bi-megaphone-fill"></i> Informasi Kelas</a>
            <a class="side-link<?= $currentScript === 'jadwal.php' ? ' active' : '' ?>" href="<?= BASE_URL ?>/dashboard/jadwal.php"><i class="bi bi-calendar3"></i> Jadwal Pelajaran</a>
            <a class="side-link<?= $currentScript === 'kegiatan.php' ? ' active' : '' ?>" href="<?= BASE_URL ?>/dashboard/kegiatan.php"><i class="bi bi-calendar-event"></i> Agenda Kegiatan</a>
            <a class="side-link<?= $currentScript === 'pengumuman.php' ? ' active' : '' ?>" href="<?= BASE_URL ?>/dashboard/pengumuman.php"><i class="bi bi-megaphone"></i> Pengumuman</a>
            <a class="side-link<?= $currentScript === 'galeri.php' ? ' active' : '' ?>" href="<?= BASE_URL ?>/dashboard/galeri.php"><i class="bi bi-images"></i> Galeri Kenangan</a>
            <a class="side-link<?= $currentScript === 'feedback.php' ? ' active' : '' ?>" href="<?= BASE_URL ?>/dashboard/feedback.php"><i class="bi bi-chat-left-text"></i> Feedback ke Admin</a>
        <?php endif; ?>

        <!-- LOGOUT -->
        <div class="sidebar-bottom">

            <a
                class="side-link"
                href="<?= BASE_URL ?>/logout.php"
            >
                <i class="bi bi-box-arrow-left"></i>
                Logout
            </a>

        </div>

    </aside>


    <!-- MAIN CONTENT -->
    <section class="dash-main">

        <!-- TOPBAR -->
        <header class="dash-topbar">

            <div class="d-flex align-items-center gap-3">
                <!-- SEMBUNYIKAN / TAMPILKAN MENU -->
                <button class="sidebar-toggle" data-sidebar-toggle type="button" aria-label="Sembunyikan atau tampilkan menu" aria-expanded="true" title="Sembunyikan / tampilkan menu">
                    <i class="bi bi-layout-sidebar-inset"></i>
                </button>
                <span class="eyebrow">
                    CLASS MANAGEMENT SYSTEM
                </span>
            </div>

            <div class="d-flex align-items-center gap-3">

                <!-- THEME TOGGLE -->
                <button
                    class="theme-toggle"
                    data-theme-toggle
                    type="button"
                    aria-label="Ganti tema"
                >
                    <i class="bi bi-moon-stars"></i>
                </button>

                <!-- USER INFORMATION -->
                <div class="user-chip">

                    <span class="avatar">
                        <?= e(strtoupper(substr($namaUser, 0, 1))) ?>
                    </span>

                    <span>
                        <strong>
                            <?= e($namaUser) ?>
                        </strong>

                        <small>
                            <?= e(strtoupper($role)) ?>
                        </small>
                    </span>

                </div>

            </div>

        </header>


        <!-- PAGE CONTENT -->
        <main class="dash-content">