<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\CellStatusLogResource;
use App\Http\Resources\CellVerificationReportResource;
use App\Http\Resources\UserResource;
use App\Models\CellStatusLog;
use App\Models\CellVerificationReport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);

        return Inertia::render('Admin/Users/Index', [
            'users' => $this->paginated(UserResource::collection(
                User::query()
                    ->select(['id', 'name', 'email'])
                    ->with('roles:id,name')
                    ->orderBy('name')
                    ->paginate($perPage)
                    ->withQueryString()
            )),
            'filters' => ['per_page' => $perPage],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create');
    }

    public function show(Request $request, User $user): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $reportsPerPage = $this->resolvePerPage($request, 20, 'reports_per_page');

        $logs = CellStatusLog::query()
            ->forListing()
            ->with(CellStatusLog::WITH_FLAG_DETAILS)
            ->where('user_id', $user->id)
            ->sorted($request)
            ->paginate($perPage)
            ->withQueryString();

        CellStatusLog::attachNextLogs($logs->getCollection());

        $reports = CellVerificationReport::query()
            ->select(CellVerificationReport::SELECT_COLUMNS)
            ->with(CellVerificationReport::WITH_DETAILS)
            ->where('user_id', $user->id)
            ->sorted($request)
            ->paginate($reportsPerPage, ['*'], 'reports_page')
            ->withQueryString();

        return Inertia::render('Admin/Users/Show', [
            'user' => new UserResource($user->loadMissing('roles:id,name')),
            'logs' => $this->paginated(CellStatusLogResource::collection($logs)),
            'reports' => $this->paginated(CellVerificationReportResource::collection($reports)),
            'filters' => ['per_page' => $perPage, 'reports_per_page' => $reportsPerPage],
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'password']));

        $user->must_change_password = true;
        $user->save();

        $user->syncRoles($request->boolean('is_admin') ? ['admin'] : []);

        return redirect()->route('admin.users.index');
    }

    public function edit(User $user): Response
    {
        $user->loadMissing('roles:id,name');

        return Inertia::render('Admin/Users/Edit', [
            'user' => new UserResource($user),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->fill($request->safe()->only(['name', 'email']));

        if ($request->filled('password')) {
            $user->password = $request->validated('password');
            $user->must_change_password = true;
        }

        $user->save();

        $user->syncRoles($request->boolean('is_admin') ? ['admin'] : []);

        return redirect()->route('admin.users.index');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 403, __('messages.cannot_delete_own_account'));

        if ($user->hasHistory()) {
            return back()->withErrors(['user' => __('messages.user_cannot_delete_has_history')]);
        }

        $user->delete();

        return redirect()->route('admin.users.index', $request->query());
    }
}
