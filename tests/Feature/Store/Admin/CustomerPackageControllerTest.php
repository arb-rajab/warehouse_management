<?php

use App\Models\CustomerPackage;
use App\Models\CustomerPackageTranslation;
use App\Models\Language;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    seedStoreCurrency();
    config(['app.default_language' => 'en']);
    Language::forceCreate(['code' => 'en', 'name' => 'English', 'rtl' => 0]);
    Language::forceCreate(['code' => 'ar', 'name' => 'Arabic', 'rtl' => 1]);
    $this->package = CustomerPackage::forceCreate(['name' => 'Gold', 'amount' => 12.5, 'product_upload' => 10, 'logo' => null]);
    CustomerPackageTranslation::create(['customer_package_id' => $this->package->id, 'lang' => 'en', 'name' => 'Gold']);
    $this->payload = ['name' => 'Updated', 'amount' => '25.75', 'product_upload' => 20, 'logo' => null, 'lang' => 'en'];
});

foreach (['index', 'create', 'edit'] as $action) {
    test("customer package $action screen renders", function () use ($action) {
        actingAsStoreUser(createStoreStaff('view_classified_packages', 'edit_classified_package'));
        $uri = match ($action) {
            'index' => '/temp/admin/customer_packages',
            'create' => '/temp/admin/customer_packages/create',
            'edit' => '/temp/admin/customer_packages/edit/'.$this->package->id,
        };
        $this->get($uri)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Customers/Packages/'.ucfirst($action)));
    });
    test("customer package $action screen refuses unauthorized callers", function (string $caller) use ($action) {
        actingAsRefusedStoreCaller($caller);
        $uri = match ($action) {
            'index' => '/temp/admin/customer_packages',
            'create' => '/temp/admin/customer_packages/create',
            'edit' => '/temp/admin/customer_packages/edit/'.$this->package->id,
        };
        assertStoreCallerRefused($this->get($uri), $caller);
    })->with('refused store admin callers');
}

test('customer packages expose the complete narrow record and paginate without overlap', function () {
    actingAsStoreUser(createStoreStaff('view_classified_packages'));
    $this->get('/temp/admin/customer_packages')->assertInertia(fn (Assert $page) => $page
        ->has('packages.data.0', fn (Assert $item) => $item->where('id', $this->package->id)
            ->where('name', 'Gold')->where('localized_name', 'Gold')->where('amount', '12.5')
            ->where('formatted_amount', single_price(12.5))->where('product_upload', 10)->where('logo', null)->where('logo_url', uploaded_asset(null)))
        ->where('filters', ['per_page' => 20])->where('defaultLanguage', 'en'));
    foreach (range(1, 22) as $i) {
        CustomerPackage::forceCreate(['name' => "Package $i", 'amount' => 0, 'product_upload' => 0]);
    }
    assertPagesWithoutOverlap(fn (int $page) => $this->get("/temp/admin/customer_packages?per_page=20&page=$page"), fn ($response) => array_column($response->inertiaProps('packages.data'), 'id'), 23, 20);
    $this->get('/temp/admin/customer_packages?per_page=10')->assertInertia(fn (Assert $page) => $page->has('packages.data', 10)->where('filters.per_page', 10));
    $this->getJson('/temp/admin/customer_packages?per_page=11')->assertUnprocessable()->assertJsonValidationErrors('per_page');
});

test('customer package creation preserves free and paid pricing and upload boundaries', function (string $amount, int $uploads) {
    actingAsStoreUser(createStoreStaff('edit_classified_package'));
    $this->post('/temp/admin/customer_packages', [...$this->payload, 'amount' => $amount, 'product_upload' => $uploads, 'lang' => 'ar'])->assertRedirect(route('customer_packages.index'));
    $package = CustomerPackage::query()->orderByDesc('id')->firstOrFail();
    expect((float) $package->amount)->toBe((float) $amount)->and($package->product_upload)->toBe($uploads);
    $this->assertDatabaseHas('customer_package_translations', ['customer_package_id' => $package->id, 'lang' => 'en', 'name' => 'Updated']);
    $this->assertDatabaseMissing('customer_package_translations', ['customer_package_id' => $package->id, 'lang' => 'ar']);
})->with([['0', 0], ['12.50', 10], ['999999.99', 2147483647]]);

