import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import {
    storeDigitalProductForm,
    storeDigitalProductTax,
    storeProductForm,
    storeProductVariantState,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import type { StoreProductSyncedField } from '@/types/store';
import DigitalCreate from './DigitalProducts/Create.vue';
import DigitalEdit from './DigitalProducts/Edit.vue';
import Create from './Products/Create.vue';
import Edit from './Products/Edit.vue';

const { usePageMock, formSlotPropsMock, submitMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    formSlotPropsMock: vi.fn(() => ({
        errors: {} as Record<string, string>,
        processing: false,
    })),
    submitMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(formSlotPropsMock, submitMock),
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

const sections = {
    shipping: true,
    productWiseShipping: true,
    flashDeals: true,
    vat: true,
    cashOnDelivery: true,
};
const options = {
    categories: [
        { id: 1, name: 'Pantry' },
        { id: 12, name: '-- Oils' },
    ],
    brands: [{ id: 5, name: 'Olivia' }],
    colors: [{ id: 3, name: 'Red', code: '#FF0000' }],
    attributes: [{ id: 1, name: 'Size' }],
    flashDeals: [{ id: 9, name: 'Autumn deals' }],
    taxes: [storeDigitalProductTax()],
    sections,
};
const languages = [
    { code: 'en', name: 'English' },
    { code: 'ar', name: 'Arabic' },
];
const editProps = {
    ...options,
    product: storeProductForm(),
    name: 'Olive oil',
    arName: 'زيت زيتون',
    readOnlyFields: [] as StoreProductSyncedField[],
    unit: 'bottle',
    description: '<p>Fresh oil</p>',
    lang: 'en',
    languages,
    listType: 'admin' as const,
    variants: storeProductVariantState(),
    initialFiles: {},
};
const digitalOptions = {
    categories: [{ id: 12, name: 'E-books' }],
    taxes: [storeDigitalProductTax()],
};
const wrappers: VueWrapper[] = [];

function mounted(wrapper: VueWrapper): VueWrapper {
    wrappers.push(wrapper);

    return wrapper;
}

async function submit(wrapper: VueWrapper): Promise<void> {
    await wrapper.get('form').trigger('submit');
    await flushPromises();
}

async function fillRequiredCreateFields(wrapper: VueWrapper): Promise<void> {
    await wrapper.get('#name').setValue('Olive oil');
    await wrapper.get('#unit').setValue('bottle');
    await wrapper.get('#category_id').setValue('1');
}

beforeEach(() => {
    vi.clearAllMocks();
    formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    usePageMock.mockReturnValue({
        url: '/temp/admin/products/create',
        props: {
            ...defaultAuthProps(),
            store: storeSharedProps({
                permissions: [
                    'add_new_product',
                    'add_digital_product',
                    'view_uploads',
                ],
            }),
        },
    });
});
afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    i18n.global.locale.value = 'en';
});

describe('physical product create form', () => {
    it('sends nothing while a required field is empty, and says which', async () => {
        const wrapper = mounted(
            mount(Create, { props: options, attachTo: document.body }),
        );
        await submit(wrapper);

        expect(submitMock).not.toHaveBeenCalled();
        expect(wrapper.get('form').attributes('novalidate')).toBeDefined();
        expect(wrapper.text()).toContain('The name field is required.');
        expect(wrapper.text()).toContain('The category field is required.');
        expect(wrapper.text()).toContain('The unit field is required.');
        expect(wrapper.get('[role="alert"]').text()).toBe(
            'Some fields need correcting. The first one is selected.',
        );
        expect(document.activeElement).toBe(wrapper.get('#name').element);
        expect(wrapper.get('#name').attributes('aria-invalid')).toBe('true');
    });

    it('keeps the required marks and attributes the server rules ask for', () => {
        const wrapper = mounted(
            mount(Create, { props: options, attachTo: document.body }),
        );

        for (const id of ['name', 'unit', 'category_id', 'unit_price']) {
            expect(wrapper.get(`#${id}`).attributes('required')).toBeDefined();
        }
        expect(wrapper.get('#ar_name').attributes('required')).toBeUndefined();
    });

    it('sends a valid form once', async () => {
        const wrapper = mounted(
            mount(Create, { props: options, attachTo: document.body }),
        );
        await fillRequiredCreateFields(wrapper);
        await submit(wrapper);

        expect(submitMock).toHaveBeenCalledTimes(1);
        expect((submitMock.mock.calls[0][0] as FormData).get('name')).toBe(
            'Olive oil',
        );
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });

    it('clears a message as soon as the field is fixed', async () => {
        const wrapper = mounted(
            mount(Create, { props: options, attachTo: document.body }),
        );
        await submit(wrapper);
        await fillRequiredCreateFields(wrapper);

        expect(wrapper.text()).not.toContain('The name field is required.');
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });

    it('keeps a flat discount below the unit price, and allows none', async () => {
        const wrapper = mounted(
            mount(Create, { props: options, attachTo: document.body }),
        );
        await fillRequiredCreateFields(wrapper);
        await wrapper.get('#unit_price').setValue('10');
        await wrapper.get('#discount').setValue('15');
        await submit(wrapper);

        expect(submitMock).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain(
            'The discount must be less than the unit price (10).',
        );

        await wrapper.get('#discount').setValue('0');
        await wrapper.get('#unit_price').setValue('0');
        await submit(wrapper);

        expect(submitMock).toHaveBeenCalledTimes(1);
    });

    it('keeps a percentage discount below 100', async () => {
        const wrapper = mounted(
            mount(Create, { props: options, attachTo: document.body }),
        );
        await fillRequiredCreateFields(wrapper);
        await wrapper.get('#discount_type').setValue('percent');
        await wrapper.get('#discount').setValue('100');
        await submit(wrapper);

        expect(wrapper.text()).toContain(
            'The discount percentage must be less than 100.',
        );
        expect(submitMock).not.toHaveBeenCalled();
    });

    it('keeps the minimum purchase quantity at or below the maximum', async () => {
        const wrapper = mounted(
            mount(Create, { props: options, attachTo: document.body }),
        );
        await fillRequiredCreateFields(wrapper);
        await wrapper.get('#max_qty').setValue('24');
        await wrapper.get('#min_qty').setValue('30');
        await submit(wrapper);

        expect(wrapper.text()).toContain(
            'The minimum purchase quantity field must be less than or equal to 24.',
        );
        expect(submitMock).not.toHaveBeenCalled();
    });

    it('checks a field as soon as it is left', async () => {
        const wrapper = mounted(
            mount(Create, { props: options, attachTo: document.body }),
        );
        await wrapper.get('#date_range').setValue('2026-11-01');
        await wrapper.get('#date_range').trigger('focusout');

        expect(wrapper.text()).toContain(
            'The date range field must match the format d-m-Y H:i:s to d-m-Y H:i:s.',
        );
        expect(wrapper.text()).not.toContain('The name field is required.');
    });

    it('shows server errors and drops each once its field is edited', async () => {
        formSlotPropsMock.mockReturnValue({
            errors: {
                name: 'The name has already been taken.',
                sku: 'The sku is in use.',
            },
            processing: false,
        });
        const wrapper = mounted(
            mount(Create, { props: options, attachTo: document.body }),
        );

        expect(wrapper.text()).toContain('The name has already been taken.');
        expect(wrapper.text()).toContain('The sku is in use.');

        await wrapper.get('#sku').setValue('NEW-1');

        expect(wrapper.text()).not.toContain('The sku is in use.');
        expect(wrapper.text()).toContain('The name has already been taken.');
    });

    it('speaks Arabic with the server wording', async () => {
        i18n.global.locale.value = 'ar';
        const wrapper = mounted(
            mount(Create, { props: options, attachTo: document.body }),
        );
        await submit(wrapper);

        expect(wrapper.text()).toContain('حقل الاسم مطلوب.');
    });
});

