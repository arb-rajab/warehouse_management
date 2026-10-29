<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\RefusesProduction;
use Database\Seeders\Support\StoreFilterSeedData;
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
        $admin = $data->user('admin', ['user_type' => 'admin']);
        $customer = $data->user('customer');
        $owner = (int) DB::table('users')->where('user_type', 'admin')->orderBy('id')->value('id');
        $product = DB::table('products')->orderBy('id')->value('id');

        DB::transaction(function () use ($data, $admin, $customer, $owner, $product): void {
            $this->support($data, $admin, $customer, $owner, $product === null ? null : (int) $product);
            $this->campaigns($data, $owner, $product === null ? null : (int) $product);
            $this->sellerFinance($data, $customer);
        });
    }

    private function support(StoreFilterSeedData $data, int $admin, int $customer, int $owner, ?int $product): void
    {
        foreach (['pending', 'open', 'solved'] as $index => $status) {
            $ticket = $data->row('tickets', 'engagement-ticket-'.$status, [
                'code' => 987650 + $index, 'user_id' => $customer,
                'subject' => 'Filter coverage '.$status, 'details' => 'Development and staging support example.',
                'status' => $status, 'viewed' => $index % 2, 'client_viewed' => 0,
            ]);
            $data->row('ticket_replies', 'engagement-reply-'.$status, [
                'ticket_id' => $ticket, 'user_id' => $admin, 'reply' => 'Filter coverage support reply.',
            ]);
        }
        $conversation = $data->row('conversations', 'engagement-conversation', [
            'sender_id' => $customer, 'receiver_id' => $admin, 'title' => 'Filter coverage conversation',
            'sender_viewed' => 1, 'receiver_viewed' => 0,
        ]);
        $data->row('messages', 'engagement-message', [
            'conversation_id' => $conversation, 'user_id' => $customer, 'message' => 'Filter coverage message.',
        ]);
        if ($product !== null) {
            $data->row('product_queries', 'engagement-product-query', [
                'customer_id' => $customer, 'seller_id' => $owner, 'product_id' => $product,
                'question' => 'Filter coverage product question?', 'reply' => 'Filter coverage product answer.',
            ]);
        }
        foreach (['pending', 'sent', 'failed'] as $status) {
            $data->row('customers_notifications', 'engagement-push-'.$status, [
                'user_id' => $customer, 'title' => 'Filter coverage '.$status,
                'body' => 'Fixture only; no push notification was sent.', 'status' => $status,
            ]);
        }
        $data->row('subscribers', 'engagement-subscriber', ['email' => 'subscriber@store-filter-seed.test']);
    }

    private function campaigns(StoreFilterSeedData $data, int $owner, ?int $product): void
    {
        $category = $data->row('blog_categories', 'engagement-blog-category', [
            'category_name' => 'Filter coverage news', 'slug' => 'store-filter-coverage-news',
        ]);
        foreach ([0, 1] as $status) {
            $data->row('blogs', 'engagement-blog-'.$status, [
                'category_id' => $category, 'title' => 'Filter coverage news '.$status,
                'slug' => 'store-filter-coverage-news-'.$status, 'status' => $status,
                'description' => '<p>Development and staging news example.</p>',
            ]);
        }
        $data->row('coupons', 'engagement-coupon', [
            'user_id' => $owner, 'type' => 'cart_base', 'code' => 'FILTERCOVERAGE5',
            'details' => json_encode(['min_buy' => 1, 'max_discount' => 5], JSON_THROW_ON_ERROR),
            'discount' => 5, 'discount_type' => 'amount',
            'start_date' => now()->subDay()->timestamp, 'end_date' => now()->addMonth()->timestamp,
        ]);
        $offer = $data->row('offers', 'engagement-offer', [
            'title' => 'Filter coverage offer', 'offer_type' => 'products_offer',
            'start_date' => now()->subDay()->timestamp, 'end_date' => now()->addMonth()->timestamp,
            'is_active' => 1, 'is_repeatable' => 1, 'multiple_usage' => 1,
        ]);
        $deal = $data->row('flash_deals', 'engagement-flash-deal', [
            'title' => 'Filter coverage flash deal', 'slug' => 'store-filter-coverage-deal',
            'start_date' => now()->subDay()->timestamp, 'end_date' => now()->addMonth()->timestamp,
            'status' => 1, 'featured' => 1, 'background_color' => '#ffffff', 'text_color' => '#111111',
        ]);
        if ($product !== null) {
            $data->row('offer_products', 'engagement-offer-base', [
                'offer_id' => $offer, 'product_id' => $product, 'is_base_product' => 1,
                'quantity' => 1, 'discount' => 0, 'discount_type' => 'amount',
            ]);
            $data->row('flash_deal_products', 'engagement-deal-product', [
                'flash_deal_id' => $deal, 'product_id' => $product, 'discount' => 5, 'discount_type' => 'percent',
            ]);
        }
        $data->row('dynamic_popups', 'engagement-popup', [
            'title' => 'Filter coverage popup', 'summary' => 'Development and staging example.',
            'status' => 0, 'offer_id' => $offer, 'show_page' => 0,
        ]);
    }

    private function sellerFinance(StoreFilterSeedData $data, int $customer): void
    {
        foreach ([1 => 'seller', 0 => 'seller-unverified'] as $verified => $key) {
            $seller = $data->user($key, ['user_type' => 'seller']);
            $data->row('shops', $verified ? 'catalogue-shop' : 'engagement-shop-'.$key, [
                'user_id' => $seller, 'name' => 'Filter coverage shop '.$verified,
                'slug' => 'store-filter-coverage-shop-'.$verified,
                'verification_status' => $verified, 'admin_to_pay' => 50,
            ]);
            $data->row('payments', 'engagement-payment-'.$key, [
                'seller_id' => $seller, 'amount' => 10, 'payment_method' => 'cash',
                'txn_code' => 'FILTER-COVERAGE-'.$verified,
            ]);
            $data->row('seller_withdraw_requests', 'engagement-withdrawal-'.$key, [
                'user_id' => $seller, 'amount' => 5, 'message' => 'Fixture only.', 'status' => $verified, 'viewed' => 0,
            ]);
        }
        $users = DB::table('users')->whereIn('id', DB::table('wallets')->select('user_id'))->pluck('id')->push($customer)->unique();
        foreach ($users as $user) {
            $data->row('wallets', 'engagement-wallet-'.$user, [
                'user_id' => $user, 'amount' => 25, 'payment_method' => 'cash',
                'payment_details' => 'Filter coverage fixture; no external payment.', 'created_at' => now(),
            ]);
        }
    }
}