test('customer package translation edit and update preserve the base name and shared pricing benefits', function () {
    actingAsStoreUser(createStoreStaff('edit_classified_package', 'view_classified_packages'));
    $this->get('/temp/admin/customer_packages/edit/'.$this->package->id.'?lang=ar')->assertInertia(fn (Assert $page) => $page->where('lang', 'ar')->where('name', 'Gold')->has('languages', 2)->where('initialFiles', []));
    $this->patch('/temp/admin/customer_packages/'.$this->package->id, [...$this->payload, 'name' => 'ذهبية', 'lang' => 'ar'])->assertRedirect(route('customer_packages.edit', ['customerPackage' => $this->package->id, 'lang' => 'ar']));
    $this->assertDatabaseHas('customer_packages', ['id' => $this->package->id, 'name' => 'Gold', 'amount' => 25.75, 'product_upload' => 20]);
    $this->assertDatabaseHas('customer_package_translations', ['customer_package_id' => $this->package->id, 'lang' => 'ar', 'name' => 'ذهبية']);
    $this->get('/temp/admin/customer_packages/edit/'.$this->package->id.'?lang=ar')->assertInertia(fn (Assert $page) => $page->where('name', 'ذهبية'));
    session(['locale' => 'ar']);
    $this->get('/temp/admin/customer_packages')->assertInertia(fn (Assert $page) => $page->where('packages.data.0.localized_name', 'ذهبية'));
    $this->put('/temp/admin/customer_packages/'.$this->package->id, $this->payload)->assertRedirect();
    expect($this->package->fresh()->name)->toBe('Updated');
    $this->assertDatabaseHas('customer_package_translations', ['customer_package_id' => $this->package->id, 'lang' => 'en', 'name' => 'Updated']);
});

foreach (['store', 'update'] as $action) {
    test("customer package $action validates before any write", function (string $field, mixed $value) use ($action) {
        actingAsStoreUser(createStoreStaff('edit_classified_package'));
        $before = DB::table('customer_packages')->get()->toArray();
        $translations = DB::table('customer_package_translations')->get()->toArray();
        $payload = [...$this->payload, $field => $value];
        $response = $action === 'store' ? $this->postJson('/temp/admin/customer_packages', $payload) : $this->putJson('/temp/admin/customer_packages/'.$this->package->id, $payload);
        $response->assertUnprocessable()->assertJsonValidationErrors($field);
        expect(DB::table('customer_packages')->get()->toArray())->toEqual($before)->and(DB::table('customer_package_translations')->get()->toArray())->toEqual($translations);
    })->with([
        ['name', ''], ['name', str_repeat('n', 51)], ['amount', -1], ['amount', '0.001'], ['amount', '1e3'],
        ['amount', '1000000000000000000.00'], ['amount', ['bad']], ['product_upload', -1], ['product_upload', 1.5],
        ['product_upload', 2147483648], ['logo', 999999], ['logo', ['bad']], ['logo', '1,2'],
    ]);
}

test('customer package selectors reject unknown or malformed languages without writes', function () {
    actingAsStoreUser(createStoreStaff('edit_classified_package'));
    foreach (['xx', ['en']] as $lang) {
        $this->getJson(route('customer_packages.edit', ['customerPackage' => $this->package->id, 'lang' => $lang]))->assertUnprocessable()->assertJsonValidationErrors('lang');
        $this->putJson('/temp/admin/customer_packages/'.$this->package->id, [...$this->payload, 'lang' => $lang])->assertUnprocessable()->assertJsonValidationErrors('lang');
    }
    $this->assertDatabaseHas('customer_packages', ['id' => $this->package->id, 'name' => 'Gold', 'amount' => 12.5, 'product_upload' => 10]);
    $this->assertDatabaseCount('customer_package_translations', 1);
});

foreach (['store', 'update', 'destroy'] as $action) {
    test("customer package $action refuses unauthorized writes without changes", function (string $caller) use ($action) {
        $before = DB::table('customer_packages')->get()->toArray();
        $translations = DB::table('customer_package_translations')->get()->toArray();
        actingAsRefusedStoreCaller($caller);
        $response = match ($action) {
            'store' => $this->post('/temp/admin/customer_packages', $this->payload),
            'update' => $this->put('/temp/admin/customer_packages/'.$this->package->id, $this->payload),
            'destroy' => $this->delete('/temp/admin/customer_packages/destroy/'.$this->package->id),
        };
        assertStoreCallerRefused($response, $caller);
        expect(DB::table('customer_packages')->get()->toArray())->toEqual($before)->and(DB::table('customer_package_translations')->get()->toArray())->toEqual($translations);
    })->with('refused store admin callers');
    test("customer package $action is atomic on persistence failure", function () use ($action) {
        actingAsStoreUser(createStoreStaff('edit_classified_package', 'delete_classified_package'));
        $before = DB::table('customer_packages')->get()->toArray();
        $translations = DB::table('customer_package_translations')->get()->toArray();
        if ($action === 'destroy') {
            CustomerPackage::deleting(fn () => throw new RuntimeException('Package delete failure'));
        } else {
            CustomerPackageTranslation::saving(fn () => throw new RuntimeException('Translation write failure'));
        }
        $this->withoutExceptionHandling();
        try {
            expect(fn () => match ($action) {
                'store' => $this->post('/temp/admin/customer_packages', $this->payload),
                'update' => $this->put('/temp/admin/customer_packages/'.$this->package->id, $this->payload),
                'destroy' => $this->delete('/temp/admin/customer_packages/destroy/'.$this->package->id),
            })->toThrow(RuntimeException::class);
            expect(DB::table('customer_packages')->get()->toArray())->toEqual($before)->and(DB::table('customer_package_translations')->get()->toArray())->toEqual($translations);
        } finally {
            CustomerPackage::flushEventListeners();
            CustomerPackageTranslation::flushEventListeners();
        }
    });
}

