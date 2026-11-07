import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import type { SearchOption } from '@/types/searchSelect';
import StoreSearchOptionRow from './StoreSearchOptionRow.vue';

const product: SearchOption = {
    id: 512,
    label: 'Basmati Rice 5kg',
    description: 'SKU RC-5000 · #512 · 24.00',
    image: 'https://images.example/rice.webp',
    icon: 'package',
    badge: { text: 'In stock 120', tone: 'success' },
};

describe('StoreSearchOptionRow', () => {
    it('shows the image, label, description and badge of a row', () => {
        const wrapper = mount(StoreSearchOptionRow, {
            props: { option: product },
        });

        expect(
            wrapper
                .get('[data-testid="search-option-image"]')
                .attributes('src'),
        ).toBe('https://images.example/rice.webp');
        expect(wrapper.get('[data-testid="search-option-label"]').text()).toBe(
            'Basmati Rice 5kg',
        );
        expect(
            wrapper.get('[data-testid="search-option-description"]').text(),
        ).toBe('SKU RC-5000 · #512 · 24.00');
        const badge = wrapper.get('[data-testid="search-option-badge"]');
        expect(badge.text()).toBe('In stock 120');
        expect(badge.classes()).toContain('bg-green-100');
    });

    it.each([
        ['neutral', 'bg-gray-100'],
        ['success', 'bg-green-100'],
        ['warning', 'bg-amber-100'],
        ['danger', 'bg-red-100'],
        ['info', 'bg-blue-100'],
    ] as const)('tints a %s badge', (tone, expected) => {
        const wrapper = mount(StoreSearchOptionRow, {
            props: { option: { ...product, badge: { text: 'x', tone } } },
        });

        expect(
            wrapper.get('[data-testid="search-option-badge"]').classes(),
        ).toContain(expected);
    });

    it('falls back to the icon tile, then to initials, when there is no image', () => {
        const icon = mount(StoreSearchOptionRow, {
            props: { option: { ...product, image: null } },
        });
        expect(icon.find('[data-testid="search-option-icon"]').exists()).toBe(
            true,
        );

        const initials = mount(StoreSearchOptionRow, {
            props: {
                option: { id: 1, label: 'Ali Hassan Trading', icon: 'user' },
            },
        });
        expect(
            initials.get('[data-testid="search-option-initials"]').text(),
        ).toBe('AH');
        expect(
            initials.find('[data-testid="search-option-image"]').exists(),
        ).toBe(false);
    });

    it('swaps a broken image for its fallback', async () => {
        const wrapper = mount(StoreSearchOptionRow, {
            props: { option: product },
        });

        await wrapper
            .get('[data-testid="search-option-image"]')
            .trigger('error');

        expect(
            wrapper.find('[data-testid="search-option-image"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-testid="search-option-icon"]').exists(),
        ).toBe(true);
    });

    it('highlights the query words in the label and the description', () => {
        const wrapper = mount(StoreSearchOptionRow, {
            props: { option: product, query: 'rice rc-5' },
        });

        expect(
            wrapper
                .get('[data-testid="search-option-label"]')
                .findAll('mark')
                .map((mark) => mark.text()),
        ).toEqual(['Rice']);
        expect(
            wrapper
                .get('[data-testid="search-option-description"]')
                .findAll('mark')
                .map((mark) => mark.text()),
        ).toEqual(['RC-5']);
    });

    it('renders the label as text, never as markup', () => {
        const wrapper = mount(StoreSearchOptionRow, {
            props: { option: { id: 1, label: '<img src=x onerror=alert(1)>' } },
        });

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.get('[data-testid="search-option-label"]').text()).toBe(
            '<img src=x onerror=alert(1)>',
        );
    });

    it('shrinks to the closed-field and chip variants without a badge', () => {
        const selected = mount(StoreSearchOptionRow, {
            props: { option: product, variant: 'selected' },
        });
        expect(
            selected.find('[data-testid="search-option-badge"]').exists(),
        ).toBe(false);
        expect(
            selected.find('[data-testid="search-option-description"]').exists(),
        ).toBe(true);

        const chip = mount(StoreSearchOptionRow, {
            props: { option: product, variant: 'chip' },
        });
        expect(
            chip.find('[data-testid="search-option-description"]').exists(),
        ).toBe(false);
        expect(chip.get('[data-testid="search-option-label"]').text()).toBe(
            'Basmati Rice 5kg',
        );
    });

    it('lets the image and badge slots replace their parts', () => {
        const wrapper = mount(StoreSearchOptionRow, {
            props: { option: product },
            slots: {
                image: '<template #image="{ option }"><i data-testid="custom-image">{{ option.id }}</i></template>',
                badge: '<template #badge="{ option }"><b data-testid="custom-badge">{{ option.label }}</b></template>',
            },
        });

        expect(wrapper.get('[data-testid="custom-image"]').text()).toBe('512');
        expect(wrapper.get('[data-testid="custom-badge"]').text()).toBe(
            'Basmati Rice 5kg',
        );
        expect(
            wrapper.find('[data-testid="search-option-image"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-testid="search-option-badge"]').exists(),
        ).toBe(false);
    });
});
