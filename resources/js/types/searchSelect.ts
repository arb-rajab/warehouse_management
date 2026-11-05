/**
 * The shape every store back office search endpoint returns for
 * StoreSearchSelect (.ai/rules/store-search-select.md). The server builds it
 * with App\Support\SearchOption; `extra` carries the domain data a page needs
 * after a pick (a unit price, a state id), never anything the row displays.
 */
export type SearchOptionIcon =
    'user' | 'package' | 'map-pin' | 'tag' | 'truck' | 'mail';

export type SearchOptionTone =
    'neutral' | 'success' | 'warning' | 'danger' | 'info';

export type SearchOption<E extends object = Record<string, unknown>> = {
    /** Posted as the form value; the newsletter recipients use the email. */
    id: number | string;
    /** The primary line, highlighted against the query. */
    label: string;
    /** The secondary line (`a · b · c`), highlighted against the query. */
    description?: string | null;
    /** A thumbnail or logo URL (http or https); without one the row shows its icon or initials. */
    image?: string | null;
    /** The fallback leading tile when there is no image; `user` shows initials. */
    icon?: SearchOptionIcon | null;
    badge?: { text: string; tone: SearchOptionTone } | null;
    extra?: E;
};

export type SearchPage<T> = {
    data: T[];
    meta: { current_page: number; last_page: number; total?: number };
};

export type SearchParams = Record<
    string,
    string | number | boolean | null | undefined
>;
