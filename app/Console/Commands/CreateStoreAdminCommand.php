<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Role;
use App\Models\StoreUser;
use App\Support\StorePermissions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

#[Signature('store:create-admin {email : Email address of the store user to make Super Admin} {--name= : Display name for a new user (defaults to the part of the email before the @)} {--promote : Give Super Admin to the existing store user with this email instead of refusing}')]
#[Description('Create the first store Super Admin (role, user and assignment); asks for the password with a hidden prompt')]
class CreateStoreAdminCommand extends Command
{
    private const MINIMUM_PASSWORD_LENGTH = 12;

    /**
     * Seeders must not run in production and the Super Admin role exists only
     * after a seeder, so this is the supported way to get the first admin.
     * The password is only ever read from a hidden prompt (never an argument,
     * option or environment variable, so it reaches no shell history or
     * process list) and is never printed or logged. Everything is written in
     * one transaction; the only role grant it makes is Super Admin to this one
     * user (Gate::before() lets that role through every gate), and it grants
     * no permission to any role or user.
     */
    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email:rfc', 'max:255']])->fails()) {
            $this->components->error('"'.$email.'" is not a valid email address.');

            return self::FAILURE;
        }

        $existing = StoreUser::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($existing === null) {
            return $this->createAdmin($email);
        }

        if (! $this->option('promote')) {
            $this->components->error('A store user with that email already exists (id '.$existing->id.'). Nothing was changed.');
            $this->line('To give that user the '.Role::SUPER_ADMIN.' role, run the command again with --promote.');

            return self::FAILURE;
        }

        return $this->promote($existing);
    }

    private function createAdmin(string $email): int
    {
        $password = $this->askForPassword();

        if ($password === null) {
            return self::FAILURE;
        }

        $name = trim((string) $this->option('name')) ?: Str::before($email, '@');

        $lines = DB::transaction(function () use ($email, $name, $password): array {
            $lines = $this->prepareRoleAndPermissions();

            $user = new StoreUser;
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'user_type' => 'admin',
                'email_verified_at' => now(),
            ])->save();
            $user->assignRole($this->superAdminRole());

            $lines[] = 'Created store user '.$user->id.' with email '.$email.' (user_type admin, email verified).';
            $lines[] = 'Assigned the '.Role::SUPER_ADMIN.' role to it.';

            return $lines;
        });

        $this->report($lines);

        return self::SUCCESS;
    }

    private function promote(StoreUser $user): int
    {
        if ($user->banned || ! in_array($user->user_type, ['admin', 'staff', 'accountant'], true)) {
            $this->components->error('Store user '.$user->id.' cannot sign in to the back office (user_type '.$user->user_type.($user->banned ? ', banned' : '').'), so the Super Admin role would not help. Nothing was changed.');

            return self::FAILURE;
        }

        $lines = DB::transaction(function () use ($user): array {
            $lines = $this->prepareRoleAndPermissions();

            if ($user->hasRole(Role::SUPER_ADMIN, 'store')) {
                $lines[] = 'Store user '.$user->id.' ('.$user->email.') already has the '.Role::SUPER_ADMIN.' role; nothing assigned.';

                return $lines;
            }

            $user->assignRole($this->superAdminRole());
            $lines[] = 'Assigned the '.Role::SUPER_ADMIN.' role to existing store user '.$user->id.' ('.$user->email.'); its password and other fields were not touched.';

            return $lines;
        });

        $this->report($lines);

        return self::SUCCESS;
    }

    /**
     * Ask twice, hidden. Returns null (after printing why) for an empty or
     * short password or a mismatch, so nothing is written.
     */
    private function askForPassword(): ?string
    {
        $password = (string) $this->secret('Password (at least '.self::MINIMUM_PASSWORD_LENGTH.' characters)');

        if (mb_strlen($password) < self::MINIMUM_PASSWORD_LENGTH) {
            $this->components->error('The password must be at least '.self::MINIMUM_PASSWORD_LENGTH.' characters. Nothing was written.');

            return null;
        }

        if ($password !== (string) $this->secret('Confirm password')) {
            $this->components->error('The passwords do not match. Nothing was written.');

            return null;
        }

        return $password;
    }

    /**
     * Create the Super Admin role if it is missing, and the store permission
     * rows only when there are none at all (a database on which neither the
     * migration nor the sync ever ran). Otherwise say whether the sync has
     * rows left to create, without writing them.
     *
     * @return list<string>
     */
    private function prepareRoleAndPermissions(): array
    {
        $lines = [];

        $role = Role::query()->where('name', Role::SUPER_ADMIN)->first();
        $lines[] = $role === null
            ? 'Created the '.Role::SUPER_ADMIN.' role (guard store).'
            : 'The '.Role::SUPER_ADMIN.' role already existed (id '.$role->id.').';
        $this->superAdminRole();

        if (Permission::query()->doesntExist()) {
            $created = count(StorePermissions::sync()['created']);
            $lines[] = 'The store permissions table was empty: created '.$created.' store permissions (no role was given any of them).';
        } elseif (($missing = count(StorePermissions::sync(dryRun: true)['created'])) > 0) {
            $lines[] = $missing.' store permissions defined in code are missing from the database: run "php artisan store:sync-permissions" (add --dry-run to preview).';
        }

        return $lines;
    }

    private function superAdminRole(): Role
    {
        /** @var Role */
        return Role::findOrCreate(Role::SUPER_ADMIN, 'store');
    }

    /**
     * @param  list<string>  $lines
     */
    private function report(array $lines): void
    {
        foreach ($lines as $line) {
            $this->components->info($line);
        }
    }
}
