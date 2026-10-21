<?php

use App\Models\Shop;
use App\Models\StoreUser;
use Illuminate\Support\Facades\Auth;

/**
 * "Register your shop" (shop.frontend.seller_form at /temp/shops/create,
 * posting to ShopController::store). StoreShopRequest requires the seller's
 * name, email and a confirmed password, the shop's name and its address, each
 * limited to its column: users.name and users.email are varchar(191),
 * shops.name varchar(200), shops.address varchar(500). The form marks and
 * requires exactly those fields (.ai/rules/form-required-fields.md,
 * store-backoffice-migration.md §47).
 */
const SELLER_REGISTRATION_FIELDS = ['name', 'email', 'password', 'password_confirmation', 'shop_name', 'address'];

/**
 * @return array<string, mixed>
 */
function sellerRegistrationPayload(array $overrides = []): array
{
    return [
        'name' => 'New Seller', 'email' => 'new-seller@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        'shop_name' => 'Amsterdam Spices', 'address' => 'Damrak 1, Amsterdam', ...$overrides,
    ];
}

function sellerRegistrationForm(DOMXPath $xpath): DOMElement
{
    return formPostingTo($xpath, '/temp/shops');
}

function sellerRegistrationNothingCreated(): void
{
    expect(StoreUser::where('user_type', 'seller')->count())->toBe(0)
        ->and(Shop::count())->toBe(0)
        ->and(Auth::guard('store')->check())->toBeFalse();
}

describe('the form', function () {
    test('marks and requires exactly the fields the request requires, under the legend', function () {
        $xpath = htmlXPath($this->get('/temp/shops/create')->assertOk());
        $form = sellerRegistrationForm($xpath);
        $required = ['required' => true, 'mark' => true, 'optional' => false];

        expect(formFieldMarks($xpath, $form))->toBe([
            'name' => ['tag' => 'input', ...$required],
            'email' => ['tag' => 'input', ...$required],
            'password' => ['tag' => 'input', ...$required],
            'password_confirmation' => ['tag' => 'input', ...$required],
            'shop_name' => ['tag' => 'input', ...$required],
            'address' => ['tag' => 'input', ...$required],
        ])
            ->and($xpath->query('.//*[@data-required-legend]', $form)->length)->toBe(1)
            ->and($xpath->query('.//label[preceding::*[@data-required-legend]]', $form)->length)->toBe($xpath->query('.//label', $form)->length)
            // The literal asterisks it printed before are gone: one mark per label.
            ->and($xpath->query('.//label/span[@class="text-primary"]', $form)->length)->toBe(0);
    });

    test('limits each field to its column and labels every control', function () {
        $xpath = htmlXPath($this->get('/temp/shops/create')->assertOk());
        $form = sellerRegistrationForm($xpath);
        $attribute = fn (string $name, string $attribute) => $xpath->query('.//input[@name="'.$name.'"]', $form)->item(0)->getAttribute($attribute);

        expect(array_map(fn (string $name) => $attribute($name, 'maxlength'), ['name', 'email', 'shop_name', 'address']))->toBe(['191', '191', '200', '500'])
            ->and($attribute('password', 'minlength'))->toBe('6')
            ->and($attribute('email', 'type'))->toBe('email');

        foreach (SELLER_REGISTRATION_FIELDS as $name) {
            $id = $attribute($name, 'id');
            expect($id)->not->toBe('')
                ->and($xpath->query('.//label[@for="'.$id.'"]', $form)->length)->toBe(1, "{$name} has a label");
        }
    });

    test('a failed registration shows the input again, without the passwords, and each error under its field', function () {
        $this->from('/temp/shops/create')->post('/temp/shops', sellerRegistrationPayload(['shop_name' => '', 'password_confirmation' => 'other']));

        $xpath = htmlXPath($this->get('/temp/shops/create')->assertOk());
        $form = sellerRegistrationForm($xpath);
        $input = fn (string $name) => $xpath->query('.//input[@name="'.$name.'"]', $form)->item(0);
        $error = fn (string $name) => trim($xpath->query('.//input[@name="'.$name.'"]/following-sibling::*[@role="alert"]', $form)->item(0)?->textContent ?? '');

        expect($input('name')->getAttribute('value'))->toBe('New Seller')
            ->and($input('address')->getAttribute('value'))->toBe('Damrak 1, Amsterdam')
            ->and($input('password')->getAttribute('value'))->toBe('')
            ->and($input('shop_name')->getAttribute('class'))->toContain('is-invalid')
            ->and($error('shop_name'))->toBe('The shop name field is required.')
            ->and($error('password'))->toBe('The password field confirmation does not match.');
    });
});

