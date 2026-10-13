import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    store,
    index as languagesIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/LanguageController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreLanguageFormFields from '@/components/StoreLanguageFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Create from './Create.vue';
const { usePageMock, formSlotPropsMock, routerGetMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    formSlotPropsMock: vi.fn(() => ({ errors: {}, processing: false })),
    routerGetMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(formSlotPropsMock),
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { get: routerGetMock, post: vi.fn() },
    };
});
beforeEach(() => {
    vi.clearAllMocks();
    formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    usePageMock.mockReturnValue({
        url: '/temp/admin/languages',
        props: defaultAuthProps({
            store: storeSharedProps({ permissions: ['language_setup'] }),
        }),
    });
});

describe('Language Create', () => {
    it('uses the store form layout, action, labels, cancellation and field errors', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { code: 'Code error' },
            processing: false,
        });
        const wrapper = mount(Create, { props: {} });
        expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
            title: t('store.languages.create'),
            action: store(),
            cancelHref: languagesIndex(),
            layout: StoreAdminLayout,
            submitLabel: t('store.settings.save'),
            submittingLabel: t('store.settings.saving'),
        });
        expect(wrapper.getComponent(Form).props('action')).toEqual(store());
        expect(
            wrapper.getComponent(StoreLanguageFormFields).props('errors'),
        ).toEqual({ code: 'Code error' });
    });
});
