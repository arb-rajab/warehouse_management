<?php

namespace App\Console\Commands;

use App\Models\Product;
use Carbon\Carbon;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

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
    public function handle(): int
    {
        try {
            $start_time = Carbon::now();
            $products_count = 0;
            $endpoint = config('services.otajer.url') . '/api/rest/newandofferproducts/' . config('services.otajer.token');
            $response = Http::timeout(30)->throw()->get($endpoint)->body();
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
            $time = (int) $end_time->diffInSeconds($start_time, true);
            $this->info($products_count . " product updated successfully. Process took " . $time . ' seconds');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('Command failed '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
