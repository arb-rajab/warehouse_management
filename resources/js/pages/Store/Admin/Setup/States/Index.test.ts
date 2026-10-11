import { Form, Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    index,
    updateStatus,
    edit,
    destroy,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/StateController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import Pagination from '@/components/Pagination.vue';
import StoreStatusButton from '@/components/StoreStatusButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { paginated, storeState, storeSharedProps } from '@/testing/factories';
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

const filters = { per_page: 50, sort_state: 'North', sort_country: 11 };
function mountPage(
    permissions = ['manage_shipping_states'],
    rows = [storeState()],
) {
    usePageMock.mockReturnValue({
        url: index.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, {
        props: {
            states: paginated(rows),
            filters,
            countries: [{ id: 11, name: 'Netherlands' }],
        },
    });
}
describe('Store States Index', () => {
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
            t('store.states.title'),
        );
        expect(wrapper.get('h1').text()).toBe(t('store.states.title'));
        expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual([
            t('store.reference.name'),
            t('store.reference.country'),
            t('store.reference.status'),
            t('store.reference.actions'),
        ]);
        expect(wrapper.get('tbody').text()).toContain(storeState().name);
    });
    it('shows an empty result message', () => {
        expect(mountPage(['manage_shipping_states'], []).text()).toContain(
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
        await wrapper.get('#sort_state').setValue('changed');
        await wrapper.get('form').trigger('submit');
        expect(routerGetMock).toHaveBeenCalledWith(
            index.url(),
            { sort_state: 'changed', sort_country: '11', per_page: 50 },
            { preserveState: true, replace: true },
        );
    });
    it('posts the desired status using the bound route and current query', () => {
        const button = mountPage().getComponent(StoreStatusButton);
        expect(button.props()).toMatchObject({
            action: updateStatus(storeState(), { mergeQuery: {} }),
            active: true,
            label: storeState().name,
        });
    });
    it('links edit and confirms delete without losing the query', () => {
        const wrapper = mountPage();
        const links = wrapper.findAllComponents(TableActionLink);
        expect(links[0].props('href')).toEqual(edit(storeState()));
        expect(links[1].props()).toMatchObject({
            href: destroy(storeState(), { mergeQuery: {} }),
            variant: 'danger',
        });
        expect(links[1].attributes('method')).toBe('delete');
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const before = links[1]
            .getComponent(Link)
            .props('onBefore') as () => boolean;
        expect(before()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', { label: storeState().name }),
        );
    });
    it('uses the established inline form and forwards validation errors', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { name: 'Name required' },
            processing: true,
        });
        const wrapper = mountPage();
        expect(wrapper.findAllComponents(Form).at(-1)?.props()).toMatchObject({
            action: store(),
            resetOnSuccess: true,
        });
        expect(wrapper.text()).toContain('Name required');
    });
});
