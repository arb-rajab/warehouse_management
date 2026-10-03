<?php

namespace App\Console\Commands;

use App\Models\CronJobs;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class CheckForCommands extends Command
{
    /**
     * Minutes after which a pending or running job is treated as abandoned
     * (a crashed run never reaches its own status update) and stops blocking
     * every later run.
     */
    public const STALE_AFTER_MINUTES = 60;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:commands';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'this cron job runs every minute to check if there is a products fetching command in the cron_jobs table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->failStaleJobs();

        // If there is already a running fetching-products-by-HOSTINGER command, exit to prevent duplicate products.
        $runningJob = CronJobs::query()
            ->where('command', 'fetching-products-by-hostinger')
            ->whereIn('status', ['pending', 'running'])
            ->first();

        if ($runningJob) {
            $this->warn('Products fetching-by-hostinger Command was running, Command_Id: '.$runningJob->id.', On '.Carbon::now()->toRfc7231String());

            return self::SUCCESS;
        }

        // If not, check if there is a request for fetching-products-by-ADMIN.
        $adminCronJob = CronJobs::query()
            ->where('command', 'fetching-products-by-admin')
            ->where('status', 'pending')
            ->first();

        if (! $adminCronJob) {
            return self::SUCCESS;
        }

        $status = 'Failed';
        $output = 'Command did not finish';
        $exitCode = self::FAILURE;

        try {
            $adminCronJob->status = 'running';
            $adminCronJob->save();

            $startTime = Carbon::now();

            if ($this->call('import:all-products-v2') !== self::SUCCESS) {
                $output = 'Command failed: import:all-products-v2 exited with an error';
                $this->error($output);
            } else {
                $time = (int) Carbon::now()->diffInSeconds($startTime, true);
                $status = 'Completed';
                $output = 'Products fetched/updated successfully. Process took '.$time.' seconds';
                $exitCode = self::SUCCESS;
                $this->info($output);
            }
        } catch (Throwable $e) {
            $output = 'Command failed '.$e->getMessage();
            $this->error($output);
        } finally {
            $adminCronJob->status = $status;
            $adminCronJob->output = $output;
            $adminCronJob->save();
            $adminCronJob->delete(); // just a soft delete, to keep the timestamps for tracking.
        }

        return $exitCode;
    }

    /**
     * Marks every pending or running job that has not been touched for
     * STALE_AFTER_MINUTES as failed, so a crashed run cannot block the queue.
     */
    private function failStaleJobs(): void
    {
        CronJobs::query()
            ->whereIn('status', ['pending', 'running'])
            ->where('updated_at', '<', Carbon::now()->subMinutes(self::STALE_AFTER_MINUTES))
            ->get()
            ->each(function (CronJobs $job): void {
                $job->status = 'Failed';
                $job->output = 'Timed out after '.self::STALE_AFTER_MINUTES.' minutes without finishing';
                $job->save();
                $job->delete();
            });
    }
}
