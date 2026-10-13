import { View } from '../core/View';
import type { PadAction, TouchElements } from '../input/TouchAdapter';
import { el } from '../utils/dom';
import { icon } from './icons';
import type { IconName } from './icons';
import type { Translator } from './SliceView';

const PAD: Array<{
    action: PadAction;
    icon: IconName;
    label: string;
    kind: 'level' | 'turn' | 'step';
}> = [
    { action: 'up', icon: 'up', label: 'gLevelUp', kind: 'level' },
    { action: 'tl', icon: 'rotl', label: 'turnLeft', kind: 'turn' },
    { action: 'fw', icon: 'up', label: 'moveForward', kind: 'step' },
    { action: 'tr', icon: 'rotr', label: 'turnRight', kind: 'turn' },
    { action: 'down', icon: 'down', label: 'gLevelDown', kind: 'level' },
    { action: 'sl', icon: 'back', label: 'moveLeft', kind: 'step' },
    { action: 'bk', icon: 'down', label: 'moveBackward', kind: 'step' },
    { action: 'sr', icon: 'right', label: 'moveRight', kind: 'step' },
];

/**
 * The on-screen steering for touch: the floating joystick (it appears under
 * the left thumb), the lift buttons of free walk and the 4 x 2 pad of guided
 * mode. It only builds the controls; `TouchAdapter` makes them do things.
 */
export class TouchControls extends View {
    readonly layer = el('div', { class: 'wms-touch wms-touch-only' });
    readonly pad = el('div', { class: 'wms-pad wms-hud wms-touch-only' });
    private readonly stick = el(
        'div',
        { class: 'wms-stick' },
        el('i', { class: 'wms-stick__knob' }),
    );
    private readonly liftUp = el(
        'button',
        { class: 'wms-pill wms-lift__btn', attrs: { type: 'button' } },
        icon('up'),
    );
    private readonly liftDown = el(
        'button',
        { class: 'wms-pill wms-lift__btn', attrs: { type: 'button' } },
        icon('down'),
    );
    private readonly padButtons: TouchElements['padButtons'] = [];
    private readonly labelled: Array<{ element: HTMLElement; key: string }> =
        [];

    constructor(
        private readonly host: HTMLElement,
        private readonly canvas: HTMLElement,
        private readonly i18n: Translator,
    ) {
        super();
    }

    /** What the touch adapter binds to. */
    get elements(): TouchElements {
        return {
            canvas: this.canvas,
            stick: this.stick,
            liftUp: this.liftUp,
            liftDown: this.liftDown,
            padButtons: this.padButtons,
        };
    }

    mount(): void {
        this.layer.append(
            this.stick,
            el('div', { class: 'wms-lift' }, this.liftUp, this.liftDown),
        );
        this.labelled.push(
            { element: this.liftUp, key: 'wUp' },
            { element: this.liftDown, key: 'wDown' },
        );

        const grid = el('div', { class: 'wms-pad__grid' });

        for (const item of PAD) {
            const button = el(
                'button',
                {
                    class: `wms-pad__btn wms-pad__btn--${item.kind}`,
                    attrs: { type: 'button' },
                },
                icon(item.icon),
            );

            this.padButtons.push({ element: button, action: item.action });
            this.labelled.push({ element: button, key: item.label });
            grid.append(button);
        }

        this.pad.append(grid);
        this.host.append(this.layer, this.pad);
        this.relabel();
        this.own(this.i18n.onChange(() => this.relabel()));
    }

    protected onDispose(): void {
        this.layer.remove();
        this.pad.remove();
    }

    private relabel(): void {
        for (const { element, key } of this.labelled) {
            element.setAttribute('aria-label', this.i18n.t(key));
            element.title = this.i18n.t(key);
        }
    }
}
