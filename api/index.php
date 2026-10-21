<?php

// Normalisasi URL untuk runtime Vercel PHP serverless:
// Ketika Vercel melakukan route ke /api/index.php, $_SERVER['REQUEST_URI']
// sering kali diset menjadi "/api/index.php" atau path tanpa prefix.
if (isset($_SERVER['HTTP_X_NOW_ROUTE']) || isset($_SERVER['VERCEL_URL']) || isset($_ENV['VERCEL_URL'])) {
    if (isset($_SERVER['HTTP_X_FORWARDED_URI'])) {
        $_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_FORWARDED_URI'];
    } elseif (isset($_SERVER['HTTP_X_ORIGINAL_URI'])) {
        $_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_ORIGINAL_URI'];
    } elseif (isset($_SERVER['HTTP_X_REWRITE_URL'])) {
        $_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_REWRITE_URL'];
    }
}

chdir(__DIR__ . '/..');

require __DIR__ . '/../public/index.php';
