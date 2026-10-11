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
