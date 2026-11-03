export interface StoreCustomer {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    company_name: string | null;
    is_rep: boolean;
    archived: boolean;
    banned: boolean;
    verified: boolean;
    important: boolean;
    activity_status: string | null;
    rep_id: string | null;
    rep_serial: string | null;
    package?: { id: number; name: string; percentage: string } | null;
    /** The stored all-time total, or the sum over the list's profit range. */
    profits?: number;
    /** Further list columns; absent when the viewer may not see them. */
    user_type?: string;
    country?: string | null;
    address?: string | null;
    state?: string | null;
    city?: string | null;
    postal_code?: string | null;
    company_address?: string | null;
    shipping_address?: string | null;
    member_serial?: string | null;
    AccSysID?: number | null;
    admin_notes?: string | null;
    registration_completed?: boolean;
    from_api?: boolean;
    email_verified_at?: string | null;
    admin_verified_at?: string | null;
    last_order_at?: string | null;
    created_at?: string | null;
    updated_at?: string | null;
    rep_discount_percentage?: number | null;
    /** Only with `edit_customer`. */
    tax_number?: string | null;
    trade_license?: string | null;
    bank_account_number?: string | null;
    balance?: string;
}
export interface CustomerDetails extends StoreCustomer {
    country: string | null;
    tax_number: string | null;
    bank_account_number: string | null;
    company_address: string | null;
    shipping_address: string | null;
    member_serial: string | null;
    AccSysID: number | null;
    trade_license: string | null;
    admin_notes: string | null;
    registration_completed: boolean;
}
export interface CustomerAddress {
    id: number;
    address: string;
    country_id: number;
    state_id: number | null;
    city_id: number | null;
    postal_code: string | null;
    phone: string;
    latitude: string | null;
    longitude: string | null;
    set_default: number;
}
export interface AddressOption {
    id: number;
    name: string;
}

export interface StoreCustomerPageView {
    id: number;
    kind: string;
    name: string | null;
    slug: string | null;
    public_url: string | null;
    view_count: number;
    last_viewed_at: string;
}
