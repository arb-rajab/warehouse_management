import { Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { edit } from '@/actions/App/Http/Controllers/Store/Admin/CityController';
import { storeCity } from '@/testing/factories';
import StoreLanguageTabs from './StoreLanguageTabs.vue';
describe('StoreLanguageTabs', () => {
    it('links each translation and submits the selected language', () => {
        const wrapper = mount(StoreLanguageTabs, {
            props: {
                lang: 'ar',
                languages: [
                    { code: 'en', name: 'English' },
                    { code: 'ar', name: 'Arabic' },
                ],
                href: (code) => edit(storeCity(), { query: { lang: code } }),
            },
        });
        expect(wrapper.get('input[name=lang]').attributes('value')).toBe('ar');
        expect(
            wrapper.findAllComponents(Link).map((link) => link.props('href')),
        ).toEqual([
            edit(storeCity(), { query: { lang: 'en' } }),
            edit(storeCity(), { query: { lang: 'ar' } }),
        ]);
        expect(wrapper.text()).toContain('English');
        expect(wrapper.text()).toContain('Arabic');
    });
});
