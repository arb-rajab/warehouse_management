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

export interface StoreReferenceOption {
    id: number;
    name: string;
}
export interface StoreCountry extends StoreReferenceOption {
    code: string;
    status: boolean;
}
export interface StoreState extends StoreReferenceOption {
    country_id: number;
    country: StoreReferenceOption | null;
    status: boolean;
}
export interface StoreCity extends StoreReferenceOption {
    state_id: number;
    state: StoreReferenceOption | null;
    cost: string;
    status: boolean;
}
export interface StoreZone extends StoreReferenceOption {
    status: boolean;
}
export interface StorePickupPoint extends StoreReferenceOption {
    address: string;
    phone: string;
    staff_id: number;
    staff: StoreReferenceOption | null;
    pick_up_status: boolean;
}
export interface StoreTax extends StoreReferenceOption {
    tax_status: boolean;
}
export interface StoreCurrency extends StoreReferenceOption {
    symbol: string;
    code: string;
    exchange_rate: string;
    status: boolean;
}
export interface OrderConfigurationProduct {
    id: number;
    name: string;
    mat_id: string;
}

/** The narrow database language shape used by administration. */
export interface AdminLanguage {
    id: number;
    name: string;
    code: string;
    app_lang_code: string | null;
    rtl: boolean;
    status: boolean;
    is_default: boolean;
    is_english: boolean;
}

export interface StoreTranslation {
    id: number;
    key: string;
    source: string;
    value: string | null;
}

export interface StoreUpload {
    id: number;
    name: string | null;
    type: string | null;
    size: number | null;
    url: string | null;
}
export interface StoreProfile {
    id: number;
    name: string;
    email: string;
    avatar: StoreUpload | null;
}

export interface ClassifiedProduct {
    id: number;
    name: string;
    public_url: string;
    added_by: string;
    status: boolean;
    published: boolean;
    thumbnail_url: string | null;
}

export interface StoreOffersCatalog {
    id: number;
    created_at: string | null;
}

export interface StoreReview {
    id: number;
    rating: number;
    comment: string;
    published: boolean;
    product: { id: number; name: string; owner: string; url: string };
    customer: { id: number; name: string; email: string };
}

export interface StoreBrandDiscount {
    id: number;
    name: string;
    logo_url: string | null;
}
