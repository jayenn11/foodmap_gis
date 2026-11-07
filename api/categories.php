<?php
declare(strict_types=1);

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
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Gagal mengambil data kategori: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
