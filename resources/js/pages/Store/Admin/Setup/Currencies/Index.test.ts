import { Form, Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    index,
    updateStatus,
    edit,
    configure,
} from '@/actions/App/Http/Controllers/Store/Admin/CurrencyController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import Pagination from '@/components/Pagination.vue';
import StoreCurrencyDialog from '@/components/StoreCurrencyDialog.vue';
import StoreStatusButton from '@/components/StoreStatusButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import {
    paginated,
    storeCurrency,
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

const filters = { per_page: 50, search: 'Euro' };
function mountPage(permissions = ['currency_setup'], rows = [storeCurrency()]) {
    usePageMock.mockReturnValue({
        url: index.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, {
        props: {
            currencies: paginated(rows),
            filters,
            activeCurrencies: [{ id: 18, name: 'Euro' }],
            settings: {
                system_default_currency: 18,
                symbol_format: 1,
                decimal_separator: 1,
                no_of_decimals: 2,
            },
            dialog: null,
            selectedCurrency: null,
        },
    });
}
describe('Store Currencies Index', () => {
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
            t('store.currencies.title'),
        );
        expect(wrapper.get('h1').text()).toBe(t('store.currencies.title'));
        expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual([
            t('store.reference.name'),
            t('store.currencies.symbol'),
            t('store.currencies.code'),
            t('store.currencies.rate'),
            t('store.reference.status'),
            t('store.reference.actions'),
        ]);
        expect(wrapper.get('tbody').text()).toContain(storeCurrency().name);
    });
    it('shows an empty result message', () => {
        expect(mountPage(['currency_setup'], []).text()).toContain(
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
    it('posts the desired status using the bound route and current query', () => {
        const button = mountPage().getComponent(StoreStatusButton);
        expect(button.props()).toMatchObject({
            action: updateStatus(storeCurrency(), { mergeQuery: {} }),
            active: true,
            label: storeCurrency().name,
        });
    });
    it('opens Vue create and edit dialogs through canceled Inertia visits', async () => {
        const wrapper = mountPage();
        const add = wrapper
            .getComponent(AddResourceLink)
            .getComponent(Link)
            .props('onBefore') as () => boolean;
        expect(add()).toBe(false);
        await wrapper.vm.$nextTick();
        expect(wrapper.getComponent(StoreCurrencyDialog).props()).toMatchObject(
            { open: true, currency: null },
        );
        wrapper
            .getComponent(StoreCurrencyDialog)
            .vm.$emit('update:open', false);
        await wrapper.vm.$nextTick();
        const editLink = wrapper.getComponent(TableActionLink);
        expect(editLink.props('href')).toEqual(edit(storeCurrency()));
        expect(
            (editLink.getComponent(Link).props('onBefore') as () => boolean)(),
        ).toBe(false);
        await wrapper.vm.$nextTick();
        expect(wrapper.getComponent(StoreCurrencyDialog).props()).toMatchObject(
            { open: true, currency: storeCurrency() },
        );
        wrapper.unmount();
    });
    it('keeps the default currency active and separates the two settings forms', () => {
        const wrapper = mountPage();

        for (const key of [
            'defaultCurrency',
            'formats',
            'symbolFormat',
            'symbolFirst',
            'amountFirst',
            'symbolFirstSpace',
            'amountFirstSpace',
            'decimalSeparator',
            'decimals',
        ]) {
            expect(wrapper.text()).toContain(t(`store.currencies.${key}`));
        }

        expect(wrapper.getComponent(StoreStatusButton).props('locked')).toBe(
            true,
        );
        expect(
            wrapper.findAllComponents(Form).map((form) => form.props('action')),
        ).toEqual([configure(), configure()]);
        expect(
            wrapper
                .get('#system_default_currency')
                .findAll('option')
                .map((option) => option.attributes('value')),
        ).toEqual(['', '18']);
        expect(wrapper.get('#symbol_format').findAll('option')).toHaveLength(5);
        expect(
            wrapper
                .get('#no_of_decimals')
                .findAll('option')
                .map((option) => option.attributes('value')),
        ).toEqual(['', '0', '1', '2', '3']);
    });
    it('allows an inactive default currency to be re-enabled', () => {
        const wrapper = mountPage(
            ['currency_setup'],
            [storeCurrency({ status: false })],
        );
        expect(wrapper.getComponent(StoreStatusButton).props('locked')).toBe(
            false,
        );
    });

    it('renders bookmarked create and edit routes as dialogs', async () => {
        const wrapper = mountPage();
        await wrapper.setProps({ dialog: 'create' });
        expect(wrapper.getComponent(StoreCurrencyDialog).props('open')).toBe(
            true,
        );
        await wrapper.setProps({
            dialog: 'edit',
            selectedCurrency: storeCurrency(),
        });
        expect(
            wrapper.getComponent(StoreCurrencyDialog).props('currency'),
        ).toEqual(storeCurrency());
        wrapper.unmount();
    });
});
