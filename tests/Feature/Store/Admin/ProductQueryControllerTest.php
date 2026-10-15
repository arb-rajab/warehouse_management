<?php

use App\Models\ProductQuery;
use App\Models\StoreUser;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function supportProductQuery(?StoreUser $seller = null): ProductQuery
{
    $seller ??= StoreUser::query()->where('user_type', 'admin')->first() ?? createStoreUser('admin');

    return ProductQuery::forceCreate([
        'seller_id' => $seller->id, 'customer_id' => createStoreUser()->id,
        'product_id' => createStoreProduct(['user_id' => $seller->id, 'name' => 'Rice'])->id,
        'question' => '<b>Available?</b><script>bad</script>', 'reply' => null,
    ]);
}

test('the product query list can be rendered', function () {
    actingAsStoreUser(createStoreStaff('view_all_product_queries'));
    $this->get(route('product_query.index'))->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Support/ProductQueries/Index')
    );
});

test('the product query list returns a complete narrow translated payload and excludes seller queries', function () {
    actingAsStoreUser(createStoreStaff('view_all_product_queries'));
    $query = supportProductQuery();
    supportProductQuery(createStoreUser('seller'));
    DB::table('product_translations')->insert(['product_id' => $query->product_id, 'lang' => 'en', 'name' => 'Translated rice']);
    $this->get(route('product_query.index'))->assertOk()->assertInertia(
        fn (Assert $page) => $page->where('filters', ['per_page' => 20])->has('queries.data', 1)
            ->has('queries.data.0', fn (Assert $row) => $row->where('id', $query->id)
                ->where('user', ['id' => $query->customer_id, 'name' => 'Customer Tester'])
                ->where('product', ['id' => $query->product_id, 'name' => 'Translated rice'])
                ->where('question', 'Available?')->where('reply', null)
                ->where('created_at', $query->created_at->toIso8601String()))
    );
});

test('product queries paginate and accept only the supported page sizes', function () {
    actingAsStoreUser(createStoreStaff('view_all_product_queries'));
    foreach (range(1, 23) as $number) {
        supportProductQuery();
    }
    $this->get(route('product_query.index'))->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('queries.data', 20)->where('queries.meta.total', 23)
    );
    $this->get(route('product_query.index', ['page' => 2]))->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('queries.data', 3)
    );
    $this->get(route('product_query.index', ['per_page' => 10]))->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('queries.data', 10)->where('filters.per_page', 10)
    );
    $this->get(route('product_query.index', ['per_page' => 7]))->assertSessionHasErrors('per_page');
});

test('the product query screen can be rendered and preserves an existing answer', function () {
    $query = supportProductQuery();
    $query->forceFill(['reply' => '<i>Yes</i><script>bad</script>'])->save();
    actingAsStoreUser(createStoreStaff('view_all_product_queries'));
    $this->get(route('product_query.show', $query))->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('Store/Admin/Support/ProductQueries/Show')
            ->where('query.reply', 'Yes')->where('canReply', false)
    );
});

test('product query replies sanitize HTML and ignore forged ownership fields', function () {
    config(['waf.enabled' => false]);
    $query = supportProductQuery();
    $noise = supportProductQuery();
    $staff = actingAsStoreUser(createStoreStaff('reply_to_product_queries'));
    $this->put(route('product_query.reply', $query), ['reply' => '<script>bad</script><b>Yes</b>', 'seller_id' => $staff->id, 'product_id' => $noise->product_id])
        ->assertRedirect(route('product_query.index'));
    expect($query->fresh()->reply)->toBe('<b>Yes</b>')->and($query->fresh()->seller_id)->toBe($query->seller_id)
        ->and($noise->fresh()->reply)->toBeNull();
});

test('product query replies refuse read-only users without changing anything', function () {
    $query = supportProductQuery();
    actingAsStoreUser(createStoreStaff('view_all_product_queries'));
    $this->put(route('product_query.reply', $query), ['reply' => 'forged'])->assertForbidden();
    expect($query->fresh()->reply)->toBeNull();
});

test('product query replies retain the seller or staff restriction and cannot overwrite an answer', function () {
    $owner = createStoreUser('admin');
    $query = supportProductQuery($owner);
    actingAsStoreUser(createStoreAdmin());
    $this->put(route('product_query.reply', $query), ['reply' => 'forged'])->assertForbidden();
    expect($query->fresh()->reply)->toBeNull();
    actingAsStoreUser(createStoreStaff('reply_to_product_queries'));
    $query->forceFill(['reply' => 'Existing answer'])->save();
    $this->put(route('product_query.reply', $query), ['reply' => 'overwrite'])->assertForbidden();
    expect($query->fresh()->reply)->toBe('Existing answer');
});

test('product query replies validate text before changing anything', function (mixed $text) {
    config(['waf.enabled' => false]);
    $query = supportProductQuery();
    actingAsStoreUser(createStoreStaff('reply_to_product_queries'));
    $this->put(route('product_query.reply', $query), ['reply' => $text])->assertSessionHasErrors('reply');
    expect($query->fresh()->reply)->toBeNull();
})->with(['missing' => [null], 'array' => [['bad']], 'too long' => [str_repeat('x', 10001)]]);

test('product query reads refuse unauthorized callers', function (string $caller, string $route) {
    $query = supportProductQuery();
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->get(route($route, $route === 'product_query.index' ? [] : $query)), $caller);
})->with('refused store admin callers')->with(['product_query.index', 'product_query.show']);

test('product query replies refuse unauthorized callers without changing the answer', function (string $caller) {
    $query = supportProductQuery();
    actingAsRefusedStoreCaller($caller);
    assertStoreCallerRefused($this->put(route('product_query.reply', $query), ['reply' => 'forged']), $caller);
    expect($query->fresh()->reply)->toBeNull();
})->with('refused store admin callers');

test('missing product queries return 404', function (string $method, string $route) {
    actingAsStoreUser(createStoreAdmin());
    $this->{$method}(route($route, 999999), ['reply' => 'valid'])->assertNotFound();
})->with([['get', 'product_query.show'], ['put', 'product_query.reply']]);

test('a WMS session alone cannot access or write product queries', function (string $method, string $route) {
    $query = supportProductQuery();
    actingAsAdmin();
    $this->{$method}(route($route, $route === 'product_query.index' ? [] : $query), ['reply' => 'forged'])->assertRedirect(route('shop.login'));
    expect($query->fresh()->reply)->toBeNull();
})->with([['get', 'product_query.index'], ['get', 'product_query.show'], ['put', 'product_query.reply']]);

test('the request firewall still refuses malicious product query replies before writing', function () {
    $query = supportProductQuery();
    actingAsStoreUser(createStoreStaff('reply_to_product_queries'));
    $this->put(route('product_query.reply', $query), ['reply' => '<script>attack</script>'])->assertForbidden();
    expect($query->fresh()->reply)->toBeNull();
});

test('empty legacy answers remain replyable and readable entities stay plain text', function () {
    $query = supportProductQuery();
    $query->forceFill(['reply' => '', 'question' => '<b>Rice &amp; beans</b> &lt;img src=x onerror=alert(1)&gt;'])->save();
    actingAsStoreUser(createStoreStaff('view_all_product_queries', 'reply_to_product_queries'));
    $this->get(route('product_query.show', $query))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('canReply', true)->where('query.question', 'Rice & beans <img src=x onerror=alert(1)>'));
    $this->put(route('product_query.reply', $query), ['reply' => 'Yes'])->assertRedirect(route('product_query.index'));
    expect($query->fresh()->reply)->toBe('Yes');
});
