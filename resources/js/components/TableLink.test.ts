import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { show as rowShow } from '@/actions/App/Http/Controllers/Admin/RowController';
import { edit as userEdit } from '@/actions/App/Http/Controllers/Admin/UserController';
import TableLink from './TableLink.vue';

describe('TableLink', () => {
    it('renders a link to the given href with its slot content', () => {
        const wrapper = mount(TableLink, {
            props: { href: rowShow.url({ row: 'A' }) },
            slots: { default: 'Row A' },
        });

        const link = wrapper.get('a');
        expect(link.attributes('href')).toBe(rowShow.url({ row: 'A' }));
        expect(link.text()).toContain('Row A');
    });

    it('accepts a wayfinder url/method pair as the href', () => {
        const wrapper = mount(TableLink, {
            props: { href: { url: userEdit.url({ user: 5 }), method: 'get' } },
            slots: { default: 'Jane Doe' },
        });

        expect(wrapper.get('a').attributes('href')).toBe(
            userEdit.url({ user: 5 }),
        );
    });
});
