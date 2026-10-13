import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import FormField from '@/components/FormField.vue';
import { t } from '@/lib/i18n';
import { adminLanguage } from '@/testing/factories';
import StoreLanguageFormFields from './StoreLanguageFormFields.vue';

describe('StoreLanguageFormFields', () => {
    it('renders every label, constraint, value and validation error', () => {
        const wrapper = mount(StoreLanguageFormFields, {
            props: {
                language: adminLanguage(),
                errors: {
                    name: 'Name error',
                    code: 'Code error',
                    app_lang_code: 'Mobile error',
                },
            },
        });
        expect(
            wrapper
                .findAllComponents(FormField)
                .map((field) => field.props('label')),
        ).toEqual(
            ['name', 'code', 'appCode'].map((key) =>
                t(`store.languages.${key}`),
            ),
        );
        expect(wrapper.get('#name').attributes('value')).toBe('Arabic');
        expect(wrapper.get('#code').attributes('value')).toBe('ar');
        expect(wrapper.get('#app_lang_code').attributes('value')).toBe('ar');

        for (const field of wrapper.findAll('input')) {
            expect(field.attributes('required')).toBeDefined();
        }

        expect(wrapper.get('#name').attributes('maxlength')).toBe('100');

        for (const key of ['code', 'app_lang_code']) {
            expect(wrapper.get(`#${key}`).attributes('maxlength')).toBe('10');
            const input = wrapper.get<HTMLInputElement>(`#${key}`).element;
            input.value = 'invalid-code';
            expect(input.validity.patternMismatch).toBe(true);
            input.value = 'ar_SA';
            expect(input.validity.patternMismatch).toBe(false);
        }

        for (const error of ['Name error', 'Code error', 'Mobile error']) {
            expect(wrapper.text()).toContain(error);
        }
    });
    it.each(['is_default', 'is_english'] as const)(
        'keeps protected %s codes read-only',
        (key) => {
            const wrapper = mount(StoreLanguageFormFields, {
                props: { language: adminLanguage({ [key]: true }), errors: {} },
            });
            expect(wrapper.get('#code').attributes('readonly')).toBeDefined();
        },
    );
    it('allows ordinary language codes to be edited', () => {
        const wrapper = mount(StoreLanguageFormFields, {
            props: { language: adminLanguage(), errors: {} },
        });
        expect(wrapper.get('#code').attributes('readonly')).toBeUndefined();
    });
});
