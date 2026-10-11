import { Form, Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { ComponentPublicInstance } from 'vue';
import {
    destroy,
    download,
    index as offersIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/OffersCatalogController';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import Pagination from '@/components/Pagination.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import {
    paginated,
    storeOffersCatalog,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Index from './Index.vue';

const { usePageMock, routerGetMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, createFormStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        Form: createFormStub(() => ({
            errors: { catalog: 'Upload refused' },
            processing: false,
        })),
        usePage: usePageMock,
        router: { get: routerGetMock, post: vi.fn() },
    };
});

function mountPage({
    existing = false,
    permissions = [
        'view_offers_catalog',
        'add_offers_catalog',
        'delete_offers_catalog',
    ],
} = {}) {
    usePageMock.mockReturnValue({
        url: offersIndex.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, {
        props: {
            catalogs: paginated(existing ? [storeOffersCatalog()] : []),
            hasCatalog: existing,
            filters: { per_page: 20 },
        },
    });
}

describe('Store offers catalogue', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        i18n.global.locale.value = 'en';
    });
    afterEach(() => {
        vi.restoreAllMocks();
        i18n.global.locale.value = 'en';
    });

    it('renders the layout header head every column and the empty state', () => {
        const wrapper = mountPage();
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('store.offersCatalog.title'),
        );
        expect(wrapper.get('h1').text()).toBe(t('store.offersCatalog.title'));
        expect(
            wrapper
                .getComponent<ComponentPublicInstance<{ columns: string[] }>>(
                    DataTable,
                )
                .props('columns'),
        ).toEqual([
            t('store.offersCatalog.file'),
            t('store.offersCatalog.createdAt'),
            t('store.offersCatalog.actions'),
        ]);
        expect(wrapper.text()).toContain(t('store.offersCatalog.empty'));
    });

    it('uses the upload action and mirrors file validation with a required PDF picker and errors', () => {
        const wrapper = mountPage();
        expect(wrapper.getComponent(Form).props('action')).toEqual(store());
        expect(wrapper.getComponent(FormField).props()).toMatchObject({
            id: 'catalog',
            type: 'file',
            label: t('store.offersCatalog.file'),
            error: 'Upload refused',
        });
        expect(wrapper.get('#catalog').attributes()).toMatchObject({
            name: 'catalog',
            type: 'file',
            accept: '.pdf,application/pdf',
            required: '',
        });
        expect(wrapper.text()).toContain(t('store.offersCatalog.limit'));
        expect(wrapper.getComponent(SubmitButton).props()).toMatchObject({
            label: t('store.offersCatalog.upload'),
            processingLabel: t('store.offersCatalog.uploading'),
            processing: false,
        });
    });

    it('rejects oversized PDFs natively and clears the error for an allowed replacement', async () => {
        const input = mountPage().get<HTMLInputElement>('#catalog');
        Object.defineProperty(input.element, 'files', {
            configurable: true,
            value: [{ size: 50 * 1024 * 1024 + 1 }],
        });
        await input.trigger('change');
        expect(input.element.validationMessage).toBe(
            t('store.offersCatalog.limit'),
        );
        Object.defineProperty(input.element, 'files', {
            configurable: true,
            value: [{ size: 50 * 1024 * 1024 }],
        });
        await input.trigger('change');
        expect(input.element.validity.customError).toBe(false);
    });

    it('hides upload when a catalogue exists or its permission is missing', () => {
        expect(mountPage({ existing: true }).findComponent(Form).exists()).toBe(
            false,
        );
        expect(
            mountPage({ permissions: ['view_offers_catalog'] })
                .findComponent(Form)
                .exists(),
        ).toBe(false);
    });

    it('uses a full PDF download and a confirmed DELETE with the current query', () => {
        const wrapper = mountPage({ existing: true });
        const fileLink = wrapper
            .findAll('a')
            .find((link) => link.attributes('href') === download.url());
        expect(fileLink?.text()).toBe(t('store.offersCatalog.download'));
        const action = wrapper.getComponent(TableActionLink);
        expect(action.props()).toMatchObject({
            href: destroy(storeOffersCatalog(), { mergeQuery: {} }),
            variant: 'danger',
        });
        expect(action.attributes('method')).toBe('delete');
        expect(action.text()).toBe(t('store.offersCatalog.delete'));
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const onBefore = action
            .getComponent(Link)
            .props('onBefore') as () => boolean;
        expect(onBefore()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', {
                label: t('store.offersCatalog.deleteLabel'),
            }),
        );
    });

    it('hides deletion without its permission', () => {
        expect(
            mountPage({ existing: true, permissions: ['view_offers_catalog'] })
                .findComponent(TableActionLink)
                .exists(),
        ).toBe(false);
    });

    it('passes pagination links and changes the page size', () => {
        const wrapper = mountPage();
        const pagination = wrapper.getComponent(Pagination);
        expect(pagination.props()).toMatchObject({ links: [], perPage: 20 });
        pagination.vm.$emit('update:perPage', 10);
        expect(routerGetMock).toHaveBeenCalledWith(
            offersIndex.url(),
            { per_page: 10 },
            { preserveState: true, replace: true },
        );
    });

    it('renders translated Arabic upload labels', () => {
        i18n.global.locale.value = 'ar';
        const wrapper = mountPage();
        expect(wrapper.get('h1').text()).toBe('كتالوج العروض');
        expect(wrapper.get('label[for="catalog"]').text()).toBe('ملف PDF');
        expect(wrapper.text()).toContain('رفع الكتالوج');
    });
});
