<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class WebsiteController extends Controller implements HasMiddleware
{
	/**
	 * Get the middleware that should be assigned to the controller.
	 *
	 * See Auth\LoginController::middleware()'s docblock for why this
	 * replaces the constructor-based $this->middleware() call.
	 *
	 * @return array<int, \Illuminate\Routing\Controllers\Middleware>
	 */
	public static function middleware(): array
	{
	    return [
	        new Middleware('permission:header_setup', only: ['header']),
	        new Middleware('permission:footer_setup', only: ['footer']),
	        new Middleware('permission:view_all_website_pages', only: ['pages']),
	        new Middleware('permission:website_appearance', only: ['appearance']),
	    ];
	}
	public function header(Request $request)
	{
		return view('shop.backend.website_settings.header');
	}
	public function footer(Request $request)
	{
		$lang = $request->lang;
		return view('shop.backend.website_settings.footer', compact('lang'));
	}
	public function pages(Request $request)
	{
		return view('shop.backend.website_settings.pages.index');
	}
	public function appearance(Request $request)
	{
		return view('shop.backend.website_settings.appearance');
	}
}
