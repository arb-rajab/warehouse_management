import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { t } from '@/lib/i18n';
import StoreDynamicPopupFormFields from './StoreDynamicPopupFormFields.vue';

vi.mock('@/components/StoreUploadField.vue', () => ({
    default: {
        props: ['id', 'name', 'label', 'modelValue', 'type', 'error'],
        template: '<div>{{label}} {{error}}</div>',
    },
}));
function mountFields() {
    return mount(StoreDynamicPopupFormFields, {
        props: {
            popup: {
                id: 1,
                title: 'مرحبا',
                summary: '<img src=x>',
                banner: '12',
                btn_text: 'Open',
                delay_seconds: 7,
                offer_id: 4,
                show_page: 5,
            },
            offers: [{ id: 4, name: 'Offer' }],
            categories: [{ id: 5, name: 'فواكه' }],
            errors: { banner: 'Invalid image', summary: 'Invalid summary' },
        },
    });
}
describe('StoreDynamicPopupFormFields', () => {
    it('renders stored values escaped and every translated field with native limits', () => {
        const wrapper = mountFields();

        for (const key of [
            'title',
            'summary',
            'banner',
            'buttonText',
            'delay',
            'offer',
            'page',
            'home',
            'choose',
        ]) {
            expect(wrapper.text()).toContain(
                t(`store.dynamicPopups.fields.${key}`),
            );
        }

        for (const id of [
            'title',
            'summary',
            'btn_text',
            'delay_seconds',
            'offer_id',
            'show_page',
        ]) {
            expect(wrapper.get(`#${id}`).attributes('required')).toBeDefined();
        }

        expect(wrapper.get('#title').attributes('maxlength')).toBe('100');
        expect(wrapper.get('#summary').attributes('maxlength')).toBe('191');
        expect(wrapper.get('#btn_text').attributes('maxlength')).toBe('191');
        expect(wrapper.get('#delay_seconds').attributes()).toMatchObject({
            type: 'number',
            min: '0',
            max: '100',
            step: '1',
        });
        expect((wrapper.get('#title').element as HTMLInputElement).value).toBe(
            'مرحبا',
        );
        expect(
            (wrapper.get('#summary').element as HTMLTextAreaElement).value,
        ).toBe('<img src=x>');
        expect(wrapper.find('img').exists()).toBe(false);
        expect(
            (wrapper.get('#offer_id').element as HTMLSelectElement).value,
        ).toBe('4');
        expect(
            (wrapper.get('#show_page').element as HTMLSelectElement).value,
        ).toBe('5');
        expect(wrapper.text()).toContain('Invalid summary');
    });
    it('reuses the image picker with its current value and server validation error', async () => {
        const wrapper = mountFields();
        expect(wrapper.getComponent(StoreUploadField).props()).toMatchObject({
            id: 'banner',
            name: 'banner',
            label: t('store.dynamicPopups.fields.banner'),
            modelValue: '12',
            type: 'image',
            error: 'Invalid image',
        });
        wrapper
            .getComponent(StoreUploadField)
            .vm.$emit('update:modelValue', '25');
        await wrapper.vm.$nextTick();
        expect(wrapper.getComponent(StoreUploadField).props('modelValue')).toBe(
            '25',
        );
    });
    it('retains changed targeting when an image selection rerenders the fields', async () => {
        const wrapper = mountFields();
        await wrapper.get('#offer_id').setValue('4');
        await wrapper.get('#show_page').setValue('0');
        wrapper
            .getComponent(StoreUploadField)
            .vm.$emit('update:modelValue', '25');
        await wrapper.vm.$nextTick();
        expect(
            (wrapper.get('#offer_id').element as HTMLSelectElement).value,
        ).toBe('4');
        expect(
            (wrapper.get('#show_page').element as HTMLSelectElement).value,
        ).toBe('0');
    });
});
