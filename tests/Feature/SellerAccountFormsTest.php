<?php

use App\Mail\SecondEmailVerifyMailManager;
use App\Models\Upload;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

function sellerProfileInput(array $overrides = []): array
{
    return ['name' => 'Updated Seller', 'phone' => '123', 'cash_on_delivery_status' => '1', 'bank_payment_status' => '1', ...$overrides];
}

beforeEach(function () {
    Mail::fake();
});

test('seller account forms mark and require exactly their server required fields', function () {
    $seller = sellerAccount();
    $xpath = htmlXPath($this->get(route('seller.profile.index'))->assertOk());
    foreach (['seller.profile.update' => [$seller->id, ['name']], 'user.change.email' => [null, ['email']]] as $route => [$id, $required]) {
        $form = formPostingTo($xpath, route($route, $id));
        foreach (formFieldMarks($xpath, $form) as $name => $field) {
            expect($field['mark'])->toBe(in_array($name, $required, true), $name)
                ->and($field['required'])->toBe(in_array($name, $required, true), $name);
        }
        expect($xpath->query('.//*[@data-required-legend]', $form)->length)->toBe(1);
    }
});

test('seller profile accepts column boundaries and a confirmed six character password', function () {
    $seller = sellerAccount();
    $photo = Upload::factory()->create(['user_id' => $seller->id, 'type' => 'image']);
    $this->from(route('seller.profile.index'))->post(route('seller.profile.update', $seller->id), sellerProfileInput([
        'name' => str_repeat('é', 191), 'phone' => str_repeat('1', 20), 'photo' => $photo->id,
        'bank_name' => str_repeat('b', 255), 'bank_acc_name' => str_repeat('é', 200),
        'bank_acc_no' => str_repeat('1', 50), 'bank_routing_no' => 2147483647,
        'new_password' => '123456', 'confirm_password' => '123456',
    ]))->assertRedirect(route('seller.profile.index'))->assertSessionHasNoErrors();
    expect(mb_strlen($seller->fresh()->name))->toBe(191)
        ->and($seller->fresh()->avatar_original)->toEqual($photo->id)
        ->and(Hash::check('123456', $seller->fresh()->password))->toBeTrue()
        ->and($seller->shop->fresh()->bank_routing_no)->toBe(2147483647)
        ->and(flashedMessage())->toBe('Your Profile has been updated successfully!');
});

test('unchecked payment switches save as zero and an absent password leaves the password alone', function () {
    $seller = sellerAccount();
    $this->post(route('seller.profile.update', $seller->id), ['name' => 'Updated Seller'])->assertSessionHasNoErrors();
    expect($seller->shop->fresh()->cash_on_delivery_status)->toBe(0)
        ->and($seller->shop->fresh()->bank_payment_status)->toBe(0)
        ->and(Hash::check('correct-password', $seller->fresh()->password))->toBeTrue();
});

test('seller profile refuses hostile input before changing either account or shop', function (array $input, string $field) {
    $seller = sellerAccount();
    $before = $seller->getAttributes();
    $shop = $seller->shop->getAttributes();
    $this->post(route('seller.profile.update', $seller->id), sellerProfileInput($input))->assertSessionHasErrorsIn('sellerProfile', [$field]);
    expect($seller->fresh()->getAttributes())->toBe($before)->and($seller->shop->fresh()->getAttributes())->toBe($shop);
})->with([
    'empty name' => [['name' => ''], 'name'],
    'long name' => [['name' => str_repeat('é', 192)], 'name'],
    'array name' => [['name' => ['x']], 'name'],
    'long phone' => [['phone' => str_repeat('1', 21)], 'phone'],
    'array phone' => [['phone' => ['x']], 'phone'],
    'long bank' => [['bank_name' => str_repeat('x', 256)], 'bank_name'],
    'long account name' => [['bank_acc_name' => str_repeat('x', 201)], 'bank_acc_name'],
    'long account number' => [['bank_acc_no' => str_repeat('x', 51)], 'bank_acc_no'],
    'array bank' => [['bank_name' => ['x']], 'bank_name'],
    'array account name' => [['bank_acc_name' => ['x']], 'bank_acc_name'],
    'array account number' => [['bank_acc_no' => ['x']], 'bank_acc_no'],
    'routing overflow' => [['bank_routing_no' => 2147483648], 'bank_routing_no'],
    'routing underflow' => [['bank_routing_no' => -2147483649], 'bank_routing_no'],
    'routing markup' => [['bank_routing_no' => '<b>1</b>'], 'bank_routing_no'],
    'routing array' => [['bank_routing_no' => [1]], 'bank_routing_no'],
    'invalid switch' => [['bank_payment_status' => 'yes'], 'bank_payment_status'],
    'array switch' => [['cash_on_delivery_status' => [1]], 'cash_on_delivery_status'],
    'short password' => [['new_password' => '12345', 'confirm_password' => '12345'], 'new_password'],
    'mismatched password' => [['new_password' => '123456', 'confirm_password' => '654321'], 'new_password'],
    'missing confirmation' => [['new_password' => '123456'], 'new_password'],
    'confirmation alone' => [['confirm_password' => '123456'], 'new_password'],
    'array password' => [['new_password' => ['123456'], 'confirm_password' => ['123456']], 'new_password'],
    'photo array' => [['photo' => [1]], 'photo'],
    'missing photo' => [['photo' => 999999], 'photo'],
    'hidden email writer' => [['email' => 'forged@example.com'], 'email'],
    'unknown empty input' => [['unexpected' => null], 'unexpected'],
]);

