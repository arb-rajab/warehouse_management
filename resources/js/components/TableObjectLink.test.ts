import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import TableLink from './TableLink.vue';
import TableObjectLink from './TableObjectLink.vue';

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub } = await import('@/testing/inertiaStubs');

    return { Link: createLinkStub() };
});

function mountLink(allowed: boolean) {
    return mount(TableObjectLink, {
        props: { href: '/temp/admin/customers/7/edit', allowed },
        slots: { default: 'Acme Foods' },
    });
}

describe('TableObjectLink', () => {
    it('links the name through TableLink when the user may open the page', () => {
        const wrapper = mountLink(true);

        expect(wrapper.findComponent(TableLink).exists()).toBe(true);
        expect(wrapper.get('a').attributes('href')).toBe(
            '/temp/admin/customers/7/edit',
        );
        expect(wrapper.text()).toBe('Acme Foods');
    });

    it('renders plain text, no link, when the user lacks access', () => {
        const wrapper = mountLink(false);

        expect(wrapper.find('a').exists()).toBe(false);
        expect(wrapper.findComponent(TableLink).exists()).toBe(false);
        expect(wrapper.text()).toBe('Acme Foods');
    });
});
