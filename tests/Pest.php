<?php

use App\Enums\CellLogAction;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Cart;
use App\Models\Cell;
use App\Models\CellStatusLog;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Pallet;
use App\Models\Product;
use App\Models\StoreUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
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

// A test that freezes the clock must not leak it into the next test, including
// when one of its assertions fails before it gets to reset it itself.
afterEach(function () {
    Carbon::setTestNow();
});

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
 * Create a legacy store user (App\Models\StoreUser, the store's own `users`
 * table) with a verified email and a known password, for tests of the store
 * mounted under /temp. There is no factory for this model: the store's rows
 * are never generated anywhere else.
 *
 * Returns the row re-read from the database, every column loaded, as a real
 * request's authenticated user is: the store's views read columns this never
 * sets, which strict mode refuses to read off a model that doesn't have them.
 */
function createStoreUser(string $userType = 'customer', ?string $email = null, string $password = 'correct-password'): StoreUser
{
    $user = StoreUser::create([
        'name' => ucfirst($userType).' Tester',
        'email' => $email ?? $userType.'-'.Str::random(8).'@example.com',
        'phone' => (string) random_int(1000000000, 9999999999),
    ]);
    $user->password = $password;
    $user->forceFill(['user_type' => $userType, 'email_verified_at' => now()])->save();

    return $user->fresh();
}

/**
 * Authenticate a legacy store user on the store's own `store` guard.
 *
 * actingAs() also makes its guard the default for the rest of the test, which
 * a real request only reaches partway through its middleware, from
 * UseStoreAuthGuard. Switching the default back makes each request start on
 * this app's own guard as a real one does, so a middleware that runs before
 * UseStoreAuthGuard fails here the way it fails for real users.
 */
function actingAsStoreUser(StoreUser $user): StoreUser
{
    $defaultGuard = Auth::getDefaultDriver();

    test()->actingAs($user, 'store');

    Auth::shouldUse($defaultGuard);

    return $user;
}

/**
 * Give the legacy store the default currency every storefront price is
 * formatted through (get_system_default_currency()), which production has and
 * a fresh database doesn't.
 */
function seedStoreCurrency(): void
{
    $currencyId = DB::table('currencies')->insertGetId([
        'name' => 'Euro', 'symbol' => '€', 'exchange_rate' => 1, 'status' => 1, 'code' => 'EUR',
    ]);

    DB::table('business_settings')->insert(['type' => 'system_default_currency', 'value' => $currencyId]);
}

/**
 * A product the legacy store can show and sell: published, priced, and with
 * the stock row the cart takes its price and quantity from.
 *
 * @param  array<string, mixed>  $attributes
 */
function createStoreProduct(array $attributes = []): Product
{
    $product = Product::factory()->create([
        'slug' => 'product-'.Str::lower(Str::random(8)),
        'unit_price' => 2.5,
        'current_stock' => 50,
        'choice_options' => '[]',
        ...$attributes,
    ]);

    DB::table('product_stocks')->insert([
        'product_id' => $product->id,
        'variant' => '',
        'sku' => 'SKU-'.$product->id,
        'price' => $product->unit_price,
        'qty' => $product->current_stock,
    ]);

    return $product;
}

/**
 * A default delivery address in the Netherlands for store user `$userId`.
 */
function addStoreAddress(int $userId): int
{
    $countryId = DB::table('countries')->where('code', 'NL')->value('id')
        ?? DB::table('countries')->insertGetId(['code' => 'NL', 'name' => 'Netherlands', 'status' => 1]);

    return DB::table('addresses')->insertGetId([
        'user_id' => $userId, 'address' => 'Damrak 1', 'country_id' => $countryId, 'phone' => '612345678', 'set_default' => 1,
    ]);
}

/**
 * A token for the store's mobile-app API, as its login hands out.
 */
function storeApiToken(StoreUser $user): string
{
    return $user->createToken('API Token')->plainTextToken;
}

/**
 * A six-digit code that is not `$code`.
 */
function wrongCode(string $code): string
{
    return $code === '100000' ? '100001' : '100000';
}

/**
 * A store admin with the store's `Super Admin` role, which
 * AuthServiceProvider's Gate::before() grants every ability.
 */
function createStoreAdmin(bool $raizerAdmin = false): StoreUser
{
    Role::findOrCreate('Super Admin', 'store');

    $admin = createStoreUser('admin');
    $admin->forceFill(['raizer_admin' => $raizerAdmin ?: null])->save();
    $admin->assignRole('Super Admin');

    return $admin;
}

/**
 * A cash-on-delivery order `$customer` placed, carrying the shipping address
 * the checkout stores on it.
 *
 * @param  array<string, mixed>  $attributes
 */