test('seller profile accepts only the sellers own image upload', function (string $type) {
    $seller = sellerAccount();
    $owner = $type === 'foreign image' ? createStoreUser('seller') : $seller;
    $photo = Upload::factory()->create(['user_id' => $owner->id, 'type' => $type === 'document' ? 'document' : 'image']);
    $this->post(route('seller.profile.update', $seller->id), sellerProfileInput(['photo' => $photo->id]))->assertSessionHasErrorsIn('sellerProfile', ['photo']);
    expect($seller->fresh()->avatar_original)->toBeNull();
})->with(['foreign image', 'document']);

test('seller profile cannot target another seller or user even with invalid input', function (string $type) {
    sellerAccount();
    $other = createStoreUser($type);
    $before = $other->getAttributes();
    $this->post(route('seller.profile.update', $other->id), ['name' => []])->assertForbidden();
    expect($other->fresh()->getAttributes())->toBe($before);
})->with(['seller', 'customer', 'admin']);

test('seller profile missing id is a 404', function () {
    sellerAccount();
    $this->post(route('seller.profile.update', 999999), sellerProfileInput())->assertNotFound();
});

test('seller profile refuses another account id in the body', function () {
    $seller = sellerAccount();
    $other = createStoreUser('seller');
    $this->post(route('seller.profile.update', $seller->id), sellerProfileInput(['user_id' => $other->id]))->assertSessionHasErrorsIn('sellerProfile', ['user_id']);
    expect($seller->fresh()->name)->toBe('Seller Tester')->and($other->fresh()->name)->toBe('Seller Tester');
});

test('profile keeps markup as text and prints escaped values', function () {
    $seller = sellerAccount();
    $this->post(route('seller.profile.update', $seller->id), sellerProfileInput(['name' => '<b>Name</b>', 'bank_name' => '<b>Bank</b>']))->assertSessionHasNoErrors();
    expect($seller->fresh()->name)->toBe('<b>Name</b>');
    actingAsStoreUser($seller->fresh());
    $this->get(route('seller.profile.index'))->assertOk()->assertSee('&lt;b&gt;Name&lt;/b&gt;', false)->assertSee('&lt;b&gt;Bank&lt;/b&gt;', false);
});

test('profile refusal keeps old text and field errors', function () {
    $seller = sellerAccount();
    $this->from(route('seller.profile.index'))->post(route('seller.profile.update', $seller->id), sellerProfileInput(['name' => '', 'bank_name' => 'Keep me', 'phone' => str_repeat('1', 21)]));
    $xpath = htmlXPath($this->get(route('seller.profile.index'))->assertOk());
    $form = formPostingTo($xpath, route('seller.profile.update', $seller->id));
    expect($xpath->query('.//input[@name="bank_name"]', $form)->item(0)->getAttribute('value'))->toBe('Keep me')
        ->and($xpath->query('.//*[contains(@class,"text-danger")]', $form)->length)->toBeGreaterThanOrEqual(2);
});

test('email change keeps the existing verification flow and flash', function () {
    $seller = sellerAccount();
    $other = createStoreUser('seller');
    $this->from(route('seller.profile.index'))->post(route('user.change.email'), ['email' => 'new@example.com'])->assertRedirect(route('seller.profile.index'))->assertSessionHasNoErrors();
    expect($seller->fresh()->email)->toBe($seller->email)
        ->and($seller->fresh()->new_email_verificiation_code)->not->toBeNull()
        ->and($other->fresh()->new_email_verificiation_code)->toBeNull()
        ->and(flashedMessage())->toBe('A verification mail has been sent to the mail you provided us with.');
    Mail::assertQueued(SecondEmailVerifyMailManager::class, fn ($mail) => $mail->hasTo('new@example.com'));
});

test('own email passes validation and keeps the existing already exists notice', function () {
    $seller = sellerAccount();
    $this->post(route('user.change.email'), ['email' => $seller->email])->assertSessionHasNoErrors();
    expect(flashedMessage())->toBe('Email already exists!');
    Mail::assertNothingQueued();
});

test('email change accepts its 191 character boundary', function () {
    sellerAccount();
    $email = str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 59).'.nl';
    expect(strlen($email))->toBe(191);
    $this->post(route('user.change.email'), ['email' => $email])->assertSessionHasNoErrors();
    Mail::assertQueued(SecondEmailVerifyMailManager::class, fn ($mail) => $mail->hasTo($email));
});

