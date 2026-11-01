import { describe, expect, it } from 'vitest';
import { edit as attributeValueEdit } from '@/actions/App/Http/Controllers/Store/Admin/AttributeValueController';
import { edit as brandEdit } from '@/actions/App/Http/Controllers/Store/Admin/BrandController';
import { index as brandDiscountIndex } from '@/actions/App/Http/Controllers/Store/Admin/BrandDiscountController';
import { edit as categoryEdit } from '@/actions/App/Http/Controllers/Store/Admin/CategoryController';
import {
    index as customersIndex,
    pageViews as customerPageViews,
} from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import { index as admin_dashboard } from '@/actions/App/Http/Controllers/Store/Admin/DashboardController';
import { edit as driverEdit } from '@/actions/App/Http/Controllers/Store/Admin/DriverController';
import { edit as offerEdit } from '@/actions/App/Http/Controllers/Store/Admin/OfferController';
import {
    all_orders,
    show as orderShow,
} from '@/actions/App/Http/Controllers/Store/Admin/OrderController';
import { edit as featureOrderEdit } from '@/actions/App/Http/Controllers/Store/Admin/OrderEntryController';
import {
    edit as productEdit,
    sellerEdit as sellerProductEdit,
} from '@/actions/App/Http/Controllers/Store/Admin/ProductController';
import { edit as productFieldSourcesEdit } from '@/actions/App/Http/Controllers/Store/Admin/ProductFieldSourceController';
import { index as profileIndex } from '@/actions/App/Http/Controllers/Store/Admin/ProfileController';
import { index as representativePackagesIndex } from '@/actions/App/Http/Controllers/Store/Admin/RepresentativePackageController';
import { index as sellerPaymentsIndex } from '@/actions/App/Http/Controllers/Store/Admin/SellerPaymentController';
import { index as withdrawRequestsIndex } from '@/actions/App/Http/Controllers/Store/Admin/SellerWithdrawRequestController';
import { index as staffIndex } from '@/actions/App/Http/Controllers/Store/Admin/StaffController';
import { show as tripShow } from '@/actions/App/Http/Controllers/Store/Admin/TripCheckpointController';
import { edit as tripEdit } from '@/actions/App/Http/Controllers/Store/Admin/TripController';
import {
    edit as truckEdit,
    photos as truckPhotos,
} from '@/actions/App/Http/Controllers/Store/Admin/TruckController';
import { show as visitShow } from '@/actions/App/Http/Controllers/Store/Admin/VisitController';
import { edit as websiteHomeEdit } from '@/actions/App/Http/Controllers/Store/Admin/WebsiteHomeController';
import { edit as websitePageEdit } from '@/actions/App/Http/Controllers/Store/Admin/WebsitePageController';
import {
    findActiveStoreMenuLink,
    normalizeStorePath,
    pathIsWithin,
    storeMenuItemContains,
    storeRoutePrefix,
} from '@/lib/storeAdminActiveMenu';
import type { StoreMenuParent } from '@/lib/storeAdminActiveMenu';
import {
    isStoreMenuGroup,
    storeAdminMenu,
    storeAdminPageParents,
    storeMenuLinks,
} from '@/lib/storeAdminMenu';
import type { StoreMenuItem } from '@/lib/storeAdminMenu';

function link(labelKey: string, url: string, exact = false): StoreMenuItem {
    return { labelKey, href: { url, method: 'get' }, exact };
}

function active(url: string): string | null {
    return (
        findActiveStoreMenuLink(url, storeAdminMenu, storeAdminPageParents)
            ?.labelKey ?? null
    );
}

describe('normalizeStorePath', () => {
    it('drops the query string, fragment, origin and trailing slashes', () => {
        expect(normalizeStorePath('/a/b/?page=2#top')).toBe('/a/b');
        expect(normalizeStorePath('https://shop.test/a/b//')).toBe('/a/b');
        expect(normalizeStorePath('')).toBe('/');
    });
});

describe('pathIsWithin', () => {
    it('matches the path itself and paths below it on a segment boundary', () => {
        expect(pathIsWithin('/p', '/p')).toBe(true);
        expect(pathIsWithin('/p/5/edit', '/p')).toBe(true);
        expect(pathIsWithin('/products-categories', '/products')).toBe(false);
    });
});

