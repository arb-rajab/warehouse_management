import { describe, expect, it, vi } from 'vitest';
import { isolateLtr } from './bidi';
import { I18n } from './I18n';

const en = {
    title: 'Warehouse Map',
    matches: '{count} match | {count} matches',
    daysLeft: '{count} d left',
    only: 'English only',
};
const ar = { title: 'خريطة المستودع', matches: '{count} نتيجة' };

describe('I18n', () => {
    it('translates, interpolates and falls back to English then the key', () => {
        const i18n = new I18n('ar', ar, en);

        expect(i18n.t('title')).toBe('خريطة المستودع');
        expect(i18n.t('only')).toBe('English only');
        expect(i18n.t('missing.key')).toBe('missing.key');
        expect(i18n.t('daysLeft', { count: 5 })).toBe('5 d left');
    });

    it('picks the singular or plural form of a count', () => {
        const i18n = new I18n('en', en);

        expect(i18n.t('matches', { count: 1 })).toBe('1 match');
        expect(i18n.t('matches', { count: 0 })).toBe('0 matches');
        expect(i18n.t('matches', { count: 7 })).toBe('7 matches');
    });

    it('uses a single form for a language that gives one', () => {
        expect(new I18n('ar', ar).t('matches', { count: 1 })).toBe('1 نتيجة');
        expect(new I18n('ar', ar).t('matches', { count: 12 })).toBe('12 نتيجة');
    });

    it('leaves an unknown placeholder alone', () => {
        expect(new I18n('en', { a: 'x {y}' }).t('a')).toBe('x {y}');
    });

    it('reads the direction from the locale', () => {
        expect(new I18n('ar', ar).dir).toBe('rtl');
        expect(new I18n('en', en).dir).toBe('ltr');
    });

    it('tells listeners when the language changes', () => {
        const i18n = new I18n('en', en);
        const listener = vi.fn();
        const off = i18n.onChange(listener);

        i18n.setMessages('ar', ar, en);
        expect(listener).toHaveBeenCalledTimes(1);
        expect(i18n.locale).toBe('ar');
        expect(i18n.t('title')).toBe('خريطة المستودع');

        off();
        i18n.setMessages('en', en);
        expect(listener).toHaveBeenCalledTimes(1);
    });
});

describe('isolateLtr', () => {
    it('wraps a code in a left-to-right isolate', () => {
        expect(isolateLtr('A5- 2')).toBe('⁦A5- 2⁩');
    });
});
