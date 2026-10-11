import type { PerPageFilters } from '@/types/admin';

export interface StoreReportFilters extends PerPageFilters {
    category_ids?: string[];
    verification_statuses?: string[];
    seller_ids?: string[];
    user_ids?: string[];
    date_from?: string | null;
    date_to?: string | null;
    created_within_days?: number | null;
}

export type StoreReportSelection =
    'category_ids' | 'verification_statuses' | 'seller_ids' | 'user_ids';
export interface StoreReportOption {
    value: string;
    label: string;
}
export interface StoreProductReportRow {
    id: number;
    name: string;
    value: number;
}
export interface StoreSellerReportRow {
    id: number;
    seller_name: string | null;
    shop_name: string | null;
    num_of_sale: number;
    order_amount: string;
}
export interface StoreSearchReportRow {
    id: number;
    query: string;
    count: number;
}
export interface StoreCommissionReportRow {
    id: number;
    order_code: string | null;
    admin_commission: string;
    seller_earning: string;
    created_at: string;
}
export interface StoreWalletReportRow {
    id: number;
    user_name: string | null;
    amount: string;
    payment_method: string | null;
    created_at: string;
}
