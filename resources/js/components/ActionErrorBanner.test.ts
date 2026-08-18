import { CircleAlert } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ActionErrorBanner from './ActionErrorBanner.vue';

describe('ActionErrorBanner', () => {
    it('renders nothing when there is no message', () => {
        const wrapper = mount(ActionErrorBanner, { props: {} });

        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });

    it('renders the message with an alert icon when present', () => {
        const wrapper = mount(ActionErrorBanner, {
            props: { message: 'Cannot delete a row that has pallets in it.' },
        });

        const alert = wrapper.get('[role="alert"]');
        expect(alert.text()).toBe(
            'Cannot delete a row that has pallets in it.',
        );
        expect(wrapper.findComponent(CircleAlert).exists()).toBe(true);
    });
});
