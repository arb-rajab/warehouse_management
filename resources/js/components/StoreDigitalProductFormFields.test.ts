import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import FieldError from '@/components/FieldError.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { i18n, t } from '@/lib/i18n';
import {
    paginated,
    storeDigitalProductForm,
    storeDigitalProductTax,
    storeSharedProps,
    storeUpload,
} from '@/testing/factories';
import { createUseHttpStub } from '@/testing/inertiaStubs';
import Fields from './StoreDigitalProductFormFields.vue';

const { usePageMock, useHttpMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    useHttpMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, createFormStub } =
        await import('@/testing/inertiaStubs');

    return {
        usePage: usePageMock,
        useHttp: useHttpMock,
        Link: createLinkStub(),
        Head: { template: '<span />' },
        Form: createFormStub(() => ({ errors: {}, processing: false })),
        router: { post: vi.fn() },
    };
});

const categories = [
    { id: 12, name: 'E-books' },
    { id: 13, name: '-- Recipes' },
    { id: 14, name: '---- كتب الطبخ' },
];
const taxes = [
    storeDigitalProductTax(),
    storeDigitalProductTax({
        id: 3,
        name: 'Service',
        tax: '2.50',
        tax_type: 'amount',
    }),
];
const amountMax = '999999999999999999.99';
const wrappers: VueWrapper[] = [];

function mountFields(props: Record<string, unknown> = {}) {
    const wrapper = mount(Fields, {
        props: { categories, taxes, errors: {}, ...props },
    });
    wrappers.push(wrapper);

    return wrapper;
}

function value(wrapper: VueWrapper, selector: string): string {
    return (wrapper.get(selector).element as HTMLInputElement).value;
}

const labelKeys = [
    'general',
    'images',
    'metaTags',
    'price',
    'information',
    'name',
    'category',
    'file',
    'tags',
    'tagsHelp',
    'photos',
    'thumbnail',
    'metaTitle',
    'metaDescription',
    'metaImage',
    'slug',
    'unitPrice',
    'purchasePrice',
    'description',
];
// The tax and discount fields are the shared product form pieces.
const sharedLabelKeys = [
    'flat',
    'percent',
    'dateRange',
    'dateHelp',
    'discount',
    'discountType',
];

beforeEach(() => {
    vi.clearAllMocks();
    usePageMock.mockReturnValue({
        props: { store: storeSharedProps({ permissions: ['view_uploads'] }) },
    });
    useHttpMock.mockImplementation((data) =>
        createUseHttpStub(data, paginated([])),
    );
});
afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    i18n.global.locale.value = 'en';
});

