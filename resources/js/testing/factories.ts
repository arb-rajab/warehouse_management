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
import type { StoreTicketReply } from '@/types/store';
import type { StoreTicketDetail } from '@/types/store';
import type { StoreWebsitePageForm } from '@/types/store';
import type { StoreWebsiteAppearanceSettings } from '@/types/store';
import type { StoreWebsiteFooterSettings } from '@/types/store';
import type {
    PermissionGroup,
    StaffMember,
    StoreRole,
    StoreSharedProps,
    StoreConversation,
    StoreMessage,
    StoreProductQuery,
    StoreUpload,
    StoreWebsiteHeaderSettings,
    StoreWebsiteHomeSettings,
    StoreWebsitePage,
    StoreSupportTicket,
} from '@/types/store';

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
        },
        location: { row_letter: 'A', cell_number: 3, flat_number: 2 },
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

/** UploadResource: a stored PNG, as the uploads library lists it. */
export function storeUpload(overrides: Partial<StoreUpload> = {}): StoreUpload {
    return {
        id: 31,
        name: 'shop front',
        extension: 'png',
        type: 'image',
        size: 2048,
        url: 'http://localhost/uploads/all/shop-front.png',
        created_at: '2026-10-01T09:30:00+00:00',
        uploaded_by: 'Sam Staff',
        ...overrides,
    };
}

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

export function storeWebsiteFooterSettings(
    overrides: Partial<StoreWebsiteFooterSettings> = {},
): StoreWebsiteFooterSettings {
    return {
        ...{
            footer_logo: '',
            about_us_description: '<p>About us</p>',
            play_store_link: '',
            app_store_link: '',
            contact_address: '',
            contact_phone: '',
            contact_email: '',
            widget_one: '',
            widget_one_labels: ['Help'],
            widget_one_links: ['/help'],
            frontend_copyright_text: '',
            show_social_links: false,
            facebook_link: '',
            twitter_link: '',
            instagram_link: '',
            youtube_link: '',
            linkedin_link: '',
            seller_app_link: 'https://example.com/seller',
            payment_method_images: '',
            registration_tutorial_button: false,
            registration_tutorial_text: '',
            registration_tutorial_link: '',
        },
        ...overrides,
    };
}

export function storeWebsiteAppearanceSettings(
    overrides: Partial<StoreWebsiteAppearanceSettings> = {},
): StoreWebsiteAppearanceSettings {
    return {
        ...{
            website_name: 'Store',
            site_motto: '',
            site_icon: '',
            base_color: '#112233',
            base_hov_color: '#123',
            login_page_image: '',
            register_page_image: '',
            seller_login_page_bg: '',
            flash_deal_banner: '',
            flash_deal_banner_small: '',
            meta_title: '',
            meta_description: '',
            meta_keywords: '',
            meta_image: '',
            cookies_agreement_text: '',
            show_cookies_agreement: false,
            show_website_popup: false,
            website_popup_content: '',
            show_subscribe_form: false,
        },
        ...overrides,
    };
}

export function storeWebsitePageForm(
    overrides: Partial<StoreWebsitePageForm> = {},
): StoreWebsitePageForm {
    return {
        title: 'About',
        slug: 'about',
        content: '<p>About us</p>',
        meta_title: 'Meta',
        meta_description: 'Description',
        keywords: 'store',
        meta_image: '',
        ...overrides,
    };
}
export function storeTicketDetail(
    overrides: Partial<StoreTicketDetail> = {},
): StoreTicketDetail {
    return {
        id: 1,
        code: 123456,
        subject: 'Help',
        status: 'pending',
        author: 'Customer',
        created_at: '2026-10-09T10:00:00Z',
        details_html: '<p>Safe customer detail</p>',
        attachments: [
            { id: 7, name: 'ticket.png', url: '/uploads/all/ticket.png' },
        ],
        ...overrides,
    };
}
export function storeTicketReply(
    overrides: Partial<StoreTicketReply> = {},
): StoreTicketReply {
    return {
        id: 1,
        author: 'Staff',
        created_at: '2026-10-09T11:00:00Z',
        reply_html: '<b>Safe reply</b>',
        attachments: [
            { id: 8, name: 'reply.png', url: '/uploads/all/reply.png' },
        ],
        ...overrides,
    };
}
