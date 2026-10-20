import type {
    Cell,
    CellPallet,
    CellWithLocation,
    CellStatusLog,
    CellVerificationReport,
    CellVerificationRound,
    MatchingPallet,
    Paginated,
    Row,
    Setting,
    User,
} from '@/types/admin';
import type {
    StoreConversation,
    StoreMessage,
    StoreProductQuery,
    StoreSupportTicket,
    StoreTicketDetail,
    StoreWebsiteAppearanceSettings,
    StoreWebsiteFooterSettings,
    StoreWebsiteHeaderSettings,
    StoreGeneralSettings,
    StoreWebsiteHomeSettings,
    StoreWebsitePage,
    StoreWebsitePageForm,
    PermissionGroup,
    StaffMember,
    StoreBlog,
    StoreBlogCategory,
    StoreCoupon,
    StoreSubscriber,
    StoreRole,
    StoreSharedProps,
    StoreUpload,
    StoreOffer,
    StoreFlashDeal,
    StoreCampaignProduct,
    AdminLanguage,
    StoreTranslation,
    StoreCountry,
    StoreState,
    StoreCity,
    StoreZone,
    StoreCarrier,
    StoreCustomerPackage,
    StoreCategory,
    StoreCategoryForm,
    StoreDigitalProduct,
    StoreDigitalProductForm,
    StoreDigitalProductTax,
    StoreProductForm,
    StoreProductVariantState,
    StoreProductListFilters,
    StoreProductListRow,
    StorePickupPoint,
    StoreTax,
    StoreCurrency,
    StoreProfile,
    ClassifiedProduct,
    StoreBrand,
    StoreBrandForm,
    StoreOffersCatalog,
    StoreReview,
    StoreTicketReply,
    StoreDriver,
    StoreOrder,
    StoreTrip,
    StoreTripCheckpointRow,
    StoreTripMapOrder,
    StoreTripMapTruck,
    StoreTripForm,
    StoreTripOrder,
} from '@/types/store';

import type {
    StoreAttribute,
    StoreAttributeValue,
    StoreColor,
} from '@/types/storeCatalogue';
import type { CustomerDetails } from '@/types/storeCustomers';
import type {
    CustomerNotification,
    NotificationCustomer,
    AdminNotification,
} from '@/types/storeNotifications';
import type {
    StoreProductReportRow,
    StoreSellerReportRow,
    StoreSearchReportRow,
    StoreCommissionReportRow,
    StoreWalletReportRow,
} from '@/types/storeReports';
import type {
    RepresentativePackage,
    RepresentativePayment,
    RepresentativeVisit,
    RepresentativeVisitDetail,
    RepresentativeVisitMarker,
} from '@/types/storeRepresentatives';
import type { StoreTruck, TruckPhoto } from '@/types/storeTruck';

/**
 * Shared `Paginated<T>` test fixture — every page test that mounts a paginated
 * table built this `meta` shape independently before this existed.
 */
export function paginated<T>(
    data: T[],
    perPage = 20,
    { currentPage = 1, lastPage = 1 } = {},
): Paginated<T> {
    return {
        data,
        meta: {
            current_page: currentPage,
            last_page: lastPage,
            per_page: perPage,
            total: data.length,
            from: data.length ? 1 : null,
            to: data.length,
            links: [],
        },
    };
}

export function row(overrides: Partial<Row> = {}): Row {
    return {
        id: 1,
        letter: 'A',
        cells_count: 5,
        flats_count: 7,
        has_pallets: false,
        ...overrides,
    };
}

export function cell(overrides: Partial<Cell> = {}): Cell {
    return {
        id: 1,
        cell_number: 1,
        flat_number: 1,
        state: 'empty',
        is_active: true,
        pallet: null,
        ...overrides,
    };
}

export function cellWithLocation(
    overrides: Partial<CellWithLocation> = {},
): CellWithLocation {
    return { ...cell(), row_letter: 'A', ...overrides };
}

export function cellPallet(overrides: Partial<CellPallet> = {}): CellPallet {
    return {
        id: 42,
        product_id: 1,
        product_name: 'Widgets',
        product_ar_name: 'ودجات',
        product_image_url: null,
        expiration_date: '2026-12-01',
        added_at: '2026-01-01',
        is_stale: false,
        remaining_boxes: 10,
        ...overrides,
    };
}

export function setting(overrides: Partial<Setting> = {}): Setting {
    return {
        qr_code_width: 240,
        qr_code_height: 240,
        allowed_open_levels: [1],
        ...overrides,
    };
}

export function user(overrides: Partial<User> = {}): User {
    return {
        id: 7,
        name: 'Jane Doe',
        email: 'jane@example.com',
        is_admin: false,
        ...overrides,
    };
}

