<?php
// app/core/Autoload.php
// Autoload class sederhana: cari class di app/core dan app/controllers

require_once __DIR__ . '/helpers.php';

spl_autoload_register(function ($class) {
    $class = ltrim($class, '\\');
    $base  = dirname(__DIR__); // => app/

    $paths = [
        $base . '/core/' . $class . '.php',
        $base . '/controllers/' . $class . '.php',
        $base . '/middleware/' . $class . '.php',
        $base . '/models/' . $class . '.php',
        $base . '/services/' . $class . '.php',
        $base . '/repositories/' . $class . '.php',
    ];

    foreach ($paths as $file) {
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});
