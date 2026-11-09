import { Link } from '@inertiajs/vue3';
import { Truck } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { index as cellLogsIndex } from '@/actions/App/Http/Controllers/Admin/CellStatusLogController';
import { index as cellVerificationRoundsIndex } from '@/actions/App/Http/Controllers/Admin/CellVerificationRoundController';
import { index as helpIndex } from '@/actions/App/Http/Controllers/Admin/HelpController';
import { index as palletsIndex } from '@/actions/App/Http/Controllers/Admin/PalletController';
import { index as productsIndex } from '@/actions/App/Http/Controllers/Admin/ProductController';
import {
    index as receiptsIndex,
    show as receiptShow,
} from '@/actions/App/Http/Controllers/Admin/ReceiptController';
import {
    index as rowsIndex,
    edit as rowEdit,
} from '@/actions/App/Http/Controllers/Admin/RowController';
import { edit as settingsEdit } from '@/actions/App/Http/Controllers/Admin/SettingController';
import { index as usersIndex } from '@/actions/App/Http/Controllers/Admin/UserController';
import { index as warehousesIndex } from '@/actions/App/Http/Controllers/Admin/WarehouseController';
import { index as warehouseMapIndex } from '@/actions/App/Http/Controllers/Admin/WarehouseMapController';
import BackofficeShell from '@/components/BackofficeShell.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import SidebarNavLink from '@/components/SidebarNavLink.vue';
import SuccessBanner from '@/components/SuccessBanner.vue';
import { t } from '@/lib/i18n';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import AdminLayout from './AdminLayout.vue';

const { usePageMock, routerPostMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerPostMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub } = await import('@/testing/inertiaStubs');

    return {
        usePage: usePageMock,
        router: { post: routerPostMock },
        Link: createLinkStub(),
    };
});

const MENU_URLS = [
    '/admin',
    warehousesIndex.url(),
    rowsIndex.url(),
    palletsIndex.url(),
    receiptsIndex.url(),
    warehouseMapIndex.url(),
    productsIndex.url(),
    cellLogsIndex.url(),
    cellVerificationRoundsIndex.url(),
    usersIndex.url(),
    settingsEdit.url(),
    helpIndex.url(),
];

function mountLayout(
    url: string,
    props: { fullBleed?: boolean } = {},
    flash: { success?: string } = {},
) {
    usePageMock.mockReturnValue({
        url,
        props: defaultAuthProps({
            brandName: 'Store name',
            auth: {
                user: { id: 7, name: 'Jane Doe', email: 'jane@example.com' },
            },
        }),
        flash,
    });

    return mount(AdminLayout, {
        props,
        slots: { default: '<p>Page content</p>' },
        attachTo: document.body,
    });
}

function menu(wrapper: ReturnType<typeof mountLayout>) {
    return wrapper.get(`nav[aria-label="${t('nav.menuLabel')}"]`);
}

function menuLink(wrapper: ReturnType<typeof mountLayout>, href: string) {
    return menu(wrapper)
        .findAll('a')
        .find((link) => link.attributes('href') === href);
}

