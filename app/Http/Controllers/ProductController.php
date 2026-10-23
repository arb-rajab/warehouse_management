<?php

namespace App\Http\Controllers;

use App\Models\CronJobs;
use App\Models\Product;
use App\Utility\ProductUtility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Retained JSON cron trigger and legacy read/SKU compatibility endpoints.
 * Admin product lists and their writes live in Store\Admin\ProductController.
 */
class ProductController extends Controller
{
    public function sku_combination(Request $request)
    {
        $options = [];
        if ($request->has('colors_active') && $request->has('colors') && count($request->colors) > 0) {
            $colors_active = 1;
            array_push($options, $request->colors);
        } else {
            $colors_active = 0;
        }

        $unit_price = $request->unit_price;
        $product_name = $request->name;

        if ($request->has('choice_no')) {
            foreach ($request->choice_no as $key => $no) {
                $name = 'choice_options_' . $no;
                $data = [];
                // foreach (json_decode($request[$name][0]) as $key => $item) {
                foreach ($request[$name] as $key => $item) {
                    // array_push($data, $item->value);
                    array_push($data, $item);
                }
                array_push($options, $data);
            }
        }

        $combinations = ProductUtility::makeCombinations($options);
        return view('shop.backend.product.products.sku_combinations', compact('combinations', 'unit_price', 'colors_active', 'product_name'));
    }

    public function fetch()
    {
        Log::info('Fetch function started at ' . now()->toDateTimeString());

        $hostingerCronJob = CronJobs::where('command', 'fetching-products-by-hostinger')->where(function ($q) { $q->where('status', 'pending')->orWhere('status', 'running'); })->where('updated_at', '>', now()->subMinutes(30))->first();

        Log::info('Checked for hostinger cron job: ' . ($hostingerCronJob ? 'found (ID: ' . $hostingerCronJob->id . ', Status: ' . $hostingerCronJob->status . ')' : 'not found'));

        if ($hostingerCronJob) {
            Log::info('Returning failure (500) because hostinger cron job is pending or running', [
                'id' => $hostingerCronJob->id,
                'status' => $hostingerCronJob->status,
                'created_at' => $hostingerCronJob->created_at,
                'updated_at' => $hostingerCronJob->updated_at,
                'command' => $hostingerCronJob->command,
            ]);

            return response()->json([
                'success' => false,
                'cron_job' => $hostingerCronJob
            ], 500);
        }

        $cronjob = CronJobs::where('command', 'fetching-products-by-admin')->where(function ($q) { $q->where('status', 'pending')->orWhere('status', 'running'); })->where('updated_at', '>', now()->subMinutes(30))->first();

        Log::info('Checked for admin cron job: ' . ($cronjob ? 'found (ID: ' . $cronjob->id . ', Status: ' . $cronjob->status . ')' : 'not found'));

        if (! $cronjob) {
            Log::info('Creating new admin cron job');

            try {
                CronJobs::create([
                    'command' => 'fetching-products-by-admin',
                    'status' => 'pending',
                ]);
                Log::info('New admin cron job created successfully');
            } catch (\Exception $e) {
                Log::error('Error creating admin cron job: ' . $e->getMessage());
                return response()->json(['success' => false], 500);
            }

            Log::info('Returning success');
            return response()->json(['success' => true]);
        } else {
            Log::info('Returning failure (500) because admin cron job is pending or running');
            return response()->json(['success' => false], 500);
        }
    }

    public function search(Request $request)
    {
        $products = Product::where('auction_product', 0)->where('wholesale_product', 0);
        if ($request->has('user_id') && $request->user_id != null) {
            $products = $products->where('user_id', $request->user_id);
            $seller_id = $request->user_id;
        }
        if ($request->search != null) {
            $sort_search = $request->q;

            $products_query = $products->where(function ($q) use ($sort_search) {
                foreach (explode(' ', trim($sort_search)) as $word) {
                    $q->where(function ($innerQ) use ($word) {
                        $innerQ->where('name', 'like', '%' . $word . '%')
                            ->orWhere('ar_name', 'like', '%' . $word . '%')
                            ->orWhere('mat_id', 'like', '%' . $word . '%')
                            ->orWhereHas('barcodes', function ($barcodeQ) use ($word) {
                                $barcodeQ->where('barcode', 'like', '%' . $word . '%');
                            });
                    });
                }
            })->get();
        }
    }

    public function getDetails($id)
    {
        $product = Product::findOrFail($id);
        return response()->json([
            'mat_id' => $product->mat_id,
            'unit' => $product->unit,
            'unit_price' => $product->unit_price,
            'tax' => $product->tax
        ]);
    }
}
