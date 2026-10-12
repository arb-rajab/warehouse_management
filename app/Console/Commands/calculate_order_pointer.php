<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class calculate_order_pointer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:calculate-order-pointer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate every order\'s discount_percent and indicator from its order details';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Order::query()
            ->select('id')
            ->with('orderDetails:id,order_id,price,original_price,is_price_changed,profits')
            ->chunkById(200, function ($orders): void {
                foreach ($orders as $order) {
                    $originalPrice = 0;
                    $repPrice = 0;
                    $totalProfits = 0;

                    foreach ($order->orderDetails as $orderDetail) {
                        $totalProfits += $orderDetail->profits;
                        $originalPrice += $orderDetail->is_price_changed != 0 ? $orderDetail->original_price : $orderDetail->price;
                        $repPrice += $orderDetail->price;
                    }

                    if ($originalPrice == 0) {
                        $order->discount_percent = 100;
                        $order->indicator = 0;
                    } else {
                        $order->discount_percent = 100 - (($repPrice / $originalPrice) * 100);
                        $order->indicator = $repPrice == 0 ? 0 : ($totalProfits / $repPrice) * 100;
                    }

                    $order->save();
                }
            });

        return self::SUCCESS;
    }
}
