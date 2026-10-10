<?php
require_once __DIR__ . '/config/database.php';
echo "<h2>base_url() Test</h2>";
echo "<p>DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>base_url(''): <b>" . base_url('') . "</b></p>";
echo "<p>base_url('assets/css/style.css'): <b>" . base_url('assets/css/style.css') . "</b></p>";
echo "<p>base_url('customer/index.php'): <b>" . base_url('customer/index.php') . "</b></p>";