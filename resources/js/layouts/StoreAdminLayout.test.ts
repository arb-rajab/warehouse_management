import { Link } from '@inertiajs/vue3';
import { CircleUser, Globe, Store } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { logout } from '@/actions/App/Http/Controllers/Auth/LoginController';
import { index as storefrontHome } from '@/actions/App/Http/Controllers/HomeController';
import { index as profileIndex } from '@/actions/App/Http/Controllers/Store/Admin/ProfileController';
import { admin_dashboard } from '@/actions/App/Http/Controllers/AdminController';
import {
    edit as staffEdit,
    index as staffIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/StaffController';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import LogoutLink from '@/components/LogoutLink.vue';
import SkipToContentLink from '@/components/SkipToContentLink.vue';
import StoreAdminNavLink from '@/components/StoreAdminNavLink.vue';
import { t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type { StoreSharedProps } from '@/types/store';
import StoreAdminLayout from './StoreAdminLayout.vue';

const { usePageMock, routerPostMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerPostMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, createFormStub } =
        await import('@/testing/inertiaStubs');

    return {
        usePage: usePageMock,
        router: { post: routerPostMock },
        Link: createLinkStub(),
        Form: createFormStub(() => ({ errors: {}, processing: false })),
    };
});

const staffManager = storeSharedProps({
    permissions: ['admin_dashboard', 'view_all_staffs'],
    settings: { Staffs_section: true },
});

function mountLayout(url: string, store: StoreSharedProps = staffManager) {
    usePageMock.mockReturnValue({
        url,
        props: defaultAuthProps({ store }),
    });

    return mount(StoreAdminLayout, {
        slots: { default: '<p>Page content</p>' },
    });
}

function navLink(wrapper: ReturnType<typeof mountLayout>, labelKey: string) {
    return wrapper
        .findAllComponents(StoreAdminNavLink)
        .find((link) => link.props('item').labelKey === labelKey);
}

function groupButton(wrapper: ReturnType<typeof mountLayout>, label: string) {
    return wrapper
        .findAll('nav button')
        .find((button) => button.text() === label);
}

