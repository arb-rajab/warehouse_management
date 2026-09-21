import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { defineComponent, h, ref } from 'vue';
import {
    useDismissibleListbox,
    useMultiSelectToggle,
} from './useDismissibleListbox';

function mountListbox(optionCount = 3) {
    return mount(
        defineComponent({
            setup() {
                const { open, containerRef, setOptionRef, onOptionKeydown } =
                    useDismissibleListbox(() => optionCount);

                return () =>
                    h('div', [
                        h('div', { ref: containerRef }, [
                            h(
                                'button',
                                { onClick: () => (open.value = !open.value) },
                                'toggle',
                            ),
                            open.value
                                ? h(
                                      'div',
                                      Array.from(
                                          { length: optionCount },
                                          (_, index) =>
                                              h('input', {
                                                  type: 'checkbox',
                                                  ref: (el: unknown) =>
                                                      setOptionRef(
                                                          el as Element | null,
                                                          index,
                                                      ),
                                                  onKeydown: (
                                                      event: KeyboardEvent,
                                                  ) =>
                                                      onOptionKeydown(
                                                          event,
                                                          index,
                                                      ),
                                              }),
                                      ),
                                  )
                                : null,
                        ]),
                        h('span', { id: 'outside' }, 'outside'),
                    ]);
            },
        }),
        { attachTo: document.body },
    );
}

describe('useDismissibleListbox', () => {
    it('defaults optionCount to zero for a dismiss-only caller with no option list', async () => {
        const wrapper = mount(
            defineComponent({
                setup() {
                    const { open, containerRef } = useDismissibleListbox();

                    return () =>
                        h('div', [
                            h('div', { ref: containerRef }, [
                                h(
                                    'button',
                                    {
                                        onClick: () =>
                                            (open.value = !open.value),
                                    },
                                    open.value ? 'open' : 'closed',
                                ),
                            ]),
                            h('span', { id: 'outside' }, 'outside'),
                        ]);
                },
            }),
            { attachTo: document.body },
        );

        expect(wrapper.get('button').text()).toBe('closed');

        await wrapper.get('button').trigger('click');
        expect(wrapper.get('button').text()).toBe('open');

        document
            .getElementById('outside')
            ?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await wrapper.vm.$nextTick();

        expect(wrapper.get('button').text()).toBe('closed');
        wrapper.unmount();
    });

    it('starts closed and opens via the returned open ref', async () => {
        const wrapper = mountListbox();

        expect(wrapper.find('input').exists()).toBe(false);

        await wrapper.get('button').trigger('click');

        expect(wrapper.findAll('input')).toHaveLength(3);
        wrapper.unmount();
    });

    it('closes when a click happens outside the container', async () => {
        const wrapper = mountListbox();
        await wrapper.get('button').trigger('click');
        expect(wrapper.findAll('input')).toHaveLength(3);

        document
            .getElementById('outside')
            ?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await wrapper.vm.$nextTick();

        expect(wrapper.find('input').exists()).toBe(false);
        wrapper.unmount();
    });

    it('does not close on a click inside the container', async () => {
        const wrapper = mountListbox();
        await wrapper.get('button').trigger('click');

        wrapper
            .get('input')
            .element.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await wrapper.vm.$nextTick();

        expect(wrapper.findAll('input')).toHaveLength(3);
        wrapper.unmount();
    });

    it('closes when Escape is pressed', async () => {
        const wrapper = mountListbox();
        await wrapper.get('button').trigger('click');

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();

        expect(wrapper.find('input').exists()).toBe(false);
        wrapper.unmount();
    });

    it('moves focus to the next option on ArrowDown, wrapping past the last', async () => {
        const wrapper = mountListbox(3);
        await wrapper.get('button').trigger('click');

        const inputs = wrapper
            .findAll('input')
            .map((input) => input.element as HTMLInputElement);

        inputs[0].focus();
        await wrapper.findAll('input')[0].trigger('keydown', {
            key: 'ArrowDown',
        });
        expect(document.activeElement).toBe(inputs[1]);

        inputs[2].focus();
        await wrapper.findAll('input')[2].trigger('keydown', {
            key: 'ArrowDown',
        });
        expect(document.activeElement).toBe(inputs[0]);

        wrapper.unmount();
    });

    it('moves focus to the previous option on ArrowUp, wrapping before the first', async () => {
        const wrapper = mountListbox(3);
        await wrapper.get('button').trigger('click');

        const inputs = wrapper
            .findAll('input')
            .map((input) => input.element as HTMLInputElement);

        inputs[0].focus();
        await wrapper.findAll('input')[0].trigger('keydown', {
            key: 'ArrowUp',
        });
        expect(document.activeElement).toBe(inputs[2]);

        wrapper.unmount();
    });

    it('moves focus to the first/last option on Home/End', async () => {
        const wrapper = mountListbox(3);
        await wrapper.get('button').trigger('click');

        const inputs = wrapper
            .findAll('input')
            .map((input) => input.element as HTMLInputElement);

        inputs[1].focus();
        await wrapper.findAll('input')[1].trigger('keydown', { key: 'End' });
        expect(document.activeElement).toBe(inputs[2]);

        await wrapper.findAll('input')[2].trigger('keydown', { key: 'Home' });
        expect(document.activeElement).toBe(inputs[0]);

        wrapper.unmount();
    });
});

describe('useMultiSelectToggle', () => {
    it('reports whether a value is present in the model', () => {
        const model = ref<string[]>(['a']);
        const { isChecked } = useMultiSelectToggle(model);

        expect(isChecked('a')).toBe(true);
        expect(isChecked('b')).toBe(false);
    });

    it('adds a value not yet selected', () => {
        const model = ref<string[]>(['a']);
        const { toggleValue } = useMultiSelectToggle(model);

        toggleValue('b');

        expect(model.value).toEqual(['a', 'b']);
    });

    it('removes a value already selected', () => {
        const model = ref<string[]>(['a', 'b']);
        const { toggleValue } = useMultiSelectToggle(model);

        toggleValue('a');

        expect(model.value).toEqual(['b']);
    });
});
