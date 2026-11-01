import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { t } from '@/lib/i18n';
import AddressMapLink from './AddressMapLink.vue';

describe('AddressMapLink', () => {
    it('links to a Google Maps search with the encoded address', () => {
        const wrapper = mount(AddressMapLink, {
            props: { address: 'A&B 5, Köln' },
        });

        const link = wrapper.get('a');
        expect(link.attributes('href')).toBe(
            'https://www.google.com/maps/search/?api=1&query=A%26B%205%2C%20K%C3%B6ln',
        );
        expect(link.text()).toBe('A&B 5, Köln');
        expect(link.attributes('aria-label')).toBe(
            t('common.openInMaps', { address: 'A&B 5, Köln' }),
        );
    });

    it('opens in a new tab without leaking the opener', () => {
        const link = mount(AddressMapLink, {
            props: { address: 'Main St 1' },
        }).get('a');

        expect(link.attributes('target')).toBe('_blank');
        expect(link.attributes('rel')).toBe('noopener noreferrer');
    });

    it.each([null, undefined, '', '   '])(
        'renders no link for %j',
        (address) => {
            const wrapper = mount(AddressMapLink, { props: { address } });

            expect(wrapper.find('a').exists()).toBe(false);
            expect(wrapper.text()).toBe('');
        },
    );

    it('shows the placeholder when empty', () => {
        const wrapper = mount(AddressMapLink, {
            props: { address: ' ', placeholder: '-' },
        });

        expect(wrapper.find('a').exists()).toBe(false);
        expect(wrapper.text()).toBe('-');
    });
});
