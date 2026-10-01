<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleTranslation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class RoleController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * See Auth\LoginController::middleware()'s docblock for why this
     * replaces the constructor-based $this->middleware() call.
     *
     * @return array<int, \Illuminate\Routing\Controllers\Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_staff_roles', only: ['index', 'show']),
            new Middleware('permission:add_staff_role', only: ['create', 'store']),
            new Middleware('permission:edit_staff_role', only: ['edit', 'update', 'add_permission', 'create_admin_permissions']),
            new Middleware('permission:delete_staff_role', only: ['destroy']),
        ];
    }
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $roles = Role::excludingSuperAdmin()->paginate(10);
        return view('shop.backend.staff.staff_roles.index', compact('roles'));

        // $roles = Role::paginate(10);
        // return view('shop.backend.staff.staff_roles.index', compact('roles'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('shop.backend.staff.staff_roles.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        // dd($request->permissions);
        $role = Role::create(['name' => $request->name]);
        $role->givePermissionTo($this->permissionIds($request));

        $role_translation = RoleTranslation::firstOrNew(['lang' => config('app.default_language'), 'role_id' => $role->id]);
        $role_translation->name = $request->name;
        $role_translation->save();

        flash(translate('New Role has been added successfully'))->success();
        return redirect()->route('roles.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $lang = $request->lang;
        $role = Role::findOrFail($id);
        return view('shop.backend.staff.staff_roles.edit', compact('role','lang'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);
        if($request->lang == config("app.default_language")){
            $role->name = $request->name;
        }
        $role->syncPermissions($this->permissionIds($request));
        $role->save();

        // Role Translation
        $role_translation = RoleTranslation::firstOrNew(['lang' => $request->lang, 'role_id' => $role->id]);
        $role_translation->name = $request->name;
        $role_translation->save();

        flash(translate('Role has been updated successfully'))->success();
        return back();
        // return redirect()->route('roles.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        RoleTranslation::where('role_id', $role->id)->delete();
        $role->delete();
        flash(translate('Role has been deleted successfully'))->success();
        return redirect()->route('roles.index');
    }

    public function add_permission(Request $request)
    {
        $permission = Permission::create(['name' => $request->name, 'section'=> $request->parent]);
        return redirect()->route('roles.index');
    }

    public function create_admin_permissions(){

    }

    /**
     * The checked permission ids as integers: spatie/laravel-permission 6+
     * looks up numeric strings by permission name, not by id.
     *
     * @return array<int, int>
     */
    private function permissionIds(Request $request): array
    {
        return collect($request->permissions)->map(fn ($id) => (int) $id)->all();
    }
}
