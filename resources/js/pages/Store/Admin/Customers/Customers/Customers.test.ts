import { Form, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import {
    create,
    repCreate,
    store,
    repStore,
    update,
    destroy,
    index as customersIndex,
    repIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import { assign } from '@/actions/App/Http/Controllers/Store/Admin/RepresentativePackageController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import Pagination from '@/components/Pagination.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreCustomerFormFields from '@/components/StoreCustomerFormFields.vue';
import StoreListFilters from '@/components/StoreListFilters.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import {
    paginated,
    storeSharedProps,
    storeCustomer,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import type { StoreCustomer } from '@/types/storeCustomers';
import Create from './Create.vue';
import Edit from './Edit.vue';
import Index from './Index.vue';
import Views from './Views.vue';
const { usePageMock, routerGetMock, confirmMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
    confirmMock: vi.fn(() => true),
}));
vi.mock('@/lib/confirm', () => ({
    confirmDelete: confirmMock,
    confirmLogout: () => true,
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        Form: createFormStub(() => ({ errors: {}, processing: false })),
        usePage: usePageMock,
        useHttp: () => ({ processing: false, get: vi.fn() }),
        router: { get: routerGetMock, post: vi.fn() },
    };
});
const customer = storeCustomer();
const countries = [{ id: 1, name: 'Netherlands' }];
function pagePermissions(permissions: string[]): void {
    usePageMock.mockReturnValue({
        url: customersIndex.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });
}
function indexPage(
    permissions: string[] = [
        'view_all_customers',
        'edit_customer',
        'delete_customer',
        'ban_customer',
        'login_as_customer',
    ],
    representatives = false,
) {
    pagePermissions(permissions);

    return mount(Index, {
        props: {
            customers: paginated([customer]),
            filters: { per_page: 20 },
            representatives,
            packages: [{ id: 2, name: 'Gold', percentage: '10' }],
        },
    });
}
beforeEach(() => {
    vi.clearAllMocks();
    window.localStorage.clear();
    pagePermissions(['view_all_customers', 'edit_customer']);
});
it('customer and representative create pages pass complete form contracts and labels', () => {
    for (const representative of [false, true]) {
        const wrapper = mount(Create, { props: { countries, representative } });
        expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
            layout: StoreAdminLayout,
            wide: true,
            action: representative ? repStore() : store(),
            cancelHref: representative ? repIndex() : customersIndex(),
            submitLabel: t('store.customers.save'),
            submittingLabel: t('store.customers.saving'),
        });
        expect(
            wrapper.getComponent(StoreCustomerFormFields).props(),
        ).toMatchObject({
            countries,
            representative,
            creating: true,
            errors: {},
        });
        expect(wrapper.get('h1').text()).toBe(
            t(
                representative
                    ? 'store.customers.createRepresentative'
                    : 'store.customers.create',
            ),
        );
    }
});
it('edit page passes customer props and keeps independent forms outside the profile form', () => {
    const wrapper = mount(Edit, {
        props: { customer, countries, addresses: [] },
    });
    expect(wrapper.getComponent(ResourceFormPage).props('action')).toEqual(
        update(customer),
    );
    expect(
        wrapper.getComponent(StoreCustomerFormFields).props('customer'),
    ).toEqual(customer);
    expect(wrapper.findAll('form form')).toHaveLength(0);

    for (const key of [
        'notes',
        'activity',
        'newUser',
        'contacted',
        'sendAgreement',
        'addresses',
        'addAddress',
        'downloadLicense',
    ]) {
        expect(wrapper.text()).toContain(t(`store.customers.${key}`));
    }

    expect(wrapper.find('a[href$="license.pdf"]').exists()).toBe(true);
});
it('index renders columns labels and every permitted action', () => {
    const wrapper = indexPage();

    for (const key of [
        'title',
        'search',
        'archived',
        'select',
        'status',
        'actions',
        'edit',
        'views',
        'toggleVerified',
        'toggleImportant',
        'toggleBan',
        'loginAs',
        'deleteSelected',
        'delete',
    ]) {
        expect(wrapper.text()).toContain(t(`store.customers.${key}`));
    }

    expect(wrapper.getComponent(AddResourceLink).props('href')).toEqual(
        create(),
    );
    expect(wrapper.getComponent(Pagination).props()).toMatchObject({
        links: paginated([]).meta.links,
        perPage: 20,
    });
    expect(wrapper.text()).toContain('Amy');
    expect(wrapper.text()).toContain('amy@example.com');
});
it('read-only staff receive no writes or impersonation links', () => {
    const wrapper = indexPage(['view_all_customers']);
    expect(wrapper.findComponent(AddResourceLink).exists()).toBe(false);
    expect(wrapper.findAllComponents(TableActionLink)).toHaveLength(1);
    expect(wrapper.find('input[type="checkbox"]').exists()).toBe(false);

    for (const key of ['edit', 'delete', 'toggleBan', 'loginAs']) {
        expect(wrapper.text()).not.toContain(t(`store.customers.${key}`));
    }
});
it('archive delete links use Wayfinder clear query and native confirmation', () => {
    const wrapper = indexPage();
    const link = wrapper
        .findAllComponents(TableActionLink)
        .find((item) => item.text() === t('store.customers.delete'));
    expect(link?.props('href')).toEqual(destroy(customer, { mergeQuery: {} }));
    const before = link?.getComponent(Link).props('onBefore') as () => boolean;
    before();
    expect(confirmMock).toHaveBeenCalledWith('Amy');
});
it('representative index shows package dialog and its assignment contract', async () => {
    const wrapper = indexPage(
        [
            'view_all_customers',
            'assign_representative_package',
            'edit_customer',
        ],
        true,
    );
    expect(wrapper.getComponent(AddResourceLink).props('href')).toEqual(
        repCreate(),
    );
    const button = wrapper
        .findAll('button')
        .find((item) => item.text() === t('store.customers.assignPackage'));
    await button?.trigger('click');
    const packageDialog = wrapper
        .findAll('[role="dialog"]')
        .find((dialog) => dialog.find('#package_id').exists());
    expect(packageDialog?.text()).toContain('Gold (10%)');
    expect(
        wrapper
            .get('label[for="package_id"]')
            .findComponent(RequiredMark)
            .exists(),
    ).toBe(true);
    expect(
        wrapper.get('#package_id').element.closest('form')?.textContent,
    ).toContain(t('common.requiredLegend'));
    const form = wrapper
        .findAllComponents(Form)
        .find((item) =>
            item.element.contains(wrapper.get('#package_id').element),
        );
    expect(form?.props('action')).toEqual(assign());
    expect(wrapper.get('input[name="user_id"]').attributes('value')).toBe('17');
    expect(
        wrapper.get('select[name="package_id"]').attributes('required'),
    ).toBeDefined();
    form?.vm.$emit('success');
    await wrapper.vm.$nextTick();
    expect(wrapper.find('#package_id').exists()).toBe(false);
});
it('page size keeps every applied filter and omits the unset ones', () => {
    pagePermissions(['view_all_customers', 'delete_customer']);
    const wrapper = mount(Index, {
        props: {
            customers: paginated([customer]),
            filters: {
                per_page: 20,
                search: 'Amy',
                archived: true,
                important: false,
                verified: true,
                activity_status: 'new_user',
            },
            representatives: false,
            packages: [],
        },
    });
    wrapper.getComponent(Pagination).vm.$emit('update:perPage', 10);
    expect(routerGetMock).toHaveBeenCalledWith(
        customersIndex().url,
        {
            per_page: 10,
            search: 'Amy',
            activity_status: 'new_user',
            archived: 1,
            important: 0,
            verified: 1,
        },
        { preserveState: true, replace: true },
    );
});
it('keeps search and archived inline and puts the other filters in the dialog', () => {
    const wrapper = indexPage();
    const form = wrapper.getComponent(StoreListFilters);
    expect(form.props('action')).toEqual(customersIndex());
    const inline = wrapper.get('form#store-list-filters');
    expect(inline.get('input[name="per_page"]').attributes('value')).toBe('20');
    expect(inline.get('input[type="search"]').attributes('name')).toBe(
        'search',
    );
    expect(inline.get('input[name="archived"]').attributes('value')).toBe('1');
    const dialog = wrapper.get('[role="dialog"]');
    expect(
        dialog.findAll('select').map((select) => select.attributes('name')),
    ).toEqual([
        'important',
        'verified',
        'from_api',
        'registration_completed',
        'activity_status',
    ]);
    expect(dialog.find('input[name="archived"]').exists()).toBe(false);
    expect(dialog.find('input[name="search"]').exists()).toBe(false);
    // Important has no "all" option and defaults to Yes.
    expect(
        dialog.findAll('select[name="important"] option').map((o) => o.text()),
    ).toEqual([t('store.customers.yes'), t('store.customers.no')]);
    expect(
        (dialog.get('select[name="important"]').element as HTMLSelectElement)
            .value,
    ).toBe('1');
});
it('counts only the applied dialog filters, and Important only when it is No', () => {
    const badge = (filters: Record<string, unknown>) => {
        pagePermissions(['view_all_customers']);

        return mount(Index, {
            props: {
                customers: paginated([customer]),
                filters: {
                    per_page: 20,
                    search: 'Amy',
                    archived: true,
                    ...filters,
                },
                representatives: false,
                packages: [],
            },
        })
            .get('form#store-list-filters > button[type="button"]')
            .text();
    };
    expect(badge({ important: true })).not.toMatch(/\d/);
    expect(badge({ important: false })).toContain('1');
    expect(
        badge({
            important: false,
            verified: false,
            from_api: true,
            registration_completed: true,
            activity_status: 'new_user',
        }),
    ).toContain('5');
});
it('the representatives list uses the same filter bar with only the profit range in its dialog', () => {
    const wrapper = indexPage(['view_all_customers', 'delete_customer'], true);
    expect(wrapper.getComponent(StoreListFilters).props('action')).toEqual(
        repIndex(),
    );
    const form = wrapper.get('form#store-list-filters');
    expect(form.find('input[name="search"]').exists()).toBe(true);
    expect(form.find('input[name="archived"]').exists()).toBe(true);
    expect(form.get('input[name="per_page"]').attributes('value')).toBe('20');
    expect(wrapper.get('[role="dialog"]').find('select').exists()).toBe(false);
    expect(
        wrapper
            .get('[role="dialog"]')
            .findAll('input')
            .map((input) => input.attributes('name')),
    ).toEqual(['profit_from', 'profit_to']);
});
function listPage(
    filters: Record<string, unknown> = {},
    rows: Partial<StoreCustomer>[] = [{}],
    permissions: string[] = ['view_all_customers', 'edit_customer'],
    representatives = false,
) {
    pagePermissions(permissions);

    return mount(Index, {
        props: {
            customers: paginated(rows.map((row) => ({ ...customer, ...row }))),
            filters: { per_page: 20, ...filters },
            representatives,
            packages: [],
        },
    });
}
/** The column chooser is one of several dialogs kept in the page; pick it by its title. */
const columnsDialog = (wrapper: ReturnType<typeof listPage>) =>
    wrapper
        .findAll('[role="dialog"]')
        .find((item) => item.text().includes(t('store.table.columnsTitle')))!;
