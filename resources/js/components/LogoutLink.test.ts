import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import LogoutLink from './LogoutLink.vue';

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub } = await import('@/testing/inertiaStubs');

    return { Link: createLinkStub() };
});

describe('LogoutLink', () => {
    it('links to the /logout route', () => {
        const wrapper = mount(LogoutLink);

        expect(wrapper.get('a,button').attributes('href')).toBe('/logout');
    });

    it('renders the logout label', () => {
        const wrapper = mount(LogoutLink);

        expect(wrapper.text()).toContain('Log out');
    });

    it('applies a class attribute to the root element via fallthrough', () => {
        const wrapper = mount(LogoutLink, {
            attrs: { class: 'my-custom-class' },
        });

        expect(wrapper.get('a,button').classes()).toContain('my-custom-class');
    });

    it('forwards a click listener via attribute fallthrough', async () => {
        const onClick = vi.fn();
        const wrapper = mount(LogoutLink, {
            attrs: { onClick },
        });

        await wrapper.get('a,button').trigger('click');

        expect(onClick).toHaveBeenCalled();
    });
});
