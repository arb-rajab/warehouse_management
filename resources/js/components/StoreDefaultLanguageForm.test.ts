import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { updateDefault } from '@/actions/App/Http/Controllers/Store/Admin/LanguageController';
import { confirmDefaultLanguageChange } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import ar from '@/locales/ar.json';
import en from '@/locales/en.json';
import { defaultLanguageSetting } from '@/testing/factories';
import StoreDefaultLanguageForm from './StoreDefaultLanguageForm.vue';

const { slotMock } = vi.hoisted(() => ({
    slotMock: vi.fn(() => ({ errors: {}, processing: false })),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub } = await import('@/testing/inertiaStubs');

    return { Form: createFormStub(slotMock) };
});
vi.mock('@/lib/confirm', () => ({
    confirmDefaultLanguageChange: vi.fn(() => true),
}));

function mountForm(
    setting = defaultLanguageSetting(),
    highlight: string | null = null,
) {
    return mount(StoreDefaultLanguageForm, {
        props: { setting, highlight },
        attachTo: document.body,
    });
}

beforeEach(() => {
    vi.clearAllMocks();
    slotMock.mockReturnValue({ errors: {}, processing: false });
});

describe('StoreDefaultLanguageForm', () => {
    it('posts to the default language route with an optional select', () => {
        const wrapper = mountForm();

        expect(wrapper.get('form').attributes('data-action-url')).toBe(
            updateDefault().url,
        );
        expect(wrapper.get('select').attributes('name')).toBe(
            'default_language',
        );
        expect(wrapper.find('[required]').exists()).toBe(false);
        expect(wrapper.find('[data-required-mark]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('shows the language in use and "from .env" when nothing is saved, with the .env choice selected', () => {
        const wrapper = mountForm();

        expect(wrapper.get('[data-testid="default-in-use"]').text()).toBe(
            t('store.languages.defaultInUse', {
                code: 'en',
                source: t('store.languages.defaultSources.env'),
            }),
        );
        const select = wrapper.get('select').element as HTMLSelectElement;
        expect(select.value).toBe('');
        expect(wrapper.get('select').findAll('option')[0].text()).toBe(
            t('store.languages.defaultServer', { code: 'en' }),
        );
        expect(wrapper.find('[data-warning]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('shows the saved language selected and "saved in admin"', () => {
        const wrapper = mountForm(
            defaultLanguageSetting({
                saved: 'ar',
                effective: { value: 'ar', source: 'admin' },
            }),
        );

        expect((wrapper.get('select').element as HTMLSelectElement).value).toBe(
            'ar',
        );
        expect(wrapper.get('[data-testid="default-in-use"]').text()).toContain(
            t('store.languages.defaultSources.admin'),
        );
        wrapper.unmount();
    });

    it('lists only the options it was given and marks a saved language that is switched off', () => {
        const wrapper = mountForm(
            defaultLanguageSetting({
                saved: 'nl',
                effective: { value: 'nl', source: 'admin' },
                state: 'inactive',
                options: [
                    { code: 'en', name: 'English', active: true },
                    { code: 'nl', name: 'Dutch', active: false },
                ],
            }),
        );
        const options = wrapper.get('select').findAll('option');

        expect(options.map((option) => option.attributes('value'))).toEqual([
            '',
            'en',
            'nl',
        ]);
        expect(options[2].text()).toContain(
            t('store.languages.defaultSwitchedOff'),
        );
        expect((wrapper.get('select').element as HTMLSelectElement).value).toBe(
            'nl',
        );
        expect(wrapper.get('[data-warning="default_language"]').text()).toBe(
            t('store.languages.default_inactive', { code: 'nl' }),
        );
        wrapper.unmount();
    });

    it.each([
        ['missing', 'default_missing'],
        ['inactive', 'default_inactive'],
    ] as const)(
        'warns, naming the exact language, when the one in use is %s',
        (state, key) => {
            const wrapper = mountForm(
                defaultLanguageSetting({
                    effective: { value: 'xx', source: 'env' },
                    environment: 'xx',
                    state,
                }),
            );

            expect(
                wrapper.get('[data-warning="default_language"]').text(),
            ).toBe(t(`store.languages.${key}`, { code: 'xx' }));
            wrapper.unmount();
        },
    );

    it('says so when no default language is configured at all', () => {
        const wrapper = mountForm(
            defaultLanguageSetting({
                effective: { value: null, source: 'env' },
                environment: null,
                state: 'missing',
            }),
        );

        expect(wrapper.get('[data-warning="default_language"]').text()).toBe(
            t('store.languages.defaultNone'),
        );
        wrapper.unmount();
    });

    it('asks before changing the language in use, and does not submit when declined', async () => {
        vi.mocked(confirmDefaultLanguageChange).mockReturnValueOnce(false);
        const wrapper = mountForm();

        await wrapper.get('select').setValue('ar');
        await wrapper.get('form').trigger('submit');

        expect(confirmDefaultLanguageChange).toHaveBeenCalledWith('Arabic');
        wrapper.unmount();
    });

    it('does not ask when the choice leaves the language in use unchanged', async () => {
        const wrapper = mountForm();

        await wrapper.get('select').setValue('');
        await wrapper.get('form').trigger('submit');
        await wrapper.get('select').setValue('en');
        await wrapper.get('form').trigger('submit');

        expect(confirmDefaultLanguageChange).toHaveBeenCalledTimes(0);
        wrapper.unmount();
    });

    it('marks, scrolls to and focuses the control for a checklist deep link, and nothing without one', async () => {
        const scrollIntoView = vi.fn();
        Element.prototype.scrollIntoView = scrollIntoView;
        const wrapper = mountForm(defaultLanguageSetting(), 'default_language');
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const marked = wrapper.findAll('[data-highlighted]');
        expect(marked).toHaveLength(1);
        expect(marked[0].attributes('data-section')).toBe('default_language');
        expect(scrollIntoView).toHaveBeenCalledWith({ block: 'center' });
        expect(document.activeElement).toBe(wrapper.get('select').element);
        wrapper.unmount();

        const plain = mountForm();
        expect(plain.findAll('[data-highlighted]')).toHaveLength(0);
        plain.unmount();
    });

    it('shows the server error and keeps the select an ordinary control', () => {
        slotMock.mockReturnValue({
            errors: { default_language: 'The language "xx" does not exist.' },
            processing: false,
        });
        const wrapper = mountForm();

        expect(wrapper.text()).toContain('The language "xx" does not exist.');
        wrapper.unmount();
    });

    it('has the same default-language strings in English and Arabic', () => {
        expect(Object.keys(ar.store.languages).sort()).toEqual(
            Object.keys(en.store.languages).sort(),
        );

        for (const key of ['default_missing', 'default_inactive'] as const) {
            expect(
                ar.store.dashboard.checklist.reasons.language[key],
            ).toContain('{code}');
            expect(
                en.store.dashboard.checklist.reasons.language[key],
            ).toContain('{code}');
        }
    });
});
