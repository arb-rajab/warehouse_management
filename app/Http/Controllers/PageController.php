<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function show_custom_page(string $slug): View
    {
        $page = Page::where('slug', $slug)->first();
        if ($page != null) {
            return view('shop.frontend.custom_page', compact('page'));
        }
        abort(404);
    }

    public function mobile_custom_page(string $slug): View
    {
        $page = Page::where('slug', $slug)->first();
        if ($page != null) {
            return view('shop.frontend.m_custom_page', compact('page'));
        }
        abort(404);
    }
}
