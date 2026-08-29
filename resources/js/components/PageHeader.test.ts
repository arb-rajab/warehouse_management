import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PageHeader from './PageHeader.vue';

describe('PageHeader', () => {
    it('renders the title in an h1', () => {
        const wrapper = mount(PageHeader, {
            props: { title: 'Rows' },
        });

        expect(wrapper.get('h1').text()).toBe('Rows');
    });

    it('renders the default slot content alongside the title', () => {
        const wrapper = mount(PageHeader, {
            props: { title: 'Rows' },
            slots: { default: '<button>Add row</button>' },
        });

        expect(wrapper.get('button').text()).toBe('Add row');
    });
});
