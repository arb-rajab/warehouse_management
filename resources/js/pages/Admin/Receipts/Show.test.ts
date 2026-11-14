import { Head } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { show as helpShow } from '@/actions/App/Http/Controllers/Admin/HelpController';
import {
    index as receiptsIndex,
    show as receiptShow,
} from '@/actions/App/Http/Controllers/Admin/ReceiptController';
import { show as userShow } from '@/actions/App/Http/Controllers/Admin/UserController';
import ProductOptionLabel from '@/components/ProductOptionLabel.vue';
import ReceiptFileTile from '@/components/ReceiptFileTile.vue';
import ReceiptStatusBadge from '@/components/ReceiptStatusBadge.vue';
import { formatDate, formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import { rowCells } from '@/testing/dom';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type {
    ReceiptDetail,
    ReceiptFile,
    ReceiptPalletEntry,
} from '@/types/admin';
import Show from './Show.vue';

const { usePageMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, headStub } = await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
    };
});

function file(overrides: Partial<ReceiptFile> = {}): ReceiptFile {
    return {
        id: 4,
        original_name: 'bill-front.jpg',
        mime_type: 'image/jpeg',
        size: 2048,
        url: '/admin/receipts/9/files/4',
        created_at: '2026-08-01T10:00:00Z',
        ...overrides,
    };
}

function entry(
    overrides: Partial<ReceiptPalletEntry> = {},
): ReceiptPalletEntry {
    return {
        product_id: 3,
        product: {
            id: 3,
            name: 'Basmati rice',
            ar_name: 'أرز بسمتي',
            image_url: null,
            boxes_count: 10,
            active: true,
        },
        boxes: 24,
        expiration_date: '2027-03-15',
        note: 'Olive oil 5L, Brand X',
        ...overrides,
    };
}

function detail(overrides: Partial<ReceiptDetail> = {}): ReceiptDetail {
    return {
        id: 9,
        warehouse: { id: 5, name: 'Annex' },
        note: 'Line one\nLine two',
        status: 'finalized',
        pallets_count: 2,
        files_count: 1,
        created_by: { id: 7, name: 'Jane Doe' },
        finalized_by: { id: 8, name: 'Omar Ali' },
        finalized_at: '2026-08-02T09:30:00Z',
        created_at: '2026-08-01T10:00:00Z',
        files: [file()],
        pallets: [
            entry(),
            entry({
                product_id: 4,
                product: {
                    id: 4,
                    name: 'Lentils',
                    ar_name: 'عدس',
                    image_url: '/images/lentils.png',
                    boxes_count: 5,
                    active: true,
                },
                boxes: 6,
                expiration_date: null,
                note: null,
            }),
        ],
        ...overrides,
    };
}

function mountPage(receipt: ReceiptDetail = detail()) {
    usePageMock.mockReturnValue({
        url: receiptShow.url({ receipt: receipt.id }),
        props: defaultAuthProps(),
    });

    return mount(Show, { props: { receipt } });
}

function detailValue(wrapper: ReturnType<typeof mountPage>, label: string) {
    const index = wrapper.findAll('dt').findIndex((dt) => dt.text() === label);

    return wrapper.findAll('dd')[index];
}

