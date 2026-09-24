<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;

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
    protected $description = 'Command description';

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
    {
        $orders = Order::all();

        foreach ($orders as $key => $order) {
            $original_price = 0;
            $rep_price = 0;
            $total_profits = 0;
            $order_details = $order->orderDetails;

            foreach ($order_details as $order_detail) {
                $total_profits += $order_detail->profits;
                if ($order_detail->is_price_changed != 0) {
                    $original_price += $order_detail->original_price;
                    $rep_price += $order_detail->price;
                } else {
                    $original_price += $order_detail->price;
                    $rep_price += $order_detail->price;
                }
            }

            if ($original_price == 0) {
                $order->discount_percent = 100;
                $order->indicator = 0;
                $order->save();
            } else {
                $order->discount_percent = 100 - (($rep_price /  $original_price)  * 100);
                $order->indicator = ($total_profits /  $rep_price) * 100;
                $order->save();
            }
        }
    }
}
