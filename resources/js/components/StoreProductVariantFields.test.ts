import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import { variantCombinations } from '@/actions/App/Http/Controllers/Store/Admin/ProductController';
import { attributeValues } from '@/actions/App/Http/Controllers/Store/Admin/ProductSearchController';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { i18n, t } from '@/lib/i18n';
import {
    storeProductVariantState,
    storeSharedProps,
    storeUpload,
} from '@/testing/factories';
import StoreProductVariantFields from './StoreProductVariantFields.vue';

const { usePageMock, useHttpMock, postMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    useHttpMock: vi.fn(),
    postMock: vi.fn(),
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

const colors = [
    { id: 4, name: 'Light Blue', code: '#ADD8E6' },
    { id: 3, name: 'Red', code: '#FF0000' },
];
const attributes = [
    { id: 1, name: 'Size' },
    { id: 2, name: 'Bottle' },
];
const values: Record<number, { id: number; value: string }[]> = {
    1: [
        { id: 10, value: 'S' },
        { id: 11, value: 'Extra Large' },
    ],
    2: [{ id: 20, value: '1.5 L' }],
};
const wrappers: VueWrapper[] = [];

function mountFields(props: Record<string, unknown> = {}) {
    const wrapper = mount(StoreProductVariantFields, {
        props: { colors, attributes, unitPrice: '12.50', errors: {}, ...props },
    });
    wrappers.push(wrapper);

    return wrapper;
}

const rowsUrl = '/temp/admin/products/41/variant-combinations';

/** The payloads the edit form's variant endpoint received, in order. */
function editRowPosts() {
    return postMock.mock.calls
        .filter(([url]) => url === rowsUrl)
        .map(([, data]) => data);
}

/** The payloads the variant endpoint received, in order. */
function variantPosts() {
    return postMock.mock.calls
        .filter(([url]) => url === variantCombinations.url())
        .map(([, data]) => data);
}

async function settle() {
    await flushPromises();
    await vi.advanceTimersByTimeAsync(300);
    await flushPromises();
}

beforeEach(() => {
    vi.useFakeTimers();
    vi.clearAllMocks();
    usePageMock.mockReturnValue({
        props: { store: storeSharedProps({ permissions: ['view_uploads'] }) },
    });
    useHttpMock.mockImplementation((data: Record<string, unknown>) => {
        const state = reactive({
            ...data,
            processing: false,
            cancel: () => {},
        }) as Record<string, unknown>;
        state.post = (url: string) =>
            postMock(
                url,
                JSON.parse(
                    JSON.stringify({
                        ...data,
                        ...Object.fromEntries(
                            Object.keys(data).map((key) => [key, state[key]]),
                        ),
                    }),
                ),
            );
        state.get = async () => [];

        return state;
    });
    postMock.mockImplementation(
        async (url: string, data: Record<string, unknown>) => {
            if (url === attributeValues.url()) {
                return values[data.attribute_id as number];
            }

            const chosen = (
                data.choice_options as { values: string[] }[]
            ).flatMap((choice) => choice.values);

            return chosen.map((value) => ({
                variant: value.replaceAll(' ', ''),
                price: data.unit_price,
                sku: '',
                qty: '10',
            }));
        },
    );
});
afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    vi.useRealTimers();
    i18n.global.locale.value = 'en';
});

