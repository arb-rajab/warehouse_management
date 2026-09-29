<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cart;

class PurgeOldCarts extends Command
{
    protected $signature = 'carts:purge-old';
    protected $description = 'Permanently delete soft deleted carts older than 30 days';

    public function handle()
    {
        $date = now()->subDays(30); // Adjust the number of days as needed
        $deletedCarts = Cart::onlyTrashed()->where('deleted_at', '<', $date)->forceDelete();

        $this->info("Deleted {$deletedCarts} old soft deleted carts.");
    }
}
