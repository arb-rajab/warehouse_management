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

export interface StoreBrand {
    id: number;
    name: string;
    logo_url: string | null;
}

export interface StoreBrandForm {
    id: number;
    logo: string | null;
    slug: string | null;
    meta_title: string | null;
    meta_description: string | null;
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

export type StoreGeneralSettingsImage =
    'system_logo_white' | 'system_logo_black' | 'admin_login_background';

/** Upload ids are the stored strings; timezone is config('app.timezone'). */
export type StoreGeneralSettings = Record<StoreGeneralSettingsImage, string> & {
    site_name: string;
    timezone: string;
};

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
/** WebsitePageResource: a row of the website page list. */
export interface StoreWebsitePage {
    id: number;
    title: string;
    slug: string | null;
    type: string;
    public_url: string | null;
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

/** SupportTicketDetailResource: the ticket on its admin page. */
export interface StoreTicketDetail {
    id: number;
    code: string;
    subject: string;
    status: string;
    user: { id: number; name: string } | null;
    created_at: string;
    details_html: string;
    attachments: StoreUpload[];
}

/** TicketReplyResource: one reply on the admin ticket page. */
export interface StoreTicketReply {
    id: number;
    user: { id: number; name: string } | null;
    reply_html: string;
    created_at: string;
    attachments: StoreUpload[];
}

/**
 * UpdateWebsiteFooterRequest::KEYS: upload ids are the stored strings
 * (`payment_method_images` comma-separated); the seller and delivery boy app
 * links are sent only while their feature is on.
 */
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
    seller_app_link?: string;
    delivery_boy_app_link?: string;
    payment_method_images: string;
    registration_tutorial_button: boolean;
    registration_tutorial_text: string;
    registration_tutorial_link: string;
}

export type StoreWebsiteAppearanceImage =
    | 'site_icon'
    | 'login_page_image'
    | 'register_page_image'
    | 'seller_login_page_bg'
    | 'delivery_boy_login_page_bg'
    | 'flash_deal_banner'
    | 'flash_deal_banner_small'
    | 'meta_image';

/**
 * UpdateWebsiteAppearanceRequest::KEYS: the seller and delivery boy login
 * backgrounds are sent only while their feature is on.
 */
export interface StoreWebsiteAppearanceSettings {
    website_name: string;
    site_motto: string;
    site_icon: string;
    base_color: string;
    base_hov_color: string;
    login_page_image: string;
    register_page_image: string;
    seller_login_page_bg?: string;
    delivery_boy_login_page_bg?: string;
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

/** The stored header and footer scripts, raw (LegacyWebsiteScriptsController). */
export interface StoreWebsiteScripts {
    header_script: string;
    footer_script: string;
}

/** WebsitePageFormResource: a page's language-independent edit fields. */
export interface StoreWebsitePageForm {
    id: number;
    type: string;
    slug: string;
    meta_title: string;
    meta_description: string;
    keywords: string;
    meta_image: string;
    public_url: string | null;
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

export type StoreTripStatus = 'pending' | 'started' | 'closed';

/** The two trip lists that share `Store/Admin/Trips/Trips/Index`. */
export type StoreTripListType = 'all' | 'today';

/**
 * A row of the trip lists: the due date as `YYYY-MM-DD`, the start and close
 * times as ISO timestamps, the distance in metres and the duration in seconds.
 */
export interface StoreTrip {
    id: number;
    code: string;
    status: StoreTripStatus;
    due_date: string | null;
    started_at: string | null;
    closed_at: string | null;
    distance: number | null;
    duration: number | null;
    notes: string | null;
    driver: { id: number; name: string } | null;
    truck: { id: number; name: string } | null;
}

/**
 * An order on the trip map picker (`TripMapController::create`): its
 * customer's coordinates, the postal code of the lookup and the stored
 * shipping address (else the customer's).
 */
export interface StoreTripMapOrder {
    id: number;
    code: string;
    latitude: number;
    longitude: number;
    postal_code: string | null;
    address: string | null;
    customer: {
        id: number;
        name: string | null;
        phone: string | null;
        accounting_id: string | null;
    };
}

/**
 * A truck card of the trip map picker (`TripMapTruckResource`): the first
 * photo's upload id, the latest open trip (where its stops are added) and
 * every open trip, latest first, with each stop's customer.
 */
export interface StoreTripMapTruck {
    id: number;
    name: string;
    max_pallets_number: string | null;
    photo: number | null;
    trip: {
        id: number;
        code: string;
        driver: { id: number; name: string | null } | null;
    } | null;
    open_trips: {
        id: number;
        code: string;
        stops: {
            id: number;
            customer: { id: number; name: string | null } | null;
        }[];
    }[];
}

/** A trip checkpoint's kind: an order's delivery or another stop at a customer. */
export type StoreTripCheckpointType = 'delivery' | 'other';

/** An order the trip forms offer for a new delivery checkpoint. */
export interface StoreTripOrderOption {
    id: number;
    code: string;
    customer: string | null;
}

/** A truck from the available-vehicles lookup. */
export interface StoreTripTruckOption {
    id: number;
    name: string;
    model: string | null;
}

/** The available-vehicles lookup's answer, narrowed to what the form shows. */
export interface StoreTripVehicles {
    drivers: StoreReferenceOption[];
    trucks: StoreTripTruckOption[];
}

/**
 * A stored checkpoint on the trip edit form, with what the form shows for its
 * order (code and customer) or customer.
 */
export interface StoreTripCheckpoint {
    id: number;
    type: StoreTripCheckpointType;
    related_id: number;
    notes: string | null;
    order_code: string | null;
    customer: string | null;
}

/** The trip being edited: the due date as `YYYY-MM-DD`. */
export interface StoreTripForm {
    id: number;
    code: string;
    status: StoreTripStatus;
    driver_id: number | null;
    truck_id: number | null;
    due_date: string | null;
    notes: string | null;
    checkpoints: StoreTripCheckpoint[];
}

export interface StoreTripFilters {
    per_page: number;
    search: string;
    status: StoreTripStatus[];
}

export type StoreTripCheckpointStatus = 'pending' | 'delivered' | 'has_issue';

/**
 * A row of the trip detail: the customer (for an order, the customer it was
 * placed for), the order with its amount as the store formats prices, the
 * arrival as an ISO timestamp, the estimated time in seconds and the photo
 * upload ids.
 */
export interface StoreTripCheckpointRow {
    id: number;
    position: number | null;
    type: StoreTripCheckpointType;
    status: StoreTripCheckpointStatus;
    arrived_at: string | null;
    eta: number | null;
    notes: string | null;
    order: {
        id: number;
        code: string;
        invoice_number: string | null;
        products: number;
        amount: string;
    } | null;
    customer: {
        id: number;
        name: string | null;
        phone: string | null;
        company_name: string | null;
        address: string | null;
    } | null;
    representative: { id: number; name: string | null } | null;
    has_cmr: boolean;
    photos: number[];
}

/**
 * A row of the trip orders list: the customer (for a representative's order,
 * the customer it was placed for), the amount as the store formats prices,
 * the delivery date as `YYYY-MM-DD`, and the latest checkpoint with its photo
 * upload ids and trip.
 */
export interface StoreTripOrder {
    id: number;
    code: string;
    is_new: boolean;
    details_count: number;
    otajer_order_id: string | null;
    order_from: string | null;
    wms_status: string | null;
    customer: {
        id: number;
        name: string | null;
        accounting_id: string | null;
        company_name: string | null;
    } | null;
    by_rep: boolean;
    representative: { id: number; name: string | null } | null;
    address: string | null;
    country: string | null;
    phone: string | null;
    invoice_number: string | null;
    customer_notes: string | null;
    manager_notes: string | null;
    amount: string;
    delivery_status: string;
    delivery_date: string | null;
    editable: boolean;
    checkpoint: { id: number; photos: number[] } | null;
    trip: {
        id: number;
        code: string;
        driver: { id: number; name: string } | null;
        truck: { id: number; name: string } | null;
    } | null;
}

/** The trip orders list's filters; an empty string is an unset filter. */
export interface StoreTripOrderFilters {
    per_page: number;
    search: string;
    delivery_status: string;
    from: string;
    to: string;
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
export interface StoreDigitalProduct {
    id: number;
    name: string;
    added_by: string | null;
    thumbnail_url: string;
    unit_price: string;
    todays_deal: boolean;
    published: boolean;
    featured: boolean;
    public_url: string | null;
    download_token: string;
}
/** The language-independent fields of the digital product edit form. */
export interface StoreDigitalProductForm {
    id: number;
    category_id: number | null;
    /** Comma-separated image upload ids. */
    photos: string | null;
    thumbnail_img: string | null;
    /** Comma-separated tags. */
    tags: string | null;
    unit_price: string;
    purchase_price: string | null;
    discount: string | null;
    discount_type: string | null;
    /** `d-m-Y H:i:s to d-m-Y H:i:s`, or null without both ends. */
    date_range: string | null;
    meta_title: string | null;
    meta_description: string | null;
    meta_img: string | null;
    slug: string | null;
    /** The downloadable file's upload id. */
    file: string | null;
}
/** An active tax, with the product's amount and type for it. */
export interface StoreDigitalProductTax {
    id: number;
    name: string;
    tax: string;
    tax_type: 'amount' | 'percent';
}
/** A colour a physical product may come in, by its code. */
export interface StoreProductColor {
    id: number;
    name: string;
    code: string;
}
/** One value of a product attribute (`products.add-more-choice-option`). */
export interface StoreProductAttributeValue {
    id: number;
    value: string;
}
/**
 * A generated variant row (`products.variant_combinations`). On the edit
 * form (`products.edit_variant_combinations`) a row also carries the photo
 * of its stored stock row, with the preview the caller may see.
 */
export interface StoreProductVariantRow {
    variant: string;
    price: string;
    sku: string;
    qty: string;
    image?: string | null;
    files?: StoreUpload[];
}
/** A product's stored colours and choices, with the rows they generate. */
export interface StoreProductVariantState {
    colors_active: boolean;
    colors: string[];
    choice_options: { attribute_id: number; values: string[] }[];
    /** Each chosen attribute's values, keyed by attribute id. */
    attribute_values: Record<number, StoreProductAttributeValue[]>;
    rows: StoreProductVariantRow[];
}
/** The language-independent fields of the physical product edit form. */
export interface StoreProductForm {
    id: number;
    added_by: string | null;
    category_id: number | null;
    brand_id: number | null;
    weight: string | null;
    max_qty: string | null;
    min_qty: string;
    /** Comma-separated tags. */
    tags: string | null;
    /** Comma-separated image upload ids. */
    photos: string | null;
    /** The Otajer import's image upload ids, comma-separated. */
    old_photo: string | null;
    thumbnail_img: string | null;
    video_provider: string | null;
    video_link: string | null;
    pdf: string | null;
    unit_price: string;
    discount: string | null;
    discount_type: string | null;
    /** `d-m-Y H:i:s to d-m-Y H:i:s`, or null without both ends. */
    date_range: string | null;
    /** The first stock row's quantity and SKU. */
    current_stock: string;
    sku: string | null;
    external_link: string | null;
    external_link_btn: string | null;
    meta_title: string | null;
    meta_description: string | null;
    meta_img: string | null;
    slug: string | null;
    shipping_type: 'free' | 'flat_rate';
    flat_shipping_cost: string;
    is_quantity_multiplied: boolean;
    est_shipping_days: string | null;
    low_stock_quantity: string | null;
    stock_visibility_state: 'quantity' | 'text' | 'hide';
    cash_on_delivery: boolean;
    featured: boolean;
    todays_deal: boolean;
    /** The product's flash deal, while the form lists it. */
    flash_deal_id: number | null;
    product_types: string[];
    product_barcodes: string[];
}
/** The product list an edit form was opened from. */
export type StoreProductEditListType = 'admin' | 'seller';
/** The physical product form's sections the business settings switch on. */
export interface StoreProductFormSections {
    shipping: boolean;
    productWiseShipping: boolean;
    flashDeals: boolean;
    vat: boolean;
    cashOnDelivery: boolean;
}
/** The list a product row action names (`list`): its URI segment. */
export type StoreProductListType = 'admin' | 'all';
/** The all-products list's legacy one-flag presets. */
export type StoreProductListPreset =
    'allProducts' | 'allDeleted' | 'allActive' | 'allForSale' | 'allFeatured';
export interface StoreProductListRow {
    id: number;
    name: string;
    ar_name: string;
    thumbnail_url: string;
    mat_id: string | null;
    unit: string;
    num_of_sale: number;
    base_price: string;
    profit: string;
    /** Each variant's stock for a variant product, otherwise null. */
    variant_stocks: { variant: string; qty: number }[] | null;
    stock_quantity: number | null;
    low_stock: boolean;
    category: string | null;
    for_sale: boolean;
    published: boolean;
    featured: boolean;
    deleted_from_api: boolean;
    public_url: string | null;
}
export interface StoreProductListFilters {
    search: string | null;
    category_id: number | null;
    type: string | null;
    active: boolean;
    for_sale: boolean;
    featured: boolean;
    /** The all-products list only. */
    deleted?: boolean;
    user_id?: number | null;
    preset?: StoreProductListPreset | null;
    per_page: number;
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
