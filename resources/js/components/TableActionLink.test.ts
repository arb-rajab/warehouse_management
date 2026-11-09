import { Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { show as rowShow } from '@/actions/App/Http/Controllers/Admin/RowController';
import {
    dangerPillVariantClass,
    primaryPillVariantClass,
} from '@/lib/actionLink';
import TableActionLink from './TableActionLink.vue';

describe('TableActionLink', () => {
    it('renders its slot content in a link to the given href', () => {
        const wrapper = mount(TableActionLink, {
            props: { href: rowShow.url({ row: 'A' }) },
            slots: { default: 'View' },
        });

        const link = wrapper.get('a');
        expect(link.attributes('href')).toBe(rowShow.url({ row: 'A' }));
        expect(link.text()).toBe('View');
    });

    it('uses the outlined secondary palette by default and the soft danger palette on request', () => {
        const primary = mount(TableActionLink, {
            props: { href: rowShow.url({ row: 'A' }) },
        });
        expect(primary.get('a').classes()).toEqual(
            expect.arrayContaining(primaryPillVariantClass.split(' ')),
        );
        expect(primary.get('a').classes()).not.toContain('bg-danger-soft');

        const danger = mount(TableActionLink, {
            props: { href: rowShow.url({ row: 'A' }), variant: 'danger' },
        });
        expect(danger.get('a').classes()).toEqual(
            expect.arrayContaining(dangerPillVariantClass.split(' ')),
        );
        expect(danger.get('a').classes()).toContain('bg-danger-soft');
        expect(danger.get('a').classes()).not.toContain('bg-surface');
    });

    it('never paints a destructive action solid red or a row action solid brand red', () => {
        for (const variant of ['primary', 'danger'] as const) {
            const classes = mount(TableActionLink, {
                props: { href: rowShow.url({ row: 'A' }), variant },
            })
                .get('a')
                .classes();

            expect(classes).not.toContain('bg-brand');
            expect(classes).not.toContain('text-on-brand');
        }
    });

    it('keeps the shared sizing regardless of variant', () => {
        const wrapper = mount(TableActionLink, {
            props: { href: rowShow.url({ row: 'A' }), variant: 'danger' },
        });

        const classes = wrapper.get('a').classes();
        expect(classes).toContain('rounded-md');
        expect(classes).toContain('text-xs');
    });

    it('forwards extra attributes to the underlying link', () => {
        const wrapper = mount(TableActionLink, {
            props: { href: rowShow.url({ row: 'A' }), variant: 'danger' },
            attrs: { method: 'delete', as: 'button' },
        });

        expect(wrapper.get('button').text()).toBe('');
    });
});

it('uses a full page load for a remaining Blade action', () => {
    const action = rowShow({ row: 'A' });
    const wrapper = mount(TableActionLink, {
        props: { href: rowShow({ row: 'A' }), inertia: false },
        slots: { default: 'View' },
    });
    expect(wrapper.findComponent(Link).exists()).toBe(false);
    expect(wrapper.get('a').attributes('href')).toBe(action.url);
    expect(wrapper.text()).toContain('View');
});
