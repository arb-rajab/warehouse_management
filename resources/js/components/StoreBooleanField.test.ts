import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { t } from '@/lib/i18n';
import StoreBooleanField from './StoreBooleanField.vue';
describe('StoreBooleanField', () => {
    it.each([true, false, undefined])(
        'posts a native boolean value for %s',
        (value) => {
            const wrapper = mount(StoreBooleanField, {
                props: {
                    id: 'status',
                    label: 'Status',
                    value,
                    error: 'Invalid status',
                },
            });
            expect(wrapper.get('select').attributes()).toMatchObject({
                name: 'status',
                required: '',
            });
            expect(wrapper.get('select').element).toHaveProperty(
                'value',
                value ? '1' : '0',
            );
            expect(
                wrapper.findAll('option').map((option) => option.text()),
            ).toEqual([
                t('store.reference.enabled'),
                t('store.reference.disabled'),
            ]);
            expect(wrapper.get('label').attributes('for')).toBe('status');
            expect(wrapper.text()).toContain('Invalid status');
        },
    );
});
