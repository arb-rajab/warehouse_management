import { Form, Head } from '@inertiajs/vue3';
import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    edit,
    index as digitalProductsIndex,
    store,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/DigitalProductController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreDigitalProductFormFields from '@/components/StoreDigitalProductFormFields.vue';
import StoreLanguageTabs from '@/components/StoreLanguageTabs.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import {
    storeDigitalProductForm,
    storeDigitalProductTax,
    storeSharedProps,
    storeUpload,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Create from './Create.vue';
import Edit from './Edit.vue';

const { usePageMock, formSlotPropsMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    formSlotPropsMock: vi.fn(() => ({ errors: {}, processing: false })),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(formSlotPropsMock),
        Link: createLinkStub(),
        useHttp: () => ({ processing: false, get: vi.fn(), cancel: vi.fn() }),
        usePage: usePageMock,
        router: { get: vi.fn(), post: vi.fn() },
    };
});

const languages = [
    { code: 'en', name: 'English' },
    { code: 'ar', name: 'Arabic' },
];
const categories = [
    { id: 12, name: 'E-books' },
    { id: 13, name: '-- Recipes' },
];
const taxes = [storeDigitalProductTax()];
const product = storeDigitalProductForm();
const initialFiles = {
    photos: [storeUpload({ id: 5 }), storeUpload({ id: 6 })],
    thumbnail_img: [storeUpload({ id: 7 })],
    meta_img: [],
    file: [storeUpload({ id: 9, name: 'Guide.pdf', type: 'document' })],
};
const wrappers: VueWrapper[] = [];

function mounted(wrapper: VueWrapper): VueWrapper {
    wrappers.push(wrapper);

    return wrapper;
}

function createPage(): VueWrapper {
    return mounted(mount(Create, { props: { categories, taxes } }));
}

function editPage(): VueWrapper {
    return mounted(
        mount(Edit, {
            props: {
                product,
                name: 'كتاب الوصفات',
                description: '<p>وصفات العائلة</p>',
                lang: 'ar',
                languages,
                categories,
                taxes,
                initialFiles,
            },
        }),
    );
}

beforeEach(() => {
    vi.clearAllMocks();
    formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    usePageMock.mockReturnValue({
        url: digitalProductsIndex.url(),
        props: defaultAuthProps({
            store: storeSharedProps({ permissions: ['view_uploads'] }),
        }),
    });
});
afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    i18n.global.locale.value = 'en';
});

describe('digital product form pages', () => {
    it.each(['en', 'ar'] as const)(
        'renders the create form in the store shell in %s',
        async (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = createPage();
            await flushPromises();

            expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
            expect(wrapper.getComponent(Head).props('title')).toBe(
                t('store.digitalProducts.form.createTitle'),
            );
            expect(wrapper.get('h1').text()).toBe(
                t('store.digitalProducts.form.createTitle'),
            );
            expect(
                wrapper.getComponent(ResourceFormPage).props(),
            ).toMatchObject({
                layout: StoreAdminLayout,
                title: t('store.digitalProducts.form.createTitle'),
                submitLabel: t('store.digitalProducts.form.save'),
                submittingLabel: t('store.digitalProducts.form.saving'),
                cancelHref: digitalProductsIndex(),
                wide: true,
            });
            expect(wrapper.getComponent(Form).props('action')).toEqual(store());
            expect(
                wrapper.getComponent(StoreDigitalProductFormFields).props(),
            ).toEqual({
                product: undefined,
                name: undefined,
                description: undefined,
                categories,
                taxes,
                initialFiles: undefined,
                errors: {},
            });
            expect(wrapper.findComponent(StoreLanguageTabs).exists()).toBe(
                false,
            );
            expect(wrapper.find('input[name=lang]').exists()).toBe(false);
            expect(wrapper.getComponent(SubmitButton).text()).toBe(
                t('store.digitalProducts.form.save'),
            );
        },
    );

    it.each(['en', 'ar'] as const)(
        'renders the edit form with its language tabs in %s',
        async (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = editPage();
            await flushPromises();

            expect(wrapper.getComponent(Head).props('title')).toBe(
                t('store.digitalProducts.form.editTitle'),
            );
            expect(
                wrapper.getComponent(ResourceFormPage).props(),
            ).toMatchObject({
                layout: StoreAdminLayout,
                title: t('store.digitalProducts.form.editTitle'),
                submitLabel: t('store.digitalProducts.form.update'),
                submittingLabel: t('store.digitalProducts.form.updating'),
                cancelHref: digitalProductsIndex(),
                wide: true,
            });
            expect(wrapper.getComponent(Form).props('action')).toEqual(
                update(product),
            );
            expect(update(product)).toEqual({
                url: '/temp/admin/digitalproducts/31',
                method: 'put',
            });
            const tabs = wrapper.getComponent(StoreLanguageTabs);
            expect(tabs.props()).toMatchObject({ languages, lang: 'ar' });
            expect(tabs.props('href')('en')).toEqual(
                edit(product, { query: { lang: 'en' } }),
            );
            expect(wrapper.get('input[name=lang]').attributes('value')).toBe(
                'ar',
            );
            expect(
                wrapper.getComponent(StoreDigitalProductFormFields).props(),
            ).toEqual({
                product,
                name: 'كتاب الوصفات',
                description: '<p>وصفات العائلة</p>',
                categories,
                taxes,
                initialFiles,
                errors: {},
            });
            expect(
                wrapper.getComponent(StoreRichTextField).props('modelValue'),
            ).toBe('<p>وصفات العائلة</p>');
            expect(
                wrapper
                    .findAllComponents(StoreUploadField)
                    .map((upload) => upload.props('initialFiles')),
            ).toEqual([
                initialFiles.file,
                initialFiles.photos,
                initialFiles.thumbnail_img,
                initialFiles.meta_img,
            ]);
            expect(wrapper.getComponent(SubmitButton).text()).toBe(
                t('store.digitalProducts.form.update'),
            );
        },
    );

    it('forwards backend errors and processing to both forms', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { name: 'Refused' },
            processing: true,
        });

        for (const wrapper of [createPage(), editPage()]) {
            expect(
                wrapper
                    .getComponent(StoreDigitalProductFormFields)
                    .props('errors'),
            ).toEqual({ name: 'Refused' });
            expect(wrapper.getComponent(SubmitButton).props('processing')).toBe(
                true,
            );
        }
    });
});