describe('physical product edit form', () => {
    it('sends the stored product unchanged', async () => {
        const wrapper = mounted(
            mount(Edit, { props: editProps, attachTo: document.body }),
        );
        await submit(wrapper);

        expect(submitMock).toHaveBeenCalledTimes(1);
    });

    it('does not validate a read-only Otajer field as posted', async () => {
        const wrapper = mounted(
            mount(Edit, {
                attachTo: document.body,
                props: {
                    ...editProps,
                    name: '',
                    readOnlyFields: ['name'],
                },
            }),
        );
        await submit(wrapper);

        expect(wrapper.text()).not.toContain('The name field is required.');
        expect(submitMock).toHaveBeenCalledTimes(1);
    });

    it('checks each variant row', async () => {
        const wrapper = mounted(
            mount(Edit, { props: editProps, attachTo: document.body }),
        );
        await wrapper.get('input[name="variants[0][price]"]').setValue('');
        await wrapper.get('input[name="variants[1][qty]"]').setValue('1.5');
        await submit(wrapper);

        expect(submitMock).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain(
            'The variant price field is required.',
        );
        expect(wrapper.text()).toContain(
            'The variant quantity field must be an integer.',
        );
    });

    it('refuses a barcode posted twice', async () => {
        const wrapper = mounted(
            mount(Edit, { props: editProps, attachTo: document.body }),
        );
        const add = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Add barcode');
        await add?.trigger('click');
        await wrapper
            .findAll('input[name="product_barcodes[]"]')[1]
            .setValue('8712345678906');
        await submit(wrapper);

        expect(wrapper.text()).toContain(
            'The product barcode field has a duplicate value.',
        );
        expect(submitMock).not.toHaveBeenCalled();
    });
});

describe('digital product forms', () => {
    it('sends nothing until the name and category are given', async () => {
        const wrapper = mounted(
            mount(DigitalCreate, {
                props: digitalOptions,
                attachTo: document.body,
            }),
        );
        await submit(wrapper);

        expect(submitMock).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('The name field is required.');
        expect(wrapper.text()).toContain('The category field is required.');

        await wrapper.get('#name').setValue('Recipe e-book');
        await wrapper.get('#category_id').setValue('12');
        await submit(wrapper);

        expect(submitMock).toHaveBeenCalledTimes(1);
    });

    it('checks the discount period but not the discount against the price', async () => {
        const wrapper = mounted(
            mount(DigitalEdit, {
                attachTo: document.body,
                props: {
                    ...digitalOptions,
                    product: storeDigitalProductForm({
                        discount: '5000',
                        discount_type: 'amount',
                    }),
                    name: 'Recipe e-book',
                    description: '<p>Recipes</p>',
                    lang: 'en',
                    languages,
                    initialFiles: {},
                },
            }),
        );
        await submit(wrapper);

        expect(submitMock).toHaveBeenCalledTimes(1);

        await wrapper
            .get('#date_range')
            .setValue('30-11-2026 00:00:00 to 01-11-2026 00:00:00');
        await submit(wrapper);

        expect(submitMock).toHaveBeenCalledTimes(1);
        expect(wrapper.text()).toContain(
            'The date range field must match the format d-m-Y H:i:s to d-m-Y H:i:s.',
        );
    });
});
