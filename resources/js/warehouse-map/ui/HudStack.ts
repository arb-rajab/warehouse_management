import { View } from '../core/View';
import { el } from '../utils/dom';

/** Bottom to top: the selection, the details card, the mouse notice, the welcome and the hint. */
export const HUD_SLOTS = [
    'selection',
    'details',
    'capture',
    'welcome',
    'hint',
    'touchhint',
] as const;
export type HudSlot = (typeof HUD_SLOTS)[number];

/**
 * Everything that floats at the bottom centre (selection, details card, mouse
 * notice, welcome, hints) is rendered in this ONE flex column, so two of them
 * can never overlap. Overlays that are information, not controls, take no
 * pointer events at all, so the map under them keeps its cursor and its drag.
 */
export class HudStack extends View {
    readonly element = el('div', { class: 'wms-stack' });
    private readonly slots = new Map<HudSlot, HTMLElement>();

    constructor(private readonly host: HTMLElement) {
        super();

        for (const name of HUD_SLOTS) {
            const slot = el('div', {
                class: `wms-stack__item wms-stack__item--${name}`,
                data: { slot: name },
            });

            this.slots.set(name, slot);
            this.element.append(slot);
        }
    }

    mount(): void {
        this.host.append(this.element);
    }

    protected onDispose(): void {
        this.element.remove();
    }

    /** The element a view fills with its content. */
    slot(name: HudSlot): HTMLElement {
        return this.slots.get(name) as HTMLElement;
    }

    show(name: HudSlot, visible: boolean): void {
        const slot = this.slot(name);

        slot.classList.toggle('is-shown', visible);
    }
}
