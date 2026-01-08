<?php
// app/core/Session.php

class Session
{
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, ?string $value = null): ?string
    {
        // set flash
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }
        // get + delete flash
        $msg = $_SESSION['_flash'][$key] ?? null;
        if (isset($_SESSION['_flash'][$key])) unset($_SESSION['_flash'][$key]);
        return $msg;
    }
}
