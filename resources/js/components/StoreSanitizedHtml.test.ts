import { mount } from '@vue/test-utils';
import { expect, it } from 'vitest';
import StoreSanitizedHtml from './StoreSanitizedHtml.vue';
it('renders server-sanitized rich formatting and preserves RTL', () => {
    const wrapper = mount(StoreSanitizedHtml, {
        props: { html: '<p dir="rtl"><strong>Safe</strong></p>' },
    });
    expect(wrapper.get('strong').text()).toBe('Safe');
    expect(wrapper.get('p').attributes('dir')).toBe('rtl');
});
