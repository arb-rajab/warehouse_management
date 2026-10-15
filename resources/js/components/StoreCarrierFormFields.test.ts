import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { i18n, t } from '@/lib/i18n';
import { storeCarrier, storeUpload } from '@/testing/factories';
import StoreCarrierFormFields from './StoreCarrierFormFields.vue';
vi.mock('@/components/StoreUploadField.vue', () => ({
    default: {
        props: [
            'name',
            'modelValue',
            'label',
            'type',
            'multiple',
            'initialFiles',
            'error',
        ],
        template: '<div>{{ label }} {{ error }}</div>',
    },
}));
const zones = [
    { id: 11, name: 'Europe' },
    { id: 12, name: 'North' },
];
function mountFields(
    carrier = storeCarrier(),
    errors: Record<string, string> = {},
) {
    return mount(StoreCarrierFormFields, {
        props: { carrier, zones, initialFiles: [storeUpload()], errors },
    });
}
describe('carrier form fields', () => {
    afterEach(() => {
        i18n.global.locale.value = 'en';
    });
    it.each(['en', 'ar'] as const)(
        'renders all field and tariff labels in %s with native constraints',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountFields();

            for (const key of [
                'name',
                'transitTime',
                'logo',
                'freeShipping',
                'billingType',
                'weightBased',
                'priceBased',
                'zones',
                'boundaries',
                'range',
                'lower',
                'upper',
                'price',
                'addRange',
                'removeRange',
            ]) {
                expect(wrapper.text()).toContain(t(`store.carriers.${key}`));
            }

            for (const id of ['carrier_name', 'transit_time']) {
                expect(wrapper.get(`#${id}`).attributes()).toMatchObject({
                    required: '',
                    maxlength: '255',
                });
            }

            expect(
                (wrapper.get('#carrier_name').element as HTMLInputElement)
                    .value,
            ).toBe('Parcel');
            expect(wrapper.get('#delimiter1-0').attributes()).toMatchObject({
                type: 'number',
                name: 'delimiter1[0]',
                required: '',
                step: '0.01',
            });
            expect(wrapper.get('#price-11-0').attributes()).toMatchObject({
                name: 'carrier_price[11][0]',
                required: '',
                step: '0.01',
                max: '999999.99',
            });
            expect(
                (wrapper.get('#price-11-1').element as HTMLInputElement).value,
            ).toBe('8');
            expect(
                wrapper.getComponent(StoreUploadField).props(),
            ).toMatchObject({
                name: 'logo',
                modelValue: '7',
                type: 'image',
                initialFiles: [storeUpload()],
            });
        },
    );
    it('compares large decimal boundaries without losing precision', async () => {
        const wrapper = mountFields(
            storeCarrier({
                ranges: [
                    {
                        billing_type: 'weight_based',
                        delimiter1: '9007199254740992',
                        delimiter2: '9007199254740993',
                        prices: { 11: '5' },
                    },
                ],
            }),
        );
        await nextTick();
        expect(
            (wrapper.get('#delimiter2-0').element as HTMLInputElement)
                .validationMessage,
        ).toBe('');
    });
    it('keeps paid tariff drafts when toggling free shipping and switching billing mode', async () => {
        const wrapper = mountFields();
        await wrapper.get('#billing_type').setValue('price_based');
        await wrapper.get('#shipping_type').setValue('1');
        expect(wrapper.find('#billing_type').exists()).toBe(false);
        expect(wrapper.find('input[name="zones[]"]').exists()).toBe(false);
        await wrapper.get('#shipping_type').setValue('0');
        expect(
            (wrapper.get('#billing_type').element as HTMLSelectElement).value,
        ).toBe('price_based');
        expect(
            (wrapper.get('#price-11-1').element as HTMLInputElement).value,
        ).toBe('8');
    });
    it('adds and removes ranges without shifting surviving prices or names', async () => {
        const wrapper = mountFields();
        await wrapper
            .findAll('button')
            .find((button) => button.text() === t('store.carriers.addRange'))!
            .trigger('click');
        expect(
            (wrapper.get('#delimiter1-2').element as HTMLInputElement).value,
        ).toBe('20');
        await wrapper
            .findAll('button')
            .find(
                (button) => button.text() === t('store.carriers.removeRange'),
            )!
            .trigger('click');
        expect(wrapper.find('#delimiter1-0').exists()).toBe(false);
        expect(wrapper.get('#price-11-1').attributes('name')).toBe(
            'carrier_price[11][0]',
        );
        expect(
            (wrapper.get('#price-11-1').element as HTMLInputElement).value,
        ).toBe('8');
    });
    it('selects only requested zone inputs and enforces contiguous increasing boundaries', async () => {
        const wrapper = mountFields();
        await wrapper.get('#delimiter1-1').setValue('11');
        await nextTick();
        expect(
            (wrapper.get('#delimiter1-1').element as HTMLInputElement)
                .validationMessage,
        ).toBe(t('store.carriers.contiguous'));
        await wrapper.get('#delimiter1-1').setValue('10');
        await wrapper.get('#delimiter2-1').setValue('10');
        await nextTick();
        expect(
            (wrapper.get('#delimiter2-1').element as HTMLInputElement)
                .validationMessage,
        ).toBe(t('store.carriers.increasing'));
        await wrapper.get('input[data-zone][value="12"]').setValue(false);
        expect(wrapper.find('#price-12-0').exists()).toBe(false);
        await wrapper.get('input[data-zone][value="11"]').setValue(false);
        await nextTick();
        expect(
            (wrapper.get('input[data-zone]').element as HTMLInputElement)
                .validationMessage,
        ).toBe(t('store.carriers.selectZone'));
    });
    it('renders nested server errors and retains dirty inputs after errors change', async () => {
        const wrapper = mountFields();
        await wrapper.get('#carrier_name').setValue('Unsaved');
        const errors = {
            carrier_name: 'Name error',
            transit_time: 'Transit error',
            logo: 'Logo error',
            shipping_type: 'Shipping error',
            billing_type: 'Billing error',
            zones: 'Zones error',
            'zones.0': 'Selected zone was removed',
            delimiter1: 'Lower array error',
            delimiter2: 'Upper array error',
            carrier_price: 'Matrix error',
            'delimiter1.0': 'Start error',
            'delimiter2.0': 'End error',
            'carrier_price.11.0': 'Price error',
        };
        await wrapper.setProps({ errors });

        for (const error of Object.values(errors)) {
            expect(wrapper.text()).toContain(error);
        }

        expect(
            (wrapper.get('#carrier_name').element as HTMLInputElement).value,
        ).toBe('Unsaved');
    });
});
