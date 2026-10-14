import { Link } from '@inertiajs/vue3';
import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
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

        expect(wrapper.find('#flat_shipping_cost').exists()).toBe(false);
        expect(
            wrapper.get('#flash_discount_type').attributes('required'),
        ).toBeUndefined();
        await wrapper
            .get('input[name="shipping_type"][value="flat_rate"]')
            .setValue(true);
        await wrapper.get('#flash_deal_id').setValue('9');

        expect(wrapper.get('#flat_shipping_cost').attributes()).toMatchObject({
            required: '',
            min: '0',
            step: '0.01',
        });
        expect(
            wrapper.get('#flash_discount_type').attributes('required'),
        ).toBeDefined();
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
});
