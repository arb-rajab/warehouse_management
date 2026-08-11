import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import LanguageSwitcher from './LanguageSwitcher.vue';

const { usePageMock, routerPostMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerPostMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: usePageMock,
    router: { post: routerPostMock },
}));

function mountWithLocale(locale: string) {
    usePageMock.mockReturnValue({ props: { locale } });

    return mount(LanguageSwitcher);
}

describe('LanguageSwitcher', () => {
    beforeEach(() => {
        usePageMock.mockReset();
        routerPostMock.mockReset();
    });

    it('renders a button for every supported locale', () => {
        const wrapper = mountWithLocale('en');

        const buttons = wrapper.findAll('button');
        expect(buttons).toHaveLength(2);
        expect(buttons[0].text()).toBe('English');
        expect(buttons[1].text()).toBe('Arabic');
    });

    it('highlights the active locale and leaves the other muted', () => {
        const wrapper = mountWithLocale('ar');

        const [enButton, arButton] = wrapper.findAll('button');
        expect(enButton.classes()).toContain('text-gray-500');
        expect(arButton.classes()).toContain('font-semibold');
    });

    it('posts to the locale route for the clicked locale', async () => {
        const wrapper = mountWithLocale('en');

        await wrapper.findAll('button')[1].trigger('click');

        expect(routerPostMock).toHaveBeenCalledTimes(1);
        expect(routerPostMock).toHaveBeenCalledWith(
            '/locale/ar',
            {},
            { onSuccess: expect.any(Function) },
        );
    });

    it('reloads the page once the locale switch request succeeds', async () => {
        const reload = vi.fn();
        vi.stubGlobal('location', { ...window.location, reload });

        const wrapper = mountWithLocale('en');
        await wrapper.findAll('button')[0].trigger('click');

        const { onSuccess } = routerPostMock.mock.calls[0][2];
        onSuccess();

        expect(reload).toHaveBeenCalledTimes(1);

        vi.unstubAllGlobals();
    });

    it('renders the accessible label for the switcher region', () => {
        const wrapper = mountWithLocale('en');

        expect(wrapper.get('div').attributes('aria-label')).toBe('Language');
    });
});
