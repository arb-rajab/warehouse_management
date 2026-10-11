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
