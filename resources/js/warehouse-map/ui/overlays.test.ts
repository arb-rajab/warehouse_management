import { afterEach, describe, expect, it, vi } from 'vitest';
import { DEFAULT_SETTINGS } from '../config';
import { buildMatchIndex } from '../domain/MatchIndex';
import type { Slot } from '../domain/types';
import { makeWarehouse } from '../testing/fixtures';
import { click, fakeSlice, host, keyI18n } from '../testing/ui';
import { HelpDialog } from './HelpDialog';
import { LegendDialog } from './LegendDialog';
import type { LegendState } from './LegendDialog';
import { MatchedDrawer } from './MatchedDrawer';
import { NameCard } from './NameCard';
import { RackDialog } from './RackDialog';
import { SelectionPill } from './SelectionPill';

afterEach(() => {
    document.body.innerHTML = '';
});

const warehouse = makeWarehouse(2, 4, 3);
const none = buildMatchIndex(warehouse.slots, {
    terms: [],
    state: 'all',
    expiring: false,
    productIds: [],
});
const occupied = warehouse.slots.find((s) => s.state !== 'empty')!;
const empty = warehouse.slots.find((s) => s.state === 'empty')!;

function withProduct(slot: Slot, name: string): Slot {
    return {
        ...slot,
        pallet: { ...slot.pallet!, productName: name, productArName: 'اسم' },
    };
}

describe('NameCard', () => {
    function card(
        slot: Slot | null,
        visible = true,
        inputKind: 'mouse' | 'touch' = 'mouse',
    ) {
        const root = host();
        const slice = fakeSlice({
            slot,
            visible,
            inputKind,
            locale: 'en' as const,
        });
        const intents = { openRack: vi.fn(), show: vi.fn() };
        const view = new NameCard(root, slice, keyI18n(), intents);

        view.mount();

        return { view, slice, intents, root };
    }

    it('describes the pallet: code, state, name, expiry and boxes left', () => {
        const slot = withProduct(
            { ...occupied, state: 'opened' },
            'Basmati Rice',
        );

        slot.pallet!.remainingBoxes = 5;
        slot.pallet!.productBoxesCount = 20;
        slot.pallet!.expirationDate = '2026-12-31';

        const { view, root, intents } = card(slot);

        expect(intents.show).toHaveBeenLastCalledWith(true);
        expect(root.querySelector('.wms-slotcode')?.textContent).toBe(
            slot.code,
        );
        expect(root.querySelector('.wms-card__name')?.textContent).toBe(
            'Basmati Rice',
        );
        expect(root.querySelector('.wms-card__sub')?.textContent).toContain(
            '5 / 20 boxes',
        );
        expect(root.querySelector('.wms-card__meter i')).not.toBeNull();
        expect(root.querySelector('.wms-card__hint')?.textContent).toContain(
            'Enter',
        );
        view.dispose();
    });

    it('says an empty slot is empty, and flags one that is out of service', () => {
        const { view, root } = card({ ...empty, active: false });

        expect(root.querySelector('.wms-card__name')?.textContent).toBe(
            'empty',
        );
        expect(root.querySelector('.wms-tag--off')?.textContent).toBe(
            'outOfService',
        );
        expect(root.querySelector('.wms-card__meter')).toBeNull();
        view.dispose();
    });

    it('stays hidden when there is nothing worth describing', () => {
        const { view, intents } = card(occupied, false);

        expect(intents.show).toHaveBeenLastCalledWith(false);
        view.dispose();
    });

    it('opens the rack on a tap on touch, and not with a mouse', () => {
        const touch = card(occupied, true, 'touch');

        click(touch.root.querySelector('.wms-card'));
        expect(touch.intents.openRack).toHaveBeenCalledWith(occupied);
        expect(touch.root.querySelector('.wms-card__hint')?.textContent).toBe(
            'tapDetails',
        );
        touch.view.dispose();

        const mouse = card(occupied);

        click(mouse.root.querySelector('.wms-card'));
        expect(mouse.intents.openRack).not.toHaveBeenCalled();
        mouse.view.dispose();
    });

    it('shows a product name as text, never as markup', () => {
        const { view, root } = card(
            withProduct(occupied, '<img src=x onerror=alert(1)>'),
        );

        expect(root.querySelector('img')).toBeNull();
        expect(root.querySelector('.wms-card__name')?.textContent).toBe(
            '<img src=x onerror=alert(1)>',
        );
        view.dispose();
    });

    it('uses the Arabic name in Arabic', () => {
        const slice = fakeSlice({
            slot: withProduct(occupied, 'Rice'),
            visible: true,
            inputKind: 'mouse' as const,
            locale: 'ar' as const,
        });
        const root = host();
        const view = new NameCard(root, slice, keyI18n(), {
            openRack: vi.fn(),
            show: vi.fn(),
        });

        view.mount();
        expect(root.querySelector('.wms-card__name')?.textContent).toBe('اسم');
        view.dispose();
    });
});

