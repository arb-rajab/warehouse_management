<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Upload;
use Illuminate\Support\Facades\Http;
use Intervention\Image\ImageManagerStatic as Image;

class FetchExternalImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fetch:otajer-photos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch images from external links and store them locally';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {

        $uploads = Upload::where('file_name', null)->where('external_link', '!=', null)->get();

        echo $uploads->count();

        foreach ($uploads as $upload) {
            $this->info("Upload ID {$upload->id} processing...");
            $exists = Product::whereJsonContains('photos', $upload->id)
                ->orWhereJsonContains('old_photo', $upload->id)
                ->orWhereJsonContains('thumbnail_img', $upload->id)
                ->exists();

            if ($exists) {
                $this->info("Upload ID {$upload->id} exists in a product.");
                if ($upload->external_link) {
                    try {

                        $response = Http::get($upload->external_link);

                        if ($response->successful()) {
                            $imageContent = $response->body();
                            $imageName = basename($upload->external_link);
                            $storagePath = 'uploads/otajer/' . $imageName;

                            // Decrease the quality of the image to less than 50%
                            $image = Image::make($imageContent)->encode('jpg', 50);

                            Storage::put($storagePath, $image);

                            // Update the file_name with the new storage path
                            if (!$upload->file_name) {
                                $upload->file_name = $storagePath;
                                $upload->external_link = null;
                                $upload->save();
                            }

                            $this->info("Successfully fetched and stored: {$upload->external_link}");
                        } else {
                            $this->error("Failed to fetch image from: {$upload->external_link}");
                        }
                    } catch (\Exception $e) {
                        $this->error("Error fetching image from: {$upload->external_link} - {$e->getMessage()}");
                    }
                }
                $this->info("Upload ID {$upload->id} does not exist in any product.");
            } else{
                $upload->delete();
                $this->info("Upload record deleted.");

            }
        }




        // ====================================================
        // $batchSize = 1000;

        // // Process Uploads in batches
        // Upload::chunk($batchSize, function ($uploads) {
        //     // Collect all upload IDs
        //     $uploadIds = $uploads->pluck('id')->toArray();

        //     // Find products that contain any of the upload IDs in JSON fields
        //     $products = Product::where(function ($query) use ($uploadIds) {
        //         $query->where(function ($query) use ($uploadIds) {
        //             foreach ($uploadIds as $id) {
        //                 $query->orWhereJsonContains('photos', $id);
        //             }
        //         })
        //             ->orWhere(function ($query) use ($uploadIds) {
        //                 foreach ($uploadIds as $id) {
        //                     $query->orWhereJsonContains('old_photo', $id);
        //                 }
        //             })
        //             ->orWhere(function ($query) use ($uploadIds) {
        //                 foreach ($uploadIds as $id) {
        //                     $query->orWhereJsonContains('thumbnail_img', $id);
        //                 }
        //             });
        //     })->get();

        //     // Extract the found upload IDs from the products
        //     $foundUploadIds = [];
        //     foreach ($products as $product) {
        //         foreach ($uploadIds as $id) {
        //             if (
        //                 in_array($id, json_decode($product->photos, true)) ||
        //                 in_array($id, json_decode($product->old_photo, true)) ||
        //                 in_array($id, json_decode($product->thumbnail_img, true))
        //             ) {
        //                 $foundUploadIds[] = $id;
        //             }
        //         }
        //     }

        //     // Update the uploads based on found IDs
        //     foreach ($uploads as $upload) {
        //         if (in_array($upload->id, $foundUploadIds)) {
        //             // Perform necessary action, e.g., update Upload model
        //             echo "Upload ID {$upload->id} exists in a product.\n";
        //             $uploads = Upload::all();

        //             foreach ($uploads as $upload) {
        //                 $exists = Product::whereJsonContains('photos', $upload->id)
        //                     ->orWhereJsonContains('old_photo', $upload->id)
        //                     ->orWhereJsonContains('thumbnail_img', $upload->id)
        //                     ->exists();

        //                 if ($exists) {
        //                     $this->info('Exists');
        //                     if ($upload->external_link) {
        //                         try {

        //                             $response = Http::get($upload->external_link);

        //                             if ($response->successful()) {
        //                                 $imageContent = $response->body();
        //                                 $imageName = basename($upload->external_link);
        //                                 $storagePath = 'uploads/otajer/' . $imageName;

        //                                 Storage::put($storagePath, $imageContent);

        //                                 // Update the file_name with the new storage path
        //                                 if (!$upload->file_name) {
        //                                     $upload->file_name = $storagePath;
        //                                     $upload->save();
        //                                 }

        //                                 $this->info("Successfully fetched and stored: {$upload->external_link}");
        //                             } else {
        //                                 $this->error("Failed to fetch image from: {$upload->external_link}");
        //                             }
        //                         } catch (\Exception $e) {
        //                             $this->error("Error fetching image from: {$upload->external_link} - {$e->getMessage()}");
        //                         }
        //                     }
        //                 }
        //             }

        //             $this->info('Completed fetching external images.');
        //         } else {
        //             echo "Upload ID {$upload->id} does not exist in any product.\n";
        //         }
        //     }
        // });
    }
}
