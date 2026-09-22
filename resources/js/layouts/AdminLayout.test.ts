import { CircleUser, Warehouse } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
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

function mountLayout(url: string) {
    usePageMock.mockReturnValue({
        url,
        props: {
            locale: 'en',
            auth: { user: { name: 'Jane Doe' } },
        },
    });

    return mount(AdminLayout, { slots: { default: '<p>Page content</p>' } });
}

describe('AdminLayout', () => {
    beforeEach(() => {
        usePageMock.mockReset();
        routerPostMock.mockReset();
    });

    it('renders a nav link for every top-level section', () => {
        const wrapper = mountLayout('/admin/rows');

        const links = wrapper.findAll('nav a');
        expect(links.map((link) => link.attributes('href'))).toEqual([
            '/admin',
            '/admin/rows',
            '/admin/cells',
            '/admin/cell-logs',
            '/admin/cell-verification-rounds',
            '/admin/products',
            '/admin/users',
            '/admin/settings',
        ]);
    });

    it('marks the exact current section as active', () => {
        const wrapper = mountLayout('/admin/rows');

        const links = wrapper.findAll('nav a');
        const rowsLink = links.find(
            (link) => link.attributes('href') === '/admin/rows',
        );
        const usersLink = links.find(
            (link) => link.attributes('href') === '/admin/users',
        );

        expect(rowsLink?.attributes('aria-current')).toBe('page');
        expect(rowsLink?.classes()).toContain('border-gray-900');
        expect(usersLink?.attributes('aria-current')).toBeUndefined();
        expect(usersLink?.classes()).not.toContain('border-gray-900');
    });

    it('marks the dashboard link active on its own URL, not on other sections', () => {
        const wrapper = mountLayout('/admin');

        const links = wrapper.findAll('nav a');
        const dashboardLink = links.find(
            (link) => link.attributes('href') === '/admin',
        );
        const rowsLink = links.find(
            (link) => link.attributes('href') === '/admin/rows',
        );

        expect(dashboardLink?.attributes('aria-current')).toBe('page');
        expect(rowsLink?.attributes('aria-current')).toBeUndefined();
    });

    it('does not mark the dashboard link active while a more specific section is open', () => {
        const wrapper = mountLayout('/admin/rows');

        const dashboardLink = wrapper
            .findAll('nav a')
            .find((link) => link.attributes('href') === '/admin');

        expect(dashboardLink?.attributes('aria-current')).toBeUndefined();
    });

    it('marks a nested section path as active too', () => {
        const wrapper = mountLayout('/admin/rows/A/edit');

        const rowsLink = wrapper
            .findAll('nav a')
            .find((link) => link.attributes('href') === '/admin/rows');

        expect(rowsLink?.attributes('aria-current')).toBe('page');
    });

    it('does not treat a section as active from a mere string prefix match', () => {
        const wrapper = mountLayout('/admin/rows-archive');

        const rowsLink = wrapper
            .findAll('nav a')
            .find((link) => link.attributes('href') === '/admin/rows');

        expect(rowsLink?.attributes('aria-current')).toBeUndefined();
    });

    it('marks the map section active when navigated to with a filter query string', () => {
        const wrapper = mountLayout('/admin/cells?state=empty');

        const links = wrapper.findAll('nav a');
        const mapLink = links.find(
            (link) => link.attributes('href') === '/admin/cells',
        );
        const dashboardLink = links.find(
            (link) => link.attributes('href') === '/admin',
        );

        expect(mapLink?.attributes('aria-current')).toBe('page');
        expect(dashboardLink?.attributes('aria-current')).toBeUndefined();
    });

    it('marks the cell log section active when navigated to with a filter query string', () => {
        const wrapper = mountLayout('/admin/cell-logs?action=inbound');

        const links = wrapper.findAll('nav a');
        const cellLogLink = links.find(
            (link) => link.attributes('href') === '/admin/cell-logs',
        );
        const dashboardLink = links.find(
            (link) => link.attributes('href') === '/admin',
        );

        expect(cellLogLink?.attributes('aria-current')).toBe('page');
        expect(dashboardLink?.attributes('aria-current')).toBeUndefined();
    });

    it('marks the products section active when navigated to with a filter query string', () => {
        const wrapper = mountLayout('/admin/products?row_id=1');

        const links = wrapper.findAll('nav a');
        const productsLink = links.find(
            (link) => link.attributes('href') === '/admin/products',
        );
        const dashboardLink = links.find(
            (link) => link.attributes('href') === '/admin',
        );

        expect(productsLink?.attributes('aria-current')).toBe('page');
        expect(dashboardLink?.attributes('aria-current')).toBeUndefined();
    });

    it('links the map nav item straight to the warehouse map with no filters applied', () => {
        const wrapper = mountLayout('/admin/rows');

        const mapLink = wrapper
            .findAll('nav a')
            .find((link) => link.attributes('href') === '/admin/cells');

        expect(mapLink?.attributes('href')).toBe('/admin/cells');
    });

    it("renders the signed-in user's name with a user icon", () => {
        const wrapper = mountLayout('/admin/rows');

        expect(wrapper.text()).toContain('Jane Doe');
        expect(wrapper.findComponent(CircleUser).exists()).toBe(true);
    });

    it('renders a brand icon next to the app name', () => {
        const wrapper = mountLayout('/admin/rows');

        expect(wrapper.findComponent(Warehouse).exists()).toBe(true);
    });

    it('links the logout action to the /logout route from the account menu', async () => {
        const wrapper = mountLayout('/admin/rows');
        await wrapper.get('button[aria-label="Account"]').trigger('click');

        const logoutLink = wrapper
            .findAll('a,button')
            .find((el) => el.attributes('href') === '/logout');

        expect(logoutLink).toBeTruthy();
    });

    it('hides the account menu panel by default and expands it via the toggle button', async () => {
        const wrapper = mountLayout('/admin/rows');
        const toggle = wrapper.get('button[aria-label="Account"]');

        expect(wrapper.find('#account-menu-panel').exists()).toBe(false);
        expect(toggle.attributes('aria-expanded')).toBe('false');

        await toggle.trigger('click');

        expect(wrapper.find('#account-menu-panel').exists()).toBe(true);
        expect(toggle.attributes('aria-expanded')).toBe('true');
    });

    it('renders the language switcher inside the account menu', async () => {
        const wrapper = mountLayout('/admin/rows');
        await wrapper.get('button[aria-label="Account"]').trigger('click');

        expect(wrapper.text()).toContain('English');
        expect(wrapper.text()).toContain('Arabic');
    });

    it('renders the default slot content inside main', () => {
        const wrapper = mountLayout('/admin/rows');

        expect(wrapper.get('main').text()).toContain('Page content');
    });

    it('hides the mobile menu panel by default and expands it via the toggle button', async () => {
        const wrapper = mountLayout('/admin/rows');
        const toggle = wrapper.get('button[aria-controls="admin-mobile-menu"]');

        expect(wrapper.find('#admin-mobile-menu').exists()).toBe(false);
        expect(toggle.attributes('aria-expanded')).toBe('false');

        await toggle.trigger('click');

        const panel = wrapper.get('#admin-mobile-menu');
        expect(toggle.attributes('aria-expanded')).toBe('true');
        expect(
            panel.findAll('a').map((link) => link.attributes('href')),
        ).toEqual([
            '/admin',
            '/admin/rows',
            '/admin/cells',
            '/admin/cell-logs',
            '/admin/cell-verification-rounds',
            '/admin/products',
            '/admin/users',
            '/admin/settings',
        ]);

        await toggle.trigger('click');

        expect(wrapper.find('#admin-mobile-menu').exists()).toBe(false);
        expect(toggle.attributes('aria-expanded')).toBe('false');
    });

    it('keeps every mobile nav link active/icon behavior in sync with the desktop nav', async () => {
        const wrapper = mountLayout('/admin/rows');
        const toggle = wrapper.get('button[aria-controls="admin-mobile-menu"]');
        await toggle.trigger('click');

        const panel = wrapper.get('#admin-mobile-menu');
        const rowsLink = panel
            .findAll('a')
            .find((link) => link.attributes('href') === '/admin/rows');
        const usersLink = panel
            .findAll('a')
            .find((link) => link.attributes('href') === '/admin/users');

        expect(rowsLink?.attributes('aria-current')).toBe('page');
        expect(rowsLink?.classes()).toContain('border-gray-900');
        expect(usersLink?.attributes('aria-current')).toBeUndefined();
        expect(usersLink?.classes()).not.toContain('border-gray-900');
    });

    it('closes the mobile menu when a nav link is clicked', async () => {
        const wrapper = mountLayout('/admin/rows');
        const toggle = wrapper.get('button[aria-controls="admin-mobile-menu"]');
        await toggle.trigger('click');

        const rowsLink = wrapper
            .get('#admin-mobile-menu')
            .findAll('a')
            .find((link) => link.attributes('href') === '/admin/rows');
        await rowsLink?.trigger('click');

        expect(wrapper.find('#admin-mobile-menu').exists()).toBe(false);
    });

    it('closes the mobile menu when logout is clicked', async () => {
        const wrapper = mountLayout('/admin/rows');
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
        const wrapper = mountLayout('/admin/rows');
        const toggle = wrapper.get('button[aria-controls="admin-mobile-menu"]');
        await toggle.trigger('click');

        const panel = wrapper.get('#admin-mobile-menu');
        expect(panel.text()).toContain('Jane Doe');
        expect(panel.text()).toContain('English');
        expect(panel.text()).toContain('Arabic');
    });
});
