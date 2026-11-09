import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import SidebarSectionHeading from './SidebarSectionHeading.vue';

describe('SidebarSectionHeading', () => {
    it('shows the group label without a rule while the sidebar is open', () => {
        const wrapper = mount(SidebarSectionHeading, {
            props: { label: 'Inventory' },
        });

        expect(wrapper.get('p').text()).toBe('Inventory');
        expect(wrapper.get('p').classes()).toContain('uppercase');
        expect(wrapper.find('hr').exists()).toBe(false);
    });

    it('keeps the label for screen readers and draws a rule on the folded rail', () => {
        const wrapper = mount(SidebarSectionHeading, {
            props: { label: 'Inventory', collapsed: true },
        });

        expect(wrapper.get('p').classes()).toContain('lg:sr-only');
        expect(wrapper.get('hr').attributes('aria-hidden')).toBe('true');
    });
});
