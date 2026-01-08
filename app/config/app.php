<?php
// app/config/app.php

// Detect scheme behind reverse proxy (Azure App Service)
$scheme = 'http';

// Azure / reverse proxy headers
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $scheme = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]); // "https,http" -> "https"
} elseif (!empty($_SERVER['HTTP_X_ARR_SSL'])) {
    $scheme = 'https';
} elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $scheme = 'https';
}

$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';

$basePath = rtrim(str_replace('\\', '/', dirname($script)), '/');
$baseUrl  = $scheme . '://' . $host . $basePath;

return [
    'name'     => 'QueueNow',
    'timezone' => 'Asia/Jakarta',
    'base_url' => $baseUrl,
    'debug'    => true,
    'session' => [
        'key'      => 'queuenow_session',
        'lifetime' => 60 * 60 * 2,
    ],
];


// // app/config/app.php

// // Auto-detect base URL (cocok untuk localhost maupun hosting)
// $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
// $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
// $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';

// // dirname('/queuenow-native/index.php') => '/queuenow-native'
// $basePath = rtrim(str_replace('\\', '/', dirname($script)), '/');
// $baseUrl  = $scheme . '://' . $host . $basePath;

// return [
//     'name'     => 'QueueNow',
//     'timezone' => 'Asia/Jakarta',
//     'base_url' => $baseUrl,            // contoh: http://localhost/queuenow-native
//     'debug'    => true,                // nanti kalau production ubah false

//     // Session
//     'session' => [
//         'key'      => 'queuenow_session',
//         'lifetime' => 60 * 60 * 2,     // 2 jam (dalam detik)
//     ],
// ];

