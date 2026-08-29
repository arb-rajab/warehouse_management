import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { t } from '@/lib/i18n';
import CellLogActivityFilterFields from './CellLogActivityFilterFields.vue';

const actions: ('stored' | 'opened' | 'emptied')[] = [
    'stored',
    'opened',
    'emptied',
];
const users = [
    { id: 7, name: 'Jane Doe' },
    { id: 9, name: 'John Smith' },
];

function mountFields(overrides: Record<string, unknown> = {}) {
    return mount(CellLogActivityFilterFields, {
        props: {
            idPrefix: 'filter',
            actions,
            users,
            action: [],
            userId: [],
            ...overrides,
        },
    });
}

describe('CellLogActivityFilterFields', () => {
    it('renders an action multiselect and a user multiselect, prefixed by idPrefix', () => {
        const wrapper = mountFields();

        const buttons = wrapper.findAll('button');
        expect(buttons).toHaveLength(2);
        expect(buttons[0].attributes('id')).toBe('filter-action');
        expect(buttons[1].attributes('id')).toBe('filter-user');

        const labels = wrapper.findAll('label');
        expect(labels[0].text()).toBe(t('cellLog.filters.statusChange'));
        expect(labels[1].text()).toBe(t('cellLog.filters.doneBy'));
    });

    it('uses a distinct DOM id per idPrefix so the fields can render twice on one page', () => {
        const wrapper = mountFields({ idPrefix: 'popover-filter' });

        const buttons = wrapper.findAll('button');
        expect(buttons[0].attributes('id')).toBe('popover-filter-action');
        expect(buttons[1].attributes('id')).toBe('popover-filter-user');
    });

    it('labels action options via cellLogActionLabel, and user options via their name', async () => {
        const wrapper = mountFields();
        const [actionButton, userButton] = wrapper.findAll('button');

        await actionButton.trigger('click');
        expect(
            wrapper
                .findAll('[role="option"] span')
                .map((el) => el.text())
                .slice(0, actions.length),
        ).toEqual(actions.map((action) => t(`cellLog.actions.${action}`)));
        await actionButton.trigger('click');

        await userButton.trigger('click');
        expect(
            wrapper.findAll('[role="option"] span').map((el) => el.text()),
        ).toEqual(['Jane Doe', 'John Smith']);
    });

    it('emits update:action when an action checkbox is toggled', async () => {
        const wrapper = mountFields();
        await wrapper.findAll('button')[0].trigger('click');

        await wrapper.findAll('input[type="checkbox"]')[0].setValue(true);

        expect(wrapper.emitted('update:action')).toEqual([[['stored']]]);
    });

    it('emits update:userId when a user checkbox is toggled', async () => {
        const wrapper = mountFields();
        await wrapper.findAll('button')[1].trigger('click');

        await wrapper.findAll('input[type="checkbox"]')[0].setValue(true);

        expect(wrapper.emitted('update:userId')).toEqual([[['7']]]);
    });
});