export function cellVerificationReport(
    overrides: Partial<CellVerificationReport> = {},
): CellVerificationReport {
    return {
        id: 1,
        cell_verification_round_id: 1,
        is_correct: true,
        cell: { row_letter: 'A', cell_number: 3, flat_number: 2 },
        expected: {
            cell_state: 'full',
            product: {
                id: 10,
                name: 'Widgets',
                ar_name: 'ودجات',
                image_url: null,
                boxes_count: 10,
                active: true,
            },
            boxes_count: 10,
            expiration_date: null,
        },
        reported: {
            cell_state: 'full',
            product: {
                id: 10,
                name: 'Widgets',
                ar_name: 'ودجات',
                image_url: null,
                boxes_count: 10,
                active: true,
            },
            boxes_count: 10,
            expiration_date: null,
        },
        note: null,
        user: { id: 7, name: 'Jane Doe' },
        round_completed: true,
        accepted_at: null,
        accepted_by: null,
        created_at: '2026-08-01T10:00:00Z',
        ...overrides,
    };
}

export function cellVerificationRound(
    overrides: Partial<CellVerificationRound> = {},
): CellVerificationRound {
    return {
        id: 1,
        started_at: '2026-08-01T10:00:00Z',
        completed_at: '2026-08-01T11:00:00Z',
        reports_count: 3,
        rows: [
            { id: 1, letter: 'A' },
            { id: 2, letter: 'B' },
        ],
        user: { id: 7, name: 'Jane Doe' },
        ...overrides,
    };
}

export function matchingPallet(
    overrides: Partial<MatchingPallet> = {},
): MatchingPallet {
    return {
        id: 1,
        state: 'full',
        expiration_date: '2026-09-01',
        added_at: '2026-08-01T10:00:00Z',
        remaining_boxes: 10,
        product: {
            id: 10,
            name: 'Widgets',
            ar_name: 'ودجات',
            image_url: null,
            boxes_count: 10,
            active: true,
        },
        location: { row_letter: 'A', cell_number: 3, flat_number: 2 },
        cell_active: true,
        ...overrides,
    };
}

export function cellLog(overrides: Partial<CellStatusLog> = {}): CellStatusLog {
    return {
        id: 1,
        action: 'stored',
        from_state: 'empty',
        to_state: 'full',
        note: 'Handle with care',
        boxes_count: null,
        cell: { row_letter: 'A', cell_number: 3, flat_number: 2 },
        related_cell: null,
        product: {
            id: 10,
            name: 'Widgets',
            ar_name: 'ودجات',
            image_url: null,
            boxes_count: 10,
            active: true,
        },
        pallet: { id: 55, expiration_date: '2026-09-01' },
        user: { id: 7, name: 'Jane Doe' },
        created_at: '2026-08-01T10:00:00Z',
        next_log_at: null,
        duration_seconds: 3600,
        flagged: false,
        flags: [],
        ...overrides,
    };
}

/** The store back office's shared `store` prop, for its layout and pages. */
export function storeSharedProps(
    overrides: Partial<StoreSharedProps> = {},
): StoreSharedProps {
    return {
        rtl: false,
        permissions: [],
        isSuperAdmin: false,
        isRaizerAdmin: false,
        settings: {},
        counts: { sellers: 0, tickets: 0, conversations: 0 },
        notifications: { unread_count: 0, items: [] },
        ...overrides,
    };
}

export function storeRole(overrides: Partial<StoreRole> = {}): StoreRole {
    return { id: 3, name: 'Clerk', ...overrides };
}

export function staffMember(overrides: Partial<StaffMember> = {}): StaffMember {
    return {
        id: 11,
        user_id: 21,
        name: 'Sam Staff',
        email: 'sam@example.com',
        phone: '0612345678',
        role: storeRole(),
        is_super_admin: false,
        ...overrides,
    };
}

export function permissionGroup(
    overrides: Partial<PermissionGroup> = {},
): PermissionGroup {
    return {
        section: 'staff',
        label: 'Staff',
        permissions: [
            { id: 1, label: 'View All Staffs' },
            { id: 2, label: 'Add Staff' },
        ],
        ...overrides,
    };
}

export function storeCountry(
    overrides: Partial<StoreCountry> = {},
): StoreCountry {
    return {
        id: 11,
        name: 'Netherlands',
        code: 'NL',
        status: true,
        ...overrides,
    };
}

export function storeState(overrides: Partial<StoreState> = {}): StoreState {
    return {
        id: 12,
        name: 'North Holland',
        country_id: 11,
        country: { id: 11, name: 'Netherlands' },
        status: true,
        ...overrides,
    };
}

export function storeCity(overrides: Partial<StoreCity> = {}): StoreCity {
    return {
        id: 13,
        name: 'Amsterdam',
        state_id: 12,
        state: { id: 12, name: 'North Holland' },
        cost: '2.50',
        status: true,
        ...overrides,
    };
}

export function storeZone(overrides: Partial<StoreZone> = {}): StoreZone {
    return { id: 14, name: 'Europe', status: true, ...overrides };
}

export function storePickupPoint(
    overrides: Partial<StorePickupPoint> = {},
): StorePickupPoint {
    return {
        id: 15,
        name: 'Warehouse',
        address: 'Warehouse road',
        phone: '0612345678',
        staff_id: 16,
        staff: { id: 16, name: 'Manager' },
        pick_up_status: true,
        ...overrides,
    };
}

export function storeTax(overrides: Partial<StoreTax> = {}): StoreTax {
    return { id: 17, name: 'VAT', tax_status: true, ...overrides };
}

