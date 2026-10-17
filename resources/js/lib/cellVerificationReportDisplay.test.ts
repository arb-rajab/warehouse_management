import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { accept as acceptReportRoute } from '@/actions/App/Http/Controllers/Admin/CellVerificationReportController';
import { cellVerificationReport } from '@/testing/factories';
import type { CellVerificationSnapshot } from '@/types/admin';
import {
    acceptReport,
    canAcceptReport,
    snapshotProductLabel,
} from './cellVerificationReportDisplay';
import { i18n, t } from './i18n';

const { routerPostMock } = vi.hoisted(() => ({ routerPostMock: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({ router: { post: routerPostMock } }));

function snapshot(
    overrides: Partial<CellVerificationSnapshot> = {},
): CellVerificationSnapshot {
    return {
        cell_state: 'full',
        product: {
            id: 1,
            name: 'Widgets',
            ar_name: 'ودجات',
            image_url: null,
            boxes_count: 10,
            active: true,
        },
        boxes_count: 10,
        expiration_date: null,
        ...overrides,
    };
}

describe('snapshotProductLabel', () => {
    afterEach(() => {
        i18n.global.locale.value = 'en';
    });

    it('returns the product name when a product is present', () => {
        expect(
            snapshotProductLabel(
                snapshot({
                    product: {
                        id: 1,
                        name: 'Widgets',
                        ar_name: 'ودجات',
                        image_url: null,
                        boxes_count: 10,
                        active: true,
                    },
                }),
            ),
        ).toBe('Widgets');
    });

    it('returns null when there is no product', () => {
        expect(snapshotProductLabel(snapshot({ product: null }))).toBeNull();
    });

    it("returns the product's Arabic name when the locale is Arabic", () => {
        i18n.global.locale.value = 'ar';

        expect(snapshotProductLabel(snapshot())).toBe('ودجات');
    });

    it('falls back to the base name for a product the store never translated', () => {
        i18n.global.locale.value = 'ar';

        expect(
            snapshotProductLabel(
                snapshot({
                    product: {
                        id: 1,
                        name: 'Widgets',
                        ar_name: '',
                        image_url: null,
                        boxes_count: 10,
                        active: true,
                    },
                }),
            ),
        ).toBe('Widgets');
    });
});

describe('canAcceptReport', () => {
    it('allows an unaccepted incorrect report from a completed round', () => {
        expect(
            canAcceptReport(
                cellVerificationReport({
                    is_correct: false,
                    round_completed: true,
                    accepted_at: null,
                }),
            ),
        ).toBe(true);
    });

    it('refuses a correct report', () => {
        expect(
            canAcceptReport(cellVerificationReport({ is_correct: true })),
        ).toBe(false);
    });

    it('refuses an already accepted report', () => {
        expect(
            canAcceptReport(
                cellVerificationReport({
                    is_correct: false,
                    accepted_at: '2026-08-02T09:00:00Z',
                    accepted_by: { id: 9, name: 'Zoe Admin' },
                }),
            ),
        ).toBe(false);
    });

    it('refuses a report whose round is unfinished or unknown', () => {
        expect(
            canAcceptReport(
                cellVerificationReport({
                    is_correct: false,
                    round_completed: false,
                }),
            ),
        ).toBe(false);
        expect(
            canAcceptReport(
                cellVerificationReport({
                    is_correct: false,
                    round_completed: undefined,
                }),
            ),
        ).toBe(false);
    });
});

describe('acceptReport', () => {
    beforeEach(() => {
        routerPostMock.mockReset();
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    const report = cellVerificationReport({
        id: 5,
        is_correct: false,
        cell: { row_letter: 'B', cell_number: 4, flat_number: 1 },
    });

    it('confirms with the slot label, then posts without a return_to by default', () => {
        const confirmSpy = vi.fn(() => true);
        vi.stubGlobal('confirm', confirmSpy);

        acceptReport(report);

        expect(confirmSpy).toHaveBeenCalledWith(
            t('cellVerificationReport.acceptConfirm', { slot: 'B4·1' }),
        );
        expect(routerPostMock).toHaveBeenCalledWith(
            acceptReportRoute.url({ cellVerificationReport: 5 }),
            { return_to: null },
            { preserveScroll: true },
        );
    });

    it('sends return_to when accepting from the users page', () => {
        vi.stubGlobal('confirm', () => true);

        acceptReport(report, 'user');

        expect(routerPostMock).toHaveBeenCalledWith(
            acceptReportRoute.url({ cellVerificationReport: 5 }),
            { return_to: 'user' },
            { preserveScroll: true },
        );
    });

    it('does nothing when the confirmation is cancelled', () => {
        vi.stubGlobal('confirm', () => false);

        acceptReport(report);

        expect(routerPostMock).not.toHaveBeenCalled();
    });
});
