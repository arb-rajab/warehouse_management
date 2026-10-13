import { afterEach, describe, expect, it, vi } from 'vitest';
import { click, fakeSlice, host, keyI18n } from '../testing/ui';
import { Notch } from './Notch';
import { SearchBox } from './SearchBox';
import { StateChips } from './StateChips';
import { Toolbar } from './Toolbar';
import type { ToolbarState } from './Toolbar';

afterEach(() => {
    document.body.innerHTML = '';
    vi.useRealTimers();
});

function searchBox(query = '') {
    const slice = fakeSlice({ query });
    const intents = { setText: vi.fn(), commit: vi.fn(), clear: vi.fn() };
    const box = new SearchBox(slice, keyI18n(), intents);

    return { box, slice, intents };
}

describe('SearchBox', () => {
    it('sends what is typed at once and commits it after a pause', () => {
        vi.useFakeTimers();

        const { box, intents } = searchBox();

        box.mount();
        box.input.value = 'basmati rice';
        box.input.dispatchEvent(new Event('input'));

        expect(intents.setText).toHaveBeenCalledWith('basmati rice');
        expect(intents.commit).not.toHaveBeenCalled();

        vi.advanceTimersByTime(250);
        expect(intents.commit).toHaveBeenCalledTimes(1);
        box.dispose();
    });

    it('commits only the last of a burst of keystrokes', () => {
        vi.useFakeTimers();

        const { box, intents } = searchBox();

        box.mount();

        for (const text of ['r', 'ri', 'ric', 'rice']) {
            box.input.value = text;
            box.input.dispatchEvent(new Event('input'));
            vi.advanceTimersByTime(100);
        }

        vi.advanceTimersByTime(300);
        expect(intents.commit).toHaveBeenCalledTimes(1);
        box.dispose();
    });

    it('clears at once, cancelling a pending commit', () => {
        vi.useFakeTimers();

        const { box, intents } = searchBox('rice');

        box.mount();
        box.input.value = 'rice!';
        box.input.dispatchEvent(new Event('input'));
        click(box.element.querySelector('.wms-search__clear'));
        vi.advanceTimersByTime(500);

        expect(intents.clear).toHaveBeenCalled();
        expect(intents.commit).not.toHaveBeenCalled();
        box.dispose();
    });

    it('shows the stored text and the clear button only when there is text', () => {
        const { box, slice } = searchBox();

        box.mount();
        expect(box.element.classList.contains('has-text')).toBe(false);

        slice.set({ query: 'sugar' });
        expect(box.input.value).toBe('sugar');
        expect(box.element.classList.contains('has-text')).toBe(true);
        expect(box.input.placeholder).toBe('searchPh');
        box.dispose();
    });

    it('does not render typed text as markup', () => {
        const { box } = searchBox('<img src=x onerror=alert(1)>');

        box.mount();

        expect(box.element.querySelector('img')).toBeNull();
        box.dispose();
    });
});

describe('StateChips', () => {
    it('lights the chosen state and the expiring chip, and reports clicks', () => {
        const slice = fakeSlice({ state: 'all' as const, expiring: false });
        const intents = { setState: vi.fn(), toggleExpiring: vi.fn() };
        const chips = new StateChips(slice as never, keyI18n(), intents);

        chips.mount();

        const buttons = [...chips.element.querySelectorAll('button')];

        expect(buttons.map((b) => b.textContent)).toEqual([
            'stAll',
            'stFull',
            'stOpened',
            'stEmpty',
            'stExpiring',
        ]);
        expect(buttons[0].classList.contains('is-on')).toBe(true);

        click(buttons[2]);
        expect(intents.setState).toHaveBeenCalledWith('opened');

        click(buttons[4]);
        expect(intents.toggleExpiring).toHaveBeenCalled();

        slice.set({ state: 'full' as never, expiring: true });
        expect(buttons[1].getAttribute('aria-pressed')).toBe('true');
        expect(buttons[0].getAttribute('aria-pressed')).toBe('false');
        expect(buttons[4].classList.contains('is-on')).toBe(true);
        chips.dispose();
    });
});

