<?php

namespace Database\Seeders;

use App\Enums\TripStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use Database\Seeders\Concerns\RefusesProduction;
use Database\Seeders\Support\StoreFilterSeedData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Activity fixtures use direct inserts so observers cannot debit stock or contact external services. */
class StoreActivityFilterSeeder extends Seeder
{
    use RefusesProduction;

    public function run(): void
    {
        $this->refuseInProduction();
        $data = app(StoreFilterSeedData::class);
        $customer = $data->user('customer');
        $representative = $data->user('representative', ['user_type' => 'customer', 'is_rep' => 1]);
        $admin = $data->user('admin', ['user_type' => 'admin']);
        $seller = $data->user('seller', ['user_type' => 'seller']);
        $driver = $data->user('driver', ['user_type' => 'driver']);
        $inhouseSeller = (int) DB::table('users')->where('user_type', 'admin')->orderBy('id')->value('id');
        $product = DB::table('products')->orderBy('id')->first();
        if ($product === null) {
            throw new RuntimeException('Run StoreCatalogueFilterSeeder before store activity fixtures: a product is required.');
        }

        $package = $data->row('representative_packages', 'representative-package', ['name' => 'Filter coverage representative package', 'percentage' => 5]);
        $data->user('representative', ['rep_package_id' => $package]);
        $staffUser = $data->user('activity-pickup-staff', ['user_type' => 'staff']);
        $staffRole = $data->row('wms_roles', 'activity-pickup-role', ['name' => 'Filter coverage pickup', 'guard_name' => 'store']);
        $staff = $data->row('staff', 'activity-pickup-staff', ['user_id' => $staffUser, 'role_id' => $staffRole]);
        $pickup = $data->row('pickup_points', 'activity-pickup', ['staff_id' => $staff, 'name' => 'Filter coverage pickup', 'address' => 'Seed warehouse', 'phone' => '+31000000000', 'pick_up_status' => 1]);
        $address = json_encode(['name' => 'Filter coverage customer', 'address' => 'Seed warehouse', 'city' => 'Amsterdam', 'country' => 'Netherlands', 'phone' => '+31000000000', 'postal_code' => '1000AA', 'latitude' => '52.1', 'longitude' => '4.2'], JSON_THROW_ON_ERROR);
        $orders = [];
        foreach (['inhouse' => $inhouseSeller, 'seller' => $seller, 'pickup' => $inhouseSeller] as $list => $sellerId) {
            $sellerProduct = $this->sellerProduct($data, (int) $sellerId, (int) $product->id);
            foreach (OrderService::DELIVERY_STATUSES as $status) {
                foreach (['paid', 'unpaid'] as $payment) {
                    if ($status === 'cancelled' && $payment === 'paid') {
                        continue;
                    }
                    $key = "activity-{$list}-{$status}-{$payment}";
                    $combined = $data->row('combined_orders', $key, ['user_id' => $representative, 'shipping_address' => $address, 'grand_total' => 20]);
                    $order = $data->row('orders', $key, [
                        'combined_order_id' => $combined, 'user_id' => $representative, 'for_customer' => $customer, 'by_rep' => 1,
                        'seller_id' => $sellerId, 'shipping_address' => $address,
                        'shipping_type' => $list === 'pickup' ? 'pickup_point' : 'home_delivery',
                        'pickup_point_id' => $list === 'pickup' ? $pickup : 0,
                        'delivery_status' => $status, 'payment_status' => $payment, 'payment_type' => 'cash_on_delivery',
                        'grand_total' => 20, 'code' => 'FILTER-'.$key, 'invoice_number' => 'FILTER-'.$key,
                        'date' => now()->timestamp, 'delivery_date' => today()->toDateString(), 'created_at' => now(),
                        'order_from' => 'web', 'commission_calculated' => 0,
                    ]);
                    $line = $data->row('order_details', $key, [
                        'order_id' => $order, 'seller_id' => $sellerId, 'product_id' => $sellerProduct,
                        'variation' => '', 'price' => 20, 'tax' => 0, 'shipping_cost' => 0, 'quantity' => 2,
                        'payment_status' => $payment, 'delivery_status' => $status,
                    ]);
                    $orders[$list][$status][$payment] = ['order' => $order, 'line' => $line];
                }
            }
        }

        foreach (['accepted', 'rejected', 'pending'] as $index => $status) {
            $data->row('representative_payments', 'activity-payment-'.$status, [
                'id' => 'filter-coverage-payment-'.$status, 'rep_id' => $representative, 'customer_id' => $customer,
                'admin_id' => $status === 'pending' ? null : $admin, 'status' => $status,
                'amount' => 20, 'receipt_code' => 'FILTER-RECEIPT-'.$status, 'invoice_code' => 900001 + $index,
                'customer_name' => 'Filter coverage customer', 'date' => now(), 'notes' => 'Local fixture; no external payment was made.',
            ]);
        }

        foreach ([false, true] as $withOrder) {
            $data->row('visits', 'activity-visit-'.(int) $withOrder, [
                'rep_id' => $representative, 'customer_id' => $customer,
                'order_id' => $withOrder ? ($orders['inhouse']['confirmed']['unpaid']['order'] ?? throw new RuntimeException('Confirmed order fixture is required for visits.')) : null,
                'note' => 'Filter coverage visit', 'map_lat' => '52.1', 'map_lng' => '4.2',
                'visit_date' => now(), 'created_at' => now(), 'rank' => $withOrder ? 0 : 1,
            ]);
        }

        foreach (TripStatus::cases() as $index => $status) {
            $tripDriver = $index === 0 ? $driver : $data->user('activity-driver-'.$status->value, ['user_type' => 'driver']);
            $truck = $data->row('trucks', 'activity-truck-'.$status->value, [
                'name' => 'Filter coverage truck '.$status->value, 'model' => 'Filter van', 'license_plate' => 'FILTER-'.$index,
                'is_available' => $status === TripStatus::Closed, 'max_pallets_number' => '10',
            ]);
            $trip = $data->row('trips', 'activity-trip-'.$status->value, [
                'code' => 'FILTER-TRIP-'.$status->value, 'truck_id' => $truck, 'driver_id' => $tripDriver,
                'due_date' => today()->toDateString(), 'status' => $status->value, 'active' => $status !== TripStatus::Closed,
                'started_at' => $status === TripStatus::Pending ? null : now()->subHour(),
                'closed_at' => $status === TripStatus::Closed ? now() : null,
            ]);
            $delivery = match ($status) {
                TripStatus::Pending => 'confirmed', TripStatus::Started => 'on_delivery', TripStatus::Closed => 'delivered',
            };
            $data->row('checkpoints', 'activity-checkpoint-'.$status->value, [
                'trip_id' => $trip, 'relationable_type' => Order::class, 'relationable_id' => $orders['inhouse'][$delivery]['unpaid']['order'] ?? throw new RuntimeException('Delivery order fixture is required for trip checkpoints.'),
                'type' => 'delivery', 'status' => $status === TripStatus::Closed ? 'delivered' : 'pending',
                'arrived_at' => $status === TripStatus::Closed ? now() : null,
                'address' => $address, 'latitudes' => '52.1', 'longitudes' => '4.2', 'sort_order_in_trip' => 1,
            ]);
        }

        foreach (DB::table('users')->where('user_type', 'seller')->pluck('id') as $sellerId) {
            $sellerProduct = $this->sellerProduct($data, (int) $sellerId, (int) $product->id);
            $key = 'activity-commission-'.$sellerId;
            $combined = $data->row('combined_orders', $key, ['user_id' => $customer, 'shipping_address' => $address, 'grand_total' => 20]);
            $order = $data->row('orders', $key, [
                'combined_order_id' => $combined, 'user_id' => $customer, 'seller_id' => $sellerId,
                'shipping_address' => $address, 'shipping_type' => 'home_delivery', 'delivery_status' => 'delivered',
                'payment_status' => 'paid', 'payment_type' => 'cash_on_delivery', 'grand_total' => 20,
                'code' => 'FILTER-COMMISSION-'.$sellerId, 'date' => now()->timestamp,
                'commission_calculated' => 1, 'created_at' => now(),
            ]);
            $line = $data->row('order_details', $key, [
                'order_id' => $order, 'seller_id' => $sellerId, 'product_id' => $sellerProduct,
                'variation' => '', 'price' => 20, 'quantity' => 2, 'payment_status' => 'paid', 'delivery_status' => 'delivered',
            ]);
            $data->row('commission_histories', 'activity-commission-'.$sellerId, [
                'seller_id' => $sellerId, 'order_id' => $order, 'order_detail_id' => $line,
                'admin_commission' => 2, 'seller_earning' => 18, 'created_at' => now(),
            ]);
        }
        $data->row('searches', 'activity-search', ['query' => 'Filter coverage', 'count' => 5]);
        foreach ([Product::class => $product->id, Category::class => DB::table('categories')->orderBy('id')->value('id')] as $type => $id) {
            if ($id !== null) {
                $data->row('user_page_views', 'activity-view-'.$type, [
                    'user_id' => $customer, 'viewable_type' => $type, 'viewable_id' => $id, 'viewed_at' => now(),
                ]);
            }
        }
    }

    private function sellerProduct(StoreFilterSeedData $data, int $seller, int $fallback): int
    {
        $existing = DB::table('products')->where('user_id', $seller)->orderBy('id')->value('id');
        if ($existing !== null) {
            return (int) $existing;
        }

        $reference = DB::table('products')->where('id', $fallback)->first();

        return (int) $data->row('products', 'activity-seller-product-'.$seller, [
            'name' => 'Filter coverage seller product '.$seller, 'ar_name' => '',
            'slug' => 'filter-coverage-seller-product-'.$seller, 'user_id' => $seller,
            'added_by' => DB::table('users')->where('id', $seller)->value('user_type') === 'seller' ? 'seller' : 'admin',
            'published' => 1, 'approved' => 1, 'digital' => 0, 'auction_product' => 0, 'wholesale_product' => 0,
            'category_id' => $reference?->category_id, 'brand_id' => $reference?->brand_id,
            'unit_price' => 10, 'current_stock' => 0,
        ]);
    }
}