describe('storeRoutePrefix', () => {
    it('cuts a Wayfinder route before its first parameter', () => {
        expect(storeRoutePrefix(customerPageViews)).toBe(
            customerPageViews.definition.url.split('/{')[0],
        );
        expect(storeRoutePrefix('/x/{id}/edit')).toBe('/x');
        expect(storeRoutePrefix('/x/')).toBe('/x');
    });
});

describe('findActiveStoreMenuLink (synthetic menu)', () => {
    const menu: StoreMenuItem[] = [
        link('home', '/s', true),
        {
            labelKey: 'catalog',
            children: [
                link('products', '/s/products'),
                link('categories', '/s/products-categories'),
                link('imports', '/s/imports/index'),
                link('importsNew', '/s/imports/create'),
            ],
        },
    ];
    const find = (url: string, parents: StoreMenuParent[] = []) =>
        findActiveStoreMenuLink(url, menu, parents)?.labelKey ?? null;

    it('matches exactly and below, ignoring query and trailing slash', () => {
        expect(find('/s/products')).toBe('products');
        expect(find('/s/products/')).toBe('products');
        expect(find('/s/products?page=2')).toBe('products');
        expect(find('/s/products/9/edit')).toBe('products');
    });

    it('never matches a sibling that merely shares a string prefix', () => {
        expect(find('/s/products-categories')).toBe('categories');
        expect(find('/s/products-categories/3/edit')).toBe('categories');
        expect(find('/s/productsx')).toBeNull();
    });

    it('lets the longest match win, so only one link is active', () => {
        expect(find('/s/imports/create')).toBe('importsNew');
        expect(find('/s/imports/7/edit')).toBe('imports');
        expect(find('/s/imports')).toBe('imports');
    });

    it('keeps an exact link from claiming the pages below it', () => {
        expect(find('/s')).toBe('home');
        expect(find('/s/unlisted')).toBeNull();
    });

    it('follows an explicit parent, or none when the parent is null', () => {
        const parents = [
            { page: '/s/other/{id}/view', parent: '/s/products' },
            { page: '/s/me', parent: null },
        ];

        expect(find('/s/other/4/view', parents)).toBe('products');
        expect(find('/s/other', parents)).toBe('products');
        expect(find('/s/me', parents)).toBeNull();
    });

    it('reports whether a group holds the active link', () => {
        const current = findActiveStoreMenuLink('/s/products/1', menu);

        expect(storeMenuItemContains(menu[1], current)).toBe(true);
        expect(storeMenuItemContains(menu[0], current)).toBe(false);
        expect(storeMenuItemContains(menu[1], null)).toBe(false);
    });
});

