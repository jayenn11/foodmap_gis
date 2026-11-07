<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../config/database.php';

function getCategoryRequestData(): array
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
$input  = getCategoryRequestData();
$action = strtolower(trim((string) ($input['action'] ?? '')));

if ($method === 'POST') {
    if ($action === 'update' || $action === 'put') {
        $method = 'PUT';
    } elseif ($action === 'delete') {
        $method = 'DELETE';
    }
}

try {
    // 1. GET: Ambil seluruh kategori beserta jumlah tempat makan yang terikat
    if ($method === 'GET') {
        $sql = "SELECT 
                    k.id_kategori,
                    k.nama_kategori,
                    k.deskripsi,
                    COUNT(p.id_penyedia) AS place_count
                FROM kategori_makanan k
                LEFT JOIN penyedia_makanan p ON k.id_kategori = p.id_kategori
                GROUP BY k.id_kategori, k.nama_kategori, k.deskripsi
                ORDER BY k.id_kategori ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $categories = $stmt->fetchAll();

        foreach ($categories as &$category) {
            $category['id_kategori'] = (int) $category['id_kategori'];
            $category['place_count'] = (int) ($category['place_count'] ?? 0);
        }
        unset($category);

        echo json_encode([
            'status' => 'success',
            'data'   => $categories,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. POST: Tambah kategori makanan baru
    if ($method === 'POST') {
        $namaKategori = trim((string) ($input['nama_kategori'] ?? ''));
        $deskripsi    = isset($input['deskripsi']) ? trim((string) $input['deskripsi']) : null;

        if ($namaKategori === '') {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Nama kategori wajib diisi.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $sql = "INSERT INTO kategori_makanan (nama_kategori, deskripsi) VALUES (?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$namaKategori, $deskripsi !== '' ? $deskripsi : null]);

        echo json_encode([
            'status'      => 'success',
            'message'     => 'Kategori makanan berhasil ditambahkan.',
            'id_kategori' => (int) $pdo->lastInsertId(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 3. PUT: Perbarui kategori makanan
    if ($method === 'PUT') {
        $idKategori   = isset($input['id_kategori']) && is_numeric($input['id_kategori'])
            ? (int) $input['id_kategori']
            : (isset($input['id']) && is_numeric($input['id']) ? (int) $input['id'] : 0);
        $namaKategori = trim((string) ($input['nama_kategori'] ?? ''));
        $deskripsi    = isset($input['deskripsi']) ? trim((string) $input['deskripsi']) : null;

        if ($idKategori <= 0 || $namaKategori === '') {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'ID kategori dan nama kategori wajib diisi dengan benar.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $sql = "UPDATE kategori_makanan SET nama_kategori = ?, deskripsi = ? WHERE id_kategori = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$namaKategori, $deskripsi !== '' ? $deskripsi : null, $idKategori]);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Kategori makanan berhasil diperbarui.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 4. DELETE: Hapus kategori makanan (dibatasi jika masih ada penyedia makanan yang terikat)
    if ($method === 'DELETE') {
        $idKategori = isset($input['id_kategori']) && is_numeric($input['id_kategori'])
            ? (int) $input['id_kategori']
            : (isset($input['id']) && is_numeric($input['id']) ? (int) $input['id'] : 0);

        if ($idKategori <= 0) {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'ID kategori tidak valid.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Cek apakah masih ada penyedia makanan yang menggunakan kategori ini (ON DELETE RESTRICT)
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM penyedia_makanan WHERE id_kategori = ?");
        $stmtCount->execute([$idKategori]);
        $boundCount = (int) $stmtCount->fetchColumn();

        if ($boundCount > 0) {
            http_response_code(409);
            echo json_encode([
                'status'  => 'error',
                'message' => "Kategori ini tidak dapat dihapus karena masih digunakan oleh {$boundCount} penyedia makanan. Silakan ubah kategori atau hapus penyedia makanan terkait terlebih dahulu.",
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmtDelete = $pdo->prepare("DELETE FROM kategori_makanan WHERE id_kategori = ?");
        $stmtDelete->execute([$idKategori]);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Kategori makanan berhasil dihapus.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(405);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Metode HTTP tidak didukung.',
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    // Tangani juga constraint violation SQLSTATE 23000 (1451) secara ramah
    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Kategori tidak dapat dihapus karena masih memiliki relasi dengan data penyedia makanan.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Kesalahan database pada pengelolaan kategori: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
