<?php
// config/db.php
// Central database connection used by every page. Credentials live in config/config.php.
// Include this at the top of any file that needs the database:
//   require_once __DIR__ . '/../config/db.php';

require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Keep MySQL NOW() in Philippine time so timestamps match what users see.
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+08:00'",
        ]
    );
} catch (PDOException $e) {
    error_log('Karga-Ments DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    if (APP_ENV === 'local') {
        die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
    }
    die('The system is temporarily unavailable. Please try again later.');
}
