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
        die('Access denied. This page requires role: ' . implode(' or ', $roles));
    }
}

function require_staff_or_admin(): void {
    require_login();
    $u = current_user();
    if (!in_array($u['role'], ['admin', 'staff'], true)) {
        http_response_code(403);
        die('Access denied.');
    }
}

/**
 * Role-based permission checker.
 * Usage: if (can('delete', 'products')) { ... }
 */
function can(string $action, ?string $resource = null): bool {
    $u = current_user();
    if (!$u) return false;
    $role = $u['role'];

    if ($role === 'admin') return true;

    if ($role === 'staff') {
        // Admin-only modules
        if (in_array($resource, ['users', 'audit_logs', 'system_settings'], true)) {
            return false;
        }
        // Destructive actions blocked
        if ($action === 'delete') return false;
        if ($resource === 'suppliers' && in_array($action, ['edit', 'delete'], true)) return false;
        if ($resource === 'sales'     && in_array($action, ['void', 'refund'], true)) return false;
        if ($resource === 'purchase_orders' && $action === 'approve') return false;
        return true;
    }

    return false;
}

function is_admin(): bool {
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

function is_staff(): bool {
    $u = current_user();
    return $u && $u['role'] === 'staff';
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

/**
 * Build a WHERE clause fragment + params array from common filter inputs.
 */
function build_product_filters(array $get, array $aliases = []): array {
    $p = $aliases[0] ?? 'p';
    $b = $aliases[1] ?? 'b';

    $where  = ' WHERE 1=1';
    $params = [];

    if (!empty($get['q'])) {
        $where .= " AND ($p.product_name LIKE ? OR $p.generic_name LIKE ? OR $p.product_code LIKE ? OR $p.brand LIKE ?)";
        $s = '%' . $get['q'] . '%';
        array_push($params, $s, $s, $s, $s);
    }

    if (!empty($get['cat']) && ctype_digit((string)$get['cat'])) {
        $where .= " AND $p.category_id = ?";
        $params[] = (int)$get['cat'];
    }

    if (!empty($get['type'])) {
        switch ($get['type']) {
            case 'medicines':
                $where .= " AND c.category_name NOT IN ('Cosmetics','Milks')";
                break;
            case 'cosmetics':
                $where .= " AND c.category_name = 'Cosmetics'";
                break;
            case 'milks':
                $where .= " AND c.category_name = 'Milks'";
                break;
        }
    }

    if (!empty($get['age'])) {
        $where .= " AND $p.age_group = ?";
        $params[] = $get['age'];
    }

    if (!empty($get['status'])) {
        if ($get['status'] === 'active' || $get['status'] === 'inactive') {
            $where .= " AND $p.status = ?";
            $params[] = $get['status'];
        }
    }

    return [$where, $params];
}

/**
 * Returns 'low' | 'out' | 'normal'
 */
function stock_status(int $qty, int $reorder): string {
    if ($qty <= 0) return 'out';
    if ($qty <= $reorder) return 'low';
    return 'normal';
}

/**
 * Builds a querystring preserving current GET params, overriding given ones.
 */
function qs(array $overrides = []): string {
    $params = array_merge($_GET, $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return $params ? '?' . http_build_query($params) : '';
}

/**
 * Returns a URL to a customer portal page.
 * Usage: customer_url('products.php')  →  /megapharm/customer/products.php
 *        customer_url('')              →  /megapharm/customer/
 */
function customer_url(string $path = ''): string {
    return base_url('customer/' . ltrim($path, '/'));
}

/**
 * Returns a URL to a customer-includes asset.
 * (Used for header/footer links back to customer pages.)
 */
function admin_url(string $path = ''): string {
    return base_url('admin/' . ltrim($path, '/'));
}