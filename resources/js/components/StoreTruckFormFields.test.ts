import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { t } from '@/lib/i18n';
import { storeTruck, truckPhoto } from '@/testing/factories';
import StoreTruckFormFields from './StoreTruckFormFields.vue';
vi.mock('@/components/StoreUploadField.vue', () => ({
    default: {
        props: [
            'id',
            'name',
            'modelValue',
            'label',
            'type',
            'multiple',
            'initialFiles',
            'error',
        ],
        template: '<div>{{label}} {{error}}</div>',
    },
}));
function mountFields() {
    return mount(StoreTruckFormFields, {
        props: { truck: storeTruck(), photos: [truckPhoto()], errors: {} },
    });
}
describe('truck form fields', () => {
    it('renders labels, current values and backend native constraints', () => {
        const wrapper = mountFields();

        for (const [id, key] of [
            ['name', 'name'],
            ['model', 'model'],
            ['max_pallets_number', 'capacity'],
            ['license_plate', 'plate'],
        ]) {
            expect(wrapper.get(`label[for="${id}"]`).text()).toBe(
                t(`store.trucks.fields.${key}`) +
                    (id === 'name' ? '*' + t('common.required') : ''),
            );
            expect(wrapper.get(`#${id}`).attributes('maxlength')).toBe('50');
        }

        expect(wrapper.get('#name').attributes('required')).toBeDefined();
        expect((wrapper.get('#name').element as HTMLInputElement).value).toBe(
            'Cargo truck',
        );
        expect(wrapper.get('#model').attributes('required')).toBeUndefined();
        expect(wrapper.get('#description').attributes('maxlength')).toBe('255');
        expect(
            (wrapper.get('#description').element as HTMLTextAreaElement).value,
        ).toBe('Refrigerated');
        expect(wrapper.get('label[for="description"]').text()).toBe(
            t('store.trucks.fields.description'),
        );
        expect(wrapper.getComponent(StoreUploadField).props()).toMatchObject({
            id: 'photos',
            name: 'photos',
            modelValue: '7',
            type: 'image',
            multiple: true,
            initialFiles: [{ ...truckPhoto(), type: 'image', size: null }],
        });
    });

    it('keeps a dirty description when server defaults or errors rerender the form', async () => {
        const wrapper = mountFields();
        const description = wrapper.get('#description');
        await description.setValue('Keep this unsaved description');
        await wrapper.setProps({
            truck: storeTruck({ description: 'Server default changed' }),
            errors: { license_plate: 'Already taken' },
        });
        expect((description.element as HTMLTextAreaElement).defaultValue).toBe(
            'Server default changed',
        );
        expect((description.element as HTMLTextAreaElement).value).toBe(
            'Keep this unsaved description',
        );
        expect(wrapper.text()).toContain('Already taken');
    });
});
