<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function sendJsonResponse(int $statusCode, string $status, string $message): void
{
    http_response_code($statusCode);
    echo json_encode([
        'status'  => $status,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Validasi HTTP Method: Hanya menerima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(400, 'error', 'Metode HTTP tidak valid. Gunakan metode POST.');
}

// 2. Tangani input data baik dari JSON (php://input) maupun Form Data ($_POST)
$rawInput = file_get_contents('php://input');
$jsonData = [];

if (is_string($rawInput) && trim($rawInput) !== '') {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $jsonData = $decoded;
    }
}

$input = !empty($jsonData) ? $jsonData : $_POST;

$action = isset($input['action']) ? strtolower(trim((string) $input['action'])) : '';

if ($action === '') {
    sendJsonResponse(400, 'error', 'Parameter action wajib disertakan (create, update, atau delete).');
}

require_once __DIR__ . '/../config/database.php';

try {
    switch ($action) {
        case 'create': {
            $nama_fasilitas = trim((string) ($input['nama_fasilitas'] ?? ''));
            $kategori       = trim((string) ($input['kategori'] ?? ''));
            $alamat         = trim((string) ($input['alamat'] ?? ''));
            $latRaw         = $input['latitude'] ?? '';
            $lngRaw         = $input['longitude'] ?? '';
            $telepon        = isset($input['telepon']) && trim((string) $input['telepon']) !== ''
                                ? trim((string) $input['telepon'])
                                : null;
            $deskripsi      = isset($input['deskripsi']) && trim((string) $input['deskripsi']) !== ''
                                ? trim((string) $input['deskripsi'])
                                : null;

            if (
                $nama_fasilitas === '' ||
                $kategori === '' ||
                $alamat === '' ||
                $latRaw === '' || $latRaw === null ||
                $lngRaw === '' || $lngRaw === null
            ) {
                sendJsonResponse(400, 'error', 'Field nama_fasilitas, kategori, alamat, latitude, dan longitude wajib diisi.');
            }

            if (!is_numeric($latRaw) || !is_numeric($lngRaw)) {
                sendJsonResponse(400, 'error', 'Nilai latitude dan longitude harus berupa angka yang valid.');
            }

            $latitude  = (float) $latRaw;
            $longitude = (float) $lngRaw;

            if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                sendJsonResponse(400, 'error', 'Koordinat latitude (-90 s/d 90) atau longitude (-180 s/d 180) berada di luar rentang valid.');
            }

            $sql = 'INSERT INTO fasilitas (nama_fasilitas, kategori, alamat, lokasi, telepon, deskripsi)
                    VALUES (:nama_fasilitas, :kategori, :alamat, POINT(:longitude, :latitude), :telepon, :deskripsi)';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nama_fasilitas' => $nama_fasilitas,
                ':kategori'       => $kategori,
                ':alamat'         => $alamat,
                ':longitude'      => $longitude,
                ':latitude'       => $latitude,
                ':telepon'        => $telepon,
                ':deskripsi'      => $deskripsi,
            ]);

            sendJsonResponse(201, 'success', 'Data fasilitas berhasil ditambahkan.');
        }

        case 'update': {
            $idRaw          = $input['id_fasilitas'] ?? '';
            $nama_fasilitas = trim((string) ($input['nama_fasilitas'] ?? ''));
            $kategori       = trim((string) ($input['kategori'] ?? ''));
            $alamat         = trim((string) ($input['alamat'] ?? ''));
            $latRaw         = $input['latitude'] ?? '';
            $lngRaw         = $input['longitude'] ?? '';
            $telepon        = isset($input['telepon']) && trim((string) $input['telepon']) !== ''
                                ? trim((string) $input['telepon'])
                                : null;
            $deskripsi      = isset($input['deskripsi']) && trim((string) $input['deskripsi']) !== ''
                                ? trim((string) $input['deskripsi'])
                                : null;

            if ($idRaw === '' || $idRaw === null || !is_numeric($idRaw) || (int) $idRaw <= 0) {
                sendJsonResponse(400, 'error', 'Parameter id_fasilitas tidak valid.');
            }

            if (
                $nama_fasilitas === '' ||
                $kategori === '' ||
                $alamat === '' ||
                $latRaw === '' || $latRaw === null ||
                $lngRaw === '' || $lngRaw === null
            ) {
                sendJsonResponse(400, 'error', 'Field nama_fasilitas, kategori, alamat, latitude, dan longitude wajib diisi.');
            }

            if (!is_numeric($latRaw) || !is_numeric($lngRaw)) {
                sendJsonResponse(400, 'error', 'Nilai latitude dan longitude harus berupa angka yang valid.');
            }

            $id_fasilitas = (int) $idRaw;
            $latitude     = (float) $latRaw;
            $longitude    = (float) $lngRaw;

            if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                sendJsonResponse(400, 'error', 'Koordinat latitude (-90 s/d 90) atau longitude (-180 s/d 180) berada di luar rentang valid.');
            }

            // Cek keberadaan data terlebih dahulu agar tetap sukses meski nilai field sama persis
            $checkStmt = $pdo->prepare('SELECT id_fasilitas FROM fasilitas WHERE id_fasilitas = :id_fasilitas LIMIT 1');
            $checkStmt->execute([':id_fasilitas' => $id_fasilitas]);
            if (!$checkStmt->fetch()) {
                sendJsonResponse(404, 'error', 'Data fasilitas tidak ditemukan.');
            }

            $sql = 'UPDATE fasilitas
                    SET nama_fasilitas = :nama_fasilitas,
                        kategori       = :kategori,
                        alamat         = :alamat,
                        lokasi         = POINT(:longitude, :latitude),
                        telepon        = :telepon,
                        deskripsi      = :deskripsi
                    WHERE id_fasilitas = :id_fasilitas';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nama_fasilitas' => $nama_fasilitas,
                ':kategori'       => $kategori,
                ':alamat'         => $alamat,
                ':longitude'      => $longitude,
                ':latitude'       => $latitude,
                ':telepon'        => $telepon,
                ':deskripsi'      => $deskripsi,
                ':id_fasilitas'   => $id_fasilitas,
            ]);

            sendJsonResponse(200, 'success', 'Data fasilitas berhasil diperbarui.');
        }

        case 'delete': {
            $idRaw = $input['id_fasilitas'] ?? '';

            if ($idRaw === '' || $idRaw === null || !is_numeric($idRaw) || (int) $idRaw <= 0) {
                sendJsonResponse(400, 'error', 'Parameter id_fasilitas tidak valid.');
            }

            $id_fasilitas = (int) $idRaw;

            $stmt = $pdo->prepare('DELETE FROM fasilitas WHERE id_fasilitas = :id_fasilitas');
            $stmt->execute([':id_fasilitas' => $id_fasilitas]);

            if ($stmt->rowCount() === 0) {
                sendJsonResponse(404, 'error', 'Data fasilitas tidak ditemukan.');
            }

            sendJsonResponse(200, 'success', 'Data fasilitas berhasil dihapus.');
        }

        default:
            sendJsonResponse(400, 'error', 'Aksi tidak dikenali. Gunakan create, update, atau delete.');
    }
} catch (PDOException $e) {
    sendJsonResponse(500, 'error', 'Terjadi kesalahan pada database: ' . $e->getMessage());
}
