<?php
require_once __DIR__ . '/../includes/functions.php';
if (!empty($_SESSION['supplier'])) {
    audit('Logout', 'Supplier', 'Logged out');
}
unset($_SESSION['supplier']);
session_destroy();
header('Location: ' . base_url('supplier/login.php'));
exit;