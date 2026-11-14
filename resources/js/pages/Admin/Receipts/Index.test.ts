import { Head } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { show as helpShow } from '@/actions/App/Http/Controllers/Admin/HelpController';
import {
    index as receiptsIndex,
    show as receiptShow,
} from '@/actions/App/Http/Controllers/Admin/ReceiptController';
import { show as userShow } from '@/actions/App/Http/Controllers/Admin/UserController';
import ReceiptStatusBadge from '@/components/ReceiptStatusBadge.vue';
import { formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import { rowCells } from '@/testing/dom';
import { paginated } from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import {
    expectColumnCanBeHidden,
    expectColumnLocked,
    expectHeadersMatchCells,
    headerTexts,
    offeredColumns,
    openColumnPicker,
    setColumnShown,
    storeColumns,
    storedColumns,
} from '@/testing/tableColumns';
import type {
    ReceiptFilterOptions,
    ReceiptFilters,
    ReceiptListItem,
} from '@/types/admin';
import Index from './Index.vue';

const { usePageMock, routerGetMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, headStub } = await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { get: routerGetMock },
        useHttp: () => ({
            get: (
                _url: string,
                options?: { onSuccess?: (response: unknown) => void },
            ) => options?.onSuccess?.(paginated(filterOptions.warehouses, 20)),
        }),
    };
});

const filterOptions: ReceiptFilterOptions = {
    warehouses: [{ id: 5, name: 'Annex' }],
};

const LIST_ID = 'admin.receipts';

function receipt(overrides: Partial<ReceiptListItem> = {}): ReceiptListItem {
    return {
        id: 12,
        warehouse: { id: 5, name: 'Annex' },
        note: null,
        status: 'finalized',
        pallets_count: 3,
        files_count: 2,
        created_by: { id: 7, name: 'Jane Doe' },
        finalized_by: { id: 8, name: 'Omar Ali' },
        finalized_at: '2026-08-02T09:30:00Z',
        created_at: '2026-08-01T10:00:00Z',
        ...overrides,
    };
}

function mountPage(
    rows: ReceiptListItem[] = [],
    filters: ReceiptFilters = {},
    perPage = 20,
) {
    usePageMock.mockReturnValue({
        url: receiptsIndex.url(),
        props: defaultAuthProps(),
    });

    return mount(Index, {
        props: {
            receipts: paginated(rows, perPage),
            filters,
            filterOptions,
        },
    });
}

function trigger(wrapper: ReturnType<typeof mountPage>) {
    return wrapper
        .findAll('button')
        .find((button) => button.text().includes(t('common.filters.title')))!;
}

async function openFilters(wrapper: ReturnType<typeof mountPage>) {
    await trigger(wrapper).trigger('click');
}

const COLUMN = {
    id: t('receipts.index.columns.id'),
    warehouse: t('receipts.index.columns.warehouse'),
    status: t('receipts.index.columns.status'),
    pallets: t('receipts.index.columns.pallets'),
    files: t('receipts.index.columns.files'),
    createdBy: t('receipts.index.columns.createdBy'),
    createdAt: t('receipts.index.columns.createdAt'),
    finalizedAt: t('receipts.index.columns.finalizedAt'),
};

