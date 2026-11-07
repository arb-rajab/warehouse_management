<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\Admin\Concerns\FlashesSuccess;
use App\Http\Controllers\Store\Admin\Concerns\SearchesOptions;
use App\Http\Requests\Store\Admin\FilterNotificationsRequest;
use App\Http\Requests\Store\Admin\SearchOptionsRequest;
use App\Http\Requests\Store\Admin\StoreCustomerNotificationRequest;
use App\Http\Resources\Store\CustomerNotificationResource;
use App\Models\Notification;
use App\Models\StoreUser;
use App\Support\NewestFirst;
use App\Support\SearchOption;
use Google\Client as GoogleClient;
use Illuminate\Http\JsonResponse;
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
    use FlashesSuccess;
    use SearchesOptions;

    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:add_notification', only: ['index', 'create', 'customers', 'store']),
            new Middleware('permission:delete_notification', only: ['destroy']),
        ];
    }

    public function index(FilterNotificationsRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);

        return Inertia::render('Store/Admin/Notifications/Customers/Index', [
            'notifications' => $this->paginated(CustomerNotificationResource::collection(
                Notification::query()->select(['id', 'user_id', 'title', 'status'])
                    ->with('user:id,name')->tap(NewestFirst::apply(...))->paginate($perPage)->withQueryString()
            )),
            'filters' => ['per_page' => $perPage],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Store/Admin/Notifications/Customers/Create');
    }

    /** The typeahead behind the notification's customer select (StoreSearchSelect). */
    public function customers(SearchOptionsRequest $request): JsonResponse
    {
        $term = $this->searchTerm($request);
        $customers = StoreUser::query()->select(['id', 'name', 'email', 'phone', 'AccSysID', 'member_serial', 'rep_discount_percentage'])->where('user_type', 'customer');
        if ($term !== '') {
            $this->whereWordsMatch($customers, ['name', 'email', 'phone', 'AccSysID', 'member_serial'], $term);
        }
        $this->orderByRelevance($customers, 'name', $term);

        return $this->optionsPage($customers, fn (StoreUser $user): array => SearchOption::customer($user));
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
        $this->flashSuccess('notification_has_been_sent_successfully');

        return redirect()->route('admin.notifications.all');
    }

    public function destroy(Request $request, Notification $notification): RedirectResponse
    {
        $notification->delete();
        $this->flashSuccess('notification_has_been_deleted_successfully');

        return $this->redirectPreservingQuery('admin.notifications.all', $request);
    }

    private function sendFailure(): RedirectResponse
    {
        return redirect()->route('admin.notifications.create')->withErrors(['body' => __('messages.notification_send_failed')]);
    }
}
