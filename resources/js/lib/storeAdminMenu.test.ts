import { describe, expect, it } from 'vitest';
import { edit as customPageEdit } from '@/actions/App/Http/Controllers/PageController';
import { index as citiesIndex } from '@/actions/App/Http/Controllers/Store/Admin/CityController';
import { index as countriesIndex } from '@/actions/App/Http/Controllers/Store/Admin/CountryController';
import { index as currenciesIndex } from '@/actions/App/Http/Controllers/Store/Admin/CurrencyController';
import { index as orderConfigurationIndex } from '@/actions/App/Http/Controllers/Store/Admin/OrderConfigurationController';
import { index as pickupPointsIndex } from '@/actions/App/Http/Controllers/Store/Admin/PickupPointController';
import { index as rolesIndex } from '@/actions/App/Http/Controllers/Store/Admin/RoleController';
import { index as shippingConfigurationIndex } from '@/actions/App/Http/Controllers/Store/Admin/ShippingConfigurationController';
import { index as staffIndex } from '@/actions/App/Http/Controllers/Store/Admin/StaffController';
import { index as statesIndex } from '@/actions/App/Http/Controllers/Store/Admin/StateController';
import { index as taxesIndex } from '@/actions/App/Http/Controllers/Store/Admin/TaxController';
import { index as zonesIndex } from '@/actions/App/Http/Controllers/Store/Admin/ZoneController';
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

        expect(inertiaUrls).toEqual([
            orderConfigurationIndex.url(),
            currenciesIndex.url(),
            taxesIndex.url(),
            pickupPointsIndex.url(),
            shippingConfigurationIndex.url(),
            countriesIndex.url(),
            statesIndex.url(),
            citiesIndex.url(),
            zonesIndex.url(),
            staffIndex.url(),
            rolesIndex.url(),
        ]);
    });

    it('sends Home banners to the home page editor inside the store', () => {
        const homeBanners = storeMenuLinks(storeAdminMenu).find(
            (link) => link.labelKey === 'store.nav.homeBanners',
        );

        expect(homeBanners?.href.url).toBe(
            customPageEdit['/temp/admin/website/custom-pages/edit/{id}'].url(
                { id: 'home' },
                { query: { page: 'home' } },
            ),
        );
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
            'store.nav.uploadedFiles',
            'store.nav.offersCatalog',
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
                        permissions: ['view_classified_packages'],
                    }),
                ),
            ).map((item) => item.labelKey),
        ).toEqual(['store.nav.representatives']);
    });
});
