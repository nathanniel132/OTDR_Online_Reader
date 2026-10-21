<?php

// Make Symfony treat public/index.php as the entry script, not /api/index.php.
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

// Forward request ke public/index.php
// Tangani routing Vercel: pastikan REQUEST_URI dan SCRIPT_NAME bersih dari prefix /public/index.php
if (isset($_SERVER['REQUEST_URI'])) {
    // Hilangkan prefix /public/index.php jika Vercel menyertakannya ke REQUEST_URI
    if (str_starts_with($_SERVER['REQUEST_URI'], '/public/index.php')) {
        $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], strlen('/public/index.php')) ?: '/';
    } elseif (str_starts_with($_SERVER['REQUEST_URI'], '/index.php')) {
        $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], strlen('/index.php')) ?: '/';
    }
}

chdir(__DIR__ . '/..');

require __DIR__ . '/../public/index.php';
