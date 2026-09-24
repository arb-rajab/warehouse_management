<?php

use App\Http\Controllers\AddonController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminNotificationController;
use App\Http\Controllers\AizUploadController;
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\BlogCategoryController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\BusinessSettingsController;
use App\Http\Controllers\CarrierController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\ConnectionController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerPackageController;
use App\Http\Controllers\CustomerProductController;
use App\Http\Controllers\DigitalProductController;
use App\Http\Controllers\DynamicPopupController;
use App\Http\Controllers\FeatureOrderController;
use App\Http\Controllers\FeatureOrderTripController;
use App\Http\Controllers\FlashDealController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\OffersCatalogController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PickupPointController;
use App\Http\Controllers\ProductBulkUploadController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductQueryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RepresentativePackageController;
use App\Http\Controllers\RepresentativePaymentAdminController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\SellerWithdrawRequestController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StateController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\Trip\DriverController;
use App\Http\Controllers\Trip\TripController;
use App\Http\Controllers\Trip\TruckController;
use App\Http\Controllers\UpdateController;
use App\Http\Controllers\VisitController;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\ZoneController;
use Aws\Middleware;

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
//Update Routes
Route::controller(UpdateController::class)->group(function () {
    Route::post('/update', 'step0')->name('update');
    Route::get('/update/step1', 'step1')->name('update.step1');
    Route::get('/update/step2', 'step2')->name('update.step2');
});
// test majd
Route::get('/admin', [AdminController::class, 'admin_dashboard'])->name('admin.dashboard')->middleware(['auth', 'admin']);
Route::group(['prefix' => 'admin', 'middleware' => ['auth', 'admin']], function () {



    // category
    Route::resource('categories', CategoryController::class);
    Route::controller(CategoryController::class)->group(function () {
        Route::get('/categories/edit/{id}', 'edit')->name('categories.edit');
        Route::get('/categories/destroy/{id}', 'destroy')->name('categories.destroy');
        Route::post('/categories/featured', 'updateFeatured')->name('categories.featured');
    });

    // Brand
    Route::resource('brands', BrandController::class);
    Route::controller(BrandController::class)->group(function () {
        Route::get('/brands/edit/{id}', 'edit')->name('brands.edit');
        Route::get('/brands/destroy/{id}', 'destroy')->name('brands.destroy');

        // brand-wise discount set
        Route::get('/brands-wise-product-discount', 'brandsWiseProductDiscount')->name('brands_wise_product_discount');
    });

    // Products
    Route::controller(ProductController::class)->group(function () {
        Route::get('/products/admin', 'admin_products')->name('products.admin');
        Route::get('/products/seller', 'seller_products')->name('products.seller');
        Route::get('/products/all', 'all_products')->name('products.all');
        Route::get('/products/create', 'create')->name('products.create');
        Route::post('/products/store/', 'store')->name('products.store');
        Route::get('/products/admin/{id}/edit', 'admin_product_edit')->name('products.admin.edit');
        Route::get('/products/seller/{id}/edit', 'seller_product_edit')->name('products.seller.edit');
        Route::post('/products/update/{product}', 'update')->name('products.update');
        Route::post('/products/todays_deal', 'updateTodaysDeal')->name('products.todays_deal');
        Route::post('/products/sale_status', 'updateSaleStatus')->name('products.update_sale');
        Route::post('/products/featured', 'updateFeatured')->name('products.featured');
        Route::post('/products/published', 'updatePublished')->name('products.published');
        Route::post('/products/approved', 'updateProductApproval')->name('products.approved');
        Route::post('/products/get_products_by_subcategory', 'get_products_by_subcategory')->name('products.get_products_by_subcategory');
        Route::get('/products/duplicate/{id}', 'duplicate')->name('products.duplicate');
        Route::get('/products/destroy/{id}', 'destroy')->name('products.destroy');
        Route::post('/bulk-product-delete', 'bulk_product_delete')->name('bulk-product-delete');
        Route::post('/products/fetch', 'fetch')->name('products.fetch');
        Route::post('/products/search', 'search')->name('products.search');
        Route::get('products/get-details/{id}', 'getDetails')->name('products.get-details');

        Route::post('/products/sku_combination', 'sku_combination')->name('products.sku_combination');
        Route::post('/products/sku_combination_edit', 'sku_combination_edit')->name('products.sku_combination_edit');
        Route::post('/products/add-more-choice-option', 'add_more_choice_option')->name('products.add-more-choice-option');
        Route::post('/set-product-discount', 'setProductDiscount')->name('set_product_discount');
    });

    Route::controller(OffersCatalogController::class)->group(function () {
        Route::get('/offers/index', 'index')->name('offers.index');
        Route::post('/offers/store', 'store')->name('offers.store');
        Route::get('/offers/download', 'download')->name('offers.download');
        Route::get('/offers/destroy/{destroy}', 'destroy')->name('offers.destroy');
    });

    // Digital Product
    Route::resource('digitalproducts', DigitalProductController::class);
    Route::controller(DigitalProductController::class)->group(function () {
        Route::get('/digitalproducts/edit/{id}', 'edit')->name('digitalproducts.edit');
        Route::get('/digitalproducts/destroy/{id}', 'destroy')->name('digitalproducts.destroy');
        Route::get('/digitalproducts/download/{id}', 'download')->name('digitalproducts.download');
    });

    Route::controller(ProductBulkUploadController::class)->group(function () {
        //Product Export
        Route::get('/product-bulk-export', 'export')->name('product_bulk_export.index');

        //Product Bulk Upload
        Route::get('/product-bulk-upload/index', 'index')->name('product_bulk_upload.index');
        Route::post('/bulk-product-upload', 'bulk_upload')->name('bulk_product_upload');
        Route::get('/product-csv-download/{type}', 'import_product')->name('product_csv.download');
        Route::get('/vendor-product-csv-download/{id}', 'import_vendor_product')->name('import_vendor_product.download');
        Route::group(['prefix' => 'bulk-upload/download'], function () {
            Route::get('/category', 'pdf_download_category')->name('pdf.download_category');
            Route::get('/brand', 'pdf_download_brand')->name('pdf.download_brand');
            Route::get('/seller', 'pdf_download_seller')->name('pdf.download_seller');
        });
    });

    // Seller
    Route::resource('sellers', SellerController::class);
    Route::controller(SellerController::class)->group(function () {
        Route::get('sellers_ban/{id}', 'ban')->name('sellers.ban');
        Route::get('/sellers/destroy/{id}', 'destroy')->name('sellers.destroy');
        Route::post('/bulk-seller-delete', 'bulk_seller_delete')->name('bulk-seller-delete');
        Route::get('/sellers/view/{id}/verification', 'show_verification_request')->name('sellers.show_verification_request');
        Route::get('/sellers/approve/{id}', 'approve_seller')->name('sellers.approve');
        Route::get('/sellers/reject/{id}', 'reject_seller')->name('sellers.reject');
        Route::get('/sellers/login/{id}', 'login')->name('sellers.login');
        Route::post('/sellers/payment_modal', 'payment_modal')->name('sellers.payment_modal');
        Route::post('/sellers/profile_modal', 'profile_modal')->name('sellers.profile_modal');
        Route::post('/sellers/approved', 'updateApproved')->name('sellers.approved');
    });

    // Seller Payment
    Route::controller(PaymentController::class)->group(function () {
        Route::get('/seller/payments', 'payment_histories')->name('sellers.payment_histories');
        Route::get('/seller/payments/show/{id}', 'show')->name('sellers.payment_history');
    });

    // Seller Withdraw Request
    Route::resource('/withdraw_requests', SellerWithdrawRequestController::class);
    Route::controller(SellerWithdrawRequestController::class)->group(function () {
        Route::get('/withdraw_requests_all', 'index')->name('withdraw_requests_all');
        Route::post('/withdraw_request/payment_modal', 'payment_modal')->name('withdraw_request.payment_modal');
        Route::post('/withdraw_request/message_modal', 'message_modal')->name('withdraw_request.message_modal');
    });

    // Customer
    Route::resource('customers', CustomerController::class)->except(['update']);

    // custom route for making update method as POST action
    Route::post('customers/{id}', [CustomerController::class, 'update'])->name('customers.update');
    Route::post('customer/store', [CustomerController::class, 'store_customer'])->name('customers.store');
    Route::get('customers/archive/get', [CustomerController::class, 'archive'])->name('customers.archive');
    Route::get('customer/change_password/{id}', [CustomerController::class, 'change_password'])->name('change_password');
    Route::get('customers/restore/{id}', [CustomerController::class, 'restore'])->name('customers.restore');
    Route::post('customer/update_password/', [CustomerController::class, 'update_password'])->name('update_password');
    Route::get('customer/whatsapp-contact/{id}', [CustomerController::class, 'whatsapp_contact'])->name('customers.whatsapp_contact');
    Route::get('customer/important/{id}', [CustomerController::class, 'important'])->name('customers.important');
    Route::get('customer/not-important/{id}', [CustomerController::class, 'not_important'])->name('customers.not_important');
    Route::get('customer/change-activity-status', [CustomerController::class, 'change_activity_status'])->name('customers.change_activity_status');
    Route::post('customer/update-customer-notes', [CustomerController::class, 'update_customer_notes'])->name('customers.update_customer_notes');
    Route::get('customer/{user_id}/pages-views', [CustomerController::class, 'pages_views'])->name('customers.page-views');

    Route::controller(CustomerController::class)->group(function () {
        Route::get('/customers/verify/{customer}', 'verify')->name('customers.verify');

        Route::get('customers_ban/{customer}', 'ban')->name('customers.ban');
        Route::get('/customers/login/{id}', 'login')->name('customers.login');
        Route::get('/customers/destroy/{id}', 'destroy')->name('customers.destroy');
        Route::post('/bulk-customer-delete', 'bulk_customer_delete')->name('bulk-customer-delete');
        Route::get('/customers/download_trade_license/{filename}', 'downloadTradeLicense')->name('download.trade_license');
        Route::get('/representatives', 'repIndex')->name('representatives.index');
        Route::get('/representatives/create', 'repCreate')->name('representatives.create');
        Route::post('/representatives/store', 'repStore')->name('representatives.store');
    });


    Route::controller(RepresentativePaymentAdminController::class)->group(function() {
        Route::get('admin/representatives/payments/index', 'index')->name('admin.rep.payments.index');
        Route::get('admin/representatives/payments/accept/{id}', 'accept')->name('admin.rep.payments.accept');
        Route::get('admin/representatives/payments/reject/{id}', 'reject')->name('admin.rep.payments.reject');
        Route::get('admin/representatives/payments/export/{id}', 'export')->name('admin.rep.payments.export');

    });

    Route::controller(VisitController::class)->group(function() {
        Route::get('/representatives/visits/index', 'index')->name('admin.rep.visits.index');
        Route::get('/representatives/visits/show/{id}', 'show')->name('admin.rep.visits.show');
        Route::get('/representatives/visits/rep-show/{id}', 'show_all_visits_in_a_day')->name('admin.rep.visits.rep-show');
        Route::get('/representatives/visits/delete/{id}', 'destroy')->name('admin.rep.visits.destroy');
        Route::post('/representatives/visits/bulk-visit-delete', 'bulk_visit_delete')->name('bulk-visit-delete');
    });

    Route::controller(RepresentativePackageController::class)->group(function() {
        Route::get('/representatives/packages/index', 'index')->name('rep.packages.index');
        Route::get('/representatives/packages/create', 'create')->name('rep.packages.create');
        Route::post('/representatives/packages/store', 'store')->name('rep.packages.store');
        Route::get('/representatives/packages/edit/{id}', 'edit')->name('rep.packages.edit');
        Route::post('/representatives/packages/update', 'update')->name('rep.packages.update');
        Route::get('/representatives/packages/delete/{id}', 'delete')->name('rep.packages.destroy');
        Route::post('/representatives/packages', 'package_modal')->name('rep.packages.modal');
        Route::post('/representatives/packages/set_package', 'set_package')->name('rep.set-package');


    });
    Route::group(['prefix' => 'trips'], function () {
        Route::controller(TripController::class)->group(function() {
            Route::get('/index', 'getAllTrips')->name('trips.index');
            Route::get('/orders', 'all_orders')->name('trips.orders');
            Route::post('/orders/bulk-change-delivery-status', 'bulk_change_delivery_status')->name('trips.orders.bulk-change-delivery-status');
            Route::post('/orders/update-invoice-number', 'updateInvoiceNumber')->name('trips.orders.update_invoice_number');
            Route::post('/orders/update-order-status', 'updateOrderStatusToDelivered')->name('trips.orders.update_order_status_to_delivered');
            Route::get('/today-trips', 'getTodayTrips')->name('trips.today-trips');
            Route::get('/create', 'create')->name('trips.create');
            Route::get('/create-from-map', 'create_from_map')->name('trips.create-from-map');
            Route::post('/store', 'store')->name('trips.store');
            Route::post('/store-from-map', 'store_from_map')->name('trips.store-from-map');
            Route::get('/show/{id}', 'show')->name('trips.show');
            Route::get('/edit/{id}', 'edit')->name('trips.edit');
            Route::post('/update', 'update')->name('trips.update');
            Route::get('/delete/{id}', 'delete')->name('trips.destroy');
            Route::post('/bulk-trips-delete', 'bulk_trip_delete')->name('trips.bulk-trip-delete');
            Route::post('/update-trip-status', 'update_trip_status')->name('trips.update-status');
            Route::post('/update-trip-notes', 'update_trip_notes')->name('trips.update-notes');
            Route::post('/update-checkpoint-notes', 'update_checkpoint_notes')->name('checkpoints.update-notes');
            Route::post('/update-checkpoint-status', 'update_checkpoint_status')->name('checkpoints.update-status');
            Route::get('/print-cmr/{id}', 'print_cmr')->name('checkpoints.print-cmr');
        });

        Route::controller(DriverController::class)->group(function() {
            Route::get('/drivers/index', 'index')->name('drivers.index');
            Route::get('/drivers/create', 'create')->name('drivers.create');
            Route::post('/drivers/store', 'store')->name('drivers.store');
            Route::get('/drivers/edit/{id}', 'edit')->name('drivers.edit');
            Route::post('/drivers/update', 'update')->name('drivers.update');
            Route::get('/drivers/delete/{id}', 'delete')->name('drivers.destroy');
        });

        Route::controller(TruckController::class)->group(function() {
            Route::get('/trucks/index', 'index')->name('trucks.index');
            Route::get('/trucks/create', 'create')->name('trucks.create');
            Route::post('/trucks/store', 'store')->name('trucks.store');
            Route::get('/trucks/edit/{id}', 'edit')->name('trucks.edit');
            Route::post('/trucks/update', 'update')->name('trucks.update');
            Route::get('/trucks/delete/{id}', 'delete')->name('trucks.destroy');
            Route::post('/trucks/bulk-trucks-delete', 'bulk_truck_delete')->name('trucks.bulk-truck-delete');

        });
    });

    // Newsletter
    Route::controller(NewsletterController::class)->group(function () {
        Route::get('/newsletter', 'index')->name('newsletters.index');
        Route::post('/newsletter/send', 'send')->name('newsletters.send');
        Route::post('/newsletter/test/smtp', 'testEmail')->name('test.smtp');
    });

    // Dynamic Popup
    Route::resource('dynamic-popups', DynamicPopupController::class);
    Route::controller(DynamicPopupController::class)->group(function () {
        Route::get('/dynamic-popups/destroy/{id}', 'destroy')->name('dynamic-popups.destroy');
        Route::post('/bulk-dynamic-popup-delete', 'bulk_dynamic_popup_delete')->name('bulk-dynamic-popup-delete');
        Route::post('/dynamic-popups-update-status', 'update_status')->name('dynamic-popups.update-status');
    });

    Route::resource('profile', ProfileController::class);

    // Business Settings
    Route::controller(BusinessSettingsController::class)->group(function () {
        Route::post('/business-settings/update', 'update')->name('business_settings.update');
        Route::post('/business-settings/update/activation', 'updateActivationSettings')->name('business_settings.update.activation')->middleware('RaizerSuperAdmin');
        Route::get('/general-setting', 'general_setting')->name('general_setting.index');
        Route::get('/activation', 'activation')->name('activation.index')->middleware('RaizerSuperAdmin');
        Route::get('/payment-method', 'payment_method')->name('payment_method.index');
        Route::get('/file_system', 'file_system')->name('file_system.index');
        Route::get('/social-login', 'social_login')->name('social_login.index');
        Route::get('/smtp-settings', 'smtp_settings')->name('smtp_settings.index');
        Route::get('/google-analytics', 'google_analytics')->name('google_analytics.index');
        Route::get('/google-recaptcha', 'google_recaptcha')->name('google_recaptcha.index');
        Route::get('/google-map', 'google_map')->name('google-map.index');
        Route::get('/google-firebase', 'google_firebase')->name('google-firebase.index');

        //Facebook Settings
        Route::get('/facebook-chat', 'facebook_chat')->name('facebook_chat.index');
        Route::post('/facebook_chat', 'facebook_chat_update')->name('facebook_chat.update');
        Route::get('/facebook-comment', 'facebook_comment')->name('facebook-comment');
        Route::post('/facebook-comment', 'facebook_comment_update')->name('facebook-comment.update');
        Route::post('/facebook_pixel', 'facebook_pixel_update')->name('facebook_pixel.update');

        Route::post('/env_key_update', 'env_key_update')->name('env_key_update.update');
        Route::post('/payment_method_update', 'payment_method_update')->name('payment_method.update');
        Route::post('/google_analytics', 'google_analytics_update')->name('google_analytics.update');
        Route::post('/google_recaptcha', 'google_recaptcha_update')->name('google_recaptcha.update');
        Route::post('/google-map', 'google_map_update')->name('google-map.update');
        Route::post('/google-firebase', 'google_firebase_update')->name('google-firebase.update');

        Route::get('/verification/form', 'seller_verification_form')->name('seller_verification_form.index');
        Route::post('/verification/form', 'seller_verification_form_update')->name('seller_verification_form.update');
        Route::get('/vendor_commission', 'vendor_commission')->name('business_settings.vendor_commission');
        Route::post('/vendor_commission_update', 'vendor_commission_update')->name('business_settings.vendor_commission.update');

        //Shipping Configuration
        Route::get('/shipping_configuration', 'shipping_configuration')->name('shipping_configuration.index');
        Route::post('/shipping_configuration/update', 'shipping_configuration_update')->name('shipping_configuration.update');

        // Order Configuration
        Route::get('/order-configuration', 'order_configuration')->name('order_configuration.index');
        Route::get('/product/search', 'search')->name('order_configuration.product-search');
    });


    //Currency
    Route::controller(CurrencyController::class)->group(function () {
        Route::get('/currency', 'currency')->name('currency.index');
        Route::post('/currency/update', 'updateCurrency')->name('currency.update');
        Route::post('/your-currency/update', 'updateYourCurrency')->name('your_currency.update');
        Route::get('/currency/create', 'create')->name('currency.create');
        Route::post('/currency/store', 'store')->name('currency.store');
        Route::post('/currency/currency_edit', 'edit')->name('currency.edit');
        Route::post('/currency/update_status', 'update_status')->name('currency.update_status');
    });

    //Tax
    Route::resource('tax', TaxController::class);
    Route::controller(TaxController::class)->group(function () {
        Route::get('/tax/edit/{id}', 'edit')->name('tax.edit');
        Route::get('/tax/destroy/{id}', 'destroy')->name('tax.destroy');
        Route::post('tax-status', 'change_tax_status')->name('taxes.tax-status');
    });

    // Language
    Route::resource('/languages', LanguageController::class);
    Route::controller(LanguageController::class)->group(function () {
        Route::post('/languages/{id}/update', 'update')->name('languages.update');
        Route::get('/languages/destroy/{id}', 'destroy')->name('languages.destroy');
        Route::post('/languages/update_rtl_status', 'update_rtl_status')->name('languages.update_rtl_status');
        Route::post('/languages/update-status', 'update_status')->name('languages.update-status');
        Route::post('/languages/key_value_store', 'key_value_store')->name('languages.key_value_store');

        //App Trasnlation
        Route::post('/languages/app-translations/import', 'importEnglishFile')->name('app-translations.import');
        Route::get('/languages/app-translations/show/{id}', 'showAppTranlsationView')->name('app-translations.show');
        Route::post('/languages/app-translations/key_value_store', 'storeAppTranlsation')->name('app-translations.store');
        Route::get('/languages/app-translations/export/{id}', 'exportARBFile')->name('app-translations.export');
    });


    // website setting
    Route::group(['prefix' => 'website'], function () {
        Route::controller(WebsiteController::class)->group(function () {
            Route::get('/footer', 'footer')->name('website.footer');
            Route::get('/header', 'header')->name('website.header');
            Route::get('/appearance', 'appearance')->name('website.appearance');
            Route::get('/pages', 'pages')->name('website.pages');
        });

        // Custom Page
        Route::resource('custom-pages', PageController::class);
        Route::controller(PageController::class)->group(function () {
            Route::get('/custom-pages/edit/{id}', 'edit')->name('custom-pages.edit');
            Route::get('/custom-pages/destroy/{id}', 'destroy')->name('custom-pages.destroy');
        });
    });

    // Staff Roles
    Route::resource('roles', RoleController::class);
    Route::controller(RoleController::class)->group(function () {
        Route::get('/roles/edit/{id}', 'edit')->name('roles.edit');
        Route::get('/roles/destroy/{id}', 'destroy')->name('roles.destroy');

        // Add Permissiom
        Route::post('/roles/add_permission', 'add_permission')->name('roles.permission');
    });

    // Staff
    Route::resource('staffs', StaffController::class);
    Route::get('/staffs/destroy/{id}', [StaffController::class, 'destroy'])->name('staffs.destroy');

    // Flash Deal
    Route::resource('flash_deals', FlashDealController::class);
    Route::controller(FlashDealController::class)->group(function () {
        Route::get('/flash_deals/edit/{id}', 'edit')->name('flash_deals.edit');
        Route::get('/flash_deals/destroy/{id}', 'destroy')->name('flash_deals.destroy');
        Route::post('/flash_deals/update_status', 'update_status')->name('flash_deals.update_status');
        Route::post('/flash_deals/update_featured', 'update_featured')->name('flash_deals.update_featured');
        Route::post('/flash_deals/product_discount', 'product_discount')->name('flash_deals.product_discount');
        Route::post('/flash_deals/product_discount_edit', 'product_discount_edit')->name('flash_deals.product_discount_edit');
    });

    Route::controller(FeatureOrderController::class)->group(function () {
        Route::get('/feat/order/create', 'create')->name('feat-order.create');
        Route::post('/feat/order/store', 'store')->name('feat-order.store');
        Route::get('/feat/order/get-needed-data', 'getNeededData')->name('feat-order.needed-data');
        // Route::get('/feat/order/products-data', 'productsData')->name('feat-order.products-table');
        Route::get('feat/order/rep-discount', 'getRepDiscount')->name('feat-order.rep-discount');
    });
    Route::controller(FeatureOrderTripController::class)->group(function () {
        Route::get('/feat/order/trip/create', 'create')->name('feat-order.trip.create');
        Route::post('/feat/order/trip/store', 'store')->name('feat-order.trip.store');
        Route::get('/feat/order/trip/get-needed-data', 'getNeededData')->name('feat-order.trip.needed-data');

        Route::get('/feat/new/order/trip/create', 'newCreate')->name('new.feat-order.trip.create');
        Route::post('/feat/new/order/trip/store', 'newStore')->name('new.feat-order.trip.store');

        Route::get('/feat/new/order/trip/edit/{id}', 'newEdit')->name('new.feat-order.trip.edit');
        Route::put('/feat/new/order/trip/update/{id}', 'newUpdate')->name('new.feat-order.trip.update');

        Route::get('/feat/new/order/trip/get-needed-data', 'newGetNeededData')->name('new.feat-order.trip.needed-data');

        // Route::get('/feat/order/products-data', 'productsData')->name('feat-order.products-table');
        // Route::get('feat/order/trip/rep-discount', 'getRepDiscount')->name('feat-order.trip.rep-discount');
    });
    Route::controller(OfferController::class)->group(function () {
        Route::get('/offer_deals/index', 'index')->name('offer_deals.index');
        Route::get('/offer_deals/create', 'create')->name('offer_deals.create');
        Route::get('/offer_deals/store', 'store')->name('offer_deals.store');
        Route::get('/offer_deals/edit/{id}', 'edit')->name('offer_deals.edit');
        Route::post('/offer_deals/update/{id}', 'update')->name('offer_deals.update');
        Route::get('/offer_deals/destroy/{id}', 'destroy')->name('offer_deals.destroy');
        Route::post('/offer_deals/update_status', 'update_status')->name('offer_deals.update_status');
        Route::post('/offer_deals/update_repeatable', 'update_repeatable')->name('offer_deals.update_repeatable');
        Route::post('/offer_deals/update_multiple_usage', 'update_multiple_usage')->name('offer_deals.update_multiple_usage');

        Route::get('/offer_deals/base_product_discount', 'base_product_discount')->name('offer_deals.base_product_discount');//post
        Route::get('/offer_deals/product_discount', 'product_discount')->name('offer_deals.product_discount');//post
        Route::post('/offer_deals/product_discount_edit', 'product_discount_edit')->name('offer_deals.product_discount_edit');

        Route::get('/offer_deals/brand-create', 'brandCreate')->name('offer_deals.brands.create');
        Route::get('/offer_deals/base_brand_discount', 'base_brand_discount')->name('offer_deals.base_brand_discount');//post
        Route::get('/offer_deals/brand_discount', 'brand_discount')->name('offer_deals.brand_discount');//post
        Route::post('/offer_deals/brand_discount_edit', 'brand_discount_edit')->name('offer_deals.brand_discount_edit');
    });

    //Subscribers
    Route::controller(SubscriberController::class)->group(function () {
        Route::get('/subscribers', 'index')->name('subscribers.index');
        Route::get('/subscribers/destroy/{id}', 'destroy')->name('subscriber.destroy');
    });

    // Order
    Route::resource('orders', OrderController::class)->except(['destroy']);
    Route::controller(OrderController::class)->group(function () {
        Route::get('orders/delete/{id}', 'destroy')->name('orders.destroy');
        // All Orders
        Route::get('/all_orders', 'all_orders')->name('all_orders.index');
        Route::get('/inhouse-orders', 'all_orders')->name('inhouse_orders.index');
        Route::get('/seller_orders', 'all_orders')->name('seller_orders.index');
        Route::get('orders_by_pickup_point', 'all_orders')->name('pick_up_point.index');

        Route::get('/orders/{id}/show', 'show')->name('all_orders.show');
        Route::get('/inhouse-orders/{id}/show', 'show')->name('inhouse_orders.show');
        Route::get('/seller_orders/{id}/show', 'show')->name('seller_orders.show');
        Route::get('/orders_by_pickup_point/{id}/show', 'show')->name('pick_up_point.order_show');

        Route::post('/bulk-order-status', 'bulk_order_status')->name('bulk-order-status');

        Route::get('/orders/destroy/{id}', 'destroy')->name('orders.destroy');
        Route::post('/bulk-order-delete', 'bulk_order_delete')->name('bulk-order-delete');

        Route::get('/orders/destroy/{id}', 'destroy')->name('orders.destroy');
        Route::post('/orders/details', 'order_details')->name('orders.details');
        Route::post('/orders/update_delivery_status', 'update_delivery_status')->name('orders.update_delivery_status');
        Route::post('/orders/update_payment_status', 'update_payment_status')->name('orders.update_payment_status');
        Route::post('/orders/update_tracking_code', 'update_tracking_code')->name('orders.update_tracking_code');
        Route::post('/orders/update_manager_notes', 'update_manager_notes')->name('orders.update_manager_notes');
        Route::post('/orders/item/edit_price', 'edit_item_price')->name('order.item.edit_price');

        //Delivery Boy Assign
        Route::post('/orders/delivery-boy-assign', 'assign_delivery_boy')->name('orders.delivery-boy-assign');
    });

    Route::post('/pay_to_seller', [CommissionController::class, 'pay_to_seller'])->name('commissions.pay_to_seller');

    //Reports
    Route::controller(ReportController::class)->group(function () {
        Route::get('/in_house_sale_report', 'in_house_sale_report')->name('in_house_sale_report.index');
        Route::get('/seller_sale_report', 'seller_sale_report')->name('seller_sale_report.index');
        Route::get('/stock_report', 'stock_report')->name('stock_report.index');
        Route::get('/wish_report', 'wish_report')->name('wish_report.index');
        Route::get('/user_search_report', 'user_search_report')->name('user_search_report.index');
        Route::get('/commission-log', 'commission_history')->name('commission-log.index');
        Route::get('/wallet-history', 'wallet_transaction_history')->name('wallet-history.index');
    });

    //Blog Section
    //Blog cateory
    Route::resource('blog-category', BlogCategoryController::class);
    Route::get('/blog-category/destroy/{id}', [BlogCategoryController::class, 'destroy'])->name('blog-category.destroy');

    // Blog
    Route::resource('blog', BlogController::class);
    Route::controller(BlogController::class)->group(function () {
        Route::get('/blog/destroy/{id}', 'destroy')->name('blog.destroy');
        Route::post('/blog/change-status', 'change_status')->name('blog.change-status');
    });

    //Coupons
    Route::resource('coupon', CouponController::class);
    Route::controller(CouponController::class)->group(function () {
        Route::get('/coupon/destroy/{id}', 'destroy')->name('coupon.destroy');

        //Coupon Form
        Route::post('/coupon/get_form', 'get_coupon_form')->name('coupon.get_coupon_form');
        Route::post('/coupon/get_form_edit', 'get_coupon_form_edit')->name('coupon.get_coupon_form_edit');
    });

    //Reviews
    Route::controller(ReviewController::class)->group(function () {
        Route::get('/reviews', 'index')->name('reviews.index');
        Route::post('/reviews/published', 'updatePublished')->name('reviews.published');
    });

    //Support_Ticket
    Route::controller(SupportTicketController::class)->group(function () {
        Route::get('support_ticket/', 'admin_index')->name('support_ticket.admin_index');
        Route::get('support_ticket/{id}/show', 'admin_show')->name('support_ticket.admin_show');
        Route::post('support_ticket/reply', 'admin_store')->name('support_ticket.admin_store');
    });

    //Pickup_Points
    Route::resource('pick_up_points', PickupPointController::class);
    Route::controller(PickupPointController::class)->group(function () {
        Route::get('/pick_up_points/edit/{id}', 'edit')->name('pick_up_points.edit');
        Route::get('/pick_up_points/destroy/{id}', 'destroy')->name('pick_up_points.destroy');
    });

    //conversation of seller customer
    Route::controller(ConversationController::class)->group(function () {
        Route::get('conversations', 'admin_index')->name('conversations.admin_index');
        Route::get('conversations/{id}/show', 'admin_show')->name('conversations.admin_show');
    });

    // product Queries show on Admin panel
    Route::controller(ProductQueryController::class)->group(function () {
        Route::get('/product-queries', 'index')->name('product_query.index');
        Route::get('/product-queries/{id}', 'show')->name('product_query.show');
        Route::put('/product-queries/{id}', 'reply')->name('product_query.reply');
    });

    // Product Attribute
    Route::resource('attributes', AttributeController::class);
    Route::controller(AttributeController::class)->group(function () {
        Route::get('/attributes/edit/{id}', 'edit')->name('attributes.edit');
        Route::get('/attributes/destroy/{id}', 'destroy')->name('attributes.destroy');

        //Attribute Value
        Route::post('/store-attribute-value', 'store_attribute_value')->name('store-attribute-value');
        Route::get('/edit-attribute-value/{id}', 'edit_attribute_value')->name('edit-attribute-value');
        Route::post('/update-attribute-value/{id}', 'update_attribute_value')->name('update-attribute-value');
        Route::get('/destroy-attribute-value/{id}', 'destroy_attribute_value')->name('destroy-attribute-value');

        //Colors
        Route::get('/colors', 'colors')->name('colors');
        Route::post('/colors/store', 'store_color')->name('colors.store');
        Route::get('/colors/edit/{id}', 'edit_color')->name('colors.edit');
        Route::post('/colors/update/{id}', 'update_color')->name('colors.update');
        Route::get('/colors/destroy/{id}', 'destroy_color')->name('colors.destroy');
    });

    // Addon
    Route::resource('addons', AddonController::class);
    Route::post('/addons/activation', [AddonController::class, 'activation'])->name('addons.activation');

    //Customer Package
    Route::resource('customer_packages', CustomerPackageController::class);
    Route::controller(CustomerPackageController::class)->group(function () {
        Route::get('/customer_packages/edit/{id}', 'edit')->name('customer_packages.edit');
        Route::get('/customer_packages/destroy/{id}', 'destroy')->name('customer_packages.destroy');
    });

    //Classified Products
    Route::controller(CustomerProductController::class)->group(function () {
        Route::get('/classified_products', 'customer_product_index')->name('classified_products');
        Route::post('/classified_products/published', 'updatePublished')->name('classified_products.published');
        Route::get('/classified_products/destroy/{id}', 'destroy_by_admin')->name('classified_products.destroy');
    });

    // Countries
    Route::resource('countries', CountryController::class);
    Route::post('/countries/status', [CountryController::class, 'updateStatus'])->name('countries.status');

    // States
    Route::resource('states', StateController::class);
    Route::post('/states/status', [StateController::class, 'updateStatus'])->name('states.status');

    // Carriers
    Route::resource('carriers', CarrierController::class);
    Route::controller(CarrierController::class)->group(function () {
        Route::get('/carriers/destroy/{id}', 'destroy')->name('carriers.destroy');
        Route::post('/carriers/update_status', 'updateStatus')->name('carriers.update_status');
    });


    // Zones
    Route::resource('zones', ZoneController::class);
    Route::get('/zones/destroy/{id}', [ZoneController::class, 'destroy'])->name('zones.destroy');

    Route::resource('cities', CityController::class);
    Route::controller(CityController::class)->group(function () {
        Route::get('/cities/edit/{id}', 'edit')->name('cities.edit');
        Route::get('/cities/destroy/{id}', 'destroy')->name('cities.destroy');
        Route::post('/cities/status', 'updateStatus')->name('cities.status');
    });

    Route::view('/system/update', 'backend.system.update')->name('system_update')->middleware('RaizerSuperAdmin');
    Route::view('/system/server-status', 'backend.system.server_status')->name('system_server')->middleware('RaizerSuperAdmin');

    // uploaded files
    Route::resource('/uploaded-files', AizUploadController::class);
    Route::controller(AizUploadController::class)->group(function () {
        Route::any('/uploaded-files/file-info', 'file_info')->name('uploaded-files.info');
        Route::get('/uploaded-files/destroy/{id}', 'destroy')->name('uploaded-files.destroy');
        Route::post('/bulk-uploaded-files-delete', 'bulk_uploaded_files_delete')->name('bulk-uploaded-files-delete');
        Route::get('/all-file', 'all_file');
    });

    Route::get('/all-notification', [NotificationController::class, 'index'])->name('admin.all-notification');

    Route::get('/clear-cache', [AdminController::class, 'clearCache'])->name('cache.clear');

    Route::get('/admin-permissions', [RoleController::class, 'create_admin_permissions']);

    Route::controller(AdminNotificationController::class)->group(function () {
        Route::get('/notifications', 'getAllNotifications')->name('admin.notifications.all');
        Route::post('/notifications/{id}/read', 'markAsRead')->name('admin.notifications.mark-read');
        Route::get('/notifications/create', 'createNotification')->name('admin.notifications.create');
        Route::post('/notifications/send', 'sendFcmNotification')->name('admin.notifications.send');
        Route::get('/notifications/{id}', 'deleteNotification')->name('admin.notifications.delete');
    });
});
