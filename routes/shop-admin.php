<?php

use App\Http\Controllers\BusinessSettingsController;
use App\Http\Controllers\Store\Admin\ClassifiedProductController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Store\Admin\SellerController;
use App\Http\Controllers\Store\Admin\SellerCommissionController;
use App\Http\Controllers\Store\Admin\SellerVerificationFormController;
use App\Http\Controllers\Store\Admin\SellerPaymentController;
use App\Http\Controllers\Store\Admin\SellerWithdrawRequestController;
use App\Http\Controllers\Store\Admin\AddressController;
use App\Http\Controllers\Store\Admin\AttributeController;
use App\Http\Controllers\Store\Admin\AttributeValueController;
use App\Http\Controllers\Store\Admin\BlogCategoryController;
use App\Http\Controllers\Store\Admin\BlogController as AdminBlogController;
use App\Http\Controllers\Store\Admin\BrandController;
use App\Http\Controllers\Store\Admin\BrandDiscountController;
use App\Http\Controllers\Store\Admin\CampaignProductController;
use App\Http\Controllers\Store\Admin\CarrierController;
use App\Http\Controllers\Store\Admin\CategoryController;
use App\Http\Controllers\Store\Admin\CityController;
use App\Http\Controllers\Store\Admin\ColorController;
use App\Http\Controllers\Store\Admin\ConversationController;
use App\Http\Controllers\Store\Admin\CountryController;
use App\Http\Controllers\Store\Admin\CouponController;
use App\Http\Controllers\Store\Admin\CurrencyController;
use App\Http\Controllers\Store\Admin\CustomerController;
use App\Http\Controllers\Store\Admin\CustomerNotificationController;
use App\Http\Controllers\Store\Admin\CustomerPackageController;
use App\Http\Controllers\Store\Admin\DashboardController;
use App\Http\Controllers\Store\Admin\DigitalProductController;
use App\Http\Controllers\Store\Admin\DriverController;
use App\Http\Controllers\Store\Admin\DynamicPopupController;
use App\Http\Controllers\Store\Admin\FlashDealController;
use App\Http\Controllers\Store\Admin\GeneralSettingsController;
use App\Http\Controllers\Store\Admin\GoogleMapController;
use App\Http\Controllers\Store\Admin\LanguageController;
use App\Http\Controllers\Store\Admin\LegacyWebsiteScriptsController;
use App\Http\Controllers\Store\Admin\NewsletterController;
use App\Http\Controllers\Store\Admin\NotificationController;
use App\Http\Controllers\Store\Admin\OfferController;
use App\Http\Controllers\Store\Admin\OffersCatalogController;
use App\Http\Controllers\Store\Admin\OrderConfigurationController;
use App\Http\Controllers\Store\Admin\OrderController;
use App\Http\Controllers\Store\Admin\OrderEntryController;
use App\Http\Controllers\Store\Admin\PickupPointController;
use App\Http\Controllers\Store\Admin\ProductBulkUploadController;
use App\Http\Controllers\Store\Admin\ProductController as StoreProductController;
use App\Http\Controllers\Store\Admin\ProductQueryController;
use App\Http\Controllers\Store\Admin\ProductSearchController;
use App\Http\Controllers\Store\Admin\ProfileController;
use App\Http\Controllers\Store\Admin\ReportController;
use App\Http\Controllers\Store\Admin\RepresentativePackageController;
use App\Http\Controllers\Store\Admin\RepresentativePaymentController;
use App\Http\Controllers\Store\Admin\ReviewController;
use App\Http\Controllers\Store\Admin\RoleController;
use App\Http\Controllers\Store\Admin\SettingsController;
use App\Http\Controllers\Store\Admin\ShippingConfigurationController;
use App\Http\Controllers\Store\Admin\StaffController;
use App\Http\Controllers\Store\Admin\StateController;
use App\Http\Controllers\Store\Admin\SubscriberController;
use App\Http\Controllers\Store\Admin\SupportTicketController as AdminSupportTicketController;
use App\Http\Controllers\Store\Admin\TaxController;
use App\Http\Controllers\Store\Admin\TripCheckpointController;
use App\Http\Controllers\Store\Admin\TripController;
use App\Http\Controllers\Store\Admin\TripMapController;
use App\Http\Controllers\Store\Admin\TripOrderController;
use App\Http\Controllers\Store\Admin\TruckController;
use App\Http\Controllers\Store\Admin\UploadController;
use App\Http\Controllers\Store\Admin\WebsiteAppearanceController;
use App\Http\Controllers\Store\Admin\WebsiteFooterController;
use App\Http\Controllers\Store\Admin\WebsiteHeaderController;
use App\Http\Controllers\Store\Admin\WebsiteHomeController;
use App\Http\Controllers\Store\Admin\WebsitePageController;
use App\Http\Controllers\Store\Admin\ZoneController;
use App\Http\Controllers\Store\Admin\VisitController;
use App\Models\Ticket;
use Aws\Middleware;
use Illuminate\Contracts\Encryption\DecryptException;

/*
  |--------------------------------------------------------------------------
  | Admin Routes
  |--------------------------------------------------------------------------
  |
  | Here is where you can register admin routes for your application. These
  | routes are loaded by the RouteServiceProvider within a group which
  | contains the "web" middleware group. Now create something great!
  |
 */
