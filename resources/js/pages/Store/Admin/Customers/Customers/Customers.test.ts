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
    expect(wrapper.get('[role="dialog"]').text()).toContain('Gold (10%)');
    expect(
        wrapper
            .get('label[for="package_id"]')
            .findComponent(RequiredMark)
            .exists(),
    ).toBe(true);
    expect(
        wrapper.get('#package_id').element.closest('form')?.textContent,
    ).toContain(t('common.requiredLegend'));
    const form = wrapper.getComponent(Form);
    expect(form.props('action')).toEqual(assign());
    expect(wrapper.get('input[name="user_id"]').attributes('value')).toBe('17');
    expect(
        wrapper.get('select[name="package_id"]').attributes('required'),
    ).toBeDefined();
    form.vm.$emit('success');
    await wrapper.vm.$nextTick();
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
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
it('the representatives list has a plain search and archived form without a dialog', () => {
    const wrapper = indexPage(['view_all_customers', 'delete_customer'], true);
    expect(wrapper.findComponent(StoreListFilters).exists()).toBe(false);
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    const form = wrapper.getComponent(Form);
    expect(form.props('action')).toEqual(repIndex());
    expect(form.find('input[name="search"]').exists()).toBe(true);
    expect(form.find('input[name="archived"]').exists()).toBe(true);
    expect(form.get('input[name="per_page"]').attributes('value')).toBe('20');
    expect(wrapper.find('select').exists()).toBe(false);
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
