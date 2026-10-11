import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import FieldError from '@/components/FieldError.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import StoreWebsiteHeaderFormFields from '@/components/StoreWebsiteHeaderFormFields.vue';
import { i18n, t } from '@/lib/i18n';
import { storeWebsiteHeaderSettings } from '@/testing/factories';

vi.mock('@inertiajs/vue3', () => ({
    useHttp: () =>
        reactive({
            processing: false,
            get: () => Promise.resolve(),
            post: () => Promise.resolve(),
        }),
}));

function mountFields(errors: Record<string, string> = {}) {
    return mount(StoreWebsiteHeaderFormFields, {
        props: { modelValue: storeWebsiteHeaderSettings(), errors },
    });
}

describe('StoreWebsiteHeaderFormFields', () => {
    beforeEach(() => vi.clearAllMocks());

    it('uses the existing image-only upload picker for each exact setting name', () => {
        const fields = mountFields().findAllComponents(StoreUploadField);
        const keys = [
            'header_logo',
            'topbar_banner',
            'topbar_banner_medium',
            'topbar_banner_small',
        ];
        expect(fields).toHaveLength(4);
        fields.forEach((field, index) =>
            expect(field.props()).toMatchObject({
                id: keys[index],
                name: `settings[${keys[index]}]`,
                type: 'image',
                multiple: false,
            }),
        );
    });

    it('renders every field and action label in both supported locales', () => {
        const wrapper = mountFields();
        const keys = [
            'logo',
            'bannerLarge',
            'bannerMedium',
            'bannerSmall',
            'languageSwitcher',
            'currencySwitcher',
            'sticky',
            'bannerLink',
            'helpline',
            'menu',
            'menuLabel',
            'menuLink',
            'remove',
            'add',
        ];
        keys.forEach((key) => {
            expect(wrapper.text()).toContain(t(`store.website.header.${key}`));
            expect(i18n.global.te(`store.website.header.${key}`, 'ar')).toBe(
                true,
            );
            expect(i18n.global.te(`store.website.header.${key}`, 'en')).toBe(
                true,
            );
        });
        expect(
            wrapper.get('button[aria-label="Remove menu row 1"]').text(),
        ).toBe(t('store.website.header.remove'));
    });

    it('edits text, switches and image IDs in the submission model', async () => {
        const wrapper = mountFields();
        await wrapper.get('#helpline_number').setValue('555');
        await wrapper.get('#topbar_banner_link').setValue('/new');
        await wrapper.get('#header_stikcy').setValue(false);
        wrapper
            .getComponent(StoreUploadField)
            .vm.$emit('update:modelValue', '19');
        expect(wrapper.props('modelValue')).toMatchObject({
            helpline_number: '555',
            topbar_banner_link: '/new',
            header_stikcy: false,
            header_logo: '19',
        });
    });

    it('keeps labels and links paired when adding, editing and removing rows', async () => {
        const wrapper = mountFields();
        await wrapper
            .findAll('button')
            .find((button) => button.text() === t('store.website.header.add'))!
            .trigger('click');
        await wrapper.get('#menu-label-1').setValue('New');
        await wrapper.get('#menu-link-1').setValue('/new');
        expect(wrapper.props('modelValue').header_menu_labels).toEqual([
            'Offers',
            'New',
        ]);
        expect(wrapper.props('modelValue').header_menu_links).toEqual([
            '/offers',
            '/new',
        ]);
        await wrapper
            .get('button[aria-label="Remove menu row 1"]')
            .trigger('click');
        expect(wrapper.props('modelValue').header_menu_labels).toEqual(['New']);
        expect(wrapper.props('modelValue').header_menu_links).toEqual(['/new']);
    });

    it('mirrors native constraints and displays nested and allowlist errors without HTML', () => {
        const wrapper = mountFields({
            settings: 'Unknown setting',
            'settings.header_logo': 'Not an image',
            'settings.header_menu_links.0': '<img src=x onerror=alert(1)>',
        });
        expect(wrapper.get('#helpline_number').attributes('maxlength')).toBe(
            '100',
        );
        expect(wrapper.get('#topbar_banner_link').attributes('maxlength')).toBe(
            '2048',
        );
        expect(wrapper.get('#menu-label-0').attributes()).toMatchObject({
            required: '',
            maxlength: '256',
        });
        expect(wrapper.get('#menu-link-0').attributes()).toMatchObject({
            required: '',
            maxlength: '2048',
        });
        expect(
            wrapper
                .findAllComponents(FieldError)
                .map((field) => field.props('message')),
        ).toContain('Unknown setting');
        expect(wrapper.getComponent(StoreUploadField).props('error')).toBe(
            'Not an image',
        );
        expect(wrapper.text()).toContain('<img src=x onerror=alert(1)>');
        expect(wrapper.find('img').exists()).toBe(false);
    });
});