describe('SelectionPill', () => {
    it('offers rack details and walking there for the picked slot', () => {
        const root = host();
        const slice = fakeSlice({
            slot: occupied as Slot | null,
            locale: 'en' as const,
        });
        const intents = { openRack: vi.fn(), walkHere: vi.fn(), show: vi.fn() };
        const view = new SelectionPill(root, slice, keyI18n(), intents);

        view.mount();

        const [details, walk] = root.querySelectorAll('.wms-selection button');

        expect(intents.show).toHaveBeenLastCalledWith(true);
        click(details);
        click(walk);
        expect(intents.openRack).toHaveBeenCalledWith(occupied);
        expect(intents.walkHere).toHaveBeenCalledWith(occupied);
        expect(
            root.querySelector('.wms-selection__who small')?.textContent,
        ).toContain(`${occupied.row}`);

        slice.set({ slot: null });
        expect(intents.show).toHaveBeenLastCalledWith(false);
        view.dispose();
    });
});

describe('RackDialog', () => {
    function rack(partial = {}) {
        const root = host();
        const slice = fakeSlice({
            rack: { row: 'A', col: 2, level: null } as {
                row: string;
                col: number;
                level: number | null;
            } | null,
            warehouse,
            match: none,
            view: '2d' as const,
            locale: 'en' as const,
            ...partial,
        });
        const intents = {
            close: vi.fn(),
            copyLink: vi.fn(),
            showIn3d: vi.fn(),
            showInPlan: vi.fn(),
        };
        const view = new RackDialog(root, slice, keyI18n(), intents);

        view.mount();

        return { view, slice, intents, root };
    }

    it("shows the column's shelves, top level first", () => {
        const { view, root } = rack();
        const levels = [...root.querySelectorAll('.wms-shelf')].map(
            (s) => (s as HTMLElement).dataset.level,
        );

        expect(levels).toEqual(['3', '2', '1']);
        expect(
            root.querySelector('.wms-backdrop')?.classList.contains('is-open'),
        ).toBe(true);
        expect(root.querySelector('.wms-dialog__title')?.textContent).toContain(
            'A',
        );
        view.dispose();
    });

    it('marks the shelf it was opened from', () => {
        const { view, root } = rack({ rack: { row: 'A', col: 2, level: 2 } });

        expect(
            root
                .querySelector('.wms-shelf.is-target')
                ?.getAttribute('data-level'),
        ).toBe('2');
        view.dispose();
    });

    it('dims shelves that the filter excludes', () => {
        const match = buildMatchIndex(warehouse.slots, {
            terms: ['no-such-product'],
            state: 'all',
            expiring: false,
            productIds: [],
        });
        const { view, root } = rack({ match });

        expect(root.querySelectorAll('.wms-shelf.is-dimmed')).toHaveLength(3);
        view.dispose();
    });

    it('closes from the close button and from the backdrop, not from inside the dialog', () => {
        const { view, root, intents } = rack();

        click(root.querySelector('.wms-dialog__x'));
        expect(intents.close).toHaveBeenCalledTimes(1);

        root.querySelector('.wms-backdrop')!.dispatchEvent(
            new MouseEvent('pointerdown', { bubbles: true }),
        );
        expect(intents.close).toHaveBeenCalledTimes(2);

        root.querySelector('.wms-dialog')!.dispatchEvent(
            new MouseEvent('pointerdown', { bubbles: true }),
        );
        expect(intents.close).toHaveBeenCalledTimes(2);
        view.dispose();
    });

    it('offers 3D from the plan and 2D from 3D, plus a link to copy', () => {
        const plan = rack();

        click(plan.root.querySelectorAll('.wms-dialog__foot button')[1]);
        expect(plan.intents.showIn3d).toHaveBeenCalled();
        click(plan.root.querySelectorAll('.wms-dialog__foot button')[0]);
        expect(plan.intents.copyLink).toHaveBeenCalled();
        plan.view.dispose();

        const three = rack({ view: '3d' });

        click(three.root.querySelectorAll('.wms-dialog__foot button')[1]);
        expect(three.intents.showInPlan).toHaveBeenCalled();
        three.view.dispose();
    });

    it('hides when the rack is cleared and gives the focus back', () => {
        const opener = document.createElement('button');

        document.body.append(opener);
        opener.focus();

        const { view, slice, root } = rack({ rack: null });

        slice.set({ rack: { row: 'A', col: 1, level: null } });
        expect(
            root.querySelector('.wms-backdrop')?.classList.contains('is-open'),
        ).toBe(true);

        slice.set({ rack: null });
        expect(
            root.querySelector('.wms-backdrop')?.classList.contains('is-open'),
        ).toBe(false);
        expect(document.activeElement).toBe(opener);
        view.dispose();
    });

    it('shows the boxes left as a gauge, and tolerates a server that does not say', () => {
        const wh = makeWarehouse(1, 1, 1);

        wh.slots[0].state = 'opened';
        wh.slots[0].pallet = {
            ...(wh.slots[0].pallet ?? occupied.pallet!),
            remainingBoxes: 5,
            productBoxesCount: 20,
        };

        const full = rack({
            warehouse: wh,
            rack: { row: 'A', col: 1, level: null },
        });

        expect(full.root.querySelector('.wms-meter.is-opened')).not.toBeNull();
        expect(full.root.textContent).toContain('5 / 20 boxes');
        full.view.dispose();

        wh.slots[0].pallet = {
            ...wh.slots[0].pallet!,
            remainingBoxes: null,
            productBoxesCount: null,
        };

        const without = rack({
            warehouse: wh,
            rack: { row: 'A', col: 1, level: null },
        });

        expect(without.root.querySelector('.wms-meter')).toBeNull();
        without.view.dispose();
    });
});

