<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\EditRoleRequest;
use App\Http\Requests\Store\Admin\FilterRolesRequest;
use App\Http\Requests\Store\Admin\StoreRoleRequest;
use App\Http\Requests\Store\Admin\UpdateRoleRequest;
use App\Http\Resources\Store\RoleResource;
use App\Models\Language;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleTranslation;
use App\Models\StoreUser;
use App\Support\StaffPrivileges;
use App\Support\StorePermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The store back office's staff roles (/temp/admin/roles): a spatie role on
 * the `store` guard, its permissions, and its name per language
 * (role_translations; the default language's name is the role's own).
 * Super Admin is neither listed nor editable: Gate::before() grants it
 * everything, so its permission list means nothing, and renaming or deleting
 * it would lock the store's owner out.
 */
class RoleController extends Controller implements HasMiddleware
{
    /**
     * Permission sections of the ActiveITzone addons this store no longer
     * carries (.ai/rules/legacy-albaraka-holland.md §6.4): no route checks
     * them, so the role form doesn't offer them.
     *
     * @var list<string>
     */
    private const array REMOVED_ADDON_SECTIONS = [
        'affiliate_system',
        'african_pg',
        'auction',
        'club_point',
        'delivery_boy',
        'offline_payment',
        'otp_system',
        'paytm',
        'pos_system',
        'refund_request',
        'seller_subscription',
        'wholesale',
    ];

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_staff_roles', only: ['index']),
            new Middleware('permission:add_staff_role', only: ['create', 'store']),
            new Middleware('permission:edit_staff_role', only: ['edit', 'update']),
            new Middleware('permission:delete_staff_role', only: ['destroy']),
        ];
    }

    public function index(FilterRolesRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);

        return Inertia::render('Store/Admin/Staff/Roles/Index', [
            'roles' => $this->paginated(RoleResource::collection(
                Role::query()
                    ->excludingSuperAdmin()
                    ->select(['id', 'name'])
                    ->orderBy('id')
                    ->paginate($perPage)
                    ->withQueryString()
            )),
            'filters' => ['per_page' => $perPage],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Store/Admin/Staff/Roles/Create', [
            'permissionGroups' => $this->permissionGroups($this->privileges($request)),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $permissionIds = $this->privileges($request)->permissionsForNewRole($this->checkedPermissionIds($request));

        DB::transaction(function () use ($request, $permissionIds) {
            $role = Role::create(['name' => $request->validated('name')]);
            $role->givePermissionTo($permissionIds);

            RoleTranslation::create([
                'role_id' => $role->id,
                'lang' => config('app.default_language'),
                'name' => $request->validated('name'),
            ]);
        });

        return redirect()->route('roles.index');
    }

    public function edit(EditRoleRequest $request, Role $role): Response
    {
        abort_if($role->name === Role::SUPER_ADMIN, 403);
        $lang = $request->validated('lang') ?? config('app.default_language');

        return Inertia::render('Store/Admin/Staff/Roles/Edit', [
            'role' => new RoleResource($role),
            'lang' => $lang,
            'name' => $role->getTranslation('name', $lang),
            'languages' => Language::query()->select(['code', 'name'])->orderBy('id')->get(),
            'permissionGroups' => $this->permissionGroups($this->privileges($request)),
            'permissionIds' => $role->permissions()->pluck($role->permissions()->getRelated()->qualifyColumn('id'))->all(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        abort_if($role->name === Role::SUPER_ADMIN, 403);
        $lang = $request->validated('lang');
        $current = $role->permissions()->pluck($role->permissions()->getRelated()->qualifyColumn('id'))->map(fn ($id) => (int) $id)->all();
        $permissionIds = $this->privileges($request)->permissionsAfterRoleEdit($this->checkedPermissionIds($request), $current);

        DB::transaction(function () use ($request, $role, $lang, $permissionIds) {
            if ($lang === config('app.default_language')) {
                $role->name = $request->validated('name');
                $role->save();
            }

            $role->syncPermissions($permissionIds);

            RoleTranslation::query()->updateOrCreate(
                ['role_id' => $role->id, 'lang' => $lang],
                ['name' => $request->validated('name')],
            );
        });

        return redirect()->route('roles.index');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->name === Role::SUPER_ADMIN, 403);

        DB::transaction(function () use ($role) {
            RoleTranslation::query()->where('role_id', $role->id)->delete();
            $role->delete();
        });

        return $this->redirectPreservingQuery('roles.index', $request);
    }

    /**
     * The role form's checkboxes: the store's permissions grouped by section,
     * using file labels for application identifiers and content translations
     * for custom permission records. Stored identifiers remain unchanged.
     *
     * Only the permissions the signed-in staff member holds are offered: no one
     * can grant what they lack (App\Support\StaffPrivileges).
     *
     * @return array<int, array<string, mixed>>
     */
    private function permissionGroups(StaffPrivileges $privileges): array
    {
        return Permission::query()
            ->select(['id', 'name', 'section'])
            ->when(! $privileges->isSuperAdmin(), fn ($query) => $query->whereIn('id', $privileges->heldPermissionIds()))
            ->where(fn ($query) => $query->whereNull('section')->orWhereNotIn('section', self::REMOVED_ADDON_SECTIONS))
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Permission $permission) => (string) $permission->section)
            ->map(fn ($permissions, string $section) => [
                'section' => $section,
                'label' => StorePermissions::displaySection($section),
                'permissions' => $permissions
                    ->map(fn (Permission $permission) => ['id' => $permission->id, 'label' => StorePermissions::displayName($permission->name)])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    private function privileges(Request $request): StaffPrivileges
    {
        /** @var StoreUser $actor */
        $actor = $request->user('store');

        return new StaffPrivileges($actor);
    }

    /**
     * The checked permission ids as integers: spatie/laravel-permission 6+
     * looks a numeric string up by permission name, not by id.
     *
     * @return array<int, int>
     */
    private function checkedPermissionIds(StoreRoleRequest|UpdateRoleRequest $request): array
    {
        return array_map('intval', $request->validated('permissions') ?? []);
    }
}
