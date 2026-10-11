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

/** Blog categories retain their stored database names. */
export interface StoreBlogCategory {
    id: number;
    category_name: string;
}

export interface StoreSubscriber {
    id: number;
    email: string;
    created_at: string | null;
}

export interface StoreCoupon {
    id: number;
    type: 'product_base' | 'cart_base';
    code: string;
    discount: number;
    discount_type: 'amount' | 'percent';
    start_date: string;
    end_date: string;
    product_ids: number[];
    min_buy: number | null;
    max_discount: number | null;
}

export interface StoreCouponProductOption {
    id: number;
    name: string;
}

/** The kinds of file the store's uploader accepts (MultipartUploadService::KINDS). */
export type StoreUploadKind =
    'image' | 'video' | 'audio' | 'archive' | 'document';

/** FilterUploadsRequest::SORTS. */
export type StoreUploadSort = 'newest' | 'oldest' | 'smallest' | 'largest';

/** UploadResource: a file in the store's uploads library. */
export interface StoreUpload {
    id: number;
    /** The client's filename without its extension, when it had one. */
    name: string | null;
    extension: string | null;
    type: StoreUploadKind | null;
    /** In bytes. */
    size: number | null;
    url: string | null;
    created_at: string | null;
    /** Only on the library page, which loads the uploader's account. */
    uploaded_by?: string | null;
}

/** The uploaded-files library's filters, as the controller echoes them. */
export interface StoreUploadFilters {
    search: string | null;
    sort: StoreUploadSort;
    per_page: number;
}

export interface StoreCampaignProduct {
    id: number;
    name: string;
    price: number;
    discount: number;
    discount_type: 'amount' | 'percent';
}
export interface StoreCampaignRow {
    id: number;
    discount: number;
    discount_type: 'amount' | 'percent';
    quantity?: number;
}
export interface StoreCampaign {
    id: number;
    title: string;
    banner: string;
    start_date: string;
    end_date: string;
}
export interface StoreOffer extends StoreCampaign {
    offer_type: 'products_offer' | 'brand_offer';
    brand_id: number | null;
    is_active: boolean;
    is_repeatable: boolean;
    multiple_usage: boolean;
}
export interface StoreFlashDeal extends StoreCampaign {
    slug: string;
    background_color: string;
    text_color: 'dark' | 'light';
    status: boolean;
    featured: boolean;
    public_url: string;
}
export interface StoreCampaignFilters {
    search: string | null;
    per_page: number;
}

export interface StoreMarketingOption {
    id: number;
    name: string;
}
export interface StoreDynamicPopup {
    id: number;
    title: string;
    summary: string | null;
    banner: string;
    btn_text: string | null;
    delay_seconds: number;
    offer_id: number | null;
    show_page: number;
}
export interface StoreDynamicPopupSummary {
    id: number;
    title: string;
    banner_url: string | null;
    btn_link: string | null;
    status: boolean;
}
export interface StoreBlogSummary {
    id: number;
    title: string;
    category: { id: number; category_name: string } | null;
    status: boolean;
    public_url: string;
}
export interface StoreBlog extends StoreBlogSummary {
    slug: string;
    short_description: string | null;
    banner: string;
    description: string;
    meta_title: string;
    meta_img: string;
    meta_description: string;
    meta_keywords: string;
    category_id: number;
}
