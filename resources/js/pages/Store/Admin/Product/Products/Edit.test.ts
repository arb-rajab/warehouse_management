import { Form } from '@inertiajs/vue3';
import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    edit,
    editVariantCombinations,
    inHouse as inHouseProducts,
    sellerEdit,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/ProductController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreLanguageTabs from '@/components/StoreLanguageTabs.vue';
import StoreProductFormFields from '@/components/StoreProductFormFields.vue';
import StoreProductListField from '@/components/StoreProductListField.vue';
import StoreProductVariantFields from '@/components/StoreProductVariantFields.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import {
    storeDigitalProductTax,
    storeProductForm,
    storeProductVariantState,
    storeSharedProps,
    storeUpload,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Edit from './Edit.vue';

const { usePageMock, formSlotPropsMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    formSlotPropsMock: vi.fn(() => ({
        errors: { slug: 'Slug taken' } as Record<string, string>,
        processing: false,
    })),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(formSlotPropsMock),
        Link: createLinkStub(),
        useHttp: () => ({
            processing: false,
            get: vi.fn(),
            post: vi.fn(),
            cancel: vi.fn(),
        }),
        usePage: usePageMock,
        router: { get: vi.fn(), post: vi.fn() },
    };
});

const product = storeProductForm();
const variants = storeProductVariantState();
const initialFiles = {
    photos: [storeUpload({ id: 5 }), storeUpload({ id: 6 })],
    thumbnail_img: [storeUpload({ id: 7 })],
    old_photo: [storeUpload({ id: 4, name: 'Otajer photo' })],
    pdf: [storeUpload({ id: 9, type: 'document', name: 'Sheet' })],
    meta_img: [],
};
const props = {
    product,
    name: 'زيت زيتون',
    unit: 'زجاجة',
    description: '<p><strong>Fresh</strong> oil</p>',
    lang: 'ar',
    languages: [
        { code: 'en', name: 'English' },
        { code: 'ar', name: 'Arabic' },
    ],
    listType: 'admin' as const,
    categories: [
        { id: 1, name: 'Pantry' },
        { id: 12, name: '-- Oils' },
    ],
    brands: [{ id: 5, name: 'Olivia' }],
    colors: [{ id: 3, name: 'Red', code: '#FF0000' }],
    attributes: [
        { id: 1, name: 'Size' },
        { id: 2, name: 'Bottle' },
    ],
    flashDeals: [{ id: 9, name: 'Autumn deals' }],
    taxes: [storeDigitalProductTax()],
    sections: {
        shipping: true,
        productWiseShipping: true,
        flashDeals: true,
        vat: true,
        cashOnDelivery: true,
    },
    variants,
    initialFiles,
};
const wrappers: VueWrapper[] = [];

function editPage(overrides: Record<string, unknown> = {}): VueWrapper {
    const wrapper = mount(Edit, { props: { ...props, ...overrides } });
    wrappers.push(wrapper);

    return wrapper;
}

function mockPage(permissions: string[]): void {
    usePageMock.mockReturnValue({
        url: '/temp/admin/products/admin/41/edit?lang=ar',
        props: {
            ...defaultAuthProps(),
            store: storeSharedProps({ permissions }),
        },
    });
}

function inputValue(wrapper: VueWrapper, selector: string): string {
    return (wrapper.get(selector).element as HTMLInputElement).value;
}

beforeEach(() => {
    vi.clearAllMocks();
    mockPage(['product_edit', 'view_uploads']);
});
afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    i18n.global.locale.value = 'en';
});

