import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import FieldError from '@/components/FieldError.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { i18n, t } from '@/lib/i18n';
import {
    storeBrandForm,
    storeSharedProps,
    storeUpload,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import StoreBrandFormFields from './StoreBrandFormFields.vue';
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
function fields(errors = {}) {
    return mount(StoreBrandFormFields, {
        props: {
            brand: storeBrandForm({ logo: '4' }),
            name: 'تمور',
            initialFiles: { logo: [storeUpload()] },
            errors,
        },
    });
}
describe('brand fields', () => {
    afterEach(() => {
        i18n.global.locale.value = 'en';
    });
    it.each(['en', 'ar'] as const)(
        'renders every label, value and native constraint in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = fields();

            for (const key of [
                'name',
                'logo',
                'metaTitle',
                'metaDescription',
                'slug',
            ]) {
                expect(wrapper.text()).toContain(t(`store.brands.${key}`));
            }

            expect(wrapper.get('#name').attributes()).toMatchObject({
                required: '',
                maxlength: '50',
                value: 'تمور',
            });
            expect(wrapper.get('#meta_title').attributes()).toMatchObject({
                maxlength: '255',
                value: 'Dates SEO title',
            });
            const description = wrapper.get('#meta_description');
            expect(description.attributes('maxlength')).toBe('65535');
            expect((description.element as HTMLTextAreaElement).value).toBe(
                'Dates SEO description',
            );
            expect(wrapper.get('#slug').attributes()).toMatchObject({
                maxlength: '255',
                value: 'dates-brand',
            });
            expect(
                wrapper.getComponent(StoreUploadField).props(),
            ).toMatchObject({
                name: 'logo',
                label: t('store.brands.logo'),
                type: 'image',
                multiple: false,
                modelValue: '4',
                initialFiles: [storeUpload()],
            });
            expect(wrapper.find('#order_level').exists()).toBe(false);
        },
    );
    it('offers blank create fields without a slug input', () => {
        const wrapper = mount(StoreBrandFormFields, { props: { errors: {} } });
        expect(wrapper.get('#name').attributes('value')).toBe('');
        expect(wrapper.find('#slug').exists()).toBe(false);
        expect(wrapper.getComponent(StoreUploadField).props()).toMatchObject({
            modelValue: '',
        });
    });
    it('shows backend errors and keeps edited values across error renders', async () => {
        const wrapper = fields({
            name: 'Name error',
            logo: 'Logo error',
            meta_title: 'Title error',
            meta_description: 'Description error',
            slug: 'Slug error',
        });
        expect(wrapper.text()).toContain('Name error');
        expect(wrapper.text()).toContain('Title error');
        expect(wrapper.text()).toContain('Slug error');
        expect(
            wrapper
                .findAllComponents(FieldError)
                .map((c) => c.props('message')),
        ).toContain('Description error');
        expect(wrapper.getComponent(StoreUploadField).props('error')).toBe(
            'Logo error',
        );
        await wrapper.get('#meta_description').setValue('Retained description');
        await wrapper
            .getComponent(StoreUploadField)
            .vm.$emit('update:modelValue', '9');
        await wrapper.setProps({ errors: { name: 'Another error' } });
        expect(
            (wrapper.get('#meta_description').element as HTMLTextAreaElement)
                .value,
        ).toBe('Retained description');
        expect(wrapper.getComponent(StoreUploadField).props('modelValue')).toBe(
            '9',
        );
    });
});
