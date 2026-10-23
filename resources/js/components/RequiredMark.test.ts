import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import { i18n } from '@/lib/i18n';
import RequiredLegend from './RequiredLegend.vue';
import RequiredMark from './RequiredMark.vue';

afterEach(() => {
    i18n.global.locale.value = 'en';
});

describe('RequiredMark', () => {
    it('renders an aria-hidden asterisk and visually hidden "required" text', () => {
        const wrapper = mount(RequiredMark);

        const asterisk = wrapper.get('[aria-hidden="true"]');
        expect(asterisk.text()).toBe('*');
        expect(wrapper.get('.sr-only').text()).toBe('required');
        expect(wrapper.text()).toBe('*required');
    });

    it('uses logical spacing and a red with a dark-mode variant', () => {
        const classes = mount(RequiredMark).classes();

        expect(classes).toContain('ms-0.5');
        expect(classes).not.toContain('ml-0.5');
        expect(classes).toContain('text-red-700');
        expect(classes).toContain('dark:text-red-400');
    });

    it('reads "required" in Arabic', () => {
        i18n.global.locale.value = 'ar';

        expect(mount(RequiredMark).get('.sr-only').text()).toBe('مطلوب');
    });
});

describe('RequiredLegend', () => {
    it('explains the asterisk in one line', () => {
        const wrapper = mount(RequiredLegend);

        expect(wrapper.element.tagName).toBe('P');
        expect(wrapper.get('[aria-hidden="true"]').text()).toBe('*');
        expect(wrapper.text()).toBe('* Required fields');
    });

    it('is translated into Arabic', () => {
        i18n.global.locale.value = 'ar';

        expect(mount(RequiredLegend).text()).toBe('* حقول مطلوبة');
    });
});
