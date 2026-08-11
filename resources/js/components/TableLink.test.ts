import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import TableLink from './TableLink.vue';

describe('TableLink', () => {
    it('renders a link to the given href with its slot content', () => {
        const wrapper = mount(TableLink, {
            props: { href: '/admin/rows/A' },
            slots: { default: 'Row A' },
        });

        const link = wrapper.get('a');
        expect(link.attributes('href')).toBe('/admin/rows/A');
        expect(link.text()).toContain('Row A');
    });

    it('accepts a wayfinder url/method pair as the href', () => {
        const wrapper = mount(TableLink, {
            props: { href: { url: '/admin/users/5/edit', method: 'get' } },
            slots: { default: 'Jane Doe' },
        });

        expect(wrapper.get('a').attributes('href')).toBe('/admin/users/5/edit');
    });
});