describe('Receipts Index', () => {
    beforeEach(() => {
        resetMocks({ usePageMock, routerGetMock });
        window.localStorage.clear();
    });

    it('renders the title in the Head and the PageHeader', () => {
        const wrapper = mountPage();

        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('receipts.index.title'),
        );
        expect(wrapper.get('h1').text()).toBe(t('receipts.index.title'));
    });

    it('links the help icon to the receipts help topic', () => {
        const wrapper = mountPage();
        const helpLink = wrapper
            .get('main')
            .findAll('a')
            .find((a) => a.attributes('aria-label') === t('help.viewHelp'));

        expect(helpLink?.attributes('href')).toBe(
            helpShow.url({ topic: 'receipts' }),
        );
    });

    it('renders the default columns and hides created by and finalized at', () => {
        const wrapper = mountPage([receipt()]);

        expect(headerTexts(wrapper)).toEqual([
            COLUMN.id,
            COLUMN.warehouse,
            COLUMN.status,
            COLUMN.pallets,
            COLUMN.files,
            COLUMN.createdAt,
        ]);
        expectHeadersMatchCells(wrapper);
    });

    it('renders a row: receipt link, warehouse, badge, counts and created date', () => {
        const wrapper = mountPage([receipt()]);
        const cells = rowCells(wrapper);

        const link = cells[0].get('a');
        expect(link.attributes('href')).toBe(receiptShow.url({ receipt: 12 }));
        expect(link.text()).toBe('#12');
        expect(cells[1].text()).toBe('Annex');
        expect(cells[2].text()).toBe(t('receipts.status.finalized'));
        expect(cells[2].getComponent(ReceiptStatusBadge).props('status')).toBe(
            'finalized',
        );
        expect(cells[3].text()).toBe('3');
        expect(cells[4].text()).toBe('2');
        expect(cells[5].text()).toBe(formatDateTime('2026-08-01T10:00:00Z'));
    });

    it('shows a draft with the draft badge', () => {
        const wrapper = mountPage([
            receipt({
                status: 'draft',
                finalized_at: null,
                finalized_by: null,
            }),
        ]);

        expect(rowCells(wrapper)[2].text()).toBe(t('receipts.status.draft'));
    });

    it('shows created by as a user link and the finalized date once those columns are on', async () => {
        const wrapper = mountPage([receipt()]);

        await setColumnShown(wrapper, COLUMN.createdBy, true);
        await setColumnShown(wrapper, COLUMN.finalizedAt, true);

        expect(headerTexts(wrapper)).toEqual([
            COLUMN.id,
            COLUMN.warehouse,
            COLUMN.status,
            COLUMN.pallets,
            COLUMN.files,
            COLUMN.createdBy,
            COLUMN.createdAt,
            COLUMN.finalizedAt,
        ]);
        expectHeadersMatchCells(wrapper);

        const cells = rowCells(wrapper);
        const userLink = cells[5].get('a');
        expect(userLink.attributes('href')).toBe(userShow.url({ user: 7 }));
        expect(userLink.text()).toBe('Jane Doe');
        expect(cells[7].text()).toBe(formatDateTime('2026-08-02T09:30:00Z'));
    });

    it('shows an em dash as the finalized date of a draft', async () => {
        const wrapper = mountPage([
            receipt({
                status: 'draft',
                finalized_at: null,
                finalized_by: null,
            }),
        ]);

        await setColumnShown(wrapper, COLUMN.finalizedAt, true);

        expect(rowCells(wrapper)[6].text()).toBe('—');
    });

    it('shows the empty message when there are no receipts and no filter', () => {
        const wrapper = mountPage([]);

        expect(wrapper.text()).toContain(t('receipts.index.empty'));
        expect(wrapper.text()).not.toContain(t('receipts.index.emptyFiltered'));
    });

    it('shows the filtered empty message when a filter is active', () => {
        const wrapper = mountPage([], { status: 'draft' });

        expect(wrapper.text()).toContain(t('receipts.index.emptyFiltered'));
    });

    describe('sorting', () => {
        it('renders only the created at header as sortable, newest first by default', () => {
            const wrapper = mountPage([receipt()]);
            const sortable = wrapper
                .findAll('thead th button')
                .map((button) => button.text());

            expect(sortable).toEqual([COLUMN.createdAt]);
        });

        it('sorts ascending on the first click, keeping the filters', async () => {
            const wrapper = mountPage([receipt()], {
                status: 'draft',
                per_page: 50,
            });

            await wrapper.get('thead th button').trigger('click');

            expect(routerGetMock).toHaveBeenCalledWith(
                receiptsIndex.url(),
                {
                    warehouse_ids: [],
                    status: 'draft',
                    sort_direction: 'asc',
                    per_page: 50,
                },
                { preserveState: true, replace: true },
            );
        });

        it('flips back to descending on the second click', async () => {
            const wrapper = mountPage([receipt()], { sort_direction: 'asc' });

            await wrapper.get('thead th button').trigger('click');

            expect(routerGetMock).toHaveBeenCalledWith(
                receiptsIndex.url(),
                expect.objectContaining({ sort_direction: 'desc' }),
                { preserveState: true, replace: true },
            );
        });
    });

    describe('filters', () => {
        it('keeps the dialog closed until the Filters button is clicked', async () => {
            const wrapper = mountPage();

            expect(wrapper.find('[role="dialog"]').exists()).toBe(false);

            await openFilters(wrapper);

            expect(wrapper.find('#filter-warehouse').exists()).toBe(true);
            expect(wrapper.find('#filter-status').exists()).toBe(true);
        });

        it('offers all, draft and finalized as status options', async () => {
            const wrapper = mountPage();
            await openFilters(wrapper);

            expect(
                wrapper
                    .get('#filter-status')
                    .findAll('option')
                    .map((option) => option.text()),
            ).toEqual([
                t('receipts.filters.all'),
                t('receipts.status.draft'),
                t('receipts.status.finalized'),
            ]);
        });

        it('shows no count badge without filters and one per active filter', () => {
            expect(trigger(mountPage()).find('span').exists()).toBe(false);

            const wrapper = mountPage([], {
                warehouse_ids: [5],
                status: 'finalized',
            });

            expect(trigger(wrapper).get('span').text()).toBe('2');
        });

        it('applies the chosen warehouse and status and closes the dialog', async () => {
            const wrapper = mountPage([], { per_page: 50 });
            await openFilters(wrapper);

            await wrapper.get('#filter-warehouse').trigger('click');
            await wrapper
                .findAll('input[type="checkbox"]')
                .find((box) => box.element.closest('[role="listbox"]'))!
                .setValue(true);
            await wrapper.get('#filter-status').setValue('draft');
            await wrapper.get('#receipts-filter-form').trigger('submit');

            expect(routerGetMock).toHaveBeenCalledWith(
                receiptsIndex.url(),
                {
                    warehouse_ids: ['5'],
                    status: 'draft',
                    sort_direction: '',
                    per_page: 50,
                },
                { preserveState: true, replace: true },
            );
            expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        });

        it('leaves status out of the query when it is all', async () => {
            const wrapper = mountPage();
            await openFilters(wrapper);

            await wrapper.get('#receipts-filter-form').trigger('submit');

            const query = routerGetMock.mock.calls[0][1];
            expect(query).not.toHaveProperty('status');
        });

        it('clears every filter but keeps the page size', async () => {
            const wrapper = mountPage(
                [],
                {
                    warehouse_ids: [5],
                    status: 'draft',
                    sort_direction: 'asc',
                    per_page: 50,
                },
                50,
            );
            await openFilters(wrapper);

            await wrapper
                .findAll('button')
                .find((button) =>
                    button.text().includes(t('common.filters.clear')),
                )!
                .trigger('click');

            expect(routerGetMock).toHaveBeenCalledWith(
                receiptsIndex.url(),
                { per_page: 50 },
                { preserveState: true, replace: true },
            );
            expect(trigger(wrapper).find('span').exists()).toBe(false);
        });
    });

    describe('column picker', () => {
        it('offers every column and locks the required ones', async () => {
            const wrapper = mountPage([receipt()]);
            await openColumnPicker(wrapper);

            expect(offeredColumns(wrapper)).toEqual(Object.values(COLUMN));
            expectColumnLocked(wrapper, COLUMN.id);
            expectColumnLocked(wrapper, COLUMN.warehouse);
            expectColumnLocked(wrapper, COLUMN.status);
        });

        it('hides an optional column from the header and every row and remembers it', async () => {
            const wrapper = mountPage([receipt(), receipt({ id: 13 })]);

            await expectColumnCanBeHidden(wrapper, LIST_ID, {
                key: 'files',
                label: COLUMN.files,
            });
        });

        it('stores nothing until the user changes the columns', () => {
            mountPage([receipt()]);

            expect(storedColumns(LIST_ID)).toBeNull();
        });

        it('stores the shown columns per user when an optional column is turned on', async () => {
            const wrapper = mountPage([receipt()]);

            await setColumnShown(wrapper, COLUMN.createdBy, true);

            expect(storedColumns(LIST_ID)).toContain('createdBy');
            expect(storedColumns(LIST_ID, 99)).toBeNull();
        });

        it('restores a remembered choice on the next visit', () => {
            storeColumns(LIST_ID, ['id', 'warehouse', 'status', 'finalizedAt']);

            const wrapper = mountPage([receipt()]);

            expect(headerTexts(wrapper)).toEqual([
                COLUMN.id,
                COLUMN.warehouse,
                COLUMN.status,
                COLUMN.finalizedAt,
            ]);
            expectHeadersMatchCells(wrapper);
        });
    });

    describe('pagination', () => {
        it('shows the page size selector at the current size', () => {
            const wrapper = mountPage([receipt()], { per_page: 50 }, 50);

            expect(
                (wrapper.get('select').element as HTMLSelectElement).value,
            ).toBe('50');
        });

        it('requests the new page size, keeping the active filters', async () => {
            const wrapper = mountPage([receipt()], {
                status: 'draft',
                per_page: 20,
            });

            await wrapper.get('select').setValue('50');

            expect(routerGetMock).toHaveBeenCalledWith(
                receiptsIndex.url(),
                expect.objectContaining({ status: 'draft', per_page: 50 }),
                { preserveState: true, replace: true },
            );
        });
    });
});