const headers = (wrapper: ReturnType<typeof listPage>) =>
    wrapper.findAll('th').map((th) => th.text());
const defaultHeaders = [
    t('store.customers.select'),
    t('store.customers.fields.id'),
    t('store.customers.fields.name'),
    t('store.customers.fields.company_name'),
    t('store.customers.fields.email'),
    t('store.customers.fields.phone'),
    t('store.customers.profits'),
    t('store.customers.status'),
    t('store.customers.actions'),
];
it('shows the default columns and keeps every other property out of the table', () => {
    const wrapper = listPage({}, [{ profits: 1234.5, country: 'Netherlands' }]);
    expect(headers(wrapper)).toEqual(defaultHeaders);
    expect(wrapper.text()).toContain(t('store.table.columns'));
    expect(wrapper.text()).toContain('1,234.50');
    expect(wrapper.text()).not.toContain('Netherlands');
    // The company moved out of the name cell into its own column.
    const cells = wrapper.findAll('tbody tr:first-child td');
    expect(cells[1].text()).toBe('17');
    expect(cells[2].text()).toBe('Amy');
    expect(cells[3].text()).toBe('Company');
});
it('the column chooser toggles, remembers, shows all and resets columns', async () => {
    const wrapper = listPage({}, [
        {
            profits: 5,
            country: 'Netherlands',
            tax_number: 'VAT',
            balance: '9.00',
            registration_completed: true,
            activity_status: 'has_been_contacted',
            created_at: '2026-11-01T12:00:00+00:00',
            last_order_at: '2026-10-30',
            company_address: 'Long Road 1',
        },
    ]);
    await wrapper
        .findAll('button')
        .find((button) => button.text() === t('store.table.columns'))!
        .trigger('click');
    const dialog = columnsDialog(wrapper);
    expect(dialog.exists()).toBe(true);
    const box = (label: string) =>
        dialog
            .findAll('label')
            .find((item) => item.text() === label)!
            .get('input');
    expect(
        (box(t('store.customers.fields.name')).element as HTMLInputElement)
            .checked,
    ).toBe(true);
    expect(
        (box(t('store.customers.fields.country')).element as HTMLInputElement)
            .checked,
    ).toBe(false);
    await box(t('store.customers.fields.country')).setValue(true);
    expect(headers(wrapper)).toContain(t('store.customers.fields.country'));
    expect(wrapper.get('tbody').text()).toContain('Netherlands');
    expect(
        JSON.parse(
            window.localStorage.getItem(
                'store.tableColumns.7.customers.customers',
            )!,
        ),
    ).toContain('country');
    await box(t('store.customers.fields.email')).setValue(false);
    expect(headers(wrapper)).not.toContain(t('store.customers.fields.email'));

    await dialog
        .findAll('button')
        .find((button) => button.text() === t('store.table.showAll'))!
        .trigger('click');
    expect(headers(wrapper)).toEqual(
        expect.arrayContaining([
            t('store.customers.fields.tax_number'),
            t('store.customers.fields.balance'),
            t('store.customers.fields.registration_completed'),
            t('store.customers.fields.created_at'),
        ]),
    );
    const body = wrapper.get('tbody').text();
    expect(body).toContain('VAT');
    expect(body).toContain('9.00');
    expect(body).toContain(t('store.customers.yes'));
    expect(body).toContain(t('store.customers.contacted'));
    expect(body).toContain('Long Road 1');
    expect(body).toContain('Nov 1, 2026');
    expect(body).toContain('Oct 30, 2026');

    await dialog
        .findAll('button')
        .find((button) => button.text() === t('store.table.reset'))!
        .trigger('click');
    expect(headers(wrapper)).toEqual(defaultHeaders);
    wrapper.unmount();
});
it('restores a remembered column choice and survives unreadable storage', () => {
    window.localStorage.setItem(
        'store.tableColumns.7.customers.customers',
        JSON.stringify(['name', 'city', 'unknown']),
    );
    const wrapper = listPage();
    expect(headers(wrapper)).toEqual([
        t('store.customers.select'),
        t('store.customers.fields.name'),
        t('store.customers.fields.city'),
        t('store.customers.actions'),
    ]);
    wrapper.unmount();
    window.localStorage.setItem(
        'store.tableColumns.7.customers.customers',
        '{oops',
    );
    expect(headers(listPage())).toEqual(defaultHeaders);
    const spy = vi
        .spyOn(Storage.prototype, 'getItem')
        .mockImplementation((key: string) => {
            if (key.startsWith('store.tableColumns')) {
                throw new Error('blocked');
            }

            return null;
        });
    expect(headers(listPage())).toEqual(defaultHeaders);
    spy.mockRestore();
});
it('keeps the name column locked and asks for it again when an old choice left it out', async () => {
    window.localStorage.setItem(
        'store.tableColumns.7.customers.customers',
        JSON.stringify(['city']),
    );
    const wrapper = listPage();
    expect(headers(wrapper)).toEqual([
        t('store.customers.select'),
        t('store.customers.fields.name'),
        t('store.customers.fields.city'),
        t('store.customers.actions'),
    ]);
    await wrapper
        .findAll('button')
        .find((button) => button.text() === t('store.table.columns'))!
        .trigger('click');
    const name = columnsDialog(wrapper)
        .findAll('label')
        .find((item) => item.text() === t('store.customers.fields.name'))!
        .get('input');
    expect((name.element as HTMLInputElement).checked).toBe(true);
    expect(name.attributes('disabled')).toBeDefined();
    wrapper.unmount();
});
it('carries over a choice stored under the old per-list key, once', () => {
    window.localStorage.setItem(
        'store.customers.columns.customers',
        JSON.stringify(['id', 'country']),
    );
    window.localStorage.setItem(
        'store.customers.columns.representatives',
        JSON.stringify(['id', 'rep_serial']),
    );
    const wrapper = listPage();
    expect(headers(wrapper)).toEqual([
        t('store.customers.select'),
        t('store.customers.fields.id'),
        t('store.customers.fields.name'),
        t('store.customers.fields.country'),
        t('store.customers.actions'),
    ]);
    expect(
        JSON.parse(
            window.localStorage.getItem(
                'store.tableColumns.7.customers.customers',
            )!,
        ),
    ).toEqual(['id', 'name', 'country']);
    // The representatives list keeps its own choice.
    expect(
        window.localStorage.getItem(
            'store.tableColumns.7.customers.representatives',
        ),
    ).toBeNull();
    wrapper.unmount();
    const reps = listPage({}, [{}], ['view_all_customers'], true);
    expect(headers(reps)).toContain(t('store.customers.fields.rep_serial'));
    reps.unmount();
});
it('restricted columns are offered only to staff who may edit customers', async () => {
    const wrapper = listPage({}, [{}], ['view_all_customers']);
    await wrapper
        .findAll('button')
        .find((button) => button.text() === t('store.table.columns'))!
        .trigger('click');
    const labels = columnsDialog(wrapper)
        .findAll('label')
        .map((item) => item.text());

    for (const key of ['tax_number', 'bank_account_number', 'balance']) {
        expect(labels).not.toContain(t(`store.customers.fields.${key}`));
    }

    expect(labels).not.toContain(t('store.customers.tradeLicense'));
    expect(labels).toContain(t('store.customers.fields.country'));
    expect(labels).not.toContain(
        t('store.customers.fields.rep_discount_percentage'),
    );
    wrapper.unmount();
});
it('the representatives list shows the package column with its assign button and its own extra column', async () => {
    const wrapper = listPage(
        {},
        [{ package: { id: 2, name: 'Gold', percentage: '10' } }],
        ['view_all_customers', 'assign_representative_package'],
        true,
    );
    expect(headers(wrapper)).toContain(t('store.customers.package'));
    expect(wrapper.get('tbody').text()).toContain('Gold');
    expect(wrapper.get('tbody').text()).toContain(
        t('store.customers.assignPackage'),
    );
    await wrapper
        .findAll('button')
        .find((button) => button.text() === t('store.table.columns'))!
        .trigger('click');
    expect(columnsDialog(wrapper).text()).toContain(
        t('store.customers.fields.rep_discount_percentage'),
    );
    wrapper.unmount();
});
it('puts the profit range in the filters dialog as two optional datetime fields', () => {
    const wrapper = listPage({
        profit_from: '2026-11-01T14:00',
        profit_to: '2026-11-03T09:30',
    });
    const dialog = wrapper.get('[role="dialog"]');
    const from = dialog.get('input[name="profit_from"]');
    const to = dialog.get('input[name="profit_to"]');
    expect(from.attributes('type')).toBe('datetime-local');
    expect(to.attributes('type')).toBe('datetime-local');
    expect(from.attributes('value')).toBe('2026-11-01T14:00');
    expect(to.attributes('value')).toBe('2026-11-03T09:30');
    expect(from.attributes('max')).toBe('2026-11-03T09:30');
    expect(to.attributes('min')).toBe('2026-11-01T14:00');
    expect(from.attributes('required')).toBeUndefined();
    expect(to.attributes('required')).toBeUndefined();
    expect(dialog.findComponent(RequiredMark).exists()).toBe(false);
    expect(dialog.text()).toContain(t('store.customers.profitFrom'));
    expect(dialog.text()).toContain(t('store.customers.profitTo'));
    expect(dialog.text()).toContain(t('store.customers.profitRangeHint'));
    expect(
        wrapper
            .find('form#store-list-filters input[name="profit_from"]')
            .exists(),
    ).toBe(true);
});
it('counts the profit range once and shows its indicator, header and reset link', () => {
    const badge = (wrapper: ReturnType<typeof listPage>) =>
        wrapper.get('form#store-list-filters > button[type="button"]').text();
    expect(badge(listPage())).not.toMatch(/\d/);
    expect(badge(listPage({ profit_from: '2026-11-01T14:00' }))).toContain('1');
    expect(
        badge(
            listPage({
                important: false,
                profit_from: '2026-11-01T14:00',
                profit_to: '2026-11-03T09:00',
            }),
        ),
    ).toContain('2');
    const chipsOf = (wrapper: ReturnType<typeof listPage>) =>
        wrapper.getComponent(StoreListFilters).props('chips');
    expect(chipsOf(listPage())).toEqual([]);
    expect(
        chipsOf(
            listPage({
                important: false,
                profit_from: '2026-11-01T14:00',
                profit_to: '2026-11-03T09:00',
            }),
        ),
    ).toEqual([
        {
            key: 'important',
            label: t('store.customers.filterLabels.important'),
            value: t('store.customers.no'),
            keys: ['important'],
        },
        {
            key: 'profit',
            label: t('store.customers.profitRangeActive', {
                from: '2026-11-01 14:00',
                to: '2026-11-03 09:00',
            }),
            keys: ['profit_from', 'profit_to'],
        },
    ]);
    expect(listPage().find('[role="status"]').exists()).toBe(false);
    expect(headers(listPage())).toContain(t('store.customers.profits'));

    const wrapper = listPage({
        search: 'Amy',
        archived: true,
        verified: true,
        profit_from: '2026-11-01T14:00',
        profit_to: '2026-11-03T09:00',
    });
    const status = wrapper.get('[role="status"]');
    expect(status.text()).toContain(
        t('store.customers.profitRangeActive', {
            from: '2026-11-01 14:00',
            to: '2026-11-03 09:00',
        }),
    );
    expect(headers(wrapper)).toContain(t('store.customers.profitsRange'));
    expect(headers(wrapper)).not.toContain(t('store.customers.profits'));
    expect(status.getComponent(Link).props('href')).toEqual(
        customersIndex({
            query: { per_page: 20, search: 'Amy', archived: 1, verified: 1 },
        }),
    );
    expect(status.text()).toContain(t('store.customers.profitRangeReset'));
    expect(
        listPage({ profit_to: '2026-11-03T09:00' })
            .get('[role="status"]')
            .text(),
    ).toContain(
        t('store.customers.profitRangeToOnly', { to: '2026-11-03 09:00' }),
    );
    expect(
        listPage({ profit_from: '2026-11-01T14:00' })
            .get('[role="status"]')
            .text(),
    ).toContain(
        t('store.customers.profitRangeFromOnly', { from: '2026-11-01 14:00' }),
    );
    const reps = listPage(
        { profit_from: '2026-11-01T14:00' },
        [{}],
        ['view_all_customers'],
        true,
    );
    expect(
        reps.get('[role="status"]').getComponent(Link).props('href'),
    ).toEqual(repIndex({ query: { per_page: 20 } }));
});
it('page size keeps the profit range', () => {
    const wrapper = listPage({
        profit_from: '2026-11-01T14:00',
        profit_to: '2026-11-03T09:00',
    });
    wrapper.getComponent(Pagination).vm.$emit('update:perPage', 50);
    expect(routerGetMock).toHaveBeenCalledWith(
        customersIndex().url,
        {
            per_page: 50,
            profit_from: '2026-11-01T14:00',
            profit_to: '2026-11-03T09:00',
        },
        { preserveState: true, replace: true },
    );
});
it('views page renders count labels and paginator contract', () => {
    const wrapper = mount(Views, {
        props: {
            customer,
            views: paginated([
                {
                    id: 5,
                    kind: 'products',
                    name: '<img onerror=alert()>',
                    slug: null,
                    public_url: '/temp/product/~id-5?catalog_id=5',
                    view_count: 3,
                    last_viewed_at: '2026-10-08 12:00:00',
                },
            ]),
            filters: { kind: null, per_page: 20 },
        },
    });

    for (const key of ['kind', 'viewCount', 'lastViewed']) {
        expect(wrapper.text()).toContain(t(`store.customers.${key}`));
    }

    expect(wrapper.find('td img').exists()).toBe(false);
    expect(wrapper.text()).toContain('<img onerror=alert()>');
    expect(wrapper.getComponent(Pagination).props('perPage')).toBe(20);
});

