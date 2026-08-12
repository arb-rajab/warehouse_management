import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import FilterDialog from './FilterDialog.vue';

function mountDialog(open = true) {
    return mount(FilterDialog, {
        props: {
            title: 'Filters',
            closeLabel: 'Close',
            open,
            'onUpdate:open': (value: boolean) => {
                open = value;
            },
        },
        slots: {
            default: '<p>Filter fields</p>',
        },
    });
}

describe('FilterDialog', () => {
    it('renders nothing when closed', () => {
        const wrapper = mountDialog(false);

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('renders the title and slot content when open', () => {
        const wrapper = mountDialog(true);

        expect(wrapper.get('[role="dialog"]').text()).toContain('Filters');
        expect(wrapper.text()).toContain('Filter fields');
    });

    it('emits update:open false when the close button is clicked', async () => {
        const wrapper = mountDialog(true);

        await wrapper.get('[aria-label="Close"]').trigger('click');

        expect(wrapper.emitted('update:open')).toEqual([[false]]);
    });

    it('emits update:open false when the backdrop is clicked', async () => {
        const wrapper = mountDialog(true);

        await wrapper.get('.fixed.inset-0.bg-black\\/50').trigger('click');

        expect(wrapper.emitted('update:open')).toEqual([[false]]);
    });

    it('emits update:open false when Escape is pressed', async () => {
        const wrapper = mountDialog(true);

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:open')).toEqual([[false]]);
    });
});