describe('StoreProductVariantFields', () => {
    it.each(['en', 'ar'] as const)(
        'labels the colours, attributes and variant table in %s',
        async (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountFields();
            await wrapper
                .get('input[value="1"][type="checkbox"]:not([name])')
                .setValue(true);
            await settle();
            await wrapper
                .get('input[name="choice_options[0][values][]"][value="S"]')
                .setValue(true);
            await settle();

            for (const key of [
                'useColors',
                'colors',
                'attributes',
                'attributesHelp',
                'variant',
                'variantPrice',
                'sku',
                'quantity',
                'photo',
                'variantsCaption',
            ]) {
                expect(wrapper.text()).toContain(
                    t(`store.products.form.${key}`),
                );
            }

            expect(wrapper.text()).toContain(
                t('store.products.form.valuesOf', { attribute: 'Size' }),
            );
            expect(
                wrapper
                    .get('input[name="variants[0][price]"]')
                    .attributes('aria-label'),
            ).toBe(t('store.products.form.variantPriceOf', { variant: 'S' }));
            expect(
                wrapper
                    .get('input[name="variants[0][sku]"]')
                    .attributes('aria-label'),
            ).toBe(t('store.products.form.skuOf', { variant: 'S' }));
            expect(
                wrapper
                    .get('input[name="variants[0][qty]"]')
                    .attributes('aria-label'),
            ).toBe(t('store.products.form.quantityOf', { variant: 'S' }));
            expect(wrapper.getComponent(StoreUploadField).props('label')).toBe(
                t('store.products.form.photoOf', { variant: 'S' }),
            );
        },
    );

    it('keeps the colours off and unposted until switched on', async () => {
        const wrapper = mountFields();
        const colorInputs = wrapper.findAll('input[name="colors[]"]');

        expect(colorInputs.map((input) => input.attributes('value'))).toEqual([
            '#ADD8E6',
            '#FF0000',
        ]);
        expect(wrapper.get('fieldset').attributes('disabled')).toBeDefined();
        await wrapper.get('input[name="colors_active"]').setValue(true);

        expect(
            wrapper.get('input[name="colors_active"]').attributes('value'),
        ).toBe('1');
        expect(wrapper.get('fieldset').attributes('disabled')).toBeUndefined();
    });

    it('loads an attribute’s values once and posts the choices in list order to the typed endpoint', async () => {
        const wrapper = mountFields();
        await wrapper.get('input[name="colors_active"]').setValue(true);
        await wrapper
            .get('input[name="colors[]"][value="#FF0000"]')
            .setValue(true);
        await wrapper
            .get('input[name="colors[]"][value="#ADD8E6"]')
            .setValue(true);
        const attributeBoxes = wrapper.findAll(
            'input[type="checkbox"]:not([name])',
        );
        await attributeBoxes[1].setValue(true);
        await attributeBoxes[0].setValue(true);
        await settle();

        expect(
            postMock.mock.calls
                .filter(([url]) => url === attributeValues.url())
                .map(([, data]) => data),
        ).toEqual([{ attribute_id: 2 }, { attribute_id: 1 }]);
        expect(
            wrapper
                .findAll('[data-attribute]')
                .map((set) => set.attributes('data-attribute')),
        ).toEqual(['1', '2']);
        expect(
            wrapper
                .get('input[name="choice_options[0][attribute_id]"]')
                .attributes('value'),
        ).toBe('1');
        expect(
            wrapper
                .get('input[name="choice_options[1][attribute_id]"]')
                .attributes('value'),
        ).toBe('2');
        await wrapper
            .get(
                'input[name="choice_options[0][values][]"][value="Extra Large"]',
            )
            .setValue(true);
        await wrapper
            .get('input[name="choice_options[0][values][]"][value="S"]')
            .setValue(true);
        await wrapper
            .get('input[name="choice_options[1][values][]"][value="1.5 L"]')
            .setValue(true);
        await settle();

        expect(variantPosts().at(-1)).toEqual({
            colors_active: true,
            colors: ['#ADD8E6', '#FF0000'],
            choice_options: [
                { attribute_id: 1, values: ['S', 'Extra Large'] },
                { attribute_id: 2, values: ['1.5 L'] },
            ],
            unit_price: '12.50',
        });
    });

    it('renders the generated rows with their defaults and reports that the product has variants', async () => {
        const wrapper = mountFields();
        await wrapper
            .findAll('input[type="checkbox"]:not([name])')[0]
            .setValue(true);
        await settle();
        await wrapper
            .get('input[name="choice_options[0][values][]"][value="S"]')
            .setValue(true);
        await wrapper
            .get(
                'input[name="choice_options[0][values][]"][value="Extra Large"]',
            )
            .setValue(true);
        await settle();

        expect(wrapper.findAll('tbody tr')).toHaveLength(2);
        expect(
            wrapper
                .findAll('input[name^="variants["][name$="[variant]"]')
                .map((input) => input.attributes('value')),
        ).toEqual(['S', 'ExtraLarge']);
        expect(
            wrapper.get('input[name="variants[1][price]"]').attributes(),
        ).toMatchObject({
            value: '12.50',
            type: 'number',
            min: '0',
            max: '999999999999999999.99',
            step: '0.01',
            required: '',
        });
        expect(
            wrapper.get('input[name="variants[1][sku]"]').attributes(),
        ).toMatchObject({ value: '', maxlength: '255' });
        expect(
            wrapper.get('input[name="variants[1][qty]"]').attributes(),
        ).toMatchObject({ value: '10', min: '0', step: '1', required: '' });
        expect(
            wrapper
                .findAllComponents(StoreUploadField)
                .map((field) => field.props()),
        ).toEqual([
            expect.objectContaining({
                id: 'variant_image_0',
                name: 'variants[0][image]',
                type: 'image',
            }),
            expect.objectContaining({
                id: 'variant_image_1',
                name: 'variants[1][image]',
                type: 'image',
            }),
        ]);
        expect(wrapper.emitted('update:hasVariants')?.at(-1)).toEqual([true]);
    });

    it('regenerates the rows at a new unit price and clears them with the last choice', async () => {
        const wrapper = mountFields();
        await wrapper
            .findAll('input[type="checkbox"]:not([name])')[0]
            .setValue(true);
        await settle();
        await wrapper
            .get('input[name="choice_options[0][values][]"][value="S"]')
            .setValue(true);
        await settle();
        await wrapper.setProps({ unitPrice: '20' });
        await settle();

        expect(variantPosts().at(-1)).toMatchObject({ unit_price: '20' });
        expect(
            wrapper.get('input[name="variants[0][price]"]').attributes('value'),
        ).toBe('20');
        const calls = variantPosts().length;
        await wrapper
            .findAll('input[type="checkbox"]:not([name])')[0]
            .setValue(false);
        await settle();

        expect(variantPosts()).toHaveLength(calls);
        expect(wrapper.find('table').exists()).toBe(false);
        expect(wrapper.emitted('update:hasVariants')?.at(-1)).toEqual([false]);
    });

    it('says when an attribute has no values or a request fails, and shows field errors', async () => {
        values[2] = [];
        const wrapper = mountFields({
            errors: {
                choice_options: 'Too many variants',
                'variants.0.price': 'Bad price',
                variants: 'Rows missing',
                colors: 'Bad colour',
            },
        });
        await wrapper
            .findAll('input[type="checkbox"]:not([name])')[1]
            .setValue(true);
        await settle();

        expect(wrapper.text()).toContain(t('store.products.form.noValues'));
        expect(wrapper.text()).toContain('Too many variants');
        expect(wrapper.text()).toContain('Rows missing');
        expect(wrapper.text()).toContain('Bad colour');
        postMock.mockRejectedValue(new Error('offline'));
        await wrapper
            .findAll('input[type="checkbox"]:not([name])')[0]
            .setValue(true);
        await settle();

        expect(wrapper.get('[role="alert"]').text()).toBe(
            t('store.products.form.valuesFailed'),
        );
        values[2] = [{ id: 20, value: '1.5 L' }];
    });

    it('says when the server refuses the choices instead of showing no rows', async () => {
        const wrapper = mountFields();
        await wrapper.get('input[name="colors_active"]').setValue(true);
        postMock.mockResolvedValue(undefined);
        await wrapper
            .get('input[name="colors[]"][value="#FF0000"]')
            .setValue(true);
        await settle();

        expect(wrapper.get('[role="alert"]').text()).toBe(
            t('store.products.form.variantsFailed'),
        );
        expect(wrapper.find('table').exists()).toBe(false);
    });

    it('says when the variants cannot be generated', async () => {
        const wrapper = mountFields();
        await wrapper.get('input[name="colors_active"]').setValue(true);
        postMock.mockRejectedValue(new Error('offline'));
        await wrapper
            .get('input[name="colors[]"][value="#FF0000"]')
            .setValue(true);
        await settle();

        expect(wrapper.get('[role="alert"]').text()).toBe(
            t('store.products.form.variantsFailed'),
        );
        expect(wrapper.find('table').exists()).toBe(false);
    });

    it('starts from the stored choice and stock rows without asking the server', async () => {
        const initial = storeProductVariantState();
        const wrapper = mountFields({ initial, rowsUrl });
        await settle();

        expect(postMock).not.toHaveBeenCalled();
        expect(wrapper.emitted('update:hasVariants')).toEqual([[true]]);
        expect(
            wrapper
                .findAll('input[type="checkbox"]:not([name]):checked')
                .map((input) => input.attributes('value')),
        ).toEqual(['1']);
        expect(
            wrapper
                .findAll('input[name="choice_options[0][values][]"]:checked')
                .map((input) => input.attributes('value')),
        ).toEqual(['S', 'Extra Large']);
        expect(
            wrapper
                .findAll('tbody tr')
                .map((row) => [
                    row.get('span').text(),
                    row.get('input[name$="[price]"]').attributes('value'),
                    row.get('input[name$="[sku]"]').attributes('value'),
                    row.get('input[name$="[qty]"]').attributes('value'),
                ]),
        ).toEqual([
            ['S', '13.00', 'OIL-S', '4'],
            ['ExtraLarge', '15.00', '', '0'],
        ]);
        expect(
            wrapper
                .findAllComponents(StoreUploadField)
                .map((field) => [
                    field.props('modelValue'),
                    field.props('initialFiles'),
                ]),
        ).toEqual([
            ['12', [storeUpload({ id: 12, name: 'Small bottle' })]],
            ['', []],
        ]);
        expect(
            wrapper.get('input[name="variants[0][image]"]').attributes('value'),
        ).toBe('12');
    });

    it('regenerates through the edit endpoint with the stored attributes ahead of new ones', async () => {
        const initial = storeProductVariantState({
            choice_options: [{ attribute_id: 2, values: ['1.5 L'] }],
            attribute_values: { 2: [{ id: 20, value: '1.5 L' }] },
            rows: [{ variant: '1.5L', price: '9.00', sku: 'B', qty: '1' }],
        });
        const wrapper = mountFields({ initial, rowsUrl });
        await wrapper
            .get('input[value="1"][type="checkbox"]:not([name])')
            .setValue(true);
        await settle();
        await wrapper
            .get('input[name="choice_options[1][values][]"][value="S"]')
            .setValue(true);
        await settle();

        expect(
            wrapper
                .findAll('input[name$="[attribute_id]"]')
                .map((input) => input.attributes('value')),
        ).toEqual(['2', '1']);
        expect(variantPosts()).toEqual([]);
        expect(editRowPosts()).toEqual([
            {
                colors_active: false,
                colors: [],
                choice_options: [
                    { attribute_id: 2, values: ['1.5 L'] },
                    { attribute_id: 1, values: ['S'] },
                ],
                unit_price: '12.50',
            },
        ]);
    });

    it('offers a value listed twice once', () => {
        const initial = storeProductVariantState({
            attribute_values: {
                1: [
                    { id: 10, value: 'S' },
                    { id: 12, value: 'S' },
                    { id: 11, value: 'Extra Large' },
                ],
            },
        });
        const wrapper = mountFields({ initial, rowsUrl });

        expect(
            wrapper
                .findAll('input[name="choice_options[0][values][]"]')
                .map((input) => input.attributes('value')),
        ).toEqual(['S', 'Extra Large']);
    });
});
