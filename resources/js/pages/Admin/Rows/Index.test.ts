import { TriangleAlert } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { t } from '@/lib/i18n';
import { rowCells } from '@/testing/dom';
import { paginated, row } from '@/testing/factories';
import type { Row } from '@/types/admin';
import Index from './Index.vue';

const { usePageMock, routerPostMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerPostMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, headStub } = await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { post: routerPostMock },
    };
});

function mountPage(rows: Row[], errors: Partial<Record<'row', string>> = {}) {
    usePageMock.mockReturnValue({
        url: '/admin/rows',
        props: {
            locale: 'en',
            auth: { user: { name: 'Jane Doe', id: 7 } },
            errors,
        },
    });

    return mount(Index, { props: { rows: paginated(rows) } });
}

describe('Rows Index', () => {
    beforeEach(() => {
        usePageMock.mockReset();
        routerPostMock.mockReset();
    });

    it('renders every column header', () => {
        const wrapper = mountPage([]);

        const headers = wrapper.findAll('thead th').map((th) => th.text());
        expect(headers).toEqual([
            t('rows.index.columnLetter'),
            t('rows.index.columnCells'),
            t('rows.index.columnFlats'),
            t('rows.index.columnAction'),
        ]);
    });

    it('shows the empty message when there are no rows', () => {
        const wrapper = mountPage([]);

        expect(wrapper.text()).toContain(t('rows.index.empty'));
    });

    it('links the "Add row" button to the create page', () => {
        const wrapper = mountPage([]);

        const addLink = wrapper
            .findAll('a')
            .find((a) => a.text() === t('rows.index.addRow'));
        expect(addLink?.attributes('href')).toBe('/admin/rows/create');
    });

    it('renders the letter, cell count, and flat count for a row', () => {
        const wrapper = mountPage([
            row({ letter: 'B', cells_count: 3, flats_count: 4 }),
        ]);

        const cells = rowCells(wrapper);
        expect(cells[0].text()).toBe('B');
        expect(cells[1].text()).toBe('3');
        expect(cells[2].text()).toBe('4');
    });

    it("links a row's view action to its show page", () => {
        const wrapper = mountPage([row({ letter: 'C' })]);

        const viewLink = wrapper
            .findAll('a')
            .find((a) => a.text().includes(t('rows.index.view')));
        expect(viewLink?.attributes('href')).toBe('/admin/rows/C');
    });

    it('shows a delete action for a row with no pallets', () => {
        const wrapper = mountPage([row({ letter: 'D', has_pallets: false })]);

        const deleteButton = wrapper
            .findAll('button')
            .find((b) => b.text().includes(t('rows.index.delete')));
        expect(deleteButton?.attributes('href')).toBe('/admin/rows/D');
        expect(wrapper.text()).not.toContain(t('rows.index.hasPallets'));
    });

    it('hides the delete action and shows a "Has pallets" badge for a row that has pallets', () => {
        const wrapper = mountPage([row({ letter: 'E', has_pallets: true })]);

        const deleteButton = wrapper
            .findAll('button')
            .find((b) => b.text().includes(t('rows.index.delete')));
        expect(deleteButton).toBeUndefined();
        expect(wrapper.text()).toContain(t('rows.index.hasPallets'));
        expect(wrapper.findComponent(TriangleAlert).exists()).toBe(true);
    });

    it('shows no delete-error banner when the backend reports no error', () => {
        const wrapper = mountPage([]);

        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });

    it('shows the delete-error banner when the backend rejects deleting a row', () => {
        const wrapper = mountPage([], {
            row: 'Cannot delete a row that has pallets in it.',
        });

        expect(wrapper.get('[role="alert"]').text()).toBe(
            'Cannot delete a row that has pallets in it.',
        );
    });
});