describe('registering a shop', function () {
    test('creates the seller and the shop, signs the seller in and goes to the shop settings', function () {
        $this->post('/temp/shops', sellerRegistrationPayload())
            ->assertRedirect(route('seller.shop.index'))
            ->assertSessionHasNoErrors();

        $seller = StoreUser::sole();
        expect($seller->only(['name', 'email', 'user_type']))->toBe(['name' => 'New Seller', 'email' => 'new-seller@example.com', 'user_type' => 'seller'])
            ->and(Hash::check('secret123', $seller->password))->toBeTrue()
            ->and(Shop::sole()->only(['user_id', 'name', 'address', 'slug']))->toEqual(['user_id' => $seller->id, 'name' => 'Amsterdam Spices', 'address' => 'Damrak 1, Amsterdam', 'slug' => 'Amsterdam-Spices'])
            ->and(Auth::guard('store')->id())->toBe($seller->id)
            ->and(flashedMessage())->toBe('Your Shop has been created successfully!');
    });

    test('takes every field up to its column', function () {
        $this->post('/temp/shops', sellerRegistrationPayload([
            'name' => str_repeat('é', 191), 'email' => str_repeat('a', 179).'@example.com',
            'shop_name' => str_repeat('ü', 200), 'address' => str_repeat('ß', 500),
        ]))->assertSessionHasNoErrors();

        expect(mb_strlen(Shop::sole()->name))->toBe(200)
            ->and(mb_strlen(Shop::sole()->address))->toBe(500)
            ->and(mb_strlen(StoreUser::sole()->name))->toBe(191);
    });

    test('refuses a missing required field with that field\'s error, keeps the input and creates nothing', function (string $field) {
        $payload = sellerRegistrationPayload();
        unset($payload[$field]);

        $this->from('/temp/shops/create')->post('/temp/shops', $payload)
            ->assertRedirect('/temp/shops/create')
            ->assertSessionHasErrors([$field === 'password_confirmation' ? 'password' : $field])
            ->assertSessionHasInput($field === 'name' ? 'shop_name' : 'name');

        sellerRegistrationNothingCreated();
    })->with(SELLER_REGISTRATION_FIELDS);

    test('refuses hostile input and creates nothing', function (array $input, string $field) {
        $this->post('/temp/shops', sellerRegistrationPayload($input))->assertSessionHasErrors([$field]);

        sellerRegistrationNothingCreated();
    })->with([
        'a name longer than users.name' => [['name' => str_repeat('a', 192)], 'name'],
        'an email longer than users.email' => [['email' => str_repeat('a', 180).'@example.com'], 'email'],
        'a shop name longer than shops.name' => [['shop_name' => str_repeat('a', 201)], 'shop_name'],
        'a multibyte shop name longer than shops.name' => [['shop_name' => str_repeat('ü', 201)], 'shop_name'],
        'an address longer than shops.address' => [['address' => str_repeat('a', 501)], 'address'],
        'a name that is not a string' => [['name' => ['New Seller']], 'name'],
        'a shop name that is not a string' => [['shop_name' => ['Amsterdam Spices']], 'shop_name'],
        'an address that is not a string' => [['address' => ['Damrak 1']], 'address'],
        'an email that is not an address' => [['email' => 'not-an-email'], 'email'],
        'a password shorter than six characters' => [['password' => 'abc', 'password_confirmation' => 'abc'], 'password'],
        'a password that does not match its confirmation' => [['password_confirmation' => 'secret124'], 'password'],
    ]);

    test('refuses an email that already has an account', function () {
        $existing = createStoreUser('customer', 'taken@example.com');

        $this->post('/temp/shops', sellerRegistrationPayload(['email' => 'taken@example.com']))->assertSessionHasErrors(['email']);

        expect(StoreUser::count())->toBe(1)
            ->and($existing->fresh()->user_type)->toBe('customer')
            ->and(Shop::count())->toBe(0);
    });

    test('keeps markup as text', function () {
        $this->post('/temp/shops', sellerRegistrationPayload(['shop_name' => '<b>Spices</b> & "Co"', 'address' => '<i>Damrak</i> 1']))->assertSessionHasNoErrors();

        expect(Shop::sole()->only(['name', 'address']))->toBe(['name' => '<b>Spices</b> & "Co"', 'address' => '<i>Damrak</i> 1']);
    });

    test('ignores an account type, an owner or a verification it is sent', function () {
        $other = createStoreUser('seller');

        $this->post('/temp/shops', sellerRegistrationPayload(['user_type' => 'admin', 'user_id' => $other->id, 'verification_status' => 1]))->assertSessionHasNoErrors();

        $shop = Shop::where('name', 'Amsterdam Spices')->sole();
        expect(StoreUser::where('email', 'new-seller@example.com')->sole()->user_type)->toBe('seller')
            ->and($shop->user_id)->not->toBe($other->id)
            ->and((int) $shop->verification_status)->toBe(0);
    });
});

