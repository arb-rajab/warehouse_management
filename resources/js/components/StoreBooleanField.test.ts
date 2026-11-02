import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { t } from '@/lib/i18n';
import StoreBooleanField from './StoreBooleanField.vue';

describe('StoreBooleanField', () => {
    it.each([true, false])(
        'sends an explicit boolean selection for %s',
        (value) => {
            const wrapper = mount(StoreBooleanField, {
                props: {
                    name: 'settings[google_analytics]',
                    label: 'Analytics',
                    value,
                    error: 'Invalid',
                },
            });
            expect(wrapper.get('label').text()).toBe('Analytics*required');
            expect(wrapper.get('label').attributes('for')).toBe(
                'settings[google_analytics]',
            );
            const select = wrapper.get('select');
            expect(select.attributes('name')).toBe(
                'settings[google_analytics]',
            );
            expect(select.element.value).toBe(value ? '1' : '0');
            expect(select.attributes('required')).toBeDefined();
            expect(
                wrapper.findAll('option').map((option) => option.text()),
            ).toEqual([
                t('store.settings.enabled'),
                t('store.settings.disabled'),
            ]);
            expect(wrapper.text()).toContain('Invalid');
        },
    );
    it('disables a protected value', () => {
        const wrapper = mount(StoreBooleanField, {
            props: {
                name: 'status',
                label: 'Active',
                value: true,
                disabled: true,
            },
        });
        expect(wrapper.get('select').attributes('disabled')).toBeDefined();
    });

    it('marks, scrolls to and focuses the option it is asked to highlight', async () => {
        const scrollIntoView = vi.fn();
        HTMLElement.prototype.scrollIntoView = scrollIntoView;
        const wrapper = mount(StoreBooleanField, {
            attachTo: document.body,
            props: {
                name: 'settings[cash_payment]',
                label: 'Cash payment',
                value: false,
                highlighted: true,
            },
        });
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        expect(wrapper.attributes('data-highlighted')).toBeDefined();
        expect(scrollIntoView).toHaveBeenCalled();
        expect(document.activeElement).toBe(wrapper.get('select').element);
        wrapper.unmount();
    });

    it('leaves an ordinary option unmarked and unfocused', async () => {
        const wrapper = mount(StoreBooleanField, {
            attachTo: document.body,
            props: { name: 'settings[x]', label: 'X', value: true },
        });
        await wrapper.vm.$nextTick();
        expect(wrapper.attributes('data-highlighted')).toBeUndefined();
        expect(document.activeElement).not.toBe(wrapper.get('select').element);
        wrapper.unmount();
    });
});
