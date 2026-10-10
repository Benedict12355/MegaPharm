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
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }
    return $pdo;
}

/**
 * Robust base_url() that works from ANY subfolder depth.
 * Always returns an ABSOLUTE path starting with "/".
 *
 * Example outputs:
 *   If project is at C:\xampp\htdocs\megapharm\          → base = /megapharm
 *   If project is at C:\xampp\htdocs\proj\megapharm\     → base = /proj/megapharm
 */
function base_url(string $path = ''): string {
    static $base = null;

    if ($base === null) {
        // Filesystem path to project root (this file is in /config/, so go up one)
        $rootFs = str_replace('\\', '/', dirname(__DIR__));

        // Filesystem path to the web root (htdocs)
        $docRootFs = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));

        // Strip the htdocs prefix to get the URL-relative path
        $rel = str_replace($docRootFs, '', $rootFs);

        // Ensure leading slash, no trailing slash
        $base = '/' . trim($rel, '/');
        if ($base === '/') $base = '';
    }

    // Always return an absolute path
    return $base . '/' . ltrim($path, '/');
}