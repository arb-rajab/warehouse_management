<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestMatID extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:mat';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This is a testing command to see if there are any duplicates mat_ids';

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




        // Fetch products from the API
        $endpoint = 'https://stores.otajer.com/api/rest/listfullproducts/' . config('services.otajer.token') . '/-1';
        $response = Http::get($endpoint);
        $allProducts = $response->json();

        // Check for duplicate Mat_ID
        $duplicates = [];
        $seenMatIds = [];

        foreach ($allProducts['Products'] as $product) {
            $matId = $product['Mat_ID'];

            // If Mat_ID is already seen, it's a duplicate
            if (in_array($matId, $seenMatIds)) {
                $duplicates[] = $product['Mat_ID'];
            } else {
                // Mark Mat_ID as seen
                $seenMatIds[] = $matId;
            }
        }

        // Display or log duplicates
        if (count($duplicates) > 0) {
            echo count($duplicates);
        } else {
            echo "No products with duplicate Mat_ID found.\n";
        }
    }
}