describe('Store/Admin/Product/Products/Edit', () => {
    it.each(['en', 'ar'] as const)(
        'labels the page, the update button and the edit-only fields in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = editPage();

            expect(wrapper.get('h1').text()).toBe(
                t('store.products.form.editTitle'),
            );
            expect(wrapper.getComponent(SubmitButton).props()).toMatchObject({
                label: t('store.products.form.update'),
                processingLabel: t('store.products.form.updating'),
            });
            expect(wrapper.get('label[for="slug"]').text()).toBe(
                t('store.products.form.slug'),
            );
            expect(wrapper.get('#slug_help').text()).toBe(
                t('store.products.form.slugHelp'),
            );
            expect(
                wrapper
                    .findAllComponents(StoreUploadField)
                    .find((field) => field.props('name') === 'old_photo')
                    ?.props('label'),
            ).toBe(t('store.products.form.otajerImage'));
            // The create form's second button is not offered here.
            expect(wrapper.find('button[name="button"]').exists()).toBe(false);
        },
    );

    it('posts to the typed update action inside the store shell and cancels to the in-house list', () => {
        const wrapper = editPage();

        expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
            action: update(product),
            cancelHref: inHouseProducts(),
            layout: StoreAdminLayout,
            wide: true,
        });
        expect(wrapper.getComponent(Form).props('action')).toEqual(
            update(product),
        );
        expect(update(product).url).toBe('/temp/admin/products/update/41');
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
    });

    it('keeps the language tabs on the admin route and posts the edited language', () => {
        const wrapper = editPage();
        const tabs = wrapper.getComponent(StoreLanguageTabs);

        expect(tabs.props()).toMatchObject({
            languages: props.languages,
            lang: 'ar',
        });
        const href = tabs.props('href') as (code: string) => unknown;
        const lang = wrapper.get('input[type="hidden"][name="lang"]');

        expect(href('en')).toEqual(edit(product, { query: { lang: 'en' } }));
        const hrefs = tabs.findAll('a').map((link) => link.attributes('href'));
        expect(hrefs).toEqual([
            '/temp/admin/products/admin/41/edit?lang=en',
            '/temp/admin/products/admin/41/edit?lang=ar',
        ]);
        expect(lang.attributes('value')).toBe('ar');
    });

    it('keeps the seller form on its own route, with no Inertia list to cancel to', () => {
        const wrapper = editPage({ listType: 'seller' });
        const tabs = wrapper.getComponent(StoreLanguageTabs);
        const href = tabs.props('href') as (code: string) => unknown;
        const page = wrapper.getComponent(ResourceFormPage);

        expect(href('en')).toEqual(
            sellerEdit(product, { query: { lang: 'en' } }),
        );
        expect(tabs.findAll('a')[0].attributes('href')).toBe(
            '/temp/admin/products/seller/41/edit?lang=en',
        );
        expect(page.props('cancelHref')).toBeUndefined();
        expect(wrapper.text()).not.toContain(t('common.cancel'));
    });

    it('passes the stored fields, the edited language, the variant state and the previews to the fields', () => {
        const wrapper = editPage();
        const fields = wrapper.getComponent(StoreProductFormFields);

        expect(fields.props()).toEqual({
            categories: props.categories,
            brands: props.brands,
            colors: props.colors,
            attributes: props.attributes,
            flashDeals: props.flashDeals,
            taxes: props.taxes,
            sections: props.sections,
            product,
            name: 'زيت زيتون',
            unit: 'زجاجة',
            description: '<p><strong>Fresh</strong> oil</p>',
            variants,
            variantsUrl: editVariantCombinations.url(product),
            initialFiles,
            errors: { slug: 'Slug taken' },
        });
        expect(editVariantCombinations.url(product)).toBe(
            '/temp/admin/products/41/variant-combinations',
        );
        const variantFields = wrapper.getComponent(StoreProductVariantFields);
        expect(variantFields.props()).toEqual({
            colors: props.colors,
            attributes: props.attributes,
            unitPrice: '12.50',
            initial: variants,
            rowsUrl: editVariantCombinations.url(product),
            errors: { slug: 'Slug taken' },
            hasVariants: true,
        });
        expect(wrapper.text()).toContain('Slug taken');
    });

    it('fills every field with the stored and translated values', async () => {
        const wrapper = editPage();
        await flushPromises();

        expect(inputValue(wrapper, '#name')).toBe('زيت زيتون');
        expect(inputValue(wrapper, '#unit')).toBe('زجاجة');
        expect(inputValue(wrapper, '#category_id')).toBe('12');
        expect(inputValue(wrapper, '#brand_id')).toBe('5');
        expect(inputValue(wrapper, '#weight')).toBe('1.25');
        expect(inputValue(wrapper, '#max_qty')).toBe('24');
        expect(inputValue(wrapper, '#min_qty')).toBe('2');
        expect(inputValue(wrapper, '#tags')).toBe('oil,olive');
        expect(inputValue(wrapper, '#video_provider')).toBe('vimeo');
        expect(inputValue(wrapper, '#video_link')).toBe(
            'https://vimeo.com/123',
        );
        expect(inputValue(wrapper, '#unit_price')).toBe('12.50');
        expect(inputValue(wrapper, '#date_range')).toBe(
            '01-11-2026 08:00:00 to 30-11-2026 20:30:00',
        );
        expect(inputValue(wrapper, '#discount')).toBe('1.50');
        expect(inputValue(wrapper, '#discount_type')).toBe('amount');
        expect(inputValue(wrapper, '#current_stock')).toBe('40');
        expect(inputValue(wrapper, '#sku')).toBe('EVOO-1');
        expect(inputValue(wrapper, '#external_link')).toBe(
            'https://example.com/oil',
        );
        expect(inputValue(wrapper, '#external_link_btn')).toBe('Buy elsewhere');
        expect(inputValue(wrapper, '#meta_title')).toBe('Olive oil');
        expect(inputValue(wrapper, '#meta_description')).toBe('Fresh oil');
        expect(inputValue(wrapper, '#slug')).toBe('olive-oil');
        expect(inputValue(wrapper, '#flat_shipping_cost')).toBe('4.95');
        expect(inputValue(wrapper, '#est_shipping_days')).toBe('3');
        expect(inputValue(wrapper, '#low_stock_quantity')).toBe('5');
        expect(inputValue(wrapper, '#flash_deal_id')).toBe('9');
        expect(inputValue(wrapper, '#flash_discount')).toBe('1.50');
        expect(inputValue(wrapper, '#flash_discount_type')).toBe('amount');
        expect(
            wrapper
                .findAll('input[type="radio"]:checked')
                .map((input) => [
                    input.attributes('name'),
                    (input.element as HTMLInputElement).value,
                ]),
        ).toEqual([
            ['shipping_type', 'flat_rate'],
            ['stock_visibility_state', 'text'],
        ]);
        const switches = wrapper.findAll(
            'input[type="checkbox"][name][value="1"]:checked',
        );
        expect(switches.map((input) => input.attributes('name'))).toEqual([
            'is_quantity_multiplied',
            'featured',
        ]);
        expect(inputValue(wrapper, 'input[name="tax[]"]')).toBe('21.00');
        expect(
            wrapper
                .findAllComponents(StoreProductListField)
                .map((field) => field.props('values')),
        ).toEqual([['Cold pressed', 'Organic'], ['8712345678906']]);
        expect(
            wrapper
                .findAll('input[name="product_types[]"]')
                .map((input) => (input.element as HTMLInputElement).value),
        ).toEqual(['Cold pressed', 'Organic']);
        expect(
            wrapper
                .findAll('input[name="product_barcodes[]"]')
                .map((input) => (input.element as HTMLInputElement).value),
        ).toEqual(['8712345678906']);
        const editor = wrapper.getComponent(StoreRichTextField);
        expect(editor.props('modelValue')).toBe(
            '<p><strong>Fresh</strong> oil</p>',
        );
        const uploads = wrapper
            .findAllComponents(StoreUploadField)
            .map((field) => [
                field.props('name'),
                [field.props('modelValue'), field.props('initialFiles')],
            ]);
        expect(Object.fromEntries(uploads)).toEqual({
            photos: ['5,6', initialFiles.photos],
            thumbnail_img: ['7', initialFiles.thumbnail_img],
            old_photo: ['4', initialFiles.old_photo],
            pdf: ['9', initialFiles.pdf],
            meta_img: ['8', []],
            'variants[0][image]': ['12', variants.rows[0].files],
            'variants[1][image]': ['', []],
        });
    });

    it('shows the stored choice and its stock rows without regenerating them', async () => {
        const wrapper = editPage();
        await flushPromises();

        expect(
            wrapper
                .findAll('input[name="choice_options[0][values][]"]:checked')
                .map((input) => input.attributes('value')),
        ).toEqual(['S', 'Extra Large']);
        expect(
            wrapper
                .get('input[name="choice_options[0][attribute_id]"]')
                .attributes('value'),
        ).toBe('1');
        expect(
            wrapper
                .findAll('tbody th[scope="row"] span')
                .map((cell) => cell.text()),
        ).toEqual(['S', 'ExtraLarge']);
        expect(
            ['price', 'sku', 'qty'].map((field) =>
                wrapper
                    .findAll(`input[name$="[${field}]"]`)
                    .map((input) => input.attributes('value')),
            ),
        ).toEqual([
            ['13.00', '15.00'],
            ['OIL-S', ''],
            ['4', '0'],
        ]);
        // Variants carry their own stock: the single quantity is hidden.
        const stock = wrapper.get('#current_stock').element;
        expect(stock.closest('.contents')?.getAttribute('style')).toContain(
            'display: none',
        );
    });

    it.each([
        { permissions: ['product_edit', 'view_uploads'], buttons: 7 },
        { permissions: ['product_edit'], buttons: 0 },
    ])(
        'offers $buttons upload pickers with $permissions',
        ({ permissions, buttons }) => {
            mockPage(permissions);
            const wrapper = editPage();
            const label = t('store.uploads.choose');
            const all = wrapper.findAll('button');
            const choose = all.filter((button) => button.text() === label);

            expect(choose).toHaveLength(buttons);
        },
    );
});
