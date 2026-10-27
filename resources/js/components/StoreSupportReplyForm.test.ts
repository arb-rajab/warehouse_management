import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import FieldError from '@/components/FieldError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { i18n, t } from '@/lib/i18n';
import StoreSupportReplyForm from './StoreSupportReplyForm.vue';

const state = vi.hoisted(() => ({
    errors: {} as Record<string, string>,
    processing: false,
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub } = await import('@/testing/inertiaStubs');

    return { Form: createFormStub(() => state) };
});

describe('StoreSupportReplyForm', () => {
    beforeEach(() => {
        state.errors = {};
        state.processing = false;
    });
    it.each(['message', 'reply'] as const)(
        'posts the named %s field through Inertia with native validation',
        async (field) => {
            const action = {
                url: '/temp/admin/example/reply',
                method: 'post' as const,
            };
            const wrapper = mount(StoreSupportReplyForm, {
                props: { action, field },
            });
            await wrapper.vm.$nextTick();
            expect(wrapper.text()).toContain(t('common.requiredLegend'));
            expect(wrapper.getComponent(Form).props('action')).toEqual(action);
            expect(wrapper.getComponent(Form).props('resetOnSuccess')).toBe(
                true,
            );
            expect(wrapper.get('label').text()).toBe(
                t('store.support.replyLabel') + '*' + t('common.required'),
            );
            expect(wrapper.get('label').attributes('for')).toBe(field);
            expect(wrapper.get('textarea').attributes()).toMatchObject({
                id: field,
                name: field,
                required: '',
                maxlength: '10000',
                rows: '4',
            });
            expect(wrapper.getComponent(SubmitButton).props()).toMatchObject({
                label: t('store.support.send'),
                processingLabel: t('store.support.sending'),
                processing: false,
            });
        },
    );
    it('shows the server field error and disables a pending submission', async () => {
        state.errors = { reply: 'Refused' };
        state.processing = true;
        const wrapper = mount(StoreSupportReplyForm, {
            props: { action: { url: '/reply', method: 'put' }, field: 'reply' },
        });
        await wrapper.get('textarea').setValue('Retained reply');
        expect(wrapper.getComponent(FieldError).props('message')).toBe(
            'Refused',
        );
        expect(wrapper.get('button').attributes('disabled')).toBeDefined();
        expect(wrapper.get('button').text()).toBe(t('store.support.sending'));
        expect(
            (wrapper.get('textarea').element as HTMLTextAreaElement).value,
        ).toBe('Retained reply');
    });
    it('provides English and Arabic strings for every support label', () => {
        const en = i18n.global.getLocaleMessage('en').store as {
            support: Record<string, string>;
        };
        const ar = i18n.global.getLocaleMessage('ar').store as {
            support: Record<string, string>;
        };
        expect(Object.keys(ar.support).sort()).toEqual(
            Object.keys(en.support).sort(),
        );
        expect(Object.values(ar.support).every(Boolean)).toBe(true);
    });
});
