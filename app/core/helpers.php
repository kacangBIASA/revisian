<?php
// app/core/helpers.php

function config(string $key, mixed $default = null): mixed
{
    // contoh: config('app.base_url')
    $parts = explode('.', $key);
    $data  = $GLOBALS['config'] ?? [];

    foreach ($parts as $p) {
        if (is_array($data) && array_key_exists($p, $data)) {
            $data = $data[$p];
        } else {
            return $default;
        }
    }
    return $data;
}

function base_url(string $path = ''): string
{
    $base = rtrim((string)config('app.base_url', ''), '/');
    $path = '/' . ltrim($path, '/');
    return $base . ($path === '/' ? '' : $path);
}

function redirect(string $path): never
{
    header('Location: ' . base_url($path));
    exit;
}
