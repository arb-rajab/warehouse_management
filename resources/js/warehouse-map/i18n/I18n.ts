import type { Locale } from '../domain/types';

export type Messages = Record<string, string>;
export type Params = Record<string, string | number>;

const RTL_LOCALES: ReadonlySet<string> = new Set(['ar']);

/**
 * A tiny translator over a flat key -> message dictionary (the panel's
 * `warehouseMap` locale subtree). Messages use `{name}` placeholders, and
 * `one | other` for a count (`{count}`) in languages that distinguish them.
 */
export class I18n {
    private listeners = new Set<() => void>();

    constructor(
        private currentLocale: Locale,
        private messages: Messages,
        private fallback: Messages = {},
    ) {}

    get locale(): Locale {
        return this.currentLocale;
    }

    get dir(): 'rtl' | 'ltr' {
        return RTL_LOCALES.has(this.currentLocale) ? 'rtl' : 'ltr';
    }

    setMessages(locale: Locale, messages: Messages, fallback?: Messages): void {
        this.currentLocale = locale;
        this.messages = messages;

        if (fallback) {
            this.fallback = fallback;
        }

        this.listeners.forEach((listener) => listener());
    }

    onChange(listener: () => void): () => void {
        this.listeners.add(listener);

        return () => this.listeners.delete(listener);
    }

    t = (key: string, params: Params = {}): string => {
        const raw = this.messages[key] ?? this.fallback[key] ?? key;

        return interpolate(selectPlural(raw, params.count), params);
    };
}

function selectPlural(
    message: string,
    count: string | number | undefined,
): string {
    if (!message.includes(' | ')) {
        return message;
    }

    const forms = message.split(' | ');

    return Number(count) === 1 ? forms[0] : (forms[1] ?? forms[0]);
}

function interpolate(message: string, params: Params): string {
    return message.replace(/\{(\w+)\}/g, (whole, name: string) =>
        name in params ? String(params[name]) : whole,
    );
}
