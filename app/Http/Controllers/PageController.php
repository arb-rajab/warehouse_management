<?php

namespace App\Http\Controllers;

use App\Models\Page;

/**
 * The storefront's page rendering. The back office's page administration is
 * Store\Admin\WebsitePageController (.ai/archive/store-backoffice/changes-20261013.md §20).
 */
class PageController extends Controller
{
    public function show_custom_page($slug){
        $page = Page::where('slug', $slug)->first();
        if($page != null){
            return view('shop.frontend.custom_page', compact('page'));
        }
        abort(404);
    }
    public function mobile_custom_page($slug){
        $page = Page::where('slug', $slug)->first();
        if($page != null){
            return view('shop.frontend.m_custom_page', compact('page'));
        }
        abort(404);
    }
}
