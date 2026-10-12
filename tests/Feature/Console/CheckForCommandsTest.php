<?php

use App\Console\Commands\CheckForCommands;
use App\Models\CronJobs;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

/**
 * Stands in for the real product import so the tests pin only what
 * check:commands does around it.
 */
class StubProductImportCommand extends Command
{
    public static int $exitCode = 0;

    public static int $calls = 0;

    public static ?Throwable $throws = null;

    protected $signature = 'import:all-products-v2';

    public function handle(): int
    {
        self::$calls++;

        if (self::$throws) {
            throw self::$throws;
        }

        return self::$exitCode;
    }
}

beforeEach(function () {
    StubProductImportCommand::$exitCode = 0;
    StubProductImportCommand::$calls = 0;
    StubProductImportCommand::$throws = null;

    app(Kernel::class)->registerCommand(new StubProductImportCommand);
});

function cronJob(string $command, string $status, ?string $updatedAt = null): CronJobs
{
    $job = CronJobs::create(['command' => $command, 'status' => $status]);

    if ($updatedAt !== null) {
        DB::table('cron_jobs')->where('id', $job->id)->update(['updated_at' => $updatedAt]);
    }

    return $job;
}

test('a pending admin job runs the import and is recorded as completed', function () {
    $job = cronJob('fetching-products-by-admin', 'pending');

    $this->artisan('check:commands')->assertSuccessful();

    $finished = CronJobs::withTrashed()->find($job->id);

    expect(StubProductImportCommand::$calls)->toBe(1)
        ->and($finished->status)->toBe('Completed')
        ->and($finished->trashed())->toBeTrue();
});

test('a failing import fails the command and the job', function () {
    StubProductImportCommand::$exitCode = Command::FAILURE;
    $job = cronJob('fetching-products-by-admin', 'pending');

    $this->artisan('check:commands')->assertFailed();

    $finished = CronJobs::withTrashed()->find($job->id);

    expect($finished->status)->toBe('Failed')
        ->and($finished->trashed())->toBeTrue();
});

test('an exception in the import still closes the job instead of leaving it running', function () {
    StubProductImportCommand::$throws = new RuntimeException('feed unreachable');
    $job = cronJob('fetching-products-by-admin', 'pending');

    $this->artisan('check:commands')->assertFailed();

    $finished = CronJobs::withTrashed()->find($job->id);

    expect($finished->status)->toBe('Failed')
        ->and($finished->output)->toContain('feed unreachable')
        ->and($finished->trashed())->toBeTrue();
});

test('with no pending job it does nothing', function () {
    $this->artisan('check:commands')->assertSuccessful();

    expect(StubProductImportCommand::$calls)->toBe(0);
});

test('a pending or running hostinger job blocks the admin job', function (string $status) {
    cronJob('fetching-products-by-hostinger', $status);
    $adminJob = cronJob('fetching-products-by-admin', 'pending');

    $this->artisan('check:commands')->assertSuccessful();

    expect(StubProductImportCommand::$calls)->toBe(0)
        ->and($adminJob->fresh()->status)->toBe('pending');
})->with(['pending', 'running']);

test('a finished hostinger job does not block the admin job', function () {
    cronJob('fetching-products-by-hostinger', 'Completed');
    cronJob('fetching-products-by-admin', 'pending');

    $this->artisan('check:commands')->assertSuccessful();

    expect(StubProductImportCommand::$calls)->toBe(1);
});

test('a running job of another command does not block the admin job', function () {
    // The status conditions used to be OR-ed outside the command condition, so
    // any running job at all held the queue.
    cronJob('some-other-command', 'running');
    cronJob('fetching-products-by-admin', 'pending');

    $this->artisan('check:commands')->assertSuccessful();

    expect(StubProductImportCommand::$calls)->toBe(1);
});

test('a hostinger job that has not moved for over the timeout is failed and stops blocking', function () {
    $stale = cronJob(
        'fetching-products-by-hostinger',
        'running',
        now()->subMinutes(CheckForCommands::STALE_AFTER_MINUTES + 5)->toDateTimeString(),
    );
    $recent = cronJob('fetching-products-by-hostinger-other', 'running');
    cronJob('fetching-products-by-admin', 'pending');

    $this->artisan('check:commands')->assertSuccessful();

    $failed = CronJobs::withTrashed()->find($stale->id);

    expect($failed->status)->toBe('Failed')
        ->and($failed->trashed())->toBeTrue()
        ->and($recent->fresh()->status)->toBe('running')
        ->and(StubProductImportCommand::$calls)->toBe(1);
});
