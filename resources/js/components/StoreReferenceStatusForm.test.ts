import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { t } from '@/lib/i18n';
import StoreReferenceStatusForm from './StoreReferenceStatusForm.vue';

const { formSlotProps } = vi.hoisted(() => ({
    formSlotProps: vi.fn(() => ({ errors: {}, processing: false })),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub } = await import('@/testing/inertiaStubs');

    return { Form: createFormStub(formSlotProps) };
});

describe('StoreReferenceStatusForm', () => {
    it('submits the opposite status to the supplied action', () => {
        const action = { url: '/status', method: 'patch' as const };
        const wrapper = mount(StoreReferenceStatusForm, {
            props: { action, active: true },
        });

        expect(wrapper.getComponent(Form).props('action')).toEqual(action);
        expect(wrapper.get('input').attributes('value')).toBe('0');
        expect(wrapper.text()).toContain(t('store.reference.active'));
    });
});
