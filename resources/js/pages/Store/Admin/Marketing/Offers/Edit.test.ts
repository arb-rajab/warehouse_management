import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import {
    index as campaignIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/OfferController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreOfferFormFields from '@/components/StoreOfferFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import {
    storeOffer,
    storeCampaignProduct,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Edit from './Edit.vue';
const { usePageMock } = vi.hoisted(() => ({ usePageMock: vi.fn() }));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Form: createFormStub(() => ({
            errors: { title: 'Invalid' },
            processing: false,
        })),
        Link: createLinkStub(),
        Head: headStub,
        usePage: usePageMock,
        router: { post: vi.fn() },
        useHttp: () => ({
            get: vi.fn().mockResolvedValue(undefined),
            errors: {},
            processing: false,
            q: '',
        }),
    };
});
beforeEach(() =>
    usePageMock.mockReturnValue({
        url: campaignIndex.url(),
        props: defaultAuthProps({ store: storeSharedProps() }),
    }),
);
it('fully mounts the store form with action, cancel route, labels and field props', () => {
    const record = storeOffer();
    const selectedProducts = [storeCampaignProduct()];
    const wrapper = mount(Edit, {
        props: {
            selectedProducts,
            offer: record,
            baseProductId: 12,
            baseQuantity: 2,
            products: [],
            selectedBrand: null,
        },
    });
    expect(wrapper.getComponent(Form).props('action')).toEqual(update(record));
    expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
        layout: StoreAdminLayout,
        wide: true,
        cancelHref: campaignIndex(),
        title: t('store.offers.edit.title'),
        submitLabel: t('store.offers.edit.submit'),
        submittingLabel: t('store.campaigns.saving'),
    });
    expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
    expect(wrapper.getComponent(StoreOfferFormFields).props()).toMatchObject({
        selectedProducts,
        errors: { title: 'Invalid' },
    });
    expect(wrapper.text()).toContain(t('store.offers.edit.title'));
});
