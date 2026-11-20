<?php
/* Helper kelas yang diajar guru (ditetapkan admin di Data Guru). */

function guru_kelas_options(): array {
    return ['10', '11', '12'];
}

/** Daftar kelas yang ditetapkan untuk guru, mis. ['10','11']. */
function guru_kelas(PDO $pdo, int $guruId): array {
    $st = $pdo->prepare("SELECT kelas FROM guru_kelas WHERE guru_id = ? ORDER BY kelas");
    $st->execute([$guruId]);
    return array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN));
}