function toolbar(partial: Partial<ToolbarState> = {}, helpUrl?: string) {
    const root = host();
    const slice = fakeSlice<ToolbarState>({
        view: '2d',
        theme: 'dark',
        searchOpen: false,
        locale: 'en',
        ...partial,
    });
    const intents = {
        setView: vi.fn(),
        openLegend: vi.fn(),
        openHelp: vi.fn(),
        toggleTheme: vi.fn(),
        switchLocale: vi.fn(),
        toggleSearchOpen: vi.fn(),
    };
    const i18n = keyI18n();
    const search = new SearchBox(fakeSlice({ query: '' }), i18n, {
        setText: vi.fn(),
        commit: vi.fn(),
        clear: vi.fn(),
    });
    const chips = new StateChips(
        fakeSlice({ state: 'all' as const, expiring: false }) as never,
        i18n,
        { setState: vi.fn(), toggleExpiring: vi.fn() },
    );
    const bar = new Toolbar(root, slice, i18n, intents, search, chips, helpUrl);

    bar.mount();

    return { bar, slice, intents, root };
}

describe('Toolbar', () => {
    it('shows the brand and the 2D | 3D toggle with the active view lit', () => {
        const { bar, root, slice } = toolbar();

        expect(root.querySelector('.wms-brand__title')?.textContent).toBe(
            'appTitle',
        );

        const [two, three] = [...root.querySelectorAll('.wms-seg button')];

        expect(two.classList.contains('is-on')).toBe(true);
        slice.set({ view: '3d' });
        expect(three.classList.contains('is-on')).toBe(true);
        expect(two.getAttribute('aria-pressed')).toBe('false');
        bar.dispose();
    });

    it('switches view, opens the legend and help, toggles theme and search', () => {
        const { bar, root, intents } = toolbar();
        const [two, three] = [...root.querySelectorAll('.wms-seg button')];

        click(three);
        click(two);
        expect(intents.setView.mock.calls).toEqual([['3d'], ['2d']]);

        const buttons = root.querySelectorAll('.wms-actions .wms-icon-btn');

        click(buttons[0]);
        click(buttons[1]);
        click(buttons[2]);
        click(root.querySelector('.wms-search-toggle'));
        expect(intents.openLegend).toHaveBeenCalled();
        expect(intents.openHelp).toHaveBeenCalled();
        expect(intents.toggleTheme).toHaveBeenCalled();
        expect(intents.toggleSearchOpen).toHaveBeenCalled();
        bar.dispose();
    });

    it('offers the other language on the language button', () => {
        const english = toolbar();

        click(english.root.querySelector('.wms-icon-btn--lang'));
        expect(english.intents.switchLocale).toHaveBeenCalledWith('ar');
        expect(
            english.root.querySelector('.wms-icon-btn--lang')?.textContent,
        ).toBe('ع');
        english.bar.dispose();

        const arabic = toolbar({ locale: 'ar' });

        click(arabic.root.querySelector('.wms-icon-btn--lang'));
        expect(arabic.intents.switchLocale).toHaveBeenCalledWith('en');
        expect(
            arabic.root.querySelector('.wms-icon-btn--lang')?.textContent,
        ).toBe('EN');
        arabic.bar.dispose();
    });

    it('folds the search behind a button on a phone', () => {
        const { bar, root, slice } = toolbar();
        const top = root.querySelector('.wms-topbar')!;

        expect(top.classList.contains('is-searching')).toBe(false);
        slice.set({ searchOpen: true });
        expect(top.classList.contains('is-searching')).toBe(true);
        expect(
            root
                .querySelector('.wms-search-toggle')
                ?.getAttribute('aria-expanded'),
        ).toBe('true');
        bar.dispose();
    });

    it('links to the help guide only when it has one', () => {
        const without = toolbar();

        expect(without.root.querySelector('.wms-guide')).toBeNull();
        without.bar.dispose();

        const withGuide = toolbar({}, '/admin/help');

        expect(
            withGuide.root.querySelector('.wms-guide')?.getAttribute('href'),
        ).toBe('/admin/help');
        expect(
            withGuide.root
                .querySelector('.wms-guide')
                ?.getAttribute('aria-label'),
        ).toBe('guide');
        withGuide.bar.dispose();
    });

    it('removes itself on dispose', () => {
        const { bar, root } = toolbar();

        bar.dispose();

        expect(root.querySelector('.wms-topbar')).toBeNull();
    });
});

describe('Notch', () => {
    it('toggles the toolbar and says whether it is open', () => {
        const root = host();
        const slice = fakeSlice({ folded: false });
        const toggle = vi.fn();
        const notch = new Notch(root, slice, keyI18n(), toggle);

        notch.mount();
        click(notch.element);
        expect(toggle).toHaveBeenCalled();
        expect(notch.element.getAttribute('aria-expanded')).toBe('true');

        slice.set({ folded: true });
        expect(notch.element.getAttribute('aria-expanded')).toBe('false');
        notch.dispose();
        expect(root.querySelector('.wms-notch')).toBeNull();
    });
});
