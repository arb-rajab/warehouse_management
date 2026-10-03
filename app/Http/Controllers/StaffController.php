<?php

namespace App\Http\Controllers;

use App\Http\Requests\StaffRequest;
use App\Models\Role;
use App\Models\Staff;
use App\Models\StoreUser;
use Auth;
use DB;
use Hash;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class StaffController extends Controller implements HasMiddleware
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
            new Middleware('permission:view_all_staffs', only: ['index', 'show']),
            new Middleware('permission:add_staff', only: ['create', 'store']),
            new Middleware('permission:edit_staff', only: ['edit', 'update']),
            new Middleware('permission:delete_staff', only: ['destroy']),
        ];
    }
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $staffs = Staff::paginate(10);
        return view('shop.backend.staff.staffs.index', compact('staffs'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $roles = Role::excludingSuperAdmin()->orderBy('id', 'desc')->get();
        return view('shop.backend.staff.staffs.create', compact('roles'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  StaffRequest  $request
     * @return Response
     */
    public function store(StaffRequest $request)
    {
        $role = Role::excludingSuperAdmin()->findOrFail($request->role_id);

        DB::transaction(function () use ($request, $role) {
            $user = new StoreUser;
            $user->name = $request->name;
            $user->email = $request->email;
            $user->phone = $request->mobile;
            $user->user_type = "staff";
            $user->password = Hash::make($request->password);
            $user->save();

            $staff = new Staff;
            $staff->user_id = $user->id;
            $staff->role_id = $role->id;
            $staff->save();

            $user->assignRole($role->name);
        });

        flash(translate('Staff has been inserted successfully'))->success();
        return redirect()->route('staffs.index');
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
    public function edit($id)
    {
        $staff = Staff::with('user')->findOrFail(decrypt($id));
        abort_if($this->isSuperAdmin($staff), 403);
        $roles = Role::excludingSuperAdmin()->orderBy('id', 'desc')->get();
        return view('shop.backend.staff.staffs.edit', compact('staff', 'roles'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  StaffRequest  $request
     * @param  int  $id
     * @return Response
     */
    public function update(StaffRequest $request, $id)
    {
        $role = Role::excludingSuperAdmin()->findOrFail($request->role_id);
        $staff = Staff::with('user')->findOrFail($id);
        abort_if($this->isSuperAdmin($staff), 403);

        DB::transaction(function () use ($request, $role, $staff) {
            $user = $staff->user;
            $user->name = $request->name;
            $user->email = $request->email;
            $user->phone = $request->mobile;
            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }
            $user->save();

            $staff->role_id = $role->id;
            $staff->save();

            $user->syncRoles($role->name);
        });

        flash(translate('Staff has been updated successfully'))->success();
        return redirect()->route('staffs.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        $staff = Staff::with('user')->findOrFail($id);
        abort_if((int) $staff->user_id === (int) Auth::id() || $this->isSuperAdmin($staff), 403);

        DB::transaction(function () use ($staff) {
            StoreUser::destroy($staff->user_id);
            $staff->delete();
        });

        flash(translate('Staff has been deleted successfully'))->success();
        return redirect()->route('staffs.index');
    }

    private function isSuperAdmin(Staff $staff): bool
    {
        return $staff->user->hasRole(Role::SUPER_ADMIN, 'store');
    }
}
