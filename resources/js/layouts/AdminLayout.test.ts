import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AdminLayout from './AdminLayout.vue';

const { usePageMock, routerPostMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerPostMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');

    const LinkStub = defineComponent({
        props: ['href', 'as'],
        setup(props, { slots }) {
            return () =>
                h(
                    props.as ?? 'a',
                    {
                        href:
                            typeof props.href === 'string'
                                ? props.href
                                : props.href?.url,
                    },
                    slots.default?.(),
                );
        },
    });

    return {
        usePage: usePageMock,
        router: { post: routerPostMock },
        Link: LinkStub,
    };
});

function mountLayout(url: string) {
    usePageMock.mockReturnValue({
        url,
        props: {
            locale: 'en',
            auth: { user: { name: 'Jane Doe' } },
        },
    });

    return mount(AdminLayout, { slots: { default: '<p>Page content</p>' } });
}

describe('AdminLayout', () => {
    beforeEach(() => {
        usePageMock.mockReset();
        routerPostMock.mockReset();
    });

    it('renders a nav link for every top-level section', () => {
        const wrapper = mountLayout('/admin/rows');

        const links = wrapper.findAll('nav a');
        expect(links.map((link) => link.attributes('href'))).toEqual([
            '/admin/rows',
            '/admin/cell-logs',
            '/admin/users',
        ]);
    });

    it('marks the exact current section as active', () => {
        const wrapper = mountLayout('/admin/rows');

        const links = wrapper.findAll('nav a');
        const rowsLink = links.find(
            (link) => link.attributes('href') === '/admin/rows',
        );
        const usersLink = links.find(
            (link) => link.attributes('href') === '/admin/users',
        );

        expect(rowsLink?.attributes('aria-current')).toBe('page');
        expect(rowsLink?.classes()).toContain('border-gray-900');
        expect(usersLink?.attributes('aria-current')).toBeUndefined();
        expect(usersLink?.classes()).not.toContain('border-gray-900');
    });

    it('marks a nested section path as active too', () => {
        const wrapper = mountLayout('/admin/rows/A/edit');

        const rowsLink = wrapper
            .findAll('nav a')
            .find((link) => link.attributes('href') === '/admin/rows');

        expect(rowsLink?.attributes('aria-current')).toBe('page');
    });

    it('does not treat a section as active from a mere string prefix match', () => {
        const wrapper = mountLayout('/admin/rows-archive');

        const rowsLink = wrapper
            .findAll('nav a')
            .find((link) => link.attributes('href') === '/admin/rows');

        expect(rowsLink?.attributes('aria-current')).toBeUndefined();
    });

    it("renders the signed-in user's name", () => {
        const wrapper = mountLayout('/admin/rows');

        expect(wrapper.text()).toContain('Jane Doe');
    });

    it('links the logout action to the /logout route', () => {
        const wrapper = mountLayout('/admin/rows');

        const logoutLink = wrapper
            .findAll('a,button')
            .find((el) => el.attributes('href') === '/logout');

        expect(logoutLink).toBeTruthy();
    });

    it('renders the language switcher', () => {
        const wrapper = mountLayout('/admin/rows');

        expect(wrapper.findAll('button').length).toBeGreaterThanOrEqual(2);
        expect(wrapper.text()).toContain('English');
        expect(wrapper.text()).toContain('Arabic');
    });

    it('renders the default slot content inside main', () => {
        const wrapper = mountLayout('/admin/rows');

        expect(wrapper.get('main').text()).toContain('Page content');
    });
});
