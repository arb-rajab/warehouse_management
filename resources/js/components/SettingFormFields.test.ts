import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import SettingFormFields from './SettingFormFields.vue';

describe('SettingFormFields', () => {
    it('renders its labels', () => {
        const wrapper = mount(SettingFormFields, { props: { errors: {} } });

        expect(wrapper.text()).toContain('QR code width (px)');
        expect(wrapper.text()).toContain('QR code height (px)');
    });

    it('pre-fills the current values', () => {
        const wrapper = mount(SettingFormFields, {
            props: { errors: {}, qrCodeWidth: 300, qrCodeHeight: 350 },
        });

        expect(
            (wrapper.find('#qr_code_width').element as HTMLInputElement).value,
        ).toBe('300');
        expect(
            (wrapper.find('#qr_code_height').element as HTMLInputElement).value,
        ).toBe('350');
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

        await wrapper.find('#qr_code_width').setValue('400');
        await wrapper.find('#qr_code_height').setValue('500');

        await wrapper.setProps({
            errors: { qr_code_width: 'The qr code width field is required.' },
        });

        expect(
            (wrapper.find('#qr_code_width').element as HTMLInputElement).value,
        ).toBe('400');
        expect(
            (wrapper.find('#qr_code_height').element as HTMLInputElement).value,
        ).toBe('500');
    });

    it('marks both fields as required and whole numbers, mirroring the backend rules', () => {
        const wrapper = mount(SettingFormFields, { props: { errors: {} } });

        expect(
            (wrapper.find('#qr_code_width').element as HTMLInputElement)
                .required,
        ).toBe(true);
        expect(
            (wrapper.find('#qr_code_height').element as HTMLInputElement)
                .required,
        ).toBe(true);
        expect(wrapper.find('#qr_code_width').attributes('step')).toBe('1');
        expect(wrapper.find('#qr_code_height').attributes('step')).toBe('1');
    });

    it('bounds both fields to 100-1000, mirroring the backend rule', () => {
        const wrapper = mount(SettingFormFields, { props: { errors: {} } });

        expect(wrapper.find('#qr_code_width').attributes('min')).toBe('100');
        expect(wrapper.find('#qr_code_width').attributes('max')).toBe('1000');
        expect(wrapper.find('#qr_code_height').attributes('min')).toBe('100');
        expect(wrapper.find('#qr_code_height').attributes('max')).toBe('1000');
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
