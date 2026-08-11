<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Authenticate a warehouse worker against the API's token guard, as the mobile app
 * does. Returns the user for tests that assert on who performed the action.
 */
function actingAsMobileUser(): User
{
    $user = User::factory()->mobileUser()->create();

    Sanctum::actingAs($user, ['*']);

    return $user;
}

/**
 * Authenticate an admin against the admin panel's session guard (the factory's
 * default role). Returns the user for tests that assert on who performed the action.
 */
function actingAsAdmin(): User
{
    $admin = User::factory()->create();

    test()->actingAs($admin);

    return $admin;
}
