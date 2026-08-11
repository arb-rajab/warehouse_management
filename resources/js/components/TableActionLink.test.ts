import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import TableActionLink from './TableActionLink.vue';

describe('TableActionLink', () => {
    it('renders its slot content in a link to the given href', () => {
        const wrapper = mount(TableActionLink, {
            props: { href: '/admin/rows/A' },
            slots: { default: 'View' },
        });

        const link = wrapper.get('a');
        expect(link.attributes('href')).toBe('/admin/rows/A');
        expect(link.text()).toBe('View');
    });

    it('uses the primary palette by default and the danger palette on request', () => {
        const primary = mount(TableActionLink, {
            props: { href: '/admin/rows/A' },
        });
        expect(primary.get('a').classes()).toContain('bg-blue-600');
        expect(primary.get('a').classes()).not.toContain('bg-red-600');

        const danger = mount(TableActionLink, {
            props: { href: '/admin/rows/A', variant: 'danger' },
        });
        expect(danger.get('a').classes()).toContain('bg-red-600');
        expect(danger.get('a').classes()).not.toContain('bg-blue-600');
    });

    it('keeps the shared sizing regardless of variant', () => {
        const wrapper = mount(TableActionLink, {
            props: { href: '/admin/rows/A', variant: 'danger' },
        });

        const classes = wrapper.get('a').classes();
        expect(classes).toContain('rounded-md');
        expect(classes).toContain('text-xs');
    });

    it('forwards extra attributes to the underlying link', () => {
        const wrapper = mount(TableActionLink, {
            props: { href: '/admin/rows/A', variant: 'danger' },
            attrs: { method: 'delete', as: 'button' },
        });

        expect(wrapper.get('button').text()).toBe('');
    });
});