describe('LegendDialog', () => {
    it('lists the five colours and names the 3D legend in 3D', () => {
        const root = host();
        const slice = fakeSlice<LegendState>({ open: true, view: '2d' });
        const close = vi.fn();
        const view = new LegendDialog(root, slice, keyI18n(), close);

        view.mount();
        expect(root.querySelectorAll('.wms-legend-row')).toHaveLength(5);
        expect(root.querySelector('.wms-dialog__title')?.textContent).toBe(
            'legendTitle',
        );

        slice.set({ view: '3d' });
        expect(root.querySelector('.wms-dialog__title')?.textContent).toBe(
            'legend3d',
        );

        click(root.querySelector('.wms-dialog__x'));
        expect(close).toHaveBeenCalled();

        slice.set({ open: false });
        expect(
            root.querySelector('.wms-backdrop')?.classList.contains('is-open'),
        ).toBe(false);
        view.dispose();
    });
});

describe('MatchedDrawer', () => {
    function drawer(terms: string[]) {
        const match = buildMatchIndex(warehouse.slots, {
            terms,
            state: 'all',
            expiring: false,
            productIds: [],
        });
        const root = host();
        const slice = fakeSlice({
            open: true,
            warehouse,
            match,
            locale: 'en' as const,
        });
        const intents = { close: vi.fn(), viewOnMap: vi.fn() };
        const view = new MatchedDrawer(root, slice, keyI18n(), intents);

        view.mount();

        return { view, slice, intents, root, match };
    }

    it('groups matches by product, biggest group first, with earliest expiry first inside', () => {
        const { view, root, match } = drawer(['rice']);
        const groups = root.querySelectorAll('.wms-pgroup');

        expect(groups.length).toBeGreaterThan(0);
        expect(root.querySelectorAll('.wms-stat b')[0].textContent).toBe(
            String(
                warehouse.slots.filter(
                    (s) => match.ids.has(s.id) && s.state !== 'empty',
                ).length,
            ),
        );

        click(groups[0].querySelector('.wms-pgroup__head'));

        const dates = [...root.querySelectorAll('.wms-pgroup.is-open .wms-loc')]
            .length;

        expect(dates).toBeGreaterThan(0);
        view.dispose();
    });

    it('narrows to sealed or partial pallets', () => {
        const { view, root } = drawer(['rice']);
        const [, sealed] = root.querySelectorAll('.wms-stat');

        click(sealed);

        const rows = root.querySelectorAll('.wms-pgroup');
        const expected = new Set(
            warehouse.slots
                .filter(
                    (s) => s.state === 'full' && s.haystack.includes('rice'),
                )
                .map((s) => s.pallet!.productId),
        );

        expect(rows).toHaveLength(expected.size);
        view.dispose();
    });

    it('takes you to a slot on the map', () => {
        const { view, root, intents } = drawer(['rice']);

        click(root.querySelector('.wms-pgroup__head'));
        click(root.querySelector('.wms-pgroup.is-open .wms-link'));

        expect(intents.viewOnMap).toHaveBeenCalledTimes(1);
        expect(intents.viewOnMap.mock.calls[0][0].haystack).toContain('rice');
        view.dispose();
    });

    it('marks a product that is no longer sold and a slot that is out of service', () => {
        const hits = warehouse.slots.filter(
            (s) => s.state !== 'empty' && s.haystack.includes('rice'),
        );
        const [first, ...rest] = hits;
        const stored: Slot[] = [
            {
                ...first,
                active: false,
                pallet: { ...first.pallet!, productActive: false },
            },
            ...rest.map((s) => ({
                ...s,
                active: true,
                pallet: { ...s.pallet!, productActive: false },
            })),
        ];
        const marked = {
            ...warehouse,
            byId: new Map(stored.map((s) => [s.id, s])),
            slots: warehouse.slots.map(
                (s) => stored.find((m) => m.id === s.id) ?? s,
            ),
        };
        const { view, root, slice } = drawer(['rice']);

        expect(root.querySelector('.wms-badge--off')).toBeNull();

        slice.set({ warehouse: marked });

        const group = [...root.querySelectorAll('.wms-pgroup')].find(
            (g) =>
                g.querySelector('.wms-pgroup__name b')?.textContent ===
                first.pallet!.productName,
        )!;

        expect(group.querySelector('.wms-badge--off')?.textContent).toBe(
            'productInactive',
        );

        click(group.querySelector('.wms-pgroup__head'));

        const offTags = [...group.querySelectorAll('.wms-loc .wms-tag--off')];

        expect(offTags.map((t) => t.textContent)).toEqual(['outOfService']);
        expect(
            offTags[0].closest('.wms-loc')?.querySelector('.wms-slotcode')
                ?.textContent,
        ).toBe(first.code);
        view.dispose();
    });

    it('says when nothing matches, and is inert while closed', () => {
        const { view, root, slice } = drawer(['zzz']);

        expect(root.querySelector('.wms-drawer__empty')?.textContent).toBe(
            'noMatches',
        );

        slice.set({ open: false });
        expect(root.querySelector('.wms-drawer')?.hasAttribute('inert')).toBe(
            true,
        );
        expect(
            root.querySelector('.wms-drawer')?.classList.contains('is-open'),
        ).toBe(false);
        view.dispose();
    });
});

