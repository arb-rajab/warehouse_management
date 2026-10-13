<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;

use App\Models\Cart;
use App\Notifications\EmailVerificationNotification;
use App\Notifications\TermsAgreementNotification;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string|null $company_name
 * @property bool|null $admin_verified
 * @property string|null $trade_license
 * @property int|null $AccSysID
 * @property string|null $admin_notes
 * @property string|null $bank_account_number
 * @property string|null $company_address
 * @property string|null $member_serial
 * @property bool|null $registration_completed
 * @property string|null $shipping_address
 * @property string|null $tax_number
 * @property int|null $is_delete
 * @property string|null $admin_verified_at
 * @property string|null $last_order_at
 * @property int $is_important
 * @property string $activity_status
 * @property string|null $rep_id
 * @property string|null $rep_serial
 * @property-read RepresentativePackage|null $representativePackage
 * @property bool $is_rep
 * @property bool|null $raizer_admin
 * @property int|null $AccSysID
 * @property string|null $device_key
 * @property-read StoreUser|null $current_customer
 */
class StoreUser extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The legacy User -> StoreUser rename (see
     * .ai/rules/legacy-albaraka-holland.md section 2) only renamed the PHP
     * class to dodge the App\Models\User collision; the actual table stayed
     * `users` (the store's own, distinct from this app's wms_users). Without
     * this, Eloquent's naming convention infers `store_users` from the class
     * name — a table that has never existed — and every query against this
     * model fails outright.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The spatie guard this model's roles and permissions live under, pinned
     * so it isn't derived from config/auth.php's guard list (`store` and
     * `store_api` both name this model's provider) or from whichever guard
     * `Auth::shouldUse()` made the default for the request. User's is `web`.
     */
    protected string $guard_name = 'store';

    /**
     * Get the default foreign key name for the model.
     *
     * Eloquent derives it from the class name, so the same rename turned
     * every hasOne/hasMany/belongsToMany below that names no key (shop,
     * addresses, carts, orders, wishlists, uploads, ...) into a lookup on
     * `store_user_id`, a column no table has. The store's tables all use the
     * `user_id` the old class name produced.
     *
     * @return string
     */
    public function getForeignKey()
    {
        return 'user_id';
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new EmailVerificationNotification);
    }

    public function sendTermsAgreementNotification()
    {
        $this->notify(new TermsAgreementNotification);
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
        'device_token',
        'address',
        'city',
        'company_name',
        'bank_account_number',
        'postal_code',
        'phone',
        'country',
        'provider_id',
        'shipping_address',
        'company_address',
        'tax_number',
        'trade_license',
        'from_api',
        'member_serial',
        'synched_with_otajer',
        'AccSysID',
        'registration_completed',
        'rep_serial',
        'admin_notes',
        'activity_status',
        'device_key',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'verification_code',
        'device_token',
        'device_key',
        'bank_account_number',
        'provider_id',
        'tax_number',
    ];

    /**
     * `user_type`, `is_rep`, `admin_verified`, `email_verified_at`,
     * `verification_code` and `rep_id` are deliberately not in $fillable: they
     * decide privilege and verification, so they are only ever set by code
     * that means to (forceFill() or a property assignment), never from a
     * request's input.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'is_rep' => 'boolean',
            'admin_verified' => 'boolean',
            'from_api' => 'boolean',
            'synched_with_otajer' => 'boolean',
            'registration_completed' => 'boolean',
        ];
    }

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

    /** @return HasOne<Staff, $this> */
    public function staff(): HasOne
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
        return $this->hasMany(Order::class, 'seller_id');
    }

    public function seller_sales()
    {
        return $this->hasMany(OrderDetail::class, 'seller_id');
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

        return $cart ? $cart->belongsTo(StoreUser::class, 'for_customer', 'id') : $this->belongsTo(StoreUser::class, 'for_customer', 'id')->where('id', '=', null);
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

    /** @return BelongsTo<RepresentativePackage, $this> */
    public function representativePackage(): BelongsTo
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
        $startOfDay = Carbon::now()->startOfDay();
        $endOfDay = Carbon::now()->endOfDay();

        return $this->rep_visits
            ->where('visit_date', '>=', $startOfDay)
            ->where('visit_date', '<=', $endOfDay)
            ->sortByDesc('created_at')
            ->first();
    }

    /** @return HasMany<UserPageView, $this> */
    public function pageViews(): HasMany
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