test('customer package create remains edit-gated and read permission allows no write', function () {
    foreach (['add_classified_package', 'view_classified_packages'] as $permission) {
        actingAsStoreUser(createStoreStaff($permission));
        $this->get('/temp/admin/customer_packages/create')->assertForbidden();
        $this->post('/temp/admin/customer_packages', $this->payload)->assertForbidden();
        $this->put('/temp/admin/customer_packages/'.$this->package->id, $this->payload)->assertForbidden();
        $this->delete('/temp/admin/customer_packages/destroy/'.$this->package->id)->assertForbidden();
    }
    expect($this->package->fresh()->name)->toBe('Gold');
    $this->assertDatabaseCount('customer_packages', 1);
    $this->assertDatabaseCount('customer_package_translations', 1);
});

test('customer package delete removes only its package and translations and preserves assignments payments and query', function () {
    actingAsStoreUser(createStoreStaff('delete_classified_package'));
    $other = CustomerPackage::forceCreate(['name' => 'Other', 'amount' => 0, 'product_upload' => 1]);
    CustomerPackageTranslation::create(['customer_package_id' => $other->id, 'lang' => 'en', 'name' => 'Other']);
    $customer = createStoreUser('customer');
    $customer->forceFill(['customer_package_id' => $this->package->id, 'remaining_uploads' => 3])->save();
    DB::table('customer_package_payments')->insert(['user_id' => $customer->id, 'customer_package_id' => $this->package->id, 'payment_method' => 'bank', 'payment_details' => 'ref', 'approval' => 1, 'offline_payment' => 1, 'reciept' => 'receipts/test.png']);
    $this->delete('/temp/admin/customer_packages/destroy/'.$this->package->id.'?page=2&per_page=10')->assertRedirect(route('customer_packages.index', ['page' => 2, 'per_page' => 10]));
    $this->assertDatabaseMissing('customer_packages', ['id' => $this->package->id]);
    $this->assertDatabaseMissing('customer_package_translations', ['customer_package_id' => $this->package->id]);
    $this->assertDatabaseHas('customer_package_translations', ['customer_package_id' => $other->id]);
    $this->assertDatabaseHas('customer_package_payments', ['customer_package_id' => $this->package->id]);
    expect($customer->fresh()->customer_package_id)->toBe($this->package->id)->and($customer->fresh()->remaining_uploads)->toBe(3);
});

test('customer package actions return 404 for missing records', function () {
    actingAsStoreUser(createStoreStaff('edit_classified_package', 'delete_classified_package'));
    $this->get('/temp/admin/customer_packages/edit/999999')->assertNotFound();
    $this->put('/temp/admin/customer_packages/999999', $this->payload)->assertNotFound();
    $this->delete('/temp/admin/customer_packages/destroy/999999')->assertNotFound();
});

test('customer package logo references enforce ownership library permission image type and legacy round trips', function () {
    $staff = createStoreStaff('edit_classified_package');
    actingAsStoreUser($staff);
    $own = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'image']);
    $foreign = Upload::factory()->create(['user_id' => createStoreUser('customer')->id, 'type' => 'image']);
    $file = Upload::factory()->create(['user_id' => $staff->id, 'type' => 'document']);
    foreach ([$foreign->id, $file->id] as $id) {
        $this->putJson('/temp/admin/customer_packages/'.$this->package->id, [...$this->payload, 'logo' => $id])->assertUnprocessable()->assertJsonValidationErrors('logo');
        $this->postJson('/temp/admin/customer_packages', [...$this->payload, 'logo' => $id])->assertUnprocessable()->assertJsonValidationErrors('logo');
    }
    $this->assertDatabaseCount('customer_packages', 1);
    expect($this->package->fresh()->name)->toBe('Gold')->and($this->package->fresh()->logo)->toBeNull();
    $this->put('/temp/admin/customer_packages/'.$this->package->id, [...$this->payload, 'logo' => $own->id])->assertRedirect();
    $this->get('/temp/admin/customer_packages/edit/'.$this->package->id)->assertInertia(fn (Assert $page) => $page->where('initialFiles.0.id', $own->id));
    $this->package->logo = (string) $foreign->id;
    $this->package->save();
    $this->put('/temp/admin/customer_packages/'.$this->package->id, [...$this->payload, 'logo' => $foreign->id])->assertRedirect();
    $this->get('/temp/admin/customer_packages/edit/'.$this->package->id)->assertInertia(fn (Assert $page) => $page->where('initialFiles', []));
    $library = createStoreStaff('edit_classified_package', 'view_uploads');
    actingAsStoreUser($library);
    $this->post('/temp/admin/customer_packages', [...$this->payload, 'logo' => $foreign->id])->assertRedirect();
});

