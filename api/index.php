<?php

// Logging debug untuk inspect $_SERVER di Vercel
if (isset($_GET['__dump_server'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? null,
        'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? null,
        'PATH_INFO' => $_SERVER['PATH_INFO'] ?? null,
        'PHP_SELF' => $_SERVER['PHP_SELF'] ?? null,
        'QUERY_STRING' => $_SERVER['QUERY_STRING'] ?? null,
        'HTTP_X_NOW_ROUTE' => $_SERVER['HTTP_X_NOW_ROUTE'] ?? null,
        'HTTP_X_FORWARDED_URI' => $_SERVER['HTTP_X_FORWARDED_URI'] ?? null,
    ], JSON_PRETTY_PRINT);
    exit;
}

chdir(__DIR__ . '/..');

require __DIR__ . '/../public/index.php';
