<?php
// config/database.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'megapharm_db');
define('DB_USER', 'root');
define('DB_PASS', '');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }
    return $pdo;
}

function base_url(string $path = ''): string {
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    // Normalize when accessed from /admin/* subfolder
    if (str_ends_with($base, '/admin') || str_ends_with($base, '/api') || str_ends_with($base, '/setup')) {
        $base = dirname($base);
    }
    return $base . '/' . ltrim($path, '/');
}