import { Form, Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    index,
    edit,
    destroy,
    create,
    updateStatus,
} from '@/actions/App/Http/Controllers/Store/Admin/CarrierController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import Pagination from '@/components/Pagination.vue';
import StoreStatusButton from '@/components/StoreStatusButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { paginated, storeCarrier, storeSharedProps } from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import Index from './Index.vue';
const {
    usePageMock,
    routerGetMock,
    routerPostMock,
    formSlotPropsMock,
    submitMock,
} = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
    routerPostMock: vi.fn(),
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
        router: { get: routerGetMock, post: routerPostMock },
    };
});

const filters = { per_page: 50, search: 'Parcel' };
function mountPage(permissions = ['manage_carriers'], rows = [storeCarrier()]) {
    usePageMock.mockReturnValue({
        url: index.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, { props: { carriers: paginated(rows), filters } });
}
describe('Store Carriers Index', () => {
    beforeEach(() => {
        resetMocks({
            usePageMock,
            routerGetMock,
            routerPostMock,
            formSlotPropsMock,
            submitMock,
        });
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });
    afterEach(() => vi.restoreAllMocks());
    it('renders the store layout, localized title and every table column', () => {
        const wrapper = mountPage();
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('store.carriers.title'),
        );
        expect(wrapper.get('h1').text()).toBe(t('store.carriers.title'));
        expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual([
            t('store.carriers.name'),
            t('store.carriers.transitTime'),
            t('store.carriers.freeShipping'),
            t('store.reference.status'),
            t('store.reference.actions'),
        ]);
        expect(wrapper.get('tbody').text()).toContain(storeCarrier().name);
    });
    it('renders the localized filter button and status action with query context', async () => {
        const wrapper = mountPage();
        expect(wrapper.get('form button[type=submit]').text()).toBe(
            t('common.filters.apply'),
        );
        expect(wrapper.getComponent(StoreStatusButton).props()).toMatchObject({
            active: true,
            label: 'Parcel',
            action: updateStatus({ mergeQuery: { id: 1 } }),
        });
        await wrapper.get('#search').setValue('Changed');
        await wrapper.get('form').trigger('submit');
        expect(routerGetMock).toHaveBeenCalledWith(
            index.url(),
            { search: 'Changed', per_page: 50 },
            { preserveState: true, replace: true },
        );
    });
    it('shows an empty result message', () => {
        expect(mountPage(['manage_carriers'], []).text()).toContain(
            t('store.reference.empty'),
        );
    });
    it('keeps filters when changing page size', () => {
        const wrapper = mountPage();
        const pagination = wrapper.getComponent(Pagination);
        expect(pagination.props()).toMatchObject({ perPage: 50, links: [] });
        pagination.vm.$emit('update:perPage', 10);
        expect(routerGetMock).toHaveBeenCalledWith(
            index.url(),
            { ...filters, per_page: 10 },
            { preserveState: true, replace: true },
        );
    });
    it('hides all write controls without permission', () => {
        const wrapper = mountPage([]);
        expect(wrapper.findAllComponents(TableActionLink)).toHaveLength(0);
        expect(wrapper.findComponent(AddResourceLink).exists()).toBe(false);
        expect(wrapper.findComponent(StoreStatusButton).exists()).toBe(false);
        expect(wrapper.findComponent(Form).exists()).toBe(false);
    });
    it('links edit and confirms delete without losing the query', () => {
        const wrapper = mountPage();
        const links = wrapper.findAllComponents(TableActionLink);
        expect(links[0].props('href')).toEqual(edit(storeCarrier()));
        expect(links[1].props()).toMatchObject({
            href: destroy(storeCarrier(), { mergeQuery: {} }),
            variant: 'danger',
        });
        expect(links[1].attributes('method')).toBe('delete');
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const before = links[1]
            .getComponent(Link)
            .props('onBefore') as () => boolean;
        expect(before()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', { label: storeCarrier().name }),
        );
    });
    it('opens the create route', () => {
        expect(mountPage().getComponent(AddResourceLink).props('href')).toEqual(
            create(),
        );
    });
});
