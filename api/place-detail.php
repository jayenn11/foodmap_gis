<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Metode request tidak diizinkan. Gunakan GET.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$idPenyedia = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;

if ($idPenyedia <= 0) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Parameter id penyedia makanan tidak valid atau tidak ditemukan.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $pdo->exec("SET time_zone = '+07:00'");

    $sqlPlace = "SELECT 
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
                    CASE WHEN CURRENT_TIME() BETWEEN p.jam_buka AND p.jam_tutup THEN 1 ELSE 0 END AS is_open
                 FROM penyedia_makanan p
                 INNER JOIN kategori_makanan k ON p.id_kategori = k.id_kategori
                 WHERE p.id_penyedia = ?
                 LIMIT 1";

    $stmtPlace = $pdo->prepare($sqlPlace);
    $stmtPlace->execute([$idPenyedia]);
    $place = $stmtPlace->fetch();

    if (!$place) {
        http_response_code(404);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Data penyedia makanan tidak ditemukan.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sqlMenus = "SELECT id_menu, nama_menu, harga 
                 FROM menu_makanan 
                 WHERE id_penyedia = ? 
                 ORDER BY id_menu ASC";

    $stmtMenus = $pdo->prepare($sqlMenus);
    $stmtMenus->execute([$idPenyedia]);
    $menus = $stmtMenus->fetchAll();

    $prices = [];
    foreach ($menus as &$menu) {
        $menu['id_menu'] = (int) $menu['id_menu'];
        $menu['harga']   = (float) $menu['harga'];
        $prices[]        = $menu['harga'];
    }
    unset($menu);

    $place['id_penyedia'] = (int) $place['id_penyedia'];
    $place['id_kategori'] = (int) $place['id_kategori'];
    $place['latitude']    = (float) $place['latitude'];
    $place['longitude']   = (float) $place['longitude'];
    $place['is_open']     = (bool) $place['is_open'];
    $place['min_price']   = !empty($prices) ? min($prices) : 0.0;
    $place['max_price']   = !empty($prices) ? max($prices) : 0.0;
    $place['menus']       = $menus;

    echo json_encode([
        'status' => 'success',
        'data'   => $place,
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Gagal mengambil detail tempat makan: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
