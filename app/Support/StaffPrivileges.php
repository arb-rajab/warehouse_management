<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\StoreUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * What a back-office staff member may hand to, or change in, other staff.
 *
 * `edit_staff_role` and `edit_staff` would otherwise be equivalent to every
 * permission but Super Admin: one can tick any permission onto a role, the
 * other can give any role (the editor's own included) to any account and set
 * its password. Rule: no one grants a permission they do not hold themselves,
 * and no one manages an account that holds more than they do. The Super Admin
 * holds everything, so it is never limited. The permissions a staff member
 * holds are the direct ones and those of their roles.
 */
final class StaffPrivileges
{
    /** @var array<int, int>|null */
    private ?array $held = null;

    public function __construct(private readonly StoreUser $actor) {}

    public function isSuperAdmin(): bool
    {
        return $this->actor->hasRole(Role::SUPER_ADMIN, 'store');
    }

    /**
     * @return array<int, int>
     */
    public function heldPermissionIds(): array
    {
        return $this->held ??= $this->isSuperAdmin()
            ? Permission::query()->pluck('id')->map(fn ($id) => (int) $id)->all()
            : $this->actor->getAllPermissions()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  array<int, int>  $permissionIds
     */
    public function holdsAll(array $permissionIds): bool
    {
        return array_diff($permissionIds, $this->heldPermissionIds()) === [];
    }

    /**
     * Whether `$role` could be given to someone: it grants nothing beyond what
     * the actor holds.
     */
    public function canAssign(Role $role): bool
    {
        return $this->isSuperAdmin() || $this->holdsAll($this->roleIds($role));
    }

    /**
     * Whether the actor may edit or delete this staff account: it holds nothing
     * beyond what the actor holds. A Super Admin is handled by the controller.
     */
    public function canManage(Staff $staff): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $theirs = $staff->user?->getAllPermissions()->pluck('id')->map(fn ($id) => (int) $id)->all() ?? [];

        return $staff->user_id === $this->actor->id || $this->holdsAll($theirs);
    }

    /**
     * The store roles the actor may give, Super Admin excluded.
     *
     * @return Collection<int, Role>
     */
    public function assignableRoles(): Collection
    {
        return Role::query()->excludingSuperAdmin()->select(['id', 'name'])->orderByDesc('id')->get()
            ->filter(fn (Role $role): bool => $this->canAssign($role))->values();
    }

    /**
     * The permissions a new role gets: those checked, all of which the actor
     * must hold.
     *
     * @param  array<int, int>  $submitted
     * @return array<int, int>
     */
    public function permissionsForNewRole(array $submitted): array
    {
        if (! $this->isSuperAdmin() && ! $this->holdsAll($submitted)) {
            throw ValidationException::withMessages(['permissions' => __('messages.role_permission_not_held')]);
        }

        return $submitted;
    }

    /**
     * The permissions an edited role ends with. The actor can add or remove
     * only permissions they hold: a permission the role has and they lack stays
     * (the form never offers it), and ticking one the role lacks is refused.
     *
     * @param  array<int, int>  $submitted
     * @param  array<int, int>  $current  the role's permissions before the edit
     * @return array<int, int>
     */
    public function permissionsAfterRoleEdit(array $submitted, array $current): array
    {
        if ($this->isSuperAdmin()) {
            return $submitted;
        }

        $held = $this->heldPermissionIds();
        if (array_diff($submitted, $held, $current) !== []) {
            throw ValidationException::withMessages(['permissions' => __('messages.role_permission_not_held')]);
        }

        return array_values(array_unique([...array_intersect($submitted, $held), ...array_diff($current, $held)]));
    }

    /**
     * @return array<int, int>
     */
    private function roleIds(Role $role): array
    {
        return $role->permissions()->pluck($role->permissions()->getRelated()->qualifyColumn('id'))->map(fn ($id) => (int) $id)->all();
    }
}
