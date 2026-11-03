<?php

namespace App\Console\Commands;

use App\Models\BusinessSetting;
use App\Models\Carrier;
use App\Models\CarrierRange;
use App\Models\CarrierRangePrice;
use App\Models\Country;
use App\Models\Product;
use App\Models\Zone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

#[Signature('store:setup-delivery
    {--country=Netherlands : The country the carrier delivers to}
    {--carrier=Test carrier : The carrier name; run again with the same name to change its price}
    {--price=5 : The carrier price for that country}
    {--transit-days=2 : The carrier transit time in days}
    {--minimum= : The minimum order amount; switches the minimum order check on}
    {--fee-mat-id= : The product id of the delivery fee product added to an order below the minimum}')]
#[Description('Give a development database one carrier for a country and, optionally, a minimum order amount (what GET delivery-info needs to be tested)')]
class SetupDeliveryCommand extends Command
{
    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->components->error('Refusing to run in production: set carriers and the order configuration in the back office.');

            return self::FAILURE;
        }

        $country = Country::query()->where('name', $this->option('country'))->first();

        if ($country === null) {
            $this->components->error('There is no country named "'.$this->option('country').'".');

            return self::FAILURE;
        }

        $fee = $this->option('fee-mat-id');

        if (filled($fee) && (! ctype_digit((string) $fee) || ! Product::query()->whereKey((int) $fee)->exists())) {
            $this->components->error('There is no product with id "'.$fee.'" for the delivery fee.');

            return self::FAILURE;
        }

        if (filled($this->option('minimum')) && (! is_numeric($this->option('minimum')) || (float) $this->option('minimum') < 0)) {
            $this->components->error('--minimum must be a number of at least 0.');

            return self::FAILURE;
        }

        // The store's delivery models were never given $fillable lists, so they are written unguarded here.
        DB::transaction(fn () => Model::unguarded(function () use ($country, $fee): void {
            // A country with zone 0 is in no zone, so no carrier price can match it.
            $zoneId = $country->zone_id ?: Zone::query()->firstOrCreate(['name' => $country->name], ['status' => 1])->id;
            Country::query()->whereKey($country->id)->update(['zone_id' => $zoneId]);

            $carrier = Carrier::query()->firstOrCreate(['name' => $this->option('carrier')], ['transit_time' => (string) (int) $this->option('transit-days'), 'free_shipping' => 0, 'status' => 1]);
            $carrier->forceFill(['status' => 1, 'free_shipping' => 0, 'transit_time' => (string) (int) $this->option('transit-days')])->save();

            $range = CarrierRange::query()->firstOrCreate(['carrier_id' => $carrier->id, 'billing_type' => 'weight_based'], ['delimiter1' => 0, 'delimiter2' => 1000000]);
            CarrierRangePrice::query()->updateOrCreate(['carrier_id' => $carrier->id, 'carrier_range_id' => $range->id, 'zone_id' => $zoneId], ['price' => (float) $this->option('price')]);

            if (filled($this->option('minimum'))) {
                $this->setting('minimum_order_amount_check', 1);
                $this->setting('minimum_order_amount', (float) $this->option('minimum'));
            }

            if (filled($fee)) {
                $this->setting('default_specific_product', $fee);
            }

            $this->components->info('Carrier "'.$carrier->name.'" now delivers to '.$country->name.' for '.(float) $this->option('price').'.');
        }));

        Cache::forget('business_settings');

        if (filled($this->option('minimum'))) {
            $this->components->info('Minimum order amount '.(float) $this->option('minimum').($fee ? ', delivery fee product '.$fee.'.' : '. No --fee-mat-id given: an order below it is not charged a fee.'));
        }

        if (get_setting('shipping_type') !== 'carrier_wise_shipping') {
            $this->components->warn('The shipping type is "'.get_setting('shipping_type').'", so the carrier is listed with a transit price of 0. Set it to carrier wise shipping in the back office (Setup, Shipping) to price it.');
        }

        return self::SUCCESS;
    }

    private function setting(string $type, string|int|float $value): void
    {
        $setting = BusinessSetting::query()->where('type', $type)->first() ?? new BusinessSetting;
        $setting->type = $type;
        $setting->value = (string) $value;
        $setting->save();
    }
}
