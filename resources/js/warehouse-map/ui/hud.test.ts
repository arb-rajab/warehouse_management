import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { I18n } from '../i18n/I18n';
import { click, fakeSlice, host, keyI18n } from '../testing/ui';
import { CaptureNotice } from './CaptureNotice';
import { Dialog } from './Dialog';
import { GlobalShortcuts } from './GlobalShortcuts';
import { HintBar } from './HintBar';
import { HudStack } from './HudStack';
import { icon, iconSprite } from './icons';
import { keyCaps, keyRows } from './keyHints';
import { KeysPanel } from './KeysPanel';
import { LoadStatus } from './LoadStatus';
import { RootAttributes } from './RootAttributes';
import type { RootState } from './RootAttributes';
import { WelcomeCard } from './WelcomeCard';

beforeEach(() => {
    vi.useFakeTimers();
});

afterEach(() => {
    vi.useRealTimers();
    document.body.innerHTML = '';
});

describe('HudStack', () => {
    it('keeps every floating message in one column, in a fixed order', () => {
        const root = host();
        const stack = new HudStack(root);

        stack.mount();

        expect(
            [...stack.element.children].map(
                (c) => (c as HTMLElement).dataset.slot,
            ),
        ).toEqual([
            'selection',
            'details',
            'capture',
            'welcome',
            'hint',
            'touchhint',
        ]);
        expect(root.querySelectorAll('.wms-stack')).toHaveLength(1);
        stack.dispose();
        expect(root.querySelector('.wms-stack')).toBeNull();
    });

    it('shows and hides a message without moving the others', () => {
        const stack = new HudStack(host());

        stack.mount();
        stack.show('hint', true);
        expect(stack.slot('hint').classList.contains('is-shown')).toBe(true);
        expect(stack.slot('details').classList.contains('is-shown')).toBe(
            false,
        );

        stack.show('hint', false);
        expect(stack.slot('hint').classList.contains('is-shown')).toBe(false);
        stack.dispose();
    });
});

describe('HintBar', () => {
    function hints(partial = {}) {
        const hintSlot = host();
        const touchSlot = host();
        const show = vi.fn();
        const slice = fakeSlice({
            hint: null as { text: string; ms: number; seq: number } | null,
            view: '2d' as '2d' | '3d',
            mode: 'overview' as 'overview' | 'walk' | 'fly',
            inputKind: 'mouse' as 'mouse' | 'touch',
            nav: 'free' as 'free' | 'guided',
            ...partial,
        });
        const view = new HintBar(hintSlot, touchSlot, show, slice, keyI18n());

        view.mount();

        return { view, slice, show, hintSlot, touchSlot };
    }

    it('says how to move the map you are looking at, then goes away', () => {
        const { view, show, hintSlot } = hints();

        expect(hintSlot.textContent).toBe('hintPlan');
        expect(show).toHaveBeenLastCalledWith('hint', true);

        vi.advanceTimersByTime(9000);
        expect(show).toHaveBeenLastCalledWith('hint', false);
        view.dispose();
    });

    it('gives touch wording on a touch screen and 3D wording in the overview', () => {
        const touch = hints({ inputKind: 'touch' });

        expect(touch.hintSlot.textContent).toBe('hintPlanTouch');
        touch.view.dispose();

        const overview = hints({ view: '3d' });

        expect(overview.hintSlot.textContent).toBe('hintOver');
        overview.view.dispose();
    });

    it('shows nothing extra while walking', () => {
        const { view, slice, show } = hints({ view: '3d' });

        slice.set({ mode: 'walk' });
        expect(show).toHaveBeenLastCalledWith('hint', false);
        view.dispose();
    });

    it('shows an explicit message once, even the same text twice', () => {
        const { view, slice, hintSlot } = hints();

        slice.set({ hint: { text: 'Link copied', ms: 2000, seq: 1 } });
        expect(hintSlot.textContent).toBe('Link copied');

        vi.advanceTimersByTime(2000);
        slice.set({ hint: { text: 'Link copied', ms: 2000, seq: 2 } });
        expect(hintSlot.textContent).toBe('Link copied');
        view.dispose();
    });

    it('shows the touch walking hint only for a free walk on a touch screen', () => {
        const { view, slice, show } = hints({
            inputKind: 'touch',
            view: '3d',
            mode: 'walk',
        });

        expect(show).toHaveBeenCalledWith('touchhint', true);

        slice.set({ nav: 'guided' });
        expect(show).toHaveBeenLastCalledWith('touchhint', false);
        view.dispose();
    });
});

