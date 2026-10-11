/**
 * The store back office's shared props (HandleInertiaRequests::storeProps()),
 * present on every page under /temp. They hide sidebar links and buttons
 * only; every route checks its own permission.
 */
export interface StoreSharedProps {
    /** The current store language is written right to left. */
    rtl: boolean;
    /** The signed-in user's store permission names. */
    permissions: string[];
    /** Gate::before() grants a Super Admin every permission. */
    isSuperAdmin: boolean;
    /** The vendor's own account, which sees every switched-off section. */
    isRaizerAdmin: boolean;
    /** The business settings that switch sidebar sections on or off. */
    settings: Record<string, boolean>;
    counts: { sellers: number; tickets: number; conversations: number };
    notifications: { unread_count: number; items: StoreNotification[] };
}

/** RoleResource: a store role, named in the current language. */
export interface StoreRole {
    id: number;
    name: string;
}

/** StaffResource: a store back-office account. */
export interface StaffMember {
    id: number;
    user_id: number;
    name: string;
    email: string;
    phone: string | null;
    role: StoreRole | null;
    is_super_admin: boolean;
}

/** RoleController::permissionGroups(): one section of the role form. */
export interface PermissionGroup {
    section: string;
    label: string;
    permissions: { id: number; label: string }[];
}

/** A store language a role name can be translated into. */
export interface StoreLanguage {
    code: string;
    name: string;
}

export interface StoreNotification {
    id: string;
    kind: 'order' | 'cart';
    order_code: string | null;
    status: string | null;
    user_name: string | null;
    message: string | null;
    created_at: string | null;
}
export interface StoreOrderOption {
    id: number;
    name: string;
    AccSysID?: string | null;
    member_serial?: string | null;
    rep_discount_percentage?: number | null;
    serial?: string | null;
    mat_id?: string | null;
    unit_price?: number;
    unit_equal?: number;
    tax?: number;
    digital?: boolean;
    stocks?: { variant: string; price: number; qty: number }[];
}
export interface StoreOrderLine {
    id: number;
    product_id: number;
    name: string;
    serial: string | null;
    mat_id: string | null;
    quantity: number;
    price: number;
    unit_price: number;
    original_price: number;
    tax: number;
    shipping_cost: number;
    profits: number;
    variation: string | null;
    variation_qty: string | null;
    unit: string;
    info: string | null;
}
export interface StoreOrder {
    id: number;
    code: string;
    invoice_number: string | null;
    customer: { id: number; name: string; AccSysID: string | null } | null;
    representative: {
        id: number;
        name: string;
        rep_discount_percentage: number | null;
    } | null;
    editable: boolean;
    by_rep: boolean;
    delivery_date: string | null;
    delivery_status: string;
    payment_status: string;
    payment_type: string;
    grand_total: number;
    profits: number;
    discount_percent: number;
    indicator: number;
    manager_notes: string | null;
    created_at: string | null;
    details?: StoreOrderLine[];
}