describe('AdminLayout', () => {
    beforeEach(() => {
        resetMocks({ usePageMock, routerPostMock });
        window.localStorage.clear();
        document.body.innerHTML = '';
    });

    it('draws the page through the shared back-office shell, with its own sidebar id', () => {
        const wrapper = mountLayout(rowsIndex.url());
        const shell = wrapper.getComponent(BackofficeShell);

        expect(shell.props('sidebarId')).toBe('admin-sidebar');
        expect(shell.props('area')).toBe(t('nav.brand'));
        expect(wrapper.find('aside#admin-sidebar').exists()).toBe(true);
    });

    it('shows the brand name and, under it, the Warehouse Admin title', () => {
        const wrapper = mountLayout(rowsIndex.url());

        expect(wrapper.get('[data-testid="sidebar-brand"]').text()).toBe(
            'Store name',
        );
        expect(wrapper.get('[data-testid="sidebar-area"]').text()).toBe(
            t('nav.brand'),
        );
        expect(wrapper.get('aside img').attributes('src')).toBe(
            '/assets/img/33logoqr.png',
        );
    });

    it('lists every destination in the sidebar menu in its fixed order', () => {
        const wrapper = mountLayout(rowsIndex.url());

        expect(
            menu(wrapper)
                .findAll('a')
                .map((link) => link.attributes('href')),
        ).toEqual(MENU_URLS);
    });

    it('groups the menu under Inventory, Activity and Administration', () => {
        const wrapper = mountLayout(rowsIndex.url());

        expect(
            menu(wrapper)
                .findAll('p')
                .map((heading) => heading.text()),
        ).toEqual([
            t('nav.groupInventory'),
            t('nav.groupActivity'),
            t('nav.groupAdmin'),
        ]);
    });

    it('lists Receipts in the Inventory group right after Pallets, with the receipts index url and the Truck icon', () => {
        const wrapper = mountLayout(rowsIndex.url());
        const hrefs = menu(wrapper)
            .findAll('a')
            .map((link) => link.attributes('href'));
        const link = menuLink(wrapper, receiptsIndex.url());
        const nav = wrapper
            .findAllComponents(SidebarNavLink)
            .find((item) => item.text() === t('nav.receipts'));

        expect(link?.text()).toBe(t('nav.receipts'));
        expect(hrefs.indexOf(receiptsIndex.url())).toBe(
            hrefs.indexOf(palletsIndex.url()) + 1,
        );
        expect(hrefs.indexOf(receiptsIndex.url())).toBeLessThan(
            hrefs.indexOf(cellLogsIndex.url()),
        );
        expect(nav?.props('href')).toEqual({
            url: receiptsIndex.url(),
            method: 'get',
        });
        expect(nav?.props('icon')).toBe(Truck);
    });

    it('lights the Receipts link up on a receipt page and nowhere else', () => {
        const wrapper = mountLayout(receiptShow.url({ receipt: 9 }));
        const active = menu(wrapper).findAll('[aria-current="page"]');

        expect(active).toHaveLength(1);
        expect(active[0].attributes('href')).toBe(receiptsIndex.url());
        expect(
            menuLink(wrapper, '/admin')?.attributes('aria-current'),
        ).toBeUndefined();
    });

    it('gives every menu link an icon and a Wayfinder route object, not a bare url string', () => {
        const wrapper = mountLayout(rowsIndex.url());
        const links = wrapper
            .findAllComponents(SidebarNavLink)
            .filter((link) => link.element.closest('nav') !== null);

        expect(links).toHaveLength(MENU_URLS.length);

        for (const link of links) {
            expect(link.props('icon')).toBeTruthy();
            expect(link.props('href')).toMatchObject({ method: 'get' });
        }

        expect(links[2].props('href')).toEqual({
            url: rowsIndex.url(),
            method: 'get',
        });
    });

    it('marks the exact current section as active with the brand-soft fill', () => {
        const wrapper = mountLayout(rowsIndex.url());
        const rowsLink = menuLink(wrapper, rowsIndex.url());
        const usersLink = menuLink(wrapper, usersIndex.url());

        expect(rowsLink?.attributes('aria-current')).toBe('page');
        expect(rowsLink?.classes()).toContain('bg-sidebar-active');
        expect(usersLink?.attributes('aria-current')).toBeUndefined();
        expect(usersLink?.classes()).not.toContain('bg-sidebar-active');
        expect(menu(wrapper).findAll('[aria-current="page"]')).toHaveLength(1);
    });

    it('uses a logical inline-start indicator so right-to-left flips it', () => {
        const wrapper = mountLayout(rowsIndex.url());

        expect(menuLink(wrapper, rowsIndex.url())?.classes()).toContain(
            'before:-start-2.5',
        );
    });

    it('names the current section in the header crumb', () => {
        const wrapper = mountLayout(rowEdit.url({ row: 'A' }));
        const crumb = wrapper.get(`nav[aria-label="${t('nav.breadcrumb')}"]`);

        expect(crumb.text()).toContain(t('nav.brand'));
        expect(crumb.text()).toContain(t('nav.rows'));
    });

    it('marks the dashboard link active on its own URL, not on other sections', () => {
        const wrapper = mountLayout('/admin');

        expect(menuLink(wrapper, '/admin')?.attributes('aria-current')).toBe(
            'page',
        );
        expect(
            menuLink(wrapper, rowsIndex.url())?.attributes('aria-current'),
        ).toBeUndefined();
    });

    it('does not mark the dashboard link active while a more specific section is open', () => {
        const wrapper = mountLayout(rowsIndex.url());

        expect(
            menuLink(wrapper, '/admin')?.attributes('aria-current'),
        ).toBeUndefined();
    });

    it('marks a nested section path as active too', () => {
        const wrapper = mountLayout(rowEdit.url({ row: 'A' }));

        expect(
            menuLink(wrapper, rowsIndex.url())?.attributes('aria-current'),
        ).toBe('page');
    });

    it('marks the warehouses link active on its subpages', () => {
        const wrapper = mountLayout(warehousesIndex.url() + '/3/edit');
        const link = menuLink(wrapper, warehousesIndex.url());

        expect(link?.text()).toBe(t('nav.warehouses'));
        expect(link?.attributes('aria-current')).toBe('page');
    });

    it('marks the pallets, receipts, settings and help destinations active on their pages', () => {
        for (const url of [
            palletsIndex.url({ query: { state: 'empty' } }),
            receiptsIndex.url({ query: { status: 'draft' } }),
            settingsEdit.url(),
            helpIndex.url() + '/rows',
        ]) {
            const wrapper = mountLayout(url);
            const active = menu(wrapper).findAll('[aria-current="page"]');

            expect(active).toHaveLength(1);
            expect(url.startsWith(active[0].attributes('href') ?? '-')).toBe(
                true,
            );
            wrapper.unmount();
        }
    });

    it('does not treat a section as active from a mere string prefix match', () => {
        const wrapper = mountLayout('/admin/rows-archive');

        expect(
            menuLink(wrapper, rowsIndex.url())?.attributes('aria-current'),
        ).toBeUndefined();
    });

    it('marks the cell log and products sections active with a filter query string', () => {
        let wrapper = mountLayout(
            cellLogsIndex.url({ query: { action: 'inbound' } }),
        );
        expect(
            menuLink(wrapper, cellLogsIndex.url())?.attributes('aria-current'),
        ).toBe('page');
        expect(
            menuLink(wrapper, '/admin')?.attributes('aria-current'),
        ).toBeUndefined();
        wrapper.unmount();

        wrapper = mountLayout(productsIndex.url({ query: { row_id: '1' } }));
        expect(
            menuLink(wrapper, productsIndex.url())?.attributes('aria-current'),
        ).toBe('page');
    });

    it('keeps the page in a padded column as wide as the control panel by default', () => {
        const wrapper = mountLayout(rowsIndex.url());
        const main = wrapper.get('main');

        expect(main.classes()).toEqual(
            expect.arrayContaining(['min-w-0', 'overflow-x-auto', 'px-4']),
        );
        expect(main.find('.max-w-screen-2xl').exists()).toBe(true);
        expect(main.find('.max-w-6xl').exists()).toBe(false);
    });

    it('hands the whole area beside the sidebar to a full-bleed page and folds the sidebar', () => {
        const wrapper = mountLayout(warehouseMapIndex.url(), {
            fullBleed: true,
        });

        expect(wrapper.get('main').classes()).toEqual(
            expect.arrayContaining(['relative', 'min-h-0', 'flex-1']),
        );
        expect(wrapper.find('main .max-w-screen-2xl').exists()).toBe(false);
        expect(wrapper.classes()).toEqual(
            expect.arrayContaining(['flex', 'h-dvh', 'flex-col']),
        );
        expect(wrapper.get('#admin-sidebar').attributes('data-collapsed')).toBe(
            'true',
        );
        expect(wrapper.getComponent(BackofficeShell).props()).toMatchObject({
            collapseScope: 'map',
            defaultCollapsed: true,
        });
    });

    it('keeps the sidebar open on ordinary pages', () => {
        const wrapper = mountLayout(rowsIndex.url());

        expect(wrapper.get('#admin-sidebar').attributes('data-collapsed')).toBe(
            'false',
        );
        expect(wrapper.getComponent(BackofficeShell).props()).toMatchObject({
            collapseScope: 'page',
            defaultCollapsed: false,
        });
    });

    it('opens the sidebar drawer from the header menu button', async () => {
        const wrapper = mountLayout(rowsIndex.url());
        const toggle = wrapper.get('button[aria-controls="admin-sidebar"]');

        expect(toggle.attributes('aria-label')).toBe(t('nav.openMenu'));
        expect(toggle.attributes('aria-expanded')).toBe('false');

        await toggle.trigger('click');

        expect(toggle.attributes('aria-expanded')).toBe('true');
        expect(wrapper.get('#admin-sidebar').classes()).toContain('flex');
    });

    it('closes the drawer when a menu link is clicked', async () => {
        const wrapper = mountLayout(rowsIndex.url());
        const toggle = wrapper.get('button[aria-controls="admin-sidebar"]');
        await toggle.trigger('click');

        await menuLink(wrapper, usersIndex.url())?.trigger('click');

        expect(toggle.attributes('aria-expanded')).toBe('false');
        expect(wrapper.get('#admin-sidebar').classes()).toContain('hidden');
    });

    it("shows the signed-in user's initials, name and e-mail in the sidebar footer", () => {
        const wrapper = mountLayout(rowsIndex.url());
        const footer = wrapper.get('#admin-sidebar');

        expect(footer.get('[data-testid="sidebar-avatar"]').text()).toBe('JD');
        expect(footer.text()).toContain('Jane Doe');
        expect(footer.text()).toContain('jane@example.com');
    });

    it('links the logout action to the /logout route from the sidebar footer', () => {
        const wrapper = mountLayout(rowsIndex.url());
        const logout = wrapper
            .get('#admin-sidebar')
            .findAll('a,button')
            .find((el) => el.attributes('href') === '/logout');

        expect(logout).toBeTruthy();
        expect(logout?.text()).toBe(t('nav.logout'));
    });

    it('puts the language switcher and the help link in the header', () => {
        const wrapper = mountLayout(rowsIndex.url());
        const header = wrapper.get('header');

        expect(header.findComponent(LanguageSwitcher).exists()).toBe(true);
        expect(header.text()).toContain('English');
        expect(header.text()).toContain('Arabic');

        const help = header
            .findAllComponents(Link)
            .find(
                (link: { props: (key: string) => unknown }) =>
                    (link.props('href') as { url: string }).url ===
                    helpIndex.url(),
            );
        expect(help?.attributes('aria-label')).toBe(t('nav.help'));
    });

    it('keeps a language switcher in the sidebar footer for phones', () => {
        const wrapper = mountLayout(rowsIndex.url());
        const switcher = wrapper
            .get('#admin-sidebar')
            .findComponent(LanguageSwitcher);

        expect(switcher.exists()).toBe(true);
        expect(switcher.element.parentElement?.className).toContain(
            'sm:hidden',
        );
    });

    it('renders a skip link that targets the main landmark', () => {
        const wrapper = mountLayout(rowsIndex.url());
        const skipLink = wrapper.get('a[href="#main"]');

        expect(skipLink.text()).toBe(t('nav.skipToContent'));
        expect(wrapper.get('main').attributes('id')).toBe('main');
    });

    it('renders the default slot content inside main', () => {
        const wrapper = mountLayout(rowsIndex.url());

        expect(wrapper.get('main').text()).toContain('Page content');
    });

    it('shows a flashed success message at the top of the page content', () => {
        const wrapper = mountLayout(
            rowsIndex.url(),
            {},
            { success: 'Row saved' },
        );

        const banner = wrapper.getComponent(SuccessBanner);
        expect(banner.attributes('role')).toBe('status');
        expect(banner.text()).toBe('Row saved');
        expect(
            wrapper.get('main').element.firstElementChild?.firstElementChild,
        ).toBe(banner.element);
    });

    it('shows no success banner without a flashed message', () => {
        const wrapper = mountLayout(rowsIndex.url());

        expect(wrapper.find('[role="status"]').exists()).toBe(false);
    });

    it('leaves the success banner out of a full-bleed page', () => {
        const wrapper = mountLayout(
            warehouseMapIndex.url(),
            { fullBleed: true },
            { success: 'Pallet stored' },
        );

        expect(wrapper.findComponent(SuccessBanner).exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Pallet stored');
    });
});
