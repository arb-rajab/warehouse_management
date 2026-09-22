import { Head } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { t } from '@/lib/i18n';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import Settings from './Settings.vue';

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
        url: '/admin/help/settings',
        props: defaultAuthProps(),
    });

    return mount(Settings);
}

describe('Help Settings', () => {
    beforeEach(() => {
        resetMocks({ usePageMock });
    });

    it('renders the page title in the Head and the PageHeader', () => {
        const wrapper = mountPage();

        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('help.settings.title'),
        );
        expect(wrapper.get('h1').text()).toBe(t('help.settings.title'));
    });

    it('explains the QR code size setting', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('help.settings.qrSize.heading'));
        expect(wrapper.text()).toContain(t('help.settings.qrSize.body'));
    });

    it('renders a preview of the QR code width/height form fields', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('settings.fields.qrCodeWidth'));
        expect(wrapper.text()).toContain(t('settings.fields.qrCodeHeight'));
        expect(wrapper.find('#help-settings-qr_code_width').exists()).toBe(
            true,
        );
    });

    it('links to the settings page', () => {
        const wrapper = mountPage();

        const settingsLink = wrapper
            .findAll('a')
            .find((a) => a.text() === t('settings.edit.title'));
        expect(settingsLink?.attributes('href')).toBe('/admin/settings');
    });

    it('links back to the help landing page', () => {
        const wrapper = mountPage();

        const backLink = wrapper
            .findAll('a')
            .find((a) => a.text() === t('help.backToHelp'));
        expect(backLink?.attributes('href')).toBe('/admin/help');
    });
});
