<?php

use App\Models\ManualPaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadCreateManualPaymentMethodsMigration(): object
{
    return require database_path('migrations/2026_11_10_100000_create_manual_payment_methods_table.php');
}

test('manual_payment_methods has the columns the offline payment code reads', function () {
    expect(Schema::hasTable('manual_payment_methods'))->toBeTrue()
        ->and(Schema::hasColumns('manual_payment_methods', ['id', 'type', 'heading', 'description', 'bank_info', 'photo', 'created_at', 'updated_at']))->toBeTrue();

    $method = ManualPaymentMethod::factory()->create(['heading' => 'Bank transfer']);

    expect($method->fresh()->heading)->toBe('Bank transfer');
});

test('running the migration when the table exists keeps the rows and does not fail', function () {
    $method = ManualPaymentMethod::factory()->create();

    loadCreateManualPaymentMethodsMigration()->up();

    expect(DB::table('manual_payment_methods')->where('id', $method->id)->exists())->toBeTrue();
});

test('rolling back drops the table outside production and migrating again restores it', function () {
    $migration = loadCreateManualPaymentMethodsMigration();

    $migration->down();

    try {
        expect(Schema::hasTable('manual_payment_methods'))->toBeFalse();
        $migration->down();
    } finally {
        $migration->up();
    }

    expect(Schema::hasTable('manual_payment_methods'))->toBeTrue();
});
