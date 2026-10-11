import type { UrlMethodPair } from '@inertiajs/core';
import {
    Banknote,
    BookOpen,
    Contact,
    FileText,
    FolderOpen,
    Images,
    LayoutDashboard,
    LifeBuoy,
    Megaphone,
    Monitor,
    Newspaper,
    Package,
    Settings,
    SlidersHorizontal,
    Store,
    Truck,
    UserCog,
    Users,
} from '@lucide/vue';
import type { Component } from 'vue';
import { admin_dashboard } from '@/actions/App/Http/Controllers/AdminController';
import { getAllNotifications } from '@/actions/App/Http/Controllers/AdminNotificationController';
import { index as uploadedFilesIndex } from '@/actions/App/Http/Controllers/AizUploadController';
import {
    colors,
    index as attributesIndex,
} from '@/actions/App/Http/Controllers/AttributeController';
import { index as blogCategoriesIndex } from '@/actions/App/Http/Controllers/BlogCategoryController';
import { index as blogIndex } from '@/actions/App/Http/Controllers/BlogController';
import {
    brandsWiseProductDiscount,
    index as brandsIndex,
} from '@/actions/App/Http/Controllers/BrandController';
import {
    activation,
    facebook_chat,
    facebook_comment,
    file_system,
    general_setting,
    google_analytics,
    google_firebase,
    google_map,
    google_recaptcha,
    order_configuration,
    payment_method,
    seller_verification_form,
    shipping_configuration,
    smtp_settings,
    social_login,
    vendor_commission,
} from '@/actions/App/Http/Controllers/BusinessSettingsController';
import { index as carriersIndex } from '@/actions/App/Http/Controllers/CarrierController';
import { index as categoriesIndex } from '@/actions/App/Http/Controllers/CategoryController';
import { index as citiesIndex } from '@/actions/App/Http/Controllers/CityController';
import { admin_index as conversationsIndex } from '@/actions/App/Http/Controllers/ConversationController';
import { index as countriesIndex } from '@/actions/App/Http/Controllers/CountryController';
import { index as couponsIndex } from '@/actions/App/Http/Controllers/CouponController';
import { currency as currenciesIndex } from '@/actions/App/Http/Controllers/CurrencyController';
import { index as customerPackagesIndex } from '@/actions/App/Http/Controllers/CustomerPackageController';
import { index as digitalProductsIndex } from '@/actions/App/Http/Controllers/DigitalProductController';
import { index as dynamicPopupsIndex } from '@/actions/App/Http/Controllers/DynamicPopupController';
import { create as featureOrderCreate } from '@/actions/App/Http/Controllers/FeatureOrderController';
import { index as flashDealsIndex } from '@/actions/App/Http/Controllers/FlashDealController';
import { index as languagesIndex } from '@/actions/App/Http/Controllers/LanguageController';
import { index as newslettersIndex } from '@/actions/App/Http/Controllers/NewsletterController';
import { index as offerDealsIndex } from '@/actions/App/Http/Controllers/OfferController';
import { index as offersCatalogIndex } from '@/actions/App/Http/Controllers/OffersCatalogController';
import { all_orders } from '@/actions/App/Http/Controllers/OrderController';
import { edit as customPageEdit } from '@/actions/App/Http/Controllers/PageController';
import { payment_histories } from '@/actions/App/Http/Controllers/PaymentController';
import { index as pickupPointsIndex } from '@/actions/App/Http/Controllers/PickupPointController';
import {
    exportMethod as productBulkExport,
    index as productBulkImport,
} from '@/actions/App/Http/Controllers/ProductBulkUploadController';
import {
    admin_products,
    all_products,
    create as productsCreate,
    seller_products,
} from '@/actions/App/Http/Controllers/ProductController';
import { index as productQueriesIndex } from '@/actions/App/Http/Controllers/ProductQueryController';
import {
    commission_history,
    in_house_sale_report,
    seller_sale_report,
    stock_report,
    user_search_report,
    wallet_transaction_history,
    wish_report,
} from '@/actions/App/Http/Controllers/ReportController';
import { index as reviewsIndex } from '@/actions/App/Http/Controllers/ReviewController';
import { index as sellersIndex } from '@/actions/App/Http/Controllers/SellerController';
import { index as withdrawRequestsIndex } from '@/actions/App/Http/Controllers/SellerWithdrawRequestController';
import { index as statesIndex } from '@/actions/App/Http/Controllers/StateController';
import { index as classifiedProductsIndex } from '@/actions/App/Http/Controllers/Store/Admin/ClassifiedProductController';
import {
    archive as customersArchive,
    create as customersCreate,
    index as customersIndex,
    repIndex as representativesIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import { index as representativePackagesIndex } from '@/actions/App/Http/Controllers/Store/Admin/RepresentativePackageController';
import { index as representativePaymentsIndex } from '@/actions/App/Http/Controllers/Store/Admin/RepresentativePaymentController';
import { index as rolesIndex } from '@/actions/App/Http/Controllers/Store/Admin/RoleController';
import { index as staffIndex } from '@/actions/App/Http/Controllers/Store/Admin/StaffController';
import { index as visitsIndex } from '@/actions/App/Http/Controllers/Store/Admin/VisitController';
import { index as subscribersIndex } from '@/actions/App/Http/Controllers/SubscriberController';
import { admin_index as supportTicketsIndex } from '@/actions/App/Http/Controllers/SupportTicketController';
import { index as taxesIndex } from '@/actions/App/Http/Controllers/TaxController';
import {
    create as driversCreate,
    index as driversIndex,
} from '@/actions/App/Http/Controllers/Trip/DriverController';
import {
    all_orders as tripOrders,
    create as tripsCreate,
    create_from_map,
    getAllTrips,
    getTodayTrips,
} from '@/actions/App/Http/Controllers/Trip/TripController';
import {
    create as trucksCreate,
    index as trucksIndex,
} from '@/actions/App/Http/Controllers/Trip/TruckController';
import {
    appearance,
    footer,
    header,
    pages,
} from '@/actions/App/Http/Controllers/WebsiteController';
import { index as zonesIndex } from '@/actions/App/Http/Controllers/ZoneController';
import type { StoreAccess } from '@/lib/storeAccess';

/**
 * When a sidebar entry shows, mirroring the Blade sidebar's conditions
 * (.ai/rules/store-backoffice-migration.md §2.2). An entry with no gate
 * shows to everyone who reaches the back office.
 */
interface StoreMenuGate {
    /** Any one of these permissions (`@can`/`@canany`). */
    permission?: string | string[];
    /** A business setting that switches the section on; the vendor's own account sees it regardless. */
    setting?: string;
    /** Only the vendor's own account (`raizer_admin`). */
    raizerAdmin?: boolean;
}

export interface StoreMenuLink extends StoreMenuGate {
    labelKey: string;
    href: UrlMethodPair;
    /** Top-level entries carry an icon (.ai/rules/layouts.md). */
    icon?: Component;
    /**
     * The target is an Inertia page, so the link is an Inertia visit. Every
     * other target is still Blade and loads in full; flip this as each module
     * is converted.
     */
    inertia?: boolean;
}

export interface StoreMenuGroup extends StoreMenuGate {
    labelKey: string;
    icon?: Component;
    children: StoreMenuItem[];
}

export type StoreMenuItem = StoreMenuLink | StoreMenuGroup;

export function isStoreMenuGroup(item: StoreMenuItem): item is StoreMenuGroup {
    return 'children' in item;
}

/**
 * The back office's sidebar, from resources/views/shop/backend/inc/admin_sidenav.blade.php
 * less the removed addons' blocks, the stray duplicate Shipping group and the
 * server-status page (a `Route::view` with no controller, marked for deletion).
 * Where the Blade gate disagreed with the route's own permission, the
 * route's wins, so a link never leads to a 403.
 */
export const storeAdminMenu: StoreMenuItem[] = [
    {
        labelKey: 'store.nav.dashboard',
        href: admin_dashboard(),
        icon: LayoutDashboard,
        permission: 'admin_dashboard',
    },
    {
        labelKey: 'store.nav.products',
        icon: Package,
        children: [
            {
                labelKey: 'store.nav.addProduct',
                href: productsCreate(),
                permission: 'add_new_product',
            },
            {
                labelKey: 'store.nav.allProducts',
                href: all_products(),
                permission: 'show_all_products',
            },
            {
                labelKey: 'store.nav.inHouseProducts',
                href: admin_products(),
                permission: 'show_in_house_products',
                setting: 'In_House_Products_Section',
            },
            {
                labelKey: 'store.nav.sellerProducts',
                href: seller_products(),
                permission: 'show_seller_products',
                setting: 'vendor_system_activation',
            },
            {
                labelKey: 'store.nav.digitalProducts',
                href: digitalProductsIndex(),
                permission: 'show_digital_products',
                setting: 'Digital_Products_Section',
            },
            {
                labelKey: 'store.nav.bulkImport',
                href: productBulkImport(),
                permission: 'product_bulk_import',
            },
            {
                labelKey: 'store.nav.bulkExport',
                href: productBulkExport(),
                permission: 'product_bulk_export',
            },
            {
                labelKey: 'store.nav.categories',
                href: categoriesIndex(),
                permission: 'view_product_categories',
            },
            {
                labelKey: 'store.nav.brands',
                href: brandsIndex(),
                permission: 'view_all_brands',
            },
            {
                labelKey: 'store.nav.brandDiscount',
                href: brandsWiseProductDiscount(),
                permission: 'view_all_brands',
            },
            {
                labelKey: 'store.nav.attributes',
                href: attributesIndex(),
                permission: 'view_product_attributes',
            },
            {
                labelKey: 'store.nav.colors',
                href: colors(),
                permission: 'view_colors',
            },
            {
                labelKey: 'store.nav.productReviews',
                href: reviewsIndex['/temp/admin/reviews'](),
                permission: 'view_product_reviews',
                setting: 'Product_Reviews_Section',
            },
        ],
    },
    {
        labelKey: 'store.nav.sales',
        icon: Banknote,
        children: [
            {
                labelKey: 'store.nav.allOrders',
                href: all_orders['/temp/admin/all_orders'](),
                permission: 'view_all_orders',
            },
            {
                labelKey: 'store.nav.createOrder',
                href: featureOrderCreate(),
                permission: 'view_all_orders',
            },
            {
                labelKey: 'store.nav.sellerOrders',
                href: all_orders['/temp/admin/seller_orders'](),
                permission: 'view_seller_orders',
                setting: 'vendor_system_activation',
            },
            {
                labelKey: 'store.nav.pickupPointOrders',
                href: all_orders['/temp/admin/orders_by_pickup_point'](),
                permission: 'view_pickup_point_orders',
                setting: 'Pickup_Orders_Section',
            },
        ],
    },
    {
        labelKey: 'store.nav.customers',
        icon: Users,
        children: [
            {
                labelKey: 'store.nav.customerList',
                inertia: true,
                href: customersIndex(),
                permission: 'view_all_customers',
            },
            {
                labelKey: 'store.nav.customerCreate',
                inertia: true,
                href: customersCreate(),
                permission: 'edit_customer',
            },
            {
                labelKey: 'store.nav.customerArchive',
                inertia: true,
                href: customersArchive(),
                permission: 'delete_customer',
            },
            {
                labelKey: 'store.nav.notifications',
                href: getAllNotifications(),
                permission: 'add_notification',
            },
            {
                labelKey: 'store.nav.classifiedProducts',
                inertia: true,
                href: classifiedProductsIndex(),
                permission: 'view_classified_products',
                setting: 'classified_product',
            },
            {
                labelKey: 'store.nav.classifiedPackages',
                href: customerPackagesIndex(),
                permission: 'view_classified_packages',
                setting: 'classified_product',
            },
        ],
    },
    {
        labelKey: 'store.nav.representatives',
        icon: Contact,
        permission: [
            'view_all_customers',
            'view_representative_packages',
            'view_representative_payments',
            'view_representative_visits',
        ],
        children: [
            {
                labelKey: 'store.nav.representativeList',
                inertia: true,
                href: representativesIndex(),
                permission: 'view_all_customers',
            },
            {
                labelKey: 'store.nav.representativePackages',
                inertia: true,
                href: representativePackagesIndex(),
                permission: 'view_representative_packages',
            },
            {
                labelKey: 'store.nav.representativePayments',
                inertia: true,
                href: representativePaymentsIndex(),
                permission: 'view_representative_payments',
            },
            {
                labelKey: 'store.nav.representativeVisits',
                inertia: true,
                href: visitsIndex(),
                permission: 'view_representative_visits',
            },
        ],
    },
    {
        labelKey: 'store.nav.sellers',
        icon: Store,
        setting: 'vendor_system_activation',
        children: [
            {
                labelKey: 'store.nav.allSellers',
                href: sellersIndex(),
                permission: 'view_all_seller',
            },
            {
                labelKey: 'store.nav.payouts',
                href: payment_histories(),
                permission: 'seller_payment_history',
                setting: 'Payouts_Section',
            },
            {
                labelKey: 'store.nav.payoutRequests',
                href: withdrawRequestsIndex[
                    '/temp/admin/withdraw_requests_all'
                ](),
                permission: 'view_seller_payout_requests',
                setting: 'Payout_request_Section',
            },
            {
                labelKey: 'store.nav.sellerCommission',
                href: vendor_commission(),
                permission: 'seller_commission_configuration',
                setting: 'Seller_Commission_Section',
            },
            {
                labelKey: 'store.nav.sellerVerificationForm',
                href: seller_verification_form(),
                permission: 'seller_verification_form_configuration',
                setting: 'Seller_Verify_Form_Section',
            },
        ],
    },
    {
        labelKey: 'store.nav.trips',
        icon: Truck,
        children: [
            {
                labelKey: 'store.nav.trucks',
                children: [
                    {
                        labelKey: 'store.nav.allTrucks',
                        href: trucksIndex(),
                        permission: 'view_all_trucks',
                    },
                    {
                        labelKey: 'store.nav.createTruck',
                        href: trucksCreate(),
                        permission: 'add_truck',
                    },
                ],
            },
            {
                labelKey: 'store.nav.tripsGroup',
                children: [
                    {
                        labelKey: 'store.nav.allTrips',
                        href: getAllTrips(),
                        permission: 'view_all_trips',
                    },
                    {
                        labelKey: 'store.nav.todayTrips',
                        href: getTodayTrips(),
                        permission: 'view_all_trips',
                    },
                    {
                        labelKey: 'store.nav.createTrip',
                        href: tripsCreate(),
                        permission: 'add_trip',
                    },
                    {
                        labelKey: 'store.nav.createTripFromMap',
                        href: create_from_map(),
                        permission: 'add_trip',
                    },
                    {
                        labelKey: 'store.nav.tripOrders',
                        href: tripOrders(),
                        permission: 'view_all_trips',
                    },
                ],
            },
            {
                labelKey: 'store.nav.drivers',
                children: [
                    {
                        labelKey: 'store.nav.allDrivers',
                        href: driversIndex(),
                        permission: 'view_all_drivers',
                    },
                    {
                        labelKey: 'store.nav.createDriver',
                        href: driversCreate(),
                        permission: 'add_driver',
                    },
                ],
            },
        ],
    },
    {
        labelKey: 'store.nav.uploadedFiles',
        href: uploadedFilesIndex['/temp/admin/uploaded-files'](),
        icon: FolderOpen,
    },
    {
        labelKey: 'store.nav.homeBanners',
        href: customPageEdit['/temp/admin/website/custom-pages/edit/{id}'](
            { id: 'home' },
            { query: { page: 'home' } },
        ),
        icon: Images,
        permission: 'edit_website_page',
    },
    {
        labelKey: 'store.nav.orderConfiguration',
        href: order_configuration(),
        icon: SlidersHorizontal,
        permission: 'order_configuration',
        setting: 'Order_configuration_section',
    },
    {
        labelKey: 'store.nav.offersCatalog',
        href: offersCatalogIndex(),
        icon: BookOpen,
    },
    {
        labelKey: 'store.nav.reports',
        icon: FileText,
        setting: 'Reports_Section',
        children: [
            {
                labelKey: 'store.nav.inHouseSaleReport',
                href: in_house_sale_report(),
                permission: 'in_house_product_sale_report',
                setting: 'In_House_Sales_Report',
            },
            {
                labelKey: 'store.nav.sellerSaleReport',
                href: seller_sale_report(),
                permission: 'seller_products_sale_report',
                setting: 'vendor_system_activation',
            },
            {
                labelKey: 'store.nav.stockReport',
                href: stock_report(),
                permission: 'products_stock_report',
                setting: 'Products_Stock_Report',
            },
            {
                labelKey: 'store.nav.wishlistReport',
                href: wish_report(),
                permission: 'product_wishlist_report',
                setting: 'Products_Wishlist_Report',
            },
            {
                labelKey: 'store.nav.userSearchReport',
                href: user_search_report(),
                permission: 'user_search_report',
                setting: 'User_Searches_Report',
            },
            {
                labelKey: 'store.nav.commissionHistory',
                href: commission_history(),
                permission: 'commission_history_report',
                setting: 'Commission_History_Report',
            },
            {
                labelKey: 'store.nav.walletHistory',
                href: wallet_transaction_history(),
                permission: 'wallet_transaction_report',
                setting: 'Wallet_Recharge_History',
            },
        ],
    },
    {
        labelKey: 'store.nav.blog',
        icon: Newspaper,
        setting: 'Blog_Section',
        children: [
            {
                labelKey: 'store.nav.allPosts',
                href: blogIndex(),
                permission: 'view_blogs',
            },
            {
                labelKey: 'store.nav.blogCategories',
                href: blogCategoriesIndex(),
                permission: 'view_blog_categories',
            },
        ],
    },
    {
        labelKey: 'store.nav.marketing',
        icon: Megaphone,
        setting: 'Marketing_Section',
        children: [
            {
                labelKey: 'store.nav.flashDeals',
                href: flashDealsIndex(),
                permission: 'view_all_flash_deals',
                setting: 'Flash_deals',
            },
            {
                labelKey: 'store.nav.offerDeals',
                href: offerDealsIndex(),
                permission: 'view_all_offer_deals',
                setting: 'Offer_deals',
            },
            {
                labelKey: 'store.nav.dynamicPopups',
                href: dynamicPopupsIndex(),
                permission: 'view_all_dynamic_popups',
                setting: 'Offer_deals',
            },
            {
                labelKey: 'store.nav.newsletters',
                href: newslettersIndex(),
                permission: 'send_newsletter',
                setting: 'Newsletters',
            },
            {
                labelKey: 'store.nav.subscribers',
                href: subscribersIndex['/temp/admin/subscribers'](),
                permission: 'view_all_subscribers',
                setting: 'Newsletters',
            },
            {
                labelKey: 'store.nav.coupons',
                href: couponsIndex(),
                permission: 'view_all_coupons',
                setting: 'coupon_system',
            },
        ],
    },
    {
        labelKey: 'store.nav.support',
        icon: LifeBuoy,
        setting: 'Support_Section',
        children: [
            {
                labelKey: 'store.nav.tickets',
                href: supportTicketsIndex(),
                permission: 'view_all_support_tickets',
            },
            {
                labelKey: 'store.nav.productConversations',
                href: conversationsIndex(),
                permission: 'view_all_product_conversations',
                setting: 'conversation_system',
            },
            {
                labelKey: 'store.nav.productQueries',
                href: productQueriesIndex['/temp/admin/product-queries'](),
                permission: 'view_all_product_queries',
                setting: 'product_query_activation',
            },
        ],
    },
    {
        labelKey: 'store.nav.websiteSetup',
        icon: Monitor,
        setting: 'website_setup',
        children: [
            {
                labelKey: 'store.nav.header',
                href: header(),
                permission: 'header_setup',
                setting: 'website_setup_header',
            },
            {
                labelKey: 'store.nav.footer',
                href: footer(),
                permission: 'footer_setup',
                setting: 'website_setup_footer',
            },
            {
                labelKey: 'store.nav.pages',
                href: pages(),
                permission: 'view_all_website_pages',
                setting: 'website_setup_pages',
            },
            {
                labelKey: 'store.nav.appearance',
                href: appearance(),
                permission: 'website_appearance',
                setting: 'website_setup_appearance',
            },
        ],
    },
    {
        labelKey: 'store.nav.setup',
        icon: Settings,
        setting: 'Setup_configuration_section',
        children: [
            {
                labelKey: 'store.nav.generalSettings',
                href: general_setting(),
                permission: 'general_settings',
            },
            {
                labelKey: 'store.nav.featuresActivation',
                href: activation(),
                permission: 'features_activation',
                raizerAdmin: true,
            },
            {
                labelKey: 'store.nav.languages',
                href: languagesIndex(),
                permission: 'language_setup',
                setting: 'Languages_section',
            },
            {
                labelKey: 'store.nav.currency',
                href: currenciesIndex(),
                permission: 'currency_setup',
                setting: 'currency_section',
            },
            {
                labelKey: 'store.nav.vatTax',
                href: taxesIndex(),
                permission: 'vat_&_tax_setup',
                setting: 'VAT_TAX_section',
            },
            {
                labelKey: 'store.nav.pickupPoints',
                href: pickupPointsIndex(),
                permission: 'pickup_point_setup',
                setting: 'Setup_pickup_point_section',
            },
            {
                labelKey: 'store.nav.smtpSettings',
                href: smtp_settings(),
                permission: 'smtp_settings',
                setting: 'SMTP_Section',
            },
            {
                labelKey: 'store.nav.paymentMethods',
                href: payment_method(),
                permission: 'payment_methods_configurations',
                raizerAdmin: true,
            },
            {
                labelKey: 'store.nav.fileSystem',
                href: file_system(),
                permission: 'file_system_&_cache_configuration',
                raizerAdmin: true,
            },
            {
                labelKey: 'store.nav.socialLogins',
                href: social_login(),
                permission: 'social_media_logins',
                setting: 'Socail_Media_logins_section',
            },
            {
                labelKey: 'store.nav.facebook',
                setting: 'Facebook_section',
                children: [
                    {
                        labelKey: 'store.nav.facebookChat',
                        href: facebook_chat(),
                        permission: 'facebook_chat',
                    },
                    {
                        labelKey: 'store.nav.facebookComment',
                        href: facebook_comment(),
                        permission: 'facebook_comment',
                    },
                ],
            },
            {
                labelKey: 'store.nav.google',
                raizerAdmin: true,
                children: [
                    {
                        labelKey: 'store.nav.analyticsTools',
                        href: google_analytics(),
                        permission: 'analytics_tools_configuration',
                    },
                    {
                        labelKey: 'store.nav.googleRecaptcha',
                        href: google_recaptcha(),
                        permission: 'google_recaptcha_configuration',
                    },
                    {
                        labelKey: 'store.nav.googleMap',
                        href: google_map(),
                        permission: 'google_map_setting',
                    },
                    {
                        labelKey: 'store.nav.googleFirebase',
                        href: google_firebase(),
                        permission: 'google_firebase_setting',
                    },
                ],
            },
            {
                labelKey: 'store.nav.shipping',
                setting: 'Setup_Shipping_section',
                children: [
                    {
                        labelKey: 'store.nav.shippingConfiguration',
                        href: shipping_configuration(),
                        permission: 'shipping_configuration',
                    },
                    {
                        labelKey: 'store.nav.shippingCountries',
                        href: countriesIndex(),
                        permission: 'shipping_country_setting',
                    },
                    {
                        labelKey: 'store.nav.shippingStates',
                        href: statesIndex(),
                        permission: 'manage_shipping_states',
                    },
                    {
                        labelKey: 'store.nav.shippingCities',
                        href: citiesIndex(),
                        permission: 'manage_shipping_cities',
                    },
                    {
                        labelKey: 'store.nav.shippingZones',
                        href: zonesIndex(),
                        permission: 'manage_zones',
                    },
                    {
                        labelKey: 'store.nav.shippingCarriers',
                        href: carriersIndex(),
                        permission: 'manage_carriers',
                    },
                ],
            },
        ],
    },
    {
        labelKey: 'store.nav.staff',
        icon: UserCog,
        setting: 'Staffs_section',
        children: [
            {
                labelKey: 'store.nav.allStaff',
                href: staffIndex(),
                permission: 'view_all_staffs',
                inertia: true,
            },
            {
                labelKey: 'store.nav.staffRoles',
                href: rolesIndex(),
                permission: 'view_staff_roles',
                inertia: true,
            },
        ],
    },
];

function passesGate(item: StoreMenuGate, access: StoreAccess): boolean {
    if (item.raizerAdmin && !access.isRaizerAdmin) {
        return false;
    }

    if (
        item.setting &&
        !access.isRaizerAdmin &&
        !access.setting(item.setting)
    ) {
        return false;
    }

    const permissions =
        item.permission === undefined ? [] : [item.permission].flat();

    return permissions.length === 0 || permissions.some(access.can);
}

/**
 * The entries `access` may see. A group shows only while one of its
 * entries does, as the Blade sidebar's `@canany` around each group did.
 */
export function visibleStoreMenu(
    items: StoreMenuItem[],
    access: StoreAccess,
): StoreMenuItem[] {
    return items.flatMap((item): StoreMenuItem[] => {
        if (!passesGate(item, access)) {
            return [];
        }

        if (!isStoreMenuGroup(item)) {
            return [item];
        }

        const children = visibleStoreMenu(item.children, access);

        return children.length > 0 ? [{ ...item, children }] : [];
    });
}

/** Every link in `items`, nested ones included. */
export function storeMenuLinks(items: StoreMenuItem[]): StoreMenuLink[] {
    return items.flatMap((item) =>
        isStoreMenuGroup(item) ? storeMenuLinks(item.children) : [item],
    );
}
