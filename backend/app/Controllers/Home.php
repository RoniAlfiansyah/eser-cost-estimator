<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        if (is_logged_in()) {
            return redirect()->to('/dashboard');
        }

        return redirect()->to('/login');
    }
}
