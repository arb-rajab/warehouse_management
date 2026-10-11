import { Form, Head } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    index,
    updateStatus,
} from '@/actions/App/Http/Controllers/Store/Admin/CountryController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import Pagination from '@/components/Pagination.vue';
import StoreStatusButton from '@/components/StoreStatusButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { paginated, storeCountry, storeSharedProps } from '@/testing/factories';
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

const filters = { per_page: 50, sort_country: 'Nether' };
function mountPage(
    permissions = ['shipping_country_setting'],
    rows = [storeCountry()],
) {
    usePageMock.mockReturnValue({
        url: index.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, { props: { countries: paginated(rows), filters } });
}
describe('Store Countries Index', () => {
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
            t('store.countries.title'),
        );
        expect(wrapper.get('h1').text()).toBe(t('store.countries.title'));
        expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual([
            t('store.reference.name'),
            t('store.countries.code'),
            t('store.reference.status'),
        ]);
        expect(wrapper.get('tbody').text()).toContain(storeCountry().name);
    });
    it('shows an empty result message', () => {
        expect(mountPage(['shipping_country_setting'], []).text()).toContain(
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
        await wrapper.get('#sort_country').setValue('changed');
        await wrapper.get('form').trigger('submit');
        expect(routerGetMock).toHaveBeenCalledWith(
            index.url(),
            { sort_country: 'changed', per_page: 50 },
            { preserveState: true, replace: true },
        );
    });
    it('posts the desired status using the bound route and current query', () => {
        const button = mountPage().getComponent(StoreStatusButton);
        expect(button.props()).toMatchObject({
            action: updateStatus(storeCountry(), { mergeQuery: {} }),
            active: true,
            label: storeCountry().name,
        });
    });
});
