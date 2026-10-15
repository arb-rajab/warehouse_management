import { expiryInfo, remainingPercent } from '../domain/expiry';
import type { Locale, Slot, SlotState } from '../domain/types';
import { formatDate } from '../i18n/format';
import { el } from '../utils/dom';
import type { Child } from '../utils/dom';

type T = (key: string, params?: Record<string, string | number>) => string;

export function stateLabel(state: SlotState, t: T): string {
    if (state === 'opened') {
        return t('stOpened');
    }

    return state === 'full' ? t('stFull') : t('stEmpty');
}

/** The expiry date with how long is left ("Expires 12 Dec 2026 · 60 d left"), tinted when soon or past. */
export function expiryText(
    slot: Slot,
    locale: Locale,
    t: T,
): { text: string; tone: 'ok' | 'soon' | 'bad' } | null {
    if (!slot.pallet?.expirationDate) {
        return null;
    }

    const info = expiryInfo(slot.expiresInDays);
    const date = `${t('expires')} ${formatDate(slot.pallet.expirationDate, locale)}`;

    if (!info) {
        return { text: date, tone: 'ok' };
    }

    return {
        text: `${date} · ${t(info.expired ? 'daysAgo' : 'daysLeft', { count: info.days })}`,
        tone: info.tone,
    };
}

export function boxesText(slot: Slot, t: T): string | null {
    const p = slot.pallet;

    if (!p || p.remainingBoxes === null || !p.productBoxesCount) {
        return null;
    }

    return `${p.remainingBoxes} / ${p.productBoxesCount} ${t('boxes')}`;
}

export function meter(slot: Slot, className: string): HTMLElement | null {
    const percent = remainingPercent(
        slot.pallet?.remainingBoxes ?? null,
        slot.pallet?.productBoxesCount ?? null,
    );

    if (percent === null) {
        return null;
    }

    const fill = el('i', { vars: { '--wms-fill': `${percent}%` } });

    return el(
        'div',
        { class: `${className}${slot.state === 'opened' ? ' is-opened' : ''}` },
        fill,
    );
}

export function stateDot(state: SlotState): HTMLElement {
    return el('span', { class: `wms-state-dot wms-state-dot--${state}` });
}

export function slotCode(code: string): HTMLElement {
    return el('span', { class: 'wms-slotcode', text: code });
}

export function tag(
    text: string,
    kind: 'full' | 'opened' | 'empty' | 'off',
): HTMLElement {
    return el('span', { class: `wms-tag wms-tag--${kind}`, text });
}

export function line(...children: Child[]): HTMLElement {
    return el('span', {}, ...children);
}
