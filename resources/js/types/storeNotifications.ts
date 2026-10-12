export interface CustomerNotification {
    id: number;
    title: string;
    status: string;
    customer: { id: number; name: string } | null;
}

export interface NotificationCustomer {
    id: number;
    name: string;
    account_id: number | null;
}

export interface AdminNotification {
    id: string;
    kind: 'order' | 'cart' | 'other';
    order_code: string | null;
    status: string | null;
    customer_name: string | null;
    message: string | null;
    url: string | null;
    created_at: string | null;
}