export function storeCurrency(
    overrides: Partial<StoreCurrency> = {},
): StoreCurrency {
    return {
        id: 18,
        name: 'Euro',
        symbol: '€',
        code: 'EUR',
        exchange_rate: '1.23000',
        status: true,
        ...overrides,
    };
}

export function adminLanguage(
    overrides: Partial<AdminLanguage> = {},
): AdminLanguage {
    return {
        id: 3,
        name: 'Arabic',
        code: 'ar',
        app_lang_code: 'ar',
        rtl: true,
        status: true,
        is_default: false,
        is_english: false,
        ...overrides,
    };
}
export function storeTranslation(
    overrides: Partial<StoreTranslation> = {},
): StoreTranslation {
    return {
        id: 7,
        key: 'hello',
        source: 'Hello',
        value: 'مرحبا',
        ...overrides,
    };
}

export function storeProductReportRow(
    overrides: Partial<StoreProductReportRow> = {},
): StoreProductReportRow {
    return { id: 1, name: 'Olive oil', value: 12, ...overrides };
}

export function storeSellerReportRow(
    overrides: Partial<StoreSellerReportRow> = {},
): StoreSellerReportRow {
    return {
        id: 1,
        seller_name: 'Sam Seller',
        shop_name: 'Sam shop',
        num_of_sale: 14,
        order_amount: '€125.00',
        ...overrides,
    };
}

export function storeSearchReportRow(
    overrides: Partial<StoreSearchReportRow> = {},
): StoreSearchReportRow {
    return { id: 1, query: 'Olive oil', count: 9, ...overrides };
}

export function storeCommissionReportRow(
    overrides: Partial<StoreCommissionReportRow> = {},
): StoreCommissionReportRow {
    return {
        id: 1,
        order_code: '20261008-101',
        admin_commission: '3.50',
        seller_earning: '31.50',
        created_at: '2026-10-08T10:30:00Z',
        ...overrides,
    };
}

export function storeWalletReportRow(
    overrides: Partial<StoreWalletReportRow> = {},
): StoreWalletReportRow {
    return {
        id: 1,
        user_name: 'Sam Customer',
        amount: '€100.00',
        payment_method: 'Cash on delivery',
        created_at: '2026-10-08T10:30:00Z',
        ...overrides,
    };
}

export function customerNotification(
    overrides: Partial<CustomerNotification> = {},
): CustomerNotification {
    return {
        id: 11,
        title: 'Hello',
        status: 'sent',
        customer: { id: 21, name: 'Customer' },
        ...overrides,
    };
}

export function notificationCustomer(
    overrides: Partial<NotificationCustomer> = {},
): NotificationCustomer {
    return { id: 21, name: 'Customer', account_id: 100, ...overrides };
}

export function adminNotification(
    overrides: Partial<AdminNotification> = {},
): AdminNotification {
    return {
        id: 'notification-uuid',
        kind: 'order',
        order_code: 'ORDER-7',
        status: 'payment_received',
        customer_name: null,
        message: null,
        url: '/temp/admin/orders/encrypted/show',
        created_at: '2026-10-01T12:00:00+00:00',
        ...overrides,
    };
}

export function storeUpload(overrides: Partial<StoreUpload> = {}): StoreUpload {
    return {
        id: 1,
        name: 'Photo',
        type: 'image',
        size: 42,
        url: 'https://store.example/photo.webp',
        ...overrides,
    };
}
export function storeProfile(
    overrides: Partial<StoreProfile> = {},
): StoreProfile {
    return {
        id: 7,
        name: 'Sam Staff',
        email: 'sam@example.com',
        avatar: null,
        ...overrides,
    };
}

export function classifiedProduct(
    overrides: Partial<ClassifiedProduct> = {},
): ClassifiedProduct {
    return {
        id: 1,
        name: 'Customer advert',
        public_url: '/temp/customer-product/customer-advert',
        added_by: 'customer',
        status: true,
        published: false,
        thumbnail_url: null,
        ...overrides,
    };
}

/** A customer account and its editable store profile. */
export function storeCustomer(
    overrides: Partial<CustomerDetails> = {},
): CustomerDetails {
    return {
        id: 17,
        name: 'Amy',
        email: 'amy@example.com',
        phone: '31612345678',
        company_name: 'Company',
        is_rep: false,
        archived: false,
        banned: false,
        verified: true,
        important: false,
        activity_status: 'new_user',
        rep_id: null,
        rep_serial: null,
        country: 'Netherlands',
        tax_number: 'VAT',
        bank_account_number: 'NL123',
        company_address: 'Company address',
        shipping_address: 'Shipping address',
        member_serial: '123',
        AccSysID: 123,
        trade_license: 'license.pdf',
        admin_notes: 'Call later',
        registration_completed: false,
        ...overrides,
    };
}

export function representativePackage(
    overrides: Partial<RepresentativePackage> = {},
): RepresentativePackage {
    return { id: 3, name: 'Gold', percentage: '12.5', ...overrides };
}

