<?php
namespace App\Console\Commands;

use App\Models\CombinedOrder;
use App\Models\Customer;
use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Order;
use Carbon\Carbon;
use Exception;

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
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    { // Refactor this code.

        try{
            $verified_customers = User::where("admin_verified", true)->where('user_type', 'customer')->get();
            $total_customers_unverified = 0;
            foreach ($verified_customers as $customer) {
                $last_order_at = $customer->last_order_at;
                $todays_date = Carbon::today();
                if ($last_order_at != null) { //
                    $diffInDays = $todays_date->diffInDays($last_order_at);
                    if ($diffInDays >= 30) {
                        $customer->admin_verified = 0;
                        $customer->last_order_at = null; // for consistency reasons
                        $customer->admin_verified_at = null;
                        $customer->save();
                        $total_customers_unverified += 1; //
                    }
                } else { // If the `Verified Customer` didn't place any orders at all, get the days difference from his account activation date til todays date.
                    $admin_verified_at = $customer->admin_verified_at;
                    $diffInDays = $todays_date->diffInDays($admin_verified_at);
                    if ($diffInDays >= 30) { //
                        $customer->admin_verified = 0;
                        $customer->admin_verified_at = null;
                        $customer->last_order_at = null; // for consistency reasons
                        $customer->save();
                        $total_customers_unverified += 1;
                    }
                }
            }

            $this->info("Total customers that have been unverified for not placing orders for the last 30 days: " . $total_customers_unverified);
        }

        catch(Exception $e){
            $this->info('Command failed ' . $e);

        }
    }
}
