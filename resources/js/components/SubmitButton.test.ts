import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { dangerButtonClass } from '@/lib/filters';
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

describe('SubmitButton variants', () => {
    it('is the brand-red primary button by default', () => {
        const button = mount(SubmitButton, {
            props: {
                label: 'Save',
                processingLabel: 'Saving',
                processing: false,
            },
        }).get('button');

        expect(button.classes()).toContain('bg-brand');
        expect(button.classes()).toContain('text-on-brand');
    });

    it('is the soft danger button with a trash icon for a destructive submit', () => {
        const button = mount(SubmitButton, {
            props: {
                label: 'Delete selection',
                processingLabel: 'Deleting',
                processing: false,
                variant: 'danger',
            },
        }).get('button');

        expect(button.classes()).toEqual(
            expect.arrayContaining(dangerButtonClass.split(' ')),
        );
        expect(button.classes()).not.toContain('bg-brand');
        expect(button.classes()).not.toContain('bg-red-600');
        expect(button.find('svg').exists()).toBe(true);
        expect(button.text()).toBe('Delete selection');
    });
});
