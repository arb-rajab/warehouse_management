import { Form, Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    create,
    destroy,
    edit,
    index as categoriesIndex,
    store,
    update,
    updateFeatured,
} from '@/actions/App/Http/Controllers/Store/Admin/CategoryController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import Pagination from '@/components/Pagination.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreCategoryFormFields from '@/components/StoreCategoryFormFields.vue';
import StoreLanguageTabs from '@/components/StoreLanguageTabs.vue';
import StoreStatusButton from '@/components/StoreStatusButton.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import TableLink from '@/components/TableLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import {
    paginated,
    storeCategory,
    storeCategoryForm,
    storeSharedProps,
    storeUpload,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Create from './Create.vue';
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
const options = {
    parents: [{ id: 3, name: 'Parent' }],
    attributes: [{ id: 2, name: 'Color' }],
    commissionEnabled: true,
};
const languages = [
    { code: 'en', name: 'English' },
    { code: 'ar', name: 'Arabic' },
];
const category = storeCategory({
    parent: options.parents[0],
    banner_url: '/uploads/banner.webp',
    icon_url: '/uploads/icon.webp',
    cover_image_url: '/uploads/cover.webp',
});
const allPermissions = [
    'view_product_categories',
    'add_product_category',
    'edit_product_category',
    'delete_product_category',
];
function indexPage(permissions = allPermissions, empty = false) {
    usePageMock.mockReturnValue({
        url: categoriesIndex.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, {
        props: {
            categories: paginated(empty ? [] : [category]),
            filters: { per_page: 50, search: 'Food' },
            defaultLanguage: 'en',
        },
    });
}
function editPage() {
    return mount(Edit, {
        props: {
            ...options,
            category: storeCategoryForm(),
            name: 'طعام',
            lang: 'ar',
            languages,
            initialFiles: {
                banner: [storeUpload()],
                icon: [],
                cover_image: [],
            },
        },
    });
}
describe('category pages', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
        usePageMock.mockReturnValue({
            url: categoriesIndex.url(),
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
        'renders all columns, images, actions and featured form in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = indexPage();
            expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
            expect(wrapper.getComponent(Head).props('title')).toBe(
                t('store.categories.title'),
            );
            expect(wrapper.get('h1').text()).toBe(t('store.categories.title'));
            expect(wrapper.findAll('thead th').map((c) => c.text())).toEqual(
                [
                    'name',
                    'parent',
                    'ordering',
                    'level',
                    'banner',
                    'icon',
                    'cover',
                    'featured',
                    'commission',
                    'actions',
                ].map((k) => t(`store.categories.${k}`)),
            );
            expect(wrapper.text()).toContain(t('store.categories.search'));
            expect(wrapper.get('#search').attributes('value')).toBe('Food');
            expect(wrapper.getComponent(AddResourceLink).props()).toMatchObject(
                { href: create(), label: t('store.categories.create') },
            );
            expect(wrapper.getComponent(TableLink).props('href')).toEqual(
                edit(options.parents[0], { query: { lang: 'en' } }),
            );
            expect(
                wrapper
                    .findAll('tbody img')
                    .map((img) => img.attributes('src')),
            ).toEqual([
                '/uploads/banner.webp',
                '/uploads/icon.webp',
                '/uploads/cover.webp',
            ]);
            expect(
                wrapper
                    .findAll('tbody img')
                    .map((img) => img.attributes('alt')),
            ).toEqual(
                ['banner', 'icon', 'cover'].map((k) =>
                    t(`store.categories.${k}`),
                ),
            );
            expect(wrapper.get('tbody').text()).toContain('2.50 %');
            expect(
                wrapper
                    .findAllComponents(TableActionLink)
                    .map((c) => c.props('href')),
            ).toEqual([
                edit(category, { query: { lang: 'en' } }),
                destroy(category, { mergeQuery: {} }),
            ]);
            expect(
                wrapper.getComponent(StoreStatusButton).props(),
            ).toMatchObject({
                action: updateFeatured({ mergeQuery: {} }),
                active: false,
                label: t('store.categories.featured'),
            });
            expect(wrapper.get('form input[name=id]').attributes('value')).toBe(
                '17',
            );
            expect(wrapper.text()).toContain(t('store.categories.edit'));
            expect(wrapper.text()).toContain(t('store.reference.delete'));
        },
    );
    it('gates every action with the matching permission', () => {
        const read = indexPage(['view_product_categories']);
        expect(read.findComponent(AddResourceLink).exists()).toBe(false);
        expect(read.findAllComponents(TableActionLink)).toHaveLength(0);
        expect(read.findComponent(StoreStatusButton).exists()).toBe(false);
        expect(read.findComponent(TableLink).exists()).toBe(false);
        expect(read.get('tbody').text()).toContain(
            t('store.reference.disabled'),
        );
        const add = indexPage(['add_product_category']);
        expect(add.findComponent(AddResourceLink).exists()).toBe(true);
        expect(add.findAllComponents(TableActionLink)).toHaveLength(0);
        const remove = indexPage(['delete_product_category']);
        expect(remove.findComponent(AddResourceLink).exists()).toBe(false);
        expect(remove.findAllComponents(TableActionLink)).toHaveLength(1);
        expect(remove.getComponent(TableActionLink).props('href')).toEqual(
            destroy(category, { mergeQuery: {} }),
        );
    });
    it('confirms deletion, preserves filters on pagination and resets page on search', async () => {
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const wrapper = indexPage();
        const deletion = wrapper
            .findAllComponents(TableActionLink)[1]
            .getComponent(Link);
        expect((deletion.props('onBefore') as () => boolean)()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', { label: 'Food' }),
        );
        expect(deletion.attributes('method')).toBe('delete');
        expect(deletion.props('as')).toBe('button');
        expect(wrapper.getComponent(Pagination).props('perPage')).toBe(50);
        wrapper.getComponent(Pagination).vm.$emit('update:perPage', 10);
        expect(routerGetMock).toHaveBeenLastCalledWith(
            categoriesIndex().url,
            { search: 'Food', per_page: 10 },
            { preserveState: true, replace: true },
        );
        await wrapper.get('#search').setValue('Fruit');
        await wrapper
            .get('#search')
            .element.closest('form')
            ?.dispatchEvent(
                new Event('submit', { bubbles: true, cancelable: true }),
            );
        expect(routerGetMock).toHaveBeenLastCalledWith(
            categoriesIndex().url,
            { search: 'Fruit', per_page: 50 },
            { preserveState: true, replace: true },
        );
        expect(indexPage([], true).text()).toContain(
            t('store.categories.empty'),
        );
    });
    it.each(['en', 'ar'] as const)(
        'uses the store form shell, actions and language tabs in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const creation = mount(Create, { props: options });
            const editing = editPage();

            for (const [wrapper, action, title] of [
                [creation, store(), 'create'],
                [editing, update(storeCategoryForm()), 'edit'],
            ] as const) {
                expect(
                    wrapper.getComponent(ResourceFormPage).props(),
                ).toMatchObject({
                    layout: StoreAdminLayout,
                    title: t(`store.categories.${title}`),
                    submitLabel: t('store.reference.save'),
                    submittingLabel: t('store.reference.saving'),
                    cancelHref: categoriesIndex(),
                    wide: true,
                });
                expect(wrapper.getComponent(Form).props('action')).toEqual(
                    action,
                );
                expect(
                    wrapper.getComponent(StoreCategoryFormFields).props(),
                ).toMatchObject({ ...options, errors: {} });
            }

            expect(
                editing.getComponent(StoreLanguageTabs).props(),
            ).toMatchObject({ languages, lang: 'ar' });
            expect(
                editing.getComponent(StoreLanguageTabs).props('href')('en'),
            ).toEqual(edit(storeCategoryForm(), { query: { lang: 'en' } }));
            expect(editing.get('input[name=lang]').attributes('value')).toBe(
                'ar',
            );
            expect(
                editing.getComponent(StoreCategoryFormFields).props(),
            ).toMatchObject({
                category: storeCategoryForm(),
                name: 'طعام',
                initialFiles: {
                    banner: [storeUpload()],
                    icon: [],
                    cover_image: [],
                },
            });
        },
    );
    it('forwards backend errors and processing to both forms', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { name: 'Refused' },
            processing: true,
        });

        for (const wrapper of [mount(Create, { props: options }), editPage()]) {
            expect(
                wrapper.getComponent(StoreCategoryFormFields).props('errors'),
            ).toEqual({ name: 'Refused' });
            expect(wrapper.getComponent(SubmitButton).props('processing')).toBe(
                true,
            );
        }
    });
});
