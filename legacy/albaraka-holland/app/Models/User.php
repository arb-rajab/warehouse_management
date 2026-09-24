<?php

namespace App\Models;

use App\Notifications\TermsAgreementNotification;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Cart;
use App\Notifications\EmailVerificationNotification;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable, HasApiTokens, HasRoles;

    public function sendEmailVerificationNotification()
    {
        $this->notify(new EmailVerificationNotification());
    }

    public function sendTermsAgreementNotification()
    {
        $this->notify(new TermsAgreementNotification());
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'user_type',
        'device_token',
        'address',
        'city',
        'company_name',
        'bank_account_number',
        'postal_code',
        'phone',
        'country',
        'provider_id',
        'email_verified_at',
        'verification_code',
        'shipping_address',
        'company_address',
        'tax_number',
        'trade_license',
        'is_rep',
        'from_api',
        'member_serial',
        'synched_with_otajer',
        'AccSysID',
        'registration_completed',
        'rep_serial',
        'rep_id',
        'admin_verified',
        'admin_notes',
        'activity_status',
        'device_key'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function customer()
    {
        return $this->hasOne(Customer::class);
    }

    public function affiliate_user()
    {
        return $this->hasOne(AffiliateUser::class);
    }

    public function affiliate_withdraw_request()
    {
        return $this->hasMany(AffiliateWithdrawRequest::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function shop()
    {
        return $this->hasOne(Shop::class);
    }
    public function seller()
    {
        return $this->hasOne(Seller::class);
    }


    public function staff()
    {
        return $this->hasOne(Staff::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function customer_visits()
    {
        return $this->hasMany(Visit::class, 'customer_id');
    }

    public function rep_visits()
    {
        return $this->hasMany(Visit::class, 'rep_id');
    }

    public function seller_orders()
    {
        return $this->hasMany(Order::class, "seller_id");
    }
    public function seller_sales()
    {
        return $this->hasMany(OrderDetail::class, "seller_id");
    }

    public function wallets()
    {
        return $this->hasMany(Wallet::class)->orderBy('created_at', 'desc');
    }

    public function club_point()
    {
        return $this->hasOne(ClubPoint::class);
    }

    public function customer_package()
    {
        return $this->belongsTo(CustomerPackage::class);
    }

    public function customer_package_payments()
    {
        return $this->hasMany(CustomerPackagePayment::class);
    }

    public function customer_products()
    {
        return $this->hasMany(CustomerProduct::class);
    }

    public function seller_package_payments()
    {
        return $this->hasMany(SellerPackagePayment::class);
    }

    public function carts()
    {
        return $this->hasMany(Cart::class);
    }
    public function offers()
    {
        return $this->belongsToMany(Offer::class, 'offer_user', 'user_id', 'offer_id');
    }
    public function carts_offered()
    {
        return $this->hasMany(CartOffered::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function current_customer()
    {
        $cart = $this->carts->first();
        return $cart ? $cart->belongsTo(User::class, 'for_customer', 'id') : $this->belongsTo(User::class, 'for_customer', 'id')->where('id', '=', null);
    }

    public function affiliate_log()
    {
        return $this->hasMany(AffiliateLog::class);
    }

    public function product_bids()
    {
        return $this->hasMany(AuctionProductBid::class);
    }
    public function product_queries()
    {
        return $this->hasMany(ProductQuery::class, 'customer_id');
    }
    public function uploads()
    {
        return $this->hasMany(Upload::class);
    }

    public function representativePackage()
    {
        return $this->belongsTo(RepresentativePackage::class, 'rep_package_id');
    }

    public function representativePayments()
    {
        return $this->hasMany(RepresentativePayment::class, 'rep_id');
    }

    public function representativePaymentAdmin()
    {
        return $this->hasMany(RepresentativePayment::class, 'admin_id');
    }

    public function representativePaymentCustomer()
    {
        return $this->hasMany(RepresentativePayment::class, 'customer_id');
    }

    public function got_offer($offer_id)
    {
        $offer = Offer::find($offer_id);
        if ($offer->is_repeatable) {
            return 0;
        }
        return $this->offers->where('id', $offer_id)->count();
    }

    public function has_open_visit()
    {
        return $this->rep_visits()
            ->whereNull('order_id')
            ->where('created_at', '>', now()->subHours(24))
            ->exists();
    }

    public function rep_last_open_visit()
    {
        return $this->rep_visits()
            ->whereNull('order_id')
            ->where('created_at', '>', now()->subHours(24))
            ->orderByDesc('created_at')
            ->first();
    }

    public function rep_last_visit()
    {
        return $this->rep_visits->sortByDesc('created_at')->first();
    }

    public function rep_last_visit_of_the_day()
    {
        // Get start and end of the day
        $startOfDay = \Carbon\Carbon::now()->startOfDay();
        $endOfDay = \Carbon\Carbon::now()->endOfDay();

        return $this->rep_visits
            ->where('visit_date', '>=', $startOfDay)
            ->where('visit_date', '<=', $endOfDay)
            ->sortByDesc('created_at')
            ->first();
    }

    public function pageViews()
    {
        return $this->hasMany(UserPageView::class);
    }

    /**
     * Scope to filter users with type 'driver'.
     */
    public function scopeDrivers($query)
    {
        return $query->where('user_type', 'driver');
    }

    /**
     * Relationship: A user (driver) can have many trips.
     */
    public function trips()
    {
        return $this->hasMany(Trip::class, 'driver_id');
    }

    /**
     * Get the user's first active trip (not closed).
     */
    public function activeTrip()
    {
        return $this->trips()->active()->first();
    }
}
