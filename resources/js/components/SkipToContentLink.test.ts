import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { t } from '@/lib/i18n';
import SkipToContentLink from './SkipToContentLink.vue';

describe('SkipToContentLink', () => {
    it('targets the main landmark', () => {
        const wrapper = mount(SkipToContentLink);

        expect(wrapper.get('a').attributes('href')).toBe('#main');
    });

    it('renders the skip label', () => {
        const wrapper = mount(SkipToContentLink);

        expect(wrapper.text()).toBe(t('nav.skipToContent'));
        expect(t('nav.skipToContent')).toBe('Skip to main content');
    });

    it('stays visually hidden until focused', () => {
        const wrapper = mount(SkipToContentLink);

        expect(wrapper.get('a').classes()).toEqual(
            expect.arrayContaining(['sr-only', 'focus:not-sr-only']),
        );
    });
});
