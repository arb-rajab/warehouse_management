<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterUsersRequest;
use App\Http\Requests\Admin\ShowUserRequest;
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
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(FilterUsersRequest $request): Response
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

    public function show(ShowUserRequest $request, User $user): Response
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
        $user = new User($request->safe()->only(['name', 'email', 'password']));
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
        return DB::transaction(function () use ($request, $user) {
            $isAdmin = $request->boolean('is_admin');

            if (! $isAdmin && $this->isLastAdmin($user)) {
                return redirect()->route('admin.users.edit', $user)->withErrors(['is_admin' => __('messages.cannot_remove_last_admin')]);
            }

            $user->fill($request->safe()->only(['name', 'email']));

            if ($request->filled('password')) {
                $user->password = $request->validated('password');
                $user->must_change_password = true;
            }

            $user->save();

            $user->syncRoles($isAdmin ? ['admin'] : []);

            return redirect()->route('admin.users.index');
        });
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 403, __('messages.cannot_delete_own_account'));

        return DB::transaction(function () use ($request, $user) {
            if ($this->isLastAdmin($user)) {
                return $this->redirectPreservingQuery('admin.users.index', $request)->withErrors(['user' => __('messages.cannot_remove_last_admin')]);
            }

            if ($user->hasHistory()) {
                return $this->redirectPreservingQuery('admin.users.index', $request)->withErrors(['user' => __('messages.user_cannot_delete_has_history')]);
            }

            $user->delete();

            return $this->redirectPreservingQuery('admin.users.index', $request);
        });
    }

    /**
     * Whether demoting or deleting $user would leave the admin panel with no
     * admin at all. The self-demotion and self-deletion guards can't catch
     * this on their own: two admins revoking each other at the same moment
     * each pass the role middleware and each still count the other as the
     * admin who remains. So the admin role's row is locked first, as a mutex
     * every such request queues behind, and both $user's role and the admin
     * count are read fresh after it — call this inside the transaction that
     * then makes the change.
     */
    private function isLastAdmin(User $user): bool
    {
        $adminRole = Role::query()
            ->where('name', 'admin')
            ->where('guard_name', 'web')
            ->lockForUpdate()
            ->first();

        if ($adminRole === null || ! $user->roles()->whereKey($adminRole->id)->exists()) {
            return false;
        }

        return User::query()->role($adminRole)->count() <= 1;
    }
}
