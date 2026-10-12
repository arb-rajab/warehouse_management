import { Form, Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    create,
    destroy,
    edit,
    index as packagesIndex,
    store,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/CustomerPackageController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import Pagination from '@/components/Pagination.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreCustomerPackageFormFields from '@/components/StoreCustomerPackageFormFields.vue';
import StoreLanguageTabs from '@/components/StoreLanguageTabs.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import {
    paginated,
    storeCustomerPackage,
    storeSharedProps,
    storeUpload,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Create from './Create.vue';
import Edit from './Edit.vue';
import Index from './Index.vue';
const { usePageMock, routerGetMock, formSlotPropsMock, submitMock } =
    vi.hoisted(() => ({
        usePageMock: vi.fn(),
        routerGetMock: vi.fn(),
        formSlotPropsMock: vi.fn(() => ({ errors: {}, processing: false })),
        submitMock: vi.fn(),
    }));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, createUseFormStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(formSlotPropsMock),
        Link: createLinkStub(),
        useHttp: () => ({ processing: false, get: vi.fn(), cancel: vi.fn() }),
        useForm: createUseFormStub(submitMock),
        usePage: usePageMock,
        router: { get: routerGetMock, post: vi.fn() },
    };
});
const languages = [
    { code: 'en', name: 'English' },
    { code: 'ar', name: 'Arabic' },
];
function indexPage(
    permissions = [
        'view_classified_packages',
        'edit_classified_package',
        'delete_classified_package',
    ],
    empty = false,
) {
    usePageMock.mockReturnValue({
        url: packagesIndex.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, {
        props: {
            packages: paginated(empty ? [] : [storeCustomerPackage()]),
            filters: { per_page: 50 },
            defaultLanguage: 'en',
        },
    });
}
describe('customer package pages', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
        usePageMock.mockReturnValue({
            url: packagesIndex.url(),
            props: defaultAuthProps({
                store: storeSharedProps({
                    permissions: ['view_uploads', 'edit_classified_package'],
                }),
            }),
        });
    });
    afterEach(() => {
        i18n.global.locale.value = 'en';
        vi.restoreAllMocks();
    });
    it.each(['en', 'ar'] as const)(
        'renders list labels and all package data in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = indexPage();
            expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
            expect(wrapper.getComponent(Head).props('title')).toBe(
                t('store.customerPackages.title'),
            );
            expect(wrapper.get('h1').text()).toBe(
                t('store.customerPackages.title'),
            );
            expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual(
                ['name', 'logo', 'amount', 'uploads', 'actions'].map((k) =>
                    t(`store.customerPackages.${k}`),
                ),
            );
            expect(wrapper.get('tbody').text()).toContain('Gold');
            expect(wrapper.get('tbody').text()).toContain('€12.50');
            expect(wrapper.get('tbody').text()).toContain('10');
            expect(wrapper.get('tbody img').attributes()).toMatchObject({
                src: '/uploads/gold.png',
                alt: t('store.customerPackages.logo'),
            });
            expect(wrapper.getComponent(AddResourceLink).props()).toMatchObject(
                { href: create(), label: t('store.customerPackages.create') },
            );
            expect(
                wrapper
                    .findAllComponents(TableActionLink)
                    .map((l) => l.props('href')),
            ).toEqual([
                edit(storeCustomerPackage(), { query: { lang: 'en' } }),
                destroy(storeCustomerPackage(), { mergeQuery: {} }),
            ]);
            expect(wrapper.text()).toContain(t('store.customerPackages.edit'));
            expect(wrapper.text()).toContain(t('store.reference.delete'));
        },
    );
    it('gates create and edit by the actual edit permission and delete separately', () => {
        const read = indexPage([
            'view_classified_packages',
            'add_classified_package',
        ]);
        expect(read.findComponent(AddResourceLink).exists()).toBe(false);
        expect(read.findAllComponents(TableActionLink)).toHaveLength(0);
        const deletion = indexPage(['delete_classified_package']);
        expect(deletion.findComponent(AddResourceLink).exists()).toBe(false);
        expect(deletion.findAllComponents(TableActionLink)).toHaveLength(1);
        expect(deletion.getComponent(TableActionLink).props('href')).toEqual(
            destroy(storeCustomerPackage(), { mergeQuery: {} }),
        );
    });
    it('confirms deletes, shows empty state and uses pagination', async () => {
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const wrapper = indexPage();
        const deletion = wrapper
            .findAllComponents(TableActionLink)[1]
            .getComponent(Link);
        expect((deletion.props('onBefore') as () => boolean)()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', { label: 'Gold' }),
        );
        expect(deletion.attributes('method')).toBe('delete');
        expect(deletion.props('as')).toBe('button');
        expect(wrapper.getComponent(Pagination).props('perPage')).toBe(50);
        wrapper.getComponent(Pagination).vm.$emit('update:perPage', 10);
        expect(routerGetMock).toHaveBeenCalledWith(
            packagesIndex().url,
            { per_page: 10 },
            { preserveState: true, replace: true },
        );
        expect(indexPage([], true).text()).toContain(
            t('store.customerPackages.empty'),
        );
    });
    it.each(['en', 'ar'] as const)(
        'renders create and edit forms with Wayfinder actions and localized copy in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const creation = mount(Create);
            const editing = mount(Edit, {
                props: {
                    package: storeCustomerPackage(),
                    name: 'ذهبية',
                    lang: 'ar',
                    languages,
                    initialFiles: [storeUpload()],
                },
            });

            for (const [wrapper, action, title] of [
                [creation, store(), 'create'],
                [editing, update(storeCustomerPackage()), 'edit'],
            ] as const) {
                expect(
                    wrapper.getComponent(ResourceFormPage).props(),
                ).toMatchObject({
                    layout: StoreAdminLayout,
                    title: t(`store.customerPackages.${title}`),
                    submitLabel: t('store.reference.save'),
                    submittingLabel: t('store.reference.saving'),
                    cancelHref: packagesIndex(),
                });
                expect(wrapper.getComponent(Form).props('action')).toEqual(
                    action,
                );
                expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(
                    true,
                );

                for (const key of ['name', 'amount', 'uploads', 'logo']) {
                    expect(wrapper.text()).toContain(
                        t(`store.customerPackages.${key}`),
                    );
                }
            }

            expect(
                editing.getComponent(StoreLanguageTabs).props(),
            ).toMatchObject({ languages, lang: 'ar' });
            expect(
                editing.getComponent(StoreLanguageTabs).props('href')('en'),
            ).toEqual(edit(storeCustomerPackage(), { query: { lang: 'en' } }));
            expect(editing.get('input[name=lang]').attributes('value')).toBe(
                'ar',
            );
            expect(
                editing.getComponent(StoreCustomerPackageFormFields).props(),
            ).toMatchObject({
                package: storeCustomerPackage(),
                name: 'ذهبية',
                initialFiles: [storeUpload()],
            });
        },
    );
    it('forwards authoritative errors and processing state to both forms', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { name: 'Refused name' },
            processing: true,
        });
        const wrappers = [
            mount(Create),
            mount(Edit, {
                props: {
                    package: storeCustomerPackage(),
                    name: 'Gold',
                    lang: 'en',
                    languages,
                    initialFiles: [],
                },
            }),
        ];

        for (const wrapper of wrappers) {
            expect(
                wrapper
                    .getComponent(StoreCustomerPackageFormFields)
                    .props('errors'),
            ).toEqual({ name: 'Refused name' });
            expect(wrapper.getComponent(SubmitButton).props('processing')).toBe(
                true,
            );
        }
    });
});
