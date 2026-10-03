import { Check, X } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import FilterDialogFooter from './FilterDialogFooter.vue';

function mountFooter() {
    return mount(FilterDialogFooter, {
        props: {
            formId: 'my-form',
            applyLabel: 'Apply it',
            clearLabel: 'Clear it',
        },
    });
}

describe('FilterDialogFooter', () => {
    it('renders a submit button bound to the given form with the apply label and a check icon', () => {
        const wrapper = mountFooter();
        const apply = wrapper.get('button[type="submit"]');

        expect(apply.text()).toBe('Apply it');
        expect(apply.attributes('form')).toBe('my-form');
        expect(apply.findComponent(Check).exists()).toBe(true);
    });

    it('renders a clear button with the clear label and an X icon', () => {
        const wrapper = mountFooter();
        const clear = wrapper.get('button[type="button"]');

        expect(clear.text()).toBe('Clear it');
        expect(clear.findComponent(X).exists()).toBe(true);
    });

    it('emits clear when the clear button is clicked, and not when apply is', async () => {
        const wrapper = mountFooter();

        await wrapper.get('button[type="submit"]').trigger('click');
        expect(wrapper.emitted('clear')).toBeUndefined();

        await wrapper.get('button[type="button"]').trigger('click');
        expect(wrapper.emitted('clear')).toHaveLength(1);
    });
});