describe('findActiveStoreMenuLink (the real Store admin menu)', () => {
    it('activates the dashboard on itself only', () => {
        expect(active(admin_dashboard.url())).toBe('store.nav.dashboard');
        expect(active(`${admin_dashboard.url()}/`)).toBe('store.nav.dashboard');
    });

    it('activates nothing for pages reached from outside the menu', () => {
        expect(active(profileIndex.url())).toBeNull();
        expect(active('/temp/admin/all-notification')).toBeNull();
    });

    it('highlights the list entry on its create, edit and show pages', () => {
        expect(active(staffIndex.url())).toBe('store.nav.allStaff');
        expect(active(categoryEdit.url({ category: 3 }))).toBe(
            'store.nav.categories',
        );
        expect(active(brandEdit.url({ brand: 3 }))).toBe('store.nav.brands');
        expect(active(`${staffIndex.url()}?page=2`)).toBe('store.nav.allStaff');
    });

    it('keeps sibling routes sharing a prefix apart', () => {
        expect(active(brandDiscountIndex.url())).toBe(
            'store.nav.brandDiscount',
        );
        expect(active(brandEdit.url({ brand: 1 }))).toBe('store.nav.brands');
    });

    it('covers detail pages that sit beside an /index list route', () => {
        expect(active(tripEdit.url({ trip: 5 }))).toBe('store.nav.allTrips');
        expect(active(tripShow.url({ trip: 5 }))).toBe('store.nav.allTrips');
        expect(active(truckEdit.url({ truck: 5 }))).toBe('store.nav.allTrucks');
        expect(active(truckPhotos.url())).toBe('store.nav.allTrucks');
        expect(active(driverEdit.url({ driver: 5 }))).toBe(
            'store.nav.allDrivers',
        );
        expect(active(offerEdit.url({ offer: 5 }))).toBe(
            'store.nav.offerDeals',
        );
        expect(active(representativePackagesIndex.url())).toBe(
            'store.nav.representativePackages',
        );
        expect(active(visitShow.url({ visit: 5 }))).toBe(
            'store.nav.representativeVisits',
        );
    });

    it('picks the in-house or seller entry for a product edit', () => {
        expect(active(productEdit.url({ product: 5 }))).toBe(
            'store.nav.inHouseProducts',
        );
        expect(active(sellerProductEdit.url({ product: 5 }))).toBe(
            'store.nav.sellerProducts',
        );
    });

    it('maps pages outside their entry URL through the parent map', () => {
        expect(active(customerPageViews.url({ customer: 5 }))).toBe(
            'store.nav.customerList',
        );
        expect(active(customersIndex.url())).toBe('store.nav.customerList');
        expect(active(attributeValueEdit.url({ attributeValue: 5 }))).toBe(
            'store.nav.attributes',
        );
        expect(
            active(orderShow['/temp/admin/orders/{order}'].url({ order: 5 })),
        ).toBe('store.nav.allOrders');
        expect(active(all_orders['/temp/admin/all_orders'].url())).toBe(
            'store.nav.allOrders',
        );
        expect(active(featureOrderEdit.url({ order: 5 }))).toBe(
            'store.nav.allOrders',
        );
        expect(
            active(
                withdrawRequestsIndex['/temp/admin/withdraw_requests'].url(),
            ),
        ).toBe('store.nav.payoutRequests');
        expect(active(sellerPaymentsIndex.url())).toBe('store.nav.payouts');
    });

    it('highlights product data sources under Setup, apart from the product lists', () => {
        expect(active(productFieldSourcesEdit.url())).toBe(
            'store.nav.productFieldSources',
        );
        expect(active(`${productFieldSourcesEdit.url()}?saved=1`)).toBe(
            'store.nav.productFieldSources',
        );
        const setup = storeAdminMenu.find(
            (item) => item.labelKey === 'store.nav.setup',
        );
        expect(setup).toBeDefined();
        expect(
            storeMenuItemContains(
                setup!,
                findActiveStoreMenuLink(
                    productFieldSourcesEdit.url(),
                    storeAdminMenu,
                    storeAdminPageParents,
                ),
            ),
        ).toBe(true);
    });

    it('keeps the home banners entry apart from custom pages', () => {
        expect(active(websiteHomeEdit.url())).toBe('store.nav.homeBanners');
        expect(active(websitePageEdit.url({ page: 'about' }))).toBe(
            'store.nav.pages',
        );
    });

    it('gives every menu link a distinct path and finds it as itself', () => {
        const links = storeMenuLinks(storeAdminMenu);
        const paths = links.map((item) => normalizeStorePath(item.href.url));

        for (const item of links) {
            const owner = findActiveStoreMenuLink(
                item.href.url,
                storeAdminMenu,
                storeAdminPageParents,
            );

            expect(owner?.href.url, item.labelKey).toBe(item.href.url);
        }

        expect(new Set(paths).size).toBe(paths.length);
    });

    it('resolves every parent-map entry to a menu link or to none', () => {
        const hrefs = storeMenuLinks(storeAdminMenu).map((item) =>
            normalizeStorePath(item.href.url),
        );

        for (const { parent } of storeAdminPageParents) {
            if (parent !== null) {
                expect(hrefs).toContain(storeRoutePrefix(parent));
            }
        }
    });

    it('has no group without links', () => {
        const check = (items: StoreMenuItem[]) =>
            items.forEach((item) => {
                if (isStoreMenuGroup(item)) {
                    expect(storeMenuLinks([item]).length).toBeGreaterThan(0);
                    check(item.children);
                }
            });

        check(storeAdminMenu);
    });
});
