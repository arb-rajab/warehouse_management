import { Tag } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import IconStat from './IconStat.vue';

const wrappers: VueWrapper[] = [];

function mountStat(props: Record<string, unknown> = {}) {
    const wrapper = mount(IconStat, {
        props: {
            icon: Tag,
            label: 'Base price: 12.50',
            value: '12.50',
            ...props,
        },
        attachTo: document.body,
    });
    wrappers.push(wrapper);

    return wrapper;
}

function bubble(): HTMLElement | null {
    return document.body.querySelector('[data-icon-stat-tooltip]');
}

afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
});

describe('IconStat', () => {
    it('shows the icon and value, hides them from assistive technology and reads the label instead', () => {
        const wrapper = mountStat();

        expect(wrapper.findComponent(Tag).exists()).toBe(true);
        expect(wrapper.find('span[aria-hidden="true"]').text()).toBe('12.50');
        expect(wrapper.find('.sr-only').text()).toBe('Base price: 12.50');
        expect(wrapper.get('button').attributes('type')).toBe('button');
    });

    it('keeps the bubble closed until the pair is hovered', async () => {
        const wrapper = mountStat();

        expect(bubble()).toBeNull();

        await wrapper.get('button').trigger('mouseenter');
        expect(bubble()?.textContent).toBe('Base price: 12.50');

        await wrapper.get('button').trigger('mouseleave');
        expect(bubble()).toBeNull();
    });

    it('opens on keyboard focus and closes on blur', async () => {
        const wrapper = mountStat();

        await wrapper.get('button').trigger('focus');
        expect(bubble()).not.toBeNull();

        await wrapper.get('button').trigger('blur');
        expect(bubble()).toBeNull();
    });

    it('toggles on tap for touch screens and closes on a click elsewhere', async () => {
        const wrapper = mountStat();

        await wrapper.get('button').trigger('click');
        expect(bubble()).not.toBeNull();

        document.body.click();
        await wrapper.vm.$nextTick();
        expect(bubble()).toBeNull();

        await wrapper.get('button').trigger('click');
        await wrapper.get('button').trigger('click');
        expect(bubble()).toBeNull();
    });

    it('closes on Escape while focus stays on the button', async () => {
        const wrapper = mountStat();

        await wrapper.get('button').trigger('focus');
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();

        expect(bubble()).toBeNull();
    });

    it('closes when the page scrolls, because a fixed bubble would be left behind', async () => {
        const wrapper = mountStat();

        await wrapper.get('button').trigger('mouseenter');
        window.dispatchEvent(new Event('scroll'));
        await wrapper.vm.$nextTick();

        expect(bubble()).toBeNull();
    });

    it('positions the bubble with the physical left property, centred on the trigger', async () => {
        const wrapper = mountStat();

        await wrapper.get('button').trigger('mouseenter');
        const style = bubble()?.style;

        expect(style?.left).toMatch(/px$/);
        expect(bubble()?.getAttribute('style')).not.toContain('inset-inline');
        expect(bubble()?.classList.contains('-translate-x-1/2')).toBe(true);
    });

    it('applies the tone to the whole pair', () => {
        const wrapper = mountStat({ tone: 'text-success-strong' });

        expect(wrapper.get('button').classes()).toContain(
            'text-success-strong',
        );
    });
});
