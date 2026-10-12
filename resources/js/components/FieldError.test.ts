import { CircleAlert } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import FieldError from './FieldError.vue';

describe('FieldError', () => {
    it('renders the message with an error icon', () => {
        const wrapper = mount(FieldError, {
            props: { message: 'The name field is required.' },
        });

        expect(wrapper.text()).toBe('The name field is required.');
        expect(wrapper.findComponent(CircleAlert).exists()).toBe(true);
    });

    it('renders nothing without a message', () => {
        const wrapper = mount(FieldError);

        expect(wrapper.find('p').exists()).toBe(false);
    });
});