export function representativePayment(
    overrides: Partial<RepresentativePayment> = {},
): RepresentativePayment {
    return {
        id: 'receipt-001',
        amount: '125.50',
        receipt_code: 'R-001',
        invoice_code: 123,
        notes: 'Paid in cash',
        status: 'pending',
        date: '2026-10-08 10:00:00',
        customer_name: 'Customer One',
        account_id: 42,
        representative: { id: 10, name: 'Rep One' },
        accountant: null,
        ...overrides,
    };
}

export function representativeVisit(
    overrides: Partial<RepresentativeVisit> = {},
): RepresentativeVisit {
    return {
        id: 4,
        note: 'Visit note',
        visit_date: '2026-10-08 10:00:00',
        has_order: false,
        representative: { id: 10, name: 'Rep One' },
        customer: { id: 11, name: 'Customer One' },
        ...overrides,
    };
}

export function storeOffersCatalog(
    overrides: Partial<StoreOffersCatalog> = {},
): StoreOffersCatalog {
    return { id: 1, created_at: '2026-10-09T00:00:00+00:00', ...overrides };
}

export function storeReview(overrides: Partial<StoreReview> = {}): StoreReview {
    return {
        id: 1,
        rating: 4,
        comment: 'Good product',
        published: true,
        product: {
            id: 7,
            name: 'Dates',
            owner: 'admin',
            url: '/temp/product/dates',
        },
        customer: { id: 9, name: 'Customer', email: 'customer@example.com' },
        ...overrides,
    };
}

export function storeAttribute(
    overrides: Partial<StoreAttribute> = {},
): StoreAttribute {
    return { id: 1, name: 'Size', values: [], ...overrides };
}

export function storeAttributeValue(
    overrides: Partial<StoreAttributeValue> = {},
): StoreAttributeValue {
    return { id: 1, attribute_id: 1, value: 'Large', ...overrides };
}

export function storeColor(overrides: Partial<StoreColor> = {}): StoreColor {
    return { id: 1, name: 'Red', code: '#ff0000', ...overrides };
}

export function storeBrand(overrides: Partial<StoreBrand> = {}): StoreBrand {
    return { id: 3, name: 'Dates brand', logo_url: null, ...overrides };
}

export function storeBrandForm(
    overrides: Partial<StoreBrandForm> = {},
): StoreBrandForm {
    return {
        id: 3,
        logo: null,
        slug: 'dates-brand',
        meta_title: 'Dates SEO title',
        meta_description: 'Dates SEO description',
        ...overrides,
    };
}

export function storeConversation(
    overrides: Partial<StoreConversation> = {},
): StoreConversation {
    return {
        id: 41,
        title: 'Delivery question',
        sender: { id: 8, name: 'Customer' },
        receiver: { id: 9, name: 'Store owner' },
        sender_viewed: true,
        receiver_viewed: false,
        created_at: '2026-10-09T10:00:00+00:00',
        ...overrides,
    };
}

export function storeMessage(
    overrides: Partial<StoreMessage> = {},
): StoreMessage {
    return {
        id: 42,
        message: 'Can you help?',
        user: { id: 8, name: 'Customer' },
        created_at: '2026-10-09T10:00:00+00:00',
        ...overrides,
    };
}

export function storeProductQuery(
    overrides: Partial<StoreProductQuery> = {},
): StoreProductQuery {
    return {
        id: 43,
        user: { id: 8, name: 'Customer' },
        product: { id: 10, name: 'Rice' },
        question: 'Is this available?',
        reply: null,
        created_at: '2026-10-09T10:00:00+00:00',
        ...overrides,
    };
}

export function storeGeneralSettings(
    overrides: Partial<StoreGeneralSettings> = {},
): StoreGeneralSettings {
    return {
        site_name: 'Al Baraka',
        timezone: 'UTC',
        system_logo_white: '1',
        system_logo_black: '',
        admin_login_background: '',
        ...overrides,
    };
}

/** UploadResource: a stored PNG, as the uploads library lists it. */
export function storeWebsiteHeaderSettings(
    overrides: Partial<StoreWebsiteHeaderSettings> = {},
): StoreWebsiteHeaderSettings {
    return {
        header_logo: '',
        show_language_switcher: true,
        show_currency_switcher: false,
        header_stikcy: true,
        topbar_banner: '',
        topbar_banner_medium: '',
        topbar_banner_small: '',
        topbar_banner_link: '/offers',
        helpline_number: '12345',
        header_menu_labels: ['Offers'],
        header_menu_links: ['/offers'],
        ...overrides,
    };
}

