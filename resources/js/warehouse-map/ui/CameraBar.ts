import { ltr, el } from '../utils/dom';
import { icon } from './icons';
import { SliceView } from './SliceView';

export interface CameraBarState {
    rows: ReadonlyArray<{ letter: string }>;
    aisles: number;
    byRow: ReadonlyMap<string, number>;
    filtering: boolean;
    /** Overview, or walking (the aisle chip you entered is lit). */
    mode: 'overview' | 'walk' | 'fly';
    landing: 'overview' | 'walk';
    activeAisle: number | null;
}

export interface CameraBarIntents {
    goOverview(): void;
    enterAisle(aisle: number): void;
}

/** The camera bar of the 3D view: Overview and one chip per aisle, each with its match count on its corner. */
export class CameraBar extends SliceView<CameraBarState> {
    readonly element = el('div', {
        class: 'wms-pill wms-camera-bar',
        attrs: { role: 'group' },
    });
    private overviewChip!: { button: HTMLButtonElement; label: HTMLElement };
    private aisleChips: Array<{
        button: HTMLButtonElement;
        name: HTMLElement;
        count: HTMLElement;
        letters: string;
    }> = [];
    private builtFor: string | null = null;
    private lastActive: string | null = null;

    constructor(
        slice: ConstructorParameters<typeof SliceView<CameraBarState>>[0],
        i18n: ConstructorParameters<typeof SliceView<CameraBarState>>[1],
        private readonly intents: CameraBarIntents,
    ) {
        super(slice, i18n);
    }

    protected build(): void {}

    protected render(): void {
        const { rows, aisles } = this.state;
        const signature = rows.map((r) => r.letter).join(',');

        if (signature !== this.builtFor) {
            this.rebuild(aisles);
            this.builtFor = signature;
        }

        const { byRow, filtering, mode, landing, activeAisle } = this.state;
        const effective = mode === 'fly' ? landing : mode;
        const active = effective === 'overview' ? 'o' : String(activeAisle);

        this.element.setAttribute('aria-label', this.t('aisles'));
        this.overviewChip.label.textContent = this.t('overview');
        this.overviewChip.button.classList.toggle('is-on', active === 'o');

        this.aisleChips.forEach((chip, a) => {
            const hits =
                (byRow.get(rows[a * 2]?.letter) ?? 0) +
                (byRow.get(rows[a * 2 + 1]?.letter) ?? 0);
            const shown = filtering && hits > 0;

            chip.name.replaceChildren(`${this.t('aisle')} `, ltr(chip.letters));
            chip.button.classList.toggle('is-on', active === String(a));
            chip.count.classList.toggle('is-hidden', !shown);
            chip.count.textContent = shown ? String(hits) : '';
        });

        if (active !== this.lastActive) {
            this.lastActive = active;

            const target =
                active === 'o'
                    ? this.overviewChip.button
                    : this.aisleChips[Number(active)]?.button;

            target?.scrollIntoView?.({
                inline: 'nearest',
                block: 'nearest',
                behavior: 'smooth',
            });
        }
    }

    private rebuild(aisles: number): void {
        const { rows } = this.state;
        const label = el('span');
        const overview = el(
            'button',
            { class: 'wms-chip', attrs: { type: 'button' } },
            icon('map'),
            label,
        );

        this.on(overview, 'click', () => this.intents.goOverview());
        this.overviewChip = { button: overview, label };
        this.aisleChips = [];
        this.element.replaceChildren(overview);

        for (let a = 0; a < aisles; a++) {
            const letters = [rows[a * 2]?.letter, rows[a * 2 + 1]?.letter]
                .filter(Boolean)
                .join(' - ');
            const name = el('span');
            const count = el('span', { class: 'wms-chip__count is-hidden' });
            const button = el(
                'button',
                { class: 'wms-chip', attrs: { type: 'button' } },
                icon('cam'),
                name,
                count,
            );

            this.on(button, 'click', () => this.intents.enterAisle(a));
            this.aisleChips.push({ button, name, count, letters });
            this.element.append(button);
        }
    }
}
