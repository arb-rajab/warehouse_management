import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import FormField from '@/components/FormField.vue';
import { t } from '@/lib/i18n';
import Fields from './StoreBlogCategoryFormFields.vue';

describe('StoreBlogCategoryFormFields', () => {
    it('renders the translated name label and mirrors authoritative string constraints', () => {
        const wrapper = mount(Fields, { props: { errors: {} } });
        expect(wrapper.get('label').text()).toBe(
            t('store.blogCategories.fields.name'),
        );
        expect(wrapper.get('input').attributes()).toMatchObject({
            id: 'category_name',
            name: 'category_name',
            required: '',
            maxlength: '255',
        });
        expect((wrapper.get('input').element as HTMLInputElement).value).toBe(
            '',
        );
    });

    it('forwards stored database content and server errors without interpreting HTML', () => {
        const wrapper = mount(Fields, {
            props: {
                categoryName: 'وصفات <script>alert(1)</script>',
                errors: { category_name: 'Name is required.' },
            },
        });
        expect(wrapper.getComponent(FormField).props()).toMatchObject({
            value: 'وصفات <script>alert(1)</script>',
            error: 'Name is required.',
        });
        expect(wrapper.text()).toContain('Name is required.');
        expect(wrapper.find('script').exists()).toBe(false);
    });
});
