<?php
// index.php (root)
declare(strict_types=1);

// Autoload sederhana (tanpa composer)
require __DIR__ . '/app/core/Autoload.php';

// Load config lebih dulu (biar bisa ambil session key)
$appConfig = require __DIR__ . '/app/config/app.php';
date_default_timezone_set($appConfig['timezone'] ?? 'Asia/Jakarta');

// Detect HTTPS behind Azure reverse proxy
$isHttps = false;
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $proto = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]);
    $isHttps = ($proto === 'https');
} elseif (!empty($_SERVER['HTTP_X_ARR_SSL'])) {
    $isHttps = true;
} elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $isHttps = true;
}

// Session cookie settings (penting untuk Azure)
$sessionKey = $appConfig['session']['key'] ?? 'PHPSESSID';
session_name($sessionKey);

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',      // jangan set domain azurewebsites.net
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

// Register config ke helper global
$GLOBALS['config'] = [
  'app'      => $appConfig,
  'database' => require __DIR__ . '/app/config/database.php',
  'midtrans' => require __DIR__ . '/app/config/midtrans.php',
  'plans'    => require __DIR__ . '/app/config/plans.php',
  'google'   => require __DIR__ . '/app/config/google.php',
];

// Core
$router = new Router();

// Routes
require __DIR__ . '/app/routes/web.php';

// Jalankan router
$router->dispatch();
