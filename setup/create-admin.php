<?php
// setup/create-admin.php
require_once __DIR__ . '/../config/database.php';

$username = 'admin';
$email    = 'admin@megapharm.com';
$password = 'Admin@123';
$hash     = password_hash($password, PASSWORD_DEFAULT);

$stmt = db()->prepare('SELECT id FROM users WHERE username = ?');
$stmt->execute([$username]);
if ($stmt->fetch()) {
    $upd = db()->prepare('UPDATE users SET password_hash = ?, status = "active", role = "admin" WHERE username = ?');
    $upd->execute([$hash, $username]);
    echo "Admin exists. Password reset to: <b>$password</b><br>";
} else {
    $ins = db()->prepare('INSERT INTO users (full_name, username, email, password_hash, role, status) VALUES (?,?,?,?,?,?)');
    $ins->execute(['System Administrator', $username, $email, $hash, 'admin', 'active']);
    echo "Admin created. Username: <b>$username</b> / Password: <b>$password</b><br>";
}
echo "Delete this file now for security.<br>";
echo "<a href='../login.php'>Go to Login</a>";