import { el } from '../utils/dom';
import { SliceView } from './SliceView';

export interface FloorState {
    levels: number;
    floor: number | null;
    filtering: boolean;
    total: number;
    byLevel: ReadonlyMap<number, number>;
}

/** The floor strip: All, then the levels top first; with a filter on, each shows how many matches it holds. */
export class FloorSelector extends SliceView<FloorState> {
    readonly element = el('div', {
        class: 'wms-floors wms-hud wms-pill',
        attrs: { role: 'group' },
    });
    private readonly title = el('div', { class: 'wms-floors__title' });
    private chips: Array<{
        level: number | null;
        button: HTMLButtonElement;
        label: HTMLElement;
        count: HTMLElement;
    }> = [];
    private builtLevels = -1;

    constructor(
        private readonly host: HTMLElement,
        slice: ConstructorParameters<typeof SliceView<FloorState>>[0],
        i18n: ConstructorParameters<typeof SliceView<FloorState>>[1],
        private readonly setFloor: (floor: number | null) => void,
    ) {
        super(slice, i18n);
    }

    protected build(): void {
        this.host.append(this.element);
    }

    protected onDispose(): void {
        this.element.remove();
    }

    protected render(): void {
        const { levels, floor, filtering, total, byLevel } = this.state;

        if (levels !== this.builtLevels) {
            this.rebuild(levels);
        }

        this.title.textContent = this.t('floors');
        this.element.setAttribute('aria-label', this.t('floors'));
        this.element.classList.toggle('has-filter', filtering);

        for (const chip of this.chips) {
            const count =
                chip.level === null ? total : (byLevel.get(chip.level) ?? 0);

            chip.label.textContent =
                chip.level === null ? this.t('allFloors') : String(chip.level);
            chip.button.classList.toggle('is-on', floor === chip.level);
            chip.button.classList.toggle('is-hit', filtering && count > 0);
            chip.button.setAttribute(
                'aria-pressed',
                String(floor === chip.level),
            );
            // the count holds its place when there is no filter, so nothing shifts when one starts
            chip.count.textContent = filtering ? String(count) : ' ';
        }
    }

    private rebuild(levels: number): void {
        this.builtLevels = levels;
        this.chips = [];
        this.element.replaceChildren(this.title);

        const levelValues: Array<number | null> = [null];

        for (let level = levels; level >= 1; level--) {
            levelValues.push(level);
        }

        for (const level of levelValues) {
            const label = el('span');
            const count = el('small', { text: ' ' });
            const button = el(
                'button',
                {
                    class: 'wms-chip wms-chip--floor',
                    attrs: { type: 'button' },
                },
                label,
                count,
            );

            this.on(button, 'click', () => this.setFloor(level));
            this.chips.push({ level, button, label, count });
            this.element.append(button);
        }
    }
}
