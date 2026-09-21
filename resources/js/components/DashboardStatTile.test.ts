import { Link } from '@inertiajs/vue3';
import { CircleDashed } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import DashboardStatTile from './DashboardStatTile.vue';

describe('DashboardStatTile', () => {
    it('renders the label and value', () => {
        const wrapper = mount(DashboardStatTile, {
            props: { label: 'Empty', value: 12, href: '/admin/cells' },
        });

        expect(wrapper.text()).toContain('12');
        expect(wrapper.text()).toContain('Empty');
    });

    it('links to the given href with the given query as data', () => {
        const wrapper = mount(DashboardStatTile, {
            props: {
                label: 'Empty',
                value: 12,
                href: '/admin/cells',
                query: { state: 'empty' },
            },
        });

        const link = wrapper.getComponent(Link);
        expect(link.props('href')).toBe('/admin/cells');
        expect(link.props('data')).toEqual({ state: 'empty' });
        expect(link.props('method')).toBe('get');
    });

    it('defaults to the default tone', () => {
        const wrapper = mount(DashboardStatTile, {
            props: { label: 'Empty', value: 12, href: '/admin/cells' },
        });

        expect(wrapper.find('.text-gray-900').exists()).toBe(true);
    });

    it('applies the warning tone', () => {
        const wrapper = mount(DashboardStatTile, {
            props: {
                label: 'Expiring soon',
                value: 3,
                href: '/admin/cells',
                tone: 'warning',
            },
        });

        expect(wrapper.find('.text-amber-600').exists()).toBe(true);
    });

    it('applies the danger tone', () => {
        const wrapper = mount(DashboardStatTile, {
            props: {
                label: 'Expired',
                value: 2,
                href: '/admin/cells',
                tone: 'danger',
            },
        });

        expect(wrapper.find('.text-red-600').exists()).toBe(true);
    });

    it('renders the given icon next to the value', () => {
        const wrapper = mount(DashboardStatTile, {
            props: {
                label: 'Empty',
                value: 12,
                href: '/admin/cells',
                icon: CircleDashed,
            },
        });

        expect(wrapper.findComponent(CircleDashed).exists()).toBe(true);
    });

    it('renders no icon when none is given', () => {
        const wrapper = mount(DashboardStatTile, {
            props: { label: 'Empty', value: 12, href: '/admin/cells' },
        });

        expect(wrapper.find('svg').exists()).toBe(false);
    });

    it('applies the hover/border classes to the root element, not just the link', () => {
        const wrapper = mount(DashboardStatTile, {
            props: { label: 'Empty', value: 12, href: '/admin/cells' },
        });

        expect(wrapper.classes()).toContain('hover:border-gray-300');
        expect(wrapper.classes()).toContain('hover:bg-gray-50');
    });

    it('renders footer slot content outside the link', () => {
        const wrapper = mount(DashboardStatTile, {
            props: { label: 'Empty', value: 12, href: '/admin/cells' },
            slots: { footer: '<button>Custom</button>' },
        });

        const link = wrapper.getComponent(Link);
        expect(link.text()).not.toContain('Custom');
        expect(wrapper.text()).toContain('Custom');
    });
});
