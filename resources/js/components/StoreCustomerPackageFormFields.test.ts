import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { i18n, t } from '@/lib/i18n';
import {
    storeCustomerPackage,
    storeSharedProps,
    storeUpload,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import StoreCustomerPackageFormFields from './StoreCustomerPackageFormFields.vue';
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub } = await import('@/testing/inertiaStubs');

    return {
        Link: createLinkStub(),
        usePage: () => ({
            url: '/',
            props: defaultAuthProps({
                store: storeSharedProps({ permissions: [] }),
            }),
        }),
        useHttp: () => ({ processing: false, get: vi.fn(), cancel: vi.fn() }),
    };
});
describe('customer package fields', () => {
    afterEach(() => {
        i18n.global.locale.value = 'en';
    });
    it.each(['en', 'ar'] as const)(
        'renders every label, value and constraint in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mount(StoreCustomerPackageFormFields, {
                props: {
                    package: storeCustomerPackage(),
                    name: 'ذهبية',
                    initialFiles: [storeUpload()],
                    errors: {},
                },
            });

            for (const key of ['name', 'amount', 'uploads', 'logo']) {
                expect(wrapper.text()).toContain(
                    t(`store.customerPackages.${key}`),
                );
            }

            expect(wrapper.get('#name').attributes()).toMatchObject({
                required: '',
                maxlength: '50',
                value: 'ذهبية',
            });
            expect(wrapper.get('#amount').attributes()).toMatchObject({
                required: '',
                type: 'number',
                min: '0',
                max: '999999999999999999.99',
                step: '0.01',
                value: '12.50',
            });
            expect(wrapper.get('#product_upload').attributes()).toMatchObject({
                required: '',
                type: 'number',
                min: '0',
                max: '2147483647',
                step: '1',
                value: '10',
            });
            expect(
                wrapper.getComponent(StoreUploadField).props(),
            ).toMatchObject({
                name: 'logo',
                type: 'image',
                multiple: false,
                modelValue: '4',
                initialFiles: [storeUpload()],
            });
        },
    );
    it('shows backend errors, preserves zero values and updates the canonical logo model', async () => {
        const wrapper = mount(StoreCustomerPackageFormFields, {
            props: {
                package: storeCustomerPackage({
                    amount: '0',
                    product_upload: 0,
                }),
                errors: {
                    name: 'Name error',
                    amount: 'Amount error',
                    product_upload: 'Upload error',
                    logo: 'Logo error',
                },
            },
        });

        for (const text of [
            'Name error',
            'Amount error',
            'Upload error',
            'Logo error',
        ]) {
            expect(wrapper.text()).toContain(text);
        }

        expect(wrapper.get('#amount').attributes('value')).toBe('0');
        expect(wrapper.get('#product_upload').attributes('value')).toBe('0');
        wrapper
            .getComponent(StoreUploadField)
            .vm.$emit('update:modelValue', '9');
        await wrapper.vm.$nextTick();
        expect(wrapper.getComponent(StoreUploadField).props('modelValue')).toBe(
            '9',
        );
    });
});
