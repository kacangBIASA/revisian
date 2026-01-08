<?php
// app/core/CSRF.php

class CSRF
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(16));
        }
        return (string) $_SESSION['_csrf'];
    }

    public static function verify(?string $token): bool
    {
        $saved = $_SESSION['_csrf'] ?? null;
        return is_string($saved) && is_string($token) && $token !== '' && hash_equals($saved, $token);
    }

    // helper untuk ambil token dari request POST
    public static function requestToken(): ?string
    {
        return $_POST['_csrf'] ?? $_POST['_token'] ?? null;
    }
}
