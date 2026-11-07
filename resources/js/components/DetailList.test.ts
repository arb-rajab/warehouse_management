import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { h } from 'vue';
import DetailItem from './DetailItem.vue';
import DetailList from './DetailList.vue';

function mountList(props: Record<string, unknown> = {}) {
    return mount(DetailList, {
        props,
        slots: {
            default: () => [
                h(DetailItem, { label: 'First', value: 'one' }),
                h(DetailItem, { label: 'Second', value: null }),
            ],
        },
    });
}

describe('DetailList', () => {
    it('renders a titled card with a dl of stacked items', () => {
        const wrapper = mountList({ title: 'Order' });

        expect(wrapper.get('h2').text()).toBe('Order');
        expect(wrapper.classes()).toContain('rounded-lg');
        expect(wrapper.classes()).toContain('border');
        expect(wrapper.findAll('dl > div')).toHaveLength(2);
        expect(wrapper.findAll('dt').map((dt) => dt.text())).toEqual([
            'First',
            'Second',
        ]);
        expect(wrapper.findAll('dd').map((dd) => dd.text())).toEqual([
            'one',
            '-',
        ]);
    });

    it('renders no heading without a title', () => {
        expect(mountList().find('h2').exists()).toBe(false);
    });

    it('is one column on a phone and `columns` from md up', () => {
        expect(mountList().get('dl').classes()).toEqual(
            expect.arrayContaining(['grid', 'grid-cols-1']),
        );
        expect(mountList({ columns: 2 }).get('dl').classes()).toEqual(
            expect.arrayContaining(['grid-cols-1', 'md:grid-cols-2']),
        );
        expect(mountList({ columns: 3 }).get('dl').classes()).toEqual(
            expect.arrayContaining(['grid-cols-1', 'md:grid-cols-3']),
        );
    });

    it('keeps the columns on a phone with keepColumns', () => {
        const classes = mountList({ columns: 2, keepColumns: true })
            .get('dl')
            .classes();

        expect(classes).toContain('grid-cols-2');
        expect(classes).not.toContain('grid-cols-1');
        expect(classes).not.toContain('md:grid-cols-2');
    });

    it('drops the card with plain', () => {
        const wrapper = mountList({ plain: true, title: 'Payout' });

        expect(wrapper.classes()).not.toContain('border');
        expect(wrapper.classes()).not.toContain('p-4');
        expect(wrapper.get('h2').text()).toBe('Payout');
    });

    it('passes extra classes to the section', () => {
        const wrapper = mountList({ class: 'md:col-span-2' });

        expect(wrapper.classes()).toContain('md:col-span-2');
    });

    it('uses logical alignment only', () => {
        expect(mountList({ title: 'x' }).html()).not.toMatch(
            /(?:^|[\s"])(?:text-left|text-right|ml-|mr-|pl-|pr-|left-|right-)/,
        );
    });
});