describe('Receipts Show', () => {
    beforeEach(() => {
        resetMocks({ usePageMock });
    });

    it('renders the receipt number in the Head and the PageHeader', () => {
        const wrapper = mountPage();

        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('receipts.show.title', { id: 9 }),
        );
        expect(wrapper.get('h1').text()).toBe(
            t('receipts.show.title', { id: 9 }),
        );
    });

    it('links the help icon to the receipts help topic and back to the list', () => {
        const wrapper = mountPage();
        const links = wrapper.get('main').findAll('a');

        expect(
            links
                .find((a) => a.attributes('aria-label') === t('help.viewHelp'))
                ?.attributes('href'),
        ).toBe(helpShow.url({ topic: 'receipts' }));
        expect(
            links
                .find((a) => a.text() === t('receipts.show.backToList'))
                ?.attributes('href'),
        ).toBe(receiptsIndex.url());
    });

    describe('details', () => {
        it('lists each label above its value', () => {
            const wrapper = mountPage();

            expect(wrapper.findAll('dt').map((dt) => dt.text())).toEqual([
                t('receipts.show.warehouse'),
                t('receipts.show.status'),
                t('receipts.show.createdBy'),
                t('receipts.show.createdAt'),
                t('receipts.show.finalizedBy'),
                t('receipts.show.finalizedAt'),
                t('receipts.show.note'),
            ]);
            expect(
                detailValue(wrapper, t('receipts.show.warehouse')).text(),
            ).toBe('Annex');
            expect(
                detailValue(wrapper, t('receipts.show.createdAt')).text(),
            ).toBe(formatDateTime('2026-08-01T10:00:00Z'));
            expect(
                detailValue(wrapper, t('receipts.show.finalizedAt')).text(),
            ).toBe(formatDateTime('2026-08-02T09:30:00Z'));
        });

        it('shows the status badge', () => {
            const wrapper = mountPage();

            expect(
                wrapper.getComponent(ReceiptStatusBadge).props('status'),
            ).toBe('finalized');
        });

        it('links the creator and the finalizer to their user pages', () => {
            const wrapper = mountPage();

            const created = detailValue(
                wrapper,
                t('receipts.show.createdBy'),
            ).get('a');
            expect(created.attributes('href')).toBe(userShow.url({ user: 7 }));
            expect(created.text()).toBe('Jane Doe');

            const finalized = detailValue(
                wrapper,
                t('receipts.show.finalizedBy'),
            ).get('a');
            expect(finalized.attributes('href')).toBe(
                userShow.url({ user: 8 }),
            );
            expect(finalized.text()).toBe('Omar Ali');
        });

        it('preserves the line breaks of the note', () => {
            const wrapper = mountPage();
            const note = detailValue(wrapper, t('receipts.show.note'));

            expect(note.text()).toBe('Line one\nLine two');
            expect(note.classes()).toContain('whitespace-pre-wrap');
        });

        it('shows "-" for the empty values of a draft without a note', () => {
            const wrapper = mountPage(
                detail({
                    status: 'draft',
                    note: null,
                    finalized_by: null,
                    finalized_at: null,
                }),
            );

            for (const key of ['finalizedBy', 'finalizedAt', 'note']) {
                const value = detailValue(wrapper, t(`receipts.show.${key}`));

                expect(value.text()).toBe('-');
                expect(value.find('a').exists()).toBe(false);
            }
        });
    });

    describe('bill files', () => {
        it('shows the count in the heading and a tile for every file', () => {
            const wrapper = mountPage(
                detail({
                    files: [
                        file(),
                        file({
                            id: 5,
                            original_name: 'bill.pdf',
                            mime_type: 'application/pdf',
                            url: '/admin/receipts/9/files/5',
                        }),
                    ],
                }),
            );

            expect(wrapper.text()).toContain(
                t('receipts.show.files', { count: 2 }),
            );

            const tiles = wrapper.findAllComponents(ReceiptFileTile);
            expect(tiles.map((tile) => tile.props('file').id)).toEqual([4, 5]);
            expect(wrapper.text()).not.toContain(t('receipts.show.noFiles'));
        });

        it('shows the empty state when there are no files', () => {
            const wrapper = mountPage(detail({ files: [] }));

            expect(wrapper.findAllComponents(ReceiptFileTile)).toHaveLength(0);
            expect(wrapper.text()).toContain(t('receipts.show.noFiles'));
            expect(wrapper.text()).toContain(
                t('receipts.show.files', { count: 0 }),
            );
        });
    });

    describe('pallets as written', () => {
        it('has number, product, boxes, expiration and note columns and no id column', () => {
            const wrapper = mountPage();

            expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual([
                t('receipts.show.columns.number'),
                t('receipts.show.columns.product'),
                t('receipts.show.columns.boxes'),
                t('receipts.show.columns.expiration'),
                t('receipts.show.columns.note'),
            ]);
            expect(wrapper.text()).toContain(
                t('receipts.show.pallets', { count: 2 }),
            );
        });

        it('numbers the rows by position from 1, in the order written', () => {
            const wrapper = mountPage();

            expect(rowCells(wrapper, 0)[0].text()).toBe('1');
            expect(rowCells(wrapper, 1)[0].text()).toBe('2');
        });

        it('shows the product label, boxes and expiry of each row', () => {
            const wrapper = mountPage();
            const first = rowCells(wrapper, 0);
            const second = rowCells(wrapper, 1);

            expect(
                first[1].getComponent(ProductOptionLabel).props('product'),
            ).toMatchObject({ id: 3 });
            expect(first[1].text()).toContain('Basmati rice');
            expect(first[2].text()).toBe('24');
            expect(first[3].text()).toBe(formatDate('2027-03-15'));
            expect(second[1].text()).toContain('Lentils');
            expect(second[2].text()).toBe('6');
            expect(second[3].text()).toBe('—');
        });

        it("shows each row's note, and a dash for a row without one", () => {
            const wrapper = mountPage();
            const first = rowCells(wrapper, 0)[4];

            expect(first.text()).toBe('Olive oil 5L, Brand X');
            expect(first.attributes('title')).toBe('Olive oil 5L, Brand X');
            expect(rowCells(wrapper, 1)[4].text()).toBe('—');
        });

        it('shows a thumbnail only for a product that has an image', () => {
            const wrapper = mountPage();

            expect(rowCells(wrapper, 0)[1].find('img').exists()).toBe(false);
            expect(rowCells(wrapper, 1)[1].get('img').attributes('src')).toBe(
                '/images/lentils.png',
            );
        });

        it('has no link in any pallet cell and never shows a pallet id', () => {
            const wrapper = mountPage();

            expect(wrapper.find('tbody a').exists()).toBe(false);
            expect(wrapper.find('tbody').html()).not.toMatch(/pallet/i);
        });

        it('shows the empty state when no pallets were written', () => {
            const wrapper = mountPage(detail({ pallets: [] }));

            expect(wrapper.findAll('tbody tr')).toHaveLength(1);
            expect(wrapper.get('tbody').text()).toContain(
                t('receipts.show.palletsEmpty'),
            );
        });
    });

    describe('draft hint', () => {
        it('is shown for a draft', () => {
            const wrapper = mountPage(
                detail({ status: 'draft', finalized_at: null }),
            );

            expect(wrapper.get('[role="status"]').text()).toBe(
                t('receipts.show.draftHint'),
            );
        });

        it('is not shown once finalized', () => {
            const wrapper = mountPage();

            expect(wrapper.find('[role="status"]').exists()).toBe(false);
            expect(wrapper.text()).not.toContain(t('receipts.show.draftHint'));
        });
    });
});