describe('StoreDigitalProductFormFields', () => {
    it.each(['en', 'ar'] as const)(
        'labels every section and field in %s',
        async (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountFields({ product: storeDigitalProductForm() });
            await flushPromises();

            for (const key of labelKeys) {
                expect(wrapper.text()).toContain(
                    t(`store.digitalProducts.form.${key}`),
                );
            }

            for (const key of sharedLabelKeys) {
                expect(wrapper.text()).toContain(t(`store.productForm.${key}`));
            }

            expect(
                wrapper.findAll('legend').map((legend) => legend.text()),
            ).toEqual(
                ['general', 'images', 'metaTags', 'price', 'information'].map(
                    (key) => t(`store.digitalProducts.form.${key}`),
                ),
            );
            expect(wrapper.get('#category_id option').text()).toBe(
                t('store.digitalProducts.form.selectCategory'),
            );
            expect(wrapper.get('label[for="tax_type_2"]').text()).toBe(
                t('store.productForm.taxType', { tax: 'VAT' }),
            );
            expect(wrapper.get('label[for="tax_2"]').text()).toBe('VAT');
            expect(wrapper.get('#date_range').attributes('placeholder')).toBe(
                t('store.productForm.dateFormat'),
            );
            expect(wrapper.get('#description-label').text()).toBe(
                t('store.digitalProducts.form.description'),
            );
        },
    );

    it('mirrors the request rules with native constraints', () => {
        const wrapper = mountFields({ product: storeDigitalProductForm() });

        for (const id of [
            'name',
            'category_id',
            'unit_price',
            'purchase_price',
            'discount',
            'discount_type',
            'tax_2',
            'tax_type_2',
        ]) {
            expect(wrapper.get(`#${id}`).attributes()).toHaveProperty(
                'required',
            );
        }

        for (const id of [
            'tags',
            'date_range',
            'meta_title',
            'slug',
            'meta_description',
        ]) {
            expect(wrapper.get(`#${id}`).attributes()).not.toHaveProperty(
                'required',
            );
        }

        expect(wrapper.get('#name').attributes('maxlength')).toBe('200');
        expect(wrapper.get('#tags').attributes('maxlength')).toBe('500');
        expect(wrapper.get('#date_range').attributes()).toMatchObject({
            name: 'date_range',
            maxlength: '42',
            dir: 'ltr',
            pattern:
                '\\d{2}-\\d{2}-\\d{4} \\d{2}:\\d{2}:\\d{2} to \\d{2}-\\d{2}-\\d{4} \\d{2}:\\d{2}:\\d{2}',
        });

        for (const id of [
            'unit_price',
            'purchase_price',
            'discount',
            'tax_2',
            'tax_3',
        ]) {
            expect(wrapper.get(`#${id}`).attributes()).toMatchObject({
                type: 'number',
                min: '0',
                max: amountMax,
                step: '0.01',
            });
        }

        expect(
            wrapper
                .findAll('#category_id option')
                .map((option) => [option.attributes('value'), option.text()]),
        ).toEqual([
            ['', t('store.digitalProducts.form.selectCategory')],
            ['12', 'E-books'],
            ['13', '-- Recipes'],
            ['14', '---- كتب الطبخ'],
        ]);
    });

    it('starts a new product empty, with the legacy price defaults and every tax at zero', () => {
        const wrapper = mountFields({
            taxes: taxes.map((tax) => ({
                ...tax,
                tax: '0',
                tax_type: 'amount',
            })),
        });

        expect(value(wrapper, '#name')).toBe('');
        expect(value(wrapper, '#category_id')).toBe('');
        expect(value(wrapper, '#unit_price')).toBe('0');
        expect(value(wrapper, '#purchase_price')).toBe('0');
        expect(value(wrapper, '#discount')).toBe('0');
        expect(value(wrapper, '#discount_type')).toBe('amount');
        expect(value(wrapper, '#date_range')).toBe('');
        expect(value(wrapper, '#tags')).toBe('');
        expect(wrapper.find('#slug').exists()).toBe(false);
        expect(value(wrapper, '#tax_2')).toBe('0');
        expect(value(wrapper, '#tax_type_2')).toBe('amount');
        expect(wrapper.getComponent(StoreRichTextField).props()).toEqual({
            id: 'description',
            name: 'description',
            label: t('store.digitalProducts.form.description'),
            modelValue: '',
            required: false,
            error: undefined,
        });
        expect(
            wrapper
                .findAllComponents(StoreUploadField)
                .map((upload) => upload.props()),
        ).toEqual([
            expect.objectContaining({
                id: 'file',
                name: 'file',
                type: undefined,
                multiple: false,
                modelValue: '',
                initialFiles: [],
            }),
            expect.objectContaining({
                id: 'photos',
                name: 'photos',
                type: 'image',
                multiple: true,
                modelValue: '',
                initialFiles: [],
            }),
            expect.objectContaining({
                id: 'thumbnail_img',
                name: 'thumbnail_img',
                type: 'image',
                multiple: false,
                modelValue: '',
            }),
            expect.objectContaining({
                id: 'meta_img',
                name: 'meta_img',
                type: 'image',
                multiple: false,
                modelValue: '',
            }),
        ]);
    });

    it('fills an existing product and wires its uploads, editor, taxes and discount', async () => {
        const product = storeDigitalProductForm();
        const file = storeUpload({
            id: 9,
            name: 'Guide.pdf',
            type: 'document',
        });
        const photos = [storeUpload({ id: 5 }), storeUpload({ id: 6 })];
        const wrapper = mountFields({
            product,
            name: 'كتاب الوصفات',
            description: '<p><strong>Family</strong> recipes</p>',
            initialFiles: {
                file: [file],
                photos,
                thumbnail_img: [],
                meta_img: [],
            },
        });
        await flushPromises();

        expect(value(wrapper, '#name')).toBe('كتاب الوصفات');
        expect(value(wrapper, '#category_id')).toBe('12');
        expect(value(wrapper, '#tags')).toBe('cooking,recipes');
        expect(value(wrapper, '#unit_price')).toBe('1250.50');
        expect(value(wrapper, '#purchase_price')).toBe('900.00');
        expect(value(wrapper, '#discount')).toBe('10.00');
        expect(value(wrapper, '#discount_type')).toBe('percent');
        expect(value(wrapper, '#date_range')).toBe(product.date_range);
        expect(value(wrapper, '#meta_title')).toBe('Recipe e-book');
        expect(value(wrapper, '#meta_description')).toBe('Family recipes');
        expect(value(wrapper, '#slug')).toBe('recipe-e-book-12345');
        expect(
            wrapper
                .findAll('input[name="tax_id[]"]')
                .map((input) => input.attributes('value')),
        ).toEqual(['2', '3']);
        expect(
            wrapper
                .findAll('input[name="tax[]"]')
                .map((input) => (input.element as HTMLInputElement).value),
        ).toEqual(['21.00', '2.50']);
        expect(
            wrapper
                .findAll('select[name="tax_type[]"]')
                .map((select) => (select.element as HTMLSelectElement).value),
        ).toEqual(['percent', 'amount']);

        const uploads = wrapper.findAllComponents(StoreUploadField);
        expect(
            uploads.map((upload) => [
                upload.props('name'),
                upload.props('modelValue'),
                upload.props('initialFiles'),
            ]),
        ).toEqual([
            ['file', '9', [file]],
            ['photos', '5,6', photos],
            ['thumbnail_img', '7', []],
            ['meta_img', '8', []],
        ]);
        expect(
            wrapper.getComponent(StoreRichTextField).props('modelValue'),
        ).toBe('<p><strong>Family</strong> recipes</p>');
        expect(
            (
                wrapper.get('input[type="hidden"][name="description"]')
                    .element as HTMLInputElement
            ).value,
        ).toBe('<p><strong>Family</strong> recipes</p>');
    });

    it('keeps typed tax amounts when the fields re-render', async () => {
        const wrapper = mountFields({ product: storeDigitalProductForm() });
        await wrapper.get('#tax_2').setValue('7.25');
        await wrapper.get('#discount_type').setValue('amount');
        await wrapper.setProps({ errors: { name: 'Name error' } });
        expect(value(wrapper, '#tax_2')).toBe('7.25');
        expect(value(wrapper, '#discount_type')).toBe('amount');
    });

    it('shows each server error beside its field', () => {
        const errors = {
            name: 'Name error',
            category_id: 'Category error',
            file: 'File error',
            photos: 'Photos error',
            'tax.0': 'Tax error',
            'tax_type.1': 'Tax type error',
            date_range: 'Range error',
            discount_type: 'Type error',
            description: 'Description error',
        };
        const wrapper = mountFields({
            product: storeDigitalProductForm(),
            errors,
        });
        const messages = wrapper
            .findAllComponents(FieldError)
            .map((error) => error.props('message'));

        for (const message of Object.values(errors)) {
            expect(messages).toContain(message);
        }
    });
});
