import { Form, Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    index,
    updateStatus,
    edit,
    destroy,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/TaxController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import Pagination from '@/components/Pagination.vue';
import StoreStatusButton from '@/components/StoreStatusButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { paginated, storeTax, storeSharedProps } from '@/testing/factories';
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

const filters = { per_page: 50 };
function mountPage(permissions = ['vat_&_tax_setup'], rows = [storeTax()]) {
    usePageMock.mockReturnValue({
        url: index.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, { props: { taxes: paginated(rows), filters } });
}
describe('Store Taxes Index', () => {
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
            t('store.taxes.title'),
        );
        expect(wrapper.get('h1').text()).toBe(t('store.taxes.title'));
        expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual([
            t('store.reference.name'),
            t('store.reference.status'),
            t('store.reference.actions'),
        ]);
        expect(wrapper.get('tbody').text()).toContain(storeTax().name);
    });
    it('shows an empty result message', () => {
        expect(mountPage(['vat_&_tax_setup'], []).text()).toContain(
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
    it('posts the desired status using the bound route and current query', () => {
        const button = mountPage().getComponent(StoreStatusButton);
        expect(button.props()).toMatchObject({
            action: updateStatus(storeTax(), { mergeQuery: {} }),
            active: true,
            label: storeTax().name,
        });
    });
    it('links edit and confirms delete without losing the query', () => {
        const wrapper = mountPage();
        const links = wrapper.findAllComponents(TableActionLink);
        expect(links[0].props('href')).toEqual(edit(storeTax()));
        expect(links[1].props()).toMatchObject({
            href: destroy(storeTax(), { mergeQuery: {} }),
            variant: 'danger',
        });
        expect(links[1].attributes('method')).toBe('delete');
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const before = links[1]
            .getComponent(Link)
            .props('onBefore') as () => boolean;
        expect(before()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', { label: storeTax().name }),
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