/**
 * ShopController::create shows the form to guests only: it turns admins and
 * customers away ("Admin or Customer can not be a seller"), sellers ("This
 * user already a seller"), and shows nothing to any other signed-in account.
 * store() used to skip all of it and convert the signed-in account into a
 * seller; StoreShopRequest now refuses every signed-in caller before its
 * input is read, with the page's notice.
 */
describe('a signed-in account', function () {
    test('is turned away by a hand-made POST as the page turns it away, and nothing is written', function (string $userType, string $notice) {
        $user = actingAsStoreUser(createStoreUser($userType));
        $shops = Shop::count();

        $this->from('/temp')->get('/temp/shops/create')->assertRedirect('/temp');
        expect(flashedMessage())->toBe($notice);

        $this->from('/temp')->post('/temp/shops', sellerRegistrationPayload())
            ->assertRedirect('/temp')
            ->assertSessionHasNoErrors();

        expect(flashedMessage())->toBe($notice)
            ->and($user->fresh()->user_type)->toBe($userType)
            ->and(Shop::count())->toBe($shops)
            ->and(StoreUser::count())->toBe(1);
    })->with([
        'a customer' => ['customer', 'Admin or Customer can not be a seller'],
        'an admin' => ['admin', 'Admin or Customer can not be a seller'],
        'a seller' => ['seller', 'This user already a seller'],
    ]);

    test('of another type, to whom the page shows nothing, is turned away too', function (string $userType) {
        $user = actingAsStoreUser(createStoreUser($userType));

        expect($this->get('/temp/shops/create')->assertOk()->getContent())->toBe('');

        $this->from('/temp')->post('/temp/shops', sellerRegistrationPayload())->assertRedirect('/temp');

        expect(flashedMessage())->toBe('Sorry! Something went wrong.')
            ->and($user->fresh()->user_type)->toBe($userType)
            ->and(Shop::count())->toBe(0);
    })->with(['staff', 'delivery_boy']);

    test('who already has a shop keeps it and its type', function () {
        $user = actingAsStoreUser(createStoreUser('customer'));
        $shop = Shop::forceCreate(['user_id' => $user->id, 'name' => 'Old Shop', 'address' => 'Old Street 1', 'slug' => 'Old-Shop']);

        $this->from('/temp')->post('/temp/shops', sellerRegistrationPayload(['shop_name' => 'New Shop']))->assertRedirect('/temp');

        expect(flashedMessage())->toBe('Admin or Customer can not be a seller')
            ->and($user->fresh()->user_type)->toBe('customer')
            ->and(Shop::sole()->only(['id', 'name']))->toBe(['id' => $shop->id, 'name' => 'Old Shop']);
    });

    test('is refused before its input is read: an invalid payload gets the same notice, not validation errors', function () {
        $user = actingAsStoreUser(createStoreUser('customer'));

        $this->from('/temp')->post('/temp/shops', ['shop_name' => ['x']])
            ->assertRedirect('/temp')
            ->assertSessionHasNoErrors();

        expect(flashedMessage())->toBe('Admin or Customer can not be a seller')
            ->and($user->fresh()->user_type)->toBe('customer');
    });
});