export function storeWebsiteHomeSettings(
    overrides: Partial<StoreWebsiteHomeSettings> = {},
): StoreWebsiteHomeSettings {
    return {
        home_slider_images: [''],
        home_slider_links: ['/offers'],
        cupon_background_color: '#123456',
        cupon_title: 'Offers',
        cupon_subtitle: 'Save today',
        home_categories: ['17'],
        ...overrides,
    };
}
export function storeWebsitePage(
    overrides: Partial<StoreWebsitePage> = {},
): StoreWebsitePage {
    return {
        id: 44,
        title: 'About us',
        slug: 'about',
        type: 'custom_page',
        public_url: 'http://localhost/temp/about',
        ...overrides,
    };
}
export function storeSupportTicket(
    overrides: Partial<StoreSupportTicket> = {},
): StoreSupportTicket {
    return {
        id: 45,
        code: '123456',
        subject: 'Help',
        status: 'pending',
        viewed: false,
        user: { id: 8, name: 'Customer' },
        created_at: '2026-10-09T10:00:00+00:00',
        last_reply_at: '2026-10-09T12:00:00+00:00',
        ...overrides,
    };
}
export function storeTicketDetail(
    overrides: Partial<StoreTicketDetail> = {},
): StoreTicketDetail {
    return {
        id: 45,
        code: '123456',
        subject: 'Help',
        status: 'open',
        user: { id: 8, name: 'Customer' },
        created_at: '2026-10-09T10:00:00+00:00',
        details_html: '<p>My <strong>order</strong> is late</p>',
        attachments: [storeUpload({ id: 7, name: 'receipt' })],
        ...overrides,
    };
}
export function storeTicketReply(
    overrides: Partial<StoreTicketReply> = {},
): StoreTicketReply {
    return {
        id: 91,
        user: { id: 3, name: 'Staff member' },
        reply_html: '<p>It ships <em>today</em></p>',
        created_at: '2026-10-09T12:00:00+00:00',
        attachments: [],
        ...overrides,
    };
}

export function storeWebsiteFooterSettings(
    overrides: Partial<StoreWebsiteFooterSettings> = {},
): StoreWebsiteFooterSettings {
    return {
        footer_logo: '12',
        about_us_description: '<p>About <strong>us</strong></p>',
        play_store_link: 'https://play.example.com/store',
        app_store_link: '',
        contact_address: 'Main street 1',
        contact_phone: '+31 20 123 4567',
        contact_email: 'shop@example.com',
        widget_one: 'Links',
        widget_one_labels: ['Help'],
        widget_one_links: ['/help'],
        frontend_copyright_text: '<p>© Store</p>',
        show_social_links: true,
        facebook_link: 'https://facebook.example.com/store',
        twitter_link: '',
        instagram_link: '',
        youtube_link: '',
        linkedin_link: '',
        payment_method_images: '',
        registration_tutorial_button: false,
        registration_tutorial_text: '',
        registration_tutorial_link: '',
        ...overrides,
    };
}

export function storeWebsiteAppearanceSettings(
    overrides: Partial<StoreWebsiteAppearanceSettings> = {},
): StoreWebsiteAppearanceSettings {
    return {
        website_name: 'Store',
        site_motto: 'Fresh food',
        site_icon: '',
        base_color: '#112233',
        base_hov_color: '#123',
        login_page_image: '',
        register_page_image: '',
        flash_deal_banner: '',
        flash_deal_banner_small: '',
        meta_title: 'Store',
        meta_description: 'Fresh food online',
        meta_keywords: 'food, rice',
        meta_image: '',
        cookies_agreement_text: '<p>We use <strong>cookies</strong></p>',
        show_cookies_agreement: true,
        show_website_popup: false,
        website_popup_content: '<p>Welcome</p>',
        show_subscribe_form: false,
        ...overrides,
    };
}

export function storeWebsitePageForm(
    overrides: Partial<StoreWebsitePageForm> = {},
): StoreWebsitePageForm {
    return {
        id: 44,
        type: 'custom_page',
        slug: 'about',
        meta_title: 'Meta',
        meta_description: 'Description',
        keywords: 'store',
        meta_image: '',
        public_url: 'http://localhost/temp/about',
        ...overrides,
    };
}
export function storeBlogCategory(
    overrides: Partial<StoreBlogCategory> = {},
): StoreBlogCategory {
    return { id: 4, category_name: 'Recipes', ...overrides };
}

/** A stored post as `Store\Admin\BlogController::edit()` sends it. */
export function storeBlog(overrides: Partial<StoreBlog> = {}): StoreBlog {
    return {
        id: 12,
        title: 'Fresh rice',
        category: { id: 4, category_name: 'Recipes' },
        status: true,
        public_url: '/temp/blog/fresh-rice',
        slug: 'fresh-rice',
        short_description: 'A short introduction',
        banner: '5',
        description: '<p>Stored <strong>body</strong></p>',
        meta_title: 'Rice metadata',
        meta_img: '6',
        meta_description: 'A rice description',
        meta_keywords: 'rice, food',
        category_id: 4,
        ...overrides,
    };
}

export function storeSubscriber(
    overrides: Partial<StoreSubscriber> = {},
): StoreSubscriber {
    return {
        id: 5,
        email: 'reader@example.com',
        created_at: '2026-10-08',
        ...overrides,
    };
}

export function storeCoupon(overrides: Partial<StoreCoupon> = {}): StoreCoupon {
    return {
        id: 6,
        type: 'cart_base',
        code: 'SPRING',
        discount: 10,
        discount_type: 'percent',
        start_date: '2026-10-01',
        end_date: '2026-10-31',
        product_ids: [],
        min_buy: 20,
        max_discount: 5,
        ...overrides,
    };
}

