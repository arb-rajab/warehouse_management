<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterNotificationsRequest;
use App\Http\Resources\Store\AdminNotificationResource;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [];
    }

    public function index(FilterNotificationsRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $user = $request->user('store');
        $notifications = $user->notifications()->select(['id', 'type', 'data', 'created_at'])
            ->orderByDesc('id')->paginate($perPage)->withQueryString();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return Inertia::render('Store/Admin/Notifications/Index', [
            'notifications' => $this->paginated(AdminNotificationResource::collection($notifications)),
            'filters' => ['per_page' => $perPage],
        ]);
    }
}
