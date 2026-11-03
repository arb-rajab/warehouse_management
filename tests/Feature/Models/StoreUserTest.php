<?php

use App\Models\StoreUser;
use App\Support\CustomerTaxNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;

test('the tax number is stored trimmed, and blank as null, however it is written', function (mixed $written, ?string $stored) {
    $customer = createStoreUser();

    $customer->tax_number = $written;
    $customer->save();
    expect($customer->fresh()->tax_number)->toBe($stored);

    $customer->forceFill(['tax_number' => $written])->save();
    expect($customer->fresh()->tax_number)->toBe($stored);
})->with([
    'a plain value' => ['NL123', 'NL123'],
    'padded with spaces' => ['  NL123  ', 'NL123'],
    'padded with tabs, newlines and non-breaking spaces' => ["\t\u{00A0}NL123\n", 'NL123'],
    'an inner space kept' => [' NL 123 ', 'NL 123'],
    'empty' => ['', null],
    'whitespace only' => ["  \t ", null],
    'null' => [null, null],
    'a number' => [123, '123'],
]);

test('two customers cannot hold the same tax number, while any number of them may have none', function () {
    $first = createStoreUser();
    $first->forceFill(['tax_number' => 'NL123'])->save();
    createStoreUser()->forceFill(['tax_number' => ''])->save();
    createStoreUser()->forceFill(['tax_number' => null])->save();
    $second = createStoreUser();

    expect(fn () => $second->forceFill(['tax_number' => ' NL123 '])->save())->toThrow(UniqueConstraintViolationException::class)
        ->and(StoreUser::whereNull('tax_number')->count())->toBeGreaterThanOrEqual(3);
});

test('the shared tax number rules refuse another customer\'s number and accept the customer\'s own', function () {
    $holder = createStoreUser();
    $holder->forceFill(['tax_number' => 'NL123'])->save();
    $other = createStoreUser();

    expect(Validator::make(['tax_number' => 'NL123'], ['tax_number' => CustomerTaxNumber::rules()])->errors()->get('tax_number'))
        ->toBe(['This tax number is already registered to another customer.'])
        ->and(Validator::make(['tax_number' => 'NL123'], ['tax_number' => CustomerTaxNumber::rules(ignoreCustomerId: $holder->id)])->passes())->toBeTrue()
        ->and(Validator::make(['tax_number' => 'NL123'], ['tax_number' => CustomerTaxNumber::rules(ignoreCustomerId: $other->id)])->fails())->toBeTrue()
        ->and(Validator::make(['tax_number' => null], ['tax_number' => CustomerTaxNumber::rules()])->passes())->toBeTrue()
        ->and(Validator::make(['tax_number' => null], ['tax_number' => CustomerTaxNumber::rules(required: true)])->fails())->toBeTrue()
        ->and(Validator::make(['tax_number' => str_repeat('x', 101)], ['tax_number' => CustomerTaxNumber::rules()])->fails())->toBeTrue();
});

test('the arabic message for a taken tax number is translated', function () {
    expect(__('validation.custom.tax_number.unique', [], 'ar'))->toBe('هذا الرقم الضريبي مسجّل بالفعل لعميل آخر.')
        ->and(__('validation.custom.tax_number.unique', [], 'en'))->toBe('This tax number is already registered to another customer.');
});
