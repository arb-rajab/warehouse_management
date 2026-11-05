import { Form, Head, Link } from '@inertiajs/vue3';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fetch as fetchProducts } from '@/actions/App/Http/Controllers/ProductController';
import { edit as categoryEdit } from '@/actions/App/Http/Controllers/Store/Admin/CategoryController';
import {
    all as allProducts,
    bulkDestroy,
    create,
    destroy,
    duplicate,
    edit,
    inHouse as inHouseProducts,
    seller as sellerProducts,
    sellerEdit,
    sellerDuplicate,
    sellerDestroy,
    sellerBulkDestroy,
    sellerPublished,
    sellerFeatured,
    updateSaleStatus,
    updateTodaysDeal,
    updateProductApproval,
    updateFeatured,
    updateForSale,
    updatePublished,
} from '@/actions/App/Http/Controllers/Store/Admin/ProductController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import IconStat from '@/components/IconStat.vue';
import Pagination from '@/components/Pagination.vue';
import StoreListFilters from '@/components/StoreListFilters.vue';
import StoreStatusSwitch from '@/components/StoreStatusSwitch.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import {
    paginated,
    storeProductListFilters,
    storeProductListRow,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import {
    columnsDialog,
    expectColumnCanBeHidden,
    headerTexts,
    offeredColumns,
    openColumnPicker,
    setColumnShown,
    storedColumns,
} from '@/testing/tableColumns';
import type {
    StoreProductListFilters,
    StoreProductListRow,
    StoreProductListType,
} from '@/types/store';
import Index from './Index.vue';

const { usePageMock, getMock, formStateMock, httpPostMock, httpState } =
    vi.hoisted(() => ({
        usePageMock: vi.fn(),
        getMock: vi.fn(),
        formStateMock: vi.fn(),
        httpPostMock: vi.fn(),
        httpState: { processing: false },
    }));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Form: createFormStub(formStateMock),
        Link: createLinkStub(),
        Head: headStub,
        usePage: usePageMock,
        useHttp: () =>
            Object.assign(httpState, {
                post: httpPostMock,
            }),
        router: { get: getMock, post: vi.fn() },
    };
});

const allPermissions = [
    'show_in_house_products',
    'show_all_products',
    'show_seller_products',
    'add_new_product',
    'product_edit',
    'product_duplicate',
    'product_delete',
];
const categories = [
    { id: 3, name: 'Oils' },
    { id: 4, name: 'Spices' },
];
const wrappers: ReturnType<typeof mount>[] = [];

function mountPage({
    listType = 'admin',
    products = [storeProductListRow()],
    filters = storeProductListFilters(),
    permissions = allPermissions,
    approvalEnabled = false,
    sellers = [],
}: {
    listType?: StoreProductListType;
    products?: StoreProductListRow[];
    filters?: StoreProductListFilters;
    permissions?: string[];
    approvalEnabled?: boolean;
    sellers?: { id: number; name: string }[];
} = {}) {
    usePageMock.mockReturnValue({
        url: {
            admin: inHouseProducts,
            all: allProducts,
            seller: sellerProducts,
        }[listType].url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });
    const wrapper = mount(Index, {
        props: {
            listType,
            products: paginated(products, filters.per_page),
            filters,
            categories,
            defaultLanguage: 'en',
            approvalEnabled,
            sellers,
        },
    });
    wrappers.push(wrapper);

    return wrapper;
}
function categoryCell(wrapper: ReturnType<typeof mount>) {
    return wrapper.findAll('tbody td').filter((td) => td.text() === 'Oils')[0];
}
function forms(wrapper: ReturnType<typeof mount>) {
    return wrapper.findAllComponents(Form);
}

beforeEach(() => {
    vi.clearAllMocks();
    formStateMock.mockReturnValue({ errors: {}, processing: false });
    httpState.processing = false;
    window.history.replaceState(
        {},
        '',
        '/temp/admin/products/admin?search=Olive&page=2',
    );
});
afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    i18n.global.locale.value = 'en';
    window.history.replaceState({}, '', '/');
    vi.restoreAllMocks();
});

