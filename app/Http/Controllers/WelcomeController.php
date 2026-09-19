<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class WelcomeController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect()->away('https://albaraka-holland.nl/');
    }
}
