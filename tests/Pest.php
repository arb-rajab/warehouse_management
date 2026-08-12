<?php

use App\Enums\CellLogAction;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Pallet;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
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
