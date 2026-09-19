import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { t } from '@/lib/i18n';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import Users from './Users.vue';

const { usePageMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, headStub } = await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
    };
});

function mountPage() {
    usePageMock.mockReturnValue({
        url: '/admin/help/users',
        props: defaultAuthProps(),
    });

    return mount(Users);
}

describe('Help Users', () => {
    beforeEach(() => {
        resetMocks({ usePageMock });
    });

    it('explains creating an admin account', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('help.users.accounts.body'));
    });

    it('explains what the roles mean', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('help.users.roles.body'));
    });

    it('links back to the help landing page', () => {
        const wrapper = mountPage();

        const backLink = wrapper
            .findAll('a')
            .find((a) => a.text() === t('help.backToHelp'));
        expect(backLink?.attributes('href')).toBe('/admin/help');
    });
});
