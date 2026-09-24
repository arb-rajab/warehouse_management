<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\BrandTranslation;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Str;

class ImportBrands extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:brands';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Brands from API';

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
$number_of_brands = 0;
            // Iterate through Filters array
            foreach ($filters['Filters'] as $filter) {
                if ($filter['Mat_Class_ID'] == 4) {
                    $brandsData = $filter['FilterDetails'];
                    $number_of_brands = count($brandsData);

                    foreach ($brandsData as $brandData) {
                        $brand = Brand::where('serial', $brandData['Mat_Class_Detail_ID'])->first();
                        if ($brand) {
                            $brand->serial = $brandData['Mat_Class_Detail_ID'];
                            $brand->name = $brandData['Mat_Class_Detail_Name'];

                            $brand->save();


                            $englishBrandTranslation = BrandTranslation::where('brand_id', $brand->id)->where('lang', 'en')->first();
                            $englishBrandTranslation->name = $brandData['Mat_Class_Detail_Lat_Name'];
                            $englishBrandTranslation->lang = 'en';
                            $englishBrandTranslation->save();

                            $arabicBrandTranslation = BrandTranslation::where('brand_id', $brand->id)->where('lang', 'sa')->first();
                            $arabicBrandTranslation->name = $brandData['Mat_Class_Detail_Name'];
                            $arabicBrandTranslation->lang = 'sa';
                            $arabicBrandTranslation->save();
                        } else {
                            $brand = new Brand;
                            $brand->serial = $brandData['Mat_Class_Detail_ID'];
                            $brand->name = $brandData['Mat_Class_Detail_Name'];
                            $brand->slug = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $brandData['Mat_Class_Detail_Name'])) . '-' . Str::random(5);
                            $brand->save();


                            BrandTranslation::create([
                                'brand_id' => $brand->id,
                                'name' => $brandData['Mat_Class_Detail_Lat_Name'],
                                'lang' => 'en'
                            ]);

                            BrandTranslation::create([
                                'brand_id' => $brand->id,
                                'name' => $brandData['Mat_Class_Detail_Name'],
                                'lang' => 'sa'
                            ]);
                        }
                    }
                }
            }
            $end_time = Carbon::now();
            $time = $end_time->diffInSeconds($start_time);
            $this->info($number_of_brands . ' brands fetched and added successfully. It took ' . $time . ' seconds');
        } catch (\Exception $e) {
            $this->info('Command was not successful' . $e);
        }
    }
}
