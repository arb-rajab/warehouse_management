<?php

use App\Support\CustomerTaxNumber;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * store:tax-number-duplicates previews what the unique tax number migration
 * does to existing rows, and lists what it released, without writing.
 * The duplicates are built the way legacy data holds them: the index is
 * dropped and rows go straight into `users`, past the model's mutator.
 */
beforeEach(function () {
    Schema::table('users', function (Blueprint $table) {
        $table->dropUnique(['tax_number']);
    });
});

/**
 * @param  array<string, mixed>  $attributes
 */
function duplicateReportUser(?string $taxNumber, array $attributes = []): int
{
    return DB::table('users')->insertGetId([
        'name' => 'Report user',
        'user_type' => 'customer',
        'tax_number' => $taxNumber,
        ...$attributes,
    ]);
}

/** @return array<string, list<string>> */
function usersSnapshot(): array
{
    return [
        'users' => DB::table('users')->orderBy('id')->get()->map(fn ($row) => json_encode($row))->all(),
        'releases' => DB::table(CustomerTaxNumber::RELEASES_TABLE)->orderBy('id')->get()->map(fn ($row) => json_encode($row))->all(),
    ];
}

test('it lists each shared tax number with its accounts, which one keeps it, and exits 1', function () {
    $banned = duplicateReportUser('NL-SHARED', ['name' => 'Banned Duplicate', 'email' => 'banned@example.com', 'banned' => 1]);
    $linked = duplicateReportUser('NL-SHARED', ['name' => 'Linked Customer', 'email' => 'linked@example.com', 'AccSysID' => 42]);
    duplicateReportUser('NL-ALONE', ['name' => 'Noise Customer']);
    duplicateReportUser('');
    duplicateReportUser('  ');
    duplicateReportUser(' NL-PADDED ');
    $before = usersSnapshot();

    $this->artisan('store:tax-number-duplicates')
        ->expectsOutputToContain('Blank or whitespace-only tax numbers (the migration stores NULL): 2')
        ->expectsOutputToContain('Untrimmed tax numbers (the migration trims them): 1')
        ->expectsOutputToContain('Duplicate tax numbers: 1 group(s), 2 customer(s)')
        ->expectsOutputToContain('Tax number "NL-SHARED" (2 customers)')
        ->expectsTable(
            ['id', 'name', 'email', 'company', 'type', 'banned', 'archived', 'AccSysID', 'created_at', 'migration'],
            [
                [(string) $linked, 'Linked Customer', 'linked@example.com', '', 'customer', 'no', 'no', '42', '-', 'keeps'],
                [(string) $banned, 'Banned Duplicate', 'banned@example.com', '', 'customer', 'yes', 'no', '-', '-', 'released (set NULL)'],
            ],
        )
        ->assertFailed();

    expect(usersSnapshot())->toBe($before);
});

test('it groups values that differ only in surrounding spaces, as the migration will', function () {
    $first = duplicateReportUser('NL-PAD');
    $second = duplicateReportUser(' NL-PAD ');

    Artisan::call('store:tax-number-duplicates', ['--json' => true]);
    $report = json_decode(Artisan::output(), true);

    expect($report['untrimmed'])->toBe(1)
        ->and($report['groups'])->toHaveCount(1)
        ->and($report['groups'][0]['tax_number'])->toBe('NL-PAD')
        ->and(array_column($report['groups'][0]['customers'], 'id'))->toBe([$first, $second]);
});

test('--json prints the whole report', function () {
    $archived = duplicateReportUser('NL-JSON', ['name' => 'Archived', 'email' => 'archived@example.com', 'company_name' => 'Old BV', 'is_delete' => 1]);
    $active = duplicateReportUser('NL-JSON', ['name' => 'Active', 'email' => 'active@example.com', 'company_name' => 'New BV']);
    duplicateReportUser('');

    expect(Artisan::call('store:tax-number-duplicates', ['--json' => true]))->toBe(1);

    expect(json_decode(Artisan::output(), true))->toBe([
        'blank' => 1,
        'untrimmed' => 0,
        'groups' => [[
            'tax_number' => 'NL-JSON',
            'customers' => [
                ['id' => $active, 'name' => 'Active', 'email' => 'active@example.com', 'company_name' => 'New BV', 'user_type' => 'customer', 'banned' => false, 'archived' => false, 'AccSysID' => null, 'created_at' => null, 'keep' => true],
                ['id' => $archived, 'name' => 'Archived', 'email' => 'archived@example.com', 'company_name' => 'Old BV', 'user_type' => 'customer', 'banned' => false, 'archived' => true, 'AccSysID' => null, 'created_at' => null, 'keep' => false],
            ],
        ]],
    ]);
});

test('it exits 0 when every tax number is unique', function () {
    duplicateReportUser('NL-ONE');
    duplicateReportUser('NL-TWO');
    duplicateReportUser(null);

    $this->artisan('store:tax-number-duplicates')
        ->expectsOutputToContain('Duplicate tax numbers: 0 group(s), 0 customer(s)')
        ->assertSuccessful();
});

test('--released lists what the migration released, with the account\'s current tax number', function () {
    $kept = duplicateReportUser('NL-KEPT');
    $released = duplicateReportUser(null, ['name' => 'Released Customer', 'email' => 'released@example.com']);
    DB::table(CustomerTaxNumber::RELEASES_TABLE)->insert(['user_id' => $released, 'kept_user_id' => $kept, 'tax_number' => 'NL-KEPT', 'released_at' => '2026-11-03 10:00:00']);
    $before = usersSnapshot();

    $this->artisan('store:tax-number-duplicates --released')
        ->expectsTable(
            ['customer id', 'name', 'email', 'tax number now', 'released tax number', 'kept by customer id', 'released at'],
            [[(string) $released, 'Released Customer', 'released@example.com', '-', 'NL-KEPT', (string) $kept, '2026-11-03 10:00:00']],
        )
        ->assertSuccessful();

    expect(usersSnapshot())->toBe($before);
});

test('--released says so when nothing was released', function () {
    $this->artisan('store:tax-number-duplicates --released')
        ->expectsOutputToContain('No tax number has been released.')
        ->assertSuccessful();
});
