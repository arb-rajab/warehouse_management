import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { ComponentPublicInstance } from 'vue';
import {
    exportAppTranslations,
    storeTranslations,
    storeAppTranslations,
} from '@/actions/App/Http/Controllers/Store/Admin/LanguageController';
import DataTable from '@/components/DataTable.vue';
import Pagination from '@/components/Pagination.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import { t } from '@/lib/i18n';
import {
    adminLanguage,
    paginated,
    storeTranslation,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import type { StoreTranslation } from '@/types/store';
import Translations from './Translations.vue';
const { usePageMock, formSlotPropsMock, routerGetMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    formSlotPropsMock: vi.fn(() => ({ errors: {}, processing: false })),
    routerGetMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(formSlotPropsMock),
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { get: routerGetMock, post: vi.fn() },
    };
});
beforeEach(() => {
    vi.clearAllMocks();
    formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    usePageMock.mockReturnValue({
        url: '/temp/admin/languages',
        props: defaultAuthProps({
            store: storeSharedProps({ permissions: ['language_setup'] }),
        }),
    });
});

const language = adminLanguage();
const translation = storeTranslation({
    key: 'hello.world',
    source: '<script>alert(1)</script>',
});
function mountPage(app = false) {
    return mount(Translations, {
        props: {
            language,
            app,
            translations: paginated([translation], 50),
            filters: { search: null, per_page: 50 },
        },
    });
}
describe('Language Translations', () => {
    it.each([false, true])(
        'renders %s translation actions, labels and safe text',
        (app) => {
            const wrapper = mountPage(app);
            expect(wrapper.getComponent(Form).props('action')).toEqual(
                (app ? storeAppTranslations : storeTranslations)({
                    mergeQuery: {},
                }),
            );
            expect(wrapper.getComponent(ResourceFormPage).props('title')).toBe(
                t(
                    app
                        ? 'store.languages.appTranslations'
                        : 'store.languages.translations',
                ),
            );

            for (const key of [
                'translationScope',
                'search',
                'copy',
                'key',
                'source',
                'value',
            ]) {
                expect(wrapper.text()).toContain(t(`store.languages.${key}`));
            }

            expect(wrapper.find('script').exists()).toBe(false);
            expect(wrapper.text()).toContain('<script>alert(1)</script>');
            expect(
                wrapper
                    .getComponent<
                        ComponentPublicInstance<{
                            rows: StoreTranslation[];
                            emptyMessage: string;
                        }>
                    >(DataTable)
                    .props('rows'),
            ).toEqual([translation]);
            expect(
                wrapper
                    .getComponent<
                        ComponentPublicInstance<{
                            rows: StoreTranslation[];
                            emptyMessage: string;
                        }>
                    >(DataTable)
                    .props('emptyMessage'),
            ).toBe(t('store.languages.noTranslations'));
            expect(wrapper.getComponent(Pagination).props()).toMatchObject({
                links: [],
                perPage: 50,
            });
            expect(
                wrapper
                    .get('[name="translations[0][key]"]')
                    .attributes('value'),
            ).toBe('hello.world');
            expect(
                wrapper
                    .get('[name="translations[0][value]"]')
                    .attributes('maxlength'),
            ).toBe(app ? '255' : '65535');

            if (app) {
                expect(
                    wrapper
                        .get(`a[href="${exportAppTranslations.url(language)}"]`)
                        .text(),
                ).toBe(t('store.languages.export'));
            }
        },
    );
    it('copies only the visible English values into the existing inputs', async () => {
        const wrapper = mountPage();
        await wrapper
            .findAll('button')
            .find((button) => button.text() === t('store.languages.copy'))!
            .trigger('click');
        expect(
            (
                wrapper.get('[name="translations[0][value]"]')
                    .element as HTMLInputElement
            ).value,
        ).toBe(translation.source);
    });
    it('searches and changes page size through the appropriate translation action', async () => {
        const wrapper = mountPage(true);
        await wrapper.get('#search').setValue('hello');
        wrapper.getComponent(Pagination).vm.$emit('update:per-page', 10);
        expect(routerGetMock).toHaveBeenCalledWith(
            '/temp/admin/languages/app-translations/show/3',
            { search: 'hello', per_page: 10 },
            { preserveState: false, replace: true },
        );
    });
    it('forwards field validation errors and hides copy and export without permission', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { 'translations.0.value': 'Invalid value' },
            processing: false,
        });
        usePageMock.mockReturnValue({
            url: '/temp/admin/languages',
            props: defaultAuthProps({
                store: storeSharedProps({ permissions: [] }),
            }),
        });
        const wrapper = mountPage(true);
        expect(wrapper.text()).toContain('Invalid value');
        expect(
            wrapper
                .get('[name="translations[0][value]"]')
                .attributes('readonly'),
        ).toBeDefined();
        expect(
            wrapper
                .findAll('button')
                .some((button) => button.text() === t('store.languages.copy')),
        ).toBe(false);
        expect(
            wrapper
                .find(`a[href="${exportAppTranslations.url(language)}"]`)
                .exists(),
        ).toBe(false);
    });
    it.each([false, true])(
        'keeps nullable translation values and UI search unmarked app=%s',
        (app) => {
            const wrapper = mountPage(app);
            expect(wrapper.findAll('[required]')).toHaveLength(0);
            expect(wrapper.findAll('[data-required-mark]')).toHaveLength(0);
            expect(wrapper.find('[data-required-legend]').exists()).toBe(false);
            expect(
                wrapper
                    .get('[name="translations[0][key]"]')
                    .attributes('value'),
            ).toBe(translation.key);
        },
    );
});
