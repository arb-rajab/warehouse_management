import type {
    Cell,
    CellStatusLog,
    CellVerificationReport,
    CellVerificationRound,
    Paginated,
    Row,
    Setting,
    User,
} from '@/types/admin';

/**
 * Shared `Paginated<T>` test fixture — every page test that mounts a paginated
 * table built this `meta` shape independently before this existed.
 */
export function paginated<T>(data: T[], perPage = 20): Paginated<T> {
    return {
        data,
        meta: {
            current_page: 1,
            last_page: 1,
            per_page: perPage,
            total: data.length,
            from: data.length ? 1 : null,
            to: data.length,
            links: [],
        },
    };
}

export function row(overrides: Partial<Row> = {}): Row {
    return {
        id: 1,
        letter: 'A',
        cells_count: 5,
        flats_count: 7,
        has_pallets: false,
        ...overrides,
    };
}

export function cell(overrides: Partial<Cell> = {}): Cell {
    return {
        id: 1,
        cell_number: 1,
        flat_number: 1,
        state: 'empty',
        is_active: true,
        pallet: null,
        ...overrides,
    };
}

export function setting(overrides: Partial<Setting> = {}): Setting {
    return {
        qr_code_width: 240,
        qr_code_height: 240,
        ...overrides,
    };
}

export function user(overrides: Partial<User> = {}): User {
    return {
        id: 7,
        name: 'Jane Doe',
        email: 'jane@example.com',
        is_admin: false,
        ...overrides,
    };
}

export function cellVerificationReport(
    overrides: Partial<CellVerificationReport> = {},
): CellVerificationReport {
    return {
        id: 1,
        cell_verification_round_id: 1,
        is_correct: true,
        cell: { row_letter: 'A', cell_number: 3, flat_number: 2 },
        expected: {
            cell_state: 'full',
            product: {
                id: 10,
                name: 'Widgets',
                ar_name: 'ودجات',
                image_url: null,
                boxes_count: 10,
            },
            boxes_count: 10,
            expiration_date: null,
        },
        reported: {
            cell_state: 'full',
            product: {
                id: 10,
                name: 'Widgets',
                ar_name: 'ودجات',
                image_url: null,
                boxes_count: 10,
            },
            boxes_count: 10,
            expiration_date: null,
        },
        note: null,
        user: { id: 7, name: 'Jane Doe' },
        created_at: '2026-08-01T10:00:00Z',
        ...overrides,
    };
}

export function cellVerificationRound(
    overrides: Partial<CellVerificationRound> = {},
): CellVerificationRound {
    return {
        id: 1,
        started_at: '2026-08-01T10:00:00Z',
        completed_at: '2026-08-01T11:00:00Z',
        reports_count: 3,
        rows: [
            { id: 1, letter: 'A' },
            { id: 2, letter: 'B' },
        ],
        user: { id: 7, name: 'Jane Doe' },
        ...overrides,
    };
}

export function cellLog(overrides: Partial<CellStatusLog> = {}): CellStatusLog {
    return {
        id: 1,
        action: 'stored',
        from_state: 'empty',
        to_state: 'full',
        note: 'Handle with care',
        boxes_count: null,
        cell: { row_letter: 'A', cell_number: 3, flat_number: 2 },
        related_cell: null,
        product: {
            id: 10,
            name: 'Widgets',
            ar_name: 'ودجات',
            image_url: null,
            boxes_count: 10,
        },
        pallet: { id: 55, expiration_date: '2026-09-01' },
        user: { id: 7, name: 'Jane Doe' },
        created_at: '2026-08-01T10:00:00Z',
        next_log_at: null,
        duration_seconds: 3600,
        flagged: false,
        flags: [],
        ...overrides,
    };
}
