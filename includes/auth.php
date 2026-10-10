<?php
// includes/auth.php
require_once __DIR__ . '/functions.php';

function attempt_login(string $username, string $password): bool {
    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    if (!$user) return false;
    if ($user['status'] !== 'active') return false;
    if (!password_verify($password, $user['password_hash'])) return false;

    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'full_name' => $user['full_name'],
        'role' => $user['role'],
    ];
    db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);
    audit('Login', 'Login', 'Logged in');
    return true;
}