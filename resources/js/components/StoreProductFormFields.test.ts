import { Link } from '@inertiajs/vue3';
import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { storeBarcodeImage } from '@/actions/App/Http/Controllers/Store/Admin/ProductController';
import { activation } from '@/actions/App/Http/Controllers/Store/Admin/SettingsController';
import { index as shippingConfigurationIndex } from '@/actions/App/Http/Controllers/Store/Admin/ShippingConfigurationController';
import StoreProductDiscountFields from '@/components/StoreProductDiscountFields.vue';
import StoreProductListField from '@/components/StoreProductListField.vue';
import StoreProductTaxFields from '@/components/StoreProductTaxFields.vue';
import StoreProductVariantFields from '@/components/StoreProductVariantFields.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { i18n, t } from '@/lib/i18n';
import {
    storeDigitalProductTax,
    storeProductForm,
    storeSharedProps,
} from '@/testing/factories';
import { createUseHttpStub } from '@/testing/inertiaStubs';
import { expectRequiredFields } from '@/testing/requiredFields';
import type { StoreProductFormSections } from '@/types/store';
import Fields from './StoreProductFormFields.vue';

const { usePageMock, useHttpMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    useHttpMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub } = await import('@/testing/inertiaStubs');

    return {
        usePage: usePageMock,
        useHttp: useHttpMock,
        Link: createLinkStub(),
        router: { post: vi.fn() },
    };
});

const allSections: StoreProductFormSections = {
    shipping: true,
    productWiseShipping: true,
    flashDeals: true,
    vat: true,
    cashOnDelivery: true,
};
const props = {
    categories: [
        { id: 1, name: 'Pantry' },
        { id: 2, name: '-- Oils' },
    ],
    brands: [{ id: 5, name: 'Olivia' }],
    colors: [{ id: 3, name: 'Red', code: '#FF0000' }],
    attributes: [{ id: 1, name: 'Size' }],
    flashDeals: [{ id: 9, name: 'Autumn deals' }],
    taxes: [storeDigitalProductTax()],
    sections: allSections,
    errors: {},
};
const labelKeys = [
    'information',
    'name',
    'category',
    'brand',
    'unit',
    'weight',
    'maxQty',
    'minQty',
    'tags',
    'tagsHelp',
    'images',
    'photosHelp',
    'thumbnailHelp',
    'videos',
    'videoProvider',
    'videoLink',
    'videoLinkHelp',
    'typesAndBarcodes',
    'variation',
    'priceAndStock',
    'unitPrice',
    'quantity',
    'sku',
    'externalLink',
    'externalLinkHelp',
    'externalLinkButton',
    'vatAndTax',
    'descriptionSection',
    'metaTags',
    'metaTitle',
    'metaDescription',
    'stockAndShipping',
    'shippingConfiguration',
    'freeShipping',
    'flatRate',
    'quantityMultiplied',
    'shippingDays',
    'lowStockQuantity',
    'stockVisibility',
    'stockVisibility_quantity',
    'stockVisibility_text',
    'stockVisibility_hide',
    'cashOnDelivery',
    'featured',
    'todaysDeal',
    'canExpire',
    'canExpireHelp',
    'flashDeal',
    'addToFlash',
    'chooseFlashDeal',
    'flashDiscount',
    'flashDiscountType',
    'chooseDiscountType',
    'selectCategory',
    'selectBrand',
];
const wrappers: VueWrapper[] = [];

function mountFields(overrides: Record<string, unknown> = {}) {
    const wrapper = mount(Fields, { props: { ...props, ...overrides } });
    wrappers.push(wrapper);

    return wrapper;
}

beforeEach(() => {
    vi.clearAllMocks();
    usePageMock.mockReturnValue({
        props: { store: storeSharedProps({ permissions: ['view_uploads'] }) },
    });
    useHttpMock.mockImplementation((data) => createUseHttpStub(data, []));
});
afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    i18n.global.locale.value = 'en';
});

