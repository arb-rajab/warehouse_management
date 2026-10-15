/** What a slot holds: `full` is the app's "sealed". */
export type SlotState = 'empty' | 'full' | 'opened';

/** The state chips of the filter bar. */
export type StateFilter = 'all' | SlotState;

/** One cell of `GET /rows/full`, as the server sends it. */
export interface ApiPallet {
    id: number;
    product_id: number;
    expiration_date: string | null;
    product_name?: string | null;
    product_ar_name?: string | null;
    product_image_url?: string | null;
    remaining_boxes?: number | null;
    product_boxes_count?: number | null;
    added_at?: string | null;
}

export interface ApiCell {
    id: number;
    row_letter?: string;
    cell_number: number;
    flat_number: number;
    state: string;
    is_active?: boolean;
    pallet?: ApiPallet | null;
}

export interface ApiRow {
    id: number;
    letter?: string;
    row_letter?: string;
    cells?: ApiCell[] | null;
}

export interface Pallet {
    id: number;
    productId: number;
    expirationDate: string | null;
    productName: string;
    productArName: string;
    productImageUrl: string | null;
    remainingBoxes: number | null;
    productBoxesCount: number | null;
}

/** One shelf position: row letter + column + level. */
export interface Slot {
    id: number;
    row: string;
    rowIdx: number;
    col: number;
    level: number;
    state: SlotState;
    /** False when the slot is out of service. */
    active: boolean;
    pallet: Pallet | null;
    /** `A5- 2`, see SlotCode. */
    code: string;
    /** Lower-cased product names (both languages), what the search matches against. */
    haystack: string;
    /** Whole days until the pallet expires (negative once expired), null without an expiry. */
    expiresInDays: number | null;
}

export interface WarehouseRow {
    id: number;
    letter: string;
    idx: number;
}

export interface Warehouse {
    rows: WarehouseRow[];
    slots: Slot[];
    /** The widest row: rows may have different column counts. */
    cols: number;
    levels: number;
    byId: Map<number, Slot>;
    /** `A|5` -> the slots of that column, lowest level first. */
    byColumn: Map<string, Slot[]>;
}

export interface SlotFilter {
    terms: string[];
    state: StateFilter;
    expiring: boolean;
}

export interface MatchIndex {
    filtering: boolean;
    ids: ReadonlySet<number>;
    count: number;
    byRow: ReadonlyMap<string, number>;
    byLevel: ReadonlyMap<number, number>;
}

export type Locale = 'en' | 'ar';
