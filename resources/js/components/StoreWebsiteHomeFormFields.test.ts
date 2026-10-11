import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { i18n, t } from '@/lib/i18n';
import { storeWebsiteHomeSettings } from '@/testing/factories';
import StoreWebsiteHomeFormFields from './StoreWebsiteHomeFormFields.vue';

vi.mock('@inertiajs/vue3', () => ({
    useHttp: () =>
        reactive({
            processing: false,
            get: () => Promise.resolve(),
            post: () => Promise.resolve(),
        }),
}));
function mountFields(
    couponEnabled = true,
    errors: Record<string, string> = {},
) {
    return mount(StoreWebsiteHomeFormFields, {
        props: {
            modelValue: storeWebsiteHomeSettings(),
            categories: [{ id: 17, name: 'Food' }],
            couponEnabled,
            errors,
        },
    });
}
describe('StoreWebsiteHomeFormFields', () => {
    it('uses the shared image picker and renders every live field and translated action', () => {
        const wrapper = mountFields();
        expect(wrapper.getComponent(StoreUploadField).props()).toMatchObject({
            id: 'slider-image-0',
            name: 'settings[home_slider_images][0]',
            type: 'image',
            multiple: false,
        });
        const labels = [
            'slider',
            'cropHint',
            'image',
            'link',
            'addSlide',
            'coupon',
            'color',
            'couponTitle',
            'subtitle',
            'categories',
            'category',
            'chooseCategory',
            'addCategory',
        ];
        labels.forEach((key) => {
            expect(wrapper.text()).toContain(t(`store.website.home.${key}`));
            expect(i18n.global.te(`store.website.home.${key}`, 'ar')).toBe(
                true,
            );
        });
        expect(
            wrapper
                .get('#home-category-0')
                .findAll('option')
                .map((option) => option.text()),
        ).toEqual([t('store.website.home.chooseCategory'), 'Food']);
        expect(wrapper.find('input[name*="auction"]').exists()).toBe(false);
    });
    it('adds and removes paired slides and category rows in order', async () => {
        const wrapper = mountFields();
        const button = (label: string) =>
            wrapper.findAll('button').find((node) => node.text() === label)!;
        await button(t('store.website.home.addSlide')).trigger('click');
        await wrapper.get('#slider-link-1').setValue('/second');
        const keptPicker =
            wrapper.findAllComponents(StoreUploadField)[1].element;
        wrapper
            .findAllComponents(StoreUploadField)[1]
            .vm.$emit('update:modelValue', '19');
        await wrapper
            .get('button[aria-label="Remove slide 1"]')
            .trigger('click');
        expect(wrapper.getComponent(StoreUploadField).element).toBe(keptPicker);
        expect(wrapper.getComponent(StoreUploadField).props('name')).toBe(
            'settings[home_slider_images][0]',
        );
        expect(wrapper.props('modelValue').home_slider_images).toEqual(['19']);
        expect(wrapper.props('modelValue').home_slider_links).toEqual([
            '/second',
        ]);
        await button(t('store.website.home.addCategory')).trigger('click');
        await wrapper.get('#home-category-1').setValue('17');
        await wrapper
            .get('button[aria-label="Remove category row 1"]')
            .trigger('click');
        expect(wrapper.props('modelValue').home_categories).toEqual(['17']);
    });
    it('edits the coupon fields and hides them when the system is disabled', async () => {
        const wrapper = mountFields();
        await wrapper.get('#cupon_title').setValue('New coupon');
        await wrapper.get('#cupon_background_color').setValue('#abcdef');
        expect(wrapper.props('modelValue').cupon_title).toBe('New coupon');
        expect(wrapper.props('modelValue').cupon_background_color).toBe(
            '#abcdef',
        );
        expect(mountFields(false).find('#cupon_title').exists()).toBe(false);
    });
    it('mirrors native rules and renders nested server errors safely', () => {
        const wrapper = mountFields(true, {
            'settings.home_slider_images.0': 'Missing image',
            'settings.cupon_title': '<script>alert(1)</script>',
            settings: 'Unknown keys',
        });
        expect(wrapper.getComponent(StoreUploadField).props('error')).toBe(
            'Missing image',
        );
        expect(wrapper.text()).toContain('Unknown keys');
        expect(wrapper.text()).toContain('<script>alert(1)</script>');
        expect(wrapper.find('script').exists()).toBe(false);
        expect(wrapper.get('#cupon_title').attributes('maxlength')).toBe('255');
        expect(wrapper.get('#slider-link-0').attributes('maxlength')).toBe(
            '2048',
        );
        expect(
            wrapper.get('#cupon_background_color').attributes('pattern'),
        ).toBe('#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?');
        expect(wrapper.get('#home-category-0').attributes('required')).toBe('');
    });
});
