import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import FormField from './FormField.vue';

describe('FormField', () => {
    it('renders a labelled input wired to the given id', () => {
        const wrapper = mount(FormField, {
            props: { id: 'letter', label: 'Letter' },
        });

        const label = wrapper.get('label');
        expect(label.text()).toBe('Letter');
        expect(label.attributes('for')).toBe('letter');

        const input = wrapper.get('input');
        expect(input.attributes('id')).toBe('letter');
        expect(input.attributes('name')).toBe('letter');
        expect(input.attributes('type')).toBe('text');
    });

    it('uses the given input type', () => {
        const wrapper = mount(FormField, {
            props: { id: 'email', label: 'Email', type: 'email' },
        });

        expect(wrapper.get('input').attributes('type')).toBe('email');
    });

    it('renders the error message only when one is given', () => {
        const wrapper = mount(FormField, {
            props: { id: 'letter', label: 'Letter' },
        });

        expect(wrapper.find('p').exists()).toBe(false);

        const withError = mount(FormField, {
            props: {
                id: 'letter',
                label: 'Letter',
                error: 'The letter has already been taken.',
            },
        });

        expect(withError.get('p').text()).toBe(
            'The letter has already been taken.',
        );
    });

    it('seeds the input as an attribute so typed values survive a re-render', async () => {
        const wrapper = mount(FormField, {
            props: { id: 'letter', label: 'Letter', value: 'A' },
        });

        const input = wrapper.get('input');
        expect((input.element as HTMLInputElement).value).toBe('A');

        await input.setValue('ZZ');
        await wrapper.setProps({ error: 'The letter has already been taken.' });

        expect((input.element as HTMLInputElement).value).toBe('ZZ');
    });

    it('omits the value attribute entirely when no value is given', () => {
        const wrapper = mount(FormField, {
            props: { id: 'password', label: 'Password', type: 'password' },
        });

        expect(wrapper.get('input').attributes('value')).toBeUndefined();
    });

    it('passes extra attributes through to the input, not the wrapper', () => {
        const wrapper = mount(FormField, {
            props: {
                id: 'cells_count',
                label: 'Cells per flat',
                type: 'number',
            },
            attrs: { min: '1', readonly: true, required: true },
        });

        const input = wrapper.get('input');
        expect(input.attributes('min')).toBe('1');
        expect((input.element as HTMLInputElement).readOnly).toBe(true);
        expect((input.element as HTMLInputElement).required).toBe(true);

        expect(wrapper.element.getAttribute('min')).toBeNull();
    });

    it('appends inputClass to the shared input styling', () => {
        const wrapper = mount(FormField, {
            props: { id: 'letter', label: 'Letter', inputClass: 'uppercase' },
        });

        const classes = wrapper.get('input').classes();
        expect(classes).toContain('uppercase');
        expect(classes).toContain('w-full');
    });
});
