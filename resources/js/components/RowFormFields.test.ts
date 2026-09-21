import { TriangleAlert } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import RowFormFields from './RowFormFields.vue';

describe('RowFormFields', () => {
    it('renders its labels', () => {
        const wrapper = mount(RowFormFields, { props: { errors: {} } });

        expect(wrapper.text()).toContain('Letter');
        expect(wrapper.text()).toContain('Cells per level');
        expect(wrapper.text()).toContain('Levels');
    });

    it('shows validation error messages', () => {
        const wrapper = mount(RowFormFields, {
            props: {
                errors: {
                    letter: 'The letter has already been taken.',
                    cells_count: 'The cells count field is required.',
                    flats_count: 'The flats count field is required.',
                },
            },
        });

        expect(wrapper.text()).toContain('The letter has already been taken.');
        expect(wrapper.text()).toContain('The cells count field is required.');
        expect(wrapper.text()).toContain('The flats count field is required.');
    });

    it('retains values the user typed when the errors prop changes after a failed submit', async () => {
        const wrapper = mount(RowFormFields, { props: { errors: {} } });

        await wrapper.find('#letter').setValue('A');
        await wrapper.find('#cells_count').setValue('5');
        await wrapper.find('#flats_count').setValue('7');

        await wrapper.setProps({
            errors: { letter: 'The letter has already been taken.' },
        });

        expect(
            (wrapper.find('#letter').element as HTMLInputElement).value,
        ).toBe('A');
        expect(
            (wrapper.find('#cells_count').element as HTMLInputElement).value,
        ).toBe('5');
        expect(
            (wrapper.find('#flats_count').element as HTMLInputElement).value,
        ).toBe('7');
    });

    it('marks the letter, cells count, and flats count fields as required, mirroring the backend rules', () => {
        const wrapper = mount(RowFormFields, { props: { errors: {} } });

        expect(
            (wrapper.find('#letter').element as HTMLInputElement).required,
        ).toBe(true);
        expect(
            (wrapper.find('#cells_count').element as HTMLInputElement).required,
        ).toBe(true);
        expect(
            (wrapper.find('#flats_count').element as HTMLInputElement).required,
        ).toBe(true);
    });

    it('restricts cells count and flats count to whole numbers', () => {
        const wrapper = mount(RowFormFields, { props: { errors: {} } });

        expect(wrapper.find('#cells_count').attributes('step')).toBe('1');
        expect(wrapper.find('#flats_count').attributes('step')).toBe('1');
    });

    it('caps cells count and flats count at the operational maximum, mirroring the backend rule', () => {
        const wrapper = mount(RowFormFields, { props: { errors: {} } });

        expect(wrapper.find('#cells_count').attributes('max')).toBe('500');
        expect(wrapper.find('#flats_count').attributes('max')).toBe('500');
    });

    it('prefixes its ids when idPrefix is given, so it can render more than once on the same page', () => {
        const wrapper = mount(RowFormFields, {
            props: { errors: {}, idPrefix: 'preview-' },
        });

        expect(wrapper.find('#preview-letter').exists()).toBe(true);
        expect(wrapper.find('#preview-cells_count').exists()).toBe(true);
        expect(wrapper.find('#preview-flats_count').exists()).toBe(true);
        expect(wrapper.find('#letter').exists()).toBe(false);
    });

    it('marks the dimension inputs readonly when disableDimensions is true', () => {
        const wrapper = mount(RowFormFields, {
            props: { errors: {}, disableDimensions: true },
        });

        expect(
            (wrapper.find('#cells_count').element as HTMLInputElement).readOnly,
        ).toBe(true);
        expect(
            (wrapper.find('#flats_count').element as HTMLInputElement).readOnly,
        ).toBe(true);
        expect(wrapper.text()).toContain(
            "This row has pallets stored in it, so its dimensions can't be changed.",
        );
        expect(wrapper.findComponent(TriangleAlert).exists()).toBe(true);
    });
});
