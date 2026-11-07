<?php
declare(strict_types=1);

$db_host = '127.0.0.1';
$db_name = 'foodmap_gis';
$db_user = 'root';
$db_pass = 'rahasia';
$db_charset = 'utf8mb4';

$dsn = "mysql:host={$db_host};dbname={$db_name};charset={$db_charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status'  => 'error',
        'message' => 'Koneksi database gagal: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

