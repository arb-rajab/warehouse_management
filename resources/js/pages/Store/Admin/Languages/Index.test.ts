import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { ComponentPublicInstance } from 'vue';
import {
    create,
    destroy,
    edit,
    show,
    showAppTranslations,
    updateStatus,
    updateRtlStatus,
    importAppTranslations,
} from '@/actions/App/Http/Controllers/Store/Admin/LanguageController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import { confirmDelete } from '@/lib/confirm';
import { syncPageLocale, t } from '@/lib/i18n';
import {
    adminLanguage,
    paginated,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import type { AdminLanguage } from '@/types/store';
import Index from './Index.vue';
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
    syncPageLocale({ locale: 'en' });
    vi.clearAllMocks();
    formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    usePageMock.mockReturnValue({
        url: '/temp/admin/languages',
        props: defaultAuthProps({
            store: storeSharedProps({ permissions: ['language_setup'] }),
        }),
    });
});
afterEach(() => syncPageLocale({ locale: 'en' }));

vi.mock('@/lib/confirm', () => ({
    confirmDelete: vi.fn(),
    confirmLogout: vi.fn(),
}));
const language = adminLanguage();
function mountPage(permissions = ['language_setup']) {
    usePageMock.mockReturnValue({
        url: '/temp/admin/languages',
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, {
        props: {
            languages: paginated([language]),
            filters: { per_page: 20 },
            defaultLanguage: 'en',
        },
    });
}
describe('Language Index', () => {
    it('renders all static labels and shares table and pagination props', () => {
        const wrapper = mountPage();

        for (const key of [
            'title',
            'create',
            'name',
            'code',
            'rtl',
            'status',
            'actions',
            'edit',
            'translations',
            'appTranslations',
            'delete',
            'importFile',
            'import',
            'translationScope',
            'mobileImportScope',
        ]) {
            expect(wrapper.text()).toContain(t(`store.languages.${key}`));
        }

        expect(wrapper.text()).toContain(
            t('store.languages.defaultReadOnly', { code: 'en' }),
        );
        expect(
            wrapper
                .getComponent<
                    ComponentPublicInstance<{
                        rows: AdminLanguage[];
                        emptyMessage: string;
                    }>
                >(DataTable)
                .props('rows'),
        ).toEqual([language]);
        expect(
            wrapper
                .getComponent<
                    ComponentPublicInstance<{
                        rows: AdminLanguage[];
                        emptyMessage: string;
                    }>
                >(DataTable)
                .props('emptyMessage'),
        ).toBe(t('store.languages.empty'));
        expect(wrapper.getComponent(AddResourceLink).props('href')).toEqual(
            create(),
        );
        expect(wrapper.getComponent(Pagination).props()).toMatchObject({
            links: [],
            perPage: 20,
        });
    });
    it.each(['en', 'ar'])(
        'explains file-managed UI and English mobile imports in %s',
        (locale) => {
            syncPageLocale({ locale });
            const wrapper = mountPage();

            for (const key of [
                'translations',
                'appTranslations',
                'translationScope',
                'mobileImportScope',
                'importFile',
            ]) {
                expect(wrapper.text()).toContain(t(`store.languages.${key}`));
            }

            expect(wrapper.text()).toContain(
                t('store.languages.defaultReadOnly', { code: 'en' }),
            );
        },
    );
    it('links to every language action and confirms deletion', () => {
        const wrapper = mountPage();
        const links = wrapper.findAllComponents(TableActionLink);
        expect(links.map((link) => link.props('href'))).toEqual([
            edit(language),
            show(language),
            showAppTranslations(language),
            destroy(language, { mergeQuery: {} }),
        ]);
        const deleteLink = links[3];
        expect(deleteLink.attributes('method')).toBe('delete');
        // The onBefore attribute falls through to the real Link stub.
        const before = (deleteLink.vm.$attrs['on-before'] ??
            deleteLink.vm.$attrs.onBefore) as () => void;
        before();
        expect(confirmDelete).toHaveBeenCalledWith(language.name);
    });
    it('submits explicit boolean status and RTL controls to their actions and imports JSON', () => {
        const forms = mountPage().findAllComponents(Form);
        expect(forms.map((form) => form.props('action'))).toEqual([
            updateRtlStatus({ mergeQuery: {} }),
            updateStatus({ mergeQuery: {} }),
            importAppTranslations(),
        ]);
        expect(forms[0].props('transform')!({ id: '3', 'rtl-3': '0' })).toEqual(
            {
                id: '3',
                status: '0',
            },
        );
        expect(
            forms[1].props('transform')!({ id: '3', 'status-3': '0' }),
        ).toEqual({ id: '3', status: '0' });
    });
    it('hides every mutation and action without language permission', () => {
        const wrapper = mountPage([]);
        expect(wrapper.findComponent(AddResourceLink).exists()).toBe(false);
        expect(wrapper.findAllComponents(TableActionLink)).toHaveLength(0);
        expect(wrapper.findAllComponents(Form)).toHaveLength(0);
    });
    it('omits delete for English and default languages and locks default activation', () => {
        const wrapper = mount(Index, {
            props: {
                languages: paginated([
                    adminLanguage({ is_default: true, is_english: true }),
                ]),
                filters: {},
                defaultLanguage: 'en',
            },
        });
        expect(wrapper.findAllComponents(TableActionLink)).toHaveLength(3);
        expect(
            wrapper.get('[name="status-3"]').attributes('disabled'),
        ).toBeDefined();
    });
    it('changes page size through the languages route', () => {
        mountPage().getComponent(Pagination).vm.$emit('update:per-page', 10);
        expect(routerGetMock).toHaveBeenCalledWith(
            '/temp/admin/languages',
            { per_page: 10 },
            { preserveState: true, replace: true },
        );
    });
    it('gives each editable status and import form its own required legend, allowing false', async () => {
        const wrapper = mountPage();
        await wrapper.vm.$nextTick();
        const forms = wrapper.findAll('form');
        expect(forms).toHaveLength(3);

        for (const form of forms) {
            expect(form.findAll('[data-required-legend]')).toHaveLength(1);
            expect(form.findAll('[data-required-mark]')).toHaveLength(1);
            expect(form.findAll('[required]')).toHaveLength(1);
        }

        await wrapper.get('[name="rtl-3"]').setValue('0');
        expect(
            wrapper
                .get<HTMLSelectElement>('[name="rtl-3"]')
                .element.checkValidity(),
        ).toBe(true);
        await wrapper.setProps({
            languages: paginated([adminLanguage({ is_default: true })]),
        });
        await wrapper.vm.$nextTick();
        expect(
            wrapper.findAll('form')[1].find('[data-required-legend]').exists(),
        ).toBe(false);
        expect(
            wrapper.findAll('form')[1].find('[data-required-mark]').exists(),
        ).toBe(false);
    });
});