function createStoreOrder(StoreUser $customer, array $attributes = []): Order
{
    $order = new Order;
    $order->forceFill([
        'user_id' => $customer->id,
        'shipping_type' => 'home_delivery',
        'shipping_address' => json_encode(['name' => $customer->name, 'email' => $customer->email, 'phone' => $customer->phone]),
        'payment_type' => 'cash_on_delivery',
        'grand_total' => 5,
        'code' => now()->format('Ymd-His').random_int(10, 99),
        'date' => now()->timestamp,
        ...$attributes,
    ])->save();

    return $order->fresh();
}

/**
 * An order `$customer` placed for `$product`, its order line in
 * `$paymentStatus` (the column the digital-download checks read).
 */
function orderStoreProduct(StoreUser $customer, Product $product, string $paymentStatus = 'paid'): OrderDetail
{
    $order = createStoreOrder($customer, ['payment_status' => $paymentStatus]);

    return OrderDetail::forceCreate([
        'order_id' => $order->id, 'seller_id' => $product->user_id, 'product_id' => $product->id,
        'price' => 5, 'tax' => 0, 'quantity' => 1, 'payment_status' => $paymentStatus,
    ]);
}

/**
 * A shop for seller `$seller`, owed `$balance` by the admin (`admin_to_pay`).
 */
function createShopFor(StoreUser $seller, float $balance): int
{
    return DB::table('shops')->insertGetId(['user_id' => $seller->id, 'name' => 'Shop of '.$seller->id, 'admin_to_pay' => $balance]);
}

/**
 * A withdraw request of `$amount` from `$seller` (status 0 pending, 1 paid).
 */
function createWithdrawRequestFor(StoreUser $seller, float $amount, ?int $status = 0): int
{
    return DB::table('seller_withdraw_requests')->insertGetId(['user_id' => $seller->id, 'amount' => $amount, 'status' => $status, 'viewed' => 0]);
}

/**
 * The `admin_to_pay` balance of shop `$shopId`, read straight from the table.
 */
function shopBalance(int $shopId): float
{
    return (float) DB::table('shops')->where('id', $shopId)->value('admin_to_pay');
}

/**
 * The store's own orders table has `manual_payment` and `manual_payment_data`;
 * this app's stand-in does not carry them yet, so tests of a manual payment
 * add them first.
 */
function addManualPaymentColumnsToOrders(): void
{
    foreach (['manual_payment' => fn (Blueprint $table) => $table->boolean('manual_payment')->default(false), 'manual_payment_data' => fn (Blueprint $table) => $table->text('manual_payment_data')->nullable()] as $column => $define) {
        if (! Schema::hasColumn('orders', $column)) {
            Schema::table('orders', $define);
        }
    }
}

/**
 * A cart-base coupon of `$owner`'s shop, valid today.
 *
 * @param  array<string, mixed>  $attributes
 */
function couponFor(StoreUser $owner, array $attributes = []): Coupon
{
    return Coupon::forceCreate([
        'user_id' => $owner->id, 'type' => 'cart_base', 'code' => 'SAVE'.random_int(1000, 9999),
        'details' => json_encode(['min_buy' => 1, 'max_discount' => 1000]), 'discount' => 50, 'discount_type' => 'amount',
        'start_date' => strtotime('-1 day'), 'end_date' => strtotime('+1 day'),
        ...$attributes,
    ]);
}

/**
 * A cart line of `$customer` for a new product of `$shopOwner`'s shop.
 *
 * @param  array<string, mixed>  $productAttributes
 */
function cartLine(StoreUser $customer, StoreUser $shopOwner, int $quantity, array $productAttributes = []): Cart
{
    $product = createStoreProduct(['user_id' => $shopOwner->id, ...$productAttributes]);

    return Cart::create(['user_id' => $customer->id, 'owner_id' => $shopOwner->id, 'product_id' => $product->id, 'quantity' => $quantity, 'variation' => '', 'price' => 2.5, 'tax' => 0, 'shipping_cost' => 0]);
}

/**
 * A cart line of `$customer` that checks out: a new product of `$shopOwner`'s
 * shop with `$stock` units, a home-delivery cart row with an address.
 *
 * @param  array<string, mixed>  $cartAttributes
 */
function orderableCartLine(StoreUser $customer, StoreUser $shopOwner, int $quantity, int $stock = 50, array $cartAttributes = []): Cart
{
    $product = createStoreProduct(['user_id' => $shopOwner->id, 'current_stock' => $stock]);
    $addressId = DB::table('addresses')->where('user_id', $customer->id)->value('id') ?? addStoreAddress($customer->id);

    return Cart::create([
        'user_id' => $customer->id, 'owner_id' => $shopOwner->id, 'product_id' => $product->id, 'quantity' => $quantity,
        'variation' => '', 'price' => 2.5, 'tax' => 0, 'shipping_cost' => 0, 'shipping_type' => 'home_delivery',
        'address_id' => $addressId, ...$cartAttributes,
    ]);
}

