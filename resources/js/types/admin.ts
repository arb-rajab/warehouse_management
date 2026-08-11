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

export interface CellPallet {
    id: number;
    product_name: string;
    product_image_url: string | null;
    expiration_date: string;
    added_at: string;
}

export interface Cell {
    id: number;
    cell_number: number;
    flat_number: number;
    state: 'empty' | 'full' | 'opened';
    pallet: CellPallet | null;
}

export interface CellSlotLocation {
    row_letter: string;
    cell_number: number;
    flat_number: number;
}

export type CellLogAction =
    'stored' | 'opened' | 'emptied' | 'transferred_out' | 'transferred_in';

export interface CellStatusLog {
    id: number;
    action: CellLogAction;
    from_state: Cell['state'];
    to_state: Cell['state'];
    note: string | null;
    cell: CellSlotLocation;
    related_cell: CellSlotLocation | null;
    product: {
        id: number;
        name: string;
        image_url: string | null;
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
}

export interface CellStatusLogFilters {
    product_id?: number;
    pallet_id?: number;
    row_id?: number;
    column_number?: number;
    user_id?: number;
    action?: CellLogAction;
    date_from?: string;
    date_to?: string;
}

export interface CellStatusLogFilterOptions {
    rows: { id: number; letter: string }[];
    maxColumnNumber: number;
    products: { id: number; name: string }[];
    users: { id: number; name: string }[];
    actions: CellLogAction[];
}
