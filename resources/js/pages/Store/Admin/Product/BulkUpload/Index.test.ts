import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    bulkUpload,
    index as importIndex,
    pdfDownloadBrand,
    pdfDownloadCategory,
} from '@/actions/App/Http/Controllers/Store/Admin/ProductBulkUploadController';
import FormField from '@/components/FormField.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Index from './Index.vue';

const { usePageMock, formSlotPropsMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    formSlotPropsMock: vi.fn(() => ({ errors: {}, processing: false })),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(formSlotPropsMock),
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { post: vi.fn() },
    };
});
const exampleUrl = '/download/product_bulk_demo.xlsx';
function mountPage() {
    usePageMock.mockReturnValue({
        url: importIndex.url(),
        props: defaultAuthProps({
            store: storeSharedProps({ permissions: ['product_bulk_import'] }),
        }),
    });

    return mount(Index, { props: { exampleUrl } });
}
describe('Store product bulk import', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });
    it('mounts the reusable form in the store layout with all instructions and labels', () => {
        const wrapper = mountPage();
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
            layout: StoreAdminLayout,
            wide: true,
            title: t('store.bulkUpload.title'),
            submitLabel: t('store.bulkUpload.upload'),
            submittingLabel: t('store.bulkUpload.uploading'),
            action: bulkUpload(),
        });
        expect(wrapper.getComponent(Form).props('action')).toEqual(
            bulkUpload(),
        );

        for (const key of [
            'title',
            'prepare',
            'identifiers',
            'editAfter',
            'example',
            'categories',
            'brands',
            'file',
            'limit',
            'upload',
        ]) {
            expect(wrapper.text()).toContain(t(`store.bulkUpload.${key}`));
        }
    });
    it('uses plain downloads for the spreadsheet and retained Blade PDFs', () => {
        const wrapper = mountPage();
        const links = wrapper.findAll('a');
        expect(
            links
                .find((link) => link.text() === t('store.bulkUpload.example'))
                ?.attributes(),
        ).toMatchObject({ href: exampleUrl, download: '' });
        expect(
            links
                .find(
                    (link) => link.text() === t('store.bulkUpload.categories'),
                )
                ?.attributes('href'),
        ).toBe(pdfDownloadCategory().url);
        expect(
            links
                .find((link) => link.text() === t('store.bulkUpload.brands'))
                ?.attributes('href'),
        ).toBe(pdfDownloadBrand().url);
    });
    it('requires a supported file and mirrors the backend maximum size', async () => {
        const wrapper = mountPage();
        const input = wrapper.get<HTMLInputElement>('input[name="bulk_file"]');
        expect(input.attributes()).toMatchObject({
            type: 'file',
            required: '',
            accept: '.xlsx,.xls,.csv,.txt',
        });
        Object.defineProperty(input.element, 'files', {
            configurable: true,
            value: [{ size: 10 * 1024 * 1024 + 1 }],
        });
        await input.trigger('change');
        expect(input.element.validationMessage).toBe(
            t('store.bulkUpload.limit'),
        );
        Object.defineProperty(input.element, 'files', {
            configurable: true,
            value: [{ size: 10 * 1024 * 1024 }],
        });
        await input.trigger('change');
        expect(input.element.validity.customError).toBe(false);
    });
    it('renders file errors and the form processing state', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { bulk_file: 'Invalid file' },
            processing: true,
        });
        const wrapper = mountPage();
        expect(
            wrapper
                .findAllComponents(FormField)
                .find((field) => field.props('id') === 'bulk_file')!
                .props('error'),
        ).toBe('Invalid file');
        expect(wrapper.text()).toContain('Invalid file');
        expect(wrapper.getComponent(SubmitButton).props('processing')).toBe(
            true,
        );
        expect(wrapper.getComponent(SubmitButton).text()).toBe(
            t('store.bulkUpload.uploading'),
        );
    });
});
