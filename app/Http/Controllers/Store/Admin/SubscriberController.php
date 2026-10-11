<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterSubscribersRequest;
use App\Http\Resources\Store\SubscriberResource;
use App\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class SubscriberController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_all_subscribers', only: ['index']),
            new Middleware('permission:delete_subscriber', only: ['destroy']),
        ];
    }

    public function index(FilterSubscribersRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);

        return Inertia::render('Store/Admin/Marketing/Subscribers/Index', [
            'subscribers' => $this->paginated(SubscriberResource::collection(
                Subscriber::query()->select(['id', 'email', 'created_at'])
                    ->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage)->withQueryString()
            )),
            'filters' => ['per_page' => $perPage],
        ]);
    }

    public function destroy(Request $request, Subscriber $subscriber): RedirectResponse
    {
        $subscriber->delete();

        return $this->redirectPreservingQuery('admin.subscribers.index', $request);
    }
}