/** UploadResource: a stored PNG, as the uploads library lists it. */
export function storeOffer(overrides: Partial<StoreOffer> = {}): StoreOffer {
    return {
        id: 1,
        title: 'Product offer',
        banner: '',
        start_date: '2026-10-01T08:09:11',
        end_date: '2026-10-30T09:08:07',
        offer_type: 'products_offer',
        brand_id: null,
        is_active: true,
        is_repeatable: false,
        multiple_usage: false,
        ...overrides,
    };
}
export function storeFlashDeal(
    overrides: Partial<StoreFlashDeal> = {},
): StoreFlashDeal {
    return {
        id: 2,
        title: 'Flash deal',
        slug: 'flash-deal',
        banner: '',
        start_date: '2026-10-01T08:09:11',
        end_date: '2026-10-30T09:08:07',
        background_color: '#ffffff',
        text_color: 'dark',
        status: true,
        featured: false,
        public_url: '/temp/flash-deal/flash-deal',
        ...overrides,
    };
}
export function storeCampaignProduct(
    overrides: Partial<StoreCampaignProduct> = {},
): StoreCampaignProduct {
    return {
        id: 12,
        name: 'Translated product',
        price: 100,
        discount: 10,
        discount_type: 'amount',
        ...overrides,
    };
}

export function storeDriver(overrides: Partial<StoreDriver> = {}): StoreDriver {
    return {
        id: 31,
        name: 'Sam Driver',
        email: 'sam@example.com',
        phone: '0612345678',
        country: 'Netherlands',
        ...overrides,
    };
}

export function storeTrip(overrides: Partial<StoreTrip> = {}): StoreTrip {
    return {
        id: 41,
        code: '20261014-080000',
        status: 'started',
        due_date: '2026-10-14',
        started_at: '2026-10-14T08:30:00+00:00',
        closed_at: null,
        distance: 12345.67,
        duration: 3720,
        notes: 'Ring at the back',
        driver: { id: 31, name: 'Sam Driver' },
        truck: { id: 11, name: 'Cargo truck' },
        ...overrides,
    };
}

/**
 * A trip detail row: a representative's delivered order with photos and a
 * CMR file.
 */
export function storeTripCheckpointRow(
    overrides: Partial<StoreTripCheckpointRow> = {},
): StoreTripCheckpointRow {
    return {
        id: 501,
        position: 1,
        type: 'delivery',
        status: 'delivered',
        arrived_at: '2026-10-14T10:30:00+00:00',
        eta: 1830,
        notes: 'Back door',
        order: {
            id: 77,
            code: '20261015-0001',
            invoice_number: 'INV-1',
            products: 3,
            amount: '€42.50',
        },
        customer: {
            id: 21,
            name: 'Alma Customer',
            phone: '0611111111',
            company_name: 'Alma BV',
            address: 'Main street 5',
        },
        representative: { id: 22, name: 'Rita Rep' },
        has_cmr: true,
        photos: [601],
        ...overrides,
    };
}

/** A trip map picker order: Alma's order with every popup detail. */
export function storeTripMapOrder(
    overrides: Partial<StoreTripMapOrder> = {},
): StoreTripMapOrder {
    return {
        id: 77,
        code: '20261017-0001',
        latitude: 52.37,
        longitude: 4.89,
        postal_code: '1011AB',
        address: 'Dock road 5',
        customer: {
            id: 21,
            name: 'Alma Customer',
            phone: '0611111111',
            accounting_id: 'ACC-7',
        },
        ...overrides,
    };
}

/** A trip map picker truck: a free truck with a photo and no trip. */
export function storeTripMapTruck(
    overrides: Partial<StoreTripMapTruck> = {},
): StoreTripMapTruck {
    return {
        id: 11,
        name: 'Cargo truck',
        max_pallets_number: '18',
        photo: 601,
        trip: null,
        open_trips: [],
        ...overrides,
    };
}

/** A trip orders row: a representative's order on a started trip. */
export function storeTripOrder(
    overrides: Partial<StoreTripOrder> = {},
): StoreTripOrder {
    return {
        id: 77,
        code: '20261015-0001',
        is_new: true,
        details_count: 3,
        otajer_order_id: 'OT-9',
        order_from: 'app',
        wms_status: 'picked',
        customer: {
            id: 21,
            name: 'Alma Customer',
            accounting_id: 'ACC-7',
            company_name: 'Alma BV',
        },
        by_rep: true,
        representative: { id: 22, name: 'Rita Rep' },
        address: 'Main street 5',
        country: 'Netherlands',
        phone: '0611111111',
        invoice_number: 'INV-1',
        customer_notes: 'Ring twice',
        manager_notes: 'Handle with care',
        amount: '€42.50',
        delivery_status: 'on_delivery',
        delivery_date: '2026-10-16',
        editable: true,
        checkpoint: { id: 501, photos: [601] },
        trip: {
            id: 41,
            code: 'TRIP-LAST',
            driver: { id: 31, name: 'Sam Driver' },
            truck: { id: 11, name: 'Cargo truck' },
        },
        ...overrides,
    };
}

