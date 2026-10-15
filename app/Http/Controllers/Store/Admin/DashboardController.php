<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public const string GRAPH_CACHE_KEY = 'store.admin.dashboard.graphs.v1';

    public function clearCache(): RedirectResponse
    {
        Cache::forget(self::GRAPH_CACHE_KEY);
        Cache::forget('cached_graph_data');

        return redirect()->route('shop.admin.dashboard');
    }
}