// test majd
Route::get('/admin', [DashboardController::class, 'index'])->name('shop.admin.dashboard')->middleware(['auth', 'admin']);
Route::group(['prefix' => 'admin', 'middleware' => ['auth', 'admin']], function () {

    // category
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('/categories/edit/{category}', [CategoryController::class, 'edit'])->name('categories.edit');
    Route::match(['PUT', 'PATCH'], '/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/destroy/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    Route::post('/categories/featured', [CategoryController::class, 'updateFeatured'])->name('categories.featured');

    // Brand
    Route::get('/brands', [BrandController::class, 'index'])->name('brands.index');
    Route::post('/brands', [BrandController::class, 'store'])->name('brands.store');
    Route::get('/brands/edit/{brand}', [BrandController::class, 'edit'])->name('brands.edit');
    Route::match(['PUT', 'PATCH'], '/brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
    Route::delete('/brands/destroy/{brand}', [BrandController::class, 'destroy'])->name('brands.destroy');

    // Brand-wide product discounts
    Route::get('/brands-wise-product-discount', [BrandDiscountController::class, 'index'])->name('brands_wise_product_discount');
    Route::post('/set-product-discount', [BrandDiscountController::class, 'update'])->name('set_product_discount');

    // Product lists and forms share Inertia; seller action aliases fix their scope.
    Route::get('/products/admin', [StoreProductController::class, 'inHouse'])->name('products.admin');
    Route::get('/products/all', [StoreProductController::class, 'all'])->name('products.all');
    Route::post('/products/{product}/published', [StoreProductController::class, 'updatePublished'])->name('products.list.published');
    Route::post('/products/{product}/featured', [StoreProductController::class, 'updateFeatured'])->name('products.list.featured');
    Route::post('/products/{product}/for-sale', [StoreProductController::class, 'updateForSale'])->name('products.list.for_sale');
    Route::post('/products/{product}/duplicate', [StoreProductController::class, 'duplicate'])->name('products.list.duplicate');
    Route::delete('/products/{product}', [StoreProductController::class, 'destroy'])->name('products.list.destroy');
    Route::post('/products/bulk-delete', [StoreProductController::class, 'bulkDestroy'])->name('products.list.bulk_destroy');
    Route::get('/products/create', [StoreProductController::class, 'create'])->name('products.create');
    Route::post('/products/store', [StoreProductController::class, 'store'])->name('products.store');
    Route::post('/products/variant-combinations', [StoreProductController::class, 'variantCombinations'])->name('products.variant_combinations');
    Route::get('/products/admin/{product}/edit', [StoreProductController::class, 'edit'])->name('products.admin.edit');
    Route::get('/products/seller/{product}/edit', [StoreProductController::class, 'sellerEdit'])->name('products.seller.edit');
    Route::post('/products/update/{product}', [StoreProductController::class, 'update'])->name('products.update');
    Route::post('/products/{product}/variant-combinations', [StoreProductController::class, 'editVariantCombinations'])->name('products.edit_variant_combinations');
    Route::get('/products/seller', [StoreProductController::class, 'seller'])->name('products.seller');
    Route::post('/products/seller/{product}/todays-deal', [StoreProductController::class, 'updateTodaysDeal'])->defaults('productList', 'seller')->name('products.todays_deal');
    Route::post('/products/seller/{product}/for-sale', [StoreProductController::class, 'updateSaleStatus'])->defaults('productList', 'seller')->name('products.update_sale');
    Route::post('/products/seller/{product}/featured', [StoreProductController::class, 'sellerFeatured'])->defaults('productList', 'seller')->name('products.featured');
    Route::post('/products/seller/{product}/published', [StoreProductController::class, 'sellerPublished'])->defaults('productList', 'seller')->name('products.published');
    Route::post('/products/seller/{product}/approved', [StoreProductController::class, 'updateProductApproval'])->name('products.approved');
    Route::post('/products/duplicate/{product}', [StoreProductController::class, 'sellerDuplicate'])->defaults('productList', 'seller')->name('products.duplicate');
    Route::delete('/products/destroy/{product}', [StoreProductController::class, 'sellerDestroy'])->defaults('productList', 'seller')->name('products.destroy');
    Route::delete('/bulk-product-delete', [StoreProductController::class, 'sellerBulkDestroy'])->defaults('productList', 'seller')->name('bulk-product-delete');
    Route::controller(ProductController::class)->group(function () {
        Route::post('/products/fetch', 'fetch')->name('products.fetch')->middleware('permission:add_new_product');
        Route::get('products/get-details/{id}', 'getDetails')->name('products.get-details');
        Route::post('/products/sku_combination', 'sku_combination')->name('products.sku_combination');
    });

    Route::get('/offers/index', [OffersCatalogController::class, 'index'])->name('offers.index');
    Route::post('/offers/store', [OffersCatalogController::class, 'store'])->name('offers.store');
    Route::get('/offers/download', [OffersCatalogController::class, 'download'])->name('offers.download');
    Route::delete('/offers/destroy/{catalog}', [OffersCatalogController::class, 'destroy'])->name('offers.destroy');
    Route::post('/products/add-more-choice-option', [ProductSearchController::class, 'attributeValues'])->name('products.add-more-choice-option');

    // Digital products: the list, its row actions and the create and edit
    // forms are Inertia.
    Route::get('/digitalproducts', [DigitalProductController::class, 'index'])->name('digitalproducts.index');
    Route::get('/digitalproducts/create', [DigitalProductController::class, 'create'])->name('digitalproducts.create');
    Route::post('/digitalproducts', [DigitalProductController::class, 'store'])->name('digitalproducts.store');
    Route::get('/digitalproducts/edit/{product}', [DigitalProductController::class, 'edit'])->name('digitalproducts.edit');
    Route::match(['PUT', 'PATCH'], '/digitalproducts/{product}', [DigitalProductController::class, 'update'])->name('digitalproducts.update');
    Route::delete('/digitalproducts/destroy/{product}', [DigitalProductController::class, 'destroy'])->name('digitalproducts.destroy');
    Route::get('/digitalproducts/download/{id}', [DigitalProductController::class, 'download'])->name('digitalproducts.download');
    Route::post('/digitalproducts/{product}/published', [DigitalProductController::class, 'updatePublished'])->name('digitalproducts.published');
    Route::post('/digitalproducts/{product}/featured', [DigitalProductController::class, 'updateFeatured'])->name('digitalproducts.featured');
    Route::post('/digitalproducts/{product}/todays-deal', [DigitalProductController::class, 'updateTodaysDeal'])->name('digitalproducts.todays_deal');

    // Bulk import/export; downloads remain server-rendered PDFs.
    Route::get('/product-bulk-export', [ProductBulkUploadController::class, 'export'])->name('product_bulk_export.index');
    Route::get('/product-bulk-upload/index', [ProductBulkUploadController::class, 'index'])->name('product_bulk_upload.index');
    Route::post('/bulk-product-upload', [ProductBulkUploadController::class, 'bulkUpload'])->name('bulk_product_upload');
    Route::get('/bulk-upload/download/category', [ProductBulkUploadController::class, 'pdfDownloadCategory'])->name('pdf.download_category');
    Route::get('/bulk-upload/download/brand', [ProductBulkUploadController::class, 'pdfDownloadBrand'])->name('pdf.download_brand');
    Route::get('/bulk-upload/download/seller', [ProductBulkUploadController::class, 'pdfDownloadSeller'])->name('pdf.download_seller');

    // Seller account and payout administration.
    Route::get('/sellers', [SellerController::class, 'index'])->name('sellers.index');
    Route::get('/sellers/create', [SellerController::class, 'create'])->name('sellers.create');
    Route::post('/sellers', [SellerController::class, 'store'])->name('sellers.store');
    Route::get('/sellers/{seller}/edit', [SellerController::class, 'edit'])->name('sellers.edit');
    Route::match(['PUT', 'PATCH'], '/sellers/{seller}', [SellerController::class, 'update'])->name('sellers.update');
    Route::post('/sellers_ban/{seller}', [SellerController::class, 'ban'])->name('sellers.ban');
    Route::delete('/sellers/destroy/{seller}', [SellerController::class, 'destroy'])->name('sellers.destroy');
    Route::delete('/bulk-seller-delete', [SellerController::class, 'bulkDestroy'])->name('bulk-seller-delete');
    Route::get('/sellers/view/{seller}/verification', [SellerController::class, 'verification'])->name('sellers.show_verification_request');
    Route::get('/sellers/verification-file/{name}', [SellerController::class, 'verificationFile'])->name('sellers.verification_file');
    Route::post('/sellers/approve/{seller}', [SellerController::class, 'approve'])->name('sellers.approve');
    Route::post('/sellers/reject/{seller}', [SellerController::class, 'reject'])->name('sellers.reject');
    Route::get('/sellers/login/{seller}', [SellerController::class, 'login'])->name('sellers.login');
    Route::post('/sellers/profile_modal', [SellerController::class, 'profile'])->name('sellers.profile_modal');
    Route::post('/sellers/approved', [SellerController::class, 'approved'])->name('sellers.approved');
    Route::post('/sellers/payment_modal', [SellerPaymentController::class, 'modal'])->name('sellers.payment_modal');
    Route::get('/seller/payments', [SellerPaymentController::class, 'index'])->name('sellers.payment_histories');
    Route::get('/seller/payments/show/{seller}', [SellerPaymentController::class, 'show'])->name('sellers.payment_history');
    Route::get('/withdraw_requests', [SellerWithdrawRequestController::class, 'index'])->name('withdraw_requests.index');
    Route::get('/withdraw_requests_all', [SellerWithdrawRequestController::class, 'index'])->name('withdraw_requests_all');
    Route::get('/withdraw_requests/create', [SellerWithdrawRequestController::class, 'create'])->name('withdraw_requests.create');
    Route::get('/withdraw_requests/{withdrawRequest}', [SellerWithdrawRequestController::class, 'show'])->name('withdraw_requests.show');
    Route::get('/withdraw_requests/{withdrawRequest}/edit', [SellerWithdrawRequestController::class, 'edit'])->name('withdraw_requests.edit');
    Route::match(['PUT', 'PATCH'], '/withdraw_requests/{withdrawRequest}', [SellerWithdrawRequestController::class, 'update'])->name('withdraw_requests.update');
    Route::delete('/withdraw_requests/{withdrawRequest}', [SellerWithdrawRequestController::class, 'destroy'])->name('withdraw_requests.destroy');
    Route::post('/withdraw_request/payment_modal', [SellerWithdrawRequestController::class, 'paymentModal'])->name('withdraw_request.payment_modal');
    Route::post('/withdraw_request/message_modal', [SellerWithdrawRequestController::class, 'messageModal'])->name('withdraw_request.message_modal');

    // Customers and representatives share customer records, with explicit write gates.
    Route::controller(CustomerController::class)->group(function () {
        Route::get('customers', 'index')->name('customers.index');
        Route::get('customers/create', 'create')->name('customers.create');
        Route::post('customer/store', 'store')->name('customers.store');
        Route::get('customers/archive/get', 'archive')->name('customers.archive');
        Route::get('customers/{customer}/edit', 'edit')->name('customers.edit');
        Route::post('customers/{customer}', 'update')->name('customers.update');
        Route::delete('customers/{customer}', 'destroy')->name('customers.destroy');
        Route::post('customers/restore/{customer}', 'restore')->name('customers.restore');
        Route::get('customer/change_password/{customer}', 'changePassword')->name('change_password');
        Route::post('customers/{customer}/password', 'updatePassword')->name('update_password');
        Route::get('customer/whatsapp-contact/{customer}', 'whatsappContact')->name('customers.whatsapp_contact');
        Route::post('customer/important/{customer}', 'important')->name('customers.important');
        Route::post('customer/not-important/{customer}', 'notImportant')->name('customers.not_important');
        Route::post('customers/{customer}/activity', 'changeActivityStatus')->name('customers.change_activity_status');
        Route::post('customers/{customer}/notes', 'updateNotes')->name('customers.update_customer_notes');
        Route::get('customer/{customer}/pages-views', 'pageViews')->name('customers.page-views');
        Route::post('customers/verify/{customer}', 'verify')->name('customers.verify');
        Route::post('customers_ban/{customer}', 'ban')->name('customers.ban');
        Route::get('customers/login/{customer}', 'login')->name('customers.login');
        Route::post('bulk-customer-delete', 'bulkDestroy')->name('bulk-customer-delete');
        Route::get('customers/download_trade_license/{filename}', 'downloadTradeLicense')->name('download.trade_license');
        Route::post('customers/terms/sendMail/{customer}', 'sendAgreement')->name('customers.sendAgreement');
        Route::get('representatives', 'repIndex')->name('representatives.index');
        Route::get('representatives/create', 'repCreate')->name('representatives.create');
        Route::post('representatives/store', 'repStore')->name('representatives.store');
    });
    Route::controller(AddressController::class)->group(function () {
        Route::get('addresses/states', 'states')->name('admin.addresses.states');
        Route::get('addresses/cities', 'cities')->name('admin.addresses.cities');
        Route::post('customers/{customer}/addresses', 'store')->name('admin.customers.addresses.store');
        Route::put('customers/{customer}/addresses/{address}', 'update')->name('admin.customers.addresses.update');
        Route::delete('customers/{customer}/addresses/{address}', 'destroy')->name('admin.customers.addresses.destroy');
        Route::post('customers/{customer}/addresses/{address}/default', 'setDefault')->name('admin.customers.addresses.default');
    });

    Route::controller(RepresentativePaymentController::class)->group(function () {
        Route::get('admin/representatives/payments/index', 'index')->name('admin.rep.payments.index');
        Route::post('admin/representatives/payments/accept/{payment}', 'accept')->name('admin.rep.payments.accept');
        Route::post('admin/representatives/payments/reject/{payment}', 'reject')->name('admin.rep.payments.reject');
        Route::get('admin/representatives/payments/export/{payment}', 'export')->name('admin.rep.payments.export');
    });

    Route::controller(VisitController::class)->group(function () {
        Route::get('/representatives/visits/index', 'index')->name('admin.rep.visits.index');
        Route::get('/representatives/visits/show/{visit}', 'show')->name('admin.rep.visits.show');
        Route::get('/representatives/visits/rep-show/{user}', 'show_all_visits_in_a_day')->name('admin.rep.visits.rep-show');
        Route::put('/representatives/visits/{visit}/note', 'update_note')->name('admin.rep.visits.note')->middleware('permission:view_representative_visits');
        Route::delete('/representatives/visits/delete/{visit}', 'destroy')->name('admin.rep.visits.destroy');
        Route::post('/representatives/visits/bulk-visit-delete', 'bulkDestroy')->name('bulk-visit-delete');
    });
    Route::controller(RepresentativePackageController::class)->group(function () {
        Route::get('/representatives/packages/index', 'index')->name('rep.packages.index');
        Route::get('/representatives/packages/create', 'create')->name('rep.packages.create');
        Route::post('/representatives/packages/store', 'store')->name('rep.packages.store');
        Route::get('/representatives/packages/{package}/edit', 'edit')->name('rep.packages.edit');
        Route::put('/representatives/packages/{package}', 'update')->name('rep.packages.update');
        Route::delete('/representatives/packages/delete/{package}', 'destroy')->name('rep.packages.destroy');
        Route::post('/representatives/packages/set_package', 'assign')->name('rep.set-package');
    });
    Route::group(['prefix' => 'trips'], function () {
        // The trip lists and their row actions (.ai/archive/store-backoffice/changes-20261014.md §24)
        // and the trip create and edit forms (§25).
        Route::controller(TripController::class)->group(function () {
            Route::get('/index', 'index')->name('trips.index');
            Route::get('/today-trips', 'today')->name('trips.today-trips');
            Route::get('/create', 'create')->name('trips.create');
            Route::post('/store', 'store')->name('trips.store');
            Route::get('/{trip}/edit', 'edit')->name('trips.edit');
            Route::put('/{trip}', 'update')->name('trips.update');
            Route::put('/{trip}/status', 'updateStatus')->name('trips.update-status');
            Route::put('/{trip}/notes', 'updateNotes')->name('trips.update-notes');
            Route::delete('/{trip}', 'destroy')->name('trips.destroy');
            Route::post('/bulk-trips-delete', 'bulkDestroy')->name('trips.bulk-trip-delete');
        });

        // The trip orders list and its order actions (§26).
        Route::controller(TripOrderController::class)->group(function () {
            Route::get('/orders', 'index')->name('trips.orders');
            Route::post('/orders/bulk-change-delivery-status', 'bulkUpdateDeliveryStatus')->name('trips.orders.bulk-change-delivery-status');
            Route::put('/orders/{order}/invoice-number', 'updateInvoiceNumber')->name('trips.orders.update_invoice_number');
            Route::put('/orders/{order}/delivered', 'markDelivered')->name('trips.orders.update_order_status_to_delivered');
        });

        // The trip detail with its checkpoint notes, status and CMR download (§27).
        Route::controller(TripCheckpointController::class)->group(function () {
            Route::get('/show/{trip}', 'show')->name('trips.show');
            Route::put('/checkpoints/{checkpoint}/notes', 'updateNotes')->name('checkpoints.update-notes');
            Route::put('/checkpoints/{checkpoint}/status', 'updateStatus')->name('checkpoints.update-status');
            Route::get('/print-cmr/{checkpoint}', 'printCmr')->name('checkpoints.print-cmr');
        });

        // The trip map picker and its writer (§30).
        Route::controller(TripMapController::class)->group(function () {
            Route::get('/create-from-map', 'create')->name('trips.create-from-map');
            Route::post('/store-from-map', 'store')->name('trips.store-from-map');
        });

        // The trip forms' lookups: the driver API's own handlers, reached with
        // the admin's session instead of an app token.
        Route::get('/available-vehicles', 'App\Http\Controllers\Api\V2\Trip\DriverController@getAvailableVehicles')->name('trips.available-vehicles')->middleware('permission:add_trip|edit_trip');
        Route::get('/user-coordinates/{id}', 'App\Http\Controllers\Api\V2\Trip\DriverController@getLongLatitude')->name('trips.user-coordinates')->middleware('permission:add_trip|edit_trip');

        Route::get('/drivers/index', [DriverController::class, 'index'])->name('drivers.index');
        Route::get('/drivers/create', [DriverController::class, 'create'])->name('drivers.create');
        Route::post('/drivers/store', [DriverController::class, 'store'])->name('drivers.store');
        Route::get('/drivers/{driver}/edit', [DriverController::class, 'edit'])->name('drivers.edit');
        Route::put('/drivers/{driver}', [DriverController::class, 'update'])->name('drivers.update');
        Route::delete('/drivers/{driver}', [DriverController::class, 'destroy'])->name('drivers.destroy');

        Route::get('/trucks/index', [TruckController::class, 'index'])->name('trucks.index');
        Route::get('/trucks/create', [TruckController::class, 'create'])->name('trucks.create');
        Route::get('/trucks/photos', [TruckController::class, 'photos'])->name('trucks.photos');
        Route::post('/trucks/store', [TruckController::class, 'store'])->name('trucks.store');
        Route::get('/trucks/{truck}/edit', [TruckController::class, 'edit'])->name('trucks.edit');
        Route::put('/trucks/{truck}', [TruckController::class, 'update'])->name('trucks.update');
        Route::get('/trucks/{truck}/qr', [TruckController::class, 'qr'])->name('trucks.qr');

    });

    // Newsletter
    Route::controller(NewsletterController::class)->group(function () {
        Route::get('/newsletter', 'index')->name('newsletters.index');
        Route::get('/newsletter/recipients', 'recipients')->name('newsletters.recipients');
        Route::post('/newsletter/send', 'send')->name('newsletters.send');
    });

    // Dynamic Popup
    Route::get('/dynamic-popups', [DynamicPopupController::class, 'index'])->name('dynamic-popups.index');
    Route::get('/dynamic-popups/create', [DynamicPopupController::class, 'create'])->name('dynamic-popups.create');
    Route::post('/dynamic-popups', [DynamicPopupController::class, 'store'])->name('dynamic-popups.store');
    Route::get('/dynamic-popups/{popup}/edit', [DynamicPopupController::class, 'edit'])->name('dynamic-popups.edit');
    Route::put('/dynamic-popups/{popup}', [DynamicPopupController::class, 'update'])->name('dynamic-popups.update');
    Route::delete('/dynamic-popups/{popup}', [DynamicPopupController::class, 'destroy'])->name('dynamic-popups.destroy');
    Route::controller(DynamicPopupController::class)->group(function () {
        Route::post('/bulk-dynamic-popup-delete', 'bulkDestroy')->name('bulk-dynamic-popup-delete');
        Route::post('/dynamic-popups-update-status', 'updateStatus')->name('dynamic-popups.update-status');
    });

    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::match(['PUT', 'PATCH'], '/profile/{profile}', [ProfileController::class, 'update'])->name('profile.update');

    // Business Settings
    Route::controller(BusinessSettingsController::class)->group(function () {
        Route::post('/business-settings/update/seller-commission-activation', 'updateSellerCommissionActivation')->name('business_settings.seller_commission.activation')->middleware('RaizerSuperAdmin');

        Route::get('/google-map', [GoogleMapController::class, 'index'])->name('google-map.index');

        Route::post('/env_key_update', 'env_key_update')->name('env_key_update.update');
        Route::post('/google-map', [GoogleMapController::class, 'update'])->name('google-map.update');

        Route::get('/verification/form', [SellerVerificationFormController::class, 'index'])->name('seller_verification_form.index');
        Route::post('/verification/form', [SellerVerificationFormController::class, 'update'])->name('seller_verification_form.update');
        Route::get('/vendor_commission', [SellerCommissionController::class, 'index'])->name('business_settings.vendor_commission');
        Route::post('/vendor_commission_update', [SellerCommissionController::class, 'update'])->name('business_settings.vendor_commission.update');

        // Shipping Configuration

        // Order Configuration
    });

    // General settings: a typed writer for the form's own keys only.
    Route::get('/general-setting', [GeneralSettingsController::class, 'edit'])->name('general_setting.index');
    Route::post('/general-setting', [GeneralSettingsController::class, 'update'])->name('general_setting.update');

    // Migrated plain settings: each write has its page permission and keys.
    Route::get('/activation', [SettingsController::class, 'activation'])->name('activation.index');
    Route::post('/activation', [SettingsController::class, 'updateActivation'])->name('business_settings.update.activation');
    Route::get('/smtp-settings', [SettingsController::class, 'smtp'])->name('smtp_settings.index');
    Route::get('/social-login', [SettingsController::class, 'socialLogin'])->name('social_login.index');
    Route::get('/facebook-chat', [SettingsController::class, 'facebookChat'])->name('facebook_chat.index');
    Route::post('/facebook-chat', [SettingsController::class, 'updateFacebookChat'])->name('facebook_chat.update');
    Route::get('/facebook-comment', [SettingsController::class, 'facebookComments'])->name('facebook-comment');
    Route::post('/facebook-comment', [SettingsController::class, 'updateFacebookComments'])->name('facebook-comment.update');
    Route::get('/google-analytics', [SettingsController::class, 'analytics'])->name('google_analytics.index');
    Route::post('/google-analytics', [SettingsController::class, 'updateAnalytics'])->name('google_analytics.update');
    Route::get('/google-recaptcha', [SettingsController::class, 'recaptcha'])->name('google_recaptcha.index');
    Route::post('/google-recaptcha', [SettingsController::class, 'updateRecaptcha'])->name('google_recaptcha.update');
    Route::get('/google-firebase', [SettingsController::class, 'firebase'])->name('google-firebase.index');
    Route::post('/google-firebase', [SettingsController::class, 'updateFirebase'])->name('google-firebase.update');
    Route::get('/payment-method', [SettingsController::class, 'payments'])->name('payment_method.index');
    Route::post('/payment-method', [SettingsController::class, 'updatePayments'])->name('payment_method.update');
    Route::get('/file_system', [SettingsController::class, 'filesystem'])->name('file_system.index');
    Route::post('/google-analytics/pixel', [SettingsController::class, 'updateAnalytics'])->name('facebook_pixel.update');
    Route::post('/smtp-settings/test', [SettingsController::class, 'testSmtp'])->name('test.smtp');

    // Shipping and order settings have page-specific write permissions and keys.
    Route::get('/shipping_configuration', [ShippingConfigurationController::class, 'index'])->name('shipping_configuration.index');
    Route::post('/shipping_configuration/update', [ShippingConfigurationController::class, 'update'])->name('shipping_configuration.update');
    Route::get('/order-configuration', [OrderConfigurationController::class, 'index'])->name('order_configuration.index');
    Route::post('/order-configuration', [OrderConfigurationController::class, 'update'])->name('order_configuration.update');
    Route::post('/order-configuration/representative', [OrderConfigurationController::class, 'updateRepresentative'])->name('order_configuration.representative.update');
    Route::get('/product/search', [ProductSearchController::class, 'search'])->name('order_configuration.product-search');

    // Currency forms are Vue dialogs; create/edit URLs also support direct visits.
    Route::get('/currency', [CurrencyController::class, 'index'])->name('currency.index');
    Route::get('/currency/create', [CurrencyController::class, 'create'])->name('currency.create');
    Route::get('/currency/{currency}/edit', [CurrencyController::class, 'edit'])->name('currency.edit');
    Route::post('/currency/store', [CurrencyController::class, 'store'])->name('currency.store');
    Route::put('/your-currency/{currency}', [CurrencyController::class, 'update'])->name('your_currency.update');
    Route::post('/currency/{currency}/status', [CurrencyController::class, 'updateStatus'])->name('currency.update_status');
    Route::post('/currency/settings', [CurrencyController::class, 'configure'])->name('currency.settings.update');

    // Tax is added inline; the orphan create and empty show routes are retired.
    Route::get('/tax', [TaxController::class, 'index'])->name('tax.index');
    Route::post('/tax', [TaxController::class, 'store'])->name('tax.store');
    Route::get('/tax/{tax}/edit', [TaxController::class, 'edit'])->name('tax.edit');
    Route::put('/tax/{tax}', [TaxController::class, 'update'])->name('tax.update');
    Route::delete('/tax/{tax}', [TaxController::class, 'destroy'])->name('tax.destroy');
    Route::post('/tax/{tax}/status', [TaxController::class, 'updateStatus'])->name('taxes.tax-status');

    // Database languages and translations (storefront, seller, emails, PDFs, mobile).
    Route::get('/languages', [LanguageController::class, 'index'])->name('languages.index');
    Route::get('/languages/create', [LanguageController::class, 'create'])->name('languages.create');
    Route::post('/languages', [LanguageController::class, 'store'])->name('languages.store');
    Route::post('/languages/update_rtl_status', [LanguageController::class, 'updateRtlStatus'])->name('languages.update_rtl_status');
    Route::post('/languages/update-status', [LanguageController::class, 'updateStatus'])->name('languages.update-status');
    Route::post('/languages/key_value_store', [LanguageController::class, 'storeTranslations'])->name('languages.key_value_store');
    Route::post('/languages/app-translations/import', [LanguageController::class, 'importAppTranslations'])->name('app-translations.import');
    Route::get('/languages/app-translations/show/{language}', [LanguageController::class, 'showAppTranslations'])->name('app-translations.show');
    Route::post('/languages/app-translations/key_value_store', [LanguageController::class, 'storeAppTranslations'])->name('app-translations.store');
    Route::get('/languages/app-translations/export/{language}', [LanguageController::class, 'exportAppTranslations'])->name('app-translations.export');
    Route::get('/languages/{language}/edit', [LanguageController::class, 'edit'])->name('languages.edit');
    Route::post('/languages/{language}/update', [LanguageController::class, 'update'])->name('languages.update');
    Route::delete('/languages/{language}', [LanguageController::class, 'destroy'])->name('languages.destroy');
    Route::get('/languages/{language}', [LanguageController::class, 'show'])->name('languages.show');

    Route::post('/website/scripts/compatibility', [LegacyWebsiteScriptsController::class, 'update'])->name('website.scripts.compatibility.update');

    // Website setup: Inertia pages, each settings page writing only its own
    // keys (.ai/archive/store-backoffice/changes-20261013.md §20). The home settings
    // route stays ahead of the custom-page edit route, whose slug it shares.
    Route::get('/website/header', [WebsiteHeaderController::class, 'edit'])->name('website.header');
    Route::put('/website/header', [WebsiteHeaderController::class, 'update'])->name('website.header.update');
    Route::get('/website/footer', [WebsiteFooterController::class, 'edit'])->name('website.footer');
    Route::put('/website/footer', [WebsiteFooterController::class, 'update'])->name('website.footer.update');
    Route::get('/website/appearance', [WebsiteAppearanceController::class, 'edit'])->name('website.appearance');
    Route::put('/website/appearance', [WebsiteAppearanceController::class, 'update'])->name('website.appearance.update');
    Route::get('/website/custom-pages/edit/home', [WebsiteHomeController::class, 'edit'])->name('website.home.edit');
    Route::put('/website/home', [WebsiteHomeController::class, 'update'])->name('website.home.update');
    Route::get('/website/pages', [WebsitePageController::class, 'index'])->name('website.pages');
    Route::get('/website/custom-pages/create', [WebsitePageController::class, 'create'])->name('custom-pages.create');
    Route::post('/website/custom-pages', [WebsitePageController::class, 'store'])->name('custom-pages.store');
    Route::get('/website/custom-pages/edit/{page:slug}', [WebsitePageController::class, 'edit'])->name('custom-pages.edit');
    Route::match(['PUT', 'PATCH'], '/website/custom-pages/{page}', [WebsitePageController::class, 'update'])->name('custom-pages.update');
    Route::delete('/website/custom-pages/destroy/{page}', [WebsitePageController::class, 'destroy'])->name('custom-pages.destroy');

    // Staff roles and staff: Inertia pages (resources/js/pages/Store/Admin/Staff),
    // so explicit routes on bound models rather than the resource-plus-override
    // pairs the Blade modules still use (.ai/rules/store-backoffice-migration.md §3).
    Route::controller(RoleController::class)->group(function () {
        Route::get('/roles', 'index')->name('roles.index');
        Route::get('/roles/create', 'create')->name('roles.create');
        Route::post('/roles', 'store')->name('roles.store');
        Route::get('/roles/{role}/edit', 'edit')->name('roles.edit');
        Route::put('/roles/{role}', 'update')->name('roles.update');
        Route::delete('/roles/{role}', 'destroy')->name('roles.destroy');
    });

    Route::controller(StaffController::class)->group(function () {
        Route::get('/staffs', 'index')->name('staffs.index');
        Route::get('/staffs/create', 'create')->name('staffs.create');
        Route::post('/staffs', 'store')->name('staffs.store');
        Route::get('/staffs/{staff}/edit', 'edit')->name('staffs.edit');
        Route::put('/staffs/{staff}', 'update')->name('staffs.update');
        Route::delete('/staffs/{staff}', 'destroy')->name('staffs.destroy');
    });

    Route::get('/campaign-products', [CampaignProductController::class, 'index'])->name('admin.campaign-products.index');
    Route::get('/campaign-brands', [CampaignProductController::class, 'brands'])->name('admin.campaign-products.brands');
    Route::controller(FlashDealController::class)->group(function () {
        Route::get('/flash_deals', 'index')->name('flash_deals.index');
        Route::get('/flash_deals/create', 'create')->name('flash_deals.create');
        Route::post('/flash_deals', 'store')->name('flash_deals.store');
        Route::get('/flash_deals/{flashDeal}/edit', 'edit')->name('flash_deals.edit');
        Route::put('/flash_deals/{flashDeal}', 'update')->name('flash_deals.update');
        Route::delete('/flash_deals/{flashDeal}', 'destroy')->name('flash_deals.destroy');
        Route::put('/flash_deals/{flashDeal}/status', 'update_status')->name('flash_deals.update_status');
        Route::put('/flash_deals/{flashDeal}/featured', 'update_featured')->name('flash_deals.update_featured');
    });

    Route::get('/feat/order/create', [OrderEntryController::class, 'create'])->name('feat-order.create');
    Route::post('/feat/order/store', [OrderEntryController::class, 'store'])->name('feat-order.store');
    Route::get('/feat/new/order/trip/create', [OrderEntryController::class, 'shippingCreate'])->name('new.feat-order.trip.create');
    Route::post('/feat/new/order/trip/store', [OrderEntryController::class, 'shippingStore'])->name('new.feat-order.trip.store');
    Route::get('/feat/new/order/trip/edit/{order}', [OrderEntryController::class, 'edit'])->name('new.feat-order.trip.edit');
    Route::put('/feat/new/order/trip/update/{order}', [OrderEntryController::class, 'update'])->name('new.feat-order.trip.update');
    Route::get('/orders/search-options', [OrderEntryController::class, 'search'])->name('orders.search-options');

    Route::controller(OfferController::class)->group(function () {
        Route::get('/offer_deals/index', 'index')->name('offer_deals.index');
        Route::get('/offer_deals/create', 'create')->name('offer_deals.create');
        Route::get('/offer_deals/brand-create', 'brandCreate')->name('offer_deals.brands.create');
        Route::post('/offer_deals/store', 'store')->name('offer_deals.store');
        Route::get('/offer_deals/{offer}/edit', 'edit')->name('offer_deals.edit');
        Route::put('/offer_deals/{offer}', 'update')->name('offer_deals.update');
        Route::delete('/offer_deals/{offer}', 'destroy')->name('offer_deals.destroy');
        Route::put('/offer_deals/{offer}/status', 'update_status')->name('offer_deals.update_status');
        Route::put('/offer_deals/{offer}/repeatable', 'update_repeatable')->name('offer_deals.update_repeatable');
        Route::put('/offer_deals/{offer}/multiple-usage', 'update_multiple_usage')->name('offer_deals.update_multiple_usage');
    });

    // Subscribers: the storefront keeps subscribers.index and subscribers.store.
    Route::get('/subscribers', [SubscriberController::class, 'index'])->name('admin.subscribers.index');
    Route::delete('/subscribers/{subscriber}', [SubscriberController::class, 'destroy'])->name('subscriber.destroy');

    // Order
    Route::get('/all_orders', [OrderController::class, 'all_orders'])->name('all_orders.index');
    Route::get('/inhouse-orders', [OrderController::class, 'all_orders'])->name('inhouse_orders.index');
    Route::get('/seller_orders', [OrderController::class, 'all_orders'])->name('seller_orders.index');
    Route::get('/orders_by_pickup_point', [OrderController::class, 'all_orders'])->name('pick_up_point.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/show', [OrderController::class, 'show'])->name('all_orders.show');
    Route::get('/inhouse-orders/{order}/show', [OrderController::class, 'show'])->name('inhouse_orders.show');
    Route::get('/seller_orders/{order}/show', [OrderController::class, 'show'])->name('seller_orders.show');
    Route::get('/orders_by_pickup_point/{order}/show', [OrderController::class, 'show'])->name('pick_up_point.order_show');
    Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
    Route::post('/bulk-order-delete', [OrderController::class, 'bulk_order_delete'])->name('bulk-order-delete');
    Route::post('/orders/details', [OrderController::class, 'details'])->name('orders.details');
    Route::post('/orders/update_delivery_status', [OrderController::class, 'update_delivery_status'])->name('orders.update_delivery_status');
    Route::post('/orders/update_payment_status', [OrderController::class, 'update_payment_status'])->name('orders.update_payment_status');
    Route::post('/orders/update_tracking_code', [OrderController::class, 'update_tracking_code'])->name('orders.update_tracking_code');
    Route::post('/orders/update_manager_notes', [OrderController::class, 'update_manager_notes'])->name('orders.update_manager_notes');
    Route::post('/orders/item/edit_price', [OrderController::class, 'edit_item_price'])->name('order.item.edit_price');

    Route::post('/pay_to_seller', [SellerPaymentController::class, 'store'])->name('commissions.pay_to_seller');

    // Reports
    Route::get('/in_house_sale_report', [ReportController::class, 'in_house_sale_report'])->name('in_house_sale_report.index');
    Route::get('/seller_sale_report', [ReportController::class, 'seller_sale_report'])->name('seller_sale_report.index');
    Route::get('/stock_report', [ReportController::class, 'stock_report'])->name('stock_report.index');
    Route::get('/wish_report', [ReportController::class, 'wish_report'])->name('wish_report.index');
    Route::get('/user_search_report', [ReportController::class, 'user_search_report'])->name('user_search_report.index');
    Route::get('/commission-log', [ReportController::class, 'commission_history'])->name('commission-log.index');
    Route::get('/wallet-history', [ReportController::class, 'wallet_transaction_history'])->name('wallet-history.index');

    // Blog Section
    Route::get('/blog-category', [BlogCategoryController::class, 'index'])->name('blog-category.index');
    Route::get('/blog-category/create', [BlogCategoryController::class, 'create'])->name('blog-category.create');
    Route::post('/blog-category', [BlogCategoryController::class, 'store'])->name('blog-category.store');
    Route::get('/blog-category/{category}/edit', [BlogCategoryController::class, 'edit'])->name('blog-category.edit');
    Route::put('/blog-category/{category}', [BlogCategoryController::class, 'update'])->name('blog-category.update');
    Route::delete('/blog-category/{category}', [BlogCategoryController::class, 'destroy'])->name('blog-category.destroy');

    // Blog
    Route::controller(AdminBlogController::class)->group(function () {
        Route::get('/blog', 'index')->name('blog.index');
        Route::get('/blog/create', 'create')->name('blog.create');
        Route::post('/blog', 'store')->name('blog.store');
        Route::get('/blog/{blog}/edit', 'edit')->name('blog.edit');
        Route::put('/blog/{blog}', 'update')->name('blog.update');
        Route::delete('/blog/{blog}', 'destroy')->name('blog.destroy');
        Route::put('/blog/{blog}/status', 'changeStatus')->name('blog.change-status');
    });

    // Coupons: the seller's shared Blade partials and request remain in place.
    Route::get('/coupon', [CouponController::class, 'index'])->name('coupon.index');
    Route::get('/coupon/create', [CouponController::class, 'create'])->name('coupon.create');
    Route::get('/coupon/product-options', [CouponController::class, 'productOptions'])->name('coupon.product-options');
    Route::post('/coupon', [CouponController::class, 'store'])->name('coupon.store');
    Route::get('/coupon/{coupon}/edit', [CouponController::class, 'edit'])->name('coupon.edit');
    Route::put('/coupon/{coupon}', [CouponController::class, 'update'])->name('coupon.update');
    Route::delete('/coupon/{coupon}', [CouponController::class, 'destroy'])->name('coupon.destroy');

    // Reviews
    Route::get('/reviews', [ReviewController::class, 'index'])->name('admin.reviews.index');
    Route::post('/reviews/{review}/published', [ReviewController::class, 'updatePublished'])->name('reviews.published');

    // Support_Ticket
    Route::get('support_ticket/', [AdminSupportTicketController::class, 'index'])->name('support_ticket.admin_index');
    // Preserve encrypted identifiers in existing email links while binding the record.
    Route::bind('supportTicket', function (string $value) {
        try {
            $id = ctype_digit($value) ? $value : decrypt($value);
        } catch (DecryptException) {
            abort(404);
        }
        abort_unless((is_int($id) && $id > 0) || (is_string($id) && ctype_digit($id)), 404);

        return Ticket::query()->whereHas('user')->findOrFail((int) $id);
    });
    Route::get('support_ticket/{supportTicket}/show', [AdminSupportTicketController::class, 'show'])->name('support_ticket.admin_show');
    Route::post('support_ticket/reply', [AdminSupportTicketController::class, 'reply'])->name('support_ticket.admin_store');

    // Pickup_Points
    Route::get('/pick_up_points', [PickupPointController::class, 'index'])->name('pick_up_points.index');
    Route::get('/pick_up_points/create', [PickupPointController::class, 'create'])->name('pick_up_points.create');
    Route::post('/pick_up_points', [PickupPointController::class, 'store'])->name('pick_up_points.store');
    Route::get('/pick_up_points/{pickupPoint}/edit', [PickupPointController::class, 'edit'])->name('pick_up_points.edit');
    Route::put('/pick_up_points/{pickupPoint}', [PickupPointController::class, 'update'])->name('pick_up_points.update');
    Route::delete('/pick_up_points/{pickupPoint}', [PickupPointController::class, 'destroy'])->name('pick_up_points.destroy');

    // Back-office support; storefront conversation/message routes remain Blade.
    Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.admin_index');
    Route::get('conversations/{conversation}/show', [ConversationController::class, 'show'])->name('conversations.admin_show');
    Route::post('conversations/{conversation}/reply', [ConversationController::class, 'reply'])->name('conversations.admin_reply');
    Route::delete('conversations/{conversation}', [ConversationController::class, 'destroy'])->name('conversations.admin_destroy');

    Route::get('product-queries', [ProductQueryController::class, 'index'])->name('product_query.index');
    Route::get('product-queries/{productQuery}', [ProductQueryController::class, 'show'])->name('product_query.show');
    Route::put('product-queries/{productQuery}', [ProductQueryController::class, 'reply'])->name('product_query.reply');

    // Product attributes, values and colors
    Route::get('/attributes', [AttributeController::class, 'index'])->name('attributes.index');
    Route::post('/attributes', [AttributeController::class, 'store'])->name('attributes.store');
    Route::get('/attributes/edit/{attribute}', [AttributeController::class, 'edit'])->name('attributes.edit');
    Route::put('/attributes/{attribute}', [AttributeController::class, 'update'])->name('attributes.update');
    Route::delete('/attributes/destroy/{attribute}', [AttributeController::class, 'destroy'])->name('attributes.destroy');
    Route::get('/attributes/{attribute}', [AttributeValueController::class, 'index'])->name('attributes.show');
    Route::post('/store-attribute-value', [AttributeValueController::class, 'store'])->name('store-attribute-value');
    Route::get('/edit-attribute-value/{attributeValue}', [AttributeValueController::class, 'edit'])->name('edit-attribute-value');
    Route::put('/update-attribute-value/{attributeValue}', [AttributeValueController::class, 'update'])->name('update-attribute-value');
    Route::delete('/destroy-attribute-value/{attributeValue}', [AttributeValueController::class, 'destroy'])->name('destroy-attribute-value');
    Route::get('/colors', [ColorController::class, 'index'])->name('colors');
    Route::post('/colors/store', [ColorController::class, 'store'])->name('colors.store');
    Route::get('/colors/edit/{color}', [ColorController::class, 'edit'])->name('colors.edit');
    Route::put('/colors/update/{color}', [ColorController::class, 'update'])->name('colors.update');
    Route::delete('/colors/destroy/{color}', [ColorController::class, 'destroy'])->name('colors.destroy');

    // Customer package administration; purchase and API controllers remain separate.
    Route::get('/customer_packages', [CustomerPackageController::class, 'index'])->name('customer_packages.index');
    Route::get('/customer_packages/create', [CustomerPackageController::class, 'create'])->name('customer_packages.create');
    Route::post('/customer_packages', [CustomerPackageController::class, 'store'])->name('customer_packages.store');
    Route::get('/customer_packages/edit/{customerPackage}', [CustomerPackageController::class, 'edit'])->name('customer_packages.edit');
    Route::match(['PUT', 'PATCH'], '/customer_packages/{customerPackage}', [CustomerPackageController::class, 'update'])->name('customer_packages.update');
    Route::delete('/customer_packages/destroy/{customerPackage}', [CustomerPackageController::class, 'destroy'])->name('customer_packages.destroy');

    // Classified Products
    Route::get('/classified_products', [ClassifiedProductController::class, 'customer_product_index'])->name('classified_products');
    Route::put('/classified_products/{classifiedProduct}/published', [ClassifiedProductController::class, 'updatePublished'])->name('classified_products.published');
    Route::delete('/classified_products/{classifiedProduct}', [ClassifiedProductController::class, 'destroy_by_admin'])->name('classified_products.destroy');

    // Countries
    Route::get('/countries', [CountryController::class, 'index'])->name('countries.index');
    Route::post('/countries/{country}/status', [CountryController::class, 'updateStatus'])->name('countries.status');

    // States
    Route::get('/states', [StateController::class, 'index'])->name('states.index');
    Route::post('/states', [StateController::class, 'store'])->name('states.store');
    Route::get('/states/{state}/edit', [StateController::class, 'edit'])->name('states.edit');
    Route::put('/states/{state}', [StateController::class, 'update'])->name('states.update');
    Route::delete('/states/{state}', [StateController::class, 'destroy'])->name('states.destroy');
    Route::post('/states/{state}/status', [StateController::class, 'updateStatus'])->name('states.status');

    // Carriers
    Route::get('/carriers', [CarrierController::class, 'index'])->name('carriers.index');
    Route::get('/carriers/create', [CarrierController::class, 'create'])->name('carriers.create');
    Route::post('/carriers', [CarrierController::class, 'store'])->name('carriers.store');
    Route::post('/carriers/update_status', [CarrierController::class, 'updateStatus'])->name('carriers.update_status');
    Route::get('/carriers/{carrier}/edit', [CarrierController::class, 'edit'])->name('carriers.edit');
    Route::match(['PUT', 'PATCH'], '/carriers/{carrier}', [CarrierController::class, 'update'])->name('carriers.update');
    Route::delete('/carriers/{carrier}', [CarrierController::class, 'destroy'])->name('carriers.destroy');

    // Zones
    Route::get('/zones', [ZoneController::class, 'index'])->name('zones.index');
    Route::get('/zones/create', [ZoneController::class, 'create'])->name('zones.create');
    Route::post('/zones', [ZoneController::class, 'store'])->name('zones.store');
    Route::get('/zones/{zone}/edit', [ZoneController::class, 'edit'])->name('zones.edit');
    Route::put('/zones/{zone}', [ZoneController::class, 'update'])->name('zones.update');
    Route::delete('/zones/{zone}', [ZoneController::class, 'destroy'])->name('zones.destroy');

    Route::get('/cities', [CityController::class, 'index'])->name('cities.index');
    Route::post('/cities', [CityController::class, 'store'])->name('cities.store');
    Route::get('/cities/{city}/edit', [CityController::class, 'edit'])->name('cities.edit');
    Route::put('/cities/{city}', [CityController::class, 'update'])->name('cities.update');
    Route::delete('/cities/{city}', [CityController::class, 'destroy'])->name('cities.destroy');
    Route::post('/cities/{city}/status', [CityController::class, 'updateStatus'])->name('cities.status');

    // Admin JSON file manager; the shared Blade uploader remains in shop-web/seller.
    Route::get('/uploads', [UploadController::class, 'index'])->name('uploaded-files.index');
    Route::get('/uploads/create', [UploadController::class, 'create'])->name('uploaded-files.create');
    Route::get('/uploads/files', [UploadController::class, 'files'])->name('uploaded-files.files');
    Route::get('/uploads/previews', [UploadController::class, 'previews'])->name('uploaded-files.info');
    Route::post('/uploads', [UploadController::class, 'store'])->name('uploaded-files.store');
    Route::delete('/uploads/{upload}', [UploadController::class, 'destroy'])->name('uploaded-files.destroy');
    Route::post('/bulk-uploaded-files-delete', [UploadController::class, 'bulkDestroy'])->name('bulk-uploaded-files-delete');
    Route::post('/all-file', [UploadController::class, 'destroyAll'])->name('all-file');

    Route::get('/all-notification', [NotificationController::class, 'index'])->name('admin.all-notification');

    Route::post('/clear-cache', [DashboardController::class, 'clearCache'])->name('cache.clear')->middleware('RaizerSuperAdmin');

    Route::get('/notifications', [CustomerNotificationController::class, 'index'])->name('admin.notifications.all');
    Route::get('/notifications/create', [CustomerNotificationController::class, 'create'])->name('admin.notifications.create');
    Route::post('/notifications/send', [CustomerNotificationController::class, 'store'])->name('admin.notifications.send');
    Route::delete('/notifications/{notification}', [CustomerNotificationController::class, 'destroy'])->name('admin.notifications.delete');
});
