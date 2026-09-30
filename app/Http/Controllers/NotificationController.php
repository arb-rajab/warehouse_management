<?php

namespace App\Http\Controllers;

use Auth;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index() {
        $notifications = auth()->user()->notifications()->paginate(15);

        auth()->user()->unreadNotifications->markAsRead();

        if(Auth::user()->user_type == 'admin') {
            return view('shop.backend.notification.index', compact('notifications'));
        }

        if(Auth::user()->user_type == 'seller') {
            return view('shop.seller.notification.index', compact('notifications'));
        }

        if(Auth::user()->user_type == 'customer') {
            return view('shop.frontend.user.customer.notification.index', compact('notifications'));
        }

    }
}
