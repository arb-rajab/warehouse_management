<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\CellStatusLogResource;
use App\Http\Resources\UserResource;
use App\Models\CellStatusLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Users/Index', [
            'users' => $this->paginated(UserResource::collection(
                User::query()
                    ->select(['id', 'name', 'email'])
                    ->with('roles:id,name')
                    ->orderBy('name')
                    ->paginate(20)
            )),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create');
    }

    public function show(Request $request, User $user): Response
    {
        $logs = CellStatusLog::query()
            ->forListing()
            ->where('user_id', $user->id)
            ->sorted($request)
            ->paginate(25)
            ->withQueryString();

        CellStatusLog::attachNextLogs($logs->getCollection());

        return Inertia::render('Admin/Users/Show', [
            'user' => new UserResource($user->loadMissing('roles:id,name')),
            'logs' => $this->paginated(CellStatusLogResource::collection($logs)),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'password']));

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
        }

        $user->save();

        $user->syncRoles($request->boolean('is_admin') ? ['admin'] : []);

        return redirect()->route('admin.users.index');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 403, __('messages.cannot_delete_own_account'));

        $user->delete();

        return redirect()->route('admin.users.index', $request->query());
    }
}
