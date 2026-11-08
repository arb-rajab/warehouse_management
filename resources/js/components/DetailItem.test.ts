import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { createCommentVNode, createTextVNode, h } from 'vue';
import DetailItem from './DetailItem.vue';

describe('DetailItem', () => {
    it('stacks a muted label above its value inside a div', () => {
        const wrapper = mount(DetailItem, {
            props: { label: 'Tracking code', value: 'TRK-1' },
        });

        expect(wrapper.element.tagName).toBe('DIV');
        expect(wrapper.get('dt').text()).toBe('Tracking code');
        expect(wrapper.get('dt').classes()).toContain('text-xs');
        expect(wrapper.get('dt').classes()).toContain('text-gray-500');
        expect(wrapper.get('dd').text()).toBe('TRK-1');
        expect(wrapper.get('dd').classes()).toContain('break-words');
        expect(wrapper.find('[data-detail-empty]').exists()).toBe(false);
    });

    it('renders a zero value, not the placeholder', () => {
        const wrapper = mount(DetailItem, {
            props: { label: 'Discount', value: 0 },
        });

        expect(wrapper.get('dd').text()).toBe('0');
    });

    it.each([
        ['null', null],
        ['undefined', undefined],
        ['an empty string', ''],
        ['blank text', '   '],
    ])('shows the "-" placeholder for %s', (_name, value) => {
        const wrapper = mount(DetailItem, { props: { label: 'Notes', value } });

        expect(wrapper.get('dd').text()).toBe('-');
        expect(wrapper.find('[data-detail-empty]').exists()).toBe(true);
    });

    it('shows the placeholder when the slot renders nothing', () => {
        const wrapper = mount(DetailItem, {
            props: { label: 'Customer' },
            slots: {
                default: () => [
                    createCommentVNode('v-if'),
                    createTextVNode(' '),
                ],
            },
        });

        expect(wrapper.get('dd').text()).toBe('-');
    });

    it('renders slot content instead of the value or the placeholder', () => {
        const wrapper = mount(DetailItem, {
            props: { label: 'Customer', value: 'ignored' },
            slots: { default: () => h('a', { href: '/c/1' }, 'Acme') },
        });

        expect(wrapper.get('dd a').attributes('href')).toBe('/c/1');
        expect(wrapper.get('dd').text()).toBe('Acme');
        expect(wrapper.find('[data-detail-empty]').exists()).toBe(false);
    });

    it('spans every column with full and keeps line breaks with preserve', () => {
        const wrapper = mount(DetailItem, {
            props: {
                label: 'Notes',
                value: 'a\nb',
                full: true,
                preserve: true,
            },
        });

        expect(wrapper.classes()).toContain('col-span-full');
        expect(wrapper.get('dd').classes()).toContain('whitespace-pre-wrap');
    });

    it('styles an amount with tabular figures and a bidi isolate', () => {
        const wrapper = mount(DetailItem, {
            props: { label: 'Total', value: '1,284.50', amount: true },
        });
        const classes = wrapper.get('dd').classes();

        expect(classes).toContain('tabular-nums');
        expect(classes).toContain('font-medium');
        expect(classes).toContain('[unicode-bidi:isolate]');
    });

    it('uses logical alignment only', () => {
        const wrapper = mount(DetailItem, {
            props: { label: 'Total', value: '1', amount: true, full: true },
        });

        expect(wrapper.html()).not.toMatch(
            /(?:^|[\s"])(?:text-left|text-right|ml-|mr-|pl-|pr-|left-|right-)/,
        );
    });
});