/**
 * The units of stock left for `$productId`, read straight from the table.
 */
function stockLeft(int $productId): int
{
    return (int) DB::table('product_stocks')->where('product_id', $productId)->value('qty');
}

/**
 * A store customer whose wallet holds `$balance`.
 */
function fundedCustomer(float $balance): StoreUser
{
    $customer = createStoreUser('customer');
    $customer->forceFill(['balance' => $balance])->save();

    return $customer;
}

/**
 * Make the test cache serialize, through the same serializable_classes
 * allow-list (config/cache.php) production's database store applies. The
 * array store tests use otherwise keeps values as they are, so a cached
 * model comes back intact here and as __PHP_Incomplete_Class in production.
 */
function useSerializingCache(): void
{
    config(['cache.stores.array.serialize' => true]);

    app('cache')->forgetDriver('array');
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
 * Set a model's `created_at` to a specific timestamp after creation.
 *
 * Note this is *not* needed to beat mass assignment: `Factory::makeInstance()`
 * builds the model inside `Model::unguarded()`, and `updateTimestamps()` leaves
 * an already-dirty `created_at` alone, so `create(['created_at' => ...])` does
 * set it. Use this helper for a model that already exists, and prefer passing
 * `created_at` to the factory when a `created`/`creating` model event must see
 * the real timestamp — `CellStatusLogObserver::created()` evaluates the
 * auto-flag thresholds against `$log->created_at`, which is still `now()` when
 * a later `backdate()` is what moves it.
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
 * Assert that a first page's `links.next` carries the request's query string —
 * a paginated listing must call `->withQueryString()`, or following the link
 * silently drops the caller's filters.
 */
function assertNextLinkKeepsQuery(TestResponse $response, string $query): void
{
    $response->assertOk();
    expect($response->json('links.next'))->toContain($query)->toContain('page=2');
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
 * Assert that a listing pages instead of returning every row: page 1 holds
 * `$perPage` of the `$total` rows, page 2 holds the rest (so `$total` is more
 * than one page and at most two) and repeats none of page 1, and none of
 * `$excluded` -- the keys of rows that belong to someone else -- shows on
 * either page.
 *
 * @param  Closure(int): TestResponse  $fetchPage  Requests the given page.
 * @param  Closure(TestResponse): list<int|string>  $keysOf  Identifies each row of a response.
 * @param  list<int|string>  $excluded
 */
function assertPagesWithoutOverlap(Closure $fetchPage, Closure $keysOf, int $total, int $perPage, array $excluded = []): void
{
    $first = $fetchPage(1);
    $second = $fetchPage(2);

    $first->assertOk();
    $second->assertOk();

    $firstKeys = $keysOf($first);
    $secondKeys = $keysOf($second);

    expect($firstKeys)->toHaveCount($perPage)
        ->and($secondKeys)->toHaveCount($total - $perPage)
        ->and(array_intersect($firstKeys, $secondKeys))->toBe([])
        ->and(array_intersect($excluded, [...$firstKeys, ...$secondKeys]))->toBe([]);
}

/**
 * Assert that a pallet action was rejected because its cell's row is under
 * an unfinished verification round (PalletActionService::lockCell()).
 */
function assertCellInActiveRoundRejection(TestResponse $response): void
{
    $response->assertConflict()->assertJsonPath('error_code', 'cell_in_active_round');
}

/**
 * Every PHP file under the given directories (relative to the project root),
 * as relative path => contents, for tests that scan the source for something
 * that must not be there.
 *
 * @param  list<string>  $directories
 * @return Generator<string, string>
 */
function sourceFilesUnder(array $directories): Generator
{
    foreach ($directories as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory), FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                yield str_replace(base_path().'/', '', $file->getPathname()) => file_get_contents($file->getPathname());
            }
        }
    }
}

/**
 * Run `$callback` with the public path pointed at a scratch directory that has
 * a sentinel `index.php` beside the upload directory, so an upload that
 * escapes its directory writes there and never into the real `public/`.
 * The callback gets the scratch public path.
 */
function withScratchPublicPath(Closure $callback): void
{
    $root = sys_get_temp_dir().'/image-upload-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists("$root/public/uploads/all");
    file_put_contents("$root/public/index.php", '<?php // sentinel');
    $originalPublicPath = app()->publicPath();
    app()->usePublicPath("$root/public");

    try {
        $callback("$root/public");
    } finally {
        app()->usePublicPath($originalPublicPath);
        File::deleteDirectory($root);
    }
}

function tinyPng(): string
{
    $image = imagecreatetruecolor(4, 4);
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

/**
 * @return list<string> The names of the files in the scratch upload directory.
 */
function storedUploadFiles(string $publicPath): array
{
    return array_map('basename', glob("$publicPath/uploads/all/*") ?: []);
}
