<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterNotificationsRequest;
use App\Http\Requests\Store\Admin\StoreCustomerNotificationRequest;
use App\Http\Resources\Store\CustomerNotificationResource;
use App\Models\Notification;
use App\Models\StoreUser;
use Google\Client as GoogleClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CustomerNotificationController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:add_notification', only: ['index', 'create', 'store']),
            new Middleware('permission:delete_notification', only: ['destroy']),
        ];
    }

    public function index(FilterNotificationsRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);

        return Inertia::render('Store/Admin/Notifications/Customers/Index', [
            'notifications' => $this->paginated(CustomerNotificationResource::collection(
                Notification::query()->select(['id', 'user_id', 'title', 'status'])
                    ->with('user:id,name')->orderBy('id')->paginate($perPage)->withQueryString()
            )),
            'filters' => ['per_page' => $perPage],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Store/Admin/Notifications/Customers/Create', [
            'customers' => StoreUser::query()->select(['id', 'name', 'AccSysID'])
                ->where('user_type', 'customer')->orderByDesc('name')->orderBy('id')->get()
                ->map(fn (StoreUser $user): array => ['id' => $user->id, 'name' => $user->name, 'account_id' => $user->AccSysID]),
        ]);
    }

    public function store(StoreCustomerNotificationRequest $request, GoogleClient $client): RedirectResponse
    {
        $user = StoreUser::query()->select(['id', 'device_key'])
            ->where('user_type', 'customer')->findOrFail($request->integer('user_id'));

        if (! $user->device_key) {
            return redirect()->route('admin.notifications.create')->withErrors(['user_id' => __('messages.notification_no_device_token')]);
        }

        try {
            $client->setAuthConfig(storage_path('app/json/file.json'));
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $client->refreshTokenWithAssertion();
            $token = $client->getAccessToken();
            $accessToken = is_array($token) ? ($token['access_token'] ?? null) : null;

            if (! is_string($accessToken) || $accessToken === '') {
                return $this->sendFailure();
            }

            $response = Http::withToken($accessToken)->timeout(30)->post(
                'https://fcm.googleapis.com/v1/projects/'.config('services.fcm.project_id').'/messages:send',
                ['message' => ['token' => $user->device_key, 'notification' => [
                    'title' => $request->validated('title'), 'body' => $request->validated('body'),
                ]]]
            );

            if (! $response->successful()) {
                return $this->sendFailure();
            }

            Notification::query()->create([
                'user_id' => $user->id, 'title' => $request->validated('title'),
                'body' => $request->validated('body'), 'status' => 'sent',
                'response' => json_encode($response->json(), JSON_THROW_ON_ERROR),
            ]);
        } catch (Throwable) {
            return $this->sendFailure();
        }

        return redirect()->route('admin.notifications.all');
    }

    public function destroy(Request $request, Notification $notification): RedirectResponse
    {
        $notification->delete();

        return $this->redirectPreservingQuery('admin.notifications.all', $request);
    }

    private function sendFailure(): RedirectResponse
    {
        return redirect()->route('admin.notifications.create')->withErrors(['body' => __('messages.notification_send_failed')]);
    }
}
