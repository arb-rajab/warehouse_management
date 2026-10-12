<?php

use App\Models\Address;
use App\Models\Cart;
use App\Models\StoreUser;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Hash;

/**
 * The storefront's and the seller area's own-record actions, which took any
 * record by id: the seller profile update (any account's name, phone and
 * password, admins' included), both areas' address edit, update, delete and
 * set-default, the web cart's remove, quantity, notes and price actions, and
 * the web wishlist's remove.
 */
function storeAddress(StoreUser $owner, bool $isDefault = false): Address
{
    return Address::forceCreate(['user_id' => $owner->id, 'address' => 'Damrak 1', 'phone' => '612345678', 'set_default' => (int) $isDefault]);
}

test("a seller cannot update another account's profile or password", function () {
    $admin = createStoreUser('admin');
    actingAsStoreUser(createStoreUser('seller'));

    $this->post("/temp/seller/profile/update/{$admin->id}", [
        'name' => 'Taken Over', 'phone' => '1', 'new_password' => 'new-password', 'confirm_password' => 'new-password',
    ])->assertForbidden();

    expect($admin->fresh()->name)->toBe('Admin Tester')
        ->and(Hash::check('correct-password', $admin->fresh()->password))->toBeTrue();
});

test('a seller can update their own profile', function () {
    $seller = actingAsStoreUser(createStoreUser('seller'));

    $this->post("/temp/seller/profile/update/{$seller->id}", ['name' => 'Renamed Seller', 'phone' => '123'])->assertRedirect();

    expect($seller->fresh()->name)->toBe('Renamed Seller');
});

dataset('address areas', [
    'storefront' => ['customer', '/temp/addresses', '/temp/addresses/update'],
    'seller area' => ['seller', '/temp/seller/addresses', '/temp/seller/address/update'],
]);

test("a store account cannot open, change, delete or default another account's address", function (string $userType, string $prefix, string $updateUri) {
    $theirs = storeAddress(createStoreUser($userType));
    $mine = storeAddress(actingAsStoreUser(createStoreUser($userType)), isDefault: true);

    $this->get("{$prefix}/{$theirs->id}/edit")->assertForbidden();
    $this->post("{$updateUri}/{$theirs->id}", ['address' => 'Changed 2', 'phone' => '1'])->assertForbidden();
    $this->post("{$prefix}/set_default/{$theirs->id}")->assertForbidden();
    $this->post("{$prefix}/destroy/{$theirs->id}")->assertForbidden();

    expect($theirs->fresh()->only(['address', 'set_default']))->toEqual(['address' => 'Damrak 1', 'set_default' => 0])
        ->and($mine->fresh()->set_default)->toBe(1);
})->with('address areas');

test('a store account can open, change, default and delete its own address', function (string $userType, string $prefix, string $updateUri) {
    $owner = actingAsStoreUser(createStoreUser($userType));
    $default = storeAddress($owner, isDefault: true);
    $other = storeAddress($owner);

    $this->get("{$prefix}/{$other->id}/edit")->assertOk()->assertJsonPath('data.address_data.id', $other->id);
    $this->post("{$updateUri}/{$other->id}", ['address' => 'Changed 2', 'phone' => '1'])->assertRedirect();
    // Re-defaulting the current default keeps it the default.
    $this->post("{$prefix}/set_default/{$default->id}")->assertRedirect();

    expect($other->fresh()->address)->toBe('Changed 2')
        ->and($default->fresh()->set_default)->toBe(1);

    $this->post("{$prefix}/destroy/{$other->id}")->assertRedirect();

    expect(Address::find($other->id))->toBeNull();
})->with('address areas');

test("a customer cannot remove or change another customer's cart item", function () {
    $product = createStoreProduct();
    $theirs = Cart::create(['user_id' => createStoreUser('customer')->id, 'product_id' => $product->id, 'quantity' => 3, 'price' => 2.5]);
    actingAsStoreUser(createStoreUser('customer'));

    $this->post('/temp/cart/removeFromCart', ['id' => $theirs->id])->assertNotFound();
    $this->post('/temp/cart/updateQuantity', ['id' => $theirs->id, 'quantity' => 1])->assertNotFound();
    $this->post('/temp/cart/update_item_notes', ['id' => $theirs->id, 'item_notes' => 'Mine now'])->assertOk()->assertSee('0');
    $this->post('/temp/cart/update_item_price', ['id' => $theirs->id, 'rep_price' => 2.5])->assertOk()->assertSee('0');

    expect($theirs->fresh()->only(['quantity', 'item_notes', 'is_price_changed']))->toEqual([
        'quantity' => 3,
        'item_notes' => null,
        'is_price_changed' => 0,
    ]);
});

test("a guest cannot remove a signed-in customer's or another guest's cart item", function () {
    $product = createStoreProduct();
    $customers = Cart::create(['user_id' => createStoreUser('customer')->id, 'product_id' => $product->id, 'quantity' => 1]);
    $otherGuests = Cart::create(['temp_user_id' => 'other-guest', 'product_id' => $product->id, 'quantity' => 1]);

    // A guest with no cart yet has no temp_user_id, which must not match every account's cart.
    $this->post('/temp/cart/removeFromCart', ['id' => $customers->id])->assertNotFound();
    $this->withSession(['temp_user_id' => 'this-guest'])
        ->post('/temp/cart/removeFromCart', ['id' => $otherGuests->id])
        ->assertNotFound();

    expect(Cart::whereKey([$customers->id, $otherGuests->id])->count())->toBe(2);
});

test('a customer and a guest can each change and remove their own cart item', function () {
    seedStoreCurrency();
    $product = createStoreProduct();
    $customer = createStoreUser('customer');
    $customers = Cart::create(['user_id' => $customer->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 2.5]);
    $guests = Cart::create(['temp_user_id' => 'this-guest', 'product_id' => $product->id, 'quantity' => 1, 'price' => 2.5]);

    $this->withSession(['temp_user_id' => 'this-guest'])
        ->post('/temp/cart/removeFromCart', ['id' => $guests->id])
        ->assertRedirect();

    actingAsStoreUser($customer);
    $this->post('/temp/cart/update_item_notes', ['id' => $customers->id, 'item_notes' => 'Gift wrap'])->assertOk()->assertSee('1');

    expect($customers->fresh()->item_notes)->toBe('Gift wrap');

    $this->post('/temp/cart/removeFromCart', ['id' => $customers->id])->assertRedirect();

    expect(Cart::whereKey([$customers->id, $guests->id])->exists())->toBeFalse();
});

test("a customer can remove their own wishlist item from the storefront and not another customer's", function () {
    $product = createStoreProduct();
    $customer = createStoreUser('customer');
    $theirs = Wishlist::create(['user_id' => createStoreUser('customer')->id, 'product_id' => $product->id]);
    $mine = Wishlist::create(['user_id' => $customer->id, 'product_id' => $product->id]);
    actingAsStoreUser($customer);

    $this->post('/temp/wishlists/remove', ['id' => $theirs->id])->assertNotFound();
    $this->post('/temp/wishlists/remove', ['id' => $mine->id])->assertOk();

    expect(Wishlist::whereKey($theirs->id)->exists())->toBeTrue()
        ->and(Wishlist::whereKey($mine->id)->exists())->toBeFalse();
});