test('email change refuses hostile input without touching verification codes', function (array $input, string $field) {
    $seller = sellerAccount();
    createStoreUser('seller', 'taken@example.com');
    $before = $seller->getAttributes();
    $this->post(route('user.change.email'), $input)->assertSessionHasErrorsIn('changeEmail', [$field]);
    expect($seller->fresh()->getAttributes())->toBe($before);
    Mail::assertNothingQueued();
})->with([
    'missing' => [[], 'email'],
    'array' => [['email' => ['a@example.com']], 'email'],
    'invalid' => [['email' => 'bad'], 'email'],
    'markup' => [['email' => '<b>a@example.com</b>'], 'email'],
    'long' => [['email' => str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 60).'.nl'], 'email'],
    'taken' => [['email' => 'taken@example.com'], 'email'],
    'unshown owner' => [['email' => 'new@example.com', 'user_id' => 999], 'user_id'],
]);

test('email errors and old email render under their own form', function () {
    sellerAccount();
    $this->from(route('seller.profile.index'))->post(route('user.change.email'), ['email' => 'invalid']);
    $xpath = htmlXPath($this->get(route('seller.profile.index'))->assertOk());
    $form = formPostingTo($xpath, route('user.change.email'));
    expect($xpath->query('.//input[@name="email"]', $form)->item(0)->getAttribute('value'))->toBe('invalid')
        ->and($xpath->query('.//*[contains(@class,"text-danger")]', $form)->length)->toBe(1);
});

test('guests cannot write a seller profile or change an email', function () {
    $seller = createStoreUser('seller');
    $this->post(route('seller.profile.update', $seller->id), sellerProfileInput())->assertNotFound();
    $this->post(route('user.change.email'), ['email' => 'new@example.com'])->assertRedirect();
    expect($seller->fresh()->name)->toBe('Seller Tester');
    Mail::assertNothingQueued();
});

test('a customer cannot write the seller profile', function () {
    $seller = createStoreUser('seller');
    actingAsStoreUser(createStoreUser());
    $this->post(route('seller.profile.update', $seller->id), sellerProfileInput())->assertNotFound();
    expect($seller->fresh()->name)->toBe('Seller Tester');
});

test('profile and email refusals still render when old input contains arrays', function (string $field) {
    $seller = sellerAccount();
    $input = $field === 'email' ? ['email' => ['bad']] : sellerProfileInput([$field => ['bad']]);
    $url = $field === 'email' ? route('user.change.email') : route('seller.profile.update', $seller->id);
    $this->from(route('seller.profile.index'))->post($url, $input);
    $this->get(route('seller.profile.index'))->assertOk();
})->with(['name', 'phone', 'photo', 'bank_name', 'bank_acc_name', 'bank_acc_no', 'bank_routing_no', 'email']);

test('seller passwords stay out of flashed old input after a refusal', function () {
    $seller = sellerAccount();
    $this->post(route('seller.profile.update', $seller->id), sellerProfileInput(['new_password' => 'secret123', 'confirm_password' => 'different123']))->assertSessionHasErrorsIn('sellerProfile', ['new_password']);
    expect(session()->getOldInput())->not->toHaveKeys(['new_password', 'confirm_password']);
});

test('seller profile refuses passwords bcrypt cannot hash without changing the account', function (string $password) {
    $seller = sellerAccount();
    $before = $seller->getAttributes();
    $this->post(route('seller.profile.update', $seller->id), sellerProfileInput(['new_password' => $password, 'confirm_password' => $password]))->assertSessionHasErrorsIn('sellerProfile', ['new_password']);
    expect($seller->fresh()->getAttributes())->toBe($before);
})->with(['too many bytes' => [str_repeat('a', 73)], 'multibyte overflow' => [str_repeat('é', 37)], 'nul' => ["secret\0password"]]);

test('seller profile accepts the bcrypt password byte boundary', function () {
    $seller = sellerAccount();
    $password = str_repeat('é', 36);
    $this->post(route('seller.profile.update', $seller->id), sellerProfileInput(['new_password' => $password, 'confirm_password' => $password]))->assertSessionHasNoErrors();
    expect(Hash::check($password, $seller->fresh()->password))->toBeTrue();
});

test('seller profile keeps the existing demo mode refusal before the ownership lookup', function () {
    $seller = sellerAccount();
    $before = $seller->getAttributes();
    $env = $_ENV['DEMO_MODE'] ?? null;
    $server = $_SERVER['DEMO_MODE'] ?? null;
    $_ENV['DEMO_MODE'] = $_SERVER['DEMO_MODE'] = 'On';
    try {
        $this->from(route('seller.profile.index'))->post(route('seller.profile.update', 999999), sellerProfileInput())->assertRedirect(route('seller.profile.index'));
        expect(flashedMessage())->toBe('Sorry! the action is not permitted in demo')
            ->and($seller->fresh()->getAttributes())->toBe($before);
    } finally {
        if ($env === null) {
            unset($_ENV['DEMO_MODE']);
        } else {
            $_ENV['DEMO_MODE'] = $env;
        }
        if ($server === null) {
            unset($_SERVER['DEMO_MODE']);
        } else {
            $_SERVER['DEMO_MODE'] = $server;
        }
    }
});
