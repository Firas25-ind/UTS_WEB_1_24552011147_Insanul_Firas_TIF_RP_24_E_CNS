-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 20 Nov 2026 pada 16.33
-- Versi server: 10.11.16-MariaDB
-- Versi PHP: 8.5.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Basis data: `steins_gate_db`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `absensi`
--

CREATE TABLE `absensi` (
  `id` int(11) NOT NULL,
  `sesi_id` int(11) NOT NULL,
  `guru_id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `kelas` enum('10','11','12') NOT NULL,
  `status` enum('Hadir','Izin','Sakit','Alpa') NOT NULL DEFAULT 'Hadir',
  `keterangan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `absensi`
--

INSERT INTO `absensi` (`id`, `sesi_id`, `guru_id`, `siswa_id`, `tanggal`, `kelas`, `status`, `keterangan`, `created_at`, `updated_at`) VALUES
(2, 2, 2, 4, '2026-11-20', '12', 'Izin', NULL, '2026-11-20 15:08:14', '2026-11-20 15:08:47');

-- --------------------------------------------------------

--
-- Struktur dari tabel `absensi_sesi`
--

CREATE TABLE `absensi_sesi` (
  `id` int(11) NOT NULL,
  `guru_id` int(11) NOT NULL,
  `kelas` enum('10','11','12') NOT NULL,
  `tanggal` date NOT NULL,
  `hari` varchar(15) NOT NULL,
  `deskripsi` varchar(255) NOT NULL DEFAULT '',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `absensi_sesi`
--

INSERT INTO `absensi_sesi` (`id`, `guru_id`, `kelas`, `tanggal`, `hari`, `deskripsi`, `created_at`, `updated_at`) VALUES
(1, 2, '11', '2026-11-19', 'Kamis', 'Absensi (data lama)', '2026-11-20 09:45:09', '2026-11-20 09:45:09'),
(2, 2, '12', '2026-11-20', 'Jumat', 'Test', '2026-11-20 15:08:14', '2026-11-20 15:08:14');

-- --------------------------------------------------------

--
-- Struktur dari tabel `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subjek` varchar(150) NOT NULL,
  `pesan` text NOT NULL,
  `status` enum('baru','dibalas') NOT NULL DEFAULT 'baru',
  `balasan` text DEFAULT NULL,
  `dibalas_oleh` int(11) DEFAULT NULL,
  `dibalas_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `galeri`
--

CREATE TABLE `galeri` (
  `id` int(11) NOT NULL,
  `kategori_id` int(11) DEFAULT NULL,
  `judul` varchar(160) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `gambar` varchar(255) NOT NULL,
  `tanggal_dokumentasi` date NOT NULL,
  `kategori` varchar(80) DEFAULT 'Dokumentasi',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `galeri`
--

INSERT INTO `galeri` (`id`, `kategori_id`, `judul`, `deskripsi`, `gambar`, `tanggal_dokumentasi`, `kategori`, `created_at`) VALUES
(1, NULL, 'Juara 1 K3 dan Mobile Legends', 'Steins Gate Berhasil Mendapatkan Juara 1 K3 dan Mobile Legends di Classmeeting 2023', 'assets/uploads/galeri/f7588a7068d8b9409f4154d26e6af213.png', '2026-11-18', 'Dokumentasi', '2026-11-18 19:49:08');

-- --------------------------------------------------------

--
-- Struktur dari tabel `guru`
--

CREATE TABLE `guru` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nama_lengkap` varchar(120) NOT NULL,
  `mata_pelajaran` varchar(100) NOT NULL,
  `nip` varchar(30) DEFAULT NULL,
  `jenis_kelamin` enum('Laki-laki','Perempuan') DEFAULT NULL,
  `no_wa` varchar(25) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `foto_profil` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `guru`
--

INSERT INTO `guru` (`id`, `user_id`, `nama_lengkap`, `mata_pelajaran`, `nip`, `jenis_kelamin`, `no_wa`, `alamat`, `foto_profil`, `created_at`, `updated_at`) VALUES
(2, 6, 'Eriyu', 'Biologi', NULL, NULL, NULL, NULL, NULL, '2026-11-18 23:29:16', '2026-11-18 23:29:16'),
(3, 7, 'Angelia', 'Bahasa Inggris', NULL, 'Perempuan', '089672345578', NULL, NULL, '2026-11-20 13:50:36', '2026-11-20 13:50:36');

-- --------------------------------------------------------

--
-- Struktur dari tabel `guru_kelas`
--

CREATE TABLE `guru_kelas` (
  `guru_id` int(11) NOT NULL,
  `kelas` enum('10','11','12') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `guru_kelas`
--

INSERT INTO `guru_kelas` (`guru_id`, `kelas`) VALUES
(2, '12'),
(3, '12');

-- --------------------------------------------------------

--
-- Struktur dari tabel `history`
--

CREATE TABLE `history` (
  `id` int(11) NOT NULL,
  `judul` varchar(160) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `tanggal` date NOT NULL,
  `kategori` varchar(80) DEFAULT 'Perjalanan Kelas'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `history`
--

INSERT INTO `history` (`id`, `judul`, `deskripsi`, `tanggal`, `kategori`) VALUES
(1, 'First Meeting', 'Awal pertemuan dan proses saling mengenal sebagai keluarga kelas.', '2024-07-15', 'Awal Perjalanan'),
(2, 'Our First Classroom', 'Memulai hari-hari belajar dan membangun cerita bersama.', '2024-08-01', 'Kehidupan Kelas'),
(3, 'Daily School Life', 'Rutinitas belajar, tugas, tawa, dan pengalaman sehari-hari.', '2025-01-10', 'Kehidupan Kelas'),
(4, 'Unforgettable Moments', 'Momen kebersamaan yang menjadi bagian dari perjalanan kelas.', '2026-03-12', 'Kenangan');

-- --------------------------------------------------------

--
-- Struktur dari tabel `informasi`
--

CREATE TABLE `informasi` (
  `id` int(11) NOT NULL,
  `kategori_id` int(11) DEFAULT NULL,
  `judul` varchar(180) NOT NULL,
  `isi` text NOT NULL,
  `gambar` varchar(255) DEFAULT NULL,
  `penulis` varchar(100) DEFAULT 'Admin STEINS GATE',
  `status` enum('draft','published') DEFAULT 'published',
  `tanggal_publikasi` date NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `informasi`
--

INSERT INTO `informasi` (`id`, `kategori_id`, `judul`, `isi`, `gambar`, `penulis`, `status`, `tanggal_publikasi`, `created_at`, `updated_at`) VALUES
(1, 1, 'Persiapan Evaluasi Pembelajaran', 'Informasi dummy: siswa diharapkan mempersiapkan perlengkapan belajar dan memeriksa kembali materi yang telah dibahas. Jadwal resmi akan diumumkan oleh pihak sekolah.', NULL, 'Admin STEINS GATE', 'published', '2026-11-10', '2026-11-18 16:55:58', '2026-11-18 16:55:58'),
(2, 2, 'Pembaruan Informasi Kelas', 'Ini merupakan contoh pengumuman untuk kebutuhan demonstrasi website. Silakan ganti isi dengan informasi kelas yang telah diverifikasi.', NULL, 'Admin STEINS GATE', 'published', '2026-11-08', '2026-11-18 16:55:58', '2026-11-18 16:55:58'),
(3, 3, 'Agenda Kebersamaan Kelas', 'Contoh agenda kegiatan kelas. Detail waktu dan lokasi dapat diperbarui oleh pengelola setelah memperoleh informasi yang benar.', NULL, 'Admin STEINS GATE', 'published', '2026-11-05', '2026-11-18 16:55:58', '2026-11-18 16:55:58'),
(4, NULL, 'Kekurangan sapu', 'Kita Kekurangan sapu guyss', NULL, 'Insanul Firas', 'published', '2026-11-20', '2026-11-20 15:17:28', '2026-11-20 15:17:28');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal_pelajaran`
--

CREATE TABLE `jadwal_pelajaran` (
  `id` int(11) NOT NULL,
  `kelas` enum('10','11','12') NOT NULL DEFAULT '10',
  `hari` varchar(15) NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `mata_pelajaran` varchar(100) NOT NULL,
  `guru_id` int(11) DEFAULT NULL,
  `nama_guru` varchar(120) NOT NULL DEFAULT '',
  `ruangan` varchar(50) DEFAULT 'Kelas IPA 2'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `jadwal_pelajaran`
--

INSERT INTO `jadwal_pelajaran` (`id`, `kelas`, `hari`, `jam_mulai`, `jam_selesai`, `mata_pelajaran`, `guru_id`, `nama_guru`, `ruangan`) VALUES
(1, '10', 'Senin', '07:30:00', '09:00:00', 'Matematika', NULL, '', 'Kelas IPA 2'),
(2, '10', 'Senin', '09:15:00', '10:45:00', 'Bahasa Indonesia', NULL, '', 'Kelas IPA 2'),
(3, '10', 'Selasa', '07:30:00', '09:00:00', 'Biologi', NULL, '', 'Kelas IPA 2'),
(4, '12', 'Senin', '07:30:00', '09:00:00', 'Biologi', 2, 'Eriyu', 'Kelas IPA 2'),
(5, '12', 'Senin', '09:00:00', '11:00:00', 'Bahasa Inggris', 3, 'Angelia', 'Kelas IPA 2');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori_galeri`
--

CREATE TABLE `kategori_galeri` (
  `id` int(11) NOT NULL,
  `nama` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `kategori_galeri`
--

INSERT INTO `kategori_galeri` (`id`, `nama`) VALUES
(1, 'Foto Bersama'),
(2, 'Kegiatan Belajar'),
(3, 'Kegiatan Sekolah'),
(4, 'Momen Lainnya');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kategori_informasi`
--

CREATE TABLE `kategori_informasi` (
  `id` int(11) NOT NULL,
  `nama` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `kategori_informasi`
--

INSERT INTO `kategori_informasi` (`id`, `nama`) VALUES
(4, 'Administrasi'),
(3, 'Agenda'),
(1, 'Akademik'),
(2, 'Pengumuman');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kegiatan`
--

CREATE TABLE `kegiatan` (
  `id` int(11) NOT NULL,
  `judul` varchar(160) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `tanggal` date NOT NULL,
  `lokasi` varchar(150) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `kegiatan`
--

INSERT INTO `kegiatan` (`id`, `judul`, `deskripsi`, `tanggal`, `lokasi`, `created_at`) VALUES
(1, 'Belajar Bersama', 'Contoh kegiatan belajar bersama.', '2026-10-10', 'Ruang Kelas IPA 2', '2026-11-18 16:55:58'),
(2, 'Class Meeting', 'Contoh agenda kegiatan sekolah.', '2026-10-20', 'MAN 2 Padang Panjang', '2026-11-18 16:55:58'),
(3, 'Dokumentasi Kelas', 'Contoh sesi dokumentasi kenangan.', '2026-11-01', 'Lingkungan Sekolah', '2026-11-18 16:55:58'),
(4, 'Upacara', 'Wajib Besok Hadir', '2026-11-23', 'Man 2 Padang Panjang', '2026-11-20 15:16:25');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mata_pelajaran`
--

CREATE TABLE `mata_pelajaran` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `urutan` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `mata_pelajaran`
--

INSERT INTO `mata_pelajaran` (`id`, `nama`, `urutan`) VALUES
(1, 'Bahasa Indonesia', 1),
(2, 'Bahasa Inggris', 2),
(3, 'Matematika', 3),
(4, 'Pendidikan Pancasila dan Kewarganegaraan (PPKn)', 4),
(5, 'Sejarah Indonesia', 5),
(6, 'Seni Budaya', 6),
(7, 'Pendidikan Jasmani, Olahraga, dan Kesehatan (PJOK)', 7),
(8, 'Informatika', 8),
(9, 'Prakarya dan Kewirausahaan', 9),
(10, 'Al-Qur\'an Hadis', 10),
(11, 'Akidah Akhlak', 11),
(12, 'Fikih', 12),
(13, 'Sejarah Kebudayaan Islam (SKI)', 13),
(14, 'Bahasa Arab', 14),
(15, 'Matematika Minat', 15),
(16, 'Fisika', 16),
(17, 'Kimia', 17),
(18, 'Biologi', 18);

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengumpulan_tugas`
--

CREATE TABLE `pengumpulan_tugas` (
  `id` int(11) NOT NULL,
  `tugas_id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `nama_asli` varchar(255) NOT NULL,
  `catatan` text DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `pengumpulan_tugas`
--

INSERT INTO `pengumpulan_tugas` (`id`, `tugas_id`, `siswa_id`, `file_path`, `nama_asli`, `catatan`, `submitted_at`, `updated_at`) VALUES
(3, 4, 4, 'assets/uploads/jawaban/6ae0a23c57b03e0cb5b80d296cad3ee0.pdf', 'TUGAS MATERI DIKSI_Putra Baadi Zaelani_24552011131.pdf', NULL, '2026-11-20 15:06:08', '2026-11-20 15:06:08');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengumuman`
--

CREATE TABLE `pengumuman` (
  `id` int(11) NOT NULL,
  `judul` varchar(180) NOT NULL,
  `isi` text NOT NULL,
  `tanggal_publikasi` date NOT NULL,
  `status` enum('aktif','arsip') DEFAULT 'aktif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `pengumuman`
--

INSERT INTO `pengumuman` (`id`, `judul`, `isi`, `tanggal_publikasi`, `status`) VALUES
(1, 'Selamat Datang di STEINS GATE', 'Data pengumuman dummy untuk demonstrasi.', '2026-11-01', 'aktif'),
(2, 'Besok libur', 'Besok Libur guru libur', '2026-11-20', 'aktif');

-- --------------------------------------------------------

--
-- Struktur dari tabel `siswa`
--

CREATE TABLE `siswa` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `nis` varchar(30) NOT NULL,
  `nama_lengkap` varchar(120) NOT NULL,
  `jenis_kelamin` enum('Laki-laki','Perempuan') NOT NULL,
  `no_wa` varchar(25) NOT NULL,
  `alamat` text NOT NULL,
  `foto_profil` varchar(255) DEFAULT NULL,
  `angkatan` varchar(20) NOT NULL,
  `kelas` enum('10','11','12') DEFAULT NULL,
  `jabatan` varchar(60) DEFAULT 'Anggota',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `siswa`
--

INSERT INTO `siswa` (`id`, `user_id`, `nis`, `nama_lengkap`, `jenis_kelamin`, `no_wa`, `alamat`, `foto_profil`, `angkatan`, `kelas`, `jabatan`, `created_at`, `updated_at`) VALUES
(2, 2, '20240001', 'Insanul Firas', 'Laki-laki', '081365162164', 'Kos Bu Emi Arkobi', 'assets/uploads/profil/e61f5e4aa09f720e1f11fe9e86a5718a.jpg', '2024', NULL, 'Anggota', '2026-11-18 17:16:57', '2026-11-18 21:56:26'),
(4, 8, '24552011147', 'Insanul Firas', 'Laki-laki', '081365162164', 'Kos Bu Emi', 'assets/uploads/profil/013f4cd9590a6e410333d6ad9d510f06.jpg', '2024', '12', 'Anggota', '2026-11-20 14:02:58', '2026-11-20 14:04:47');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tugas`
--

CREATE TABLE `tugas` (
  `id` int(11) NOT NULL,
  `guru_id` int(11) NOT NULL,
  `judul` varchar(180) NOT NULL,
  `deskripsi` text NOT NULL,
  `target_kelas` enum('10','11','12') NOT NULL,
  `lampiran` varchar(255) DEFAULT NULL,
  `deadline` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `tugas`
--

INSERT INTO `tugas` (`id`, `guru_id`, `judul`, `deskripsi`, `target_kelas`, `lampiran`, `deadline`, `created_at`, `updated_at`) VALUES
(1, 2, 'Tugas 1', 'tugas bebas asal nugas!!!', '11', NULL, '2026-11-19 17:31:00', '2026-11-18 23:31:06', '2026-11-20 10:31:16'),
(2, 3, 'Tugas English 1', 'Buatkan Jawaban Sesuai Soal Tersebut', '11', 'assets/uploads/tugas/ddfd00a48785efddd042910f02d6447c.pdf', '2026-11-21 23:59:00', '2026-11-20 13:52:31', '2026-11-20 13:52:31'),
(3, 2, 'Tugas 2', 'Hai', '11', NULL, '2026-11-20 23:59:00', '2026-11-20 13:55:22', '2026-11-20 13:55:22'),
(4, 2, 'Tugas 1', 'Buatlah Tugas Berikut', '12', 'assets/uploads/tugas/0a7a17e822ebe4962c3caea27a0d9a6c.pdf', '2026-11-20 22:10:00', '2026-11-20 15:05:23', '2026-11-20 15:05:23');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','guru','siswa') NOT NULL DEFAULT 'siswa',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `created_at`, `updated_at`) VALUES
(2, 'firastest', 'firas.test@example.com', '$2y$12$cBBsjPBnO49ohmmNXtuOOe1a2QVxPO7fpBVLqBz2g/HVaKaT9zqGm', 'admin', '2026-11-18 17:16:57', '2026-11-18 18:36:36'),
(6, 'Eriyu', 'eriyu@gmail.com', '$2y$12$iU3SCL1iBp9CdeF4faTMX.js2i3gVh7Ju5ccXlrHexCw7eMRLexp2', 'guru', '2026-11-18 23:29:16', '2026-11-18 23:29:16'),
(7, 'MissAngel', 'novi.test@example.com', '$2y$12$9ovtDn5UBJCvS4E9itld9ua43gznif8MOqZRVWv1k5ZAejxu73psC', 'guru', '2026-11-20 13:50:36', '2026-11-20 13:50:36'),
(8, 'Firas', 'insanul@gmail.com', '$2y$12$tfduwGqQBq/l5P.JIgSJb.9Jf2Gd.Fko6tvCuvCcnt0a9ngYjsDfC', 'siswa', '2026-11-20 14:02:58', '2026-11-20 14:02:58');

--
-- Indeks untuk tabel yang dibuang
--

--
-- Indeks untuk tabel `absensi`
--
ALTER TABLE `absensi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_absensi_sesi_siswa` (`sesi_id`,`siswa_id`),
  ADD KEY `idx_absensi_kelas_tanggal` (`kelas`,`tanggal`),
  ADD KEY `idx_absensi_guru` (`guru_id`),
  ADD KEY `idx_absensi_siswa` (`siswa_id`);

--
-- Indeks untuk tabel `absensi_sesi`
--
ALTER TABLE `absensi_sesi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sesi_guru_kelas` (`guru_id`,`kelas`,`tanggal`);

--
-- Indeks untuk tabel `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_feedback_status` (`status`,`created_at`),
  ADD KEY `idx_feedback_user` (`user_id`),
  ADD KEY `fk_feedback_admin` (`dibalas_oleh`);

--
-- Indeks untuk tabel `galeri`
--
ALTER TABLE `galeri`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kategori_id` (`kategori_id`);

--
-- Indeks untuk tabel `guru`
--
ALTER TABLE `guru`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `guru_kelas`
--
ALTER TABLE `guru_kelas`
  ADD PRIMARY KEY (`guru_id`,`kelas`);

--
-- Indeks untuk tabel `history`
--
ALTER TABLE `history`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `informasi`
--
ALTER TABLE `informasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kategori_id` (`kategori_id`),
  ADD KEY `status` (`status`,`tanggal_publikasi`);

--
-- Indeks untuk tabel `jadwal_pelajaran`
--
ALTER TABLE `jadwal_pelajaran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_jadwal_kelas` (`kelas`),
  ADD KEY `fk_jadwal_guru` (`guru_id`);

--
-- Indeks untuk tabel `kategori_galeri`
--
ALTER TABLE `kategori_galeri`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama` (`nama`);

--
-- Indeks untuk tabel `kategori_informasi`
--
ALTER TABLE `kategori_informasi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama` (`nama`);

--
-- Indeks untuk tabel `kegiatan`
--
ALTER TABLE `kegiatan`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `mata_pelajaran`
--
ALTER TABLE `mata_pelajaran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama` (`nama`);

--
-- Indeks untuk tabel `pengumpulan_tugas`
--
ALTER TABLE `pengumpulan_tugas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_jawaban` (`tugas_id`,`siswa_id`),
  ADD KEY `fk_jawaban_siswa` (`siswa_id`);

--
-- Indeks untuk tabel `pengumuman`
--
ALTER TABLE `pengumuman`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `siswa`
--
ALTER TABLE `siswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nis` (`nis`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_siswa_kelas` (`kelas`);

--
-- Indeks untuk tabel `tugas`
--
ALTER TABLE `tugas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tugas_kelas` (`target_kelas`),
  ADD KEY `idx_tugas_guru` (`guru_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `absensi`
--
ALTER TABLE `absensi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `absensi_sesi`
--
ALTER TABLE `absensi_sesi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `galeri`
--
ALTER TABLE `galeri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `guru`
--
ALTER TABLE `guru`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `history`
--
ALTER TABLE `history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `informasi`
--
ALTER TABLE `informasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `jadwal_pelajaran`
--
ALTER TABLE `jadwal_pelajaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `kategori_galeri`
--
ALTER TABLE `kategori_galeri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `kategori_informasi`
--
ALTER TABLE `kategori_informasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `kegiatan`
--
ALTER TABLE `kegiatan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `mata_pelajaran`
--
ALTER TABLE `mata_pelajaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT untuk tabel `pengumpulan_tugas`
--
ALTER TABLE `pengumpulan_tugas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `pengumuman`
--
ALTER TABLE `pengumuman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `siswa`
--
ALTER TABLE `siswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `tugas`
--
ALTER TABLE `tugas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `absensi`
--
ALTER TABLE `absensi`
  ADD CONSTRAINT `fk_absensi_guru` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`),
  ADD CONSTRAINT `fk_absensi_sesi` FOREIGN KEY (`sesi_id`) REFERENCES `absensi_sesi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_absensi_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `absensi_sesi`
--
ALTER TABLE `absensi_sesi`
  ADD CONSTRAINT `fk_sesi_guru` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `fk_feedback_admin` FOREIGN KEY (`dibalas_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_feedback_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `galeri`
--
ALTER TABLE `galeri`
  ADD CONSTRAINT `galeri_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `kategori_galeri` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `guru`
--
ALTER TABLE `guru`
  ADD CONSTRAINT `fk_guru_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `guru_kelas`
--
ALTER TABLE `guru_kelas`
  ADD CONSTRAINT `fk_guru_kelas_guru` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `informasi`
--
ALTER TABLE `informasi`
  ADD CONSTRAINT `informasi_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `kategori_informasi` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `jadwal_pelajaran`
--
ALTER TABLE `jadwal_pelajaran`
  ADD CONSTRAINT `fk_jadwal_guru` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `pengumpulan_tugas`
--
ALTER TABLE `pengumpulan_tugas`
  ADD CONSTRAINT `fk_jawaban_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_jawaban_tugas` FOREIGN KEY (`tugas_id`) REFERENCES `tugas` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `siswa`
--
ALTER TABLE `siswa`
  ADD CONSTRAINT `fk_siswa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `tugas`
--
ALTER TABLE `tugas`
  ADD CONSTRAINT `fk_tugas_guru` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
