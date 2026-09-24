<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;

use Carbon\Carbon;
use Exception;

class NewAndOfferProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:offer-new-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the status of products';

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
        try {
            $start_time = Carbon::now();
            $products_count = 0;
            $endpoint = 'https://stores.otajer.com/api/rest/newandofferproducts/' . config('services.otajer.token');
            $response = file_get_contents($endpoint);
            $allProducts = json_decode($response, true);
            $products_count += count($allProducts['NewProducts']);
            $products_count += count($allProducts['OfferProducts']);


            $oldProducts = Product::where('isNewProduct', true)->orWhere('isOfferProduct', true)->get();
            if ($oldProducts) {
                foreach ($oldProducts as $product) {
                    $product->update([
                        'isNewProduct' => false,
                        'isOfferProduct' => false
                    ]);
                }
            }


            foreach ($allProducts['NewProducts'] as $product) {
                $selectedProduct = Product::where('serial', $product['Serial'])->first();

                if ($selectedProduct) {
                    $selectedProduct->update([
                        'isNewProduct' => true
                    ]);
                }
            }



            foreach ($allProducts['OfferProducts'] as $product) {
                $selectedProduct = Product::where('serial', $product['Serial'])->first();

                if ($selectedProduct) {
                    $selectedProduct->update([
                        'isOfferProduct' => true
                    ]);
                }
            }

            $end_time = Carbon::now();
            $time = $end_time->diffInSeconds($start_time);
            $this->info($products_count . " product updated successfully. Process took " . $time . ' seconds');
        } catch (Exception $e) {
            $this->info('Command failed ' . $e);
        }
    }
}