it('activity and new/edit address forms each show marks and their own legend; notes stay optional', async () => {
    const wrapper = mount(Edit, {
        props: { customer, countries, addresses: [] },
    });
    await wrapper.vm.$nextTick();
    const activity = wrapper.get('#new_status');
    expect(activity.attributes('required')).toBeDefined();
    expect(
        wrapper
            .get('label[for="new_status"]')
            .findComponent(RequiredMark)
            .exists(),
    ).toBe(true);
    expect(activity.element.closest('form')?.textContent).toContain(
        t('common.requiredLegend'),
    );
    const notes = wrapper.get('#admin_notes');
    expect(notes.attributes('required')).toBeUndefined();
    expect(notes.element.closest('form')?.textContent).not.toContain(
        t('common.requiredLegend'),
    );
    await wrapper
        .findAll('button')
        .find((button) => button.text() === t('store.customers.addAddress'))!
        .trigger('click');
    await wrapper.vm.$nextTick();
    expect(
        wrapper.get('#country_id').element.closest('form')?.textContent,
    ).toContain(t('common.requiredLegend'));
    wrapper.unmount();
});

it.each([
    [null, '/temp/product/~id-5?catalog_id=5'],
    ['', '/temp/product/~id-5?catalog_id=5'],
    ['normal-product', '/temp/product/normal-product'],
    ['0', '/temp/product/0'],
])('view rows use the complete server URL for slug %s', (slug, public_url) => {
    const wrapper = mount(Views, {
        props: {
            customer,
            views: paginated([
                {
                    id: 5,
                    kind: 'products',
                    name: 'Viewed product',
                    slug,
                    public_url,
                    view_count: 1,
                    last_viewed_at: '2026-10-25 12:00:00',
                },
            ]),
            filters: { kind: null, per_page: 20 },
        },
    });
    expect(wrapper.get('td a').attributes('href')).toBe(public_url);
    wrapper.unmount();
});