describe('Store product lists Index', () => {
    it.each(['en', 'ar'] as const)(
        'renders every static label of both lists in %s',
        (locale) => {
            i18n.global.locale.value = locale;

            for (const listType of ['admin', 'all'] as const) {
                const title = t(
                    listType === 'all'
                        ? 'store.products.allTitle'
                        : 'store.products.inHouseTitle',
                );
                const wrapper = mountPage({
                    listType,
                    products: [
                        storeProductListRow({
                            low_stock: true,
                            deleted_from_api: true,
                        }),
                    ],
                });
                expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(
                    true,
                );
                expect(wrapper.getComponent(Head).props('title')).toBe(title);
                expect(wrapper.get('h1').text()).toBe(title);
                expect(wrapper.get('h1 + div button').text()).toBe(
                    t('store.products.fetch'),
                );
                expect(
                    wrapper.getComponent(AddResourceLink).props('label'),
                ).toBe(t('store.products.create'));

                if (listType === 'all') {
                    expect(
                        wrapper
                            .get('#product-presets')
                            .attributes('aria-label'),
                    ).toBe(t('store.products.presets'));
                    expect(
                        wrapper
                            .findAll('#product-presets a')
                            .map((link) => link.text()),
                    ).toEqual(
                        [
                            'presetAllProducts',
                            'presetAllActive',
                            'presetAllForSale',
                            'presetAllFeatured',
                            'presetAllDeleted',
                        ].map((key) => t(`store.products.${key}`)),
                    );
                } else {
                    // The presets are the all list's status switch; the
                    // other lists keep their state boxes in the dialog.
                    expect(wrapper.find('#product-presets').exists()).toBe(
                        false,
                    );
                }

                const [filterForm, bulkForm] = forms(wrapper);
                expect(filterForm.getComponent(FormField).props('label')).toBe(
                    t('store.products.search'),
                );
                expect(filterForm.get('label[for="category_id"]').text()).toBe(
                    t('store.products.category'),
                );
                expect(
                    filterForm
                        .findAll('#category_id option')
                        .map((option) => option.text()),
                ).toEqual([
                    t('store.products.allCategories'),
                    'Oils',
                    'Spices',
                ]);
                expect(filterForm.get('label[for="type"]').text()).toBe(
                    t('store.products.sort'),
                );
                expect(
                    filterForm
                        .findAll('#type option')
                        .map((option) => option.text()),
                ).toEqual(
                    [
                        'defaultOrder',
                        'sortRatingDesc',
                        'sortRatingAsc',
                        'sortSalesDesc',
                        'sortSalesAsc',
                        'sortPriceDesc',
                        'sortPriceAsc',
                    ].map((key) => t(`store.products.${key}`)),
                );

                if (listType === 'all') {
                    // Never in both places: the all list has no state boxes.
                    expect(filterForm.find('fieldset').exists()).toBe(false);
                } else {
                    expect(filterForm.get('legend').text()).toBe(
                        t('store.products.stateFilters'),
                    );
                    expect(
                        filterForm
                            .findAll('fieldset label')
                            .map((label) => label.text()),
                    ).toEqual(
                        ['active', 'forSale', 'featured'].map((key) =>
                            t(`store.products.${key}`),
                        ),
                    );
                    expect(filterForm.get('fieldset p').text()).toBe(
                        t('store.products.inHouseStateHint'),
                    );
                }

                expect(filterForm.get('button[type="submit"]').text()).toBe(
                    t('common.filters.apply'),
                );
                expect(
                    filterForm.get('button[type="button"]').text(),
                ).toContain(t('common.filters.title'));
                expect(
                    bulkForm.getComponent(SubmitButton).props(),
                ).toMatchObject({
                    label: t('store.products.deleteSelection'),
                    processingLabel: t('store.products.deleting'),
                });
                expect(bulkForm.get('button').attributes('disabled')).toBe('');
                expect(
                    wrapper.findAll('thead th').map((th) => th.text()),
                ).toEqual(
                    [
                        'select',
                        'name',
                        'id',
                        'unit',
                        'info',
                        'totalStock',
                        'category',
                        'forSale',
                        'active',
                        'featured',
                        'deletedFromOtajer',
                        'actions',
                    ].map((key) => t(`store.products.${key}`)),
                );
                const row = wrapper.get('tbody tr');
                expect(
                    row.get('input[type="checkbox"]').attributes('aria-label'),
                ).toBe(
                    t('store.products.selectProduct', { name: 'Olive oil 1L' }),
                );

                for (const text of [
                    t('store.products.salesCount', { count: 7 }),
                    t('store.products.basePrice', { price: '€12.50' }),
                    t('store.products.profit', { price: '€1.25' }),
                    t('store.products.low'),
                    t('store.products.deletedBadge'),
                    t('store.products.view'),
                    t('store.products.edit'),
                    t('store.products.duplicate'),
                    t('store.products.delete'),
                ]) {
                    expect(row.text()).toContain(text);
                }

                expect(row.get('img').attributes('alt')).toBe(
                    t('store.products.photo'),
                );
                expect(wrapper.text()).toContain(t('store.products.selectAll'));
            }
        },
    );

    it('renders a product row from its narrow payload, escaping stored names', () => {
        const wrapper = mountPage({
            products: [
                storeProductListRow(),
                storeProductListRow({
                    id: 42,
                    name: '<img src=x onerror=alert(1)>',
                    ar_name: '',
                    variant_stocks: [
                        { variant: 'Small', qty: 2 },
                        { variant: 'Large', qty: 5 },
                    ],
                    stock_quantity: 7,
                    category: null,
                    public_url: null,
                    deleted_from_api: true,
                }),
            ],
        });
        const [first, second] = wrapper.findAll('tbody tr');
        const cells = first.findAll('td');
        expect(first.get('img').attributes('src')).toBe(
            '/assets/img/placeholder.jpg',
        );
        expect(cells[1].text()).toContain('Olive oil 1L');
        expect(cells[1].get('[dir="auto"]').text()).toBe('زيت زيتون');
        expect(cells[1].find('.text-red-700').exists()).toBe(false);
        expect(cells[2].text()).toBe('41');
        expect(cells[3].text()).toBe('Box(12)');
        expect(cells[5].text()).toBe('40');
        expect(cells[6].text()).toBe('Oils');
        expect(cells[10].text()).toBe('');
        const secondCells = second.findAll('td');
        expect(secondCells[1].text()).toContain('<img src=x onerror=alert(1)>');
        expect(second.findAll('img')).toHaveLength(1);
        expect(secondCells[1].find('[dir="auto"]').exists()).toBe(false);
        expect(secondCells[1].find('.text-red-700').exists()).toBe(true);
        expect(secondCells[2].text()).toBe('42');
        expect(
            secondCells[5].findAll('span').map((span) => span.text()),
        ).toEqual(['Small - 2', 'Large - 5']);
        expect(secondCells[6].text()).toBe('');
        expect(secondCells[10].text()).toBe(t('store.products.deletedBadge'));
        expect(second.find('a[target="_blank"]').exists()).toBe(false);
        expect(first.get('a[target="_blank"]').text()).toBe(
            t('store.products.view'),
        );
        expect(mountPage({ products: [] }).text()).toContain(
            t('store.products.empty'),
        );
    });

    it('links create and edit as Inertia visits, view as a full load, and posts every row action through its typed route with the list', () => {
        const product = storeProductListRow();

        for (const list of ['admin', 'all'] as const) {
            const wrapper = mountPage({ listType: list, products: [product] });
            const query = { mergeQuery: { list } };
            expect(wrapper.getComponent(AddResourceLink).props()).toMatchObject(
                { href: create(), inertia: true },
            );
            expect(
                wrapper
                    .getComponent(AddResourceLink)
                    .getComponent(Link)
                    .props('href'),
            ).toEqual(create());
            const [view, editLink, copy, remove] =
                wrapper.findAllComponents(TableActionLink);
            expect(view.props()).toMatchObject({
                href: '/temp/product/olive-oil-1l',
                inertia: false,
            });
            expect(view.attributes()).toMatchObject({
                target: '_blank',
                rel: 'noopener noreferrer',
            });
            expect(editLink.props()).toMatchObject({
                href: edit(product, { query: { lang: 'en' } }),
                inertia: true,
            });
            expect(editLink.attributes('href')).toBe(
                '/temp/admin/products/admin/41/edit?lang=en',
            );
            // The edit form is Inertia (§23): a visit, not a full load.
            expect(editLink.findComponent(Link).exists()).toBe(true);
            expect(copy.props('href')).toEqual(duplicate(product, query));
            expect(copy.props('href')).toMatchObject({
                url: `/temp/admin/products/41/duplicate?search=Olive&page=2&list=${list}`,
                method: 'post',
            });
            expect(copy.attributes()).toMatchObject({ method: 'post' });
            expect(copy.getComponent(Link).props('as')).toBe('button');
            expect(remove.props()).toMatchObject({
                href: destroy(product, query),
                variant: 'danger',
            });
            expect(remove.props('href')).toMatchObject({
                url: `/temp/admin/products/41?search=Olive&page=2&list=${list}`,
                method: 'delete',
            });
            expect(remove.attributes()).toMatchObject({ method: 'delete' });
            expect(remove.getComponent(Link).props('as')).toBe('button');
            expect(
                wrapper
                    .findAllComponents(StoreStatusSwitch)
                    .map((button) => button.props()),
            ).toMatchObject([
                {
                    action: updateForSale(product, query),
                    checked: true,
                    label: t('store.products.forSale'),
                },
                {
                    action: updatePublished(product, query),
                    checked: true,
                    label: t('store.products.active'),
                },
                {
                    action: updateFeatured(product, query),
                    checked: false,
                    label: t('store.products.featured'),
                },
            ]);
            expect(forms(wrapper)[1].props('action')).toEqual(
                bulkDestroy(query),
            );
            const presetLinks = wrapper.findAll('#product-presets a');

            if (list !== 'all') {
                expect(presetLinks).toHaveLength(0);

                continue;
            }

            expect(presetLinks.map((link) => link.attributes('href'))).toEqual(
                [
                    'allProducts',
                    'allActive',
                    'allForSale',
                    'allFeatured',
                    'allDeleted',
                ].map((preset) => allProducts.url({ query: { [preset]: 1 } })),
            );
        }
    });

    it('shows a switch server error inside that switch', () => {
        formStateMock.mockReturnValue({
            errors: { status: 'Package limit reached' },
            processing: false,
        });
        const wrapper = mountPage();
        expect(
            wrapper.findAllComponents(StoreStatusSwitch)[1].text(),
        ).toContain('Package limit reached');
    });

    it('renders the switches as real switches and locks only an Otajer-owned Active', () => {
        const wrapper = mountPage({
            products: [
                storeProductListRow({ published_locked: true }),
                storeProductListRow({ id: 42, featured: true }),
            ],
        });
        const switches = wrapper.findAllComponents(StoreStatusSwitch);
        expect(switches).toHaveLength(6);
        expect(switches.map((item) => item.props('locked'))).toEqual([
            undefined,
            {
                reason: t('store.products.otajerLocked'),
                hint: t('store.products.form.otajerSynced'),
            },
            undefined,
            undefined,
            undefined,
            undefined,
        ]);
        const locked = switches[1].get('[role="switch"]');
        expect(locked.attributes()).toMatchObject({
            type: 'button',
            'aria-checked': 'true',
            'aria-disabled': 'true',
            'aria-label': t('store.products.active'),
            title: t('store.products.form.otajerSynced'),
        });
        expect(switches[1].text()).toContain(t('store.products.otajerLocked'));
        expect(switches[1].findComponent(Form).exists()).toBe(false);
        expect(
            switches[4].get('[role="switch"]').attributes('aria-checked'),
        ).toBe('true');
        expect(switches[4].get('[role="switch"]').attributes('type')).toBe(
            'submit',
        );
        expect(i18n.global.te('store.products.otajerLocked', 'ar')).toBe(true);
    });

    it('hides every action without its own permission and shows switch states read-only', () => {
        const wrapper = mountPage({
            products: [storeProductListRow({ featured: true })],
            permissions: ['show_in_house_products'],
        });
        expect(wrapper.findComponent(AddResourceLink).exists()).toBe(false);
        expect(wrapper.find('h1 + div button').exists()).toBe(false);
        expect(wrapper.find('#product-presets').exists()).toBe(false);
        expect(forms(wrapper)).toHaveLength(1);
        expect(wrapper.findAllComponents(StoreStatusSwitch)).toHaveLength(0);
        expect(
            wrapper
                .findAllComponents(TableActionLink)
                .map((link) => link.text()),
        ).toEqual([t('store.products.view')]);
        expect(wrapper.find('tbody input[type="checkbox"]').exists()).toBe(
            false,
        );
        expect(wrapper.text()).not.toContain(t('store.products.selectAll'));
        expect(wrapper.findAll('thead th')[0].text()).toBe(
            t('store.products.name'),
        );
        const cells = wrapper.findAll('tbody td');
        expect(cells.slice(6, 9).map((cell) => cell.text())).toEqual([
            t('store.reference.enabled'),
            t('store.reference.enabled'),
            t('store.reference.enabled'),
        ]);
        const only = (permission: string) =>
            mountPage({ permissions: ['show_in_house_products', permission] });
        expect(
            only('add_new_product').findComponent(AddResourceLink).exists(),
        ).toBe(true);
        expect(only('add_new_product').find('h1 + div button').exists()).toBe(
            true,
        );
        // The presets belong to the all list only, and need the permission.
        expect(
            only('show_all_products').find('#product-presets').exists(),
        ).toBe(false);
        expect(
            mountPage({
                listType: 'all',
                permissions: ['show_all_products'],
            })
                .find('#product-presets')
                .exists(),
        ).toBe(true);
        expect(
            mountPage({
                listType: 'all',
                permissions: ['show_in_house_products'],
            })
                .find('#product-presets')
                .exists(),
        ).toBe(false);
        expect(
            only('product_edit').findAllComponents(StoreStatusSwitch),
        ).toHaveLength(3);
        expect(
            only('product_edit')
                .findAllComponents(TableActionLink)
                .map((link) => link.text()),
        ).toEqual([t('store.products.view'), t('store.products.edit')]);
        expect(
            only('product_duplicate')
                .findAllComponents(TableActionLink)
                .map((link) => link.text()),
        ).toEqual([t('store.products.view'), t('store.products.duplicate')]);
        const deleter = only('product_delete');
        expect(
            deleter
                .findAllComponents(TableActionLink)
                .map((link) => link.text()),
        ).toEqual([t('store.products.view'), t('store.products.delete')]);
        expect(forms(deleter)).toHaveLength(2);
        expect(deleter.findAll('thead th')[0].text()).toBe(
            t('store.products.select'),
        );
    });

    it('confirms single and bulk deletion and honors cancellation', async () => {
        const wrapper = mountPage({
            products: [
                storeProductListRow(),
                storeProductListRow({ id: 42, name: 'Rice' }),
            ],
        });
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const onBefore = wrapper
            .findAllComponents(TableActionLink)[3]
            .getComponent(Link)
            .props('onBefore') as () => boolean;
        expect(onBefore()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', { label: 'Olive oil 1L' }),
        );
        confirm.mockReturnValue(true);
        expect(onBefore()).toBe(true);

        const bulk = forms(wrapper)[1];
        const bulkBefore = () => (bulk.props('onBefore') as () => boolean)();
        confirm.mockClear();
        expect(bulkBefore()).toBe(false);
        expect(confirm).not.toHaveBeenCalled();
        await wrapper.findAll('tbody input[type="checkbox"]')[1].setValue(true);
        expect(
            bulk
                .findAll('input[name="id[]"]')
                .map((input) => (input.element as HTMLInputElement).value),
        ).toEqual(['42']);
        expect(bulk.get('button').attributes('disabled')).toBeUndefined();
        confirm.mockReturnValue(false);
        expect(bulkBefore()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', {
                label: t('store.products.selection', { count: 1 }),
            }),
        );
        confirm.mockReturnValue(true);
        expect(bulkBefore()).toBe(true);
        const selectAll = wrapper.get('label.mt-3 input');
        await selectAll.setValue(true);
        expect(bulk.findAll('input[name="id[]"]')).toHaveLength(2);
        expect((selectAll.element as HTMLInputElement).checked).toBe(true);
        bulk.vm.$emit('success');
        await wrapper.vm.$nextTick();
        expect(bulk.findAll('input[name="id[]"]')).toHaveLength(0);
        formStateMock.mockReturnValue({
            errors: { id: 'The selected id is invalid.' },
            processing: false,
        });
        expect(
            mountPage()
                .findAllComponents(Form)[1]
                .getComponent(FieldError)
                .props('message'),
        ).toBe('The selected id is invalid.');
    });

    it('filters through a GET form that keeps the current state and page size', () => {
        const wrapper = mountPage({
            listType: 'all',
            filters: storeProductListFilters({
                search: 'Olive',
                category_id: 4,
                type: 'unit_price,desc',
                active: true,
                deleted: true,
                user_id: 9,
                preset: 'allFeatured',
                per_page: 50,
            }),
        });
        const filterForm = forms(wrapper)[0];
        expect(filterForm.props('action')).toEqual(allProducts());
        expect(
            (
                filterForm.get('input[name="per_page"]')
                    .element as HTMLInputElement
            ).value,
        ).toBe('50');
        expect(wrapper.get('#search').attributes()).toMatchObject({
            maxlength: '255',
            value: 'Olive',
            name: 'search',
        });
        expect(
            (wrapper.get('#category_id').element as HTMLSelectElement).value,
        ).toBe('4');
        expect((wrapper.get('#type').element as HTMLSelectElement).value).toBe(
            'unit_price,desc',
        );
        // Category is the inline key filter here; sort is the only dialog
        // filter (counted). The preset links are the status switch, so the
        // all list has no state boxes: a filter is never in both places.
        expect(filterForm.findAll('#category_id').length).toBe(1);
        expect(filterForm.find('fieldset').exists()).toBe(false);
        expect(
            wrapper
                .get('[role="dialog"]')
                .findAll('select, input')
                .map((control) => control.attributes('name')),
        ).toEqual(['type']);
        expect(
            wrapper.get('[role="dialog"]').find('#category_id').exists(),
        ).toBe(false);
        expect(wrapper.getComponent(StoreListFilters).props('chips')).toEqual([
            {
                key: 'type',
                label: t('store.products.sort'),
                value: t('store.products.sortPriceDesc'),
                keys: ['type'],
            },
        ]);
        const presetLinks = wrapper.findAll('#product-presets a');
        expect(presetLinks[3].classes()).toContain('bg-gray-900');
        expect(presetLinks[0].classes()).not.toContain('bg-gray-900');
        const pagination = wrapper.getComponent(Pagination);
        expect(pagination.props()).toMatchObject({ perPage: 50 });
        pagination.vm.$emit('update:perPage', 25);
        expect(getMock).toHaveBeenCalledWith(
            allProducts.url(),
            {
                per_page: 25,
                search: 'Olive',
                category_id: 4,
                type: 'unit_price,desc',
                user_id: 9,
                active: 1,
                deleted: 1,
                allFeatured: 1,
            },
            { preserveState: true, replace: true },
        );
        const inHouse = mountPage();
        expect(forms(inHouse)[0].props('action')).toEqual(inHouseProducts());
        inHouse.getComponent(Pagination).vm.$emit('update:perPage', 10);
        expect(getMock).toHaveBeenLastCalledWith(
            inHouseProducts.url(),
            { per_page: 10 },
            { preserveState: true, replace: true },
        );
        expect(inHouse.findAll('tbody tr')).toHaveLength(1);
    });

    it('requests a product fetch through the legacy endpoint and reports either answer', async () => {
        const wrapper = mountPage();
        const button = wrapper.get('h1 + div button');
        httpPostMock.mockResolvedValueOnce({ success: true });
        await button.trigger('click');
        await flushPromises();
        expect(httpPostMock).toHaveBeenCalledWith(fetchProducts.url());
        expect(wrapper.get('[role="status"]').text()).toBe(
            t('store.products.fetchStarted'),
        );
        httpPostMock.mockRejectedValueOnce(new Error('500'));
        await button.trigger('click');
        await flushPromises();
        expect(wrapper.get('[role="status"]').text()).toBe(
            t('store.products.fetchBusy'),
        );
        httpState.processing = true;
        const busy = mountPage();
        expect(
            busy.get('h1 + div button').attributes('disabled'),
        ).toBeDefined();
        expect(busy.get('h1 + div button').text()).toBe(
            t('store.products.fetching'),
        );
    });
});

