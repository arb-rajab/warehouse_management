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
export interface StoreCarrierRange {
    billing_type: 'weight_based' | 'price_based';
    delimiter1: string;
    delimiter2: string;
    prices: Record<string, string>;
}
export interface StoreCarrier extends StoreReferenceOption {
    transit_time: string;
    logo: number | null;
    free_shipping: boolean;
    status: boolean;
    ranges?: StoreCarrierRange[];
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

export interface StoreWebsiteHeaderSettings {
    header_logo: string;
    show_language_switcher: boolean;
    show_currency_switcher: boolean;
    header_stikcy: boolean;
    topbar_banner: string;
    topbar_banner_medium: string;
    topbar_banner_small: string;
    topbar_banner_link: string;
    helpline_number: string;
    header_menu_labels: string[];
    header_menu_links: string[];
}

/** Narrow back-office support resources; message text is always escaped by Vue. */
export interface StoreConversation {
    id: number;
    title: string | null;
    sender: { id: number; name: string } | null;
    receiver: { id: number; name: string } | null;
    sender_viewed: boolean;
    receiver_viewed: boolean;
    created_at: string | null;
}

export interface StoreMessage {
    id: number;
    message: string;
    user: { id: number; name: string } | null;
    created_at: string | null;
}

export interface StoreProductQuery {
    id: number;
    user: { id: number; name: string } | null;
    product: { id: number; name: string } | null;
    question: string;
    reply: string | null;
    created_at: string | null;
}

/** The kinds of file the store's uploader accepts (MultipartUploadService::KINDS). */
export type StoreUploadKind =
    'image' | 'video' | 'audio' | 'archive' | 'document';

/** FilterUploadsRequest::SORTS. */
export type StoreUploadSort = 'newest' | 'oldest' | 'smallest' | 'largest';

/** UploadResource: a file in the store's uploads library. */
export interface StoreUploadFilters {
    search: string | null;
    sort: StoreUploadSort;
    per_page: number;
}

export interface StoreWebsiteHomeSettings {
    home_slider_images: string[];
    home_slider_links: string[];
    cupon_background_color: string;
    cupon_title: string;
    cupon_subtitle: string;
    home_categories: string[];
}
export interface StoreWebsitePage {
    id: number;
    title: string;
    slug: string;
    type: string;
    public_url: string;
}
export interface StoreSupportTicket {
    id: number;
    code: string;
    subject: string;
    status: string;
    viewed: boolean;
    user: { id: number; name: string } | null;
    created_at: string | null;
    last_reply_at: string | null;
}

export interface StoreWebsiteFooterSettings {
    footer_logo: string;
    about_us_description: string;
    play_store_link: string;
    app_store_link: string;
    contact_address: string;
    contact_phone: string;
    contact_email: string;
    widget_one: string;
    widget_one_labels: string[];
    widget_one_links: string[];
    frontend_copyright_text: string;
    show_social_links: boolean;
    facebook_link: string;
    twitter_link: string;
    instagram_link: string;
    youtube_link: string;
    linkedin_link: string;
    seller_app_link: string;
    payment_method_images: string;
    registration_tutorial_button: boolean;
    registration_tutorial_text: string;
    registration_tutorial_link: string;
}
export interface StoreWebsiteAppearanceSettings {
    website_name: string;
    site_motto: string;
    site_icon: string;
    base_color: string;
    base_hov_color: string;
    login_page_image: string;
    register_page_image: string;
    seller_login_page_bg: string;
    flash_deal_banner: string;
    flash_deal_banner_small: string;
    meta_title: string;
    meta_description: string;
    meta_keywords: string;
    meta_image: string;
    cookies_agreement_text: string;
    show_cookies_agreement: boolean;
    show_website_popup: boolean;
    website_popup_content: string;
    show_subscribe_form: boolean;
}
export interface StoreWebsitePageForm {
    title: string;
    slug: string;
    content: string;
    meta_title: string;
    meta_description: string;
    keywords: string;
    meta_image: string;
}
export interface StoreTicketAttachment {
    id: number;
    name: string;
    url: string;
}
export interface StoreTicketDetail {
    id: number;
    code: number;
    subject: string;
    status: string;
    author: string;
    created_at: string;
    details_html: string;
    attachments: StoreTicketAttachment[];
}
export interface StoreTicketReply {
    id: number;
    author: string | null;
    created_at: string;
    reply_html: string;
    attachments: StoreTicketAttachment[];
}

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

export interface StoreDriver {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    country: string | null;
}

export interface StoreNotification {
    id: string;
    kind: 'order' | 'cart' | 'other';
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

export interface StoreCustomerPackage {
    id: number;
    name: string | null;
    localized_name: string | null;
    amount: string;
    formatted_amount: string;
    product_upload: number;
    logo: string | null;
    logo_url: string;
}

export interface StoreCategory {
    id: number;
    name: string;
    parent: StoreReferenceOption | null;
    order_level: number;
    level: number;
    banner_url: string | null;
    icon_url: string | null;
    cover_image_url: string | null;
    featured: boolean;
    commision_rate: string;
}
export interface StoreCategoryForm {
    id: number;
    name: string;
    parent_id: number;
    order_level: number;
    digital: boolean;
    banner: string | null;
    icon: string | null;
    cover_image: string | null;
    commision_rate: string;
    slug: string | null;
    meta_title: string | null;
    meta_description: string | null;
    filtering_attributes: number[];
}
export interface StoreCategoryOptions {
    parents: StoreReferenceOption[];
    attributes: StoreReferenceOption[];
    commissionEnabled: boolean;
}
