<?php
// app/middleware/AuthMiddleware.php

class AuthMiddleware
{
    public function handle(): void
    {
        if (!Auth::check()) {
            Session::flash('error', 'Silakan login terlebih dahulu.');
            redirect('/login');
        }
    }
}
