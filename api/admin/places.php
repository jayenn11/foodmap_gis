<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../config/database.php';

/**
 * Ambil payload input dari JSON body, $_POST, maupun $_GET
 */
function getRequestData(): array
{
    $rawInput = file_get_contents('php://input');
    $jsonData = [];
    if ($rawInput !== false && trim($rawInput) !== '') {
        $decoded = json_decode($rawInput, true);
        if (is_array($decoded)) {
            $jsonData = $decoded;
        }
    }
    return array_merge($_GET, $_POST, $jsonData);
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$input  = getRequestData();
$action = strtolower(trim((string) ($input['action'] ?? '')));

// Normalisasi metode berdasarkan action jika dikirim via POST
if ($method === 'POST') {
    if ($action === 'update' || $action === 'put') {
        $method = 'PUT';
    } elseif ($action === 'delete') {
        $method = 'DELETE';
    }
}

try {
    // 1. GET: Ambil seluruh daftar penyedia makanan beserta jumlah menu
    if ($method === 'GET') {
        $pdo->exec("SET time_zone = '+07:00'");
        $sql = "SELECT 
                    p.id_penyedia,
                    p.id_kategori,
                    k.nama_kategori,
                    p.nama_penyedia,
                    p.alamat,
                    p.latitude,
                    p.longitude,
                    p.jam_buka,
                    p.jam_tutup,
                    p.no_telepon,
                    p.foto,
                    CASE WHEN CURRENT_TIME() BETWEEN p.jam_buka AND p.jam_tutup THEN 1 ELSE 0 END AS is_open,
                    COUNT(m.id_menu) AS menu_count,
                    MIN(m.harga) AS min_price,
                    MAX(m.harga) AS max_price
                FROM penyedia_makanan p
                INNER JOIN kategori_makanan k ON p.id_kategori = k.id_kategori
                LEFT JOIN menu_makanan m ON p.id_penyedia = m.id_penyedia
                GROUP BY 
                    p.id_penyedia,
                    p.id_kategori,
                    k.nama_kategori,
                    p.nama_penyedia,
                    p.alamat,
                    p.latitude,
                    p.longitude,
                    p.jam_buka,
                    p.jam_tutup,
                    p.no_telepon,
                    p.foto
                ORDER BY p.id_penyedia DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['id_penyedia'] = (int) $row['id_penyedia'];
            $row['id_kategori'] = (int) $row['id_kategori'];
            $row['latitude']    = (float) $row['latitude'];
            $row['longitude']   = (float) $row['longitude'];
            $row['is_open']     = (bool) $row['is_open'];
            $row['menu_count']  = (int) ($row['menu_count'] ?? 0);
            $row['min_price']   = $row['min_price'] !== null ? (float) $row['min_price'] : 0.0;
            $row['max_price']   = $row['max_price'] !== null ? (float) $row['max_price'] : 0.0;
        }
        unset($row);

        echo json_encode([
            'status' => 'success',
            'total'  => count($rows),
            'data'   => $rows,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. POST: Tambah data penyedia makanan baru
    if ($method === 'POST') {
        $namaPenyedia = trim((string) ($input['nama_penyedia'] ?? ($input['nama'] ?? '')));
        $idKategori   = isset($input['id_kategori']) && is_numeric($input['id_kategori']) ? (int) $input['id_kategori'] : 0;
        $alamat       = trim((string) ($input['alamat'] ?? ''));
        $latRaw       = $input['latitude'] ?? null;
        $lngRaw       = $input['longitude'] ?? null;
        $jamBuka      = trim((string) ($input['jam_buka'] ?? ''));
        $jamTutup     = trim((string) ($input['jam_tutup'] ?? ''));
        $noTelepon    = isset($input['no_telepon']) ? trim((string) $input['no_telepon']) : null;
        $foto         = isset($input['foto']) ? trim((string) $input['foto']) : null;

        if ($namaPenyedia === '' || $idKategori <= 0 || $alamat === '' || $latRaw === null || $lngRaw === null || !is_numeric($latRaw) || !is_numeric($lngRaw) || $jamBuka === '' || $jamTutup === '') {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Seluruh atribut wajib (nama, kategori, alamat, koordinat latitude/longitude, jam buka, dan jam tutup) harus diisi dengan benar.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $latitude  = (float) $latRaw;
        $longitude = (float) $lngRaw;

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Nilai koordinat Latitude (-90 s/d 90) atau Longitude (-180 s/d 180) berada di luar rentang geografis yang valid.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Normalisasi format waktu HH:MM menjadi HH:MM:00 jika perlu
        if (preg_match('/^\d{2}:\d{2}$/', $jamBuka)) {
            $jamBuka .= ':00';
        }
        if (preg_match('/^\d{2}:\d{2}$/', $jamTutup)) {
            $jamTutup .= ':00';
        }

        $sql = "INSERT INTO penyedia_makanan 
                    (id_kategori, nama_penyedia, alamat, latitude, longitude, jam_buka, jam_tutup, no_telepon, foto)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $idKategori,
            $namaPenyedia,
            $alamat,
            $latitude,
            $longitude,
            $jamBuka,
            $jamTutup,
            $noTelepon !== '' ? $noTelepon : null,
            $foto !== '' ? $foto : null,
        ]);

        echo json_encode([
            'status'      => 'success',
            'message'     => 'Data penyedia makanan berhasil ditambahkan.',
            'id_penyedia' => (int) $pdo->lastInsertId(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 3. PUT: Perbarui data penyedia makanan
    if ($method === 'PUT') {
        $idPenyedia   = isset($input['id_penyedia']) && is_numeric($input['id_penyedia'])
            ? (int) $input['id_penyedia']
            : (isset($input['id']) && is_numeric($input['id']) ? (int) $input['id'] : 0);
        $namaPenyedia = trim((string) ($input['nama_penyedia'] ?? ($input['nama'] ?? '')));
        $idKategori   = isset($input['id_kategori']) && is_numeric($input['id_kategori']) ? (int) $input['id_kategori'] : 0;
        $alamat       = trim((string) ($input['alamat'] ?? ''));
        $latRaw       = $input['latitude'] ?? null;
        $lngRaw       = $input['longitude'] ?? null;
        $jamBuka      = trim((string) ($input['jam_buka'] ?? ''));
        $jamTutup     = trim((string) ($input['jam_tutup'] ?? ''));
        $noTelepon    = isset($input['no_telepon']) ? trim((string) $input['no_telepon']) : null;
        $foto         = isset($input['foto']) ? trim((string) $input['foto']) : null;

        if ($idPenyedia <= 0) {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'ID penyedia makanan tidak valid.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($namaPenyedia === '' || $idKategori <= 0 || $alamat === '' || $latRaw === null || $lngRaw === null || !is_numeric($latRaw) || !is_numeric($lngRaw) || $jamBuka === '' || $jamTutup === '') {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Seluruh atribut wajib (nama, kategori, alamat, koordinat latitude/longitude, jam buka, dan jam tutup) harus diisi dengan benar.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $latitude  = (float) $latRaw;
        $longitude = (float) $lngRaw;

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Nilai koordinat Latitude (-90 s/d 90) atau Longitude (-180 s/d 180) berada di luar rentang geografis yang valid.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $jamBuka)) {
            $jamBuka .= ':00';
        }
        if (preg_match('/^\d{2}:\d{2}$/', $jamTutup)) {
            $jamTutup .= ':00';
        }

        $sql = "UPDATE penyedia_makanan
                SET id_kategori = ?,
                    nama_penyedia = ?,
                    alamat = ?,
                    latitude = ?,
                    longitude = ?,
                    jam_buka = ?,
                    jam_tutup = ?,
                    no_telepon = ?,
                    foto = ?
                WHERE id_penyedia = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $idKategori,
            $namaPenyedia,
            $alamat,
            $latitude,
            $longitude,
            $jamBuka,
            $jamTutup,
            $noTelepon !== '' ? $noTelepon : null,
            $foto !== '' ? $foto : null,
            $idPenyedia,
        ]);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Data penyedia makanan berhasil diperbarui.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 4. DELETE: Hapus data penyedia makanan (otomatis menghapus menu terkait via ON DELETE CASCADE)
    if ($method === 'DELETE') {
        $idPenyedia = isset($input['id_penyedia']) && is_numeric($input['id_penyedia'])
            ? (int) $input['id_penyedia']
            : (isset($input['id']) && is_numeric($input['id']) ? (int) $input['id'] : 0);

        if ($idPenyedia <= 0) {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'ID penyedia makanan yang akan dihapus tidak valid.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmtCheck = $pdo->prepare("SELECT nama_penyedia FROM penyedia_makanan WHERE id_penyedia = ? LIMIT 1");
        $stmtCheck->execute([$idPenyedia]);
        $existing = $stmtCheck->fetch();

        if (!$existing) {
            http_response_code(404);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Data penyedia makanan tidak ditemukan.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmtDelete = $pdo->prepare("DELETE FROM penyedia_makanan WHERE id_penyedia = ?");
        $stmtDelete->execute([$idPenyedia]);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Data penyedia makanan "' . $existing['nama_penyedia'] . '" beserta seluruh menunya berhasil dihapus.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(405);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Metode HTTP tidak didukung.',
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Kesalahan database pada pengelolaan tempat makan: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
