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

require_once __DIR__ . '/../config/database.php';

try {
    // Pastikan zona waktu sesi MySQL menggunakan WIB (+07:00) untuk CURRENT_TIME()
    $pdo->exec("SET time_zone = '+07:00'");

    // Tangkap parameter query string
    $search    = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
    $kategori  = isset($_GET['kategori']) && $_GET['kategori'] !== '' ? (int) $_GET['kategori'] : null;
    $openNow   = isset($_GET['open_now']) && filter_var($_GET['open_now'], FILTER_VALIDATE_BOOLEAN);
    $minPrice  = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (float) $_GET['min_price'] : null;
    $maxPrice  = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (float) $_GET['max_price'] : null;
    $userLat   = isset($_GET['user_lat']) && is_numeric($_GET['user_lat']) ? (float) $_GET['user_lat'] : null;
    $userLng   = isset($_GET['user_lng']) && is_numeric($_GET['user_lng']) ? (float) $_GET['user_lng'] : null;
    $radius    = isset($_GET['radius']) && is_numeric($_GET['radius']) && (float) $_GET['radius'] > 0 ? (float) $_GET['radius'] : null;

    // Mendukung parameter 'nearest' maupun 'limit_nearest'
    $nearestRaw = $_GET['nearest'] ?? ($_GET['limit_nearest'] ?? null);
    $nearest    = $nearestRaw !== null && filter_var($nearestRaw, FILTER_VALIDATE_BOOLEAN);

    $hasUserLocation = ($userLat !== null && $userLng !== null);

    $selectParams = [];
    $whereClauses = [];
    $whereParams  = [];
    $havingClauses = [];
    $havingParams  = [];

    // Kalkulasi Spasial (Formula Haversine) jika koordinat pengguna tersedia
    if ($hasUserLocation) {
        $distanceSelect = "(6371 * acos(LEAST(1.0, GREATEST(-1.0, cos(radians(?)) * cos(radians(p.latitude)) * cos(radians(p.longitude) - radians(?)) + sin(radians(?)) * sin(radians(p.latitude)))))) AS distance_km";
        $selectParams[] = $userLat;
        $selectParams[] = $userLng;
        $selectParams[] = $userLat;
    } else {
        $distanceSelect = "NULL AS distance_km";
    }

    // Filter pencarian parsial pada nama_penyedia ATAU nama_menu
    if ($search !== '') {
        $likeQuery = '%' . $search . '%';
        $whereClauses[] = "(p.nama_penyedia LIKE ? OR EXISTS (
            SELECT 1 FROM menu_makanan ms 
            WHERE ms.id_penyedia = p.id_penyedia AND ms.nama_menu LIKE ?
        ))";
        $whereParams[] = $likeQuery;
        $whereParams[] = $likeQuery;
    }

    // Filter kategori
    if ($kategori !== null && $kategori > 0) {
        $whereClauses[] = "p.id_kategori = ?";
        $whereParams[]  = $kategori;
    }

    // Filter jam operasional (Buka Sekarang)
    if ($openNow) {
        $whereClauses[] = "(CURRENT_TIME() BETWEEN p.jam_buka AND p.jam_tutup)";
    }

    // Filter rentang harga menu
    if ($minPrice !== null && $maxPrice !== null) {
        $whereClauses[] = "EXISTS (
            SELECT 1 FROM menu_makanan mp 
            WHERE mp.id_penyedia = p.id_penyedia AND mp.harga >= ? AND mp.harga <= ?
        )";
        $whereParams[] = $minPrice;
        $whereParams[] = $maxPrice;
    } elseif ($minPrice !== null) {
        $whereClauses[] = "EXISTS (
            SELECT 1 FROM menu_makanan mp 
            WHERE mp.id_penyedia = p.id_penyedia AND mp.harga >= ?
        )";
        $whereParams[] = $minPrice;
    } elseif ($maxPrice !== null) {
        $whereClauses[] = "EXISTS (
            SELECT 1 FROM menu_makanan mp 
            WHERE mp.id_penyedia = p.id_penyedia AND mp.harga <= ?
        )";
        $whereParams[] = $maxPrice;
    }

    // Filter radius (km) jika koordinat pengguna tersedia
    if ($hasUserLocation && $radius !== null) {
        $havingClauses[] = "distance_km <= ?";
        $havingParams[]  = $radius;
    }

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
                MAX(m.harga) AS max_price,
                GROUP_CONCAT(m.nama_menu ORDER BY m.id_menu ASC SEPARATOR ', ') AS menu_list,
                {$distanceSelect}
            FROM penyedia_makanan p
            INNER JOIN kategori_makanan k ON p.id_kategori = k.id_kategori
            LEFT JOIN menu_makanan m ON p.id_penyedia = m.id_penyedia";

    if (!empty($whereClauses)) {
        $sql .= " WHERE " . implode(' AND ', $whereClauses);
    }

    $sql .= " GROUP BY 
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
                p.foto";

    if (!empty($havingClauses)) {
        $sql .= " HAVING " . implode(' AND ', $havingClauses);
    }

    // Pengurutan dan pembatasan (nearest / default)
    if ($hasUserLocation && $nearest) {
        $sql .= " ORDER BY distance_km ASC LIMIT 5";
    } elseif ($hasUserLocation) {
        $sql .= " ORDER BY distance_km ASC, p.nama_penyedia ASC";
    } else {
        $sql .= " ORDER BY p.id_penyedia ASC";
    }

    $params = array_merge($selectParams, $whereParams, $havingParams);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $places = $stmt->fetchAll();

    foreach ($places as &$place) {
        $place['id_penyedia'] = (int) $place['id_penyedia'];
        $place['id_kategori'] = (int) $place['id_kategori'];
        $place['latitude']    = (float) $place['latitude'];
        $place['longitude']   = (float) $place['longitude'];
        $place['is_open']     = (bool) $place['is_open'];
        $place['menu_count']  = (int) ($place['menu_count'] ?? 0);
        $place['min_price']   = $place['min_price'] !== null ? (float) $place['min_price'] : 0.0;
        $place['max_price']   = $place['max_price'] !== null ? (float) $place['max_price'] : 0.0;
        $place['distance_km'] = $place['distance_km'] !== null ? round((float) $place['distance_km'], 2) : null;
        $place['menu_list']   = $place['menu_list'] !== null ? $place['menu_list'] : '';
    }
    unset($place);

    echo json_encode([
        'status' => 'success',
        'total'  => count($places),
        'data'   => $places,
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Gagal mengambil data tempat makan: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
