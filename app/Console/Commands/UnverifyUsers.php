<?php

namespace App\Console\Commands;

use App\Models\StoreUser;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class UnverifyUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:customers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the date of the last purchase of each customer, if it was more than 30 days ago, unverify his account(hide the prices)';
    // unverify means: don't let him see the prices of the products

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $cutoff = Carbon::today()->subDays(30)->toDateString();

            // A verified customer is unverified 30 days after their last order, or,
            // if they never ordered, 30 days after their account was verified.
            $totalCustomersUnverified = StoreUser::query()
                ->where('admin_verified', true)
                ->where('user_type', 'customer')
                ->where(function (Builder $query) use ($cutoff): void {
                    $query->where(function (Builder $query) use ($cutoff): void {
                        $query->whereNotNull('last_order_at')->whereDate('last_order_at', '<=', $cutoff);
                    })->orWhere(function (Builder $query) use ($cutoff): void {
                        $query->whereNull('last_order_at')->whereDate('admin_verified_at', '<=', $cutoff);
                    });
                })
                ->update([
                    'admin_verified' => 0,
                    'admin_verified_at' => null,
                    'last_order_at' => null, // for consistency reasons
                ]);

            $this->info('Total customers that have been unverified for not placing orders for the last 30 days: '.$totalCustomersUnverified);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Command failed '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
