<?php

namespace App\Console\Commands;

use App\Models\ProductCollection;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Exception;

class ImportCollections extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:collections';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import collections from API';

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

            $endpoint = 'https://stores.otajer.com/api/rest/listproductfilters/' . config('services.otajer.token');
            $response = file_get_contents($endpoint);

            $filters = json_decode($response, true);
            $number_of_collections = 0;

            // Iterate through Filters array
            foreach ($filters['Filters'] as $filter) {
                if ($filter['Mat_Class_ID'] == 2) {
                    $collectionsData = $filter['FilterDetails'];
                    $number_of_collections = count($collectionsData);

                    foreach ($collectionsData as $collectionData) {
                        $productCollection = ProductCollection::where('serial', $collectionData['Mat_Class_Detail_ID'])->first();
                        if ($productCollection) {
                            $productCollection->serial = $collectionData['Mat_Class_Detail_ID'];
                            $productCollection->name = $collectionData['Mat_Class_Detail_Name'];

                            preg_match_all('!\d+!', $collectionData['Mat_Class_Detail_Name'], $matches);
                            $productCollection->amount = $matches[0][0] ?? 0;

                            $productCollection->save();
                        } else {
                            $productCollection = new productCollection;
                            $productCollection->serial = $collectionData['Mat_Class_Detail_ID'];
                            $productCollection->name = $collectionData['Mat_Class_Detail_Name'];

                            preg_match_all('!\d+!', $collectionData['Mat_Class_Detail_Name'], $matches);
                            $productCollection->amount = $matches[0][0] ?? 0;

                            $productCollection->save();
                        }
                    }
                }
            }

            // creating default product collection
            ProductCollection::firstOrCreate(
                ['amount' => 0],
                [
                    'name' => 'default collection',
                    'serial' => 0,
                    'amount' => 0
                ],
            );

            $end_time = Carbon::now();
            $time = $end_time->diffInSeconds($start_time);
            $this->info($number_of_collections . ' collections fetched and added successfully. It took ' . $time . ' seconds');
        } catch (\Exception $e) {
            $this->info('Command was not successful' . $e);
        }
    }
}
