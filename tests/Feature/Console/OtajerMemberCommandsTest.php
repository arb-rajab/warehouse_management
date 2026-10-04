<?php

use App\Models\StoreUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.otajer.url' => 'https://otajer.test', 'services.otajer.token' => 'fake-token']);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function otajerMember(array $overrides = []): array
{
    return [
        'Email' => 'new-member@example.com',
        'FirstName' => 'New',
        'LastName' => 'Member',
        'CompanyName' => 'Member BV',
        'TaxNumber' => 'NL123',
        'CountryenName' => 'Holland',
        'Serial' => 'M-1',
        'AccSystemID' => 777,
        ...$overrides,
    ];
}

test('import:members creates a verified customer with a random, unguessable password', function () {
    Http::fake(['otajer.test/api/rest/loadmembers/*' => Http::response(['Members' => [otajerMember()]])]);

    $this->artisan('import:members')->assertSuccessful();

    $member = StoreUser::where('email', 'new-member@example.com')->first();

    expect($member)->not->toBeNull()
        ->and($member->country)->toBe('Netherlands')
        ->and($member->email_verified_at)->not->toBeNull()
        ->and($member->email_verified_at->diffInMinutes(now(), true))->toBeLessThan(2)
        ->and(Hash::check('123456', $member->password))->toBeFalse();
});

test('import:members gives each imported member a different password', function () {
    Http::fake(['otajer.test/api/rest/loadmembers/*' => Http::response(['Members' => [
        otajerMember(),
        otajerMember(['Email' => 'second@example.com', 'AccSystemID' => 778, 'Serial' => 'M-2']),
    ]])]);

    $this->artisan('import:members')->assertSuccessful();

    expect(StoreUser::whereIn('email', ['new-member@example.com', 'second@example.com'])->pluck('password')->unique())->toHaveCount(2);
});

test('import:members updates an existing member and leaves a different one alone', function () {
    $existing = createStoreUser('customer', 'new-member@example.com');
    $noise = createStoreUser('customer', 'noise@example.com');

    Http::fake(['otajer.test/api/rest/loadmembers/*' => Http::response(['Members' => [otajerMember()]])]);

    $this->artisan('import:members')->assertSuccessful();

    expect($existing->fresh()->member_serial)->toBe('M-1')
        ->and($existing->fresh()->AccSysID)->toEqual(777)
        ->and($noise->fresh()->member_serial)->toBeNull();
});

test('import:agents creates a verified representative with a random password', function () {
    Http::fake(['otajer.test/api/rest/listagents/*' => Http::response(['Agents' => [
        ['ID' => 5, 'OSerial' => 'A-5', 'Name' => 'Agent Five'],
    ]])]);

    $this->artisan('import:agents')->assertSuccessful();

    $agent = StoreUser::where('rep_serial', 'A-5')->first();

    expect($agent)->not->toBeNull()
        ->and($agent->is_rep)->toBeTrue()
        ->and($agent->email_verified_at)->not->toBeNull()
        ->and($agent->email_verified_at->diffInMinutes(now(), true))->toBeLessThan(2)
        ->and(Hash::check('123456', $agent->password))->toBeFalse();
});

test('fix:members retires the zero-profit duplicate and keeps the other', function () {
    $keeper = createStoreUser('customer');
    $keeper->forceFill(['AccSysID' => 100, 'profits' => 50])->save();
    $duplicate = createStoreUser('customer');
    $duplicate->forceFill(['AccSysID' => 100, 'profits' => 0])->save();
    $noise = createStoreUser('customer');
    $noise->forceFill(['AccSysID' => 200, 'profits' => 0])->save();

    Http::fake(['otajer.test/api/rest/loadmembers/*' => Http::response(['Members' => [
        otajerMember(['AccSystemID' => 100, 'Serial' => 'S-100']),
        otajerMember(['AccSystemID' => 200, 'Serial' => 'S-200']),
    ]])]);

    $this->artisan('fix:members')->assertSuccessful();

    expect($keeper->fresh()->member_serial)->toBe('S-100')
        ->and($keeper->fresh()->banned)->toEqual(0)
        ->and($duplicate->fresh()->banned)->toEqual(1)
        ->and($duplicate->fresh()->AccSysID)->toBeNull()
        ->and($noise->fresh()->banned)->toEqual(0)
        ->and($noise->fresh()->AccSysID)->toEqual(200)
        ->and(StoreUser::whereKey($duplicate->id)->exists())->toBeTrue();

    // The duplicate no longer shares the AccSysID, so a second run changes nothing.
    $this->artisan('fix:members')->assertSuccessful();

    expect($keeper->fresh()->banned)->toEqual(0);
});

test('fix:members --dry-run reports the duplicate without writing anything', function () {
    $keeper = createStoreUser('customer');
    $keeper->forceFill(['AccSysID' => 100, 'profits' => 50])->save();
    $duplicate = createStoreUser('customer');
    $duplicate->forceFill(['AccSysID' => 100, 'profits' => 0])->save();

    Http::fake(['otajer.test/api/rest/loadmembers/*' => Http::response(['Members' => [
        otajerMember(['AccSystemID' => 100, 'Serial' => 'S-100']),
    ]])]);

    $this->artisan('fix:members', ['--dry-run' => true])
        ->expectsOutputToContain('would retire')
        ->assertSuccessful();

    expect($duplicate->fresh()->banned)->toEqual(0)
        ->and($duplicate->fresh()->AccSysID)->toEqual(100)
        ->and($keeper->fresh()->member_serial)->toBeNull();
});

test('fix:members rolls back when it fails part way', function () {
    $user = createStoreUser('customer');
    $user->forceFill(['AccSysID' => 100])->save();

    // The second member has no Serial, which raises after the first was written.
    Http::fake(['otajer.test/api/rest/loadmembers/*' => Http::response(['Members' => [
        otajerMember(['AccSystemID' => 100, 'Serial' => 'S-100']),
        ['AccSystemID' => 200],
    ]])]);

    $this->artisan('fix:members')->assertFailed();

    expect($user->fresh()->member_serial)->toBeNull();
});

test('every otajer command exits non-zero when the api answers with an error', function (string $command) {
    Http::fake(['otajer.test/*' => Http::response('Server Error', 500)]);

    $this->artisan($command)->assertFailed();
})->with([
    'import:members',
    'import:agents',
    'fix:members',
    'sync:users',
    'import:brands',
    'import:categories',
    'import:collections',
    'import:offer-new-products',
    'import:all-products',
]);
