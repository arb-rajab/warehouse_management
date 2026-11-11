import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { h } from 'vue';
import StoreHighlight from './StoreHighlight.vue';

const mounted: ReturnType<typeof mount>[] = [];

function mountHighlight(
    active: boolean,
    content: () => ReturnType<typeof h>[],
    as?: string,
) {
    const wrapper = mount(StoreHighlight, {
        props: { active, ...(as ? { as } : {}) },
        slots: { default: content },
        attachTo: document.body,
    });
    mounted.push(wrapper);

    return wrapper;
}

afterEach(() => {
    mounted.splice(0).forEach((wrapper) => wrapper.unmount());
});

describe('StoreHighlight', () => {
    it('marks, scrolls to and focuses the first control of an active block', async () => {
        const scrollIntoView = vi.fn();
        Element.prototype.scrollIntoView = scrollIntoView;
        const wrapper = mountHighlight(true, () => [
            h('input', { type: 'hidden', name: 'type' }),
            h('label', { for: 'cost' }, 'Cost'),
            h('input', { id: 'cost', type: 'number' }),
            h('input', { id: 'other' }),
        ]);
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.attributes('data-highlighted')).toBeDefined();
        expect(wrapper.classes()).toEqual(
            expect.arrayContaining(['ring-2', 'ring-warning']),
        );
        expect(scrollIntoView).toHaveBeenCalledWith({ block: 'center' });
        expect(scrollIntoView.mock.contexts[0]).toBe(
            wrapper.get('#cost').element,
        );
        expect(document.activeElement).toBe(wrapper.get('#cost').element);
    });

    it('focuses the block itself when it holds no control', async () => {
        Element.prototype.scrollIntoView = vi.fn();
        const wrapper = mountHighlight(
            true,
            () => [h('span', 'Read-only status')],
            'p',
        );
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.element.tagName).toBe('P');
        expect(wrapper.attributes('tabindex')).toBe('-1');
        expect(document.activeElement).toBe(wrapper.element);
    });

    it('is a plain wrapper when inactive', async () => {
        const scrollIntoView = vi.fn();
        Element.prototype.scrollIntoView = scrollIntoView;
        const wrapper = mountHighlight(false, () => [
            h('input', { id: 'cost' }),
        ]);
        await wrapper.vm.$nextTick();

        expect(wrapper.attributes('data-highlighted')).toBeUndefined();
        expect(wrapper.attributes('tabindex')).toBeUndefined();
        expect(wrapper.classes()).not.toContain('ring-warning');
        expect(scrollIntoView).not.toHaveBeenCalled();
        expect(document.activeElement).not.toBe(wrapper.get('#cost').element);
    });
});
