import { Form } from '@inertiajs/vue3';
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { updateSellerCommissionActivation } from '@/actions/App/Http/Controllers/BusinessSettingsController';
import { update } from '@/actions/App/Http/Controllers/Store/Admin/SellerCommissionController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreBooleanField from '@/components/StoreBooleanField.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import {
    storeSellerCommissionSettings,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Index from './Index.vue';

enableAutoUnmount(afterEach);

const { usePageMock, slotProps, http } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    slotProps: vi.fn(() => ({
        errors: {} as Record<string, string>,
        processing: false,
    })),
    http: {
        type: '',
        value: 0,
        processing: false,
        errors: {} as Record<string, string>,
        post: vi.fn(),
        cancel: vi.fn(),
    },
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Form: createFormStub(slotProps),
        Link: createLinkStub(),
        Head: headStub,
        usePage: usePageMock,
        useHttp: () => http,
        router: { post: vi.fn() },
    };
});
function access(
    permissions = ['seller_commission_configuration', 'features_activation'],
    vendor = true,
) {
    usePageMock.mockReturnValue({
        url: '/temp/admin/vendor_commission',
        props: defaultAuthProps({
            store: storeSharedProps({ permissions, isRaizerAdmin: vendor }),
        }),
    });
}
function mountPage() {
    return mount(Index, {
        props: { settings: storeSellerCommissionSettings() },
    });
}
beforeEach(() => {
    access();
    slotProps.mockReturnValue({ errors: {}, processing: false });
    http.processing = false;
    http.errors = {};
    http.cancel.mockReset();
    http.post.mockReset().mockResolvedValue('1');
});
afterEach(() => {
    i18n.global.locale.value = 'en';
});
it.each(['en', 'ar'] as const)(
    'full mounts commission settings, route, constraints and every label in %s',
    (locale) => {
        i18n.global.locale.value = locale;
        const wrapper = mountPage();
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
            title: t('store.sellers.commissionTitle'),
            action: update(),
            layout: StoreAdminLayout,
            submitLabel: t('store.sellers.save'),
            submittingLabel: t('store.sellers.saving'),
        });
        expect(
            wrapper
                .findAllComponents(Form)
                .find(
                    (form) =>
                        JSON.stringify(form.props('action')) ===
                        JSON.stringify(update()),
                )
                ?.props('action'),
        ).toEqual(update());
        expect(wrapper.get('#vendor_commission').attributes()).toMatchObject({
            type: 'number',
            required: '',
            min: '0',
            max: '100',
            step: '0.01',
        });
        expect(
            wrapper.get('#minimum_seller_amount_withdraw').attributes(),
        ).toMatchObject({
            type: 'number',
            required: '',
            min: '0',
            step: 'any',
        });
        expect(
            wrapper
                .findAll('input[name="types[]"]')
                .map((item) => item.attributes('value')),
        ).toEqual(['vendor_commission', 'minimum_seller_amount_withdraw']);
        expect(
            wrapper
                .findAllComponents(StoreBooleanField)
                .map((item) => item.props()),
        ).toEqual([
            {
                name: 'vendor_commission_activation',
                label: t('store.sellers.vendor_commission_activation'),
                value: true,
                error: undefined,
                disabled: false,
            },
            {
                name: 'category_wise_commission',
                label: t('store.sellers.category_wise_commission'),
                value: false,
                error: undefined,
                disabled: false,
            },
        ]);

        for (const key of [
            'commissionTitle',
            'commissionPercent',
            'minimumWithdrawal',
            'vendor_commission_activation',
            'category_wise_commission',
            'categoryCommissionNote',
            'save',
        ]) {
            expect(wrapper.text()).toContain(t(`store.sellers.${key}`));
        }

        expect(wrapper.text()).toContain(
            t('store.sellers.commissionNote', { percentage: '12.50' }),
        );
        wrapper.unmount();
    },
);
it('forwards field errors and processing state through the real form', () => {
    slotProps.mockReturnValue({
        errors: {
            vendor_commission: 'Invalid percent',
            minimum_seller_amount_withdraw: 'Invalid minimum',
            types: 'Invalid types',
        },
        processing: true,
    });
    const wrapper = mountPage();
    expect(wrapper.text()).toContain('Invalid percent');
    expect(wrapper.text()).toContain('Invalid minimum');
    expect(wrapper.text()).toContain('Invalid types');
    expect(wrapper.getComponent(SubmitButton).props('processing')).toBe(true);
});
it.each(['vendor_commission_activation', 'category_wise_commission'])(
    'posts the exact compatibility switch contract for %s',
    async (key) => {
        const wrapper = mountPage();
        await wrapper.get(`#${key}`).setValue('0');
        await flushPromises();
        expect(http.post).toHaveBeenCalledWith(
            updateSellerCommissionActivation.url(),
        );
        expect({ type: http.type, value: http.value }).toEqual({
            type: key,
            value: 0,
        });
        expect(wrapper.get('[role="status"]').text()).toBe(
            t('store.sellers.activationSaved'),
        );
        wrapper.unmount();
        expect(http.cancel).toHaveBeenCalled();
    },
);
it.each([false, true])(
    'keeps switches disabled when the vendor or activation gate is absent (%s)',
    (vendor) => {
        access(
            vendor
                ? ['seller_commission_configuration']
                : ['seller_commission_configuration', 'features_activation'],
            vendor,
        );
        const wrapper = mountPage();
        expect(
            wrapper
                .findAll('select')
                .every((item) => item.attributes('disabled') !== undefined),
        ).toBe(true);
        expect(wrapper.text()).toContain(
            t('store.sellers.activationRestricted'),
        );
    },
);
it('rolls a refused switch back and displays failure instead of claiming it saved', async () => {
    http.post.mockResolvedValue(undefined);
    const wrapper = mountPage();
    await wrapper.get('#category_wise_commission').setValue('1');
    await flushPromises();
    expect(
        (wrapper.get('#category_wise_commission').element as HTMLSelectElement)
            .value,
    ).toBe('0');
    expect(wrapper.text()).toContain(t('store.sellers.activationError'));
    expect(wrapper.find('[role="status"]').exists()).toBe(false);
});
it('handles network failure and exposes backend activation errors', async () => {
    http.errors = { value: 'Invalid switch' };
    http.post.mockRejectedValue(new Error('offline'));
    const wrapper = mountPage();
    await wrapper.get('#vendor_commission_activation').setValue('0');
    await flushPromises();
    expect(wrapper.text()).toContain('Invalid switch');
    expect(wrapper.text()).toContain(t('store.sellers.activationError'));
    expect(
        (
            wrapper.get('#vendor_commission_activation')
                .element as HTMLSelectElement
        ).value,
    ).toBe('1');
});
it('shows no form actions without the commission permission', () => {
    access([]);
    const wrapper = mountPage();
    expect(wrapper.findComponent(ResourceFormPage).exists()).toBe(false);
    expect(wrapper.find('#vendor_commission').exists()).toBe(false);
    expect(wrapper.find('#category_wise_commission').exists()).toBe(false);
});
