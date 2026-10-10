<?php
// includes/functions.php
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function redirect(string $url): void { header('Location: ' . $url); exit; }

function flash(string $type, string $msg): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $msg];
}

function get_flashes(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool { return !empty($_SESSION['user']); }

function require_login(): void {
    if (!is_logged_in()) {
        redirect(base_url('login.php'));
    }
}

function require_role(string ...$roles): void {
    require_login();
    $u = current_user();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        die('Access denied.');
    }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(?string $token): void {
    if (!$token || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        die('Invalid CSRF token.');
    }
}

function audit(string $action, string $module, string $details = ''): void {
    $u = current_user();
    $stmt = db()->prepare(
        'INSERT INTO audit_logs (user_id, username_snapshot, role_snapshot, action, module, details, ip_address)
         VALUES (?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $u['id'] ?? null,
        $u['username'] ?? 'guest',
        $u['role'] ?? 'guest',
        $action, $module, $details,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function setting(string $key, $default = null) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT setting_key, setting_value FROM system_settings') as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache[$key] ?? $default;
}

function peso($n): string { return '₱' . number_format((float)$n, 2); }

function next_number(string $table, string $column, string $prefix, int $pad = 4): string {
    $row = db()->query("SELECT $column FROM $table WHERE $column LIKE " . db()->quote($prefix . '%') . " ORDER BY id DESC LIMIT 1")->fetch();
    $n = 1;
    if ($row) {
        $parts = explode('-', $row[$column]);
        $n = ((int)end($parts)) + 1;
    }
    return $prefix . str_pad((string)$n, $pad, '0', STR_PAD_LEFT);
}

function notify(int $userId, string $type, string $title, string $message, ?string $relatedType = null, ?int $relatedId = null): void {
    $stmt = db()->prepare('INSERT INTO notifications (user_id, notification_type, title, message, related_type, related_id) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$userId, $type, $title, $message, $relatedType, $relatedId]);
}

function notify_admins(string $type, string $title, string $message, ?string $relatedType = null, ?int $relatedId = null): void {
    $stmt = db()->query("SELECT id FROM users WHERE role='admin' AND status='active'");
    foreach ($stmt as $r) notify((int)$r['id'], $type, $title, $message, $relatedType, $relatedId);
}