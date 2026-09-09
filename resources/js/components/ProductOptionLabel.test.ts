import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import { i18n } from '@/lib/i18n';
import type { ProductFilterOption } from '@/types/admin';
import ProductOptionLabel from './ProductOptionLabel.vue';

function mountLabel(product: ProductFilterOption) {
    return mount(ProductOptionLabel, { props: { product } });
}

function primary(wrapper: ReturnType<typeof mountLabel>) {
    return wrapper.get('[data-testid="product-option-name"]');
}

function alternate(wrapper: ReturnType<typeof mountLabel>) {
    return wrapper.find('[data-testid="product-option-alternate-name"]');
}

describe('ProductOptionLabel', () => {
    afterEach(() => {
        i18n.global.locale.value = 'en';
    });

    it("leads with the base name and shows the store's Arabic name beneath it in English", () => {
        const wrapper = mountLabel({
            id: 1,
            name: 'Widgets',
            ar_name: 'ودجات',
        });

        expect(primary(wrapper).text()).toBe('Widgets');
        expect(alternate(wrapper).text()).toBe('ودجات');
    });

    it('leads with the Arabic name and shows the base name beneath it in Arabic', () => {
        i18n.global.locale.value = 'ar';

        const wrapper = mountLabel({
            id: 1,
            name: 'Widgets',
            ar_name: 'ودجات',
        });

        expect(primary(wrapper).text()).toBe('ودجات');
        expect(alternate(wrapper).text()).toBe('Widgets');
    });

    it('shows no second line for a product the store never translated, in either locale', () => {
        const english = mountLabel({ id: 1, name: 'Widgets', ar_name: '' });

        expect(primary(english).text()).toBe('Widgets');
        expect(alternate(english).exists()).toBe(false);

        i18n.global.locale.value = 'ar';
        const arabic = mountLabel({ id: 1, name: 'Widgets', ar_name: '' });

        expect(primary(arabic).text()).toBe('Widgets');
        expect(alternate(arabic).exists()).toBe(false);
    });

    it('shows no second line when both store columns hold the same string', () => {
        const wrapper = mountLabel({
            id: 1,
            name: 'Widgets',
            ar_name: 'Widgets',
        });

        expect(primary(wrapper).text()).toBe('Widgets');
        expect(alternate(wrapper).exists()).toBe(false);
    });

    it("isolates each line's direction, since the document sets only one", () => {
        // `<html dir>` follows the panel locale, so a Latin name inside the
        // Arabic panel needs its own isolation to render in reading order.
        i18n.global.locale.value = 'ar';

        const wrapper = mountLabel({
            id: 1,
            name: 'Widgets',
            ar_name: 'ودجات',
        });

        expect(primary(wrapper).attributes('dir')).toBe('auto');
        expect(alternate(wrapper).attributes('dir')).toBe('auto');
    });

    it('re-resolves both lines when the locale changes under it', async () => {
        const wrapper = mountLabel({
            id: 1,
            name: 'Widgets',
            ar_name: 'ودجات',
        });

        i18n.global.locale.value = 'ar';
        await wrapper.vm.$nextTick();

        expect(primary(wrapper).text()).toBe('ودجات');
        expect(alternate(wrapper).text()).toBe('Widgets');
    });
});
