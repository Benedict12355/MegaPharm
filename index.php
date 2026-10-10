<?php
require_once __DIR__ . '/includes/functions.php';
redirect(is_logged_in() ? base_url('admin/dashboard.php') : base_url('login.php'));