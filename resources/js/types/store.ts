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

export interface StoreCountry {
    id: number;
    code: string;
    name: string;
    zone_id: number;
    active: boolean;
}

export interface StoreState {
    id: number;
    name: string;
    country_id: number;
    active: boolean;
    country?: StoreCountry | null;
}

export interface StoreCity {
    id: number;
    name: string;
    state_id: number;
    cost: string;
    active: boolean;
    state?: StoreState | null;
}

export interface StoreZone {
    id: number;
    name: string;
}

export interface StoreStaffOption {
    id: number;
    name: string;
}

export interface StorePickupPoint {
    id: number;
    staff_id: number;
    name: string;
    address: string;
    phone: string;
    active: boolean;
    manager: StoreStaffOption | null;
}

export interface StoreTax {
    id: number;
    name: string;
    active: boolean;
}

export interface StoreCurrency {
    id: number;
    name: string;
    symbol: string;
    code: string | null;
    exchange_rate: string;
    active: boolean;
}

export interface StoreCurrencySettings {
    system_default_currency: number;
    symbol_format: number;
    decimal_separator: number;
    no_of_decimals: number;
}

export interface StoreOrderProduct {
    id: number;
    name: string;
    mat_id: string;
}

export interface StoreOrderConfigurationSettings {
    minimum_order_amount_check: boolean;
    minimum_order_amount: string;
    default_specific_product: string;
    default_representative_check: boolean;
    default_representative_id: number;
}
