import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import HelpTooltip from './HelpTooltip.vue';

function mountTooltip() {
    return mount(HelpTooltip, {
        props: { label: 'About this', text: 'Helpful text' },
        attachTo: document.body,
    });
}

describe('HelpTooltip', () => {
    it('names the button and describes it with a hidden tooltip', () => {
        const wrapper = mountTooltip();
        const button = wrapper.get('button');
        const tip = wrapper.get('[role="tooltip"]');

        expect(button.attributes('aria-label')).toBe('About this');
        expect(button.attributes('aria-describedby')).toBe(
            tip.attributes('id'),
        );
        expect(tip.text()).toBe('Helpful text');
        expect(tip.isVisible()).toBe(false);
        wrapper.unmount();
    });

    it('shows on focus and hover, hides on blur and leave', async () => {
        const wrapper = mountTooltip();
        const tip = wrapper.get('[role="tooltip"]');

        await wrapper.get('button').trigger('focus');
        expect(tip.isVisible()).toBe(true);
        await wrapper.get('button').trigger('blur');
        expect(tip.isVisible()).toBe(false);

        await wrapper.trigger('mouseenter');
        expect(tip.isVisible()).toBe(true);
        await wrapper.trigger('mouseleave');
        expect(tip.isVisible()).toBe(false);
        wrapper.unmount();
    });

    it('toggles on click and closes on Escape, keeping focus on the button', async () => {
        const wrapper = mountTooltip();
        const button = wrapper.get('button');
        const tip = wrapper.get('[role="tooltip"]');

        (button.element as HTMLElement).focus();
        await button.trigger('click');
        expect(tip.isVisible()).toBe(true);

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();
        expect(tip.isVisible()).toBe(false);
        expect(document.activeElement).toBe(button.element);
        wrapper.unmount();
    });

    it('closes a pinned tooltip on a click outside', async () => {
        const wrapper = mountTooltip();
        const tip = wrapper.get('[role="tooltip"]');

        await wrapper.get('button').trigger('click');
        expect(tip.isVisible()).toBe(true);
        document.body.click();
        await wrapper.vm.$nextTick();
        expect(tip.isVisible()).toBe(false);
        wrapper.unmount();
    });
});
