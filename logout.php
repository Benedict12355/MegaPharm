<?php
require_once __DIR__ . '/includes/functions.php';
if (is_logged_in()) audit('Logout', 'Login', 'Logged out');
session_destroy();
redirect(base_url('login.php'));