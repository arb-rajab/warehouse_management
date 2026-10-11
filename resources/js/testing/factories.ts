import type {
    Cell,
    CellPallet,
    CellWithLocation,
    CellStatusLog,
    CellVerificationReport,
    CellVerificationRound,
    MatchingPallet,
    Paginated,
    Row,
    Setting,
    User,
} from '@/types/admin';
import type {
    PermissionGroup,
    StaffMember,
    StoreBlogCategory,
    StoreCoupon,
    StoreSubscriber,
    StoreRole,
    StoreSharedProps,
    StoreUpload,
    StoreOffer,
    StoreFlashDeal,
    StoreCampaignProduct,
} from '@/types/store';

/**
 * Shared `Paginated<T>` test fixture — every page test that mounts a paginated
 * table built this `meta` shape independently before this existed.
 */
export function paginated<T>(
    data: T[],
    perPage = 20,
    { currentPage = 1, lastPage = 1 } = {},
): Paginated<T> {
    return {
        data,
        meta: {
            current_page: currentPage,
            last_page: lastPage,
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

export function cellWithLocation(
    overrides: Partial<CellWithLocation> = {},
): CellWithLocation {
    return { ...cell(), row_letter: 'A', ...overrides };
}

export function cellPallet(overrides: Partial<CellPallet> = {}): CellPallet {
    return {
        id: 42,
        product_id: 1,
        product_name: 'Widgets',
        product_ar_name: 'ودجات',
        product_image_url: null,
        expiration_date: '2026-12-01',
        added_at: '2026-01-01',
        is_stale: false,
        remaining_boxes: 10,
        ...overrides,
    };
}

export function setting(overrides: Partial<Setting> = {}): Setting {
    return {
        qr_code_width: 240,
        qr_code_height: 240,
        allowed_open_levels: [1],
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
        round_completed: true,
        accepted_at: null,
        accepted_by: null,
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

export function matchingPallet(
    overrides: Partial<MatchingPallet> = {},
): MatchingPallet {
    return {
        id: 1,
        state: 'full',
        expiration_date: '2026-09-01',
        added_at: '2026-08-01T10:00:00Z',
        remaining_boxes: 10,
        product: {
            id: 10,
            name: 'Widgets',
            ar_name: 'ودجات',
            image_url: null,
            boxes_count: 10,
        },
        location: { row_letter: 'A', cell_number: 3, flat_number: 2 },
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

/** The store back office's shared `store` prop, for its layout and pages. */
export function storeSharedProps(
    overrides: Partial<StoreSharedProps> = {},
): StoreSharedProps {
    return {
        rtl: false,
        permissions: [],
        isSuperAdmin: false,
        isRaizerAdmin: false,
        settings: {},
        ...overrides,
    };
}

export function storeRole(overrides: Partial<StoreRole> = {}): StoreRole {
    return { id: 3, name: 'Clerk', ...overrides };
}

export function staffMember(overrides: Partial<StaffMember> = {}): StaffMember {
    return {
        id: 11,
        user_id: 21,
        name: 'Sam Staff',
        email: 'sam@example.com',
        phone: '0612345678',
        role: storeRole(),
        is_super_admin: false,
        ...overrides,
    };
}

export function permissionGroup(
    overrides: Partial<PermissionGroup> = {},
): PermissionGroup {
    return {
        section: 'staff',
        label: 'Staff',
        permissions: [
            { id: 1, label: 'View All Staffs' },
            { id: 2, label: 'Add Staff' },
        ],
        ...overrides,
    };
}

export function storeBlogCategory(
    overrides: Partial<StoreBlogCategory> = {},
): StoreBlogCategory {
    return { id: 4, category_name: 'Recipes', ...overrides };
}

export function storeSubscriber(
    overrides: Partial<StoreSubscriber> = {},
): StoreSubscriber {
    return {
        id: 5,
        email: 'reader@example.com',
        created_at: '2026-10-08',
        ...overrides,
    };
}

export function storeCoupon(overrides: Partial<StoreCoupon> = {}): StoreCoupon {
    return {
        id: 6,
        type: 'cart_base',
        code: 'SPRING',
        discount: 10,
        discount_type: 'percent',
        start_date: '2026-10-01',
        end_date: '2026-10-31',
        product_ids: [],
        min_buy: 20,
        max_discount: 5,
        ...overrides,
    };
}

/** UploadResource: a stored PNG, as the uploads library lists it. */
export function storeUpload(overrides: Partial<StoreUpload> = {}): StoreUpload {
    return {
        id: 31,
        name: 'shop front',
        extension: 'png',
        type: 'image',
        size: 2048,
        url: 'http://localhost/uploads/all/shop-front.png',
        created_at: '2026-10-01T09:30:00+00:00',
        uploaded_by: 'Sam Staff',
        ...overrides,
    };
}

export function storeOffer(overrides: Partial<StoreOffer> = {}): StoreOffer {
    return {
        id: 1,
        title: 'Product offer',
        banner: '',
        start_date: '2026-10-01T08:09:11',
        end_date: '2026-10-30T09:08:07',
        offer_type: 'products_offer',
        brand_id: null,
        is_active: true,
        is_repeatable: false,
        multiple_usage: false,
        ...overrides,
    };
}
export function storeFlashDeal(
    overrides: Partial<StoreFlashDeal> = {},
): StoreFlashDeal {
    return {
        id: 2,
        title: 'Flash deal',
        slug: 'flash-deal',
        banner: '',
        start_date: '2026-10-01T08:09:11',
        end_date: '2026-10-30T09:08:07',
        background_color: '#ffffff',
        text_color: 'dark',
        status: true,
        featured: false,
        public_url: '/temp/flash-deal/flash-deal',
        ...overrides,
    };
}
export function storeCampaignProduct(
    overrides: Partial<StoreCampaignProduct> = {},
): StoreCampaignProduct {
    return {
        id: 12,
        name: 'Translated product',
        price: 100,
        discount: 10,
        discount_type: 'amount',
        ...overrides,
    };
}