describe('CaptureNotice', () => {
    it('tells you to click to look around while the mouse is released', () => {
        const slotEl = host();
        const slice = fakeSlice({ visible: false });
        const show = vi.fn();
        const notice = new CaptureNotice(slotEl, slice, keyI18n(), show);

        notice.mount();
        expect(show).toHaveBeenLastCalledWith(false);

        slice.set({ visible: true });
        expect(show).toHaveBeenLastCalledWith(true);
        expect(slotEl.textContent).toBe('captureTitlecaptureSub');
        notice.dispose();
    });
});

describe('WelcomeCard', () => {
    function welcome(visits = 0) {
        const slotEl = host();
        const slice = fakeSlice({ eligible: false, walkedEnough: false });
        let seen = visits;
        const intents = {
            visits: () => seen,
            countVisit: vi.fn(() => seen++),
            setVisible: vi.fn(),
        };
        const show = vi.fn();
        const card = new WelcomeCard(slotEl, slice, keyI18n(), intents, show);

        card.mount();

        return { card, slice, intents, show, slotEl };
    }

    it('welcomes you on the first visits to the free walk and counts the visit', () => {
        const { card, slice, intents, show, slotEl } = welcome();

        slice.set({ eligible: true });

        expect(intents.countVisit).toHaveBeenCalledTimes(1);
        expect(show).toHaveBeenLastCalledWith(true);
        expect(intents.setVisible).toHaveBeenLastCalledWith(true);
        expect(slotEl.querySelectorAll('.wms-wasd kbd')).toHaveLength(4);
        expect(slotEl.textContent).toContain('welcomeTitle');
        card.dispose();
    });

    it('stops welcoming after three visits', () => {
        const { card, slice, intents } = welcome(3);

        slice.set({ eligible: true });

        expect(intents.countVisit).not.toHaveBeenCalled();
        card.dispose();
    });

    it('goes away after a few seconds of walking, when you leave the free walk, or by itself', () => {
        const walked = welcome();

        walked.slice.set({ eligible: true });
        walked.slice.set({ walkedEnough: true });
        expect(walked.show).toHaveBeenLastCalledWith(false);
        walked.card.dispose();

        const left = welcome();

        left.slice.set({ eligible: true });
        left.slice.set({ eligible: false });
        expect(left.show).toHaveBeenLastCalledWith(false);
        left.card.dispose();

        const timed = welcome();

        timed.slice.set({ eligible: true });
        vi.advanceTimersByTime(16000);
        expect(timed.show).toHaveBeenLastCalledWith(false);
        timed.card.dispose();
    });

    it('does not come back on re-entering within the same visit count', () => {
        const { card, slice, intents } = welcome(2);

        slice.set({ eligible: true });
        slice.set({ eligible: false });
        slice.set({ eligible: true });

        expect(intents.countVisit).toHaveBeenCalledTimes(1);
        card.dispose();
    });
});

describe('KeysPanel', () => {
    function keys() {
        const root = host();
        const slice = fakeSlice({
            kind: 'overview' as 'plan' | 'overview' | 'free' | 'guided',
            aisles: 3,
            walking: false,
        });
        const panel = new KeysPanel(root, slice, keyI18n());

        panel.mount();

        return { panel, slice, root };
    }

    it('lists the keys for what you are doing', () => {
        const { panel, slice, root } = keys();

        expect(root.querySelector('.wms-keys__head')?.textContent).toBe('help');
        expect(root.textContent).toContain('kOrbit');

        slice.set({ kind: 'guided' });
        expect(root.textContent).toContain('gStep');
        panel.dispose();
    });

    it('appears when the walk begins, fades after a while, and comes back with H', () => {
        const { panel, slice, root } = keys();
        const el = root.querySelector('.wms-keys')!;

        slice.set({ walking: true });
        expect(el.classList.contains('is-faded')).toBe(false);

        vi.advanceTimersByTime(12000);
        expect(el.classList.contains('is-faded')).toBe(true);

        panel.toggle();
        expect(el.classList.contains('is-faded')).toBe(false);
        panel.toggle();
        expect(el.classList.contains('is-faded')).toBe(true);
        panel.dispose();
    });
});

