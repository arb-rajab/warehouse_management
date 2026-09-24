import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { pxToCm } from '@/lib/qrCodeSize';
import SettingFormFields from './SettingFormFields.vue';

describe('SettingFormFields', () => {
    it('renders its labels', () => {
        const wrapper = mount(SettingFormFields, { props: { errors: {} } });

        expect(wrapper.text()).toContain('QR code width (cm)');
        expect(wrapper.text()).toContain('QR code height (cm)');
    });

    it('pre-fills the current values converted from pixels to centimeters', () => {
        const wrapper = mount(SettingFormFields, {
            props: { errors: {}, qrCodeWidth: 300, qrCodeHeight: 350 },
        });

        expect(
            (wrapper.find('#qr_code_width').element as HTMLInputElement).value,
        ).toBe(String(pxToCm(300)));
        expect(
            (wrapper.find('#qr_code_height').element as HTMLInputElement).value,
        ).toBe(String(pxToCm(350)));
    });

    it('shows validation error messages', () => {
        const wrapper = mount(SettingFormFields, {
            props: {
                errors: {
                    qr_code_width: 'The qr code width field is required.',
                    qr_code_height: 'The qr code height field is required.',
                },
            },
        });

        expect(wrapper.text()).toContain(
            'The qr code width field is required.',
        );
        expect(wrapper.text()).toContain(
            'The qr code height field is required.',
        );
    });

    it('retains values the user typed when the errors prop changes after a failed submit', async () => {
        const wrapper = mount(SettingFormFields, { props: { errors: {} } });

        await wrapper.find('#qr_code_width').setValue('10.5');
        await wrapper.find('#qr_code_height').setValue('12.5');

        await wrapper.setProps({
            errors: { qr_code_width: 'The qr code width field is required.' },
        });

        expect(
            (wrapper.find('#qr_code_width').element as HTMLInputElement).value,
        ).toBe('10.5');
        expect(
            (wrapper.find('#qr_code_height').element as HTMLInputElement).value,
        ).toBe('12.5');
    });

    it('marks both fields as required and entered in tenths of a centimeter', () => {
        const wrapper = mount(SettingFormFields, { props: { errors: {} } });

        expect(
            (wrapper.find('#qr_code_width').element as HTMLInputElement)
                .required,
        ).toBe(true);
        expect(
            (wrapper.find('#qr_code_height').element as HTMLInputElement)
                .required,
        ).toBe(true);
        expect(wrapper.find('#qr_code_width').attributes('step')).toBe('0.1');
        expect(wrapper.find('#qr_code_height').attributes('step')).toBe('0.1');
    });

    it('bounds both fields to the cm equivalent of the backend 100-1000px rule', () => {
        const wrapper = mount(SettingFormFields, { props: { errors: {} } });
        const minCm = String(pxToCm(100));
        const maxCm = String(pxToCm(1000));

        expect(wrapper.find('#qr_code_width').attributes('min')).toBe(minCm);
        expect(wrapper.find('#qr_code_width').attributes('max')).toBe(maxCm);
        expect(wrapper.find('#qr_code_height').attributes('min')).toBe(minCm);
        expect(wrapper.find('#qr_code_height').attributes('max')).toBe(maxCm);
    });

    it('prefixes its ids when idPrefix is given, so it can render more than once on the same page', () => {
        const wrapper = mount(SettingFormFields, {
            props: { errors: {}, idPrefix: 'preview-' },
        });

        expect(wrapper.find('#preview-qr_code_width').exists()).toBe(true);
        expect(wrapper.find('#preview-qr_code_height').exists()).toBe(true);
        expect(wrapper.find('#qr_code_width').exists()).toBe(false);
    });
});
