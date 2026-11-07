<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../config/database.php';

function getMenuRequestData(): array
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
$input  = getMenuRequestData();
$action = strtolower(trim((string) ($input['action'] ?? '')));

if ($method === 'POST') {
    if ($action === 'update' || $action === 'put') {
        $method = 'PUT';
    } elseif ($action === 'delete') {
        $method = 'DELETE';
    }
}

try {
    // 1. GET: Ambil daftar menu berdasarkan id_penyedia
    if ($method === 'GET') {
        $idPenyedia = isset($input['id_penyedia']) && is_numeric($input['id_penyedia'])
            ? (int) $input['id_penyedia']
            : (isset($input['place_id']) && is_numeric($input['place_id']) ? (int) $input['place_id'] : 0);

        if ($idPenyedia <= 0) {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Parameter id_penyedia tidak valid.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $sql = "SELECT id_menu, id_penyedia, nama_menu, harga 
                FROM menu_makanan 
                WHERE id_penyedia = ? 
                ORDER BY id_menu ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idPenyedia]);
        $menus = $stmt->fetchAll();

        foreach ($menus as &$menu) {
            $menu['id_menu']     = (int) $menu['id_menu'];
            $menu['id_penyedia'] = (int) $menu['id_penyedia'];
            $menu['harga']       = (float) $menu['harga'];
        }
        unset($menu);

        echo json_encode([
            'status' => 'success',
            'total'  => count($menus),
            'data'   => $menus,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. POST: Tambah menu makanan baru
    if ($method === 'POST') {
        $idPenyedia = isset($input['id_penyedia']) && is_numeric($input['id_penyedia']) ? (int) $input['id_penyedia'] : 0;
        $namaMenu   = trim((string) ($input['nama_menu'] ?? ''));
        $hargaRaw   = $input['harga'] ?? null;

        if ($idPenyedia <= 0 || $namaMenu === '' || $hargaRaw === null || !is_numeric($hargaRaw) || (float) $hargaRaw < 0) {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'ID penyedia, nama menu, dan harga (angka positif) wajib diisi.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $harga = (float) $hargaRaw;

        $sql = "INSERT INTO menu_makanan (id_penyedia, nama_menu, harga) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$idPenyedia, $namaMenu, $harga]);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Menu makanan berhasil ditambahkan.',
            'id_menu' => (int) $pdo->lastInsertId(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 3. PUT: Perbarui data menu makanan
    if ($method === 'PUT') {
        $idMenu   = isset($input['id_menu']) && is_numeric($input['id_menu'])
            ? (int) $input['id_menu']
            : (isset($input['id']) && is_numeric($input['id']) ? (int) $input['id'] : 0);
        $namaMenu = trim((string) ($input['nama_menu'] ?? ''));
        $hargaRaw = $input['harga'] ?? null;

        if ($idMenu <= 0 || $namaMenu === '' || $hargaRaw === null || !is_numeric($hargaRaw) || (float) $hargaRaw < 0) {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'ID menu, nama menu, dan harga (angka positif) wajib diisi dengan benar.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $harga = (float) $hargaRaw;

        $sql = "UPDATE menu_makanan SET nama_menu = ?, harga = ? WHERE id_menu = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$namaMenu, $harga, $idMenu]);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Menu makanan berhasil diperbarui.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 4. DELETE: Hapus menu makanan
    if ($method === 'DELETE') {
        $idMenu = isset($input['id_menu']) && is_numeric($input['id_menu'])
            ? (int) $input['id_menu']
            : (isset($input['id']) && is_numeric($input['id']) ? (int) $input['id'] : 0);

        if ($idMenu <= 0) {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'ID menu yang akan dihapus tidak valid.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM menu_makanan WHERE id_menu = ?");
        $stmt->execute([$idMenu]);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Menu makanan berhasil dihapus.',
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
        'message' => 'Kesalahan database pada pengelolaan menu: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
