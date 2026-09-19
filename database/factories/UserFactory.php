<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'must_change_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Assign the admin role, since most factory-created users stand in for admin panel users.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole(Role::findOrCreate('admin', 'web'));
        });
    }

    /**
     * Indicate that the user is a mobile app user, without admin panel access.
     */
    public function mobileUser(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->syncRoles([]);
        });
    }
}
