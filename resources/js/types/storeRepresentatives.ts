export interface RepresentativePackage {
    id: number;
    name: string;
    percentage: string;
}

export interface RepresentativePayment {
    id: string;
    amount: string;
    receipt_code: string;
    invoice_code: number | null;
    notes: string | null;
    status: 'pending' | 'accepted' | 'rejected';
    date: string;
    customer_name: string | null;
    account_id: number | null;
    representative: { id: number; name: string } | null;
    accountant: { id: number; name: string } | null;
}

export interface RepresentativeVisit {
    id: number;
    note: string | null;
    visit_date: string | null;
    has_order: boolean;
    representative: { id: number; name: string } | null;
    customer: { id: number; name: string } | null;
}

/** VisitController::mapMarkers: rank is one-based; coordinates retain zero. */
export interface RepresentativeVisitMarker {
    id: number;
    lat: number;
    lng: number;
    rank: number;
    has_order: boolean;
    label: string;
}

export interface RepresentativeVisitDetail {
    id: number;
    note: string | null;
    visit_date: string | null;
    representative: { id: number; name: string } | null;
    customer: {
        id: number;
        name: string;
        company_name: string | null;
        email: string | null;
        phone: string | null;
        member_serial: string | null;
        accounting_id: string | null;
    } | null;
    order: {
        id: number;
        code: string;
        delivery_status: string;
        date: number;
        amount: string;
        payment_type: string;
        customer_notes: string | null;
        customer_otajer_id: string | null;
        delivery_date: string | null;
    } | null;
}
