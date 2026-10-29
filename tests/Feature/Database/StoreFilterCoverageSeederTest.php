<?php

use App\Models\StoreUser;
use Database\Seeders\StoreAccountFilterSeeder;
use Database\Seeders\StoreFilterCoverageSeeder;
use Database\Seeders\Support\StoreFilterSeedData;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    Http::preventStrayRequests();
    Mail::fake();
    Notification::fake();
});

test('account fixtures populate each individual customer and representative filter with defaults applied', function () {
    (new StoreAccountFilterSeeder)->run();
    actingAsStoreUser(StoreUser::findOrFail(app(StoreFilterSeedData::class)->user('admin')));
    foreach (['customers.index', 'representatives.index'] as $route) {
        foreach (['important', 'verified', 'from_api', 'registration_completed', 'archived'] as $filter) {
            foreach ([0, 1] as $value) {
                $this->get(route($route, [$filter => $value]))->assertOk()->assertInertia(
                    fn (Assert $page) => $page->where('customers.meta.total', fn ($count) => $count > 0)
                );
            }
        }
        foreach (['new_user', 'has_been_contacted'] as $activity) {
            $this->get(route($route, ['activity_status' => $activity]))->assertOk()->assertInertia(
                fn (Assert $page) => $page->where('customers.meta.total', fn ($count) => $count > 0)
            );
        }
    }
});

test('full fixture reruns preserve original records and reuse fixture identities', function () {
    $user = createStoreUser('customer', 'original@example.invalid');
    $product = createStoreProduct(['name' => 'Original untouched product']);
    $userBefore = DB::table('users')->where('id', $user->id)->first();
    $productBefore = DB::table('products')->where('id', $product->id)->first();
    DB::table('business_settings')->insert(['type' => 'existing_filter_demo', 'value' => 'preserve']);
    $before = [];
    $tablesBefore = Schema::getTableListing(schemaQualified: false);
    foreach ($tablesBefore as $table) {
        if (Schema::hasColumn($table, 'id')) {
            $before[$table] = DB::table($table)->orderBy('id')->get();
        }
    }
    (new StoreFilterCoverageSeeder)->run();
    foreach ($before as $table => $records) {
        foreach ($records as $record) {
            expect(DB::table($table)->where('id', $record->id)->first(), $table)->toEqual($record);
        }
    }
    $registry = app(StoreFilterSeedData::class)->records();
    expect(Schema::getTableListing(schemaQualified: false))->toBe($tablesBefore);
    $counts = [];
    foreach (collect($registry)->pluck('table_name')->unique() as $table) {
        $counts[$table] = DB::table($table)->count();
    }
    (new StoreFilterCoverageSeeder)->run();
    expect(app(StoreFilterSeedData::class)->records())->toBe($registry);
    foreach ($counts as $table => $count) {
        expect(DB::table($table)->count(), $table)->toBe($count);
    }
    expect(DB::table('users')->where('id', $user->id)->first())->toEqual($userBefore)
        ->and(DB::table('products')->where('id', $product->id)->first())->toEqual($productBefore);
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
    Notification::assertNothingSent();
});

test('deleted fixture records are repaired without adopting an existing account', function () {
    $data = app(StoreFilterSeedData::class);
    $id = $data->user('repair-example');
    DB::table('users')->where('id', $id)->delete();
    $repaired = $data->user('repair-example');
    expect($repaired)->not->toBe($id)
        ->and(collect($data->records())->where('seed_key', 'repair-example')->count())->toBe(1);
});

test('fixture seeders refuse environments outside development staging and testing', function (string $environment) {
    app()->instance('env', $environment);
    try {
        expect(fn () => (new StoreFilterCoverageSeeder)->run())->toThrow(RuntimeException::class)
            ->and(count(app(StoreFilterSeedData::class)->records()))->toBe(0)
            ->and(DB::table('users')->count())->toBe(0);
    } finally {
        app()->instance('env', 'testing');
    }
})->with(['production', 'preview']);

test('reserved email collisions never overwrite existing users', function () {
    $user = createStoreUser('customer', 'store-filter-admin@example.invalid');
    $before = $user->getAttributes();
    expect(fn () => app(StoreFilterSeedData::class)->user('admin'))->toThrow(QueryException::class);
    expect($user->fresh()->getAttributes())->toBe($before);
});

test('relative date examples refresh only seed-owned records on later runs', function () {
    $this->travelTo(now()->startOfDay());
    (new StoreFilterCoverageSeeder)->run();
    $data = app(StoreFilterSeedData::class);
    $wallet = $data->find('wallets', 'engagement-wallet-'.$data->user('customer'));
    $this->travel(10)->days();
    (new StoreFilterCoverageSeeder)->run();
    expect(DB::table('wallets')->where('id', $wallet)->value('created_at'))->toBe(now()->toDateTimeString());
});

test('the complete seed transaction rolls back on a reserved identity collision', function () {
    $existing = createStoreUser('customer', 'store-filter-seller@example.invalid');
    $before = $existing->getAttributes();
    expect(fn () => (new StoreFilterCoverageSeeder)->run())->toThrow(QueryException::class);
    expect(DB::table('users')->count())->toBe(1)
        ->and(count(app(StoreFilterSeedData::class)->records()))->toBe(0)
        ->and($existing->fresh()->getAttributes())->toBe($before);
});

test('new fixture accounts use a hashed password literal and reruns preserve changed credentials', function () {
    $data = app(StoreFilterSeedData::class);
    $id = $data->user('password-example');
    $hash = DB::table('users')->where('id', $id)->value('password');
    expect($hash)->not->toBe('password')->and(Hash::check('password', $hash))->toBeTrue();
    $changed = Hash::make('changed-fixture-password');
    DB::table('users')->where('id', $id)->update(['password' => $changed]);
    expect($data->user('password-example'))->toBe($id)
        ->and(DB::table('users')->where('id', $id)->value('password'))->toBe($changed);
});
