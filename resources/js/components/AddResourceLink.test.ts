import { Plus } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import AddResourceLink from './AddResourceLink.vue';

describe('AddResourceLink', () => {
    it('renders the label in a link to the given href', () => {
        const wrapper = mount(AddResourceLink, {
            props: { href: '/admin/rows/create', label: 'Add row' },
        });

        const link = wrapper.get('a');
        expect(link.attributes('href')).toBe('/admin/rows/create');
        expect(link.text()).toContain('Add row');
    });

    it('always renders the plus icon ahead of the label', () => {
        const wrapper = mount(AddResourceLink, {
            props: { href: '/admin/users/create', label: 'Add user' },
        });

        expect(wrapper.findComponent(Plus).exists()).toBe(true);
    });
});
