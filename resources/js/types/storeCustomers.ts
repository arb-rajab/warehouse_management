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
