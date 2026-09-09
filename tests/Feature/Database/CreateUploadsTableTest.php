<?php

use App\Models\Upload;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function loadCreateUploadsTableMigration(): object
{
    return require database_path('migrations/2026_09_08_000001_create_uploads_table.php');
}

test('the migration leaves an existing uploads table alone', function () {
    // The shared-database case: `uploads` belongs to the store app, with
    // columns this app never reads.
    Schema::dropIfExists('uploads');
    Schema::create('uploads', function (Blueprint $table) {
        $table->integer('id')->autoIncrement();
        $table->string('store_only_column')->nullable();
    });

    loadCreateUploadsTableMigration()->up();

    expect(Schema::hasColumn('uploads', 'store_only_column'))->toBeTrue();
    expect(Schema::hasColumn('uploads', 'file_name'))->toBeFalse();
});

test('the migration creates only the columns this app reads', function () {
    expect(Schema::hasColumns('uploads', ['id', 'file_name', 'external_link', 'created_at', 'updated_at']))->toBeTrue();
    // Deliberately absent, so nothing here can start depending on them.
    expect(Schema::hasColumn('uploads', 'file_original_name'))->toBeFalse();
    expect(Schema::hasColumn('uploads', 'user_id'))->toBeFalse();
});

test('both file_name and external_link are nullable', function () {
    $upload = Upload::factory()->create(['file_name' => null, 'external_link' => null]);

    expect($upload->fresh())
        ->file_name->toBeNull()
        ->external_link->toBeNull();
});

test('the migration skips dropping the uploads table in production', function () {
    Schema::dropIfExists('uploads');
    loadCreateUploadsTableMigration()->up();
    DB::table('uploads')->insert(['file_name' => 'uploads/all/keeper.png']);

    app()->instance('env', 'production');
    loadCreateUploadsTableMigration()->down();
    app()->instance('env', 'testing');

    expect(Schema::hasTable('uploads'))->toBeTrue();
    expect(DB::table('uploads')->value('file_name'))->toBe('uploads/all/keeper.png');
});
