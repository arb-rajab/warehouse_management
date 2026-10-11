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
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreCustomerFormFields from '@/components/StoreCustomerFormFields.vue';
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
it('page size and search preserve list filters', async () => {
    const wrapper = indexPage();
    await wrapper.get('input[type="search"]').setValue('Amy');
    wrapper.getComponent(Pagination).vm.$emit('update:perPage', 10);
    expect(routerGetMock).toHaveBeenCalledWith(
        customersIndex().url,
        { search: 'Amy', archived: 0, per_page: 10, important: '1' },
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
