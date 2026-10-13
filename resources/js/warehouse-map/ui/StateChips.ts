import type { StateFilter } from '../domain/types';
import { el } from '../utils/dom';
import { icon } from './icons';
import { SliceView } from './SliceView';

export interface StateChipsState {
    state: StateFilter;
    expiring: boolean;
}

export interface StateChipsIntents {
    setState(state: StateFilter): void;
    toggleExpiring(): void;
}

const CHIPS: Array<{
    state: StateFilter;
    label: string;
    dot?: string;
    priority?: boolean;
}> = [
    { state: 'all', label: 'stAll' },
    { state: 'full', label: 'stFull', dot: 'success' },
    { state: 'opened', label: 'stOpened', dot: 'warning' },
    { state: 'empty', label: 'stEmpty', dot: 'empty', priority: true },
];

/** The state chips (All / Full / Opened / Empty) and the "expiring within 30 days" chip. */
export class StateChips extends SliceView<StateChipsState> {
    readonly element = el('div', {
        class: 'wms-chips',
        attrs: { role: 'group' },
    });
    private readonly buttons = new Map<StateFilter, HTMLButtonElement>();
    private readonly expiringButton = el('button', {
        class: 'wms-chip wms-chip--optional',
        attrs: { type: 'button' },
    });
    private readonly expiringLabel = el('span');

    constructor(
        slice: ConstructorParameters<typeof SliceView<StateChipsState>>[0],
        i18n: ConstructorParameters<typeof SliceView<StateChipsState>>[1],
        private readonly intents: StateChipsIntents,
    ) {
        super(slice, i18n);
    }

    protected build(): void {
        for (const chip of CHIPS) {
            const button = el(
                'button',
                {
                    class: `wms-chip${chip.priority ? ' wms-chip--optional' : ''}`,
                    attrs: { type: 'button' },
                },
                chip.dot
                    ? el('span', {
                          class: `wms-chip__dot wms-chip__dot--${chip.dot}`,
                      })
                    : null,
                el('span', { data: { label: chip.label } }),
            );

            this.on(button, 'click', () => this.intents.setState(chip.state));
            this.buttons.set(chip.state, button);
            this.element.append(button);
        }

        this.expiringButton.append(icon('clock'), this.expiringLabel);
        this.on(this.expiringButton, 'click', () =>
            this.intents.toggleExpiring(),
        );
        this.element.append(this.expiringButton);
    }

    protected render(): void {
        for (const chip of CHIPS) {
            const button = this.buttons.get(chip.state) as HTMLButtonElement;
            const label = button.querySelector('[data-label]') as HTMLElement;

            label.textContent = this.t(chip.label);
            button.classList.toggle('is-on', this.state.state === chip.state);
            button.setAttribute(
                'aria-pressed',
                String(this.state.state === chip.state),
            );
        }

        this.expiringLabel.textContent = this.t('stExpiring');
        this.expiringButton.classList.toggle('is-on', this.state.expiring);
        this.expiringButton.setAttribute(
            'aria-pressed',
            String(this.state.expiring),
        );
    }
}
