import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import SubmitButton from './SubmitButton.vue';

describe('SubmitButton', () => {
    it('renders the idle label and stays enabled while not processing', () => {
        const wrapper = mount(SubmitButton, {
            props: {
                label: 'Create row',
                processingLabel: 'Creating...',
                processing: false,
            },
        });

        const button = wrapper.get('button');
        expect(button.text()).toBe('Create row');
        expect(button.attributes('type')).toBe('submit');
        expect((button.element as HTMLButtonElement).disabled).toBe(false);
        expect(button.find('svg').exists()).toBe(false);
    });

    it('swaps to the processing label and disables itself while processing', () => {
        const wrapper = mount(SubmitButton, {
            props: {
                label: 'Create row',
                processingLabel: 'Creating...',
                processing: true,
            },
        });

        const button = wrapper.get('button');
        expect(button.text()).toBe('Creating...');
        expect((button.element as HTMLButtonElement).disabled).toBe(true);
        expect(button.find('svg.animate-spin').exists()).toBe(true);
    });
});