/** A trip on the edit form: the departure checkpoint, then a delivery. */
export function storeTripForm(
    overrides: Partial<StoreTripForm> = {},
): StoreTripForm {
    return {
        id: 41,
        code: '20261014-080000',
        status: 'pending',
        driver_id: 31,
        truck_id: 11,
        due_date: '2026-10-14',
        notes: 'Ring at the back',
        checkpoints: [
            {
                id: 501,
                type: 'other',
                related_id: 9,
                notes: 'Default starting checkpoint',
                order_code: null,
                customer: 'Warehouse',
            },
            {
                id: 502,
                type: 'delivery',
                related_id: 77,
                notes: 'Back door',
                order_code: '20261013-0042',
                customer: 'Alma Customer',
            },
        ],
        ...overrides,
    };
}

export function storeTruck(overrides: Partial<StoreTruck> = {}): StoreTruck {
    return {
        id: 11,
        name: 'Cargo truck',
        model: 'Volvo',
        max_pallets_number: '32',
        description: 'Refrigerated',
        license_plate: 'AB-123',
        photos: [7],
        open_trip_id: null,
        ...overrides,
    };
}

export function truckPhoto(overrides: Partial<TruckPhoto> = {}): TruckPhoto {
    return {
        id: 7,
        name: 'Truck photo',
        url: 'https://images.example/truck.jpg',
        ...overrides,
    };
}

export function storeOrder(overrides: Partial<StoreOrder> = {}): StoreOrder {
    return {
        id: 7,
        code: 'ORD-7',
        invoice_number: null,
        customer: { id: 3, name: 'Buyer', AccSysID: null },
        representative: null,
        editable: true,
        by_rep: false,
        delivery_date: '2026-10-15',
        delivery_status: 'ready_for_delivery',
        payment_status: 'unpaid',
        payment_type: 'cash_on_delivery',
        grand_total: 30.3,
        profits: 2,
        discount_percent: 16.67,
        indicator: 10,
        manager_notes: 'Office notes',
        created_at: '2026-10-10',
        details: [
            {
                id: 1,
                product_id: 5,
                name: '<script>unsafe()</script>',
                serial: 'SER-5',
                mat_id: null,
                quantity: 2,
                price: 20,
                unit_price: 10,
                original_price: 24,
                tax: 6.3,
                shipping_cost: 4,
                profits: 2,
                variation: '',
                variation_qty: '[]',
                unit: 'PerBox',
                info: 'Notes',
            },
        ],
        ...overrides,
    };
}

export function storeCarrier(
    overrides: Partial<StoreCarrier> = {},
): StoreCarrier {
    return {
        id: 1,
        name: 'Parcel',
        transit_time: '2–3 days',
        logo: 7,
        free_shipping: false,
        status: true,
        ranges: [
            {
                billing_type: 'weight_based',
                delimiter1: '0',
                delimiter2: '10',
                prices: { 11: '5', 12: '7' },
            },
            {
                billing_type: 'weight_based',
                delimiter1: '10',
                delimiter2: '20',
                prices: { 11: '8', 12: '9' },
            },
        ],
        ...overrides,
    };
}

export function storeCustomerPackage(
    overrides: Partial<StoreCustomerPackage> = {},
): StoreCustomerPackage {
    return {
        id: 17,
        name: 'Gold',
        localized_name: 'Gold',
        amount: '12.50',
        formatted_amount: '€12.50',
        product_upload: 10,
        logo: '4',
        logo_url: '/uploads/gold.png',
        ...overrides,
    };
}

export function storeCategory(
    overrides: Partial<StoreCategory> = {},
): StoreCategory {
    return {
        id: 17,
        name: 'Food',
        parent: null,
        level: 0,
        order_level: 3,
        banner_url: null,
        icon_url: null,
        cover_image_url: null,
        featured: false,
        commision_rate: '2.50',
        ...overrides,
    };
}

