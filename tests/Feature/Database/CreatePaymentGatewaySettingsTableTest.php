<?php

use App\Models\PaymentGatewaySetting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the payment gateway settings table has one row per gateway and field, with the secret flag and the editor', function () {
    expect(Schema::hasColumns('payment_gateway_settings', [
        'id', 'gateway', 'field', 'value', 'is_secret', 'updated_by', 'created_at', 'updated_at',
    ]))->toBeTrue();

    $columns = collect(Schema::getColumns('payment_gateway_settings'))->keyBy('name');

    expect($columns['gateway']['nullable'])->toBeFalse()
        ->and($columns['field']['nullable'])->toBeFalse()
        ->and($columns['value']['nullable'])->toBeFalse()
        ->and($columns['updated_by']['nullable'])->toBeTrue();
});

test('a gateway field can be saved only once', function () {
    DB::table('payment_gateway_settings')->insert(['gateway' => 'bkash', 'field' => 'app_key', 'value' => 'one']);
    DB::table('payment_gateway_settings')->insert(['gateway' => 'nagad', 'field' => 'app_key', 'value' => 'noise']);

    expect(fn () => DB::table('payment_gateway_settings')->insert(['gateway' => 'bkash', 'field' => 'app_key', 'value' => 'two']))
        ->toThrow(QueryException::class)
        ->and(DB::table('payment_gateway_settings')->count())->toBe(2);
});

test('a row outlives the staff account that saved it: no foreign key, the raw id stays', function () {
    $user = createStoreStaff('payment_methods_configurations');
    $row = PaymentGatewaySetting::query()->create(['gateway' => 'bkash', 'field' => 'app_key', 'value' => 'KEY', 'updated_by' => $user->id]);

    $user->forceDelete();

    expect(DB::table('payment_gateway_settings')->where('id', $row->id)->value('updated_by'))->toBe($user->id);
});

test('a row never serialises its value', function () {
    $row = PaymentGatewaySetting::query()->create(['gateway' => 'bkash', 'field' => 'app_secret', 'value' => 'ciphertext', 'is_secret' => true]);

    expect($row->toArray())->not->toHaveKey('value')
        ->and($row->toJson())->not->toContain('ciphertext');
});
