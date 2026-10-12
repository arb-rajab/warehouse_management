import { Form, Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    destroy,
    edit,
    index as brandsIndex,
    store,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/BrandController';
import Pagination from '@/components/Pagination.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreBrandFormFields from '@/components/StoreBrandFormFields.vue';
import StoreLanguageTabs from '@/components/StoreLanguageTabs.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import {
    paginated,
    storeBrand,
    storeBrandForm,
    storeSharedProps,
    storeUpload,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Edit from './Edit.vue';
import Index from './Index.vue';
const { usePageMock, routerGetMock, formSlotPropsMock, submitMock } =
    vi.hoisted(() => ({
        usePageMock: vi.fn(),
        routerGetMock: vi.fn(),
        formSlotPropsMock: vi.fn(() => ({ errors: {}, processing: false })),
        submitMock: vi.fn(),
    }));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, createUseFormStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(formSlotPropsMock),
        Link: createLinkStub(),
        useHttp: () => ({ processing: false, get: vi.fn(), cancel: vi.fn() }),
        useForm: createUseFormStub(submitMock),
        usePage: usePageMock,
        router: { get: routerGetMock, post: vi.fn() },
    };
});
const languages = [
    { code: 'en', name: 'English' },
    { code: 'ar', name: 'Arabic' },
];
const brand = storeBrand({ logo_url: '/uploads/brand.webp' });
const allPermissions = [
    'view_all_brands',
    'add_brand',
    'edit_brand',
    'delete_brand',
];
function indexPage(permissions = allPermissions, rows = [brand]) {
    usePageMock.mockReturnValue({
        url: brandsIndex.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, {
        props: {
            brands: paginated(rows),
            filters: { per_page: 50, search: 'Dates' },
            defaultLanguage: 'en',
        },
    });
}
function editPage() {
    return mount(Edit, {
        props: {
            brand: storeBrandForm(),
            name: 'تمور',
            lang: 'ar',
            languages,
            initialFiles: { logo: [storeUpload()] },
        },
    });
}
describe('brand pages', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
        usePageMock.mockReturnValue({
            url: brandsIndex.url(),
            props: defaultAuthProps({
                store: storeSharedProps({ permissions: allPermissions }),
            }),
        });
    });
    afterEach(() => {
        i18n.global.locale.value = 'en';
        vi.restoreAllMocks();
    });
    it.each(['en', 'ar'] as const)(
        'renders the columns, logo, actions and inline add form in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = indexPage();
            expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
            expect(wrapper.getComponent(Head).props('title')).toBe(
                t('store.brands.title'),
            );
            expect(wrapper.get('h1').text()).toBe(t('store.brands.title'));
            expect(wrapper.findAll('thead th').map((c) => c.text())).toEqual(
                ['name', 'logo', 'actions'].map((k) => t(`store.brands.${k}`)),
            );
            expect(wrapper.text()).toContain(t('store.brands.search'));
            expect(wrapper.get('#search').attributes('value')).toBe('Dates');
            expect(wrapper.get('tbody').text()).toContain('Dates brand');
            const logo = wrapper.get('tbody img');
            expect(logo.attributes('src')).toBe('/uploads/brand.webp');
            expect(logo.attributes('alt')).toBe(t('store.brands.logo'));
            expect(
                wrapper
                    .findAllComponents(TableActionLink)
                    .map((c) => c.props('href')),
            ).toEqual([
                edit(brand, { query: { lang: 'en' } }),
                destroy(brand, { mergeQuery: {} }),
            ]);
            expect(wrapper.text()).toContain(t('store.brands.edit'));
            expect(wrapper.text()).toContain(t('store.reference.delete'));
            expect(wrapper.get('h2').text()).toBe(t('store.brands.create'));
            expect(wrapper.getComponent(Form).props('action')).toEqual(store());
            expect(
                wrapper.getComponent(StoreBrandFormFields).props(),
            ).toMatchObject({ errors: {}, brand: undefined });
            expect(wrapper.getComponent(SubmitButton).props()).toMatchObject({
                label: t('store.reference.save'),
                processingLabel: t('store.reference.saving'),
                processing: false,
            });
        },
    );
    it('shows a dash for a brand without a logo and the empty message', () => {
        const wrapper = indexPage(allPermissions, [
            storeBrand({ logo_url: null }),
        ]);
        expect(wrapper.find('tbody img').exists()).toBe(false);
        expect(wrapper.get('tbody').text()).toContain('—');
        expect(indexPage(allPermissions, []).text()).toContain(
            t('store.brands.empty'),
        );
    });
    it('clears the inline add fields, including the picked logo, after a successful save', async () => {
        const wrapper = indexPage();
        await wrapper
            .getComponent(StoreUploadField)
            .vm.$emit('update:modelValue', '9');
        await wrapper.get('#meta_description').setValue('Draft description');
        expect(wrapper.getComponent(StoreUploadField).props('modelValue')).toBe(
            '9',
        );
        wrapper.getComponent(Form).vm.$emit('success');
        await wrapper.vm.$nextTick();
        expect(wrapper.getComponent(StoreUploadField).props('modelValue')).toBe(
            '',
        );
        expect(
            (wrapper.get('#meta_description').element as HTMLTextAreaElement)
                .value,
        ).toBe('');
    });
    it('gates every action with the matching permission', () => {
        const read = indexPage(['view_all_brands']);
        expect(read.findComponent(Form).exists()).toBe(false);
        expect(read.findAllComponents(TableActionLink)).toHaveLength(0);
        const add = indexPage(['add_brand']);
        expect(add.getComponent(Form).props('action')).toEqual(store());
        expect(add.findAllComponents(TableActionLink)).toHaveLength(0);
        const editor = indexPage(['edit_brand']);
        expect(editor.findComponent(Form).exists()).toBe(false);
        expect(
            editor
                .findAllComponents(TableActionLink)
                .map((c) => c.props('href')),
        ).toEqual([edit(brand, { query: { lang: 'en' } })]);
        const remove = indexPage(['delete_brand']);
        expect(
            remove
                .findAllComponents(TableActionLink)
                .map((c) => c.props('href')),
        ).toEqual([destroy(brand, { mergeQuery: {} })]);
    });
    it('confirms deletion, preserves filters on pagination and searches', async () => {
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const wrapper = indexPage();
        const deletion = wrapper
            .findAllComponents(TableActionLink)[1]
            .getComponent(Link);
        expect((deletion.props('onBefore') as () => boolean)()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', { label: 'Dates brand' }),
        );
        expect(deletion.attributes('method')).toBe('delete');
        expect(deletion.props('as')).toBe('button');
        expect(wrapper.getComponent(Pagination).props('perPage')).toBe(50);
        wrapper.getComponent(Pagination).vm.$emit('update:perPage', 10);
        expect(routerGetMock).toHaveBeenLastCalledWith(
            brandsIndex().url,
            { search: 'Dates', per_page: 10 },
            { preserveState: true, replace: true },
        );
        await wrapper.get('#search').setValue('Fresh');
        await wrapper
            .get('#search')
            .element.closest('form')
            ?.dispatchEvent(
                new Event('submit', { bubbles: true, cancelable: true }),
            );
        expect(routerGetMock).toHaveBeenLastCalledWith(
            brandsIndex().url,
            { search: 'Fresh', per_page: 50 },
            { preserveState: true, replace: true },
        );
    });
    it.each(['en', 'ar'] as const)(
        'uses the store form shell and language tabs for editing in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = editPage();
            expect(
                wrapper.getComponent(ResourceFormPage).props(),
            ).toMatchObject({
                layout: StoreAdminLayout,
                title: t('store.brands.edit'),
                submitLabel: t('store.reference.save'),
                submittingLabel: t('store.reference.saving'),
                cancelHref: brandsIndex(),
                wide: false,
            });
            expect(wrapper.getComponent(Form).props('action')).toEqual(
                update(storeBrandForm()),
            );
            expect(
                wrapper.getComponent(StoreLanguageTabs).props(),
            ).toMatchObject({ languages, lang: 'ar' });
            expect(
                wrapper.getComponent(StoreLanguageTabs).props('href')('en'),
            ).toEqual(edit(storeBrandForm(), { query: { lang: 'en' } }));
            expect(wrapper.get('input[name=lang]').attributes('value')).toBe(
                'ar',
            );
            expect(
                wrapper.getComponent(StoreBrandFormFields).props(),
            ).toMatchObject({
                brand: storeBrandForm(),
                name: 'تمور',
                initialFiles: { logo: [storeUpload()] },
                errors: {},
            });
        },
    );
    it('forwards backend errors and processing to both forms', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { name: 'Refused' },
            processing: true,
        });

        for (const wrapper of [indexPage(), editPage()]) {
            expect(
                wrapper.getComponent(StoreBrandFormFields).props('errors'),
            ).toEqual({ name: 'Refused' });
            expect(wrapper.getComponent(SubmitButton).props('processing')).toBe(
                true,
            );
        }
    });
});
