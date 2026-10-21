<?php

// Forward request ke entrypoint Laravel public/index.php
// Pastikan working directory adalah project root agar vendor & storage terbaca
chdir(__DIR__ . '/..');

require __DIR__ . '/../public/index.php';
