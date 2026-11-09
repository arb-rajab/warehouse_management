import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import SidebarUser from './SidebarUser.vue';

describe('SidebarUser', () => {
    it('shows the initials avatar in the saffron accent, the name and the detail line', () => {
        const wrapper = mount(SidebarUser, {
            props: { name: 'Sami  Haddad', detail: 'sami@example.com' },
        });
        const avatar = wrapper.get('[data-testid="sidebar-avatar"]');

        expect(avatar.text()).toBe('SH');
        expect(avatar.attributes('aria-hidden')).toBe('true');
        expect(avatar.classes()).toEqual(
            expect.arrayContaining(['bg-accent-soft', 'text-accent-strong']),
        );
        expect(wrapper.text()).toContain('Sami  Haddad');
        expect(wrapper.text()).toContain('sami@example.com');
    });

    it('uses at most two initials and copes with a missing name', () => {
        expect(
            mount(SidebarUser, { props: { name: 'ana maria de souza' } })
                .get('[data-testid="sidebar-avatar"]')
                .text(),
        ).toBe('AM');
        expect(
            mount(SidebarUser, { props: {} })
                .get('[data-testid="sidebar-avatar"]')
                .text(),
        ).toBe('');
    });

    it('renders its actions slot beside the user', () => {
        const wrapper = mount(SidebarUser, {
            props: { name: 'Jane' },
            slots: { default: '<button type="button">Log out</button>' },
        });

        expect(wrapper.get('button').text()).toBe('Log out');
    });

    it('keeps only the avatar and the actions visible on the folded rail', () => {
        const wrapper = mount(SidebarUser, {
            props: { name: 'Jane Doe', detail: 'j@x.test', collapsed: true },
        });

        expect(
            wrapper.get('[data-testid="sidebar-avatar"]').attributes('title'),
        ).toBe('Jane Doe');
        expect(wrapper.get('p').element.parentElement?.className).toContain(
            'lg:sr-only',
        );
    });
});
