<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterStaffRequest;
use App\Http\Requests\Store\Admin\StoreStaffRequest;
use App\Http\Requests\Store\Admin\UpdateStaffRequest;
use App\Http\Resources\Store\RoleResource;
use App\Http\Resources\Store\StaffResource;
use App\Models\Role;
use App\Models\Staff;
use App\Models\StoreUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The store back office's staff accounts (/temp/admin/staffs). A staff member
 * is a StoreUser of `user_type` staff plus a `staff` row naming their store
 * role. Super Admin accounts are listed but never edited or deleted here, and
 * no one deletes their own account.
 */
class StaffController extends Controller implements HasMiddleware
{
    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_all_staffs', only: ['index']),
            new Middleware('permission:add_staff', only: ['create', 'store']),
            new Middleware('permission:edit_staff', only: ['edit', 'update']),
            new Middleware('permission:delete_staff', only: ['destroy']),
        ];
    }

    public function index(FilterStaffRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);

        return Inertia::render('Store/Admin/Staff/Staffs/Index', [
            'staff' => $this->paginated(StaffResource::collection(
                Staff::query()
                    ->select(['id', 'user_id', 'role_id'])
                    ->has('user')
                    ->with($this->staffRelations())
                    ->orderBy('id')
                    ->paginate($perPage)
                    ->withQueryString()
            )),
            'filters' => ['per_page' => $perPage],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Store/Admin/Staff/Staffs/Create', [
            'roles' => RoleResource::collection($this->assignableRoles()),
        ]);
    }

    public function store(StoreStaffRequest $request): RedirectResponse
    {
        $role = Role::query()->findOrFail($request->integer('role_id'));

        DB::transaction(function () use ($request, $role) {
            $user = new StoreUser;
            $user->name = $request->validated('name');
            $user->email = $request->validated('email');
            $user->phone = $request->validated('mobile');
            $user->password = $request->validated('password');
            $user->user_type = 'staff';
            $user->save();

            $staff = new Staff;
            $staff->user_id = $user->id;
            $staff->role_id = $request->integer('role_id');
            $staff->save();

            $user->assignRole($role);
        });

        return redirect()->route('staffs.index');
    }

    public function edit(Staff $staff): Response
    {
        $staff->load($this->staffRelations());
        abort_if($staff->isSuperAdmin(), 403);

        return Inertia::render('Store/Admin/Staff/Staffs/Edit', [
            'staffMember' => new StaffResource($staff),
            'roles' => RoleResource::collection($this->assignableRoles()),
        ]);
    }

    public function update(UpdateStaffRequest $request, Staff $staff): RedirectResponse
    {
        abort_if($staff->isSuperAdmin(), 403);
        $role = Role::query()->findOrFail($request->integer('role_id'));

        DB::transaction(function () use ($request, $role, $staff) {
            $user = $staff->user;
            abort_if($user === null, 404);

            $user->name = $request->validated('name');
            $user->email = $request->validated('email');
            $user->phone = $request->validated('mobile');

            if ($request->filled('password')) {
                $user->password = $request->validated('password');
            }

            $user->save();

            $staff->role_id = $request->integer('role_id');
            $staff->save();

            $user->syncRoles([$role]);
        });

        return redirect()->route('staffs.index');
    }

    public function destroy(Request $request, Staff $staff): RedirectResponse
    {
        abort_if($staff->user_id === $request->user('store')?->id || $staff->isSuperAdmin(), 403);

        DB::transaction(function () use ($staff) {
            StoreUser::destroy($staff->user_id);
            $staff->delete();
        });

        return $this->redirectPreservingQuery('staffs.index', $request);
    }

    /**
     * What StaffResource reads: the account's contact details and roles (for
     * the Super Admin check), and the staff role's translated name.
     *
     * @return array<int|string, mixed>
     */
    private function staffRelations(): array
    {
        return [
            'user:id,name,email,phone',
            'user.roles' => fn (Relation $query) => $query->without('role_translations')->select(['id', 'name', 'guard_name']),
            'role:id,name',
        ];
    }

    /**
     * The roles a staff member can be given: every store role but Super Admin.
     *
     * @return Collection<int, Role>
     */
    private function assignableRoles(): Collection
    {
        return Role::query()->excludingSuperAdmin()->select(['id', 'name'])->orderByDesc('id')->get();
    }
}
