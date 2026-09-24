<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CronJobs;
use App\Models\Product;
use App\Models\ProductStock;
use Carbon\Carbon;
use Exception;
use App\Models\ProductTax;
use App\Models\Tax;
use App\Models\ProductTranslation;
use Illuminate\Support\Str;
use App\Models\Upload;
use App\Models\Category;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\ProductVariation;
use App\Models\ProductBarcode;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use App\Models\Cart;


class CheckForCommands extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:commands';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'this cron job runs every minute to check if there is a products fetching command in the cron_jobs table';

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

        // If there is already a running fetching-products-by-HOSTINGER  command, exit to prevent duplicates products
        $cron_job_exists = CronJobs::where('command', 'fetching-products-by-hostinger')->where('status', 'pending')->orWhere('status', 'running')->first();
        if ($cron_job_exists) {
            $date = Carbon::now();
            $date = $date->toRfc7231String();
            $this->error('Products fetching-by-hostinger Command was running, Command_Id: ' . $cron_job_exists->id . ", On " . $date);
            return true;
        }

        // If not check if there is a request for fetching-products-by-ADMIN:
        $adminCronJob = CronJobs::where('command', 'fetching-products-by-admin')->where('status', 'pending')->first();
        if (! $adminCronJob) {
            return true;
        }

        try {

            $adminCronJob->status = 'running';
            $adminCronJob->save();
            //          $this->call('import:categories');
            $start_time = Carbon::now();
            $this->call('import:all-products-v2');
            $products_count = 0;

            //             $endpoint = 'https://stores.otajer.com/api/rest/listfullproducts/'. config('services.otajer.token'). '/-1';
            //             $response = Http::get($endpoint);
            //             $allProducts = json_decode($response, true);
            //             $products_count += count($allProducts['Products']);
            //             $admin_id = User::where('user_type', 'admin')->where('raizer_admin', null)->value('id');

            //             // if(! Attribute::where('name', 'Variations')->first()){
            //             //     Attribute::create([
            //             //         'name' => 'Variations',
            //             //     ]);
            //             // }
            //             if(! $typeOfSaleAttribute = Attribute::where('name', 'Type Of Sale')->first()){
            //                    $typeOfSaleAttribute =  Attribute::create([
            //                         'name' => 'Type Of Sale',
            //                     ]);
            //             }
            //             // firstOrNew is a sensational method
            //             AttributeValue::firstOrCreate([
            //                 "attribute_id" => $typeOfSaleAttribute->id,
            //                 "value" => 'Per Box',
            //             ]);

            //             AttributeValue::firstOrCreate([
            //                 "attribute_id" => $typeOfSaleAttribute->id,
            //                 "value" => 'Per Piece',
            //             ]);


            //             foreach($allProducts['Products'] as $product)
            //             {
            //                 $selectedProduct = Product::where('serial', $product['Serial'])->first();
            //                 if($selectedProduct) // if the product already exists, update it.
            //                 {
            //                     // begin: image processing
            //                      // updating the image
            //                     if(isset($product['Image'])){
            //                         $oldPhoto = Upload::where('id', $selectedProduct->thumbnail_img )->first();
            //                         if($oldPhoto){
            //                             if($oldPhoto->external_link != $product['Image']){ // if the image(url) has been changed
            //                                 $oldPhoto->forceDelete();
            //                                 $image_upload_id = $this->downloadThumbnail(($product['Image']));
            //                             }
            //                             else{
            //                                 $image_upload_id = $oldPhoto->id;
            //                             }
            //                         }
            //                         else{ // if the product did not have a photo, but now it has
            //                             $image_upload_id = $this->downloadThumbnail(($product['Image']));
            //                         }
            //                     }
            //                     else{ // if the product does not have a photo initially, or the api admin deleted the photo
            //                         $image_upload_id = null;
            //                     }
            //                      // end: image processing


            //                     //Begin: Product Attributes (PerBox and PerPiece)
            //                     $types_of_sale = Attribute::where('name', 'Type Of Sale')->first(); // per box or per piece
            //                     $variant_product = true;
            //                     // Initialize the choice_options array
            //                     $choice_options = [];
            //                     $choice_options[] = [
            //                         "attribute_id" => "$types_of_sale->id",
            //                         "values" => ["Per Box", "Per Piece"]
            //                     ];

            //                     $choice_options = json_encode($choice_options);
            //                     $attributes = json_encode(["$types_of_sale->id"]);
            //                     //End: Product Attributes (PerBox and PerPiece)


            //                     //Begin: Updating the product
            //                     //
            //                     $selectedProduct->update([
            //                         'serial' => $product['Serial'],
            //                         'name' => $product['enName'],
            //                         'ar_name' => $product['arName'],
            //                         'unit_price' => $product['Price'],
            //                         'api_unitId' => $product['UnitID'],
            //                         'api_unit_name' => $product['UnitEnName'],
            //                         'deleted_from_api' => false,
            //                         'unit' => $product['UnitEnName'],
            //                         'tax' => $product['TaxPercent'],
            //                         'tax_type' => 'percent',
            //                         'thumbnail_img' =>  $image_upload_id,
            //                         'photos' => $image_upload_id,
            //                         'description' => $product['Desc'],
            //                         'variant_product' => $variant_product,
            //                         'category_id' => $product['enCatName'] ? Category::where('name', $product['enCatName'])->first()->id : Category::where('name', 'Uncategorized')->first()->id,
            //                         'mat_id' => $product['Mat_ID'],
            //                         'variations' => json_encode($product['Variations'], JSON_UNESCAPED_UNICODE),
            //                         'from_api' => 1,
            //                         'published' => $product['Active'] ? ($product['enCatName'] ? true : false) : false,
            //                         'unit_equal' => $product['Unit_Equal'],
            //                         'choice_options' => $choice_options,
            //                         'attributes' => $attributes,
            //                         'colors' => '[]',
            //                         'stock_visibility_state' => 'text',
            //                         'cash_on_delivery' => 1
            //                   //      'sku' => $product['enName'] . '-' . $product['Unit_Equal'] . '-' . uniqid(),
            //                     ]);
            //                     //End: Updating the product

            //                     //Begin: Updating Product Stocks
            //                     $productStocks = ProductStock::where('product_id', $selectedProduct->id)->get();
            //                     foreach($productStocks as $productStock){
            //                         if($selectedProduct->for_sale == 0 || $selectedProduct->published == 0){
            //                             $quantity = 0;

            //                         }
            //                         else{
            //                             $quantity = 1000;
            //                         }
            //                         if(Str::contains($productStock->variant, 'PerBox')){
            //                                 $productStock->update([
            //                                     'price' => $product['Price'],
            //                                     'qty' => $quantity,
            //                                     //'sku' => $product['enName'] . '-' . $product['Unit_Equal'] . '-' . uniqid(),
            //                             ]);
            //                             }
            //                         else{ // update the PerPiece
            //                                 $productStock->update([
            //                                     'price' => ($product['Price'] / $product['Unit_Equal']),
            //                                     'qty' => $quantity,
            //                                     //'sku' => $product['enName'] . '-' . $product['Unit_Equal'] . '-' . uniqid(),
            //                                 ]);
            //                             }
            //                         }
            //                     //End: Updating Product Stocks


            //                     //Begin: Updating Product Types (green, white, small ..etc)
            //                     if (isset($product['Variations'])) {
            //                         $old_variations = ProductVariation::where('product_id', $selectedProduct->id)->get();
            //                         foreach($old_variations as $oldVariation){
            //                             $oldVariation->delete();
            //                         }


            //                         // Loop through each variation and store the "VName" values
            //                         $variations = $product['Variations'];
            //                             foreach($variations as $var){
            //                                 ProductVariation::create([
            //                                     'product_id' => $selectedProduct->id,
            //                                     'product_serial' => $selectedProduct->serial,
            //                                     'name' => $var['VName']
            //                                 ]);
            //                             }
            //                     }
            //                     //End: Updating Product Types



            //                     //Begin: Updating Product Taxes and Translations
            //                     //  $productTax = ProductTax::where('product_id', $selectedProduct->id)->first();
            //                     $productArabicTranslation = ProductTranslation::where('product_id', $selectedProduct->id)->where('lang', 'sa')->first();
            //                     $productEnglishTranslation = ProductTranslation::where('product_id', $selectedProduct->id)->where('lang', 'en')->first();
            //                     // $productTax->update([
            //                     //     'tax' => $product['TaxPercent']
            //                     // ]);
            //                     $productArabicTranslation->update([ // arabic
            //                         'name' => $product['arName'],
            //                         'unit' => $product['UnitArName'],
            //                         'description' => $product['Desc']
            //                     ]);

            //                     $productEnglishTranslation->update([ // english
            //                         'name' => $product['enName'],
            //                         'unit' => $product['UnitEnName'],
            //                         'description' => $product['Desc']
            //                     ]);
            //                     //End: Updating Product Taxes and Translations


            //                     //Begin: Updating Barcode
            //                     if($product['Barcodes'] != null){
            //                         $barcodes = $product['Barcodes'];
            //                         foreach($barcodes as $barcode){
            //                             $barcodeValue = $barcode['Barcode'];
            //                             if(! ProductBarcode::where('barcode', $barcodeValue)->exists()){
            //                                 ProductBarcode::create([
            //                                     'product_id' => $selectedProduct->id,
            //                                     'barcode'   => $barcodeValue
            //                                 ]);
            //                             }
            //                         }
            //                     }
            //                     //End: Updating Barcode

            //                     // Store the material_id as a barcode - Task:BS-152
            //                     if(! ProductBarcode::where('barcode', $product['Mat_ID'])->exists()){
            //                         ProductBarcode::create([
            //                             'product_id' => $selectedProduct->id,
            //                             'barcode'   => $product['Mat_ID']
            //                         ]);
            //                     }
            //                     // $this->info('Product: '. $selectedProduct->serial .' Updated Successfully');

            //                         }

            // /*******************************************  If the product does not exist ********************************************/

            //                 else { // if the product does not exist, create it.
            //                         $endpoint = 'https://stores.otajer.com/api/rest/productdetails/'.$product['Serial'].'/' . config('services.otajer.token');
            //                         $response = Http::get($endpoint);
            //                         $productDetails = json_decode($response, true);
            //                         $productDetails = $productDetails['ProductDetails'];


            //                     //Begin: Generating a slug for the product
            //                         $slug = Str::slug($productDetails['enName']);
            //                         $same_slug_count = Product::where('slug', 'LIKE', $slug . '%')->count();
            //                         $slug_suffix = $same_slug_count ? '-' . $same_slug_count + 1 : '';
            //                         $slug .= $slug_suffix;
            //                     //End: Generating a slug for the product

            //                     // uploading the image
            //                     if(isset($productDetails['images'][0]['FileName'])){
            //                         $image_upload_id = $this->downloadThumbnail(($productDetails['images'][0]['FileName']));
            //                     }
            //                     else{
            //                         $image_upload_id = null;
            //                     }


            //                     //Begin: Attributes (PerBox and PerPiece)
            //                     $types_of_sale = Attribute::where('name', 'Type Of Sale')->first(); // per box or per piece
            //                     $variant_product = true; // all products are variant!
            //                     $choice_options = []; // init
            //                     $choice_options[] = [
            //                         "attribute_id" => "$types_of_sale->id",
            //                         "values" => ["Per Box", "Per Piece"],
            //                     ];
            //                     $choice_options = json_encode($choice_options, JSON_UNESCAPED_UNICODE);
            //                     $attributes = json_encode(["$types_of_sale->id"]);
            //                     //End: Attributes (PerBox and PerPiece)

            //                     //Begin: Product Creation
            //                       $productId =  Product::create([
            //                             'serial' => $productDetails['Serial'],
            //                             'name' => $productDetails['enName'],
            //                             'ar_name' => $productDetails['arName'],
            //                             'unit_price' => $productDetails['Price'],
            //                             'category_id' => $productDetails['enCatName'] ? Category::where('name', $productDetails['enCatName'])->first()->id : Category::where('name', 'Uncategorized')->first()->id,
            //                             'api_unitId' => $productDetails['UnitID'],
            //                             'api_unit_name' => $productDetails['UnitEnName'],

            //                             'unit' => $productDetails['UnitEnName'],
            //                             'tax' => $productDetails['TaxPercent'],
            //                             'tax_type' => 'percent',
            //                         //  'image' => isset($productDetails['images'][0]['FileName'])? $productDetails['images'][0]['FileName'] : null,
            //                             'description' => $productDetails['Desc'],
            //                             'variant_product' => $variant_product,
            //                             'published' => $productDetails['Active'] ? ($productDetails['enCatName'] ? true : false) : false,
            //                             'for_sale' => $productDetails['enCatName'] ? 1 : 0,
            //                             'mat_id' => $productDetails['Mat_ID'],
            //                         //    'mat_barcode_guid' => isset($productDetails['barcodes'][0]["Mat_BarCode_Guid"])? $productDetails['barcodes'][0]["Mat_BarCode_Guid"] : null,
            //                             'variations' => json_encode($productDetails['variations'], JSON_UNESCAPED_UNICODE),
            //                             'from_api' => 1,
            //                             'user_id' => $admin_id,
            //                             'slug' => $slug,
            //                             'thumbnail_img' => $image_upload_id,
            //                             'photos' => $image_upload_id,
            //                             'unit_equal' => $productDetails['Unit_Equal'],
            //                             'choice_options' => $choice_options,
            //                             'attributes' => $attributes,
            //                            'colors' => '[]',
            //                             'cash_on_delivery' => 1,
            //                             'stock_visibility_state' => 'text'
            //                             /*
            //                            'shipping_type' => 'free',
            //                             'discount' => 0.00,
            //                             'discount_type' => 'amount',
            //                             'low_stock_quantity' => 1*/

            //                         ]);
            //                     //End: Product Creation


            //                         //Begin: Product Types (small, large..etc)
            //                         $variations = $productDetails['variations'];
            //                             foreach($variations as $var){
            //                                 ProductVariation::create([
            //                                     'product_id' => $productId->id,
            //                                     'product_serial' => $productId->serial,
            //                                     'name' => $var['VName']
            //                                 ]);
            //                             }
            //                         //End: Product Types

            //                         //Begin: Product Stocks
            //                         if($productId->for_sale == 0 || $productId->published == 0){
            //                             $quantity = 0;

            //                         }
            //                         else{
            //                             $quantity = 1000;
            //                         }

            //                         //  create only 2 product_stocks records for perBox and perPiece
            //                             ProductStock::create([
            //                             'product_id' => $productId->id,
            //                             'qty' => $quantity,
            //                             'price' => $productId->unit_price,
            //                           //  'sku' => $product['enName'] . '-' . $product['Unit_Equal'] . '-' . uniqid(),
            //                             'variant' => 'PerBox'
            //                             ]);

            //                             ProductStock::create([
            //                                 'product_id' => $productId->id,
            //                                 'qty' => $quantity,
            //                                 'price' => $productId->unit_price /  $product['Unit_Equal'],
            //                          //       'sku' => $product['enName'] . '-' . $product['Unit_Equal'] . '-' . uniqid(),
            //                                 'variant' => 'PerPiece'
            //                                 ]);
            //                         //End: Product Stocks



            //                         //Begin: Tax
            //                         // $product_tax = new ProductTax();
            //                         // $product_tax->tax_id = Tax::first()->id;
            //                         // $product_tax->product_id = $productId->id;
            //                         // $product_tax->tax = $productId->tax;
            //                         // $product_tax->tax_type = 'percent';
            //                         // $product_tax->save();
            //                         //End: Tax


            //                         //Begin: Translation
            //                         ProductTranslation::create([
            //                             'lang' => 'sa',
            //                             'name' => $productDetails['arName'],
            //                             'unit' => $productDetails['UnitArName'],
            //                             'description' => $productId->description,
            //                             'product_id' => $productId->id
            //                         ]);

            //                         ProductTranslation::create([
            //                             'lang' => 'en',
            //                             'name' => $productDetails['enName'],
            //                             'unit' => $productDetails['UnitEnName'],
            //                             'description' => $productId->description,
            //                             'product_id' => $productId->id
            //                            ]);
            //                         //End: Translation


            //                         //Begin: Barcode
            //                         $barcodes = $productDetails['VariationFreeBarcodes'];
            //                         foreach($barcodes as $barcode){
            //                             if(! ProductBarcode::where('barcode', $barcode)->exists()){
            //                                 ProductBarcode::create([
            //                                     'product_id' => $productId->id,
            //                                     'barcode'   => $barcode
            //                                 ]);
            //                             }
            //                         }
            //                         //End: Barcode

            //                     // Store the material_id as a barcode - Task:BS-152
            //                     if(! ProductBarcode::where('barcode', $productDetails['Mat_ID'])->exists()){
            //                         ProductBarcode::create([
            //                             'product_id' => $productId->id,
            //                             'barcode'   => $productDetails['Mat_ID']
            //                         ]);
            //                     }
            //                     //uncomment this line in local environment
            //                     // $this->info('Product: '. $productId->serial .' Created Successfully');
            //                 }
            //             }



            //           // Extract serials
            //           $serials = array_column($allProducts['Products'], 'Serial');

            //           // Explicitly cast the serial values to varchar
            //           $serials = array_map('strval', $serials);

            //           $deletedProductsCount = 0;

            //           // Get products to delete
            //           $productsToDelete = Product::whereNotIn('serial', $serials)->get();

            //           // Loop through products and delete related records
            //           foreach ($productsToDelete as $deleted_product) {


            //             $deleted_product->published = false;
            //             $deleted_product->for_sale = false;
            //             $deleted_product->featured = false;
            //             $deleted_product->deleted_from_api = true;
            //             $deleted_product->save();
            //               // Delete related records in child tables
            //             //   $product->product_translations()->delete();
            //             //   $product->stocks()->delete();
            //             //   $product->taxes()->delete();
            //             //   $product->variations()->delete();
            //             //   $product->barcodes()->delete();

            //             //   Cart::where('product_id', $product->id)->delete();

            //             //   // Delete the parent product
            //             //   $product->delete();

            //               $deletedProductsCount++;
            //           }


            //   $this->info('All products that does not exist in the API response has been deActivated (' . $deletedProductsCount . ' products).');



            $end_time = Carbon::now();
            $time = $end_time->diffInSeconds($start_time);

            $adminCronJob->status = 'Completed';
            $adminCronJob->output = $products_count . " product fetched/updated successfully. Process took " . $time . ' seconds' . 'All products that does not exist in the API response has been deActivated.';
            $adminCronJob->save();
            $adminCronJob->delete(); // this is just a soft delete for tracking the timestamps.

            $this->info($products_count . " product fetched/updated successfully. Process took " . $time . ' seconds');
        } catch (Exception $e) {
            $adminCronJob->output = 'Command failed ' . $e->getMessage();
            $adminCronJob->status = 'Failed';
            $adminCronJob->save();
            $adminCronJob->delete(); // in case of any error.
            $this->error('Command failed ' . $e->getMessage());
        }
    }
}