describe('keyHints', () => {
    it('offers different keys for the plan, the overview, free walk and guided walk', () => {
        const kinds = (['plan', 'overview', 'free', 'guided'] as const).map(
            (k) => keyRows(k, 3, false).map((r) => r.description),
        );

        expect(new Set(kinds.map((k) => k.join()))).toHaveProperty('size', 4);
        expect(keyRows('free', 3, true).length).toBeLessThan(
            keyRows('free', 3, false).length + 1,
        );
        expect(
            keyRows('guided', 3, false).some((r) => r.keys.includes('Q')),
        ).toBe(true);
    });

    it('says 1-0 once there are more than nine aisles', () => {
        expect(
            keyRows('overview', 9, false).some((r) => r.keys[0] === '1–9'),
        ).toBe(true);
        expect(
            keyRows('overview', 10, false).some((r) => r.keys[0] === '1–0'),
        ).toBe(true);
    });

    it('translates the words among the keys but not the letters', () => {
        const caps = keyCaps(['k:kClick', 'F', 'Esc'], (key) => `T(${key})`);

        expect(caps.map((c) => c.textContent)).toEqual([
            'T(kClick)',
            'F',
            'Esc',
        ]);
    });
});

describe('GlobalShortcuts', () => {
    function shortcuts(view: '2d' | '3d' = '2d', overlay = false) {
        const plan = {
            fit: vi.fn(),
            zoomBy: vi.fn(),
            rotate: vi.fn(),
            reveal: vi.fn(),
        };
        const intents = {
            closeTopOverlay: vi.fn(() => true),
            toggleFolded: vi.fn(),
            toggleHelp: vi.fn(),
            focusSearch: vi.fn(),
            resetPlanOrientation: vi.fn(),
            view: () => view,
            isOverlayOpen: () => overlay,
        };
        const s = new GlobalShortcuts(intents, plan);

        s.mount();

        const press = (init: KeyboardEventInit, target: EventTarget = window) =>
            target.dispatchEvent(
                new KeyboardEvent('keydown', {
                    bubbles: true,
                    cancelable: true,
                    ...init,
                }),
            );

        return { s, plan, intents, press };
    }

    it('closes the top overlay on Escape', () => {
        const { s, intents, press } = shortcuts();

        press({ key: 'Escape' });
        expect(intents.closeTopOverlay).toHaveBeenCalled();
        s.dispose();
    });

    it('focuses the search on /, folds the bars on T, toggles help on ?', () => {
        const { s, intents, press } = shortcuts();

        press({ key: '/' });
        press({ key: 't', code: 'KeyT' });
        press({ key: '?' });
        expect(intents.focusSearch).toHaveBeenCalled();
        expect(intents.toggleFolded).toHaveBeenCalled();
        expect(intents.toggleHelp).toHaveBeenCalled();
        s.dispose();
    });

    it('drives the 2D plan with 0, R, + and -', () => {
        const { s, plan, intents, press } = shortcuts();

        press({ key: '0' });
        press({ key: 'r', code: 'KeyR' });
        press({ key: '+' });
        press({ key: '-' });
        expect(intents.resetPlanOrientation).toHaveBeenCalled();
        expect(plan.fit).toHaveBeenCalled();
        expect(plan.rotate).toHaveBeenCalled();
        expect(plan.zoomBy.mock.calls.map((c) => c[0])).toEqual([1.25, 0.8]);
        s.dispose();
    });

    it('leaves the plan keys alone in 3D and while a dialog is open', () => {
        const three = shortcuts('3d');

        three.press({ key: 'r', code: 'KeyR' });
        expect(three.plan.rotate).not.toHaveBeenCalled();
        three.s.dispose();

        const dialog = shortcuts('2d', true);

        dialog.press({ key: '+' });
        expect(dialog.plan.zoomBy).not.toHaveBeenCalled();
        dialog.s.dispose();
    });

    it('never hijacks a browser shortcut or a key typed into a field', () => {
        const { s, intents, press } = shortcuts();

        press({ key: '/', ctrlKey: true });
        press({ key: 't', code: 'KeyT', metaKey: true });
        expect(intents.focusSearch).not.toHaveBeenCalled();
        expect(intents.toggleFolded).not.toHaveBeenCalled();

        const input = document.createElement('input');

        document.body.append(input);
        input.focus();
        press({ key: 't', code: 'KeyT' }, input);
        expect(intents.toggleFolded).not.toHaveBeenCalled();

        const blur = vi.spyOn(input, 'blur');

        press({ key: 'Escape' }, input);
        expect(blur).toHaveBeenCalled();
        expect(intents.closeTopOverlay).not.toHaveBeenCalled();
        s.dispose();
    });
});

