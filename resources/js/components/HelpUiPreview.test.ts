import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import HelpUiPreview from './HelpUiPreview.vue';

describe('HelpUiPreview', () => {
    it('renders its slot content, marked decorative and non-interactive', () => {
        const wrapper = mount(HelpUiPreview, {
            slots: { default: '<button>Add row</button>' },
        });

        expect(wrapper.text()).toContain('Add row');
        expect(wrapper.attributes('aria-hidden')).toBe('true');
        expect(wrapper.classes()).toContain('pointer-events-none');
    });
});
