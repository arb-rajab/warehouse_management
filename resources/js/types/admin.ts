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

/**
 * The id/name pair a row, cell or pallet payload carries for the warehouse it
 * belongs to, and the option shape of the warehouse filter's search
 * (`WarehouseSummaryResource`). Narrower than `Warehouse`, which the
 * warehouses pages list.
 */
export interface WarehouseSummary {
    id: number;
    name: string;
}

/** A warehouse as the warehouses admin pages show it (`WarehouseResource`). */
export interface Warehouse extends WarehouseSummary {
    code: string | null;
    address: string | null;
    /** Present on the listing and the edit page; the delete button is offered only while it is 0. */
    rows_count?: number;
}

/**
 * Hydration data for a warehouse filter's already-selected ids (see
 * `FilterWarehouseSelect.vue`'s `selected` prop) — not every warehouse,
 * which the search endpoint fetches on demand.
 */
export interface WarehouseFilterOptions {
    warehouses: WarehouseSummary[];
}

/**
 * The `filters` prop of a page whose only filter is the warehouse one (the
 * warehouse map). `null` means every warehouse.
 */
export interface WarehouseFilters {
    warehouse_ids: number[] | null;
}

export interface Row {
    id: number;
    letter: string;
    cells_count: number;
    flats_count: number;
    has_pallets: boolean;
    warehouse: WarehouseSummary;
}

/** The rows listing's `filters` prop: the warehouse filter and the page size. */
export interface RowFilters {
    warehouse_ids?: number[];
    per_page?: number;
}

/**
 * The persisted, admin-editable size every QR-label export uses — see
 * App\Models\Setting.
 */
export interface Setting {
    qr_code_width: number;
    qr_code_height: number;
    allowed_open_levels: number[];
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

/**
 * One row of a row dropdown. `warehouse` is present only for a letter that
 * more than one warehouse uses (`Row::filterOptions()`), so the label can tell
 * the two apart — see `rowOptionLabel()` in `lib/location.ts`.
 */
export interface RowFilterOption {
    id: number;
    letter: string;
    warehouse?: WarehouseSummary;
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
    /** The row's warehouse, which a transfer to this row names (a letter alone is unique per warehouse only). */
    warehouse_id: number;
}

/**
 * Where an admin pallet transfer can go (`BuildsPalletTransferTargets`): every
 * warehouse, and every row of every warehouse. The transfer fields list the
 * rows of the chosen warehouse.
 */
export interface PalletTransferTargets {
    warehouses: WarehouseSummary[];
    rows: CellMapRow[];
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
    /** False once the product is no longer sold (unpublished in the store). */
    active: boolean;
}

export interface ProductFilterOption {
    id: number;
    /** The store's raw base name — see `CellPallet.product_name`. */
    name: string;
    /** The store's raw Arabic name — see `CellPallet.product_ar_name`. */
    ar_name: string;
    active: boolean;
}

/**
 * Hydration data for a product filter's already-selected ids (see
 * `FilterProductSelect.vue`'s `selected` prop) — not the full product
 * catalog, which is fetched on demand via the search endpoint instead.
 */
export interface ProductFilterOptions {
    products: ProductFilterOption[];
}

export interface CellSlotLocation {
    row_letter: string;
    cell_number: number;
    flat_number: number;
    /**
     * The warehouse of the cell's row, present in the admin listings. A row
     * letter names one row per warehouse, so a link to the row carries
     * `warehouse.id` (`rowRouteOptions()` in `lib/location.ts`).
     */
    warehouse?: WarehouseSummary;
}

export type CellLogAction =
    | 'stored'
    | 'opened'
    | 'boxes_removed'
    | 'emptied'
    | 'transferred_out'
    | 'transferred_in'
    | 'deactivated'
    | 'reactivated'
    | 'product_changed';

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
    row_id?: number[];
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
    warehouse_ids?: number[];
    per_page?: number;
}

export interface CellStatusLogFilterOptions
    extends
        RowAndColumnFilterOptions,
        ProductFilterOptions,
        Partial<WarehouseFilterOptions> {
    users: UserSummary[];
    actions: CellLogAction[];
}

/**
 * One row of the products page's Barcodes dialog: a `product_barcodes` row, the
 * table the WMS shares with the store. The product's own-id rows (the import's
 * Mat_ID) are left out, since the default barcode already is the id.
 */
export interface ProductBarcode {
    id: number;
    barcode: string;
    /**
     * Whether Code 128 can draw the value (Arabic digits converted). Store
     * barcodes can be any text; preview and download answer 422 when false.
     */
    printable: boolean;
}

