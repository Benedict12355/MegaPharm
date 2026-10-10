<?php
require_once __DIR__ . '/../includes/functions.php';
if (!empty($_SESSION['customer'])) {
    audit('Logout', 'Customer', 'Logged out');
}
unset($_SESSION['customer']);
session_destroy();
header('Location: ' . base_url('customer/login.php'));
exit;