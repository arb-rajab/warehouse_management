import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AccountMenu from './AccountMenu.vue';

const { usePageMock, routerPostMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerPostMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub } = await import('@/testing/inertiaStubs');

    return {
        usePage: usePageMock,
        router: { post: routerPostMock },
        Link: createLinkStub(),
    };
});

function mountMenu(userName = 'Jane Doe') {
    usePageMock.mockReturnValue({ props: { locale: 'en' } });

    return mount(AccountMenu, { props: { userName } });
}

describe('AccountMenu', () => {
    beforeEach(() => {
        usePageMock.mockReset();
        routerPostMock.mockReset();
    });

    it('renders the signed-in user name on the trigger button', () => {
        const wrapper = mountMenu('Jane Doe');

        expect(wrapper.get('button[aria-label="Account"]').text()).toContain(
            'Jane Doe',
        );
    });

    it('hides the menu panel by default and expands it via the toggle button', async () => {
        const wrapper = mountMenu();
        const toggle = wrapper.get('button[aria-label="Account"]');

        expect(wrapper.find('#account-menu-panel').exists()).toBe(false);
        expect(toggle.attributes('aria-expanded')).toBe('false');

        await toggle.trigger('click');

        expect(wrapper.find('#account-menu-panel').exists()).toBe(true);
        expect(toggle.attributes('aria-expanded')).toBe('true');

        await toggle.trigger('click');

        expect(wrapper.find('#account-menu-panel').exists()).toBe(false);
        expect(toggle.attributes('aria-expanded')).toBe('false');
    });

    it('renders the language switcher inside the panel', async () => {
        const wrapper = mountMenu();
        await wrapper.get('button[aria-label="Account"]').trigger('click');

        expect(wrapper.text()).toContain('English');
        expect(wrapper.text()).toContain('Arabic');
    });

    it('links to the settings page inside the panel', async () => {
        const wrapper = mountMenu();
        await wrapper.get('button[aria-label="Account"]').trigger('click');

        const settingsLink = wrapper
            .findAll('a')
            .find((el) => el.attributes('href') === '/admin/settings');

        expect(settingsLink).toBeTruthy();
    });

    it('links the logout action to the /logout route and closes the menu on click', async () => {
        const wrapper = mountMenu();
        await wrapper.get('button[aria-label="Account"]').trigger('click');

        const logoutLink = wrapper
            .findAll('a,button')
            .find((el) => el.attributes('href') === '/logout');
        expect(logoutLink).toBeTruthy();

        await logoutLink?.trigger('click');

        expect(wrapper.find('#account-menu-panel').exists()).toBe(false);
    });

    it('closes the menu when clicking outside', async () => {
        usePageMock.mockReturnValue({ props: { locale: 'en' } });
        const wrapper = mount(
            {
                components: { AccountMenu },
                template:
                    '<div><AccountMenu user-name="Jane Doe" /><button id="outside">outside</button></div>',
            },
            { attachTo: document.body },
        );
        await wrapper.get('button[aria-label="Account"]').trigger('click');
        expect(wrapper.find('#account-menu-panel').exists()).toBe(true);

        document
            .getElementById('outside')
            ?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await wrapper.vm.$nextTick();

        expect(wrapper.find('#account-menu-panel').exists()).toBe(false);
        wrapper.unmount();
    });
});
