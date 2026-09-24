<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Upload;
use Exception;
use Illuminate\Support\Str;

class ImportCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:categories';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Categories from API';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }


    public function downloadThumbnail($url)
    {
        try {
            if ($url != null) {
                $upload = new Upload;
                $upload->external_link = $url;
                $upload->type = 'image';
                $upload->save();

                return $upload->id;
            } else {
                return null;
            }
        } catch (\Exception $e) {
            $this->info('Command was not successful');
        }
        return null;
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
            // Create the UnCategorized category if it does not exist
            $uncategorized =  Category::where('name', 'Uncategorized')->exists();
            if (! $uncategorized) {
                $uncategory = new Category;
                $uncategory->serial = null;
                $uncategory->name = "Uncategorized";
                $uncategory->banner = null;
                $uncategory->cover_image = null;
                $uncategory->slug = 'Uncategorized' . '-' . Str::random(5);
                $uncategory->featured = 1;
                $uncategory->save();
            }

            $endpoint = 'https://stores.otajer.com//api/rest/listcats/' . config('services.otajer.token');
            $response = file_get_contents($endpoint);

            $categoriesData = json_decode($response, true);
            $number_of_categories = count($categoriesData['Cats']);

            foreach ($categoriesData['Cats'] as $categoryData) {

                if (isset($categoryData['MobilePic'])) {
                    $image_upload_id = $this->downloadThumbnail(($categoryData['MobilePic']));
                } else {
                    $image_upload_id = null;
                }


                $category = Category::where('serial', $categoryData['Serial'])->first();
                if ($category) {
                    $category->serial = $categoryData['Serial'];
                    $category->name = $categoryData['enCatName'];
                    $category->banner = $category->banner;
                    $category->cover_image = $category->cover_image;
                    // $category->slug = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $categoryData['enCatName'])).'-'.Str::random(5);
                    $category->featured = 1;
                    $category->save();


                    CategoryTranslation::updateOrCreate(
                        ['category_id' => $category->id, 'lang' => 'en'],
                        ['name' => $categoryData['enCatName'] ?? 'Uncategorized']
                    );

                    CategoryTranslation::updateOrCreate(
                        ['category_id' => $category->id, 'lang' => 'sa'],
                        ['name' => $categoryData['enCatName'] ?? 'Uncategorized']
                    );
                } else {
                    $category = new Category;
                    $category->serial = $categoryData['Serial'];
                    $category->name = $categoryData['enCatName'];
                    $category->banner = $image_upload_id;
                    $category->cover_image = $image_upload_id;
                    $category->slug = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $categoryData['enCatName'])) . '-' . Str::random(5);
                    $category->featured = 1;
                    $category->save();


                    CategoryTranslation::create([
                        'category_id' => $category->id,
                        'name' => $categoryData['enCatName'],
                        'lang' => 'en'
                    ]);

                    CategoryTranslation::create([
                        'category_id' => $category->id,
                        'name' => $categoryData['arCatName'],
                        'lang' => 'sa'
                    ]);
                }
            }
            $end_time = Carbon::now();
            $time = $end_time->diffInSeconds($start_time);
            $this->info($number_of_categories . ' categories fetched and added successfully. It took ' . $time . ' seconds');
        } catch (\Exception $e) {
            $this->info('Command was not successful' . $e);
        }
    }
}
