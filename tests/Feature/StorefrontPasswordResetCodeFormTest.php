<?php

use App\Models\StoreUser;
use App\Services\StoreVerificationCodes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Password reset by code: the form (shop.auth.passwords.reset, rendered after
 * the forgot-password request) posts the account's email, the emailed six-digit
 * code and the new password to HomeController::reset_password_with_code
 * (shop.password.update). ResetPasswordWithCodeRequest requires the four
 * fields; the code's expiry, single use and attempt cap
 * (StoreVerificationCodes) and the store-auth throttle are as they were.
 * The flows themselves are in LegacyStorePasswordResetTest and
 * LegacyStoreAccountRecoveryTest (store-backoffice-migration.md §48).
 */
const PASSWORD_RESET_CODE_FIELDS = ['email', 'code', 'password', 'password_confirmation'];

/**
 * @return array{0: StoreUser, 1: string} The customer, and the code issued to them.
 */
function customerWithResetCode(string $email = 'reset@example.com'): array
{
    $customer = createStoreUser('customer', $email, 'old-password');

    return [$customer, app(StoreVerificationCodes::class)->issue($customer)];
}

/**
 * @return array<string, mixed>
 */
function resetCodePayload(string $code, array $overrides = []): array
{
    return ['email' => 'reset@example.com', 'code' => $code, 'password' => 'new-password1', 'password_confirmation' => 'new-password1', ...$overrides];
}

function resetCodeForm(DOMXPath $xpath): DOMElement
{
    return formPostingTo($xpath, '/temp/password/reset/email/submit');
}

function expectPasswordUnchanged(StoreUser $customer): void
{
    expect(Hash::check('old-password', $customer->fresh()->password))->toBeTrue()
        ->and(Auth::guard('store')->check())->toBeFalse();
}

describe('the form', function () {
    test('marks and requires all four fields, under the legend', function () {
        $xpath = htmlXPath($this->post('/temp/password/email', ['email' => 'nobody@example.com'])->assertOk());
        $form = resetCodeForm($xpath);
        $required = ['tag' => 'input', 'required' => true, 'mark' => true, 'optional' => false];

        expect(formFieldMarks($xpath, $form))->toBe(array_fill_keys(PASSWORD_RESET_CODE_FIELDS, $required))
            ->and($xpath->query('.//*[@data-required-legend]', $form)->length)->toBe(1)
            ->and($xpath->query('.//label[preceding::*[@data-required-legend]]', $form)->length)->toBe(4);
    });

    test('limits each field in HTML as the request does and labels every control', function () {
        $xpath = htmlXPath($this->post('/temp/password/email', ['email' => 'nobody@example.com']));
        $form = resetCodeForm($xpath);
        $attribute = fn (string $name, string $attribute) => $xpath->query('.//input[@name="'.$name.'"]', $form)->item(0)->getAttribute($attribute);

        expect($attribute('email', 'type'))->toBe('email')
            ->and($attribute('email', 'maxlength'))->toBe('191')
            ->and([$attribute('code', 'minlength'), $attribute('code', 'maxlength'), $attribute('code', 'pattern'), $attribute('code', 'inputmode')])->toBe(['6', '6', '[0-9]{6}', 'numeric'])
            ->and($attribute('password', 'minlength'))->toBe('6')
            ->and($attribute('password_confirmation', 'minlength'))->toBe('6');

        foreach (PASSWORD_RESET_CODE_FIELDS as $name) {
            $id = $attribute($name, 'id');
            expect($id)->not->toBe('')
                ->and($xpath->query('.//label[@for="'.$id.'"]', $form)->length)->toBe(1, "{$name} has a label");
        }
    });

    test('the reset link page no longer prints the email into the code field', function () {
        $xpath = htmlXPath($this->get('/temp/password/reset/some-token?email=reset@example.com')->assertOk());
        $form = resetCodeForm($xpath);

        expect($xpath->query('.//input[@name="email"]', $form)->item(0)->getAttribute('value'))->toBe('reset@example.com')
            ->and($xpath->query('.//input[@name="code"]', $form)->item(0)->getAttribute('value'))->toBe('');
    });

    test('an invalid reset shows the form again with the email and code, never the passwords, and each error under its field', function () {
        [$customer, $code] = customerWithResetCode();

        $response = $this->post(route('shop.password.update'), resetCodePayload($code, ['password' => 'abc', 'password_confirmation' => 'abc']))
            ->assertUnprocessable();

        $form = resetCodeForm($xpath = htmlXPath($response));
        $input = fn (string $name) => $xpath->query('.//input[@name="'.$name.'"]', $form)->item(0);
        $error = fn (string $name) => trim($xpath->query('.//input[@name="'.$name.'"]/following-sibling::*[@role="alert"]', $form)->item(0)?->textContent ?? '');

        expect($input('email')->getAttribute('value'))->toBe('reset@example.com')
            ->and($input('code')->getAttribute('value'))->toBe($code)
            ->and($input('password')->getAttribute('value'))->toBe('')
            ->and($input('password')->getAttribute('class'))->toContain('is-invalid')
            ->and($error('password'))->toBe('The password field must be at least 6 characters.')
            ->and($error('code'))->toBe('');

        expectPasswordUnchanged($customer);
    });

    test('markup sent in the email is printed escaped', function () {
        $response = $this->post(route('shop.password.update'), resetCodePayload('123456', ['email' => '<b>x</b>@example.com']))->assertUnprocessable();

        expect($response->getContent())->not->toContain('<b>x</b>')
            ->and(resetCodeForm($xpath = htmlXPath($response)))->toBeInstanceOf(DOMElement::class)
            ->and($xpath->query('//input[@name="email"]')->item(0)->getAttribute('value'))->toBe('<b>x</b>@example.com');
    });
});

