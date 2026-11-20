<?php
/* Helper tanggal Indonesia, deadline tugas, dan pratinjau file (dipakai guru & siswa). */

function hari_indonesia(string $tanggal): string {
    $nama = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $ts = strtotime($tanggal);
    return $ts ? $nama[(int)date('w', $ts)] : '';
}

function bulan_indonesia(): array {
    return [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
}

/** "Kamis, 20 November 2026" */
function tanggal_panjang(string $tanggal): string {
    $ts = strtotime($tanggal);
    if (!$ts) return '-';
    return hari_indonesia($tanggal) . ', ' . (int)date('j', $ts) . ' ' . bulan_indonesia()[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

/** "20 Nov 2026, 23:59 WIB" untuk deadline (atau "Tanpa batas waktu"). */
function deadline_label(?string $deadline): string {
    if (!$deadline) return 'Tanpa batas waktu';
    $ts = strtotime($deadline);
    return $ts ? date('d M Y, H:i', $ts) . ' WIB' : 'Tanpa batas waktu';
}

/** Pengumpulan masih dibuka? (tanpa deadline = selalu dibuka) */
function tugas_terbuka(?string $deadline): bool {
    if (!$deadline) return true;
    $ts = strtotime($deadline);
    return !$ts || time() < $ts;
}

/** Teks sisa waktu, mis. "Sisa 2 hari 3 jam" / "Sisa 45 menit" / "Ditutup". */
function sisa_waktu(?string $deadline): string {
    if (!$deadline) return '';
    $ts = strtotime($deadline);
    if (!$ts) return '';
    $d = $ts - time();
    if ($d <= 0) return 'Ditutup';
    $hari = intdiv($d, 86400);
    $jam = intdiv($d % 86400, 3600);
    $menit = intdiv($d % 3600, 60);
    if ($hari > 0) return 'Sisa ' . $hari . ' hari ' . $jam . ' jam';
    if ($jam > 0) return 'Sisa ' . $jam . ' jam ' . $menit . ' menit';
    return 'Sisa ' . max(1, $menit) . ' menit';
}

/** Nilai input datetime-local ("2026-11-20T23:59") dari DATETIME database. */
function deadline_input_value(?string $deadline): string {
    $ts = $deadline ? strtotime($deadline) : false;
    return $ts ? date('Y-m-d\TH:i', $ts) : '';
}

/**
 * Baca input datetime-local. Return [string|null $dbValue, string $error].
 * Kosong = tanpa deadline.
 */
function parse_deadline_input(?string $raw): array {
    $raw = trim((string)$raw);
    if ($raw === '') return [null, ''];
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $raw) ?: DateTime::createFromFormat('Y-m-d H:i', $raw);
    if (!$dt) return [null, 'Format deadline tidak valid.'];
    return [$dt->format('Y-m-d H:i:00'), ''];
}

/** Jenis file yang bisa ditampilkan langsung oleh browser (sama dengan download.php?view=1). */
function file_inline_ext(string $namaAsli): string {
    $ext = strtolower(pathinfo($namaAsli, PATHINFO_EXTENSION));
    return in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'txt'], true) ? $ext : '';
}