describe('HelpDialog', () => {
    function help(inputKind: 'mouse' | 'touch' = 'mouse') {
        const root = host();
        const slice = fakeSlice({ open: true, inputKind, aisles: 2 });
        const settings = { ...DEFAULT_SETTINGS };
        const intents = {
            close: vi.fn(),
            settings: () => settings,
            update: vi.fn(),
        };
        const view = new HelpDialog(root, slice, keyI18n(), intents);

        view.mount();

        return { view, slice, intents, root };
    }

    it('lists the keys for the plan, the overview and both walks on a desktop', () => {
        const { view, root } = help();

        expect(
            [...root.querySelectorAll('.wms-hsec__title')].map(
                (h) => h.textContent,
            ),
        ).toEqual([
            'hSec2d',
            'hSecOver',
            'hSecFree',
            'hSecGuided',
            'hSecSettings',
        ]);
        expect(root.querySelector('.wms-hrow kbd')).not.toBeNull();
        view.dispose();
    });

    it('lists gestures, not keys, on a touch screen', () => {
        const { view, root } = help('touch');

        expect(
            [...root.querySelectorAll('.wms-hsec__title')].map(
                (h) => h.textContent,
            ),
        ).toEqual(['hSecTouch', 'hSecSettings']);
        expect(root.querySelector('.wms-hrow kbd')).toBeNull();
        view.dispose();
    });

    it('changes settings as the sliders and switches move, and shows the value', () => {
        const { view, root, intents } = help();
        const sensitivity = root.querySelector<HTMLInputElement>(
            'input[type="range"]',
        )!;

        sensitivity.value = '1.5';
        sensitivity.dispatchEvent(new Event('input'));
        expect(intents.update).toHaveBeenCalledWith({ sens: 1.5 });
        expect(
            sensitivity.parentElement?.querySelector('output')?.textContent,
        ).toBe('1.50×');

        const invert = root.querySelectorAll<HTMLInputElement>(
            'input[type="checkbox"]',
        )[0];

        invert.checked = true;
        invert.dispatchEvent(new Event('change'));
        expect(intents.update).toHaveBeenCalledWith({ invertY: true });
        view.dispose();
    });

    it('starts the sliders at the saved settings and closes', () => {
        const { view, root, intents } = help();
        const fov = root.querySelectorAll<HTMLInputElement>(
            'input[type="range"]',
        )[2];

        expect(fov.value).toBe(String(DEFAULT_SETTINGS.fov));
        expect(
            root.querySelectorAll<HTMLInputElement>('input[type="checkbox"]')[1]
                .checked,
        ).toBe(DEFAULT_SETTINGS.bob);

        click(root.querySelector('.wms-dialog__x'));
        expect(intents.close).toHaveBeenCalled();
        view.dispose();
    });
});
