<?php
/**
 * database.php — Konfigurasi dan koneksi terpusat database MySQL menggunakan PDO.
 *
 * Membaca variabel lingkungan dari file .env di root proyek via vlucas/phpdotenv.
 * Menggunakan prepared statements dan exception mode untuk keamanan maksimal.
 */

// Hindari inisialisasi ganda jika koneksi sudah terbentuk
if (isset($pdo) && $pdo instanceof PDO) {
    return;
}

$projectRoot = dirname(__DIR__);

// Memuat autoloader Composer untuk phpdotenv
require_once $projectRoot . '/vendor/autoload.php';

// Memuat variabel lingkungan dari file .env
$dotenv = Dotenv\Dotenv::createImmutable($projectRoot);
$dotenv->safeLoad();

// Mengambil parameter konfigurasi
$host     = $_ENV['DB_HOST'] ?? 'localhost';
$port     = $_ENV['DB_PORT'] ?? '3306';
$dbname   = $_ENV['DB_NAME'] ?? 'laundryku';
$username = $_ENV['DB_USERNAME'] ?? 'root';
$password = $_ENV['DB_PASSWORD'] ?? '';
$charset  = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

// Validasi konfigurasi minimum
if ($dbname === '' || $username === '') {
    error_log('Konfigurasi database belum lengkap pada file .env.');
    http_response_code(500);
    exit('Konfigurasi database belum lengkap. Periksa file .env.');
}

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log('Koneksi database PDO gagal: ' . $e->getMessage());
    http_response_code(500);
    exit('Koneksi database gagal. Pastikan layanan MySQL berjalan dan konfigurasi .env sesuai.');
}
