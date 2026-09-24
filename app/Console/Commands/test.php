<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cart;
use App\Models\Product;
use App\Models\User;


class test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:test';

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

        $products = Product::where('published', 1)->where('for_sale', 1)->where('attributes', '["6"]')->take(250)->get();
        $user = User::where('email', 'test@test.com')->first();



        //remove the old user carts, to avoid issues
        $carts = Cart::where('user_id', $user->id)->get();
        foreach($carts as $cart){
            $cart->delete();
        }

        //
        foreach($products as $product){
            $data = array();

            $data['owner_id'] = $product->user_id;
            $data['user_id'] = $user->id;
            $data['product_id'] = $product->id;
            $data['variation'] = 'PerBox';
            $data['price'] = $product->price;
            $data['tax'] = 0;
            $data['discount'] = 0;
            $data['coupon_applied'] = false;
            $data['shipping_type'] = 'home_delivery';
            $data['quantity'] = 1;

            Cart::create($data);
        }


    }
}
