<?php
// app/controllers/HomeController.php

class HomeController extends Controller
{
    public function landing()
    {
        return View::render('home/landing', [
            'title' => 'QueueNow — Kelola antrean bisnis lebih cepat & rapi',
        ], 'layouts/guest');
    }
}
