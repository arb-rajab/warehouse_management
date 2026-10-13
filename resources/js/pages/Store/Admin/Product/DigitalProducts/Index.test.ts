import { Form, Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    create,
    destroy,
    edit,
    download,
    index as digitalProductsIndex,
    updateFeatured,
    updatePublished,
    updateTodaysDeal,
} from '@/actions/App/Http/Controllers/Store/Admin/DigitalProductController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import FormField from '@/components/FormField.vue';
import Pagination from '@/components/Pagination.vue';
import StoreStatusButton from '@/components/StoreStatusButton.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import {
    paginated,
    storeDigitalProduct,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import type { StoreDigitalProduct } from '@/types/store';
import Index from './Index.vue';
const { usePageMock, getMock, formStateMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    getMock: vi.fn(),
    formStateMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Form: createFormStub(formStateMock),
        Link: createLinkStub(),
        Head: headStub,
        usePage: usePageMock,
        router: { get: getMock, post: vi.fn() },
    };
});
const allPermissions = [
    'show_digital_products',
    'add_digital_product',
    'edit_digital_product',
    'delete_digital_product',
    'download_digital_product',
    'product_edit',
];
const wrappers: ReturnType<typeof mount>[] = [];
function mountPage(
    products: StoreDigitalProduct[] = [storeDigitalProduct()],
    permissions = allPermissions,
) {
    usePageMock.mockReturnValue({
        url: digitalProductsIndex.url({ query: { search: 'Recipe' } }),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });
    const wrapper = mount(Index, {
        props: {
            products: paginated(products),
            filters: { search: 'Recipe', per_page: 25 },
            defaultLanguage: 'en',
        },
    });
    wrappers.push(wrapper);

    return wrapper;
}
beforeEach(() => {
    vi.clearAllMocks();
    formStateMock.mockReturnValue({ errors: {}, processing: false });
});
afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    i18n.global.locale.value = 'en';
    vi.restoreAllMocks();
});
describe('Store digital products Index', () => {
    it.each(['en', 'ar'] as const)(
        'renders every static label and the row data in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountPage([
                storeDigitalProduct(),
                storeDigitalProduct({
                    id: 32,
                    name: '<img src=x onerror=alert(1)>',
                    added_by: 'seller',
                    public_url: null,
                }),
            ]);
            expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
            expect(wrapper.getComponent(Head).props('title')).toBe(
                t('store.digitalProducts.title'),
            );
            expect(wrapper.get('h1').text()).toBe(
                t('store.digitalProducts.title'),
            );
            expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual(
                [
                    'name',
                    'addedBy',
                    'photo',
                    'basePrice',
                    'todaysDeal',
                    'published',
                    'featured',
                    'actions',
                ].map((key) => t(`store.digitalProducts.${key}`)),
            );
            expect(wrapper.getComponent(AddResourceLink).props()).toMatchObject(
                { label: t('store.digitalProducts.create') },
            );
            const search = wrapper.findAllComponents(Form)[0];
            expect(search.getComponent(FormField).props('label')).toBe(
                t('store.digitalProducts.search'),
            );
            expect(search.getComponent(SubmitButton).props()).toMatchObject({
                label: t('store.digitalProducts.search'),
                processingLabel: t('store.digitalProducts.searching'),
            });
            const [first, second] = wrapper.findAll('tbody tr');
            expect(first.get('a[target="_blank"]').attributes()).toMatchObject({
                href: '/temp/product/recipe-e-book',
                rel: 'noopener noreferrer',
            });
            expect(first.text()).toContain('Recipe e-book');
            expect(first.text()).toContain(
                t('store.digitalProducts.addedByAdmin'),
            );
            expect(first.text()).toContain('1,250.50');
            expect(first.get('img').attributes()).toMatchObject({
                src: '/assets/img/placeholder.jpg',
                alt: t('store.digitalProducts.photo'),
            });

            for (const key of ['edit', 'download', 'delete']) {
                expect(first.text()).toContain(
                    t(`store.digitalProducts.${key}`),
                );
            }

            expect(second.find('a[target="_blank"]').exists()).toBe(false);
            expect(second.text()).toContain('<img src=x onerror=alert(1)>');
            expect(second.findAll('img')).toHaveLength(1);
            expect(second.text()).toContain(
                t('store.digitalProducts.addedBySeller'),
            );
        },
    );
    it('shows a translated empty state and an unknown author as stored', () => {
        expect(mountPage([]).text()).toContain(
            t('store.digitalProducts.empty'),
        );
        const wrapper = mountPage([
            storeDigitalProduct({ added_by: 'vendor' }),
            storeDigitalProduct({ id: 33, added_by: null }),
        ]);
        const rows = wrapper.findAll('tbody tr');
        expect(rows[0].findAll('td')[1].text()).toBe('vendor');
        expect(rows[1].findAll('td')[1].text()).toBe('—');
    });
    it('visits create and edit with Inertia, downloads as a full load and deletes through Wayfinder', () => {
        const product = storeDigitalProduct();
        const wrapper = mountPage([product]);
        const addLink = wrapper.getComponent(AddResourceLink);
        expect(addLink.props()).toMatchObject({
            href: create(),
            inertia: true,
        });
        expect(addLink.getComponent(Link).props('href')).toEqual(create());
        const [editLink, downloadLink, deleteLink] =
            wrapper.findAllComponents(TableActionLink);
        expect(editLink.props()).toMatchObject({
            href: edit(product, { query: { lang: 'en' } }),
            inertia: true,
        });
        expect(editLink.getComponent(Link).props('href')).toEqual(
            edit(product, { query: { lang: 'en' } }),
        );
        expect(edit(product, { query: { lang: 'en' } }).url).toBe(
            '/temp/admin/digitalproducts/edit/31?lang=en',
        );
        expect(downloadLink.props()).toMatchObject({
            href: download('encrypted-31'),
            inertia: false,
        });
        expect(downloadLink.attributes('href')).toBe(
            '/temp/admin/digitalproducts/download/encrypted-31',
        );
        expect(downloadLink.findComponent(Link).exists()).toBe(false);
        expect(deleteLink.props()).toMatchObject({
            href: destroy(product, { mergeQuery: {} }),
            variant: 'danger',
        });
        expect(deleteLink.getComponent(Link).props('as')).toBe('button');
        expect(deleteLink.attributes()).toMatchObject({ method: 'delete' });
    });
    it('posts each switch through its own typed action with the next state', () => {
        const product = storeDigitalProduct({
            todays_deal: true,
            published: false,
            featured: true,
        });
        const wrapper = mountPage([product]);
        const switches = wrapper.findAllComponents(StoreStatusButton);
        expect(switches.map((button) => button.props())).toMatchObject([
            {
                action: updateTodaysDeal(product, { mergeQuery: {} }),
                active: true,
                label: t('store.digitalProducts.todaysDeal'),
            },
            {
                action: updatePublished(product, { mergeQuery: {} }),
                active: false,
                label: t('store.digitalProducts.published'),
            },
            {
                action: updateFeatured(product, { mergeQuery: {} }),
                active: true,
                label: t('store.digitalProducts.featured'),
            },
        ]);
        expect(
            switches.map(
                (button) =>
                    (
                        button.get('input[name="status"]')
                            .element as HTMLInputElement
                    ).value,
            ),
        ).toEqual(['0', '1', '0']);
    });
    it('shows a switch server error inside that switch', () => {
        formStateMock.mockReturnValue({
            errors: { status: 'Package limit reached' },
            processing: false,
        });
        const wrapper = mountPage();
        expect(
            wrapper.findAllComponents(StoreStatusButton)[1].text(),
        ).toContain('Package limit reached');
    });
    it('hides every action without its own permission and shows the switch state read-only', () => {
        const wrapper = mountPage(
            [storeDigitalProduct({ todays_deal: true })],
            ['show_digital_products'],
        );
        expect(wrapper.findComponent(AddResourceLink).exists()).toBe(false);
        expect(wrapper.findAllComponents(TableActionLink)).toHaveLength(0);
        expect(wrapper.findAllComponents(StoreStatusButton)).toHaveLength(0);
        const cells = wrapper.findAll('tbody td');
        expect(cells[4].text()).toBe(t('store.reference.enabled'));
        expect(cells[5].text()).toBe(t('store.reference.enabled'));
        expect(cells[6].text()).toBe(t('store.reference.disabled'));
        const only = (permission: string) =>
            mountPage(
                [storeDigitalProduct()],
                ['show_digital_products', permission],
            );
        expect(
            only('add_digital_product').findComponent(AddResourceLink).exists(),
        ).toBe(true);
        expect(
            only('edit_digital_product')
                .findAllComponents(TableActionLink)
                .map((link) => link.text()),
        ).toEqual([t('store.digitalProducts.edit')]);
        expect(
            only('download_digital_product')
                .findAllComponents(TableActionLink)
                .map((link) => link.text()),
        ).toEqual([t('store.digitalProducts.download')]);
        expect(
            only('delete_digital_product')
                .findAllComponents(TableActionLink)
                .map((link) => link.text()),
        ).toEqual([t('store.digitalProducts.delete')]);
        const switchesOnly = only('product_edit');
        expect(switchesOnly.findAllComponents(StoreStatusButton)).toHaveLength(
            3,
        );
        expect(switchesOnly.findAllComponents(TableActionLink)).toHaveLength(0);
    });
    it('confirms deletion with the product name and honors cancellation', () => {
        const wrapper = mountPage();
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const onBefore = wrapper
            .findAllComponents(TableActionLink)[2]
            .getComponent(Link)
            .props('onBefore') as () => boolean;
        expect(onBefore()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', { label: 'Recipe e-book' }),
        );
        confirm.mockReturnValue(true);
        expect(onBefore()).toBe(true);
    });
    it('searches through a constrained GET form and keeps the search when changing page size', () => {
        const wrapper = mountPage();
        const search = wrapper.findAllComponents(Form)[0];
        expect(search.props('action')).toEqual(digitalProductsIndex());
        expect(
            (search.get('input[name="per_page"]').element as HTMLInputElement)
                .value,
        ).toBe('25');
        expect(wrapper.get('#search').attributes()).toMatchObject({
            maxlength: '255',
            value: 'Recipe',
        });
        const pagination = wrapper.getComponent(Pagination);
        expect(pagination.props('perPage')).toBe(25);
        pagination.vm.$emit('update:perPage', 50);
        expect(getMock).toHaveBeenCalledWith(
            digitalProductsIndex.url(),
            { per_page: 50, search: 'Recipe' },
            { preserveState: true, replace: true },
        );
    });
});
