import { Link } from '@inertiajs/vue3';
import { CircleUser, Warehouse } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { index as cellLogsIndex } from '@/actions/App/Http/Controllers/Admin/CellStatusLogController';
import { index as cellVerificationRoundsIndex } from '@/actions/App/Http/Controllers/Admin/CellVerificationRoundController';
import { index as productsIndex } from '@/actions/App/Http/Controllers/Admin/ProductController';
import {
    index as rowsIndex,
    edit as rowEdit,
} from '@/actions/App/Http/Controllers/Admin/RowController';
import { edit as settingsEdit } from '@/actions/App/Http/Controllers/Admin/SettingController';
import { index as usersIndex } from '@/actions/App/Http/Controllers/Admin/UserController';
import { index as warehouseMapIndex } from '@/actions/App/Http/Controllers/Admin/WarehouseMapController';
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

function mountLayout(url: string, props: { fullBleed?: boolean } = {}) {
    usePageMock.mockReturnValue({
        url,
        props: defaultAuthProps(),
    });

    return mount(AdminLayout, {
        props,
        slots: { default: '<p>Page content</p>' },
    });
}

describe('AdminLayout', () => {
    beforeEach(() => {
        resetMocks({ usePageMock, routerPostMock });
    });

    it('renders a nav link for every top-level section', () => {
        const wrapper = mountLayout(rowsIndex.url());

        const links = wrapper.findAll('nav a');
        expect(links.map((link) => link.attributes('href'))).toEqual([
            '/admin',
            rowsIndex.url(),
            warehouseMapIndex.url(),
            cellLogsIndex.url(),
            cellVerificationRoundsIndex.url(),
            productsIndex.url(),
            usersIndex.url(),
        ]);
    });

    it('links to the warehouse map with its own icon and label, and marks it active on its page', () => {
        const wrapper = mountLayout(warehouseMapIndex.url());

        const link = wrapper
            .findAll('nav a')
            .find((a) => a.attributes('href') === warehouseMapIndex.url());

        expect(link?.text()).toBe(t('nav.warehouseMap'));
        expect(link?.attributes('aria-current')).toBe('page');
    });

    it('keeps the page in a padded, width-limited column by default', () => {
        const wrapper = mountLayout(rowsIndex.url());

        expect(wrapper.get('main').classes()).toContain('max-w-6xl');
        expect(wrapper.get('main').classes()).not.toContain('flex-1');
    });

    it('hands the whole area under the app bar to a full-bleed page', () => {
        const wrapper = mountLayout(warehouseMapIndex.url(), {
            fullBleed: true,
        });

        expect(wrapper.get('main').classes()).toEqual(
            expect.arrayContaining(['relative', 'flex-1']),
        );
        expect(wrapper.get('main').classes()).not.toContain('max-w-6xl');
        expect(wrapper.classes()).toEqual(
            expect.arrayContaining(['flex', 'h-dvh', 'flex-col']),
        );
        expect(wrapper.get('nav').classes()).toContain('shrink-0');
    });

    it('gives every nav link a Wayfinder route object, not a bare url string', () => {
        const wrapper = mountLayout(rowsIndex.url());

        const navLinks = wrapper
            .findAllComponents(Link)
            .filter((link) => link.element.closest('nav') !== null);
        expect(navLinks).toHaveLength(7);

        for (const link of navLinks) {
            expect(link.props('href')).toMatchObject({ method: 'get' });
        }

        expect(navLinks[1].props('href')).toEqual({
            url: rowsIndex.url(),
            method: 'get',
        });
    });

    it('marks the exact current section as active', () => {
        const wrapper = mountLayout(rowsIndex.url());

        const links = wrapper.findAll('nav a');
        const rowsLink = links.find(
            (link) => link.attributes('href') === rowsIndex.url(),
        );
        const usersLink = links.find(
            (link) => link.attributes('href') === usersIndex.url(),
        );

        expect(rowsLink?.attributes('aria-current')).toBe('page');
        expect(rowsLink?.classes()).toContain('border-gray-900');
        expect(usersLink?.attributes('aria-current')).toBeUndefined();
        expect(usersLink?.classes()).not.toContain('border-gray-900');
    });

    it('uses a logical border-inline-start utility on the mobile nav link so RTL locales flip the active indicator', async () => {
        const wrapper = mountLayout(rowsIndex.url());
        const toggle = wrapper.get('button[aria-controls="admin-mobile-menu"]');
        await toggle.trigger('click');

        const rowsLink = wrapper
            .get('#admin-mobile-menu')
            .findAll('a')
            .find((link) => link.attributes('href') === rowsIndex.url());

        expect(rowsLink?.classes()).toContain('border-s-4');
    });

    it('marks the dashboard link active on its own URL, not on other sections', () => {
        const wrapper = mountLayout('/admin');

        const links = wrapper.findAll('nav a');
        const dashboardLink = links.find(
            (link) => link.attributes('href') === '/admin',
        );
        const rowsLink = links.find(
            (link) => link.attributes('href') === rowsIndex.url(),
        );

        expect(dashboardLink?.attributes('aria-current')).toBe('page');
        expect(rowsLink?.attributes('aria-current')).toBeUndefined();
    });

    it('does not mark the dashboard link active while a more specific section is open', () => {
        const wrapper = mountLayout(rowsIndex.url());

        const dashboardLink = wrapper
            .findAll('nav a')
            .find((link) => link.attributes('href') === '/admin');

        expect(dashboardLink?.attributes('aria-current')).toBeUndefined();
    });

    it('marks a nested section path as active too', () => {
        const wrapper = mountLayout(rowEdit.url({ row: 'A' }));

        const rowsLink = wrapper
            .findAll('nav a')
            .find((link) => link.attributes('href') === rowsIndex.url());

        expect(rowsLink?.attributes('aria-current')).toBe('page');
    });

    it('does not treat a section as active from a mere string prefix match', () => {
        const wrapper = mountLayout('/admin/rows-archive');

        const rowsLink = wrapper
            .findAll('nav a')
            .find((link) => link.attributes('href') === rowsIndex.url());

        expect(rowsLink?.attributes('aria-current')).toBeUndefined();
    });

    it('marks the cell log section active when navigated to with a filter query string', () => {
        const wrapper = mountLayout(
            cellLogsIndex.url({ query: { action: 'inbound' } }),
        );

        const links = wrapper.findAll('nav a');
        const cellLogLink = links.find(
            (link) => link.attributes('href') === cellLogsIndex.url(),
        );
        const dashboardLink = links.find(
            (link) => link.attributes('href') === '/admin',
        );

        expect(cellLogLink?.attributes('aria-current')).toBe('page');
        expect(dashboardLink?.attributes('aria-current')).toBeUndefined();
    });

    it('marks the products section active when navigated to with a filter query string', () => {
        const wrapper = mountLayout(
            productsIndex.url({ query: { row_id: '1' } }),
        );

        const links = wrapper.findAll('nav a');
        const productsLink = links.find(
            (link) => link.attributes('href') === productsIndex.url(),
        );
        const dashboardLink = links.find(
            (link) => link.attributes('href') === '/admin',
        );

        expect(productsLink?.attributes('aria-current')).toBe('page');
        expect(dashboardLink?.attributes('aria-current')).toBeUndefined();
    });

    it("renders the signed-in user's name with a user icon", () => {
        const wrapper = mountLayout(rowsIndex.url());

        expect(wrapper.text()).toContain('Jane Doe');
        expect(wrapper.findComponent(CircleUser).exists()).toBe(true);
    });

    it('renders a brand icon next to the app name', () => {
        const wrapper = mountLayout(rowsIndex.url());

        expect(wrapper.findComponent(Warehouse).exists()).toBe(true);
    });

    it('links the logout action to the /logout route from the account menu', async () => {
        const wrapper = mountLayout(rowsIndex.url());
        await wrapper.get('button[aria-label="Account"]').trigger('click');

        const logoutLink = wrapper
            .findAll('a,button')
            .find((el) => el.attributes('href') === '/logout');

        expect(logoutLink).toBeTruthy();
    });

    it('hides the account menu panel by default and expands it via the toggle button', async () => {
        const wrapper = mountLayout(rowsIndex.url());
        const toggle = wrapper.get('button[aria-label="Account"]');

        expect(wrapper.find('#account-menu-panel').exists()).toBe(false);
        expect(toggle.attributes('aria-expanded')).toBe('false');

        await toggle.trigger('click');

        expect(wrapper.find('#account-menu-panel').exists()).toBe(true);
        expect(toggle.attributes('aria-expanded')).toBe('true');
    });

    it('renders the language switcher inside the account menu', async () => {
        const wrapper = mountLayout(rowsIndex.url());
        await wrapper.get('button[aria-label="Account"]').trigger('click');

        expect(wrapper.text()).toContain('English');
        expect(wrapper.text()).toContain('Arabic');
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

    it('hides the mobile menu panel by default and expands it via the toggle button', async () => {
        const wrapper = mountLayout(rowsIndex.url());
        const toggle = wrapper.get('button[aria-controls="admin-mobile-menu"]');

        expect(wrapper.find('#admin-mobile-menu').exists()).toBe(false);
        expect(toggle.attributes('aria-expanded')).toBe('false');

        await toggle.trigger('click');

        const panel = wrapper.get('#admin-mobile-menu');
        expect(toggle.attributes('aria-expanded')).toBe('true');
        expect(
            panel
                .findAll('a')
                .map((link) => link.attributes('href'))
                .slice(0, 7),
        ).toEqual([
            '/admin',
            rowsIndex.url(),
            warehouseMapIndex.url(),
            cellLogsIndex.url(),
            cellVerificationRoundsIndex.url(),
            productsIndex.url(),
            usersIndex.url(),
        ]);
        expect(
            panel.findAll('a').map((link) => link.attributes('href')),
        ).toContain(settingsEdit.url());

        await toggle.trigger('click');

        expect(wrapper.find('#admin-mobile-menu').exists()).toBe(false);
        expect(toggle.attributes('aria-expanded')).toBe('false');
    });

    it('keeps every mobile nav link active/icon behavior in sync with the desktop nav', async () => {
        const wrapper = mountLayout(rowsIndex.url());
        const toggle = wrapper.get('button[aria-controls="admin-mobile-menu"]');
        await toggle.trigger('click');

        const panel = wrapper.get('#admin-mobile-menu');
        const rowsLink = panel
            .findAll('a')
            .find((link) => link.attributes('href') === rowsIndex.url());
        const usersLink = panel
            .findAll('a')
            .find((link) => link.attributes('href') === usersIndex.url());

        expect(rowsLink?.attributes('aria-current')).toBe('page');
        expect(rowsLink?.classes()).toContain('border-gray-900');
        expect(usersLink?.attributes('aria-current')).toBeUndefined();
        expect(usersLink?.classes()).not.toContain('border-gray-900');
    });

    it('closes the mobile menu when a nav link is clicked', async () => {
        const wrapper = mountLayout(rowsIndex.url());
        const toggle = wrapper.get('button[aria-controls="admin-mobile-menu"]');
        await toggle.trigger('click');

        const rowsLink = wrapper
            .get('#admin-mobile-menu')
            .findAll('a')
            .find((link) => link.attributes('href') === rowsIndex.url());
        await rowsLink?.trigger('click');

        expect(wrapper.find('#admin-mobile-menu').exists()).toBe(false);
    });

    it('closes the mobile menu when logout is clicked', async () => {
        const wrapper = mountLayout(rowsIndex.url());
        const toggle = wrapper.get('button[aria-controls="admin-mobile-menu"]');
        await toggle.trigger('click');

        const logoutLink = wrapper
            .get('#admin-mobile-menu')
            .findAll('a,button')
            .find((el) => el.attributes('href') === '/logout');
        await logoutLink?.trigger('click');

        expect(wrapper.find('#admin-mobile-menu').exists()).toBe(false);
    });

    it('renders the language switcher and signed-in user inside the mobile menu', async () => {
        const wrapper = mountLayout(rowsIndex.url());
        const toggle = wrapper.get('button[aria-controls="admin-mobile-menu"]');
        await toggle.trigger('click');

        const panel = wrapper.get('#admin-mobile-menu');
        expect(panel.text()).toContain('Jane Doe');
        expect(panel.text()).toContain('English');
        expect(panel.text()).toContain('Arabic');
    });
});
