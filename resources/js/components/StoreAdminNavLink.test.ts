import { Link } from '@inertiajs/vue3';
import { LayoutDashboard } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { index as admin_dashboard } from '@/actions/App/Http/Controllers/Store/Admin/DashboardController';
import { index as staffIndex } from '@/actions/App/Http/Controllers/Store/Admin/StaffController';
import { t } from '@/lib/i18n';
import StoreAdminNavLink from './StoreAdminNavLink.vue';

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub } = await import('@/testing/inertiaStubs');

    return { Link: createLinkStub() };
});

describe('StoreAdminNavLink', () => {
    it('makes an Inertia link to a converted page, passing the route object', () => {
        const wrapper = mount(StoreAdminNavLink, {
            props: {
                item: {
                    labelKey: 'store.nav.allStaff',
                    href: staffIndex(),
                    inertia: true,
                },
                active: false,
            },
        });

        expect(wrapper.getComponent(Link).props('href')).toEqual(staffIndex());
        expect(wrapper.text()).toBe(t('store.nav.allStaff'));
    });

    it('makes a plain link to a Blade page, so it loads in full', () => {
        const wrapper = mount(StoreAdminNavLink, {
            props: {
                item: {
                    labelKey: 'store.nav.dashboard',
                    href: admin_dashboard(),
                    icon: LayoutDashboard,
                },
                active: false,
            },
        });

        expect(wrapper.findComponent(Link).exists()).toBe(false);
        expect(wrapper.get('a').attributes('href')).toBe(admin_dashboard.url());
        expect(wrapper.findComponent(LayoutDashboard).exists()).toBe(true);
    });

    it('marks the active link for assistive technology and styles it apart', () => {
        const props = {
            item: { labelKey: 'store.nav.dashboard', href: admin_dashboard() },
        };
        const active = mount(StoreAdminNavLink, {
            props: { ...props, active: true },
        });
        const inactive = mount(StoreAdminNavLink, {
            props: { ...props, active: false },
        });

        expect(active.get('a').attributes('aria-current')).toBe('page');
        expect(active.get('a').classes()).toContain('font-medium');
        expect(inactive.get('a').attributes('aria-current')).toBeUndefined();
        expect(inactive.get('a').classes()).not.toContain('font-medium');
    });
});
