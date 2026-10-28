<?php

namespace App\Http\Middleware;

use App\Support\StoreLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Language
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale');

        app()->setLocale(StoreLocale::isSupported($locale) ? $locale : config('app.locale'));

        return $next($request);
    }
}
