<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\RefusesProduction;
use Database\Seeders\Support\StoreFilterSeedData;
use Database\Seeders\Support\StoreSeedExamples;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Searchable support/campaign fixtures and seller/wallet report options. */
class StoreEngagementFilterSeeder extends Seeder
{
    use RefusesProduction;

    public function run(): void
    {
        $this->refuseInProduction();
        if (! app()->environment('local', 'development', 'dev', 'staging', 'testing')) {
            throw new RuntimeException('Store filter fixtures require development, staging or testing.');
        }

        $data = app(StoreFilterSeedData::class);
        $data->batch(function () use ($data): void {
            $admin = $data->user('admin', ['user_type' => 'admin']);
            $customer = $data->user('customer');
            $owner = (int) DB::table('users')->where('user_type', 'admin')->orderBy('id')->value('id');
            $product = DB::table('products')->orderBy('id')->value('id');

            DB::transaction(function () use ($data, $admin, $customer, $owner, $product): void {
                $this->support($data, $admin, $customer, $owner, $product === null ? null : (int) $product);
                $this->campaigns($data, $owner, $product === null ? null : (int) $product);
                $this->sellerFinance($data, $customer);
            });
        });
    }

    private function support(StoreFilterSeedData $data, int $admin, int $customer, int $owner, ?int $product): void
    {
        for ($copy = 0; $copy < StoreSeedExamples::ADMIN_ROWS; $copy++) {
            foreach (['pending', 'open', 'solved'] as $index => $status) {
                $ticket = $data->row('tickets', ('engagement-ticket-'.$status).($copy === 0 ? '' : '-'.$copy), [
                    'code' => (987650 + $index) * 100 + $copy, 'user_id' => $customer,
                    'subject' => match ($status) {
                        'pending' => 'Please confirm our next delivery time', 'open' => 'Missing carton from our grocery delivery', 'solved' => 'Invoice copy for our latest order'
                    }, 'details' => 'Please confirm the delivery window and the quantities on our wholesale order.',
                    'status' => $status, 'viewed' => $index % 2, 'client_viewed' => 0,
                ]);
                $data->row('ticket_replies', ('engagement-reply-'.$status).($copy === 0 ? '' : '-'.$copy), [
                    'ticket_id' => $ticket, 'user_id' => $admin, 'reply' => 'We have checked the order and will confirm the delivery time with our warehouse team.',
                ]);
            }
            $conversation = $data->row('conversations', ('engagement-conversation').($copy === 0 ? '' : '-'.$copy), [
                'sender_id' => $customer, 'receiver_id' => $admin, 'title' => 'Weekly grocery replenishment',
                'sender_viewed' => 1, 'receiver_viewed' => 0,
            ]);
            $data->row('messages', ('engagement-message').($copy === 0 ? '' : '-'.$copy), [
                'conversation_id' => $conversation, 'user_id' => $customer, 'message' => 'Could you include two cartons of olive oil in our next delivery?',
            ]);
            if ($product !== null) {
                $data->row('product_queries', ('engagement-product-query').($copy === 0 ? '' : '-'.$copy), [
                    'customer_id' => $customer, 'seller_id' => $owner, 'product_id' => $product,
                    'question' => 'How many jars are included in one wholesale carton?', 'reply' => 'Each carton contains twelve jars. Please store them in a cool, dry place.',
                ]);
            }
            foreach (['pending', 'sent', 'failed'] as $status) {
                $data->row('customers_notifications', ('engagement-push-'.$status).($copy === 0 ? '' : '-'.$copy), [
                    'user_id' => $customer, 'title' => 'Delivery enquiry: '.$status,
                    'body' => 'Your next grocery delivery is being prepared by our warehouse team.', 'status' => $status,
                ]);
            }
            $data->row('subscribers', ('engagement-subscriber').($copy === 0 ? '' : '-'.$copy), ['email' => 'newsletter-'.($copy + 1).'@example.invalid']);
        }
    }

