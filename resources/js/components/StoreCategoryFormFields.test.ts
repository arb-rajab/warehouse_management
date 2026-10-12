import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import StoreSelectField from '@/components/StoreSelectField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { i18n, t } from '@/lib/i18n';
import {
    storeCategoryForm,
    storeSharedProps,
    storeUpload,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import StoreCategoryFormFields from './StoreCategoryFormFields.vue';
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub } = await import('@/testing/inertiaStubs');

    return {
        Link: createLinkStub(),
        usePage: () => ({
            url: '/',
            props: defaultAuthProps({
                store: storeSharedProps({ permissions: [] }),
            }),
        }),
        useHttp: () => ({ processing: false, get: vi.fn(), cancel: vi.fn() }),
    };
});
const options = {
    parents: [{ id: 3, name: 'Parent' }],
    attributes: [
        { id: 2, name: 'Color' },
        { id: 6, name: 'Size' },
    ],
    commissionEnabled: true,
};
function fields(errors = {}) {
    return mount(StoreCategoryFormFields, {
        props: {
            ...options,
            category: storeCategoryForm(),
            name: 'طعام',
            initialFiles: {
                banner: [storeUpload()],
                icon: [],
                cover_image: [],
            },
            errors,
        },
    });
}
describe('category fields', () => {
    afterEach(() => {
        i18n.global.locale.value = 'en';
    });
    it.each(['en', 'ar'] as const)(
        'renders every label value and native constraint in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = fields();

            for (const key of [
                'name',
                'parent',
                'noParent',
                'ordering',
                'orderHint',
                'type',
                'physical',
                'digital',
                'banner',
                'icon',
                'cover',
                'metaTitle',
                'metaDescription',
                'slug',
                'commission',
                'attributes',
            ]) {
                expect(wrapper.text()).toContain(t(`store.categories.${key}`));
            }

            expect(wrapper.get('#name').attributes()).toMatchObject({
                required: '',
                maxlength: '50',
                value: 'طعام',
            });
            expect(wrapper.get('#order_level').attributes()).toMatchObject({
                type: 'number',
                min: '-2147483648',
                max: '2147483647',
                step: '1',
                value: '3',
            });
            expect(wrapper.get('#commision_rate').attributes()).toMatchObject({
                type: 'number',
                min: '0',
                max: '999999.99',
                step: '0.01',
                value: '2.50',
            });

            for (const id of ['meta_title', 'slug']) {
                expect(wrapper.get(`#${id}`).attributes('maxlength')).toBe(
                    '255',
                );
            }

            expect(wrapper.get('#meta_title').attributes('value')).toBe(
                'Food SEO',
            );
            expect(wrapper.get('#slug').attributes('value')).toBe('food');
            expect(
                wrapper.get('#meta_description').attributes('maxlength'),
            ).toBe('65535');
            expect(
                (
                    wrapper.get('#meta_description')
                        .element as HTMLTextAreaElement
                ).value,
            ).toBe('Food description');
            expect(
                wrapper
                    .findAllComponents(StoreSelectField)
                    .map((c) => c.props()),
            ).toMatchObject([
                {
                    id: 'parent_id',
                    required: true,
                    value: 0,
                    options: [
                        { id: 0, name: t('store.categories.noParent') },
                        ...options.parents,
                    ],
                },
                { id: 'digital', required: true, value: 0 },
            ]);
            expect(
                wrapper.get('#filtering_attributes').attributes(),
            ).toMatchObject({ multiple: '', name: 'filtering_attributes[]' });
            expect(
                (
                    wrapper.get('#filtering_attributes option[value="2"]')
                        .element as HTMLOptionElement
                ).selected,
            ).toBe(true);
            expect(
                (
                    wrapper.get('#filtering_attributes option[value="6"]')
                        .element as HTMLOptionElement
                ).selected,
            ).toBe(false);
            expect(
                wrapper
                    .findAllComponents(StoreUploadField)
                    .map((c) => c.props()),
            ).toMatchObject([
                {
                    name: 'banner',
                    type: 'image',
                    multiple: false,
                    modelValue: '4',
                    initialFiles: [storeUpload()],
                },
                {
                    name: 'icon',
                    type: 'image',
                    multiple: false,
                    modelValue: '',
                },
                {
                    name: 'cover_image',
                    type: 'image',
                    multiple: false,
                    modelValue: '',
                },
            ]);
        },
    );
    it('shows all authoritative errors and keeps draft description and picker values', async () => {
        const errors = Object.fromEntries(
            [
                'name',
                'parent_id',
                'digital',
                'order_level',
                'banner',
                'icon',
                'cover_image',
                'meta_title',
                'meta_description',
                'slug',
                'commision_rate',
                'filtering_attributes.1',
            ].map((key) => [key, `${key} invalid`]),
        );
        const wrapper = fields(errors);

        for (const error of Object.values(errors)) {
            expect(wrapper.text()).toContain(error);
        }

        await wrapper.get('#meta_description').setValue('Retained description');
        wrapper
            .findAllComponents(StoreUploadField)[0]
            .vm.$emit('update:modelValue', '9');
        await wrapper.setProps({ errors: { name: 'Another error' } });
        expect(
            (wrapper.get('#meta_description').element as HTMLTextAreaElement)
                .value,
        ).toBe('Retained description');
        expect(
            wrapper.findAllComponents(StoreUploadField)[0].props('modelValue'),
        ).toBe('9');
    });
    it('preserves root physical defaults and hides commission when its setting is disabled', () => {
        const wrapper = mount(StoreCategoryFormFields, {
            props: { ...options, commissionEnabled: false, errors: {} },
        });
        expect(wrapper.find('#commision_rate').exists()).toBe(false);
        expect(wrapper.get('#order_level').attributes('value')).toBe('0');
        expect(
            wrapper.get('#digital option[value="0"]').attributes('selected'),
        ).toBeDefined();
        expect(
            wrapper.get('#parent_id option[value="0"]').attributes('selected'),
        ).toBeDefined();
    });
});
