import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { t } from '@/lib/i18n';
import ProductActiveBadge from './ProductActiveBadge.vue';

describe('ProductActiveBadge', () => {
    it('labels an active product as active', () => {
        const wrapper = mount(ProductActiveBadge, { props: { active: true } });

        expect(wrapper.text()).toBe(t('products.status.active'));
    });

    it('labels an inactive product as inactive', () => {
        const wrapper = mount(ProductActiveBadge, {
            props: { active: false },
        });

        expect(wrapper.text()).toBe(t('products.status.inactive'));
    });
});