describe('StoreProductFormFields', () => {
    it.each(['en', 'ar'] as const)(
        'labels every section and field in %s',
        async (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountFields();
            await wrapper
                .get('input[name="shipping_type"][value="flat_rate"]')
                .setValue(true);

            for (const key of labelKeys) {
                expect(wrapper.text()).toContain(
                    t(`store.products.form.${key}`),
                );
            }

            expect(
                wrapper.findAll('legend').map((legend) => legend.text()),
            ).toEqual(
                expect.arrayContaining(
                    [
                        'information',
                        'images',
                        'videos',
                        'typesAndBarcodes',
                        'variation',
                        'priceAndStock',
                        'descriptionSection',
                        'metaTags',
                        'stockAndShipping',
                        'flashDeal',
                    ].map((key) => t(`store.products.form.${key}`)),
                ),
            );
            expect(wrapper.text()).toContain(
                t('store.products.form.shippingCost'),
            );
            expect(wrapper.get('#unit').attributes('placeholder')).toBe(
                t('store.products.form.unitPlaceholder'),
            );
        },
    );

    it('passes each shared field its props', () => {
        const wrapper = mountFields();

        expect(
            wrapper.getComponent(StoreProductVariantFields).props(),
        ).toMatchObject({
            colors: props.colors,
            attributes: props.attributes,
            unitPrice: '0',
            errors: {},
        });
        expect(wrapper.getComponent(StoreProductTaxFields).props()).toEqual({
            taxes: props.taxes,
            errors: {},
        });
        expect(
            wrapper.getComponent(StoreProductDiscountFields).props(),
        ).toMatchObject({ errors: {} });
        expect(
            wrapper
                .findAllComponents(StoreProductListField)
                .map((field) => field.props()),
        ).toEqual([
            expect.objectContaining({
                id: 'product_types',
                name: 'product_types',
                label: t('store.products.form.types'),
                addLabel: t('store.products.form.addType'),
                removeLabel: t('store.products.form.removeType'),
                placeholder: t('store.products.form.typePlaceholder'),
            }),
            expect.objectContaining({
                id: 'product_barcodes',
                name: 'product_barcodes',
                label: t('store.products.form.barcodes'),
                addLabel: t('store.products.form.addBarcode'),
                removeLabel: t('store.products.form.removeBarcode'),
                placeholder: t('store.products.form.barcodePlaceholder'),
            }),
        ]);
        expect(wrapper.getComponent(StoreRichTextField).props()).toMatchObject({
            id: 'description',
            name: 'description',
            label: t('store.products.form.description'),
        });
        expect(
            wrapper
                .findAllComponents(StoreUploadField)
                .map((field) => [
                    field.props('name'),
                    field.props('type'),
                    field.props('multiple'),
                    field.props('label'),
                ]),
        ).toEqual([
            ['photos', 'image', true, t('store.products.form.photos')],
            [
                'thumbnail_img',
                'image',
                false,
                t('store.products.form.thumbnail'),
            ],
            ['pdf', 'document', false, t('store.products.form.pdf')],
            ['meta_img', 'image', false, t('store.products.form.metaImage')],
        ]);
    });

    it('shows each saved barcode as its barcode image below its input, and a note for any other value', async () => {
        const wrapper = mountFields({
            product: storeProductForm({
                product_barcodes: ['8712345678906', 'متجر', 'EVOO-1'],
            }),
            barcodeImages: [
                { id: 3, barcode: '8712345678906', printable: true },
                { id: 4, barcode: 'متجر', printable: false },
                { id: 5, barcode: 'EVOO-1', printable: true },
            ],
        });
        const rows = () => wrapper.get('#product_barcodes').findAll('li');
        const images = () =>
            wrapper
                .get('#product_barcodes')
                .findAll('img')
                .map((img) => [img.attributes('src'), img.attributes('alt')]);

        expect(images()).toEqual([
            [
                storeBarcodeImage.url({ product: 41, barcode: 3 }),
                '8712345678906',
            ],
            [storeBarcodeImage.url({ product: 41, barcode: 5 }), 'EVOO-1'],
        ]);
        expect(storeBarcodeImage.url({ product: 41, barcode: 3 })).toBe(
            '/temp/admin/products/41/store-barcodes/3/image',
        );
        expect(rows()[1].text()).toContain(
            t('store.products.form.barcodeNotPrintable'),
        );
        expect(rows()[0].find('img').classes()).toEqual(
            expect.arrayContaining(['bg-white', 'max-w-xs']),
        );

        // A changed value keeps its input editable and loses the image until saved.
        await rows()[2].get('input').setValue('EVOO-2');
        expect(rows()[2].find('img').exists()).toBe(false);
        expect(rows()[2].text()).toContain(
            t('store.products.form.barcodeImageAfterSave'),
        );
        // Typing the saved value back shows its image again.
        await rows()[2].get('input').setValue(' EVOO-1 ');
        expect(rows()[2].get('img').attributes('src')).toBe(
            storeBarcodeImage.url({ product: 41, barcode: 5 }),
        );

        // A new row says nothing while empty, then the after-save note.
        await wrapper
            .findAll('button')
            .find(
                (button) =>
                    button.text() === t('store.products.form.addBarcode'),
            )!
            .trigger('click');
        expect(rows()[3].text()).toBe('');
        await rows()[3].get('input').setValue('5901234123457');
        expect(rows()[3].text()).toContain(
            t('store.products.form.barcodeImageAfterSave'),
        );
        expect(rows()[3].find('img').exists()).toBe(false);
    });

    it('posts the loaded barcodes as the seen list on the edit form only, blank when it loaded none', async () => {
        const seen = (wrapper: ReturnType<typeof mountFields>) =>
            wrapper
                .findAll('input[name="product_barcodes_seen[]"]')
                .map((input) => (input.element as HTMLInputElement).value);
        const edit = mountFields({
            product: storeProductForm({
                product_barcodes: ['8712345678906', 'EVOO-1'],
            }),
        });

        expect(seen(edit)).toEqual(['8712345678906', 'EVOO-1']);
        await edit
            .get(
                'button[aria-label="' +
                    t('store.products.form.removeBarcode') +
                    ' 1"]',
            )
            .trigger('click');
        expect(seen(edit)).toEqual(['8712345678906', 'EVOO-1']);

        // A product that loaded none still posts the key, blank.
        expect(
            seen(
                mountFields({
                    product: storeProductForm({ product_barcodes: [] }),
                }),
            ),
        ).toEqual(['']);
        // The create form has no seen list.
        expect(seen(mountFields())).toEqual([]);
        // The product types are not tracked.
        expect(edit.find('input[name="product_types_seen[]"]').exists()).toBe(
            false,
        );
    });

    it.each(['en', 'ar'] as const)(
        'labels the barcode notes in %s, and the create form has no image',
        async (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountFields();
            await wrapper
                .findAll('button')
                .find(
                    (button) =>
                        button.text() === t('store.products.form.addBarcode'),
                )!
                .trigger('click');
            await wrapper
                .get('input[name="product_barcodes[]"]')
                .setValue('8712345678906');

            expect(t('store.products.form.barcodeImageAfterSave')).not.toBe(
                'store.products.form.barcodeImageAfterSave',
            );
            expect(t('store.products.form.barcodeNotPrintable')).not.toBe(
                'store.products.form.barcodeNotPrintable',
            );
            expect(wrapper.get('#product_barcodes').text()).toContain(
                t('store.products.form.barcodeImageAfterSave'),
            );
            expect(wrapper.find('#product_barcodes img').exists()).toBe(false);
            expect(wrapper.get('#product_types').text()).not.toContain(
                t('store.products.form.barcodeImageAfterSave'),
            );
        },
    );

    it('offers the slug and the Otajer images only on the edit form', () => {
        const create = mountFields();

        expect(create.find('#slug').exists()).toBe(false);
        expect(create.find('input[name="old_photo"]').exists()).toBe(false);

        const edit = mountFields({ product: storeProductForm() });
        const otajer = edit
            .findAllComponents(StoreUploadField)
            .find((field) => field.props('name') === 'old_photo');

        expect(edit.get('#slug').attributes()).toMatchObject({
            name: 'slug',
            dir: 'ltr',
            'aria-describedby': 'slug_help',
        });
        expect((edit.get('#slug').element as HTMLInputElement).value).toBe(
            'olive-oil',
        );
        expect(otajer?.props()).toMatchObject({
            id: 'old_photo',
            type: 'image',
            multiple: true,
            modelValue: '4',
            label: t('store.products.form.otajerImage'),
        });
    });

    it('lists the options and mirrors every backend rule with native constraints', () => {
        const wrapper = mountFields();

        expect(
            wrapper
                .findAll('#category_id option')
                .map((option) => [option.attributes('value'), option.text()]),
        ).toEqual([
            ['', t('store.products.form.selectCategory')],
            ['1', 'Pantry'],
            ['2', '-- Oils'],
        ]);
        expect(
            wrapper.findAll('#brand_id option').map((option) => option.text()),
        ).toEqual([t('store.products.form.selectBrand'), 'Olivia']);
        expect(
            wrapper
                .findAll('#video_provider option')
                .map((option) => option.attributes('value')),
        ).toEqual(['youtube', 'dailymotion', 'vimeo']);
        expect(
            wrapper
                .findAll('#flash_deal_id option')
                .map((option) => option.text()),
        ).toEqual([t('store.products.form.chooseFlashDeal'), 'Autumn deals']);
        const attrs = (selector: string) => wrapper.get(selector).attributes();
        expect(attrs('#name')).toMatchObject({
            maxlength: '200',
            required: '',
        });
        expect(attrs('#category_id')).toMatchObject({ required: '' });
        expect(attrs('#unit')).toMatchObject({ maxlength: '20', required: '' });
        expect(attrs('#weight')).toMatchObject({
            min: '0',
            max: '999999.99',
            step: '0.01',
            value: '0.00',
        });
        expect(attrs('#max_qty')).toMatchObject({
            min: '1',
            max: '2147483647',
            step: '1',
            value: '1000',
            required: '',
        });
        expect(attrs('#min_qty')).toMatchObject({
            min: '1',
            max: '2147483647',
            step: '1',
            value: '1',
            required: '',
        });
        expect(attrs('#tags')).toMatchObject({ maxlength: '500' });
        expect(attrs('#video_link')).toMatchObject({
            type: 'url',
            maxlength: '100',
        });
        expect(attrs('#unit_price')).toMatchObject({
            min: '0',
            max: '999999999999999999.99',
            step: '0.01',
            required: '',
        });
        expect(attrs('#current_stock')).toMatchObject({
            min: '0',
            step: '1',
            value: '0',
            required: '',
        });
        expect(attrs('#sku')).toMatchObject({ maxlength: '255' });
        expect(attrs('#external_link')).toMatchObject({
            type: 'url',
            maxlength: '500',
        });
        expect(attrs('#external_link_btn')).toMatchObject({ maxlength: '255' });
        expect(attrs('#est_shipping_days')).toMatchObject({
            min: '1',
            step: '1',
        });
        expect(attrs('#low_stock_quantity')).toMatchObject({
            min: '0',
            step: '1',
            value: '1',
        });
        expect(attrs('#flash_discount')).toMatchObject({
            min: '0',
            step: '0.01',
            value: '0',
        });
        expect(
            (
                wrapper.get('input[name="shipping_type"][value="free"]')
                    .element as HTMLInputElement
            ).checked,
        ).toBe(true);
        expect(
            (
                wrapper.get(
                    'input[name="stock_visibility_state"][value="quantity"]',
                ).element as HTMLInputElement
            ).checked,
        ).toBe(true);
        expect(
            (
                wrapper.get('input[name="cash_on_delivery"]')
                    .element as HTMLInputElement
            ).checked,
        ).toBe(true);
        expect(
            (wrapper.get('input[name="featured"]').element as HTMLInputElement)
                .checked,
        ).toBe(false);
        expect(
            (
                wrapper.get('input[name="todays_deal"]')
                    .element as HTMLInputElement
            ).checked,
        ).toBe(false);
    });

    it('asks for a flat shipping cost and a flash discount type only when they apply', async () => {
        const wrapper = mountFields();
        const required = [
            'name',
            'category_id',
            'unit',
            'max_qty',
            'min_qty',
            'unit_price',
            'discount',
            'discount_type',
            'current_stock',
            'tax_2',
            'tax_type_2',
            'shipping_type',
            'stock_visibility_state',
        ];
        expectRequiredFields(wrapper, required);

        expect(wrapper.find('#flat_shipping_cost').exists()).toBe(false);
        expect(
            wrapper.get('#flash_discount_type').attributes('required'),
        ).toBeUndefined();
        await wrapper
            .get('input[name="shipping_type"][value="flat_rate"]')
            .setValue(true);
        await wrapper.get('#flash_deal_id').setValue('9');

        expectRequiredFields(wrapper, [
            ...required,
            'flat_shipping_cost',
            'flash_discount',
            'flash_discount_type',
        ]);
        expect(wrapper.get('#flat_shipping_cost').attributes()).toMatchObject({
            required: '',
            min: '0',
            step: '0.01',
        });
        expect(
            wrapper.get('#flash_discount_type').attributes('required'),
        ).toBeDefined();
        expect(wrapper.text()).toContain(
            t('store.products.form.shippingCostHelp'),
        );
        expect(wrapper.text()).toContain(
            t('store.products.form.flashDiscountHelp'),
        );
        await wrapper
            .get('input[name="shipping_type"][value="free"]')
            .setValue(true);
        await wrapper.get('#flash_deal_id').setValue('');
        expectRequiredFields(wrapper, required);
    });

    it('hands the variant rows the unit price exactly as typed', async () => {
        const wrapper = mountFields();
        await wrapper.get('#unit_price').setValue('12.50');

        expect(
            wrapper.getComponent(StoreProductVariantFields).props('unitPrice'),
        ).toBe('12.50');
    });

    it('hides the quantity and SKU, still posted, while variants carry their own', async () => {
        const wrapper = mountFields();
        await wrapper
            .getComponent(StoreProductVariantFields)
            .vm.$emit('update:hasVariants', true);
        await flushPromises();

        expect(
            (
                wrapper
                    .get('#current_stock')
                    .element.closest('.contents') as HTMLElement
            ).style.display,
        ).toBe('none');
        expect(wrapper.get('#current_stock').attributes('name')).toBe(
            'current_stock',
        );
    });

    it('drops the sections their settings switch off and links to where they are configured', () => {
        const wrapper = mountFields({
            flashDeals: [],
            taxes: [],
            sections: {
                shipping: true,
                productWiseShipping: false,
                flashDeals: false,
                vat: false,
                cashOnDelivery: false,
            },
        });

        expectRequiredFields(wrapper, [
            'name',
            'category_id',
            'unit',
            'max_qty',
            'min_qty',
            'unit_price',
            'discount',
            'discount_type',
            'current_stock',
            'stock_visibility_state',
        ]);
        expect(wrapper.find('input[name="shipping_type"]').exists()).toBe(
            false,
        );
        expect(wrapper.find('#flash_deal_id').exists()).toBe(false);
        expect(wrapper.findComponent(StoreProductTaxFields).exists()).toBe(
            false,
        );
        expect(wrapper.find('input[name="cash_on_delivery"]').exists()).toBe(
            false,
        );
        expect(wrapper.text()).toContain(
            t('store.products.form.shippingConfiguredElsewhere'),
        );
        expect(wrapper.text()).toContain(
            t('store.products.form.cashOnDeliveryDisabled'),
        );
        expect(
            wrapper
                .findAllComponents(Link)
                .map((link) => [link.props('href'), link.text()]),
        ).toEqual([
            [
                shippingConfigurationIndex(),
                t('store.products.form.shippingConfiguration'),
            ],
            [activation(), t('store.products.form.cashPaymentActivation')],
        ]);
        expect(wrapper.find('#est_shipping_days').exists()).toBe(true);
        const noShipping = mountFields({
            sections: {
                ...allSections,
                shipping: false,
                productWiseShipping: false,
            },
        });
        expect(noShipping.find('#est_shipping_days').exists()).toBe(false);
        expect(noShipping.text()).not.toContain(
            t('store.products.form.shippingConfiguration'),
        );
    });

    it('shows the errors next to their fields', () => {
        const errors = {
            name: 'Name missing',
            unit: 'Unit missing',
            brand_id: 'Bad brand',
            category_id: 'Bad category',
            video_provider: 'Bad provider',
            unit_price: 'Bad price',
            stock_visibility_state: 'Bad state',
            flash_deal_id: 'Bad deal',
            flash_discount_type: 'Bad type',
            meta_description: 'Bad meta',
            shipping_type: 'Bad shipping',
        };
        const wrapper = mountFields({ errors });

        for (const message of Object.values(errors)) {
            expect(wrapper.text()).toContain(message);
        }
    });
    it('shows an empty, optional Arabic name and no synced-field note on the create form', () => {
        const wrapper = mountFields();

        const arName = wrapper.get('#ar_name');
        expect(arName.attributes('name')).toBe('ar_name');
        expect(arName.attributes('dir')).toBe('auto');
        expect(arName.attributes('maxlength')).toBe('191');
        expect(arName.attributes('required')).toBeUndefined();
        expect(arName.attributes('readonly')).toBeUndefined();
        expect((arName.element as HTMLInputElement).value).toBe('');
        expect(wrapper.find('#ar_name-otajer-synced').exists()).toBe(false);
        expect(wrapper.find('#otajer-synced-fields').exists()).toBe(false);
        expect(wrapper.get('#name').attributes('readonly')).toBeUndefined();
    });

    it('posts can_expire as a checkbox with a hidden 0 fallback, optional and unmarked', async () => {
        const create = mountFields();
        const [hidden, box] = create.findAll('input[name="can_expire"]');
        expect(hidden.attributes('type')).toBe('hidden');
        expect((hidden.element as HTMLInputElement).value).toBe('0');
        expect(box.attributes('type')).toBe('checkbox');
        expect((box.element as HTMLInputElement).value).toBe('1');
        expect((box.element as HTMLInputElement).checked).toBe(false);
        expect(box.attributes('required')).toBeUndefined();
        expect(box.attributes('aria-describedby')).toBe('can_expire_help');
        expect(create.get('#can_expire_help').text()).toBe(
            t('store.products.form.canExpireHelp'),
        );
        expect(box.element.closest('label')?.textContent?.trim()).toBe(
            t('store.products.form.canExpire'),
        );
        expect(
            box.element.closest('label')?.querySelector('[aria-hidden]'),
        ).toBeNull();

        const edit = mountFields({
            product: storeProductForm({ can_expire: true }),
        });
        expect(
            (
                edit.get('input[type="checkbox"][name="can_expire"]')
                    .element as HTMLInputElement
            ).checked,
        ).toBe(true);
    });

    it('renders the Arabic name as an optional edit field and read-only fields with their notes', () => {
        const editable = mountFields({ arName: 'زيت', name: 'Oil' });
        expect(editable.get('#ar_name').attributes('name')).toBe('ar_name');
        expect(editable.get('#ar_name').attributes('dir')).toBe('auto');
        expect(editable.get('#ar_name').attributes('readonly')).toBeUndefined();
        expect(editable.get('#ar_name').attributes('required')).toBeUndefined();

        const locked = mountFields({
            arName: 'زيت',
            name: 'Oil',
            readOnlyFields: ['name', 'ar_name'],
        });

        for (const id of ['name', 'ar_name']) {
            const input = locked.get(`#${id}`);
            expect(input.attributes('readonly')).toBeDefined();
            expect(input.attributes('aria-describedby')).toBe(
                `${id}-otajer-synced`,
            );
            expect(locked.get(`#${id}-otajer-synced`).text()).toBe(
                t('store.products.form.otajerSynced'),
            );
        }

        // The name stays required (the server requires it), read-only or not.
        expect(locked.get('#name').attributes('required')).toBeDefined();
        expect(locked.get('#otajer-synced-fields').text()).toContain(
            t('store.productFieldSources.fields.ar_name'),
        );
    });

    it('lists an Otajer-owned active status in the note even though it has no field here', () => {
        const wrapper = mountFields({
            arName: '',
            readOnlyFields: ['published'],
        });

        expect(wrapper.get('#otajer-synced-fields').text()).toBe(
            t('store.products.form.otajerSyncedFields', {
                fields: t('store.productFieldSources.fields.published'),
            }),
        );
        expect(wrapper.get('#name').attributes('readonly')).toBeUndefined();
        expect(wrapper.get('#ar_name').attributes('readonly')).toBeUndefined();
    });
    it('renders the synced unit and unit price read-only with their notes, still required', () => {
        const wrapper = mountFields({
            unit: 'bottle',
            readOnlyFields: ['unit', 'unit_price'],
        });

        for (const id of ['unit', 'unit_price']) {
            const input = wrapper.get(`#${id}`);
            expect(input.attributes('readonly')).toBeDefined();
            expect(input.attributes('required')).toBeDefined();
            expect(input.attributes('aria-describedby')).toBe(
                `${id}-otajer-synced`,
            );
            expect(wrapper.get(`#${id}-otajer-synced`).text()).toBe(
                t('store.products.form.otajerSynced'),
            );
        }

        expect(wrapper.get('#otajer-synced-fields').text()).toBe(
            t('store.products.form.otajerSyncedFields', {
                fields: [
                    t('store.productFieldSources.fields.unit'),
                    t('store.productFieldSources.fields.unit_price'),
                ].join(', '),
            }),
        );
        // Nothing else locks.
        expect(wrapper.get('#name').attributes('readonly')).toBeUndefined();
        expect(wrapper.find('#unit-otajer-synced').exists()).toBe(true);
    });

    it('keeps the unit and unit price editable when they are not read-only', () => {
        const wrapper = mountFields({ readOnlyFields: ['name'] });

        for (const id of ['unit', 'unit_price']) {
            expect(
                wrapper.get(`#${id}`).attributes('readonly'),
            ).toBeUndefined();
            expect(wrapper.find(`#${id}-otajer-synced`).exists()).toBe(false);
        }
    });

    it('shows a synced description as a locked block that posts the stored HTML, and no editor', () => {
        const wrapper = mountFields({
            description: '<p>Synced <strong>text</strong></p>',
            readOnlyFields: ['description'],
        });

        expect(wrapper.findComponent(StoreRichTextField).exists()).toBe(false);
        expect(wrapper.get('#description').html()).toContain(
            '<strong>text</strong>',
        );
        expect(wrapper.get('#description').attributes('aria-labelledby')).toBe(
            'description-label',
        );
        expect(wrapper.get('#description-label').text()).toBe(
            t('store.products.form.description'),
        );
        expect(wrapper.get('#description-otajer-synced').text()).toBe(
            t('store.products.form.otajerSynced'),
        );
        const hidden = wrapper.get('input[type="hidden"][name="description"]');
        expect((hidden.element as HTMLInputElement).value).toBe(
            '<p>Synced <strong>text</strong></p>',
        );
        // Optional, so no required mark or attribute beside the label.
        expect(
            wrapper
                .get('#description-label')
                .find('[data-required-mark]')
                .exists(),
        ).toBe(false);
    });

    it('keeps the description editor when the description is not read-only', () => {
        const wrapper = mountFields({ readOnlyFields: ['name'] });

        expect(wrapper.findComponent(StoreRichTextField).exists()).toBe(true);
        expect(wrapper.find('#description-otajer-synced').exists()).toBe(false);
        // The only hidden description input is the editor's own, not the
        // locked block's.
        const hidden = wrapper.findAll(
            'input[type="hidden"][name="description"]',
        );
        expect(hidden).toHaveLength(1);
        expect(
            wrapper
                .findComponent(StoreRichTextField)
                .find('input[type="hidden"][name="description"]')
                .exists(),
        ).toBe(true);
    });

    it('lists the synced fields that have no input here in the note', () => {
        const wrapper = mountFields({
            readOnlyFields: [
                'serial',
                'tax',
                'unit_equal',
                'api_unitId',
                'api_unit_name',
            ],
        });

        expect(wrapper.get('#otajer-synced-fields').text()).toBe(
            t('store.products.form.otajerSyncedFields', {
                fields: [
                    'serial',
                    'tax',
                    'unit_equal',
                    'api_unitId',
                    'api_unit_name',
                ]
                    .map((field) =>
                        t(`store.productFieldSources.fields.${field}`),
                    )
                    .join(', '),
            }),
        );
        expect(wrapper.get('#unit').attributes('readonly')).toBeUndefined();
    });
});