export interface ProductSummary {
    image_verification: 'matches' | 'doesnt_match' | 'image_missing' | null;
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
    /**
     * How many pallets of this product should be in the warehouse at
     * minimum, or `null` when nobody has configured one — such a product
     * never counts as low-stock, whatever `pallets_count` is.
     */
    minimum_pallets: number | null;
    /** How many pallets of this product are currently in the warehouse, across every cell state — not just full/opened. */
    pallets_count: number;
    full_cells_count: number;
    opened_cells_count: number;
    expired_cells_count: number;
    expiring_soon_count: number;
    /** Barcodes attached to this product, oldest first, from the shared `product_barcodes` table (the own-id rows are left out). */
    barcodes: ProductBarcode[];
}

export type ProductSortBy =
    | 'id'
    | 'name'
    | 'full_cells_count'
    | 'opened_cells_count'
    | 'expired_cells_count'
    | 'expiring_soon_count';

export interface ProductImageReviewDetails {
    id: number;
    name: string;
    ar_name: string;
    image_url: string | null;
    image_source: string;
    barcodes: string[];
    reviews: {
        data: {
            id: number;
            status: string;
            note: string | null;
            image_url: string | null;
            user: { id: number; name: string };
            created_at: string;
        }[];
        current_page: number;
        last_page: number;
    };
}

export interface ProductFilters {
    image_verification?: string;
    state?: Cell['state'];
    expired?: boolean;
    expires_within_days?: number;
    inactive?: boolean;
    hide_inactive?: boolean;
    low_stock?: boolean;
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

/**
 * One row of the "matching pallets" admin list (Admin\PalletController::index) —
 * where the dashboard's stat tiles and a product's occupancy counts land.
 * Carries the full `ProductDetails` (the list spans every product, unlike a
 * row's per-cell `CellPallet`) plus `added_at`, the list's sort tiebreaker
 * after `expiration_date`.
 */
export interface MatchingPallet {
    id: number;
    /** False for a received pallet that has no cell yet. */
    placed: boolean;
    /** Null for an unplaced pallet. */
    state: Cell['state'] | null;
    expiration_date: string | null;
    added_at: string | null;
    remaining_boxes: number;
    product: ProductDetails;
    /** Null for an unplaced pallet: it has no map location. */
    location: CellSlotLocation | null;
    /** False when the pallet's cell is out of service; null when unplaced. */
    cell_active: boolean | null;
    /** The receipt the pallet arrived on (kept after it is placed). */
    receipt_id: number | null;
    /** The warehouse of the receipt, for an unplaced pallet only. */
    warehouse: WarehouseSummary | null;
}

/**
 * The highlight-filter query params a dashboard tile or a product's occupancy
 * count links to the matching pallets list with.
 */
export interface MatchingPalletFilters {
    state?: Cell['state'];
    is_active?: boolean;
    expired?: boolean;
    unplaced?: boolean;
    expires_within_days?: number;
    stale_after_days?: number;
    product_id?: number[];
    warehouse_ids?: number[];
    per_page?: number;
}

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
    /** Whether the report's round is finished — absent where the round isn't loaded (the mobile report response). */
    round_completed?: boolean;
    /** Set once an admin accepted the reported state; the discrepancy itself stays as reported. */
    accepted_at: string | null;
    accepted_by: UserSummary | null;
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
    warehouse_ids?: number[];
    per_page?: number;
}

export interface CellVerificationRoundFilterOptions extends WarehouseFilterOptions {
    users: UserSummary[];
}

/**
 * Filters for one round's own reports listing
 * (Admin\CellVerificationRoundController::show()) — the round itself is
 * scoped by the route, not a filter field here.
 */
export interface CellVerificationReportFilters {
    cell_id?: number;
    row_id?: number[];
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

/** A truck receipt's state: a draft until it is finalized into pallets. */
export type ReceiptStatus = 'draft' | 'finalized';

/** One row of the receipts list (`ReceiptResource` without its files and pallets). */
export interface ReceiptListItem {
    id: number;
    warehouse: WarehouseSummary;
    note: string | null;
    status: ReceiptStatus;
    pallets_count: number;
    files_count: number;
    created_by: UserSummary;
    finalized_by: UserSummary | null;
    finalized_at: string | null;
    created_at: string;
}

/**
 * A bill photo or PDF. `url` is the session-authenticated admin download
 * route; the storage path is never sent.
 */
export interface ReceiptFile {
    id: number;
    original_name: string;
    mime_type: string;
    /** Bytes stored. */
    size: number;
    url: string;
    created_at: string | null;
}

/**
 * One pallet exactly as the merchant wrote it on the bill, in the order it was
 * typed. Carries no pallet id and no entry id.
 */
export interface ReceiptPalletEntry {
    product_id: number;
    product: ProductDetails;
    boxes: number;
    expiration_date: string | null;
}

export interface ReceiptDetail extends ReceiptListItem {
    files: ReceiptFile[];
    pallets: ReceiptPalletEntry[];
}

export interface ReceiptFilters {
    warehouse_ids?: number[];
    status?: ReceiptStatus;
    sort_direction?: 'asc' | 'desc';
    per_page?: number;
}

export type ReceiptFilterOptions = WarehouseFilterOptions;
