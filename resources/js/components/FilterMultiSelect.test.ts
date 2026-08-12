import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { defineComponent, h, ref } from 'vue';
import FilterMultiSelect from './FilterMultiSelect.vue';

const options = [
    { value: 'stored', label: 'Stored' },
    { value: 'opened', label: 'Opened' },
    { value: 'emptied', label: 'Emptied' },
];

function mountSelect(modelValue: string[] = []) {
    return mount(FilterMultiSelect, {
        props: {
            id: 'filter-status',
            label: 'Status change',
            allLabel: 'All',
            selectedCountLabel: (count: number) => `${count} selected`,
            options,
            modelValue,
            'onUpdate:modelValue': (value: string[]) => {
                modelValue = value;
            },
        },
    });
}

describe('FilterMultiSelect', () => {
    it('shows the all label when nothing is selected', () => {
        const wrapper = mountSelect([]);

        expect(wrapper.get('button').text()).toBe('All');
    });

    it("shows the option's own label when exactly one is selected", () => {
        const wrapper = mountSelect(['opened']);

        expect(wrapper.get('button').text()).toBe('Opened');
    });

    it('shows the selected count label when more than one is selected', () => {
        const wrapper = mountSelect(['opened', 'stored']);

        expect(wrapper.get('button').text()).toBe('2 selected');
    });

    it('renders a checkbox per option, closed by default', () => {
        const wrapper = mountSelect([]);

        expect(wrapper.find('[role="listbox"]').exists()).toBe(false);
    });

    it('opens the option panel when the button is clicked', async () => {
        const wrapper = mountSelect([]);

        await wrapper.get('button').trigger('click');

        const panel = wrapper.get('[role="listbox"]');
        expect(panel.findAll('input[type="checkbox"]')).toHaveLength(
            options.length,
        );
    });

    it('checks the checkboxes matching the current selection', async () => {
        const wrapper = mountSelect(['opened']);

        await wrapper.get('button').trigger('click');

        const checkboxes = wrapper.findAll('input[type="checkbox"]');
        expect(
            checkboxes.map(
                (checkbox) => (checkbox.element as HTMLInputElement).checked,
            ),
        ).toEqual([false, true, false]);
    });

    it('emits the value added to the selection when an unchecked option is checked', async () => {
        const wrapper = mountSelect(['opened']);
        await wrapper.get('button').trigger('click');

        await wrapper.findAll('input[type="checkbox"]')[0].setValue(true);

        expect(wrapper.emitted('update:modelValue')).toEqual([
            [['opened', 'stored']],
        ]);
    });

    it('emits the value removed from the selection when a checked option is unchecked', async () => {
        const wrapper = mountSelect(['opened', 'stored']);
        await wrapper.get('button').trigger('click');

        await wrapper.findAll('input[type="checkbox"]')[1].setValue(false);

        expect(wrapper.emitted('update:modelValue')).toEqual([[['stored']]]);
    });

    it('closes the panel when a click happens outside the component', async () => {
        const Host = defineComponent({
            setup() {
                const selected = ref<string[]>([]);

                return () =>
                    h('div', [
                        h(FilterMultiSelect, {
                            id: 'filter-status',
                            label: 'Status change',
                            allLabel: 'All',
                            selectedCountLabel: (count: number) =>
                                `${count} selected`,
                            options,
                            modelValue: selected.value,
                            'onUpdate:modelValue': (value: string[]) => {
                                selected.value = value;
                            },
                        }),
                        h('span', { id: 'outside' }, 'outside'),
                    ]);
            },
        });
        const wrapper = mount(Host, { attachTo: document.body });

        await wrapper.get('button').trigger('click');
        expect(wrapper.find('[role="listbox"]').exists()).toBe(true);

        document
            .getElementById('outside')
            ?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[role="listbox"]').exists()).toBe(false);

        wrapper.unmount();
    });

    it('closes the panel when Escape is pressed', async () => {
        const wrapper = mountSelect([]);
        await wrapper.get('button').trigger('click');
        expect(wrapper.find('[role="listbox"]').exists()).toBe(true);

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[role="listbox"]').exists()).toBe(false);
    });
});
