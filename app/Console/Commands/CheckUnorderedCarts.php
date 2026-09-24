<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cart;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use App\Notifications\UnorderedCartNotification;
use Exception;

class CheckUnorderedCarts extends Command
{
    protected $signature = 'check:unordered-carts';

    protected $description = 'Check for unordered carts and send notifications.';


    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            $start_time = Carbon::now();

            $carts = Cart::where('updated_at', '<=', Carbon::now()->subDays(5))->where('is_admin_notified', false)->groupBy('user_id')->get();
            $admins = User::where('user_type', 'admin')->get();

            $notifications_count = 0;
            if ($carts->isNotEmpty()) {
                foreach ($carts as $cart) {
                    if(!$cart->user->is_rep){
                        $userName = $cart->user->name;
                        $userId = $cart->user->id;

                        $cartCount = $cart->user->carts()->count();
                        $message = 'have added ' . $cartCount . ' products to their cart since 5 days and not placed an order.';

                        $notifications_count++;
                        Notification::send($admins, new UnorderedCartNotification($userName, $userId, $cartCount, $message));

                        $cart->is_admin_notified = 1;
                        $cart->save();
                    }
                }
            }

            $end_time = Carbon::now();
            $time = $end_time->diffInSeconds($start_time);

            $this->info($notifications_count . " notifications have sent successfully. Process took " . $time . ' seconds');
        } catch(Exception $e){
            $this->error('Command failed ' . $e->getMessage());
        }
    }
}
