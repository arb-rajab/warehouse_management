import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';
import type {
    CellHighlightSample,
    CellHighlightSeed,
    CellMap3DBand,
    ProductFilterOptions,
    CellMapRow,
    CellSlotLocation,
    CellWithLocation,
} from '@/types/admin';
import Index from './Index.vue';

const {
    usePageMock,
    routerGetMock,
    cellMap3DFocusCell,
    cellMap3DResetView,
    cellMap3DSetCameraMode,
} = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
    cellMap3DFocusCell: vi.fn(),
    cellMap3DResetView: vi.fn(),
    cellMap3DSetCameraMode: vi.fn(),
}));

vi.mock('@/components/CellMap3D.vue', async () => {
    const { defineComponent, h } = await import('vue');

    return {
        default: defineComponent({
            name: 'CellMap3DStub',
            props: ['bands'],
            emits: ['camera-mode-change'],
            setup(_props, { expose, emit }) {
                expose({
                    focusCell: cellMap3DFocusCell,
                    resetView: cellMap3DResetView,
                    setCameraMode: (mode: 'walk' | 'orbit') => {
                        cellMap3DSetCameraMode(mode);
                        emit('camera-mode-change', mode);
                    },
                });

                return () => h('div', { 'data-testid': 'map-3d-stub' });
            },
        }),
    };
});

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');

    const LinkStub = defineComponent({
        props: ['href', 'as'],
        setup(props, { slots }) {
            return () =>
                h(
                    props.as ?? 'a',
                    {
                        href:
                            typeof props.href === 'string'
                                ? props.href
                                : props.href?.url,
                    },
                    slots.default?.(),
                );
        },
    });

    return {
        Head: defineComponent({ render: () => null }),
        Link: LinkStub,
        usePage: usePageMock,
        router: { get: routerGetMock },
        useHttp: () => ({
            get: (
                _url: string,
                options?: { onSuccess?: (response: unknown) => void },
            ) =>
                options?.onSuccess?.({
                    data: products,
                    meta: {
                        current_page: 1,
                        last_page: 1,
                        per_page: 20,
                        total: products.length,
                        from: 1,
                        to: products.length,
                        links: [],
                    },
                }),
        }),
    };
});

const products = [
    { id: 1, name: 'Widgets' },
    { id: 2, name: 'Gadgets' },
];

function row(overrides: Partial<CellMapRow> = {}): CellMapRow {
    return { id: 1, letter: 'A', cells_count: 2, flats_count: 2, ...overrides };
}

function cell(overrides: Partial<CellWithLocation> = {}): CellWithLocation {
    return {
        id: 1,
        row_letter: 'A',
        cell_number: 1,
        flat_number: 1,
        state: 'empty',
        pallet: null,
        ...overrides,
    };
}

function pallet(
    overrides: Partial<CellWithLocation['pallet']> = {},
): NonNullable<CellWithLocation['pallet']> {
    return {
        id: 1,
        product_id: 1,
        product_name: 'Widgets',
        product_image_url: null,
        expiration_date: '2026-09-01',
        added_at: '2026-07-01T10:00:00Z',
        is_stale: null,
        ...overrides,
    };
}

function highlightSample(
    overrides: Partial<CellHighlightSample> = {},
): CellHighlightSample {
    return {
        row_letter: 'A',
        cell_number: 1,
        flat_number: 1,
        state: 'empty',
        pallet: null,
        ...overrides,
    };
}

function mountPage(
    rows: CellMapRow[],
    cells: CellWithLocation[],
    overrides: {
        flatNumber?: number;
        maxFlatNumber?: number;
        today?: string;
        initialHighlight?: CellHighlightSeed;
        jumpToCell?: CellSlotLocation | null;
        searchError?: boolean;
        filterOptions?: ProductFilterOptions;
        cellHighlightSamples?: CellHighlightSample[];
    } = {},
) {
    usePageMock.mockReturnValue({
        url: '/admin/cells',
        props: { locale: 'en', auth: { user: { name: 'Jane Doe', id: 7 } } },
    });

    return mount(Index, {
        props: {
            rows,
            cells,
            flatNumber: overrides.flatNumber ?? 1,
            maxFlatNumber: overrides.maxFlatNumber ?? 2,
            today: overrides.today ?? '2026-08-13',
            initialHighlight: overrides.initialHighlight ?? {
                state: null,
                productIds: [],
                expiresWithinDays: null,
                expired: false,
            },
            jumpToCell: overrides.jumpToCell ?? null,
            searchError: overrides.searchError ?? false,
            filterOptions: overrides.filterOptions ?? { products },
            cellHighlightSamples: overrides.cellHighlightSamples ?? [],
        },
    });
}

async function openHighlightFilters(
    wrapper: ReturnType<typeof mountPage>,
): Promise<void> {
    const trigger = wrapper
        .findAll('button')
        .find((button) =>
            button.text().includes(t('rows.show.highlight.button')),
        );
    await trigger?.trigger('click');
}

function slot(wrapper: ReturnType<typeof mountPage>, label: string) {
    return wrapper
        .findAll('[data-testid="cell-slot"]')
        .find((el) => el.text().includes(label));
}

