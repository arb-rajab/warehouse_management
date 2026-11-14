<?php

namespace App\Services\Store;

use App\Models\Role;
use App\Models\StoreUser;
use Illuminate\Support\Str;

/**
 * Who is told about a change to a sensitive setting (the SMTP settings, the
 * payment gateways): every active Super Admin, i.e. the role on the store
 * guard, back-office staff, not banned, with a valid email address, once per
 * address (.ai/rules/admin-env-overrides.md, "Security").
 */
final class SuperAdminRecipients
{
    /** @return list<array{email: string, user: StoreUser}> */
    public function all(): array
    {
        $recipients = [];
        $seen = [];

        $superAdmins = StoreUser::query()
            ->whereHas('roles', fn ($roles) => $roles->where($roles->qualifyColumn('name'), Role::SUPER_ADMIN))
            ->whereIn('user_type', StoreUser::ADMIN_USER_TYPES)
            ->where(fn ($query) => $query->whereNull('banned')->orWhere('banned', 0))
            ->whereNotNull('email')
            ->orderBy('id')
            ->get();

        foreach ($superAdmins as $admin) {
            $email = trim((string) $admin->email);
            if (! self::validEmail($email) || isset($seen[Str::lower($email)])) {
                continue;
            }

            $seen[Str::lower($email)] = true;
            $recipients[] = ['email' => $email, 'user' => $admin];
        }

        return $recipients;
    }

    public static function validEmail(string $email): bool
    {
        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false && ! preg_match('/[\r\n]/', $email);
    }
}
