<?php

namespace App\Models;

use Closure;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $must_change_password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
#[Table('wms_users')]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The spatie guard this model's roles and permissions live under, pinned
     * so it isn't derived from config/auth.php's guard list (`web` and
     * `sanctum` both name this model's provider). StoreUser's is `store`;
     * see .ai/rules/legacy-albaraka-holland.md, "Auth guards".
     */
    protected string $guard_name = 'web';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * The id/name pairs for a "filter by who did this" dropdown, ordered by
     * name — the same shape `Row::filterOptions()`/`Product::filterOptions()`
     * return for their own filters. Removed users are included: their logs
     * and reports stay on record, so they must stay filterable.
     *
     * @return Collection<int, User>
     */
    public static function filterOptions(): Collection
    {
        return self::withTrashed()->select(['id', 'name'])->orderBy('name')->get();
    }

    /**
     * Shared `Gate::define()` callback for the admin-only internal tools
     * (Telescope, Pulse, Health) — avoids repeating the same closure in
     * every tool's service provider.
     */
    public static function isAdminGate(): Closure
    {
        return fn (User $user): bool => $user->isAdmin();
    }
}
