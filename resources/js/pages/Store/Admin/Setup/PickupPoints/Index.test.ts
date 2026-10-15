import { Form, Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    index,
    edit,
    destroy,
    create,
} from '@/actions/App/Http/Controllers/Store/Admin/PickupPointController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import Pagination from '@/components/Pagination.vue';
import StoreStatusButton from '@/components/StoreStatusButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import {
    paginated,
    storePickupPoint,
    storeSharedProps,
} from '@/testing/factories';
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
        useForm: createUseFormStub(submitMock),
        usePage: usePageMock,
        router: { get: routerGetMock, post: routerPostMock },
    };
});

const filters = { per_page: 50, search: 'Ware' };
function mountPage(
    permissions = ['pickup_point_setup'],
    rows = [storePickupPoint()],
) {
    usePageMock.mockReturnValue({
        url: index.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, { props: { pickupPoints: paginated(rows), filters } });
}
describe('Store PickupPoints Index', () => {
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
            t('store.pickupPoints.title'),
        );
        expect(wrapper.get('h1').text()).toBe(t('store.pickupPoints.title'));
        expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual([
            t('store.reference.name'),
            t('store.pickupPoints.address'),
            t('store.pickupPoints.phone'),
            t('store.pickupPoints.manager'),
            t('store.reference.status'),
            t('store.reference.actions'),
        ]);
        expect(wrapper.get('tbody').text()).toContain(storePickupPoint().name);
    });
    it('shows an empty result message', () => {
        expect(mountPage(['pickup_point_setup'], []).text()).toContain(
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
    it('submits the filter with the selected page size', async () => {
        const wrapper = mountPage();
        await wrapper.get('#search').setValue('changed');
        await wrapper.get('form').trigger('submit');
        expect(routerGetMock).toHaveBeenCalledWith(
            index.url(),
            { search: 'changed', per_page: 50 },
            { preserveState: true, replace: true },
        );
    });
    it('links edit and confirms delete without losing the query', () => {
        const wrapper = mountPage();
        const links = wrapper.findAllComponents(TableActionLink);
        expect(links[0].props('href')).toEqual(edit(storePickupPoint()));
        expect(links[1].props()).toMatchObject({
            href: destroy(storePickupPoint(), { mergeQuery: {} }),
            variant: 'danger',
        });
        expect(links[1].attributes('method')).toBe('delete');
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const before = links[1]
            .getComponent(Link)
            .props('onBefore') as () => boolean;
        expect(before()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', { label: storePickupPoint().name }),
        );
    });
    it('opens the create route', () => {
        expect(mountPage().getComponent(AddResourceLink).props('href')).toEqual(
            create(),
        );
    });
});
