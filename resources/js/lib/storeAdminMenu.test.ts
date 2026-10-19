import { describe, expect, it } from 'vitest';
import { index as attributesIndex } from '@/actions/App/Http/Controllers/Store/Admin/AttributeController';
import { index as blogCategoriesIndex } from '@/actions/App/Http/Controllers/Store/Admin/BlogCategoryController';
import { index as blogIndex } from '@/actions/App/Http/Controllers/Store/Admin/BlogController';
import { index as brandDiscountsIndex } from '@/actions/App/Http/Controllers/Store/Admin/BrandDiscountController';
import { index as colorsIndex } from '@/actions/App/Http/Controllers/Store/Admin/ColorController';
import { index as conversationsIndex } from '@/actions/App/Http/Controllers/Store/Admin/ConversationController';
import { index as couponsIndex } from '@/actions/App/Http/Controllers/Store/Admin/CouponController';
import {
    index as customersIndex,
    archive as customersArchive,
    repIndex as representativesIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import { index as customerNotificationsIndex } from '@/actions/App/Http/Controllers/Store/Admin/CustomerNotificationController';
import {
    index as driversIndex,
    create as driversCreate,
} from '@/actions/App/Http/Controllers/Store/Admin/DriverController';
import { index as dynamicPopupsIndex } from '@/actions/App/Http/Controllers/Store/Admin/DynamicPopupController';
import { index as flashDealsIndex } from '@/actions/App/Http/Controllers/Store/Admin/FlashDealController';
import { index as googleMapIndex } from '@/actions/App/Http/Controllers/Store/Admin/GoogleMapController';
import { index as languagesIndex } from '@/actions/App/Http/Controllers/Store/Admin/LanguageController';
import { index as newslettersIndex } from '@/actions/App/Http/Controllers/Store/Admin/NewsletterController';
import { index as offerDealsIndex } from '@/actions/App/Http/Controllers/Store/Admin/OfferController';
import { index as offersIndex } from '@/actions/App/Http/Controllers/Store/Admin/OffersCatalogController';
import { all_orders } from '@/actions/App/Http/Controllers/Store/Admin/OrderController';
import { create as featureOrderCreate } from '@/actions/App/Http/Controllers/Store/Admin/OrderEntryController';
import { index as bulkIndex } from '@/actions/App/Http/Controllers/Store/Admin/ProductBulkUploadController';
import { index as productQueriesIndex } from '@/actions/App/Http/Controllers/Store/Admin/ProductQueryController';
import {
    in_house_sale_report,
    seller_sale_report,
    stock_report,
    wish_report,
    user_search_report,
    commission_history,
    wallet_transaction_history,
} from '@/actions/App/Http/Controllers/Store/Admin/ReportController';
import { index as representativePackagesIndex } from '@/actions/App/Http/Controllers/Store/Admin/RepresentativePackageController';
import { index as representativePaymentsIndex } from '@/actions/App/Http/Controllers/Store/Admin/RepresentativePaymentController';
import { index as reviewsIndex } from '@/actions/App/Http/Controllers/Store/Admin/ReviewController';
import { index as rolesIndex } from '@/actions/App/Http/Controllers/Store/Admin/RoleController';
import {
    activation,
    smtp,
    socialLogin,
    facebookChat,
    facebookComments,
    analytics,
    recaptcha,
    firebase,
    payments,
    filesystem,
} from '@/actions/App/Http/Controllers/Store/Admin/SettingsController';
import { index as staffIndex } from '@/actions/App/Http/Controllers/Store/Admin/StaffController';
import { index as subscribersIndex } from '@/actions/App/Http/Controllers/Store/Admin/SubscriberController';
import { index as supportTicketsIndex } from '@/actions/App/Http/Controllers/Store/Admin/SupportTicketController';
import {
    create as tripsCreate,
    index as tripsIndex,
    today as todayTrips,
} from '@/actions/App/Http/Controllers/Store/Admin/TripController';
import { create as tripsCreateFromMap } from '@/actions/App/Http/Controllers/Store/Admin/TripMapController';
import { index as tripOrdersIndex } from '@/actions/App/Http/Controllers/Store/Admin/TripOrderController';
import {
    index as trucksIndex,
    create as trucksCreate,
} from '@/actions/App/Http/Controllers/Store/Admin/TruckController';
import { index as uploadsIndex } from '@/actions/App/Http/Controllers/Store/Admin/UploadController';
import { index as visitsIndex } from '@/actions/App/Http/Controllers/Store/Admin/VisitController';
import { edit as appearance } from '@/actions/App/Http/Controllers/Store/Admin/WebsiteAppearanceController';
import { edit as footer } from '@/actions/App/Http/Controllers/Store/Admin/WebsiteFooterController';
import { edit as header } from '@/actions/App/Http/Controllers/Store/Admin/WebsiteHeaderController';
import { edit as homeEdit } from '@/actions/App/Http/Controllers/Store/Admin/WebsiteHomeController';
import { index as websitePages } from '@/actions/App/Http/Controllers/Store/Admin/WebsitePageController';
import { i18n } from '@/lib/i18n';
import { storeAccess } from '@/lib/storeAccess';
import {
    isStoreMenuGroup,
    storeAdminMenu,
    storeMenuLinks,
    visibleStoreMenu,
} from '@/lib/storeAdminMenu';
import type { StoreMenuItem } from '@/lib/storeAdminMenu';
import { storeSharedProps } from '@/testing/factories';

function labelKeys(items: StoreMenuItem[]): string[] {
    return items.flatMap((item) => [
        item.labelKey,
        ...(isStoreMenuGroup(item) ? labelKeys(item.children) : []),
    ]);
}

function settingKeys(items: StoreMenuItem[]): string[] {
    return items.flatMap((item) => [
        ...(item.setting ? [item.setting] : []),
        ...(isStoreMenuGroup(item) ? settingKeys(item.children) : []),
    ]);
}

const everySetting = Object.fromEntries(
    settingKeys(storeAdminMenu).map((key) => [key, true]),
);

describe('storeAdminMenu', () => {
    it('has an English and an Arabic label for every entry', () => {
        const missing = labelKeys(storeAdminMenu).filter(
            (key) => !i18n.global.te(key, 'en') || !i18n.global.te(key, 'ar'),
        );

        expect(missing).toEqual([]);
    });

    it('gives every top-level entry an icon', () => {
        expect(storeAdminMenu.filter((item) => !item.icon)).toEqual([]);
    });

    it('links every entry to a store route object', () => {
        for (const link of storeMenuLinks(storeAdminMenu)) {
            expect(link.href.url).toMatch(/^\/temp\//);
            expect(link.href.method).toBe('get');
        }
    });

    it('makes Inertia visits only to converted modules', () => {
        const inertiaUrls = storeMenuLinks(storeAdminMenu)
            .filter((link) => link.inertia)
            .map((link) => link.href.url);

        expect(inertiaUrls.sort()).toEqual(
            [
                '/temp/admin',
                '/temp/admin/classified_products',
                '/temp/admin/sellers',
                '/temp/admin/vendor_commission',
                '/temp/admin/verification/form',
                '/temp/admin/seller/payments',
                '/temp/admin/withdraw_requests_all',
                '/temp/admin/order-configuration',
                '/temp/admin/general-setting',
                '/temp/admin/currency',
                '/temp/admin/tax',
                '/temp/admin/pick_up_points',
                '/temp/admin/shipping_configuration',
                '/temp/admin/countries',
                '/temp/admin/states',
                '/temp/admin/cities',
                '/temp/admin/zones',
                '/temp/admin/carriers',
                '/temp/admin/categories',
                '/temp/admin/digitalproducts',
                '/temp/admin/products/all',
                '/temp/admin/products/admin',
                '/temp/admin/products/create',
                '/temp/admin/brands',
                '/temp/admin/customer_packages',
                customerNotificationsIndex.url(),
                in_house_sale_report.url(),
                seller_sale_report.url(),
                stock_report.url(),
                wish_report.url(),
                user_search_report.url(),
                commission_history.url(),
                wallet_transaction_history.url(),
                uploadsIndex.url(),
                '/temp/admin/customers/create',
                customersIndex.url(),
                customersArchive.url(),
                representativesIndex.url(),
                representativePackagesIndex.url(),
                representativePaymentsIndex.url(),
                visitsIndex.url(),
                bulkIndex.url(),
                brandDiscountsIndex.url(),
                attributesIndex.url(),
                colorsIndex.url(),
                reviewsIndex.url(),
                offersIndex.url(),
                header.url(),
                footer.url(),
                websitePages.url(),
                appearance.url(),
                homeEdit.url(),
                conversationsIndex.url(),
                productQueriesIndex.url(),
                supportTicketsIndex.url(),
                blogCategoriesIndex.url(),
                blogIndex.url(),
                couponsIndex.url(),
                dynamicPopupsIndex.url(),
                flashDealsIndex.url(),
                offerDealsIndex.url(),
                subscribersIndex.url(),
                newslettersIndex.url(),
                driversIndex.url(),
                driversCreate.url(),
                trucksIndex.url(),
                trucksCreate.url(),
                tripsIndex.url(),
                todayTrips.url(),
                tripsCreate.url(),
                tripsCreateFromMap.url(),
                tripOrdersIndex.url(),
                all_orders['/temp/admin/all_orders'].url(),
                all_orders['/temp/admin/seller_orders'].url(),
                all_orders['/temp/admin/orders_by_pickup_point'].url(),
                featureOrderCreate.url(),
                staffIndex.url(),
                rolesIndex.url(),
                languagesIndex.url(),
                activation.url(),
                smtp.url(),
                socialLogin.url(),
                facebookChat.url(),
                facebookComments.url(),
                analytics.url(),
                recaptcha.url(),
                googleMapIndex.url(),
                firebase.url(),
                payments.url(),
                filesystem.url(),
            ].sort(),
        );
    });

    it('sends Home banners to the home page editor inside the store', () => {
        const homeBanners = storeMenuLinks(storeAdminMenu).find(
            (link) => link.labelKey === 'store.nav.homeBanners',
        );

        expect(homeBanners?.href.url).toBe(homeEdit.url());
    });
});

describe('visibleStoreMenu', () => {
    it('shows a link only to a user holding its permission', () => {
        const menu = visibleStoreMenu(
            storeAdminMenu,
            storeAccess(
                storeSharedProps({
                    permissions: ['view_all_staffs'],
                    settings: { Staffs_section: true },
                }),
            ),
        );

        expect(labelKeys(menu)).toEqual([
            'store.nav.staff',
            'store.nav.allStaff',
        ]);
    });

    it('drops a group whose section setting is off, unless the user is the vendor', () => {
        const props = storeSharedProps({
            permissions: ['view_all_staffs'],
            settings: { Staffs_section: false },
        });

        expect(
            labelKeys(visibleStoreMenu(storeAdminMenu, storeAccess(props))),
        ).not.toContain('store.nav.staff');
        expect(
            labelKeys(
                visibleStoreMenu(
                    storeAdminMenu,
                    storeAccess({ ...props, isRaizerAdmin: true }),
                ),
            ),
        ).toContain('store.nav.allStaff');
    });

    it("keeps the vendor's own entries from a Super Admin who is not the vendor", () => {
        const keys = labelKeys(
            visibleStoreMenu(
                storeAdminMenu,
                storeAccess(
                    storeSharedProps({
                        isSuperAdmin: true,
                        settings: { Setup_configuration_section: true },
                    }),
                ),
            ),
        );

        expect(keys).toContain('store.nav.generalSettings');
        expect(keys).not.toContain('store.nav.featuresActivation');
        expect(keys).not.toContain('store.nav.paymentMethods');
        expect(keys).not.toContain('store.nav.google');
    });

    it('shows every entry to the vendor holding every permission', () => {
        const keys = labelKeys(
            visibleStoreMenu(
                storeAdminMenu,
                storeAccess(
                    storeSharedProps({
                        isSuperAdmin: true,
                        isRaizerAdmin: true,
                        settings: everySetting,
                    }),
                ),
            ),
        );

        expect(keys).toEqual(labelKeys(storeAdminMenu));
    });

    it('drops a group with no entry left to show', () => {
        const menu = visibleStoreMenu(
            [
                {
                    labelKey: 'store.nav.staff',
                    children: [
                        {
                            labelKey: 'store.nav.allStaff',
                            href: staffIndex(),
                            permission: 'view_all_staffs',
                        },
                    ],
                },
            ],
            storeAccess(storeSharedProps()),
        );

        expect(menu).toEqual([]);
    });

    it('shows a group gated on any of several permissions to a holder of one', () => {
        const representatives = storeAdminMenu.find(
            (item) => item.labelKey === 'store.nav.representatives',
        ) as StoreMenuItem;

        expect(
            visibleStoreMenu(
                [representatives],
                storeAccess(
                    storeSharedProps({
                        permissions: ['view_representative_packages'],
                    }),
                ),
            ).map((item) => item.labelKey),
        ).toEqual(['store.nav.representatives']);
    });
});
