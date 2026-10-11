import { Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { create as rowsCreate } from '@/actions/App/Http/Controllers/Admin/RowController';
import { create as usersCreate } from '@/actions/App/Http/Controllers/Admin/UserController';
import AddResourceLink from './AddResourceLink.vue';

describe('AddResourceLink', () => {
    it('renders the label in a link to the given href', () => {
        const wrapper = mount(AddResourceLink, {
            props: { href: rowsCreate.url(), label: 'Add row' },
        });

        const link = wrapper.get('a');
        expect(link.attributes('href')).toBe(rowsCreate.url());
        expect(link.text()).toContain('Add row');
    });

    it('always renders the plus icon ahead of the label', () => {
        const wrapper = mount(AddResourceLink, {
            props: { href: usersCreate.url(), label: 'Add user' },
        });

        expect(wrapper.findComponent(Plus).exists()).toBe(true);
    });
});

it('uses a full page load for a remaining Blade action', () => {
    const action = rowsCreate();
    const wrapper = mount(AddResourceLink, {
        props: { href: rowsCreate(), inertia: false, label: 'Add row' },
        slots: { default: 'Add row' },
    });
    expect(wrapper.findComponent(Link).exists()).toBe(false);
    expect(wrapper.get('a').attributes('href')).toBe(action.url);
    expect(wrapper.text()).toContain('Add row');
});