describe('resetting the password', function () {
    test('the right code resets the password, uses the code up and signs the customer in, as before', function () {
        [$customer, $code] = customerWithResetCode();

        $this->post(route('shop.password.update'), resetCodePayload($code))
            ->assertRedirect(route('home'))
            ->assertSessionHasNoErrors();

        expect(Hash::check('new-password1', $customer->fresh()->password))->toBeTrue()
            ->and(Auth::guard('store')->id())->toBe($customer->id)
            ->and(flashedMessage())->toBe('Password updated successfully')
            ->and(DB::table('wms_store_verification_codes')->count())->toBe(0);
    });

    test('a missing field is refused with its own error, and the code stays usable', function (string $field) {
        [$customer, $code] = customerWithResetCode();
        $payload = resetCodePayload($code);
        unset($payload[$field]);

        $response = $this->post(route('shop.password.update'), $payload)->assertUnprocessable();

        $errorField = $field === 'password_confirmation' ? 'password' : $field;
        $form = resetCodeForm($xpath = htmlXPath($response));
        expect($xpath->query('.//input[contains(@class, "is-invalid")]/@name', $form)->item(0)?->nodeValue)->toBe($errorField);
        expectPasswordUnchanged($customer);

        // Nothing was spent on the code: no attempt was counted.
        expect(DB::table('wms_store_verification_codes')->where('user_id', $customer->id)->value('attempts'))->toBe(0);
        $this->post(route('shop.password.update'), resetCodePayload($code))->assertRedirect(route('home'));
    })->with(PASSWORD_RESET_CODE_FIELDS);

    test('hostile input is refused before the code is tried', function (array $input, string $field) {
        [$customer, $code] = customerWithResetCode();

        $response = $this->post(route('shop.password.update'), resetCodePayload($code, $input))->assertUnprocessable();

        $form = resetCodeForm($xpath = htmlXPath($response));
        expect($xpath->query('.//input[@name="'.$field.'"][contains(@class, "is-invalid")]', $form)->length)->toBe(1);
        expectPasswordUnchanged($customer);
        expect(DB::table('wms_store_verification_codes')->where('user_id', $customer->id)->value('attempts'))->toBe(0);
    })->with([
        'an email longer than users.email' => [['email' => str_repeat('a', 180).'@example.com'], 'email'],
        'an email that is not an address' => [['email' => 'reset-at-example.com'], 'email'],
        'an email that is an array' => [['email' => ['reset@example.com']], 'email'],
        'a code longer than six digits' => [['code' => '1234567'], 'code'],
        'a code shorter than six digits' => [['code' => '12345'], 'code'],
        'a code that is not digits' => [['code' => 'abcdef'], 'code'],
        'a code that is an array' => [['code' => ['123456']], 'code'],
        'a code with markup' => [['code' => '<b>1</b>'], 'code'],
        'a password shorter than six characters' => [['password' => 'abc', 'password_confirmation' => 'abc'], 'password'],
        'a password that is an array' => [['password' => ['new-password1'], 'password_confirmation' => ['new-password1']], 'password'],
        'a password that does not match its confirmation' => [['password_confirmation' => 'new-password2'], 'password'],
    ]);

    test('a wrong code is refused with the same notice as before and counts as an attempt', function () {
        [$customer, $code] = customerWithResetCode();

        $this->post(route('shop.password.update'), resetCodePayload(wrongCode($code)))->assertOk();

        expect(flashedMessage())->toBe('Verification code mismatch')
            ->and(DB::table('wms_store_verification_codes')->where('user_id', $customer->id)->value('attempts'))->toBe(1);
        expectPasswordUnchanged($customer);
    });

    test('an expired code is refused', function () {
        [$customer, $code] = customerWithResetCode();
        $this->travel(StoreVerificationCodes::LIFETIME_MINUTES + 1)->minutes();

        $this->post(route('shop.password.update'), resetCodePayload($code))->assertOk();

        expect(flashedMessage())->toBe('Verification code mismatch');
        expectPasswordUnchanged($customer);
    });

    test('a code that was already used is refused the second time', function () {
        [$customer, $code] = customerWithResetCode();
        $this->post(route('shop.password.update'), resetCodePayload($code))->assertRedirect(route('home'));
        Auth::guard('store')->logout();

        $this->post(route('shop.password.update'), resetCodePayload($code, ['password' => 'other-password1', 'password_confirmation' => 'other-password1']))->assertOk();

        expect(Hash::check('new-password1', $customer->fresh()->password))->toBeTrue();
    });

    test('a code works only for the account it was issued to', function () {
        [$attacker, $attackersCode] = customerWithResetCode('attacker@example.com');
        $victim = createStoreUser('customer', 'victim@example.com', 'old-password');

        $this->post(route('shop.password.update'), resetCodePayload($attackersCode, ['email' => 'victim@example.com']))->assertOk();

        expect(flashedMessage())->toBe('Verification code mismatch');
        expectPasswordUnchanged($victim);
        // The attacker's own code is untouched by the attempt on another account.
        expect(DB::table('wms_store_verification_codes')->where('user_id', $attacker->id)->value('attempts'))->toBe(0);
    });

    test('an email with no account is refused with the same notice as a wrong code', function () {
        [$customer, $code] = customerWithResetCode();

        $this->post(route('shop.password.update'), resetCodePayload($code, ['email' => 'nobody@example.com']))->assertOk();

        expect(flashedMessage())->toBe('Verification code mismatch');
        expectPasswordUnchanged($customer);
    });

    test('the store-auth throttle still counts refused input, and then stops even the right code', function () {
        [$customer, $code] = customerWithResetCode();

        foreach (range(1, 5) as $attempt) {
            $this->post(route('shop.password.update'), resetCodePayload('x'))->assertUnprocessable();
        }

        $this->post(route('shop.password.update'), resetCodePayload($code))->assertTooManyRequests();

        expectPasswordUnchanged($customer);
    });
});