describe('Cells Index (warehouse map)', () => {
    beforeEach(() => {
        routerGetMock.mockClear();
        cellMap3DFocusCell.mockClear();
        cellMap3DResetView.mockClear();
    });

    it('shows the empty message when there are no rows', () => {
        const wrapper = mountPage([], []);

        expect(wrapper.text()).toContain(t('cells.empty'));
    });

    it('renders every row with a slot per its cell count', () => {
        const wrapper = mountPage(
            [
                row({ letter: 'A', cells_count: 2 }),
                row({ id: 2, letter: 'B', cells_count: 1 }),
            ],
            [],
        );

        const rowGroups = wrapper.findAll('[data-testid="map-row"]');
        expect(rowGroups).toHaveLength(2);
        expect(rowGroups[0].text()).toContain('A');
        expect(rowGroups[0].findAll('[data-testid="cell-slot"]')).toHaveLength(
            2,
        );
        expect(rowGroups[1].text()).toContain('B');
        expect(rowGroups[1].findAll('[data-testid="cell-slot"]')).toHaveLength(
            1,
        );
    });

    it('places each cell in its own row and cell-number slot, leaving unmatched slots empty', () => {
        const wrapper = mountPage(
            [row({ letter: 'A', cells_count: 2 })],
            [
                cell({
                    row_letter: 'A',
                    cell_number: 1,
                    flat_number: 1,
                    state: 'full',
                    pallet: pallet(),
                }),
            ],
        );

        const slotWithPallet = slot(wrapper, formatSlot('A', 1, 1));
        expect(slotWithPallet?.text()).toContain('Widgets');

        const emptySlot = slot(wrapper, formatSlot('A', 2, 1));
        expect(emptySlot?.text()).toContain(t('rows.show.empty'));
    });

    it('shows a not-available placeholder instead of empty cells for a row without this flat', () => {
        const wrapper = mountPage(
            [
                row({ letter: 'A', cells_count: 2, flats_count: 1 }),
                row({ id: 2, letter: 'B', cells_count: 2, flats_count: 3 }),
            ],
            [],
            { flatNumber: 2, maxFlatNumber: 3 },
        );

        const rowGroups = wrapper.findAll('[data-testid="map-row"]');
        expect(
            rowGroups[0].findAll('[data-testid="flat-not-available"]'),
        ).toHaveLength(2);
        expect(rowGroups[0].text()).toContain(t('cells.flatNotAvailable'));
        expect(rowGroups[0].findAll('[data-testid="cell-slot"]')).toHaveLength(
            0,
        );

        expect(
            rowGroups[1].findAll('[data-testid="flat-not-available"]'),
        ).toHaveLength(0);
        expect(rowGroups[1].findAll('[data-testid="cell-slot"]')).toHaveLength(
            2,
        );
    });

    it('renders a tab for every flat number and marks the current one active', () => {
        const wrapper = mountPage([row()], [], {
            flatNumber: 2,
            maxFlatNumber: 3,
        });

        const tabs = wrapper.findAll('[data-testid="flat-tab"]');
        expect(tabs.map((tab) => tab.text())).toEqual([
            t('rows.show.flat', { n: 1 }),
            t('rows.show.flat', { n: 2 }),
            t('rows.show.flat', { n: 3 }),
        ]);
        expect(tabs[1].attributes('aria-pressed')).toBe('true');
        expect(tabs[0].attributes('aria-pressed')).toBe('false');
    });

    it('shows no per-flat match count badge when no highlight filter is active', () => {
        const wrapper = mountPage([row()], [], {
            maxFlatNumber: 2,
            cellHighlightSamples: [
                highlightSample({ flat_number: 1, state: 'full' }),
            ],
        });

        expect(
            wrapper.findAll('[data-testid="flat-match-count"]'),
        ).toHaveLength(0);
    });

    it('shows a per-flat match count badge for the active highlight, covering flats other than the one on screen', () => {
        const seed: CellHighlightSeed = {
            state: 'full',
            productIds: [],
            expiresWithinDays: null,
            expired: false,
        };
        const wrapper = mountPage([row()], [], {
            flatNumber: 1,
            maxFlatNumber: 3,
            initialHighlight: seed,
            cellHighlightSamples: [
                highlightSample({ flat_number: 1, state: 'full' }),
                highlightSample({ flat_number: 1, state: 'empty' }),
                highlightSample({ flat_number: 2, state: 'full' }),
                highlightSample({ flat_number: 2, state: 'full' }),
                highlightSample({ flat_number: 3, state: 'opened' }),
            ],
        });

        const badges = wrapper.findAll('[data-testid="flat-match-count"]');
        expect(badges.map((badge) => badge.text())).toEqual(['1', '2']);
    });

    it('hides the per-flat match count badge for a flat with zero matches', () => {
        const seed: CellHighlightSeed = {
            state: 'full',
            productIds: [],
            expiresWithinDays: null,
            expired: false,
        };
        const wrapper = mountPage([row()], [], {
            flatNumber: 1,
            maxFlatNumber: 2,
            initialHighlight: seed,
            cellHighlightSamples: [
                highlightSample({ flat_number: 1, state: 'full' }),
                highlightSample({ flat_number: 2, state: 'opened' }),
            ],
        });

        const tabs = wrapper.findAll('[data-testid="flat-tab"]');
        expect(
            tabs[0].findAll('[data-testid="flat-match-count"]'),
        ).toHaveLength(1);
        expect(
            tabs[1].findAll('[data-testid="flat-match-count"]'),
        ).toHaveLength(0);
    });

    it('shows no total match count when no highlight filter is active', () => {
        const wrapper = mountPage([row()], [], {
            maxFlatNumber: 2,
            cellHighlightSamples: [
                highlightSample({ flat_number: 1, state: 'full' }),
            ],
        });

        expect(wrapper.find('[data-testid="total-match-count"]').exists()).toBe(
            false,
        );
    });

    it('shows the total match count across every flat for the active highlight', () => {
        const seed: CellHighlightSeed = {
            state: 'full',
            productIds: [],
            expiresWithinDays: null,
            expired: false,
        };
        const wrapper = mountPage([row()], [], {
            flatNumber: 1,
            maxFlatNumber: 3,
            initialHighlight: seed,
            cellHighlightSamples: [
                highlightSample({ flat_number: 1, state: 'full' }),
                highlightSample({ flat_number: 1, state: 'empty' }),
                highlightSample({ flat_number: 2, state: 'full' }),
                highlightSample({ flat_number: 2, state: 'full' }),
                highlightSample({ flat_number: 3, state: 'opened' }),
            ],
        });

        expect(wrapper.get('[data-testid="total-match-count"]').text()).toBe(
            t('cells.filters.matchCount', { count: 3 }),
        );
    });

    it('hides next/previous match controls when no highlight filter is active', () => {
        const wrapper = mountPage([row()], [], {
            cellHighlightSamples: [highlightSample({ state: 'full' })],
        });

        expect(wrapper.find('[data-testid="next-match"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="previous-match"]').exists()).toBe(
            false,
        );
    });

    it('hides next/previous match controls when the active highlight matches nothing', () => {
        const seed: CellHighlightSeed = {
            state: 'opened',
            productIds: [],
            expiresWithinDays: null,
            expired: false,
        };
        const wrapper = mountPage([row()], [], {
            initialHighlight: seed,
            cellHighlightSamples: [highlightSample({ state: 'full' })],
        });

        expect(wrapper.find('[data-testid="next-match"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="previous-match"]').exists()).toBe(
            false,
        );
    });

    it('cycles next through matches on the current flat in row/cell-number order, wrapping around', async () => {
        const seed: CellHighlightSeed = {
            state: 'full',
            productIds: [],
            expiresWithinDays: null,
            expired: false,
        };
        const wrapper = mountPage([row({ letter: 'A', cells_count: 2 })], [], {
            flatNumber: 1,
            initialHighlight: seed,
            cellHighlightSamples: [
                highlightSample({
                    row_letter: 'A',
                    cell_number: 2,
                    state: 'full',
                }),
                highlightSample({
                    row_letter: 'A',
                    cell_number: 1,
                    state: 'full',
                }),
            ],
        });

        const next = () =>
            wrapper
                .get(`[title="${t('cells.filters.nextMatch')}"]`)
                .trigger('click');

        await next();
        await flushPromises();
        expect(slot(wrapper, formatSlot('A', 1, 1))?.classes()).toContain(
            'ring-emerald-500',
        );
        expect(wrapper.get('[data-testid="match-position"]').text()).toBe(
            t('cells.filters.matchPosition', { current: 1, total: 2 }),
        );

        await next();
        await flushPromises();
        expect(slot(wrapper, formatSlot('A', 2, 1))?.classes()).toContain(
            'ring-emerald-500',
        );
        expect(wrapper.get('[data-testid="match-position"]').text()).toBe(
            t('cells.filters.matchPosition', { current: 2, total: 2 }),
        );

        await next();
        await flushPromises();
        expect(slot(wrapper, formatSlot('A', 1, 1))?.classes()).toContain(
            'ring-emerald-500',
        );
        expect(wrapper.get('[data-testid="match-position"]').text()).toBe(
            t('cells.filters.matchPosition', { current: 1, total: 2 }),
        );
    });

    it('jumps to the last match when clicking previous with no current focus', async () => {
        const seed: CellHighlightSeed = {
            state: 'full',
            productIds: [],
            expiresWithinDays: null,
            expired: false,
        };
        const wrapper = mountPage([row({ letter: 'A', cells_count: 2 })], [], {
            flatNumber: 1,
            initialHighlight: seed,
            cellHighlightSamples: [
                highlightSample({
                    row_letter: 'A',
                    cell_number: 1,
                    state: 'full',
                }),
                highlightSample({
                    row_letter: 'A',
                    cell_number: 2,
                    state: 'full',
                }),
            ],
        });

        await wrapper
            .get(`[title="${t('cells.filters.previousMatch')}"]`)
            .trigger('click');
        await flushPromises();

        expect(slot(wrapper, formatSlot('A', 2, 1))?.classes()).toContain(
            'ring-emerald-500',
        );
        expect(wrapper.get('[data-testid="match-position"]').text()).toBe(
            t('cells.filters.matchPosition', { current: 2, total: 2 }),
        );
    });

    it('reloads onto the matching flat when the next match is on a different flat, keeping the highlight filters in the query', async () => {
        const seed: CellHighlightSeed = {
            state: 'full',
            productIds: [],
            expiresWithinDays: null,
            expired: false,
        };
        const wrapper = mountPage([row({ letter: 'A', cells_count: 1 })], [], {
            flatNumber: 1,
            maxFlatNumber: 2,
            initialHighlight: seed,
            cellHighlightSamples: [
                highlightSample({
                    row_letter: 'A',
                    cell_number: 1,
                    flat_number: 1,
                    state: 'full',
                }),
                highlightSample({
                    row_letter: 'A',
                    cell_number: 1,
                    flat_number: 2,
                    state: 'full',
                }),
            ],
        });

        const next = () =>
            wrapper
                .get(`[title="${t('cells.filters.nextMatch')}"]`)
                .trigger('click');

        await next();
        await next();

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cells',
            { flat_number: 2, search: 'A1·2', state: 'full' },
            { preserveState: true, replace: true },
        );
    });

    it('navigates to the clicked flat', async () => {
        const wrapper = mountPage([row()], [], {
            flatNumber: 1,
            maxFlatNumber: 2,
        });

        await wrapper.findAll('[data-testid="flat-tab"]')[1].trigger('click');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cells',
            { flat_number: 2 },
            { preserveState: true, replace: true },
        );
    });

    it('pre-highlights cells matching the initial highlight seed from the dashboard link', () => {
        const seed: CellHighlightSeed = {
            state: 'full',
            productIds: [],
            expiresWithinDays: null,
            expired: false,
        };
        const wrapper = mountPage(
            [row({ letter: 'A', cells_count: 2 })],
            [
                cell({
                    row_letter: 'A',
                    cell_number: 1,
                    flat_number: 1,
                    state: 'full',
                    pallet: pallet(),
                }),
                cell({
                    row_letter: 'A',
                    cell_number: 2,
                    flat_number: 1,
                    state: 'empty',
                }),
            ],
            { initialHighlight: seed },
        );

        expect(slot(wrapper, formatSlot('A', 1, 1))?.classes()).toContain(
            'ring-blue-500',
        );
        expect(slot(wrapper, formatSlot('A', 2, 1))?.classes()).not.toContain(
            'ring-blue-500',
        );
    });

    it('pre-highlights cells by an expires-within-days seed from the dashboard link', () => {
        const seed: CellHighlightSeed = {
            state: null,
            productIds: [],
            expiresWithinDays: 7,
            expired: false,
        };
        const wrapper = mountPage(
            [row({ letter: 'A', cells_count: 2 })],
            [
                cell({
                    row_letter: 'A',
                    cell_number: 1,
                    flat_number: 1,
                    state: 'full',
                    pallet: pallet({ expiration_date: '2026-08-15' }),
                }),
                cell({
                    row_letter: 'A',
                    cell_number: 2,
                    flat_number: 1,
                    state: 'full',
                    pallet: pallet({ expiration_date: '2026-12-01' }),
                }),
            ],
            { today: '2026-08-13', initialHighlight: seed },
        );

        expect(slot(wrapper, formatSlot('A', 1, 1))?.classes()).toContain(
            'ring-blue-500',
        );
        expect(slot(wrapper, formatSlot('A', 2, 1))?.classes()).not.toContain(
            'ring-blue-500',
        );
    });

    it('pre-highlights only already-expired cells from an expired seed, not future ones', () => {
        const seed: CellHighlightSeed = {
            state: null,
            productIds: [],
            expiresWithinDays: null,
            expired: true,
        };
        const wrapper = mountPage(
            [row({ letter: 'A', cells_count: 2 })],
            [
                cell({
                    row_letter: 'A',
                    cell_number: 1,
                    flat_number: 1,
                    state: 'full',
                    pallet: pallet({ expiration_date: '2026-08-01' }),
                }),
                cell({
                    row_letter: 'A',
                    cell_number: 2,
                    flat_number: 1,
                    state: 'full',
                    pallet: pallet({ expiration_date: '2026-08-20' }),
                }),
            ],
            { today: '2026-08-13', initialHighlight: seed },
        );

        expect(slot(wrapper, formatSlot('A', 1, 1))?.classes()).toContain(
            'ring-blue-500',
        );
        expect(slot(wrapper, formatSlot('A', 2, 1))?.classes()).not.toContain(
            'ring-blue-500',
        );
    });

    it('keeps the active highlight filters in the query when switching flats', async () => {
        const wrapper = mountPage([row({ letter: 'A', cells_count: 2 })], [], {
            initialHighlight: {
                state: null,
                productIds: [1, 2],
                expiresWithinDays: 7,
                expired: false,
            },
        });

        await wrapper.findAll('[data-testid="flat-tab"]')[1].trigger('click');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cells',
            {
                flat_number: 2,
                product_id: ['1', '2'],
                expires_within_days: 7,
            },
            { preserveState: true, replace: true },
        );
    });

    it('keeps the active highlight filters in the query when submitting a search', async () => {
        const wrapper = mountPage([row()], [], {
            initialHighlight: {
                state: 'full',
                productIds: [],
                expiresWithinDays: null,
                expired: true,
            },
        });

        await wrapper.get('input[type="text"]').setValue('A1');
        await wrapper.get('form').trigger('submit');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cells',
            {
                flat_number: 1,
                search: 'A1',
                state: 'full',
                expired: true,
            },
            { preserveState: true, replace: true },
        );
    });

    it('opens the highlight filter dialog with every expected field', async () => {
        const wrapper = mountPage([], []);

        await openHighlightFilters(wrapper);

        expect(wrapper.find('#highlight-state').exists()).toBe(true);
        expect(wrapper.find('#highlight-expires-within-days').exists()).toBe(
            true,
        );
        expect(wrapper.find('#highlight-product').exists()).toBe(true);
        expect(wrapper.find('#highlight-stale-after-days').exists()).toBe(true);
    });

    it('highlights only cells matching the selected product', async () => {
        const wrapper = mountPage(
            [row({ letter: 'A', cells_count: 2 })],
            [
                cell({
                    row_letter: 'A',
                    cell_number: 1,
                    flat_number: 1,
                    state: 'full',
                    pallet: pallet({ product_id: 1 }),
                }),
                cell({
                    row_letter: 'A',
                    cell_number: 2,
                    flat_number: 1,
                    state: 'full',
                    pallet: pallet({
                        id: 2,
                        product_id: 2,
                        product_name: 'Gadgets',
                    }),
                }),
            ],
        );

        await openHighlightFilters(wrapper);
        await wrapper.get('#highlight-product').trigger('click');
        await wrapper
            .findAll('[role="listbox"] input[type="checkbox"]')[0]
            .setValue(true);

        expect(slot(wrapper, formatSlot('A', 1, 1))?.classes()).toContain(
            'ring-blue-500',
        );
        expect(slot(wrapper, formatSlot('A', 2, 1))?.classes()).not.toContain(
            'ring-blue-500',
        );
    });

    it('submits a search and reloads with the query, preserving the current flat', async () => {
        const wrapper = mountPage([row()], [], { flatNumber: 2 });

        await wrapper.get('input[type="text"]').setValue('A12·3');
        await wrapper.get('form').trigger('submit');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/cells',
            { flat_number: 2, search: 'A12·3' },
            { preserveState: true, replace: true },
        );
    });

    it('does not reload when the search box is submitted empty', async () => {
        const wrapper = mountPage([row()], []);

        await wrapper.get('form').trigger('submit');

        expect(routerGetMock).not.toHaveBeenCalled();
    });

    it('shows a not-found message when the search found nothing', () => {
        const wrapper = mountPage([row()], [], { searchError: true });

        expect(wrapper.text()).toContain(t('cells.search.notFound'));
    });

    it('pulses the cell matched by a search result', async () => {
        const jumpToCell: CellSlotLocation = {
            row_letter: 'A',
            cell_number: 1,
            flat_number: 1,
        };
        const wrapper = mountPage([row({ letter: 'A', cells_count: 2 })], [], {
            jumpToCell,
        });

        await flushPromises();

        expect(slot(wrapper, formatSlot('A', 1, 1))?.classes()).toContain(
            'ring-emerald-500',
        );
        expect(slot(wrapper, formatSlot('A', 2, 1))?.classes()).not.toContain(
            'ring-emerald-500',
        );
    });

    it('zooms in and out via the toolbar buttons, clamped to the zoom bounds', async () => {
        const wrapper = mountPage([row()], []);

        expect(wrapper.get('[data-testid="zoom-percent"]').text()).toContain(
            '100%',
        );

        await wrapper
            .get(`[title="${t('cells.map.zoomIn')}"]`)
            .trigger('click');
        expect(wrapper.get('[data-testid="zoom-percent"]').text()).toContain(
            '120%',
        );

        await wrapper
            .get(`[title="${t('cells.map.zoomOut')}"]`)
            .trigger('click');
        await wrapper
            .get(`[title="${t('cells.map.zoomOut')}"]`)
            .trigger('click');
        expect(wrapper.get('[data-testid="zoom-percent"]').text()).toContain(
            '80%',
        );
    });

    function headerLabels(wrapper: ReturnType<typeof mountPage>): string[] {
        return wrapper
            .findAll('[data-testid="axis-header-item"]')
            .map((el) => el.text());
    }

    function bandLabels(wrapper: ReturnType<typeof mountPage>): string[] {
        return wrapper
            .findAll('[data-testid="band-label"]')
            .map((el) => el.text());
    }

    function bandLabelAtEnd(wrapper: ReturnType<typeof mountPage>): boolean {
        return (
            wrapper
                .findAll('[data-testid="map-row"]')[0]
                .element.lastElementChild?.getAttribute('data-testid') ===
            'band-label'
        );
    }

    function bandsAsColumns(wrapper: ReturnType<typeof mountPage>): boolean {
        return wrapper
            .get('[data-testid="map-viewport"] > div')
            .classes()
            .includes('flex-row');
    }

    function headerAtBottom(wrapper: ReturnType<typeof mountPage>): boolean {
        const children = Array.from(
            wrapper.get('[data-testid="map-viewport"] > div').element.children,
        );
        const headerIndex = children.findIndex((el) =>
            el.querySelector('[data-testid="axis-header-item"]'),
        );
        const firstRowIndex = children.findIndex(
            (el) => el.getAttribute('data-testid') === 'map-row',
        );

        return headerIndex > firstRowIndex;
    }

    /** The cell-number each band's slots resolve to, in on-screen order, '-' for a padded slot. */
    function bandItemNumbers(
        wrapper: ReturnType<typeof mountPage>,
        bandIndex: number,
    ): string[] {
        const band = wrapper.findAll('[data-testid="map-row"]')[bandIndex];
        const itemsContainer = band.element.children[
            band.element.children[0].getAttribute('data-testid') ===
            'band-label'
                ? 1
                : 0
        ] as HTMLElement;

        return Array.from(itemsContainer.children).map((el) => {
            const label = el.getAttribute('data-slot-label');

            return label ? label.split('·')[0].slice(1) : '-';
        });
    }

    it('keeps row headers in the same relative order through every rotation direction', async () => {
        const wrapper = mountPage(
            [
                row({ id: 1, letter: 'A', cells_count: 2, flats_count: 2 }),
                row({ id: 2, letter: 'B', cells_count: 2, flats_count: 2 }),
            ],
            [],
        );

        expect(bandLabels(wrapper)).toEqual(['A', 'B']);

        for (const title of [
            t('cells.map.rotateRight'),
            t('cells.map.rotateRight'),
            t('cells.map.rotateRight'),
            t('cells.map.rotateLeft'),
            t('cells.map.rotateLeft'),
        ]) {
            await wrapper.get(`[title="${title}"]`).trigger('click');
            expect(bandLabels(wrapper)).toEqual(['A', 'B']);
        }
    });

    it('rotates the map, swapping rows-as-columns and re-anchoring the band label edge', async () => {
        const wrapper = mountPage(
            [
                row({ id: 1, letter: 'A', cells_count: 2, flats_count: 2 }),
                row({ id: 2, letter: 'B', cells_count: 2, flats_count: 2 }),
            ],
            [],
        );

        expect(bandsAsColumns(wrapper)).toBe(false);
        expect(headerLabels(wrapper)).toEqual(['1', '2']);
        expect(bandLabelAtEnd(wrapper)).toBe(false);
        expect(headerAtBottom(wrapper)).toBe(false);

        await wrapper
            .get(`[title="${t('cells.map.rotateRight')}"]`)
            .trigger('click');

        // 90deg: bands run as columns, no shared number header, label at the
        // trailing (bottom) edge of each column.
        expect(bandsAsColumns(wrapper)).toBe(true);
        expect(wrapper.find('[data-testid="axis-header-item"]').exists()).toBe(
            false,
        );
        expect(bandLabelAtEnd(wrapper)).toBe(true);

        await wrapper
            .get(`[title="${t('cells.map.rotateLeft')}"]`)
            .trigger('click');
        await wrapper
            .get(`[title="${t('cells.map.rotateLeft')}"]`)
            .trigger('click');

        // wraps past 0deg to 270deg: bands run as columns again, but the
        // label sits at the leading (top) edge this time.
        expect(bandsAsColumns(wrapper)).toBe(true);
        expect(wrapper.find('[data-testid="axis-header-item"]').exists()).toBe(
            false,
        );
        expect(bandLabelAtEnd(wrapper)).toBe(false);
    });

    it('pads shorter rows so every band label lines up flush, matching the sorted cell order', async () => {
        const wrapper = mountPage(
            [
                row({ id: 1, letter: 'A', cells_count: 2, flats_count: 2 }),
                row({ id: 2, letter: 'B', cells_count: 3, flats_count: 2 }),
            ],
            [],
        );

        // 0deg: label at the start, rows are left unpadded/ragged.
        expect(bandItemNumbers(wrapper, 0)).toEqual(['1', '2']);
        expect(bandItemNumbers(wrapper, 1)).toEqual(['1', '2', '3']);

        await wrapper
            .get(`[title="${t('cells.map.rotateRight')}"]`)
            .trigger('click');

        // 90deg: bands as columns, numbers high-to-low, padded at the
        // leading edge so both labels land in the same trailing row.
        expect(bandItemNumbers(wrapper, 0)).toEqual(['-', '2', '1']);
        expect(bandItemNumbers(wrapper, 1)).toEqual(['3', '2', '1']);

        await wrapper
            .get(`[title="${t('cells.map.rotateRight')}"]`)
            .trigger('click');

        // 180deg: bands as rows again, numbers high-to-low, label at the
        // end — padded at the leading edge so both labels line up.
        expect(bandItemNumbers(wrapper, 0)).toEqual(['-', '2', '1']);
        expect(bandItemNumbers(wrapper, 1)).toEqual(['3', '2', '1']);

        await wrapper
            .get(`[title="${t('cells.map.rotateRight')}"]`)
            .trigger('click');

        // 270deg: bands as columns, numbers low-to-high, label at the start
        // — padded at the trailing edge.
        expect(bandItemNumbers(wrapper, 0)).toEqual(['1', '2', '-']);
        expect(bandItemNumbers(wrapper, 1)).toEqual(['1', '2', '3']);
    });

    it('resets zoom and position but keeps rotation via the reset-view button', async () => {
        const wrapper = mountPage([row({ letter: 'A', cells_count: 2 })], []);

        await wrapper
            .get(`[title="${t('cells.map.zoomIn')}"]`)
            .trigger('click');
        await wrapper
            .get(`[title="${t('cells.map.rotateRight')}"]`)
            .trigger('click');
        await wrapper
            .get(`[title="${t('cells.map.resetView')}"]`)
            .trigger('click');

        expect(wrapper.get('[data-testid="zoom-percent"]').text()).toContain(
            '100%',
        );
        // rotation from rotateRight is preserved: still columns with the
        // band label at the end, not reset back to 0deg.
        expect(bandsAsColumns(wrapper)).toBe(true);
        expect(bandLabelAtEnd(wrapper)).toBe(true);
    });

    it('zooms via a wheel event on the map viewport', async () => {
        const wrapper = mountPage([row()], []);

        await wrapper
            .get('[data-testid="map-viewport"]')
            .trigger('wheel', { deltaY: -500 });

        expect(wrapper.get('[data-testid="zoom-percent"]').text()).toContain(
            '150%',
        );
    });

    it('shows a grab cursor on the map viewport to indicate it is scrollable', () => {
        const wrapper = mountPage([row()], []);

        const classes = wrapper.get('[data-testid="map-viewport"]').classes();
        expect(classes).toContain('cursor-grab');
        expect(classes).toContain('active:cursor-grabbing');
    });

    it('disables text selection on the map viewport so dragging does not select cell text', () => {
        const wrapper = mountPage([row()], []);

        const classes = wrapper.get('[data-testid="map-viewport"]').classes();
        expect(classes).toContain('select-none');
    });

    it('toggles the map into and out of full screen mode via the toolbar button', async () => {
        const wrapper = mountPage([row()], []);

        expect(
            wrapper.get('[data-testid="map-section"]').classes(),
        ).not.toContain('fixed');

        await wrapper
            .get(`[title="${t('cells.map.fullscreen')}"]`)
            .trigger('click');

        expect(wrapper.get('[data-testid="map-section"]').classes()).toContain(
            'fixed',
        );

        await wrapper
            .get(`[title="${t('cells.map.exitFullscreen')}"]`)
            .trigger('click');

        expect(
            wrapper.get('[data-testid="map-section"]').classes(),
        ).not.toContain('fixed');
    });

    it('exits full screen mode when Escape is pressed', async () => {
        const wrapper = mountPage([row()], []);

        await wrapper
            .get(`[title="${t('cells.map.fullscreen')}"]`)
            .trigger('click');
        expect(wrapper.get('[data-testid="map-section"]').classes()).toContain(
            'fixed',
        );

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await flushPromises();

        expect(
            wrapper.get('[data-testid="map-section"]').classes(),
        ).not.toContain('fixed');
    });

    describe('3D view toggle', () => {
        it('toggles between the 2D and 3D map views, hiding 2D-only controls in 3D', async () => {
            const wrapper = mountPage([row()], []);

            expect(wrapper.find('[data-testid="map-viewport"]').exists()).toBe(
                true,
            );
            expect(wrapper.find('[data-testid="map-3d-stub"]').exists()).toBe(
                false,
            );
            expect(
                wrapper.find(`[title="${t('cells.map.zoomIn')}"]`).exists(),
            ).toBe(true);
            expect(wrapper.find('[data-testid="flat-tab"]').exists()).toBe(
                true,
            );

            await wrapper.get('[data-testid="view-mode-3d"]').trigger('click');

            expect(wrapper.find('[data-testid="map-viewport"]').exists()).toBe(
                false,
            );
            expect(wrapper.find('[data-testid="map-3d-stub"]').exists()).toBe(
                true,
            );
            expect(
                wrapper.find(`[title="${t('cells.map.zoomIn')}"]`).exists(),
            ).toBe(false);
            expect(wrapper.find('[data-testid="flat-tab"]').exists()).toBe(
                false,
            );

            await wrapper.get('[data-testid="view-mode-2d"]').trigger('click');

            expect(wrapper.find('[data-testid="map-viewport"]').exists()).toBe(
                true,
            );
            expect(wrapper.find('[data-testid="map-3d-stub"]').exists()).toBe(
                false,
            );
        });

        it('shows the overview/orbit toggle only in 3D mode, and only once entering 3D', async () => {
            const wrapper = mountPage([row()], []);

            expect(
                wrapper.find('[data-testid="camera-mode-orbit"]').exists(),
            ).toBe(false);

            await wrapper.get('[data-testid="view-mode-3d"]').trigger('click');

            expect(
                wrapper.find('[data-testid="camera-mode-orbit"]').exists(),
            ).toBe(true);

            await wrapper.get('[data-testid="view-mode-2d"]').trigger('click');

            expect(
                wrapper.find('[data-testid="camera-mode-orbit"]').exists(),
            ).toBe(false);
        });

        it('toggles the 3D camera between walk and orbit, tracking the pressed state via the emitted mode', async () => {
            const wrapper = mountPage([row()], []);

            await wrapper.get('[data-testid="view-mode-3d"]').trigger('click');
            const toggle = () =>
                wrapper.get('[data-testid="camera-mode-orbit"]');

            expect(toggle().attributes('aria-pressed')).toBe('false');

            await toggle().trigger('click');

            expect(cellMap3DSetCameraMode).toHaveBeenCalledWith('orbit');
            expect(toggle().attributes('aria-pressed')).toBe('true');

            await toggle().trigger('click');

            expect(cellMap3DSetCameraMode).toHaveBeenCalledWith('walk');
            expect(toggle().attributes('aria-pressed')).toBe('false');
        });

        it('resets the orbit toggle back to walk every time 3D mode is freshly entered', async () => {
            const wrapper = mountPage([row()], []);

            await wrapper.get('[data-testid="view-mode-3d"]').trigger('click');
            await wrapper
                .get('[data-testid="camera-mode-orbit"]')
                .trigger('click');
            expect(
                wrapper
                    .get('[data-testid="camera-mode-orbit"]')
                    .attributes('aria-pressed'),
            ).toBe('true');

            await wrapper.get('[data-testid="view-mode-2d"]').trigger('click');
            await wrapper.get('[data-testid="view-mode-3d"]').trigger('click');

            expect(
                wrapper
                    .get('[data-testid="camera-mode-orbit"]')
                    .attributes('aria-pressed'),
            ).toBe('false');
        });

        it("passes every flat's cells to the 3D view with highlight/pulse flags applied", async () => {
            const seed: CellHighlightSeed = {
                state: 'full',
                productIds: [],
                expiresWithinDays: null,
                expired: false,
            };
            const wrapper = mountPage(
                [row({ letter: 'A', cells_count: 2 })],
                [],
                {
                    flatNumber: 1,
                    maxFlatNumber: 2,
                    initialHighlight: seed,
                    cellHighlightSamples: [
                        highlightSample({
                            row_letter: 'A',
                            cell_number: 1,
                            flat_number: 1,
                            state: 'full',
                        }),
                        highlightSample({
                            row_letter: 'A',
                            cell_number: 2,
                            flat_number: 2,
                            state: 'empty',
                        }),
                    ],
                },
            );

            await wrapper.get('[data-testid="view-mode-3d"]').trigger('click');

            const stub = wrapper.getComponent({ name: 'CellMap3DStub' });
            const bands = stub.props('bands') as CellMap3DBand[];

            expect(bands).toEqual([
                {
                    letter: 'A',
                    items: [
                        {
                            cellNumber: 1,
                            flatNumber: 1,
                            state: 'full',
                            highlighted: true,
                            pulsing: false,
                            pallet: null,
                        },
                        {
                            cellNumber: 2,
                            flatNumber: 2,
                            state: 'empty',
                            highlighted: false,
                            pulsing: false,
                            pallet: null,
                        },
                    ],
                },
            ]);
        });

        it('resets via the exposed 3D method instead of the 2D recenter, when in 3D mode', async () => {
            const wrapper = mountPage([row()], []);

            await wrapper.get('[data-testid="view-mode-3d"]').trigger('click');
            await wrapper
                .get(`[title="${t('cells.map.resetView')}"]`)
                .trigger('click');

            expect(cellMap3DResetView).toHaveBeenCalled();
        });

        it('focuses the 3D camera on a cross-flat match without reloading, in 3D mode', async () => {
            const seed: CellHighlightSeed = {
                state: 'full',
                productIds: [],
                expiresWithinDays: null,
                expired: false,
            };
            const wrapper = mountPage(
                [row({ letter: 'A', cells_count: 1 })],
                [],
                {
                    flatNumber: 1,
                    maxFlatNumber: 2,
                    initialHighlight: seed,
                    cellHighlightSamples: [
                        highlightSample({
                            row_letter: 'A',
                            cell_number: 1,
                            flat_number: 1,
                            state: 'full',
                        }),
                        highlightSample({
                            row_letter: 'A',
                            cell_number: 1,
                            flat_number: 2,
                            state: 'full',
                        }),
                    ],
                },
            );

            await wrapper.get('[data-testid="view-mode-3d"]').trigger('click');

            const next = () =>
                wrapper
                    .get(`[title="${t('cells.filters.nextMatch')}"]`)
                    .trigger('click');

            await next();
            await next();

            expect(cellMap3DFocusCell).toHaveBeenCalledWith('A', 1, 2);
            expect(routerGetMock).not.toHaveBeenCalled();
        });

        it('focuses the 3D camera when the jumpToCell prop changes while in 3D mode', async () => {
            const wrapper = mountPage(
                [row({ letter: 'A', cells_count: 2 })],
                [],
            );

            await wrapper.get('[data-testid="view-mode-3d"]').trigger('click');
            cellMap3DFocusCell.mockClear();

            await wrapper.setProps({
                jumpToCell: {
                    row_letter: 'A',
                    cell_number: 2,
                    flat_number: 1,
                },
            });

            expect(cellMap3DFocusCell).toHaveBeenCalledWith('A', 2, 1);
        });
    });
});