export function storeDigitalProduct(
    overrides: Partial<StoreDigitalProduct> = {},
): StoreDigitalProduct {
    return {
        id: 31,
        name: 'Recipe e-book',
        added_by: 'admin',
        thumbnail_url: '/assets/img/placeholder.jpg',
        unit_price: '1,250.50',
        todays_deal: false,
        published: true,
        featured: false,
        public_url: '/temp/product/recipe-e-book',
        download_token: 'encrypted-31',
        ...overrides,
    };
}
export function storeDigitalProductForm(
    overrides: Partial<StoreDigitalProductForm> = {},
): StoreDigitalProductForm {
    return {
        id: 31,
        category_id: 12,
        photos: '5,6',
        thumbnail_img: '7',
        tags: 'cooking,recipes',
        unit_price: '1250.50',
        purchase_price: '900.00',
        discount: '10.00',
        discount_type: 'percent',
        date_range: '01-11-2026 08:00:00 to 30-11-2026 20:00:00',
        meta_title: 'Recipe e-book',
        meta_description: 'Family recipes',
        meta_img: '8',
        slug: 'recipe-e-book-12345',
        file: '9',
        ...overrides,
    };
}
/** A physical product's edit-form fields, as ProductFormResource sends them. */
export function storeProductForm(
    overrides: Partial<StoreProductForm> = {},
): StoreProductForm {
    return {
        id: 41,
        added_by: 'admin',
        category_id: 12,
        brand_id: 5,
        weight: '1.25',
        max_qty: '24',
        min_qty: '2',
        tags: 'oil,olive',
        photos: '5,6',
        old_photo: '4',
        thumbnail_img: '7',
        video_provider: 'vimeo',
        video_link: 'https://vimeo.com/123',
        pdf: '9',
        unit_price: '12.50',
        discount: '1.50',
        discount_type: 'amount',
        date_range: '01-11-2026 08:00:00 to 30-11-2026 20:30:00',
        current_stock: '40',
        sku: 'EVOO-1',
        external_link: 'https://example.com/oil',
        external_link_btn: 'Buy elsewhere',
        meta_title: 'Olive oil',
        meta_description: 'Fresh oil',
        meta_img: '8',
        slug: 'olive-oil',
        shipping_type: 'flat_rate',
        flat_shipping_cost: '4.95',
        is_quantity_multiplied: true,
        est_shipping_days: '3',
        low_stock_quantity: '5',
        stock_visibility_state: 'text',
        cash_on_delivery: false,
        featured: true,
        todays_deal: false,
        flash_deal_id: 9,
        product_types: ['Cold pressed', 'Organic'],
        product_barcodes: ['8712345678906'],
        ...overrides,
    };
}
/** A product's stored size choice with its two stored stock rows. */
export function storeProductVariantState(
    overrides: Partial<StoreProductVariantState> = {},
): StoreProductVariantState {
    return {
        colors_active: false,
        colors: [],
        choice_options: [{ attribute_id: 1, values: ['S', 'Extra Large'] }],
        attribute_values: {
            1: [
                { id: 10, value: 'S' },
                { id: 11, value: 'Extra Large' },
            ],
        },
        rows: [
            {
                variant: 'S',
                price: '13.00',
                sku: 'OIL-S',
                qty: '4',
                image: '12',
                files: [storeUpload({ id: 12, name: 'Small bottle' })],
            },
            {
                variant: 'ExtraLarge',
                price: '15.00',
                sku: '',
                qty: '0',
                image: null,
                files: [],
            },
        ],
        ...overrides,
    };
}
export function storeDigitalProductTax(
    overrides: Partial<StoreDigitalProductTax> = {},
): StoreDigitalProductTax {
    return {
        id: 2,
        name: 'VAT',
        tax: '21.00',
        tax_type: 'percent',
        ...overrides,
    };
}
export function storeProductListRow(
    overrides: Partial<StoreProductListRow> = {},
): StoreProductListRow {
    return {
        id: 41,
        name: 'Olive oil 1L',
        ar_name: 'زيت زيتون',
        thumbnail_url: '/assets/img/placeholder.jpg',
        mat_id: '100027',
        unit: 'Box(12)',
        num_of_sale: 7,
        base_price: '€12.50',
        profit: '€1.25',
        variant_stocks: null,
        stock_quantity: 40,
        low_stock: false,
        category: 'Oils',
        for_sale: true,
        published: true,
        featured: false,
        deleted_from_api: false,
        public_url: '/temp/product/olive-oil-1l',
        ...overrides,
    };
}
export function storeProductListFilters(
    overrides: Partial<StoreProductListFilters> = {},
): StoreProductListFilters {
    return {
        search: null,
        category_id: null,
        type: null,
        active: false,
        for_sale: false,
        featured: false,
        per_page: 20,
        ...overrides,
    };
}
export function storeCategoryForm(
    overrides: Partial<StoreCategoryForm> = {},
): StoreCategoryForm {
    return {
        id: 17,
        name: 'Food',
        parent_id: 0,
        order_level: 3,
        digital: false,
        banner: '4',
        icon: null,
        cover_image: null,
        commision_rate: '2.50',
        slug: 'food',
        meta_title: 'Food SEO',
        meta_description: 'Food description',
        filtering_attributes: [2],
        ...overrides,
    };
}

export function representativeVisitDetail(
    overrides: Partial<RepresentativeVisitDetail> = {},
): RepresentativeVisitDetail {
    return {
        id: 81,
        note: 'Visit note',
        visit_date: '2026-10-09 12:00:00',
        representative: { id: 18, name: 'Representative Aya' },
        customer: {
            id: 21,
            name: 'Customer Noor',
            company_name: 'Noor BV',
            email: 'noor@example.com',
            phone: '0612345678',
            member_serial: '42',
            accounting_id: 'AC-21',
        },
        order: {
            id: 31,
            code: 'VISIT-31',
            delivery_status: 'delivered',
            date: 1791547200,
            amount: '€12.50',
            payment_type: 'Cash on delivery',
            customer_notes: 'Leave at reception',
            customer_otajer_id: 'OT-21',
            delivery_date: '2026-10-10',
        },
        ...overrides,
    };
}
export function representativeVisitMarker(
    overrides: Partial<RepresentativeVisitMarker> = {},
): RepresentativeVisitMarker {
    return {
        id: 81,
        lat: 0,
        lng: 0,
        rank: 1,
        has_order: true,
        label: 'Customer Noor',
        ...overrides,
    };
}
