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

/**
 * Identitas device untuk customer (disimpan di cookie).
 * Dipakai untuk: 1 device = 1 tiket aktif per cabang per hari + rate limit per device.
 */
function qn_client_uuid(): string
{
    if (!empty($_COOKIE['qn_cid'])) {
        return (string)$_COOKIE['qn_cid'];
    }

    // 32-char hex id (cukup untuk MVP)
    $uuid = bin2hex(random_bytes(16));

    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    setcookie('qn_cid', $uuid, [
        'expires'  => time() + (86400 * 365), // 1 tahun
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $secure,
    ]);

    // supaya request ini juga langsung “lihat” cookienya
    $_COOKIE['qn_cid'] = $uuid;

    return $uuid;
}

/**
 * IP client sederhana (cukup untuk rate limit MVP).
 * Kalau kamu pakai reverse proxy/CDN, baru pertimbangkan X-Forwarded-For.
 */
function qn_client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
