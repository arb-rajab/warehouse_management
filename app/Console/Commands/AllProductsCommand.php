<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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
use App\Models\User;

class AllProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:all-products';




    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch all products';

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
            if($url != null){
                $upload = new Upload;
                $upload->external_link = $url;
                $upload->type = 'image';
                $upload->save();

                return $upload->id;
            }
            else{
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
        try {
            $start_time = Carbon::now();
            $products_count = 0;

            $endpoint = 'https://stores.otajer.com/api/rest/listfullproducts/7D0F1431A09796822632E2AB19DC0231/-1';
            $response = file_get_contents($endpoint);
            $allProducts = json_decode($response, true);
            $products_count += count($allProducts['Products']);
            $admin_id = User::where('user_type', 'admin')->where('raizer_admin', null)->value('id');

            if(! Attribute::where('name', 'Variations')->first()){
                Attribute::create([
                    'name' => 'Variations',
                ]);
            }
            if(! $typeOfSaleAttribute = Attribute::where('name', 'Type Of Sale')->first()){
                   $typeOfSaleAttribute =  Attribute::create([
                        'name' => 'Type Of Sale',
                    ]);
            }
            // firstOrNew is a sensational method
            AttributeValue::firstOrCreate([
                "attribute_id" => $typeOfSaleAttribute->id,
                "value" => 'Per Box',
            ]);

            AttributeValue::firstOrCreate([
                "attribute_id" => $typeOfSaleAttribute->id,
                "value" => 'Per Piece',
            ]);


            foreach($allProducts['Products'] as $product)
            {
                $selectedProduct = Product::where('serial', $product['Serial'])->first();
                if($selectedProduct) // if the product already exists, update it.
                {
                     // updating the image
                    if(isset($product['Image'])){
                        $oldPhoto = Upload::where('id', $selectedProduct->thumbnail_img )->first();
                        if($oldPhoto){
                            if($oldPhoto->external_link != $product['Image']){ // if the image(url) has been changed
                                $oldPhoto->forceDelete();
                                $image_upload_id = $this->downloadThumbnail(($product['Image']));
                            }
                            else{
                                $image_upload_id = $oldPhoto->id;
                            }
                        }
                        else{ // if the product did not have a photo, but now it has
                            $image_upload_id = $this->downloadThumbnail(($product['Image']));
                        }
                    }
                    else{ // if the product does not have a photo initially, or the api admin deleted the photo
                        $image_upload_id = null;
                    }


                    $variation_attributes =  Attribute::where('name', 'Variations')->first();
                    $types_of_sale = Attribute::where('name', 'Type Of Sale')->first(); // per box or per piece
                    $variant_product = true;
                    // Initialize the choice_options array
                    $choice_options = [];
                    if($product['Variations'] != null){ // if its a variant product
                        $attributes = json_encode(["$variation_attributes->id", "$types_of_sale->id"]);
                        $variations = json_decode($product['Variations'], true);

                        foreach($variations as $var){ // refactor this
                        $attribute_value = new AttributeValue;
                        $attribute_value->attribute_id = $variation_attributes->id;
                        $attribute_value->value = $var['VName'];
                        $attribute_value->save();
                        }


                    // Define the static part of the attribute information for "Per Box" and "Per Piece.", right now all products can sold per box or per piece
                    $choice_options[] = [
                        "attribute_id" => "$types_of_sale->id",
                        "values" => ["Per Box", "Per Piece"]
                    ];
                    // Initialize an array to hold the "VName" values
                    $attributeValues = [];

                    // Loop through variations to collect "VName" values
                    foreach ($variations as $var) {
                        $attributeValues[] = $var['VName'];
                    }
                    // Add the dynamic part of the attribute information
                    $choice_options[] = [
                        "attribute_id" =>  "$variation_attributes->id",
                        "values" => $attributeValues,
                    ];
                    // Convert the final array to JSON
                    $choice_options = json_encode($choice_options, JSON_UNESCAPED_UNICODE);
            }
            else{ // if there are no variations
                $choice_options[] = [
                    "attribute_id" => "$types_of_sale->id",
                    "values" => ["Per Box", "Per Piece"]
                ];

                $choice_options = json_encode($choice_options);

                $attributes = json_encode(["$types_of_sale->id"]);
            }
                    $selectedProduct->update([
                        'serial' => $product['Serial'],
                        'name' => $product['enName'],
                        'ar_name' => $product['arName'],
                        'unit_price' => $product['Price'],
                       // 'unit_id' => $product['UnitID'],
                       'unit' => $product['UnitEnName'],
                        'tax' => $product['TaxPercent'],
                       'tax_type' => 'percent',
                       'thumbnail_img' =>  $image_upload_id,
                       'photos' => $image_upload_id,
                        'description' => $product['Desc'],
                        'variant_product' => $variant_product,
                        'category_id' => $product['enCatName'] ? Category::where('name', $product['enCatName'])->first()->id : Category::where('name', 'Uncategorized')->first()->id,
                        'mat_id' => $product['Mat_ID'],
                        'variations' => json_encode($product['Variations'], JSON_UNESCAPED_UNICODE),
                        'from_api' => 1,
                        'published' => $product['enCatName'] ? 1 : 0,
                        'unit_equal' => $product['Unit_Equal'],
                        'choice_options' => $choice_options,
                        'attributes' => $attributes,
                        'colors' => '[]',
                        'cash_on_delivery' => 1
                  //      'sku' => $product['enName'] . '-' . $product['Unit_Equal'] . '-' . uniqid(),
                    ]);

                    // update the product stocks, tax, and product translations
                    $productStocks = ProductStock::where('product_id', $selectedProduct->id)->get();
                    foreach($productStocks as $productStock){
                        if(Str::contains($productStock->variant, 'PerBox')){
                                $productStock->update([
                                    'price' => $product['Price'],
                                    'qty' => $product['enCatName'] ? 1000 : 0,
                                    //'sku' => $product['enName'] . '-' . $product['Unit_Equal'] . '-' . uniqid(),
                            ]);
                            }
                        else{
                                $productStock->update([
                                    'price' => ($product['Price'] / $product['Unit_Equal']),
                                    'qty' => $product['enCatName'] ? 1000 : 0,
                                    //'sku' => $product['enName'] . '-' . $product['Unit_Equal'] . '-' . uniqid(),
                                ]);
                            }
                        }





                  //  $productTax = ProductTax::where('product_id', $selectedProduct->id)->first();
                    $productArabicTranslation = ProductTranslation::where('product_id', $selectedProduct->id)->where('lang', 'sa')->first();
                    $productEnglishTranslation = ProductTranslation::where('product_id', $selectedProduct->id)->where('lang', 'en')->first();




                    // $productTax->update([
                    //     'tax' => $product['TaxPercent']
                    // ]);

                    $productArabicTranslation->update([
                        'name' => $product['arName'],
                        'unit' => $product['UnitArName'],
                        'description' => $product['Desc']
                    ]);

                    $productEnglishTranslation->update([
                        'name' => $product['enName'],
                        'unit' => $product['UnitEnName'],
                        'description' => $product['Desc']
                    ]);
                        }

                else { // if the product does not exist
                        $endpoint = 'https://stores.otajer.com/api/rest/productdetails/'.$product['Serial'].'/7D0F1431A09796822632E2AB19DC0231';
                        $response = file_get_contents($endpoint);
                        $productDetails = json_decode($response, true);
                        $productDetails = $productDetails['ProductDetails'];


                    // generate a slug for the product
                        $slug = Str::slug($productDetails['enName']);
                        $same_slug_count = Product::where('slug', 'LIKE', $slug . '%')->count();
                        $slug_suffix = $same_slug_count ? '-' . $same_slug_count + 1 : '';
                        $slug .= $slug_suffix;
                    // end of slug

                    // uploading the image
                    if(isset($productDetails['images'][0]['FileName'])){
                        $image_upload_id = $this->downloadThumbnail(($productDetails['images'][0]['FileName']));
                    }
                    else{
                        $image_upload_id = null;
                    }



                    $variation_attributes =  Attribute::where('name', 'Variations')->first();
                    $types_of_sale = Attribute::where('name', 'Type Of Sale')->first(); // per box or per piece
                    $variant_product = true; // all products are variant!
                    $choice_options = [];
                     // if the product has variations, do the magic
                    if($productDetails['variations'] != null){// if its a variant product

                    $attributes = json_encode(["$variation_attributes->id", "$types_of_sale->id"]);

                    $variations = $productDetails['variations'];

                    foreach($variations as $var){
                    $attribute_value = new AttributeValue;
                    $attribute_value->attribute_id = $variation_attributes->id; // store the values in the variation attribute
                    $attribute_value->value = $var['VName'];
                    $attribute_value->save();
                    }

                     // Initialize the choice_options array


                     // Define the static part of the attribute information for "Per Box" and "Per Piece.", right now all products can sold per box or per piece
                     $choice_options[] = [
                         "attribute_id" => "$types_of_sale->id",
                         "values" => ["Per Box", "Per Piece"]
                     ];

                     // Initialize an array to hold the "VName" values
                     $attributeValues = [];

                     // Loop through variations to collect "VName" values
                     foreach ($variations as $var) {
                         $attributeValues[] = $var['VName'];
                     }

                     // Add the dynamic part of the attribute information
                     $choice_options[] = [
                         "attribute_id" =>  "$variation_attributes->id",
                         "values" => $attributeValues,
                     ];

                     // Convert the final array to JSON
                     $choice_options = json_encode($choice_options, JSON_UNESCAPED_UNICODE);

                }
                else{ // if there are no variations, create perBox and perPiece only
                    $choice_options[] = [
                        "attribute_id" => "$types_of_sale->id",
                        "values" => ["Per Box", "Per Piece"],
                    ];
                    $choice_options = json_encode($choice_options, JSON_UNESCAPED_UNICODE);

                    $attributes = json_encode(["$types_of_sale->id"]);
                    }
                      $productId =  Product::create([
                            'serial' => $productDetails['Serial'],
                            'name' => $productDetails['enName'],
                            'ar_name' => $productDetails['arName'],
                            'unit_price' => $productDetails['Price'],
                            'category_id' => $productDetails['enCatName'] ? Category::where('name', $productDetails['enCatName'])->first()->id : Category::where('name', 'Uncategorized')->first()->id,
                       //     'unit_id' => $productDetails['UnitID'],
                            'unit' => $productDetails['UnitEnName'],
                            'tax' => $productDetails['TaxPercent'],
                            'tax_type' => 'percent',
                        //  'image' => isset($productDetails['images'][0]['FileName'])? $productDetails['images'][0]['FileName'] : null,
                            'description' => $productDetails['Desc'],
                            'variant_product' => $variant_product,
                            'published' => $productDetails['enCatName'] ? 1 : 0,
                            'mat_id' => $productDetails['Mat_ID'],
                        //    'mat_barcode_guid' => isset($productDetails['barcodes'][0]["Mat_BarCode_Guid"])? $productDetails['barcodes'][0]["Mat_BarCode_Guid"] : null,
                            'variations' => json_encode($productDetails['variations'], JSON_UNESCAPED_UNICODE),
                            'from_api' => 1,
                            'user_id' => $admin_id,
                            'slug' => $slug,
                            'thumbnail_img' => $image_upload_id,
                            'photos' => $image_upload_id,
                            'unit_equal' => $productDetails['Unit_Equal'],
                            'choice_options' => $choice_options,
                            'attributes' => $attributes,
                           'colors' => '[]',
                            'cash_on_delivery' => 1
                            /*
                           'shipping_type' => 'free',
                            'discount' => 0.00,
                            'discount_type' => 'amount',
                            'low_stock_quantity' => 1*/

                        ]); //

                        if($productDetails['variations'] != null){
                            foreach ($productDetails['variations'] as $variation) {

                                // Create a product_stock record for per Box
                                $variation['VName'] = str_replace(' ', '', $variation['VName']);
                                $productStock = new ProductStock();
                                $productStock->product_id = $productId->id;
                                $productStock->variant = trim('PerBox' . '-' . $variation['VName']);
                                $productStock->qty = $productDetails['enCatName'] ? 1000 : 0;
                                $productStock->price = $productId->unit_price;
                                //$productStock->sku = $product['enName'] . '-' . $product['Unit_Equal'] . '-' . uniqid();
                                $productStock->save();


                                $productStock = new ProductStock();
                                $productStock->product_id = $productId->id;
                                $productStock->variant = trim('PerPiece' . '-' . $variation['VName']);
                                $productStock->qty = $productDetails['enCatName'] ? 1000 : 0;
                                $productStock->price = ($productId->unit_price / $productId->unit_equal);



                                //$productStock->sku = $product['enName'] . '-' . $product['Unit_Equal'] . '-' . uniqid();
                                $productStock->save();
                        }
                        }
                        else{ // if the are no variations, create only 2 stocks perBox and perPiece
                            ProductStock::create([
                            'product_id' => $productId->id,
                            'qty' => $productDetails['enCatName'] ? 1000 : 0,
                            'price' => $productId->unit_price,
                          //  'sku' => $product['enName'] . '-' . $product['Unit_Equal'] . '-' . uniqid(),
                            'variant' => 'PerBox'
                            ]);

                            ProductStock::create([
                                'product_id' => $productId->id,
                                'qty' => $productDetails['enCatName'] ? 1000 : 0,
                                'price' => $productId->unit_price /  $product['Unit_Equal'],
                         //       'sku' => $product['enName'] . '-' . $product['Unit_Equal'] . '-' . uniqid(),
                                'variant' => 'PerPiece'
                                ]);
                        }



                        // $product_tax = new ProductTax();
                        // $product_tax->tax_id = Tax::first()->id;
                        // $product_tax->product_id = $productId->id;
                        // $product_tax->tax = $productId->tax;
                        // $product_tax->tax_type = 'percent';
                        // $product_tax->save();


                        ProductTranslation::create([
                            'lang' => 'sa',
                            'name' => $productDetails['arName'],
                            'unit' => $productDetails['UnitArName'],
                            'description' => $productId->description,
                            'product_id' => $productId->id
                        ]);

                        ProductTranslation::create([
                            'lang' => 'en',
                            'name' => $productDetails['enName'],
                            'unit' => $productDetails['UnitEnName'],
                            'description' => $productId->description,
                            'product_id' => $productId->id
                           ]);
                }

            }
            $end_time = Carbon::now();
            $time = $end_time->diffInSeconds($start_time);
            $this->info($products_count." product fetched/updated successfully. Process took " . $time . ' seconds');


    }

        catch(Exception $e)
        {
            $this->info('Command failed ' . $e);
        }
    }
}