describe('StoreAdminLayout', () => {
    beforeEach(() => {
        resetMocks({ usePageMock, routerPostMock });
    });

    it('renders the brand with its icon, in the bar and the sidebar', () => {
        const wrapper = mountLayout(staffIndex.url());

        expect(wrapper.findAllComponents(Store)).toHaveLength(2);
        expect(wrapper.text()).toContain(t('store.nav.brand'));
        expect(t('store.nav.brand')).toBe('Store Admin');
    });

    it('labels the sidebar navigation', () => {
        const wrapper = mountLayout(staffIndex.url());

        expect(wrapper.get('nav').attributes('aria-label')).toBe(
            t('store.nav.menuLabel'),
        );
    });

    it('shows only the entries the signed-in user may open', () => {
        const wrapper = mountLayout(staffIndex.url());

        expect(
            wrapper
                .findAllComponents(StoreAdminNavLink)
                .map((link) => link.props('item').labelKey),
        ).toEqual([
            'store.nav.dashboard',
            'store.nav.allStaff',
        ]);
        expect(wrapper.text()).not.toContain(t('store.nav.staffRoles'));
        expect(wrapper.text()).not.toContain(t('store.nav.products'));
    });

    it('opens the group holding the current page and marks its link active', () => {
        const wrapper = mountLayout(staffEdit.url({ staff: 4 }));

        expect(
            groupButton(wrapper, t('store.nav.staff'))?.attributes(
                'aria-expanded',
            ),
        ).toBe('true');
        expect(navLink(wrapper, 'store.nav.allStaff')?.props('active')).toBe(
            true,
        );
        expect(navLink(wrapper, 'store.nav.dashboard')?.props('active')).toBe(
            false,
        );
    });

    it('marks only the dashboard active on the dashboard itself', () => {
        const wrapper = mountLayout(admin_dashboard.url());

        expect(navLink(wrapper, 'store.nav.dashboard')?.props('active')).toBe(
            true,
        );
        expect(
            groupButton(wrapper, t('store.nav.staff'))?.attributes(
                'aria-expanded',
            ),
        ).toBe('false');
        expect(navLink(wrapper, 'store.nav.allStaff')).toBeUndefined();
    });

    it('opens and closes a group from its button', async () => {
        const wrapper = mountLayout(admin_dashboard.url());
        const button = groupButton(wrapper, t('store.nav.staff'));

        await button?.trigger('click');
        expect(navLink(wrapper, 'store.nav.allStaff')).toBeDefined();

        await button?.trigger('click');
        expect(navLink(wrapper, 'store.nav.allStaff')).toBeUndefined();
    });

    it('makes Inertia visits to staff and profile while keeping Blade links', () => {
        const wrapper = mountLayout(staffIndex.url());
        expect(
            navLink(wrapper, 'store.nav.allStaff')
                ?.findComponent(Link)
                .props('href'),
        ).toEqual(staffIndex());
        expect(navLink(wrapper, 'store.nav.dashboard')?.get('a').attributes('href')).toBe(admin_dashboard.url());
        expect(navLink(wrapper, 'store.nav.dashboard')?.findComponent(Link).exists()).toBe(false);
        expect(wrapper.find('a[href="/temp"]').exists()).toBe(true);
    });

    it('renders a nested section as a heading over its links', async () => {
        const wrapper = mountLayout(
            staffIndex.url(),
            storeSharedProps({ permissions: ['view_all_trucks'] }),
        );

        expect(wrapper.text()).not.toContain(t('store.nav.allTrucks'));

        await groupButton(wrapper, t('store.nav.trips'))?.trigger('click');

        expect(wrapper.text()).toContain(t('store.nav.trucks'));
        expect(navLink(wrapper, 'store.nav.allTrucks')?.exists()).toBe(true);
        expect(wrapper.text()).not.toContain(t('store.nav.drivers'));
    });

    it('links to the storefront in a new tab', () => {
        const wrapper = mountLayout(staffIndex.url());

        const link = (wrapper.findComponent(Globe).element as Element).closest(
            'a',
        );
        expect(link?.getAttribute('href')).toBe(storefrontHome.url());
        expect(link?.getAttribute('target')).toBe('_blank');
        expect(wrapper.text()).toContain(t('store.nav.browseWebsite'));
    });

    it("links the signed-in user's name to their profile", () => {
        const wrapper = mountLayout(staffIndex.url());

        const link = (
            wrapper.findComponent(CircleUser).element as Element
        ).closest('a');
        expect(link?.getAttribute('href')).toBe(profileIndex.url());
        expect(link?.getAttribute('aria-label')).toBe(t('store.nav.profile'));
        expect(link?.textContent).toContain('Jane Doe');
    });

    it("signs out of the store's own guard, and offers the language switcher", () => {
        const wrapper = mountLayout(staffIndex.url());

        expect(wrapper.getComponent(LogoutLink).props('href')).toEqual(
            logout.post(),
        );
        expect(wrapper.findComponent(LanguageSwitcher).exists()).toBe(true);
    });

    it('folds the sidebar behind the menu button on small screens', async () => {
        const wrapper = mountLayout(staffIndex.url());
        const toggle = wrapper.get(
            'button[aria-controls="store-admin-sidebar"]',
        );

        expect(wrapper.get('#store-admin-sidebar').classes()).toContain(
            'hidden',
        );
        expect(toggle.attributes('aria-expanded')).toBe('false');
        expect(toggle.attributes('aria-label')).toBe(t('nav.openMenu'));

        await toggle.trigger('click');

        expect(wrapper.get('#store-admin-sidebar').classes()).not.toContain(
            'hidden',
        );
        expect(toggle.attributes('aria-expanded')).toBe('true');
        expect(toggle.attributes('aria-label')).toBe(t('nav.closeMenu'));
    });

    it('closes the small-screen sidebar when a link is followed', async () => {
        const wrapper = mountLayout(staffIndex.url());
        await wrapper
            .get('button[aria-controls="store-admin-sidebar"]')
            .trigger('click');

        await navLink(wrapper, 'store.nav.dashboard')?.trigger('click');

        expect(wrapper.get('#store-admin-sidebar').classes()).toContain(
            'hidden',
        );
    });

    it('filters translated menu links and opens escaped notifications without showing cache to ordinary staff', async () => {
        const wrapper = mountLayout(
            staffIndex.url(),
            storeSharedProps({
                permissions: ['admin_dashboard', 'view_all_staffs'],
                settings: { Staffs_section: true },
                notifications: {
                    unread_count: 1,
                    items: [
                        {
                            id: 'n1',
                            kind: 'cart',
                            order_code: null,
                            status: null,
                            user_name: 'Buyer',
                            message: '<script>unsafe()</script>',
                            created_at: '2026-10-10T12:00:00Z',
                        },
                    ],
                },
            }),
        );
        await wrapper.get('#store-menu-search').setValue('Staff');
        expect(wrapper.get('nav').text()).toContain('All staff');
        expect(wrapper.get('nav').text()).not.toContain('Dashboard');
        expect(wrapper.find('form').exists()).toBe(false);
        const trigger = wrapper.get(
            'button[aria-controls="store-notifications"]',
        );
        await trigger.trigger('click');
        expect(wrapper.get('#store-notifications').text()).toContain(
            '<script>unsafe()</script>',
        );
        expect(wrapper.find('script').exists()).toBe(false);
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();
        expect(wrapper.find('#store-notifications').exists()).toBe(false);
        wrapper.unmount();
    });

    it('renders vendor cache POST action and sidebar badges from shared props', async () => {
        const wrapper = mountLayout(
            admin_dashboard.url(),
            storeSharedProps({
                isSuperAdmin: true,
                isRaizerAdmin: true,
                counts: { sellers: 3, tickets: 4, conversations: 5 },
            }),
        );
        expect(wrapper.get('form').attributes('data-action-url')).toBe(
            '/temp/admin/clear-cache',
        );
        expect(wrapper.get('form').text()).toContain('Clear Cache');
        await wrapper.get('#store-menu-search').setValue('Sellers');
        expect(navLink(wrapper, 'store.nav.allSellers')?.props('count')).toBe(
            3,
        );
        await wrapper.get('#store-menu-search').setValue('Support');
        expect(navLink(wrapper, 'store.nav.tickets')?.props('count')).toBe(4);
        wrapper.unmount();
    });

    it('renders the skip link and the page inside main', () => {
        const wrapper = mountLayout(staffIndex.url());

        expect(wrapper.findComponent(SkipToContentLink).exists()).toBe(true);
        expect(wrapper.get('main#main').text()).toContain('Page content');
    });
});
