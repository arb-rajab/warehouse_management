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
import StoreStatusSwitch from '@/components/StoreStatusSwitch.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import { confirmDelete } from '@/lib/confirm';
import { i18n, syncPageLocale, t } from '@/lib/i18n';
import {
    adminLanguage,
    paginated,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import {
    expectColumnCanBeHidden,
    expectColumnLocked,
} from '@/testing/tableColumns';
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
    it('posts RTL and activation as switches with the inverse value and imports JSON', () => {
        const wrapper = mountPage();
        const forms = wrapper.findAllComponents(Form);
        expect(forms.map((form) => form.props('action'))).toEqual([
            updateRtlStatus({ mergeQuery: {} }),
            updateStatus({ mergeQuery: {} }),
            importAppTranslations(),
        ]);
        const switches = wrapper.findAllComponents(StoreStatusSwitch);
        expect(switches.map((item) => item.props())).toMatchObject([
            {
                action: updateRtlStatus({ mergeQuery: {} }),
                checked: true,
                label: t('store.languages.rtlFor', { name: 'Arabic' }),
                locked: undefined,
            },
            {
                action: updateStatus({ mergeQuery: {} }),
                checked: true,
                label: t('store.languages.statusFor', { name: 'Arabic' }),
                locked: undefined,
            },
        ]);

        for (const form of forms.slice(0, 2)) {
            expect(form.get('input[name=id]').attributes('value')).toBe('3');
            expect(form.get('input[name=status]').attributes('value')).toBe(
                '0',
            );
            expect(form.get('[role="switch"]').attributes('aria-checked')).toBe(
                'true',
            );
        }

        const off = mount(Index, {
            props: {
                languages: paginated([
                    adminLanguage({ rtl: false, status: false }),
                ]),
                filters: {},
                defaultLanguage: 'en',
            },
        });
        expect(
            off
                .findAll('input[name=status]')
                .map((input) => input.attributes('value')),
        ).toEqual(['1', '1']);
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
        const [rtl, status] = wrapper.findAllComponents(StoreStatusSwitch);
        expect(rtl.props('locked')).toBeUndefined();
        expect(status.props('locked')).toEqual({
            reason: t('store.languages.defaultLocked'),
            hint: t('store.languages.defaultReadOnly', { code: 'en' }),
        });
        expect(status.findComponent(Form).exists()).toBe(false);
        expect(status.get('[role="switch"]').attributes()).toMatchObject({
            'aria-checked': 'true',
            'aria-disabled': 'true',
        });
        expect(status.text()).toContain(t('store.languages.defaultLocked'));
        expect(wrapper.findAllComponents(Form)).toHaveLength(2);
    });
    it('changes page size through the languages route', () => {
        mountPage().getComponent(Pagination).vm.$emit('update:per-page', 10);
        expect(routerGetMock).toHaveBeenCalledWith(
            '/temp/admin/languages',
            { per_page: 10 },
            { preserveState: true, replace: true },
        );
    });
    it('keeps the required legend on the import form only; the switches post fixed values', async () => {
        const wrapper = mountPage();
        await wrapper.vm.$nextTick();
        const forms = wrapper.findAll('form');
        expect(forms).toHaveLength(3);

        for (const form of forms.slice(0, 2)) {
            expect(form.find('[data-required-legend]').exists()).toBe(false);
            expect(form.find('[data-required-mark]').exists()).toBe(false);
            expect(form.find('[required]').exists()).toBe(false);
            expect(form.find('select').exists()).toBe(false);
        }

        expect(forms[2].findAll('[data-required-legend]')).toHaveLength(1);
        expect(forms[2].findAll('[data-required-mark]')).toHaveLength(1);
        expect(forms[2].findAll('[required]')).toHaveLength(1);
        expect(i18n.global.te('store.languages.rtlFor', 'ar')).toBe(true);
        expect(i18n.global.te('store.languages.statusFor', 'ar')).toBe(true);
        expect(i18n.global.te('store.languages.defaultLocked', 'ar')).toBe(
            true,
        );
    });

    it('lets staff hide an optional column from the header and every row, and keeps the identifier', async () => {
        window.localStorage.clear();
        const wrapper = mountPage();
        await expectColumnCanBeHidden(wrapper, 'languages.languages', {
            key: 'code',
            label: t('store.languages.code'),
        });
        expectColumnLocked(wrapper, t('store.languages.name'));
        window.localStorage.clear();
    });
});