describe('seller product list', () => {
    it.each(['en', 'ar'] as const)(
        'renders the seller filters and labels in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountPage({
                listType: 'seller',
                approvalEnabled: true,
                sellers: [{ id: 9, name: '<b>Seller</b>' }],
                filters: storeProductListFilters({
                    user_id: 9,
                    approved: false,
                }),
            });
            expect(wrapper.getComponent(Head).props('title')).toBe(
                t('store.nav.sellerProducts'),
            );
            expect(wrapper.get('h1').text()).toBe(
                t('store.nav.sellerProducts'),
            );
            expect(forms(wrapper)[0].props('action')).toEqual(sellerProducts());
            expect(wrapper.findComponent(AddResourceLink).exists()).toBe(false);
            expect(wrapper.text()).not.toContain(t('store.products.fetch'));

            for (const key of [
                'seller',
                'allSellers',
                'approvalStatus',
                'allApprovals',
                'approved',
                'unapproved',
            ]) {
                expect(t(`store.products.${key}`)).not.toBe(
                    `store.products.${key}`,
                );
                expect(wrapper.text()).toContain(t(`store.products.${key}`));
            }

            expect(wrapper.text()).toContain(
                t('store.products.form.todaysDeal'),
            );
            expect(wrapper.get('#user_id').element).toHaveProperty(
                'value',
                '9',
            );
            expect(wrapper.get('#approved').element).toHaveProperty(
                'value',
                '0',
            );
            expect(wrapper.find('#user_id b').exists()).toBe(false);
            expect(wrapper.get('#user_id').text()).toContain('<b>Seller</b>');
            expect(wrapper.find('input[name=deleted]').exists()).toBe(false);
        },
    );
    it('keeps the seller inline and puts category, approval and sort in the dialog', () => {
        const wrapper = mountPage({
            listType: 'seller',
            approvalEnabled: true,
            sellers: [{ id: 9, name: 'Seller' }],
            filters: storeProductListFilters({
                user_id: 9,
                approved: false,
                category_id: 3,
                featured: true,
            }),
        });
        const inline = wrapper.get('form#store-list-filters');
        const dialog = wrapper.get('[role="dialog"]');
        expect(inline.find('#user_id').exists()).toBe(true);
        expect(dialog.find('#user_id').exists()).toBe(false);
        expect(inline.find('#category_id').exists()).toBe(true);
        expect(
            dialog
                .findAll('select, input')
                .map((control) => control.attributes('name')),
        ).toEqual([
            'approved',
            'category_id',
            'type',
            'active',
            'for_sale',
            'featured',
        ]);
        expect(
            (dialog.get('#category_id').element as HTMLSelectElement).value,
        ).toBe('3');
        // approved, category and featured; the seller is not counted.
        expect(wrapper.getComponent(StoreListFilters).props('chips')).toEqual([
            {
                key: 'approved',
                label: t('store.products.approvalStatus'),
                value: t('store.products.unapproved'),
                keys: ['approved'],
            },
            {
                key: 'category_id',
                label: t('store.products.category'),
                value: 'Oils',
                keys: ['category_id'],
            },
            {
                key: 'featured',
                label: t('store.products.featured'),
                keys: ['featured'],
            },
        ]);
        expect(
            wrapper
                .getComponent(StoreListFilters)
                .get('input[name="per_page"]')
                .attributes('value'),
        ).toBe('20');
    });
    it('uses the seller edit, switches, duplicate, DELETE and bulk routes with current query', () => {
        const product = storeProductListRow();
        const wrapper = mountPage({
            listType: 'seller',
            approvalEnabled: true,
        });
        const query = { mergeQuery: { list: 'seller' } };
        expect(
            wrapper
                .findAllComponents(StoreStatusSwitch)
                .map((button) => button.props()),
        ).toMatchObject([
            {
                action: updateSaleStatus(product, query),
                checked: true,
                inputName: 'status',
            },
            { action: sellerPublished(product, query), checked: true },
            {
                action: updateProductApproval(product, query),
                checked: true,
                inputName: 'approved',
            },
            { action: sellerFeatured(product, query), checked: false },
            { action: updateTodaysDeal(product, query), checked: false },
        ]);
        const actions = wrapper.findAllComponents(TableActionLink);
        expect(
            actions
                .find((action) =>
                    action.text().includes(t('store.products.edit')),
                )
                ?.props('href'),
        ).toEqual(sellerEdit(product, { query: { lang: 'en' } }));
        expect(
            actions
                .find((action) =>
                    action.text().includes(t('store.products.duplicate')),
                )
                ?.props('href'),
        ).toEqual(sellerDuplicate(product, query));
        const remove = actions.find((action) =>
            action.text().includes(t('store.products.delete')),
        )!;
        expect(remove.props('href')).toEqual(sellerDestroy(product, query));
        expect(remove.attributes('method')).toBe('delete');
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const onBefore = remove
            .getComponent(Link)
            .props('onBefore') as () => boolean;
        expect(onBefore()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', { label: product.name }),
        );
        expect(forms(wrapper)[1].props('action')).toEqual(
            sellerBulkDestroy(query),
        );
        confirm.mockRestore();
    });
    it('hides approval when its setting is off and all writes without their permissions', () => {
        const wrapper = mountPage({
            listType: 'seller',
            permissions: ['show_seller_products'],
        });
        expect(wrapper.find('#approved').exists()).toBe(false);
        expect(wrapper.findAllComponents(StoreStatusSwitch)).toHaveLength(0);
        expect(wrapper.findAllComponents(TableActionLink)).toHaveLength(1);
        expect(forms(wrapper)).toHaveLength(1);
    });
    it('offers the other four seller switches when approval is disabled', () => {
        const wrapper = mountPage({ listType: 'seller' });
        expect(wrapper.find('#approved').exists()).toBe(false);
        expect(
            wrapper
                .findAllComponents(StoreStatusSwitch)
                .map((button) => button.props('label')),
        ).toEqual([
            t('store.products.forSale'),
            t('store.products.active'),
            t('store.products.featured'),
            t('store.products.form.todaysDeal'),
        ]);
    });
    it('preserves an explicitly unapproved filter and seller on per-page changes', async () => {
        const wrapper = mountPage({
            listType: 'seller',
            filters: storeProductListFilters({
                approved: false,
                user_id: 9,
                active: true,
            }),
        });
        wrapper.getComponent(Pagination).vm.$emit('update:perPage', 10);
        await flushPromises();
        expect(getMock).toHaveBeenCalledWith(
            sellerProducts.url(),
            { per_page: 10, approved: 0, user_id: 9, active: 1 },
            { preserveState: true, replace: true },
        );
    });
    it('links the category to its edit page for staff who may edit categories', () => {
        const wrapper = mountPage({
            products: [storeProductListRow({ category_id: 5 })],
            permissions: ['edit_product_category'],
        });
        const link = categoryCell(wrapper).get('a');

        expect(link.text()).toBe('Oils');
        expect(link.attributes('href')).toBe(
            categoryEdit(5, { query: { lang: 'en' } }).url,
        );
    });

    it('keeps the category as plain text without that permission or without an id', () => {
        const withoutAccess = mountPage({
            products: [storeProductListRow({ category_id: 5 })],
            permissions: [],
        });
        expect(categoryCell(withoutAccess).find('a').exists()).toBe(false);

        const withoutId = mountPage({
            products: [storeProductListRow({ category_id: null })],
            permissions: ['edit_product_category'],
        });
        expect(categoryCell(withoutId).find('a').exists()).toBe(false);
    });

    it('shows sales, base price and profit as icon pairs that each explain their value', () => {
        const wrapper = mountPage();
        const stats = wrapper.get('tbody tr').findAllComponents(IconStat);

        expect(stats.map((stat) => stat.props('label'))).toEqual([
            t('store.products.salesCount', { count: 7 }),
            t('store.products.basePrice', { price: '€12.50' }),
            t('store.products.profit', { price: '€1.25' }),
        ]);
        expect(stats.map((stat) => stat.props('value'))).toEqual([
            7,
            '€12.50',
            '€1.25',
        ]);
    });

    it('lets staff hide optional columns per list, keeps the name and remembers the choice', async () => {
        window.localStorage.clear();
        const wrapper = mountPage({
            listType: 'seller',
            approvalEnabled: true,
        });
        const switchCount = () =>
            wrapper.get('tbody tr').findAllComponents(StoreStatusSwitch).length;
        expect(switchCount()).toBe(5);
        expect(wrapper.get('tbody').text()).toContain('Oils');

        await expectColumnCanBeHidden(wrapper, 'product.products.seller', {
            key: 'category',
            label: t('store.products.category'),
        });
        expect(wrapper.get('tbody').text()).not.toContain('Oils');
        await setColumnShown(wrapper, t('store.products.featured'), false);
        expect(headerTexts(wrapper)).not.toContain(
            t('store.products.featured'),
        );
        expect(switchCount()).toBe(4);
        expect(storedColumns('product.products.seller')).not.toContain(
            'featured',
        );

        // The name is the row's identifier: shown, locked, never stored away.
        const name = columnsDialog(wrapper)
            .findAll('label')
            .find((item) => item.text() === t('store.products.name'))!
            .get('input');
        expect((name.element as HTMLInputElement).disabled).toBe(true);
        expect(headerTexts(wrapper)).toContain(t('store.products.name'));
        expect(offeredColumns(wrapper)).toContain(t('store.products.approved'));

        // Another list type keeps its own choice (and its own switch columns).
        const inHouse = mountPage({ listType: 'admin' });
        expect(headerTexts(inHouse)).toContain(t('store.products.category'));
        await openColumnPicker(inHouse);
        expect(offeredColumns(inHouse)).not.toContain(
            t('store.products.approved'),
        );
        window.localStorage.clear();
    });
});
