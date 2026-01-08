<?php
// app/core/Auth.php

class Auth
{
    public static function check(): bool
    {
        return (bool) Session::get('owner');
    }

    public static function user(): ?array
    {
        $o = Session::get('owner');
        return is_array($o) ? $o : null;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int)$u['id'] : null;
    }
}
