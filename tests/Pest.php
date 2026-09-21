<?php

use App\Enums\CellLogAction;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
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
 *
 * Pass an existing $user to re-authenticate as them (e.g. to prove two known
 * users each get their own rate-limit budget) instead of creating a new one.
 */
function actingAsMobileUser(?User $user = null): User
{
    $user ??= User::factory()->mobileUser()->create();

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

/**
 * Authenticate an admin against the API's token guard, as the mobile app
 * does for actingAsMobileUser() — for the handful of API responses (e.g.
 * CellStatusLogResource's flagged/flags keys) that vary by whether the
 * authenticated caller is an admin. Returns the user for tests that assert
 * on who performed the action.
 */
function actingAsAdminMobileUser(): User
{
    $admin = User::factory()->create();

    Sanctum::actingAs($admin, ['*']);

    return $admin;
}

/**
 * Authenticate a mobile app user against the admin panel's session guard, to
 * assert that panel routes reject a non-admin caller. Returns the user for
 * tests that assert on who performed the action.
 */
function actingAsMobilePanelUser(): User
{
    $mobileUser = User::factory()->mobileUser()->create();

    test()->actingAs($mobileUser);

    return $mobileUser;
}

/**
 * Headers an Inertia client sends on every visit, including ordinary
 * client-side page navigations (not just partial reloads) — see
 * app/Http/Middleware/StoreInertiaPreviousUrl.php's docblock for why that
 * matters: it's what makes `$request->ajax()` true and breaks Laravel's
 * default `back()` redirect unless that middleware compensates. Tests that
 * reproduce the validation-redirect bug send these on both the form page's
 * GET and the failing submission, and never set a Referer (this app's
 * `no-referrer` policy means real browsers never send one either).
 *
 * @return array<string, string>
 */
function inertiaHeaders(): array
{
    return [
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia' => 'true',
        // Inertia's middleware answers a GET whose asset version doesn't match
        // with a 409 + X-Inertia-Location instead of the page, so a visit that
        // omits this header can never reach a 200 (CI builds public/build/
        // manifest.json, which is what the version hashes). Asking the app's own
        // middleware for the value keeps it in step with however it is computed,
        // and yields '' — matching Inertia's own default — when there is no
        // manifest, so this works with or without a build present.
        'X-Inertia-Version' => (string) (new HandleInertiaRequests)->version(request()),
    ];
}

/**
 * Set a model's `created_at` to a specific timestamp after creation — needed
 * because `created_at` is never mass-assignable, so factories can't set it
 * via `create()`.
 *
 * @template TModel of Model
 *
 * @param  TModel  $model
 * @return TModel
 */
function backdate(Model $model, string $timestamp): Model
{
    $model->forceFill(['created_at' => $timestamp])->save();

    return $model;
}

/**
 * Create the pair of `CellStatusLog` rows a transfer writes in the same
 * transaction — `transferred_out` on the source cell, `transferred_in` on
 * the destination cell — backdated to the given timestamps.
 *
 * @return array{0: CellStatusLog, 1: CellStatusLog}
 */
function createTransferPair(Pallet $pallet, Cell $source, Cell $destination, string $outAt, string $inAt): array
{
    $transferredOut = backdate(CellStatusLog::factory()->create([
        'pallet_id' => $pallet->id,
        'cell_id' => $source->id,
        'related_cell_id' => $destination->id,
        'action' => CellLogAction::TransferredOut,
    ]), $outAt);

    $transferredIn = backdate(CellStatusLog::factory()->create([
        'pallet_id' => $pallet->id,
        'cell_id' => $destination->id,
        'related_cell_id' => $source->id,
        'action' => CellLogAction::TransferredIn,
    ]), $inAt);

    return [$transferredOut, $transferredIn];
}

/**
 * Assert that a mobile API JSON listing response paginates instead of
 * returning every row at once — `data` has exactly `$perPage` entries and
 * `meta.total` matches the full row count.
 */
function assertJsonListingPaginates(TestResponse $response, int $total, int $perPage = 20): void
{
    $response->assertOk();
    expect($response->json('data'))->toHaveCount($perPage);
    expect($response->json('meta.total'))->toBe($total);
    expect($response->json('meta.current_page'))->toBe(1);
    expect($response->json('meta.per_page'))->toBe($perPage);
    expect($response->json('meta.last_page'))->toBe((int) ceil($total / $perPage));
    expect($response->json('meta.links'))->not->toBeEmpty();
}

/**
 * Assert that an admin Inertia index response paginates a prop instead of
 * returning every row at once — the `{$prop}.data` count matches the page
 * size and `{$prop}.meta.total` matches the full row count. Pass `$component`
 * to also assert which page rendered (skip it when an earlier assertion in
 * the same test already covers that).
 */
function assertInertiaPaginates(
    TestResponse $response,
    string $prop,
    int $dataCount,
    int $total,
    ?string $component = null,
): void {
    $response->assertOk()->assertInertia(
        fn (Assert $page) => ($component !== null ? $page->component($component) : $page)
            ->has("{$prop}.data", $dataCount)
            ->where("{$prop}.meta.total", $total)
            ->where("{$prop}.meta.current_page", 1)
            ->where("{$prop}.meta.per_page", $dataCount)
            ->where("{$prop}.meta.last_page", (int) ceil($total / $dataCount))
    );

    expect($response->inertiaProps("{$prop}.meta.links"))->not->toBeEmpty();
}

/**
 * Assert that a pallet action was rejected because its cell's row is under
 * an unfinished verification round (PalletActionService::lockCell()).
 */
function assertCellInActiveRoundRejection(TestResponse $response): void
{
    $response->assertStatus(409)->assertJsonPath('error_code', 'cell_in_active_round');
}