test('WMS authentication grants no customer package action and cannot change store data', function () {
    $this->actingAs(User::factory()->create(), 'web');
    foreach ([['GET', '/temp/admin/customer_packages'], ['GET', '/temp/admin/customer_packages/create'], ['GET', '/temp/admin/customer_packages/edit/'.$this->package->id], ['POST', '/temp/admin/customer_packages'], ['PUT', '/temp/admin/customer_packages/'.$this->package->id], ['DELETE', '/temp/admin/customer_packages/destroy/'.$this->package->id]] as [$method, $uri]) {
        $this->call($method, $uri, $this->payload)->assertRedirect(route('shop.login'));
    }
    $this->assertDatabaseHas('customer_packages', ['id' => $this->package->id, 'name' => 'Gold', 'amount' => 12.5, 'product_upload' => 10]);
    $this->assertDatabaseCount('customer_packages', 1);
    $this->assertDatabaseCount('customer_package_translations', 1);
});

test('customer package migration preserves purchase and mobile registrations and drops only empty show', function () {
    expect(Route::has('customer_packages.show'))->toBeFalse();
    expect(Route::getRoutes()->getByName('customer_packages.purchase')->getActionName())->toBe('App\\Http\\Controllers\\CustomerPackageController@purchase_package');
    $api = collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => str_contains($route->getActionName(), 'Api\\V2\\CustomerPackageController'));
    expect($api->map(fn ($route) => class_basename($route->getActionName()))->values()->all())->toEqual([
        'CustomerPackageController@purchase_package_offline', 'CustomerPackageController@purchase_package_free', 'CustomerPackageController@customer_packages_list',
    ]);
});

test('admin-created free package purchases still credit once through the retained customer handler', function () {
    actingAsStoreUser(createStoreStaff('edit_classified_package'));
    $this->post('/temp/admin/customer_packages', [...$this->payload, 'amount' => '0', 'product_upload' => 7])->assertRedirect();
    $package = CustomerPackage::query()->orderByDesc('id')->firstOrFail();
    $customer = createStoreUser('customer');
    $customer->forceFill(['remaining_uploads' => 3])->save();
    actingAsStoreUser($customer);
    $this->post('/temp/customer_packages/purchase', ['customer_package_id' => $package->id, 'payment_option' => ''])->assertRedirect(route('dashboard'));
    expect($customer->fresh()->customer_package_id)->toBe($package->id)->and($customer->fresh()->remaining_uploads)->toBe(10);
    $this->from('/temp/customer-packages')->post('/temp/customer_packages/purchase', ['customer_package_id' => $package->id, 'payment_option' => ''])->assertRedirect();
    expect($customer->fresh()->remaining_uploads)->toBe(10);
});

test('paid packages still refuse unsupported payment options without crediting any customer', function () {
    Http::preventStrayRequests();
    config(['store.enabled_payment_gateways' => []]);
    $customer = createStoreUser('customer');
    $customer->forceFill(['remaining_uploads' => 3])->save();
    actingAsStoreUser($customer);
    $this->from('/temp/customer-packages')->post('/temp/customer_packages/purchase', ['customer_package_id' => $this->package->id, 'payment_option' => 'aamarpay'])->assertRedirect();
    expect($customer->fresh()->customer_package_id)->toBeNull()->and($customer->fresh()->remaining_uploads)->toBe(3);
});

test('the unchanged public mobile package resource preserves its exact free and paid payload', function () {
    $this->getJson('/temp/api/v2/customer-packages')->assertOk()->assertJsonPath('data.0', [
        'id' => $this->package->id, 'name' => 'Gold', 'logo' => uploaded_asset(null), 'product_upload_limit' => 10,
        'amount' => single_price(12.5), 'price' => 12.5,
    ]);
    $this->package->amount = 0;
    $this->package->save();
    $this->getJson('/temp/api/v2/customer-packages')->assertOk()->assertJsonPath('data.0', [
        'id' => $this->package->id, 'name' => 'Gold', 'logo' => uploaded_asset(null), 'product_upload_limit' => 10,
        'amount' => translate('Free'), 'price' => 0,
    ]);
});