    private function campaigns(StoreFilterSeedData $data, int $owner, ?int $product): void
    {
        for ($copy = 0; $copy < StoreSeedExamples::ADMIN_ROWS; $copy++) {
            $category = $data->row('blog_categories', ('engagement-blog-category').($copy === 0 ? '' : '-'.$copy), [
                'category_name' => 'Pantry Journal', 'slug' => 'pantry-journal-'.$copy,
            ]);
            foreach ([0, 1] as $status) {
                $data->row('blogs', ('engagement-blog-'.$status).($copy === 0 ? '' : '-'.$copy), [
                    'category_id' => $category, 'title' => 'Pantry Journal: Seasonal ingredients '.($copy + 1),
                    'slug' => 'pantry-journal-'.$status.'-'.$copy, 'status' => $status,
                    'description' => '<p>Discover seasonal pantry essentials, serving ideas and practical storage advice for your shop.</p>',
                ]);
            }
            $data->row('coupons', ('engagement-coupon').($copy === 0 ? '' : '-'.$copy), [
                'user_id' => $owner, 'type' => 'cart_base', 'code' => 'PANTRY'.str_pad((string) ($copy + 1), 3, '0', STR_PAD_LEFT),
                'details' => json_encode(['min_buy' => 1, 'max_discount' => 5], JSON_THROW_ON_ERROR),
                'discount' => 5, 'discount_type' => 'amount',
                'start_date' => now()->subDay()->timestamp, 'end_date' => now()->addMonth()->timestamp,
            ]);
            $offer = $data->row('offers', ('engagement-offer').($copy === 0 ? '' : '-'.$copy), [
                'title' => 'Pantry Restock Savings '.($copy + 1), 'offer_type' => 'products_offer',
                'start_date' => now()->subDay()->timestamp, 'end_date' => now()->addMonth()->timestamp,
                'is_active' => 1, 'is_repeatable' => 1, 'multiple_usage' => 1,
            ]);
            $deal = $data->row('flash_deals', ('engagement-flash-deal').($copy === 0 ? '' : '-'.$copy), [
                'title' => 'Weekend Grocery Offers '.($copy + 1), 'slug' => 'pantry-savings-'.$copy,
                'start_date' => now()->subDay()->timestamp, 'end_date' => now()->addMonth()->timestamp,
                'status' => 1, 'featured' => 1, 'background_color' => '#ffffff', 'text_color' => '#111111',
            ]);
            if ($product !== null) {
                $data->row('offer_products', ('engagement-offer-base').($copy === 0 ? '' : '-'.$copy), [
                    'offer_id' => $offer, 'product_id' => $product, 'is_base_product' => 1,
                    'quantity' => 1, 'discount' => 0, 'discount_type' => 'amount',
                ]);
                $data->row('flash_deal_products', ('engagement-deal-product').($copy === 0 ? '' : '-'.$copy), [
                    'flash_deal_id' => $deal, 'product_id' => $product, 'discount' => 5, 'discount_type' => 'percent',
                ]);
            }
            $data->row('dynamic_popups', ('engagement-popup').($copy === 0 ? '' : '-'.$copy), [
                'title' => 'Fresh arrivals for your pantry', 'summary' => 'Discover this week’s grocery arrivals and wholesale offers.',
                'status' => 0, 'offer_id' => $offer, 'show_page' => 0,
            ]);
        }
    }

    private function sellerFinance(StoreFilterSeedData $data, int $customer): void
    {
        foreach ([1 => 'seller', 0 => 'seller-unverified'] as $verified => $prefix) {
            for ($copy = 0; $copy < StoreSeedExamples::ADMIN_ROWS; $copy++) {
                $key = $copy === 0 ? $prefix : $prefix.'-'.$copy;
                $seller = $data->user($key, ['user_type' => 'seller']);
                $data->row('shops', $key === 'seller' ? 'catalogue-shop' : 'engagement-shop-'.$key, [
                    'user_id' => $seller, 'name' => StoreSeedExamples::company($key),
                    'slug' => StoreSeedExamples::code('shop', $key),
                    'verification_status' => $verified, 'admin_to_pay' => 50,
                ]);
                for ($entry = 0; $entry < StoreSeedExamples::ADMIN_ROWS; $entry++) {
                    $data->row('payments', 'engagement-payment-'.$key.($entry === 0 ? '' : '-'.$entry), [
                        'seller_id' => $seller, 'amount' => 10, 'payment_method' => 'cash',
                        'txn_code' => StoreSeedExamples::code('SET', $key.'-'.$entry),
                    ]);
                    $data->row('seller_withdraw_requests', 'engagement-withdrawal-'.$key.($entry === 0 ? '' : '-'.$entry), [
                        'user_id' => $seller, 'amount' => 5, 'message' => 'Please transfer the available wholesale sales balance.', 'status' => $verified, 'viewed' => 0,
                    ]);
                }
            }
        }
        $users = DB::table('users')->whereIn('id', DB::table('wallets')->select('user_id'))->pluck('id')->push($customer)->unique();
        foreach ($users as $user) {
            for ($copy = 0; $copy < StoreSeedExamples::ADMIN_ROWS; $copy++) {
                $data->row('wallets', 'engagement-wallet-'.$user.($copy === 0 ? '' : '-'.$copy), [
                    'user_id' => $user, 'amount' => 25, 'payment_method' => 'cash',
                    'payment_details' => 'Cash deposit recorded at the Amsterdam collection desk.', 'created_at' => now(),
                ]);
            }
        }
    }
}