describe('RootAttributes', () => {
    const state: RootState = {
        view: '2d',
        mode: 'overview',
        nav: 'free',
        inputKind: 'mouse',
        folded: false,
        theme: 'dark',
        captured: false,
        modal: false,
        welcome: false,
        magnet: true,
        away: false,
        locale: 'en',
    };

    it('puts the page state on data attributes and follows the language direction', () => {
        const root = host();
        const slice = fakeSlice(state);
        const i18n = new I18n('en', {});
        const attrs = new RootAttributes(root, slice, i18n);

        attrs.mount();
        expect(root.dataset.view).toBe('2d');
        expect(root.dataset.input).toBe('mouse');
        expect(root.dataset.theme).toBe('dark');
        expect(root.getAttribute('dir')).toBe('ltr');
        expect(root.className).toBe('');

        slice.set({
            view: '3d',
            mode: 'walk',
            nav: 'guided',
            captured: true,
            folded: true,
        });
        expect(root.dataset.view).toBe('3d');
        expect(root.dataset.mode).toBe('walk');
        expect(root.dataset.nav).toBe('guided');
        expect(root.dataset.captured).toBe('true');
        expect(root.dataset.folded).toBe('true');

        i18n.setMessages('ar', {});
        expect(root.getAttribute('dir')).toBe('rtl');
        expect(root.getAttribute('lang')).toBe('ar');
        attrs.dispose();
    });
});

describe('Dialog', () => {
    function dialog() {
        const root = host();
        const onClose = vi.fn();
        const d = new Dialog(root, onClose, 'default', 'Label');

        d.mount();

        return { d, root, onClose };
    }

    it('is a modal dialog, labelled, and closed until shown', () => {
        const { d, root } = dialog();
        const panel = root.querySelector('[role="dialog"]')!;

        expect(panel.getAttribute('aria-modal')).toBe('true');
        expect(panel.getAttribute('aria-label')).toBe('Label');
        expect(d.isOpen).toBe(false);

        d.show('Hello');
        expect(d.isOpen).toBe(true);
        expect(panel.textContent).toBe('Hello');
        d.dispose();
    });

    it('keeps Tab inside while open', () => {
        const { d, root } = dialog();
        const first = document.createElement('button');
        const last = document.createElement('button');

        d.show(first, last);
        last.focus();
        root.querySelector('[role="dialog"]')!.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'Tab',
                bubbles: true,
                cancelable: true,
            }),
        );
        expect(document.activeElement).toBe(first);

        root.querySelector('[role="dialog"]')!.dispatchEvent(
            new KeyboardEvent('keydown', {
                key: 'Tab',
                shiftKey: true,
                bubbles: true,
                cancelable: true,
            }),
        );
        expect(document.activeElement).toBe(last);
        d.dispose();
    });

    it('keeps the scroll position when its content is refreshed', () => {
        const { d, root } = dialog();
        const body = document.createElement('div');

        body.className = 'wms-dialog__body';
        d.show(body);
        body.scrollTop = 40;

        const fresh = document.createElement('div');

        fresh.className = 'wms-dialog__body';
        d.refresh(fresh);
        expect(root.querySelector('.wms-dialog__body')).toBe(fresh);
        d.dispose();
    });
});

describe('LoadStatus', () => {
    it('shows loading, then an error with a retry, then gets out of the way', () => {
        const root = host();
        const slice = fakeSlice({ loaded: false, failed: false });
        const retry = vi.fn();
        const status = new LoadStatus(root, slice, keyI18n(), retry);

        status.mount();
        expect(root.querySelector('.wms-load__text')?.textContent).toBe(
            'loading',
        );
        expect(root.querySelector('button')?.hidden).toBe(true);

        slice.set({ failed: true });
        expect(root.querySelector('.wms-load__text')?.textContent).toBe(
            'loadFailed',
        );
        click(root.querySelector('button'));
        expect(retry).toHaveBeenCalled();

        slice.set({ loaded: true, failed: false });
        expect(
            root.querySelector('.wms-load')?.classList.contains('is-hidden'),
        ).toBe(true);
        status.dispose();
    });
});

describe('icons', () => {
    it('draws an icon from the sprite and builds the sprite from data', () => {
        const svg = icon('search', 'extra');

        expect(svg.getAttribute('class')).toBe('wms-icon extra');
        expect(svg.querySelector('use')?.getAttribute('href')).toBe(
            '#wms-i-search',
        );

        const sprite = iconSprite();

        expect(sprite.querySelector('#wms-i-search')).not.toBeNull();
        expect(sprite.querySelectorAll('symbol').length).toBeGreaterThan(20);
        expect(sprite.querySelector('#wms-i-search circle')).not.toBeNull();
    });
});
