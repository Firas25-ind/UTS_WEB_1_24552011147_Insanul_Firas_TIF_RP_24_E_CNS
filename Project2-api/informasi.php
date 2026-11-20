<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$raw    = file_get_contents("php://input");
$input  = json_decode($raw, true);

if (!is_array($input)) {
    $input = [];
}

function sendJson(int $statusCode, string $status, string $message, $data = null): void
{
    http_response_code($statusCode);
    $payload = [
        "status"  => $status,
        "message" => $message,
    ];
    if ($data !== null) {
        $payload["data"] = $data;
    }
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // =========================================================
    // 1. READ (GET) - Semua data atau berdasarkan ?id=
    // =========================================================
    if ($method === 'GET') {
        if ($id) {
            $stmt = $pdo->prepare("
                SELECT i.id, i.kategori_id, k.nama AS nama_kategori, i.judul, i.isi,
                       i.gambar, i.penulis, i.status, i.tanggal_publikasi, i.created_at, i.updated_at
                FROM informasi i
                LEFT JOIN kategori_informasi k ON k.id = i.kategori_id
                WHERE i.id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                sendJson(404, "error", "Data informasi dengan ID {$id} tidak ditemukan.");
            }
            sendJson(200, "success", "Detail informasi berhasil diambil.", $row);
        } else {
            $stmt = $pdo->query("
                SELECT i.id, i.kategori_id, k.nama AS nama_kategori, i.judul, i.isi,
                       i.gambar, i.penulis, i.status, i.tanggal_publikasi, i.created_at, i.updated_at
                FROM informasi i
                LEFT JOIN kategori_informasi k ON k.id = i.kategori_id
                ORDER BY i.id DESC
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJson(200, "success", "Daftar informasi berhasil diambil.", $rows);
        }
    }

    // =========================================================
    // 2. CREATE (POST) - Tambah data informasi baru
    // =========================================================
    elseif ($method === 'POST') {
        $judul       = trim($input['judul'] ?? $_POST['judul'] ?? '');
        $isi         = trim($input['isi'] ?? $_POST['isi'] ?? '');
        $kategori_id = $input['kategori_id'] ?? $_POST['kategori_id'] ?? 1;
        $penulis     = trim($input['penulis'] ?? $_POST['penulis'] ?? 'Admin STEINS GATE');
        $status      = $input['status'] ?? $_POST['status'] ?? 'published';
        $tanggal     = $input['tanggal_publikasi'] ?? $_POST['tanggal_publikasi'] ?? date('Y-m-d');

        if ($judul === '' || $isi === '') {
            sendJson(400, "error", "Field 'judul' dan 'isi' wajib diisi.");
        }

        if (!in_array($status, ['draft', 'published'], true)) {
            $status = 'published';
        }

        $stmt = $pdo->prepare("
            INSERT INTO informasi (kategori_id, judul, isi, penulis, status, tanggal_publikasi)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$kategori_id, $judul, $isi, $penulis, $status, $tanggal]);
        $newId = (int)$pdo->lastInsertId();

        sendJson(201, "success", "Data informasi berhasil ditambahkan.", [
            "id"                => $newId,
            "kategori_id"       => (int)$kategori_id,
            "judul"             => $judul,
            "isi"               => $isi,
            "penulis"           => $penulis,
            "status"            => $status,
            "tanggal_publikasi" => $tanggal,
        ]);
    }

    // =========================================================
    // 3. UPDATE (PUT) - Perbarui data informasi berdasarkan ?id=
    // =========================================================
    elseif ($method === 'PUT') {
        if (!$id) {
            sendJson(400, "error", "Parameter '?id=' wajib disertakan pada URL untuk update data.");
        }

        $cek = $pdo->prepare("SELECT * FROM informasi WHERE id = ? LIMIT 1");
        $cek->execute([$id]);
        $existing = $cek->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            sendJson(404, "error", "Data informasi dengan ID {$id} tidak ditemukan.");
        }

        $judul       = trim($input['judul'] ?? $existing['judul']);
        $isi         = trim($input['isi'] ?? $existing['isi']);
        $kategori_id = $input['kategori_id'] ?? $existing['kategori_id'];
        $penulis     = trim($input['penulis'] ?? $existing['penulis']);
        $status      = $input['status'] ?? $existing['status'];
        $tanggal     = $input['tanggal_publikasi'] ?? $existing['tanggal_publikasi'];

        if ($judul === '' || $isi === '') {
            sendJson(400, "error", "Field 'judul' dan 'isi' tidak boleh kosong.");
        }

        $stmt = $pdo->prepare("
            UPDATE informasi
            SET kategori_id = ?, judul = ?, isi = ?, penulis = ?, status = ?, tanggal_publikasi = ?
            WHERE id = ?
        ");
        $stmt->execute([$kategori_id, $judul, $isi, $penulis, $status, $tanggal, $id]);

        sendJson(200, "success", "Data informasi ID {$id} berhasil diperbarui.", [
            "id"                => $id,
            "kategori_id"       => (int)$kategori_id,
            "judul"             => $judul,
            "isi"               => $isi,
            "penulis"           => $penulis,
            "status"            => $status,
            "tanggal_publikasi" => $tanggal,
        ]);
    }

    // =========================================================
    // 4. DELETE (DELETE) - Hapus data informasi berdasarkan ?id=
    // =========================================================
    elseif ($method === 'DELETE') {
        if (!$id) {
            sendJson(400, "error", "Parameter '?id=' wajib disertakan pada URL untuk menghapus data.");
        }

        $stmt = $pdo->prepare("DELETE FROM informasi WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            sendJson(404, "error", "Data informasi dengan ID {$id} tidak ditemukan.");
        }

        sendJson(200, "success", "Data informasi ID {$id} berhasil dihapus.");
    }

    else {
        sendJson(405, "error", "Metode HTTP {$method} tidak diizinkan.");
    }
} catch (PDOException $e) {
    sendJson(500, "error", "Terjadi kesalahan pada database: " . $e->getMessage());
}