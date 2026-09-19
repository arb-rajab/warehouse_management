import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { t } from '@/lib/i18n';
import Welcome from './Welcome.vue';

vi.mock('@inertiajs/vue3', async () => {
    const { headStub } = await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
    };
});

describe('Welcome', () => {
    it('renders the brand title and the placeholder message', () => {
        const wrapper = mount(Welcome);

        expect(wrapper.text()).toContain(t('welcome.brand'));
        expect(wrapper.text()).toContain(t('welcome.message'));
    });
});
