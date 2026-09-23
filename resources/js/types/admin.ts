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

/**
 * The id/name pair every listing carries for the user who did something, and
 * that `User::filterOptions()` returns for a "done by" dropdown. Narrower than
 * `User` on purpose — these payloads never include the email or admin flag.
 */
export interface UserSummary {
    id: number;
    name: string;
}

export interface Row {
    id: number;
    letter: string;
    cells_count: number;
    flats_count: number;
    has_pallets: boolean;
}

/**
 * The persisted, admin-editable size every QR-label export uses — see
 * App\Models\Setting.
 */
export interface Setting {
    qr_code_width: number;
    qr_code_height: number;
}

/**
 * The `filters` prop shape for an admin listing whose only server-driven
 * state is its page size — Rows/Index.vue, Users/Index.vue.
 * A listing with its own filters/sort embeds `per_page` directly into its
 * own filters interface (see ProductFilters, CellStatusLogFilters) instead
 * of extending this.
 */
export interface PerPageFilters {
    per_page?: number;
}

/**
 * Users/Show.vue's filters — it renders two independently paginated tables
 * (the user's cell-status-log actions and their cell-verification reports),
 * each with its own page size so changing one doesn't reset the other.
 */
export interface UserShowFilters {
    per_page?: number;
    reports_per_page?: number;
}

export interface CellPallet {
    id: number;
    product_id: number;
    /**
     * The store's raw base name, straight from `products.name`. The backend
     * resolves no label: pass this and `product_ar_name` through
     * `lib/productName.ts`, which picks by the active locale, wherever a
     * pallet's product is rendered.
     */
    product_name: string;
    /**
     * The store's raw `products.ar_name`. NOT NULL upstream, so a product the
     * store never translated carries `''` — hence `productName()`'s fallback
     * to `product_name` rather than a null check.
     */
    product_ar_name: string;
    product_image_url: string | null;
    expiration_date: string | null;
    /**
     * Null-safe because `Pallet::toMapSummaryArray()` derives it from the
     * model's nullable `created_at` (`$this->created_at?->toIso8601String()`).
     */
    added_at: string | null;
    is_stale: boolean | null;
    remaining_boxes: number;
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

/**
 * What `ProductResource` emits wherever a payload embeds the product itself
 * rather than just an option label — the log listings and the verification
 * snapshots.
 */
export interface ProductDetails {
    id: number;
    /** The store's raw base name — see `CellPallet.product_name`. */
    name: string;
    /** The store's raw Arabic name — see `CellPallet.product_ar_name`. */
    ar_name: string;
    image_url: string | null;
    boxes_count: number;
}

export interface ProductFilterOption {
    id: number;
    /** The store's raw base name — see `CellPallet.product_name`. */
    name: string;
    /** The store's raw Arabic name — see `CellPallet.product_ar_name`. */
    ar_name: string;
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
    staleAfterDays: number | null;
    expired: boolean;
    inactive: boolean;
}

/**
 * The pallet detail the cell map carries for every flat — `CellPallet` minus
 * `is_stale`, which only the current flat's fully-loaded cells compute. Shared
 * by the 2D map's per-flat highlight samples and the 3D map's items, which are
 * built from those same samples.
 */
export type CellPalletSummary = Pick<
    CellPallet,
    | 'id'
    | 'product_id'
    | 'product_name'
    | 'product_ar_name'
    | 'product_image_url'
    | 'expiration_date'
    | 'added_at'
    | 'remaining_boxes'
>;

/**
 * The minimal per-cell data the warehouse map loads for every flat (not just
 * the one on screen) to compute a highlight-match count per flat tab, order
 * matches for next/previous-match navigation, and (via the 3D map's "faced
 * cell" panel) show the same product/date detail the 2D grid shows, and let
 * that panel drive real pallet actions/toggle-active via `cell_id`/
 * `pallet.id`/`pallet.remaining_boxes`.
 */
export interface CellHighlightSample {
    cell_id: number;
    row_letter: string;
    cell_number: number;
    flat_number: number;
    state: Cell['state'];
    is_active: boolean;
    pallet: CellPalletSummary | null;
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
 * detail panel — including `cellId`/`pallet.id`/`pallet.remaining_boxes` so
 * that panel's manage-pallet/toggle-active buttons can drive the same
 * `PalletActionsDialog`/`ToggleCellActiveDialog` the 2D grid uses.
 */
export interface CellMap3DItem {
    cellId: number;
    cellNumber: number;
    flatNumber: number;
    state: Cell['state'];
    isActive: boolean;
    highlighted: boolean;
    dimmed: boolean;
    pulsing: boolean;
    pallet: CellPalletSummary | null;
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
    acknowledged_by?: UserSummary | null;
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
    product: ProductDetails | null;
    pallet: {
        id: number;
        expiration_date: string | null;
    } | null;
    user: UserSummary;
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
    users: UserSummary[];
    actions: CellLogAction[];
}

export interface ProductSummary {
    id: number;
    /** The store's raw base name — see `CellPallet.product_name`. */
    name: string;
    /** The store's raw Arabic name — see `CellPallet.product_ar_name`. */
    ar_name: string;
    image_url: string | null;
    active: boolean;
    /**
     * How many boxes a full pallet of this product holds. Stored in this app's
     * own `wms_product_settings`, not on the store-owned products table, and
     * falls back to 1 for a product nobody has configured yet.
     */
    boxes_count: number;
    full_cells_count: number;
    opened_cells_count: number;
    expired_cells_count: number;
    expiring_soon_count: number;
}

export type ProductSortBy =
    | 'name'
    | 'full_cells_count'
    | 'opened_cells_count'
    | 'expired_cells_count'
    | 'expiring_soon_count';

export interface ProductFilters {
    state?: Cell['state'];
    expired?: boolean;
    expires_within_days?: number;
    inactive?: boolean;
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

export interface CellVerificationSnapshot {
    cell_state: Cell['state'] | null;
    product: ProductDetails | null;
    boxes_count: number | null;
    expiration_date: string | null;
}

export interface CellVerificationReport {
    id: number;
    cell_verification_round_id: number;
    is_correct: boolean;
    cell: CellSlotLocation;
    expected: CellVerificationSnapshot;
    reported: CellVerificationSnapshot;
    note: string | null;
    user: UserSummary;
    created_at: string;
}

export interface CellVerificationRound {
    id: number;
    started_at: string;
    completed_at: string | null;
    reports_count?: number;
    reports?: CellVerificationReport[];
    /**
     * The rows this round covers — a round walks a subset of the warehouse,
     * never implicitly all of it. Same `{id, letter}` shape the row filter
     * dropdowns consume, so it reuses that type rather than restating it.
     */
    rows?: RowFilterOption[];
    user?: UserSummary;
}

export interface CellVerificationRoundFilters {
    user_id?: number[];
    completed?: boolean;
    date_from?: string;
    date_to?: string;
    created_within_days?: number;
    sort_direction?: 'asc' | 'desc';
    per_page?: number;
}

export interface CellVerificationRoundFilterOptions {
    users: UserSummary[];
}

/**
 * Filters for one round's own reports listing
 * (Admin\CellVerificationRoundController::show()) — the round itself is
 * scoped by the route, not a filter field here.
 */
export interface CellVerificationReportFilters {
    cell_id?: number;
    row_id?: number;
    column_number?: number;
    product_id?: number[];
    is_correct?: boolean;
    date_from?: string;
    date_to?: string;
    created_within_days?: number;
    sort_direction?: 'asc' | 'desc';
    per_page?: number;
}

export type CellVerificationReportFilterOptions = RowAndColumnFilterOptions &
    ProductFilterOptions;
