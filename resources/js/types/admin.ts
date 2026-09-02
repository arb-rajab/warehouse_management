export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: PaginationLink[];
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
}

export interface Row {
    id: number;
    letter: string;
    cells_count: number;
    flats_count: number;
    has_pallets: boolean;
}

/**
 * The `filters` prop shape for an admin listing whose only server-driven
 * state is its page size — Rows/Index.vue, Users/Index.vue, Users/Show.vue.
 * A listing with its own filters/sort embeds `per_page` directly into its
 * own filters interface (see ProductFilters, CellStatusLogFilters) instead
 * of extending this.
 */
export interface PerPageFilters {
    per_page?: number;
}

export interface CellPallet {
    id: number;
    product_id: number;
    product_name: string;
    product_image_url: string | null;
    expiration_date: string;
    added_at: string;
    is_stale: boolean | null;
}

export interface Cell {
    id: number;
    cell_number: number;
    flat_number: number;
    state: 'empty' | 'full' | 'opened';
    is_active: boolean;
    pallet: CellPallet | null;
}

export interface CellWithLocation extends Cell {
    row_letter: string;
}

export interface RowFilterOption {
    id: number;
    letter: string;
}

export interface RowAndColumnFilterOptions {
    rows: RowFilterOption[];
    maxColumnNumber: number;
}

export interface CellMapRow {
    id: number;
    letter: string;
    cells_count: number;
    flats_count: number;
}

export interface ProductFilterOption {
    id: number;
    name: string;
}

/**
 * Hydration data for a product filter's already-selected ids (see
 * `FilterProductSelect.vue`'s `selected` prop) — not the full product
 * catalog, which is fetched on demand via the search endpoint instead.
 */
export interface ProductFilterOptions {
    products: ProductFilterOption[];
}

export interface CellHighlightSeed {
    state: Cell['state'] | null;
    productIds: number[];
    expiresWithinDays: number | null;
    expired: boolean;
    inactive: boolean;
}

/**
 * The minimal per-cell data the warehouse map loads for every flat (not just
 * the one on screen) to compute a highlight-match count per flat tab, order
 * matches for next/previous-match navigation, and (via the 3D map's "faced
 * cell" panel) show the same product/date detail the 2D grid shows — narrower
 * than `Cell` only in that it skips the cell's own id.
 */
export interface CellHighlightSample {
    row_letter: string;
    cell_number: number;
    flat_number: number;
    state: Cell['state'];
    is_active: boolean;
    pallet: Pick<
        CellPallet,
        | 'product_id'
        | 'product_name'
        | 'product_image_url'
        | 'expiration_date'
        | 'added_at'
    > | null;
}

export interface CellSlotLocation {
    row_letter: string;
    cell_number: number;
    flat_number: number;
}

/**
 * One cell's worth of data for the 3D warehouse map (CellMap3D.vue) — built
 * from `CellHighlightSample` (all flats, already loaded for the 2D map's
 * per-flat match badges) rather than the current-flat-only `Cell`/
 * `CellWithLocation`, since the 3D view renders every flat at once. Carries
 * the same pallet detail `CellSlot.vue` shows in 2D, for the 3D "faced cell"
 * detail panel.
 */
export interface CellMap3DItem {
    cellNumber: number;
    flatNumber: number;
    state: Cell['state'];
    isActive: boolean;
    highlighted: boolean;
    pulsing: boolean;
    pallet: Pick<
        CellPallet,
        'product_name' | 'product_image_url' | 'expiration_date' | 'added_at'
    > | null;
}

export interface CellMap3DBand {
    letter: string;
    items: CellMap3DItem[];
}

export type CellLogAction =
    | 'stored'
    | 'opened'
    | 'boxes_removed'
    | 'emptied'
    | 'transferred_out'
    | 'transferred_in'
    | 'deactivated'
    | 'reactivated';

export type CellLogFlagReason = 'rapid_actions' | 'off_hours' | 'quick_flip';

export interface CellStatusLogFlag {
    id: number;
    reason: CellLogFlagReason;
    acknowledged: boolean;
}

export interface CellStatusLog {
    id: number;
    action: CellLogAction;
    from_state: Cell['state'];
    to_state: Cell['state'];
    note: string | null;
    boxes_count: number | null;
    cell: CellSlotLocation;
    related_cell: CellSlotLocation | null;
    product: {
        id: number;
        name: string;
        image_url: string | null;
        boxes_count: number;
    } | null;
    pallet: {
        id: number;
        expiration_date: string | null;
    } | null;
    user: {
        id: number;
        name: string;
    };
    created_at: string;
    next_log_at: string | null;
    duration_seconds: number;
    flagged: boolean;
    flags: CellStatusLogFlag[];
}

export type CellStatusLogSortBy = 'created_at' | 'expiration_date';

export interface CellStatusLogFilters {
    product_id?: number[];
    pallet_id?: number;
    row_id?: number;
    column_number?: number;
    user_id?: number[];
    action?: CellLogAction[];
    date_from?: string;
    date_to?: string;
    created_within_days?: number;
    expiration_date_from?: string;
    expiration_date_to?: string;
    expires_within_days?: number;
    sort_by?: CellStatusLogSortBy;
    sort_direction?: 'asc' | 'desc';
    flagged?: boolean;
    per_page?: number;
}

export interface CellStatusLogFilterOptions
    extends RowAndColumnFilterOptions, ProductFilterOptions {
    users: { id: number; name: string }[];
    actions: CellLogAction[];
}

export interface ProductSummary {
    id: number;
    name: string;
    image_url: string | null;
    full_cells_count: number;
    opened_cells_count: number;
    expired_cells_count: number;
    expiring_soon_count: number;
    activity_today_count: number;
    activity_week_count: number;
}

export type ProductSortBy =
    | 'name'
    | 'full_cells_count'
    | 'opened_cells_count'
    | 'expired_cells_count'
    | 'expiring_soon_count'
    | 'activity_today_count'
    | 'activity_week_count';

export interface ProductFilters {
    row_id?: number;
    column_number?: number;
    state?: Cell['state'];
    expired?: boolean;
    expires_within_days?: number;
    product_id?: number[];
    user_id?: number[];
    action?: CellLogAction[];
    date_from?: string;
    date_to?: string;
    created_within_days?: number;
    sort_by?: ProductSortBy;
    sort_direction?: 'asc' | 'desc';
    per_page?: number;
}

export type ProductIndexFilterOptions = CellStatusLogFilterOptions;
