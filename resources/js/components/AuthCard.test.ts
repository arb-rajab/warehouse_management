import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import AuthCard from './AuthCard.vue';

const { usePageMock } = vi.hoisted(() => ({ usePageMock: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    usePage: usePageMock,
    router: { post: vi.fn() },
}));

function mountCard(props: Record<string, unknown> = {}) {
    usePageMock.mockReturnValue({
        props: { locale: 'en', brandName: 'Placeholder Brand', ...props },
    });

    return mount(AuthCard, {
        props: { area: 'Warehouse Admin' },
        slots: { default: '<form id="inner"></form>' },
    });
}

describe('AuthCard', () => {
    beforeEach(() => usePageMock.mockReset());

    it('shows the logo, the shared brand name and the area title', () => {
        const wrapper = mountCard();

        expect(wrapper.get('[data-testid="auth-logo"]').attributes('src')).toBe(
            '/assets/img/33logoqr.png',
        );
        expect(wrapper.get('h1').text()).toBe('Placeholder Brand');
        expect(wrapper.get('[data-testid="auth-area"]').text()).toBe(
            'Warehouse Admin',
        );
    });

    it('renders the language switcher and puts the slot in a surface card', () => {
        const wrapper = mountCard();

        expect(wrapper.findComponent(LanguageSwitcher).exists()).toBe(true);

        const card = wrapper.get('#inner').element.parentElement;
        expect(card?.className).toContain('bg-surface');
        expect(card?.className).toContain('border-line');
    });

    it('uses the Direction A page background and no raw palette classes', () => {
        const wrapper = mountCard();

        expect(wrapper.classes()).toContain('bg-page');
        expect(wrapper.html()).not.toMatch(/(gray|neutral|blue|red)-\d{2,3}/);
    });

    it('falls back to an empty brand name when the prop is missing', () => {
        const wrapper = mountCard({ brandName: undefined });

        expect(wrapper.get('h1').text()).toBe('');
    });
});
